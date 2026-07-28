<?php

namespace App\Imports\LabManagement\TestGroups;

use App\Models\TestGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa grupos de pruebas desde .xlsx/.csv.
 *
 * Columnas (ver TestGroupsImportTemplate):
 *   code        obligatorio, max 40, clave natural
 *   name        obligatorio, max 100
 *   sort_order  opcional, entero
 *
 * LA CLAVE ES `code`, NO EL NOMBRE (el scaffold buscaba por nombre porque
 * clonaba un catálogo). Además el código de esta tabla es único en TODO el
 * sistema, no por workspace: por eso, cuando el código ya existe pero pertenece
 * a otro workspace, la fila se rechaza con un mensaje en vez de dejar que
 * reviente el índice único y se caiga la importación completa.
 *
 * El import NO maneja is_active: toda alta nace activa.
 *
 * Modes: 'create_only' | 'update_or_create'
 *
 * Todo va en transacción. dryRun=true → rollback al final (vista previa).
 */
class TestGroupsImport implements ToCollection, WithHeadingRow
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

    /** Count de grupos visibles para el actor (pre-import). */
    protected int $currentCount;

    public function __construct(
        protected string $mode = 'update_or_create',
        protected bool $dryRun = false,
    ) {
        $user = Auth::user();

        if ($user && $user->tenant) {
            $this->maxRecords = $user->tenant->maxRecordsPerModule();
        } else {
            $this->maxRecords = PHP_INT_MAX;
        }

        $this->currentCount = TestGroup::count();
    }

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            $seenInFileByCode = [];
            $newRecordsCount  = 0;
            $tenantId = Auth::user()?->tenant_id;

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2; // +2 = fila de encabezado + índice desde 0.

                $code = $this->str($row['code'] ?? null);
                if ($code === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('test_groups.code_required'),
                        'value'   => '—',
                    ];
                    continue;
                }
                if (mb_strlen($code) > 40) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_code_too_long'),
                        'value'   => mb_substr($code, 0, 30) . '…',
                    ];
                    continue;
                }

                $codeKey = mb_strtolower($code);
                if (isset($seenInFileByCode[$codeKey])) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFileByCode[$codeKey]]),
                        'value'   => $code,
                    ];
                    continue;
                }
                $seenInFileByCode[$codeKey] = $absoluteRow;

                $name = $this->str($row['name'] ?? null);
                if ($name === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_required'),
                        'value'   => $code,
                    ];
                    continue;
                }
                if (mb_strlen($name) > 100) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_too_long'),
                        'value'   => mb_substr($name, 0, 60) . '…',
                    ];
                    continue;
                }

                $sortOrder = $this->int($row['sort_order'] ?? null);

                // El código es único GLOBAL: hay que mirar TODA la tabla, no
                // solo lo que el actor ve. Si el dueño es otro workspace no se
                // puede ni actualizar ni crear.
                $existing = $this->findByCodeAnywhere($code);

                // Dueño de otro workspace, o un registro en la papelera: en los
                // dos casos el índice único ya tiene ese código tomado y no se
                // puede crear. Se rechaza la fila con mensaje en vez de dejar
                // que reviente el insert.
                $ownedByOther = $existing
                    && $tenantId !== null
                    && $existing->tenant_id !== null
                    && $existing->tenant_id !== $tenantId;

                if ($ownedByOther || ($existing && $existing->trashed())) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_code_duplicate', ['value' => $code]),
                        'value'   => $code,
                    ];
                    continue;
                }

                if ($existing) {
                    // Registro BLOQUEADO (Lockable): el import no lo pisa.
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

                    $patch = [];
                    if ((string) $existing->name !== $name) {
                        $patch['name'] = $name;
                    }
                    if ($sortOrder !== null && (int) $existing->sort_order !== $sortOrder) {
                        $patch['sort_order'] = $sortOrder;
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
                    if ($this->maxRecords > 0 && $this->maxRecords !== PHP_INT_MAX) {
                        if (($this->currentCount + $newRecordsCount) >= $this->maxRecords) {
                            $this->errors[] = [
                                'row'     => $absoluteRow,
                                'message' => __('plans.limit_records_reached', ['max' => $this->maxRecords]),
                                'value'   => $code,
                            ];
                            continue;
                        }
                    }

                    TestGroup::create([
                        'code'       => $code,
                        'name'       => $name,
                        'sort_order' => $sortOrder ?? 0,
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

    /** Trim → null si queda vacío. */
    protected function str(mixed $value): ?string
    {
        if ($value === null) return null;
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    /** Entero o null. Un texto no numérico se trata como vacío, no como 0. */
    protected function int(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) return null;
        return (int) $value;
    }

    /**
     * Búsqueda por código en TODA la tabla (el único es global, no per-tenant).
     * Se saltean los global scopes a propósito: si no, un código de otro
     * workspace parecería libre y el insert reventaría el índice único
     * arrastrando toda la importación.
     */
    protected function findByCodeAnywhere(string $code): ?TestGroup
    {
        return TestGroup::withoutGlobalScopes()
            ->whereRaw('LOWER(test_groups.code) = LOWER(?)', [trim($code)])
            ->first();
    }
}
