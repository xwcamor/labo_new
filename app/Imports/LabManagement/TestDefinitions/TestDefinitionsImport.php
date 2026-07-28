<?php

namespace App\Imports\LabManagement\TestDefinitions;

use App\Models\TestDefinition;
use App\Models\TestGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa pruebas desde .xlsx/.csv (ver TestDefinitionsImportTemplate).
 *
 * LA CLAVE ES `code`, NO EL NOMBRE, y ese código es único en TODO el sistema
 * (no por workspace): cuando ya está tomado por otro workspace o por un
 * registro en la papelera, la fila se rechaza con mensaje en vez de dejar que
 * reviente el índice único y se caiga la importación completa.
 *
 * EL GRUPO SE REFERENCIA POR CÓDIGO (`group_code`), no por id: los id no son
 * estables entre instalaciones y una planilla de números no la revisa nadie.
 * Un código de grupo que no existe NO crea el grupo: se avisa y la prueba queda
 * sin clasificar, porque inventar filas de catálogo desde una planilla es como
 * se llenan de basura.
 *
 * NO importa las columnas de la hoja de trabajo (`test_fields`): son otra tabla
 * y las trae `php artisan import:legacy-tests`. Tampoco toca `legacy_id`, que es
 * del importador del sistema viejo.
 *
 * El import NO maneja is_active: toda alta nace activa.
 *
 * Modes: 'create_only' | 'update_or_create'
 *
 * Todo va en transacción. dryRun=true → rollback al final (vista previa).
 */
class TestDefinitionsImport implements ToCollection, WithHeadingRow
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

    /** Count de pruebas visibles para el actor (pre-import). */
    protected int $currentCount;

    /** Cache code(minúscula) → id de grupo, para no consultar por fila. */
    protected array $groupIdByCode = [];

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

        $this->currentCount = TestDefinition::count();

        foreach (TestGroup::query()->get(['id', 'code']) as $g) {
            $this->groupIdByCode[mb_strtolower((string) $g->code)] = $g->id;
        }
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
                        'message' => __('test_definitions.code_required'),
                        'value'   => '—',
                    ];
                    continue;
                }
                if (mb_strlen($code) > 60) {
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
                if (mb_strlen($name) > 150) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_too_long'),
                        'value'   => mb_substr($name, 0, 60) . '…',
                    ];
                    continue;
                }

                // Grupo por código. Si no existe se avisa y la prueba entra sin
                // grupo: no se inventan filas de catálogo desde una planilla.
                $groupId   = null;
                $groupCode = $this->str($row['group_code'] ?? null);
                if ($groupCode !== null) {
                    $groupId = $this->groupIdByCode[mb_strtolower($groupCode)] ?? null;
                    if ($groupId === null) {
                        $this->errors[] = [
                            'row'     => $absoluteRow,
                            'message' => __('test_definitions.group_invalid'),
                            'value'   => $groupCode,
                        ];
                    }
                }

                $attributes = [
                    'name'               => $name,
                    'test_group_id'      => $groupId,
                    'description'        => $this->str($row['description'] ?? null),
                    'container'          => $this->str($row['container'] ?? null, 100),
                    'chart_unit'         => $this->str($row['chart_unit'] ?? null, 40),
                    'has_control'        => $this->bool($row['has_control'] ?? null),
                    'requires_control'   => $this->bool($row['requires_control'] ?? null),
                    'requires_duplicate' => $this->bool($row['requires_duplicate'] ?? null),
                    'replicates'         => $this->int($row['replicates'] ?? null),
                    'sort_order'         => $this->int($row['sort_order'] ?? null),
                ];

                $existing = $this->findByCodeAnywhere($code);

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

                    // Solo lo que trae valor: una planilla parcial no debe
                    // vaciar el envase ni desmarcar las banderas de control por
                    // venir con la columna en blanco.
                    $patch = [];
                    foreach ($attributes as $key => $value) {
                        if ($value === null) continue;
                        if ((string) $existing->{$key} !== (string) $value) {
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

                    TestDefinition::create(
                        array_filter($attributes, fn ($v) => $v !== null) + [
                            'code'       => $code,
                            'is_active'  => true,
                            'created_by' => Auth::id(),
                            // tenant_id lo autorellena BelongsToTenantOrGlobal;
                            // el slug lo auto-genera el modelo en `creating`.
                        ]
                    );

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

    /** Entero o null. Un texto no numérico se trata como vacío, no como 0. */
    protected function int(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) return null;
        return (int) $value;
    }

    /**
     * Sí/no de una planilla. Se aceptan las formas que el laboratorio escribe
     * de verdad (si, sí, x, 1, true, yes) y cualquier otra cosa se toma como
     * "no dijeron nada" → null, para no desmarcar una bandera de control por
     * una celda escrita raro.
     */
    protected function bool(mixed $value): ?bool
    {
        if ($value === null) return null;
        $text = mb_strtolower(trim((string) $value));
        if ($text === '') return null;

        if (in_array($text, ['1', 'si', 'sí', 'x', 'true', 'yes', 'y', 'v'], true)) return true;
        if (in_array($text, ['0', 'no', 'false', 'n', 'f'], true))                  return false;

        return null;
    }

    /**
     * Búsqueda por código en TODA la tabla (el único es global, no per-tenant).
     * Se saltean los global scopes a propósito: si no, un código de otro
     * workspace o uno en la papelera parecerían libres y el insert reventaría el
     * índice único arrastrando toda la importación.
     */
    protected function findByCodeAnywhere(string $code): ?TestDefinition
    {
        return TestDefinition::withoutGlobalScopes()
            ->whereRaw('LOWER(test_definitions.code) = LOWER(?)', [trim($code)])
            ->first();
    }
}
