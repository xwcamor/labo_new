<?php

namespace App\Imports\BusinessManagement\Instruments;

use App\Models\Instrument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa instrumentos desde .xlsx/.csv.
 *
 * Columnas (ver InstrumentsImportTemplate):
 *   name                     obligatorio, clave natural, único por workspace
 *                            (es el código de calibración: PP-LA-01C-100)
 *   description              opcional — el tipo de equipo ("Bureta"), SE REPITE
 *                            a propósito entre equipos distintos
 *   brand, model, serial     opcionales, max 100
 *   calibrated_at            opcional, fecha
 *   calibration_due_at       opcional, fecha (no anterior a calibrated_at)
 *   calibration_certificate  opcional, max 150
 *   location                 opcional, max 150
 *
 * LA CLAVE ES EL NOMBRE, que es el código de calibración — NO la descripción.
 * Buscar por la descripción fusionaría las tres buretas del laboratorio en un
 * solo registro y les pisaría la calibración entre sí. Por eso también hay una
 * sola capa de dedup en archivo (por nombre) y no dos.
 *
 * El import NO maneja is_active: toda alta nace activa. El estado se gestiona
 * desde la interfaz / acciones masivas.
 *
 * Modes: 'create_only' | 'update_or_create'
 *
 * instruments es PER-TENANT: el import se limita al workspace del actor vía el
 * global scope (Instrument::create autorellena su tenant).
 *
 * Protección de duplicados (per-tenant):
 *   1. En archivo: nombre normalizado (trim+lower) detecta repetidos del mismo
 *      envío.
 *   2. En aplicación: búsqueda case-insensitive contra la tabla.
 *   3. En base: índice único parcial (tenant_id, LOWER(name)).
 *
 * Enforce `Tenant::maxRecordsPerModule()`: las filas que crearían un registro
 * por encima del límite del plan se marcan como error; las que actualizan uno
 * existente no cuentan.
 *
 * Todo va en transacción. dryRun=true → rollback al final (vista previa).
 */
class InstrumentsImport implements ToCollection, WithHeadingRow
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

    /** Count de instrumentos del tenant del actor (pre-import). */
    protected int $currentCount;

    public function __construct(
        protected string $mode = 'update_or_create',
        protected bool $dryRun = false,
    ) {
        $user = Auth::user();

        // Limite del plan del usuario. Sin tenant/plan → sin limite.
        if ($user && $user->tenant) {
            $this->maxRecords = $user->tenant->maxRecordsPerModule();
        } else {
            $this->maxRecords = PHP_INT_MAX;
        }

        $this->currentCount = Instrument::count();
    }

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            $seenInFileByName = [];
            $newRecordsCount  = 0;

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2; // +2 = fila de encabezado + índice desde 0.

                $name = $this->str($row['name'] ?? null);
                if ($name === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_required'),
                        'value'   => '—',
                    ];
                    continue;
                }
                if (mb_strlen($name) > 255) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_too_long'),
                        'value'   => mb_substr($name, 0, 60) . '…',
                    ];
                    continue;
                }

                $nameKey = mb_strtolower($name);
                if (isset($seenInFileByName[$nameKey])) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFileByName[$nameKey]]),
                        'value'   => $name,
                    ];
                    continue;
                }
                $seenInFileByName[$nameKey] = $absoluteRow;

                $calibratedAt = $this->date($row['calibrated_at'] ?? null);
                $dueAt        = $this->date($row['calibration_due_at'] ?? null);

                // Un certificado que vence antes de emitirse es un error de
                // tipeo; si entra, el equipo queda "vencido" para siempre sin
                // que se entienda por qué.
                if ($calibratedAt && $dueAt && $dueAt < $calibratedAt) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('instruments.due_before_calibrated'),
                        'value'   => $name,
                    ];
                    continue;
                }

                $attributes = [
                    'description'             => $this->str($row['description'] ?? null, 2000),
                    'brand'                   => $this->str($row['brand'] ?? null, 100),
                    'model'                   => $this->str($row['model'] ?? null, 100),
                    'serial'                  => $this->str($row['serial'] ?? null, 100),
                    'calibrated_at'           => $calibratedAt,
                    'calibration_due_at'      => $dueAt,
                    'calibration_certificate' => $this->str($row['calibration_certificate'] ?? null, 150),
                    'location'                => $this->str($row['location'] ?? null, 150),
                ];

                $existing = $this->findExistingByName($name);

                if ($existing) {
                    // Registro BLOQUEADO (Lockable): el import no lo pisa. Se
                    // reporta como saltado para que se sepa que existe pero
                    // está congelado.
                    if ($existing->is_locked) {
                        $this->skipped++;
                        $this->preview[] = [
                            'row'       => $absoluteRow,
                            'name'      => $name,
                            'is_active' => (bool) $existing->is_active,
                            'action'    => 'skipped',
                            'reason'    => 'locked',
                        ];
                        continue;
                    }

                    if ($this->mode === 'create_only') {
                        $this->skipped++;
                        $this->preview[] = [
                            'row'       => $absoluteRow,
                            'name'      => $name,
                            'is_active' => (bool) $existing->is_active,
                            'action'    => 'skipped',
                        ];
                        continue;
                    }

                    // Solo se tocan los campos que traen valor: una planilla de
                    // recalibración suele venir con el código y las fechas
                    // nuevas, y vaciar la marca o la ubicación por columnas en
                    // blanco sería perder datos ya cargados.
                    $patch = [];
                    foreach ($attributes as $key => $value) {
                        if ($value === null) continue;
                        $current = $existing->{$key};
                        if ($current instanceof \DateTimeInterface) {
                            $current = $current->format('Y-m-d');
                        }
                        if ((string) $current !== (string) $value) {
                            $patch[$key] = $value;
                        }
                    }
                    if (!empty($patch)) {
                        $existing->fill($patch)->save();
                    }

                    $this->updated++;
                    $this->preview[] = [
                        'row'       => $absoluteRow,
                        'name'      => $name,
                        'is_active' => (bool) $existing->is_active,
                        'action'    => 'updated',
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

                    Instrument::create($attributes + [
                        'name'       => $name,
                        'is_active'  => true,
                        'created_by' => Auth::id(),
                        // tenant_id lo autorellena BelongsToTenantOrGlobal;
                        // el slug lo auto-genera el modelo en `creating`.
                    ]);

                    $newRecordsCount++;
                    $this->created++;
                    $this->preview[] = [
                        'row'       => $absoluteRow,
                        'name'      => $name,
                        'is_active' => true,
                        'action'    => 'created',
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

    /** Trim → null si queda vacío, recortando al largo de la columna. */
    protected function str(mixed $value, ?int $max = null): ?string
    {
        if ($value === null) return null;
        $text = trim((string) $value);
        if ($text === '') return null;

        return $max !== null ? mb_substr($text, 0, $max) : $text;
    }

    /**
     * Fecha de la planilla → 'Y-m-d'. Excel puede entregarla como número de
     * serie o como texto según cómo esté formateada la celda; las dos se
     * aceptan porque el laboratorio manda la planilla que ya tiene, no una
     * hecha para el importador.
     */
    protected function date(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return \Carbon\Carbon::parse(trim((string) $value))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Búsqueda por código, case-insensitive y per-tenant (el global scope de
     * BelongsToTenantOrGlobal limita al workspace del actor).
     */
    protected function findExistingByName(string $name): ?Instrument
    {
        return Instrument::query()
            ->whereRaw('LOWER(instruments.name) = LOWER(?)', [trim($name)])
            ->first();
    }
}
