<?php

namespace App\Imports\BusinessManagement\Equipment;

use App\Models\Equipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Imports Equipment from .xlsx/.csv.
 *
 * Columnas:
 *   - name     (obligatoria, max 255) — cómo llama el cliente al equipo
 *   - customer (obligatoria)          — el cliente dueño, por nombre exacto
 *   - serial   (opcional)             — número de serie de la chapa
 *   - tag      (opcional)             — código en planta (TR-01)
 *
 * La columna `code` del scaffold NO existe: `equipment` no tiene esa columna.
 * Importar por ella escribía un campo inexistente y, peor, el importador creaba
 * los equipos SIN CLIENTE — y un equipo sin cliente no aparece en ninguna
 * recepción, así que el lote entero quedaba invisible.
 *
 * El import NO maneja is_active: toda alta nace activa (coherente con clientes). El estado se gestiona desde la UI / bulk actions.
 *
 * Modes: 'create_only' | 'update_or_create'
 *
 * equipment es PER-TENANT: el import scope-a por tenant_id via el global scope de
 * BelongsToTenant (Equipment::create autorellena el tenant del actor).
 *
 * 3 capas contra duplicados (per-tenant):
 *   1. En el archivo: la chapa (serie+tag) normalizada, y si no hay chapa, el
 *      par cliente+nombre. Dos clientes SÍ pueden tener un "Transformador
 *      Principal" cada uno; el mismo cliente, no.
 *   2. En la aplicación: búsqueda insensible a mayúsculas y acentos.
 *   3. En la base: el índice único parcial (tenant, serie, tag).
 *
 * Enforce `Tenant::maxRecordsPerModule()`:
 *   - Si el plan del usuario tiene limite, contamos cuantos equipment hay HOY +
 *     cuantos vamos a CREAR. Si supera, marcamos las filas excedentes como
 *     errores (no se crean). Las filas que actualizan existentes no cuentan
 *     contra el limite. El conteo es global (catalogo unico).
 *
 * Todo va en transaccion. dryRun=true â†’ rollback al final (preview UI).
 */
class EquipmentImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /** @var array<int, array{row:int, message:string, value?:string}> */
    public array $errors = [];

    /** @var array<int, array{row:int, name:string, is_active:bool, action:string}> */
    public array $preview = [];
    /** Limite de records del plan (>0 = aplica; 0 o PHP_INT_MAX = ilimitado). */
    protected int $maxRecords;

    /** Count de equipment del tenant del actor (pre-import). */
    protected int $currentCount;

    /** Cliente resuelto por nombre, para no repetir la consulta por fila. */
    protected array $clientesCache = [];

    public function __construct(
        protected string $mode = 'update_or_create',
        protected bool $dryRun = false,
    ) {
        $user = Auth::user();

        // Limite del plan del usuario. Sin tenant/plan â†’ sin limite.
        if ($user && $user->tenant) {
            $this->maxRecords = $user->tenant->maxRecordsPerModule();
        } else {
            $this->maxRecords = PHP_INT_MAX;
        }

        // Snapshot del count actual del tenant (global scope de BelongsToTenant).
        $this->currentCount = Equipment::count();
    }

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            // Capa 1: duplicados dentro del propio archivo. Una sola tabla:
            // la clave es la chapa cuando la hay, y cliente+nombre cuando no.
            $seenInFile = [];
            $newRecordsCount = 0; // contador de filas que crearian un nuevo equipment

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2; // +2 = header (1) + indexacion desde 0.

                $name = $this->normalizeName($row['name'] ?? null);
                if ($name === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_required'),
                        'value'   => 'â€”',
                    ];
                    continue;
                }
                if (mb_strlen($name) > 255) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_too_long'),
                        'value'   => mb_substr($name, 0, 60) . 'â€¦',
                    ];
                    continue;
                }

                // El CLIENTE dueño del equipo. Obligatorio: un equipo sin
                // cliente no aparece en ninguna recepción, así que importarlo
                // sin él es cargar un lote invisible.
                $customerId = $this->resolveCustomer($row['customer'] ?? null);
                if ($customerId === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('equipment.import_customer_unknown'),
                        'value'   => trim((string) ($row['customer'] ?? '—')),
                    ];
                    continue;
                }

                // La chapa: serie y tag.
                $serial = $this->normalizeCode($row['serial'] ?? null);
                $tag    = $this->normalizeCode($row['tag'] ?? null);

                // Capa 1 — duplicado dentro del mismo archivo. Con chapa, la
                // clave es la chapa; sin chapa, el par cliente+nombre (dos
                // clientes distintos SÍ pueden repetir el nombre).
                $claveArchivo = ($serial !== null && $tag !== null)
                    ? 'chapa:' . mb_strtolower($serial . '|' . $tag)
                    : 'nombre:' . $customerId . '|' . $this->normalizeKey($name);

                if (isset($seenInFile[$claveArchivo])) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFile[$claveArchivo]]),
                        'value'   => $serial !== null ? $serial . ' / ' . $tag : $name,
                    ];
                    continue;
                }
                $seenInFile[$claveArchivo] = $absoluteRow;

                // Capa 2 — contra lo que ya está cargado.
                $existing = $this->findExisting($customerId, $name, $serial, $tag);

                if ($existing) {
                    // Registro BLOQUEADO (Lockable): el import no lo pisa. Se reporta
                    // como saltado para que el usuario sepa que existe pero está
                    // congelado (hay que desbloquearlo para actualizarlo).
                    if ($existing->is_locked) {
                        $this->skipped++;
                        $this->preview[] = [
                            'row'         => $absoluteRow,
                            'name'        => $name,
                            'is_active'   => (bool) $existing->is_active,
                            'action'      => 'skipped',
                            'reason'      => 'locked',
                        ];
                        continue;
                    }

                    if ($this->mode === 'create_only') {
                        $this->skipped++;
                        $this->preview[] = [
                            'row'         => $absoluteRow,
                            'name'        => $name,
                            'is_active'   => (bool) $existing->is_active,
                            'action'      => 'skipped',
                        ];
                        continue;
                    }

                    // Solo tocar campos que cambian (evita audit logs vacíos). El
                    // import NO gestiona el estado (eso va por la UI / bulk).
                    $patch = [];
                    if ($existing->name !== $name)                  $patch['name'] = $name;
                    if ($serial !== null && $existing->serial !== $serial) $patch['serial'] = $serial;
                    if ($tag !== null && $existing->tag !== $tag)          $patch['tag'] = $tag;
                    if (!empty($patch)) {
                        $existing->fill($patch)->save();
                    }

                    $this->updated++;
                    $this->preview[] = [
                        'row'         => $absoluteRow,
                        'name'        => $name,
                        'is_active'   => (bool) $existing->is_active,
                        'action'      => 'updated',
                    ];
                } else {
                    // Antes de crear, validar limite del plan.
                    if ($this->maxRecords > 0 && $this->maxRecords !== PHP_INT_MAX) {
                        if (($this->currentCount + $newRecordsCount) >= $this->maxRecords) {
                            $this->errors[] = [
                                'row'     => $absoluteRow,
                                'message' => __('plans.limit_records_reached', ['max' => $this->maxRecords]),
                                'value'   => $name,
                            ];
                            continue;
                        }
                    }

                    // Las altas nacen activas. El import no importa registros inactivos (coherente con clientes/oil_types): el estado se gestiona desde la UI / bulk actions.
                    Equipment::create([
                        'name'        => $name,
                        'customer_id' => $customerId,
                        'serial'      => $serial,
                        'tag'         => $tag,
                        'is_active'   => true,
                        'created_by'  => Auth::id(),
                        // tenant_id lo autorellena BelongsToTenant (tenant del actor);
                        // el slug lo auto-genera el modelo en `creating`.
                    ]);

                    $newRecordsCount++;
                    $this->created++;
                    $this->preview[] = [
                        'row'         => $absoluteRow,
                        'name'        => $name,
                        'is_active'   => true,
                        'action'      => 'created',
                    ];
                }
            }

            if ($this->dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function summary(): array
    {
        return [
            'created'      => $this->created,
            'updated'      => $this->updated,
            'skipped'      => $this->skipped,
            'error_count'  => count($this->errors),
            'total_rows'   => $this->created + $this->updated + $this->skipped + count($this->errors),
            'errors'       => array_slice($this->errors, 0, 50),
            'preview'      => array_slice($this->preview, 0, 100),
            'dry_run'      => $this->dryRun,
        ];
    }

    protected function normalizeName(mixed $value): ?string
    {
        if ($value === null) return null;
        $name = trim((string) $value);
        return $name === '' ? null : $name;
    }

    /** Trim → null si vacío. Vale para la serie y para el tag. */
    protected function normalizeCode(mixed $value): ?string
    {
        if ($value === null) return null;
        $code = trim((string) $value);
        return $code === '' ? null : $code;
    }

    /**
     * El cliente, por nombre exacto (insensible a mayúsculas y acentos).
     *
     * No se crea al vuelo: si el nombre no coincide con ningún cliente, la fila
     * se rechaza. Crear clientes desde el importador de equipos es cómo una
     * empresa termina cargada tres veces con tres grafías distintas.
     */
    protected function resolveCustomer(mixed $valor): ?int
    {
        $nombre = trim((string) $valor);

        if ($nombre === '') {
            return null;
        }

        if (isset($this->clientesCache[$nombre])) {
            return $this->clientesCache[$nombre];
        }

        $q = \App\Models\Customer::query();
        DB::getDriverName() === 'pgsql'
            ? $q->whereRaw('unaccent(LOWER(customers.name)) = unaccent(LOWER(?))', [$nombre])
            : $q->whereRaw('LOWER(customers.name) = LOWER(?)', [$nombre]);

        return $this->clientesCache[$nombre] = $q->value('id');
    }

    /**
     * El equipo que ya está cargado, si está.
     *
     * Por la CHAPA primero (serie + tag), que es la identidad real y la que
     * tiene índice único. Sin chapa se cae al par cliente + nombre: acotado al
     * cliente, porque el nombre solo no identifica a nadie —el sistema anterior
     * emparejaba por nombre suelto y por eso terminaba actualizando el
     * transformador de otra empresa—.
     */
    protected function findExisting(int $customerId, string $name, ?string $serial, ?string $tag): ?Equipment
    {
        if ($serial !== null && $tag !== null) {
            $porChapa = Equipment::query()
                ->whereRaw('LOWER(serial) = LOWER(?)', [$serial])
                ->whereRaw('LOWER(tag) = LOWER(?)', [$tag])
                ->first();

            if ($porChapa) {
                return $porChapa;
            }
        }

        $q = Equipment::query()->where('customer_id', $customerId);

        DB::getDriverName() === 'pgsql'
            ? $q->whereRaw('unaccent(LOWER(equipment.name)) = unaccent(LOWER(?))', [$name])
            : $q->whereRaw('LOWER(equipment.name) = LOWER(?)', [$name]);

        return $q->first();
    }
    /** Lowercase + strip accents (iconv) â€” mismo pattern que el DB-level layer 2. */
    protected function normalizeKey(string $name): string
    {
        $lower    = mb_strtolower(trim($name));
        $stripped = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
        return $stripped !== false ? $stripped : $lower;
    }

    /**
     * Lookup case + accent insensitive (Postgres unaccent / fallback LOWER).
     * Per-tenant: el global scope de BelongsToTenant limita al tenant del actor.
     */
    protected function findExistingByNameInsensitive(string $name): ?Equipment
    {
        $isPgsql = DB::getDriverName() === 'pgsql';
        $query   = Equipment::query();

        if ($isPgsql) {
            $query->whereRaw('unaccent(LOWER(equipment.name)) = unaccent(LOWER(?))', [$name]);
        } else {
            $query->whereRaw('LOWER(equipment.name) = LOWER(?)', [$name]);
        }

        return $query->first();
    }
}
