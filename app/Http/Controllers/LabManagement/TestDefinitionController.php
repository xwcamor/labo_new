<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabManagement\TestDefinition\BulkDeleteTestDefinitionRequest;
use App\Http\Requests\LabManagement\TestDefinition\BulkRestoreTestDefinitionRequest;
use App\Http\Requests\LabManagement\TestDefinition\BulkSetActiveTestDefinitionRequest;
use App\Http\Requests\LabManagement\TestDefinition\DeleteTestDefinitionRequest;
use App\Http\Requests\LabManagement\TestDefinition\EditAllUpdateTestDefinitionRequest;
use App\Http\Requests\LabManagement\TestDefinition\ForceDeleteTestDefinitionRequest;
use App\Http\Requests\LabManagement\TestDefinition\ImportTestDefinitionRequest;
use App\Http\Requests\LabManagement\TestDefinition\StoreTestDefinitionRequest;
use App\Http\Requests\LabManagement\TestDefinition\UpdateTestDefinitionRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\LabManagement\TestDefinitions\GenerateTestDefinitionsCsvJob;
use App\Jobs\LabManagement\TestDefinitions\GenerateTestDefinitionsExcelJob;
use App\Jobs\LabManagement\TestDefinitions\GenerateTestDefinitionsPdfJob;
use App\Jobs\LabManagement\TestDefinitions\GenerateTestDefinitionsWordJob;
use App\Models\AuditLog;
use App\Models\TestDefinition;
use App\Services\LabManagement\TestDefinitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TestDefinitionController extends Controller
{
    use \App\Traits\BuildsRecordAudit;
    use \App\Http\Controllers\Concerns\HandlesRecordLocking;

    /** Pone el candado a la prueba (super → nivel sistema; admin → nivel tenant). */
    public function lock(Request $request, TestDefinition $testDefinition): RedirectResponse
    {
        return $this->applyLock($testDefinition, $request);
    }

    /** Saca el candado (un admin no puede quitar un candado del super). */
    public function unlock(Request $request, TestDefinition $testDefinition): RedirectResponse
    {
        return $this->applyUnlock($testDefinition, $request);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 10;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'desc']);
        }

        $userId  = $request->user()?->id;
        $isSuper = $request->user()?->hasRole('super') ?? false;

        // test_definitions es per-tenant (BelongsToTenant lo scopea solo) — eager-load creator.
        // El super ve cross-tenant: carga el tenant para mostrarlo en el drawer.
        // El grupo se muestra como columna del listado: sin el eager-load son
        // N+1 consultas por página.
        $with = ['creator:id,name,email', 'group:id,name,code'];
        if ($isSuper) {
            $with[] = 'tenant:id,name';
        }

        $test_definitions = TestDefinition::query()
            ->withCount('fields')
            ->select('test_definitions.*')
            ->with($with)
            ->orderByFavoriteFirst($userId)
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = TestDefinition::count();

        $names = $request->get('name', []);
        if (is_string($names)) $names = $names === '' ? [] : [$names];

        return inertia('TestDefinitions/Index', [
            'test_definitions' => array_merge($test_definitions->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            // Limites de export por formato — el frontend deshabilita formatos
            // que exceden su limite. CSV con 0 = sin limite (streaming).
            'exportLimits' => \App\Models\Setting::getExportLimits('test_definitions'),
            'filters' => [
                'name'         => array_values($names),
                'code'         => $request->get('code', ''),
                'test_group_id' => $request->filled('test_group_id')
                    ? (int) $request->test_group_id
                    : null,
                'requires_control' => $request->filled('requires_control')
                    ? filter_var($request->requires_control, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'requires_duplicate' => $request->filled('requires_duplicate')
                    ? filter_var($request->requires_duplicate, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'is_active'    => $request->filled('is_active')
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'created_from' => $request->get('created_from', ''),
                'created_to'   => $request->get('created_to', ''),
                'only_favorites' => $request->boolean('only_favorites'),
                'sort'         => $request->get('sort', 'id'),
                'direction'    => $request->get('direction', 'desc'),
                'per_page'     => $perPage,
                // Filtros avanzados: array de clausulas {field, op, value}
                // que el drawer construye. Lo persisto para que al recargar
                // la pagina (paginate, sort) el filtro siga aplicado.
                'advanced_where' => $this->parseAdvancedWhere($request),
            ],
            'isSuper'        => $isSuper,
            // Schema de campos filtrables — alimenta el drawer "Filtros
            // avanzados" del frontend (selects de field/op + control tipado
            // del valor). Cada modulo declara el suyo en su modelo.
            // El grupo va como `enum` CON sus opciones: si no, el constructor de
            // filtros pediría el id del grupo a mano. La columna del grupo ya se
            // mostraba en el listado y no se podía filtrar por ella.
            'filterSchema'   => TestDefinition::filterSchema(['groups' => $this->groupOptions()]),
            // Opciones del filtro por grupo. Son tres filas: se mandan enteras
            // en vez de montar un endpoint de búsqueda.
            'groups'         => $this->groupOptions(),
        ]);
    }

    /**
     * Los grupos que se pueden elegir, en el orden en que se muestran. Los
     * inactivos NO se ofrecen para clasificar una prueba nueva, pero sí se
     * incluye el que la prueba ya tiene: si no, editar cualquier otro campo de
     * una prueba vieja le vaciaría el grupo sin que nadie lo pida.
     *
     * @return array<int, array{value:int, label:string, code:string}>
     */
    /**
     * Las familias que ya existen, para elegir en vez de tipear.
     *
     * Salen de la propia tabla y no de una lista escrita en el código: la
     * familia es el nombre con el que el laboratorio agrupa sus pruebas en el
     * informe, y no hay catálogo que la represente. El formulario también
     * admite escribir una nueva.
     *
     * @return list<string>
     */
    protected function familyOptions(): array
    {
        return TestDefinition::query()
            ->whereNotNull('report_comment_group')
            ->distinct()
            ->orderBy('report_comment_group')
            ->pluck('report_comment_group')
            ->all();
    }

    protected function groupOptions(?int $keepId = null): array
    {
        return \App\Models\TestGroup::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($keepId, fn ($qq) => $qq->orWhere('id', $keepId)))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($g) => ['value' => $g->id, 'label' => $g->name, 'code' => $g->code])
            ->all();
    }

    /**
     * Normaliza `advanced_where` del request: viene como JSON string o
     * array directo segun como Inertia lo serialice. Filtra clausulas
     * vacias o incompletas antes de pasarlo al frontend.
     */
    protected function parseAdvancedWhere(\Illuminate\Http\Request $request): array
    {
        $raw = $request->input('advanced_where', []);
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }
        if (!is_array($raw)) return [];

        return array_values(array_filter($raw, fn ($c) =>
            is_array($c) && !empty($c['field']) && !empty($c['op'])
        ));
    }

    public function show(Request $request, TestDefinition $testDefinition)
    {
        $testDefinition->load([
            'creator:id,name,email', 'deleter:id,name,email', 'locker:id,name',
            'group:id,name,code',
        ]);

        $canSeeAudit = $request->user()?->hasAnyRole(['super', 'admin']) ?? false;
        $activity = $canSeeAudit
            ? AuditLogResource::collection(
                AuditLog::query()
                    ->where('auditable_type', TestDefinition::class)
                    ->where('auditable_id', $testDefinition->id)
                    ->with('user:id,name,email')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'user_id', 'event', 'old_values', 'new_values', 'created_at'])
            )->resolve()
            : [];

        // Resumen de la hoja de trabajo. La ficha NO edita las columnas —de eso
        // se encarga el editor de columnas, que se monta aparte—, pero sí dice
        // cuántas hay y cuántas son un resultado: sin resultados declarados el
        // informe no tiene de dónde leer.
        $fieldsCount  = $testDefinition->fields()->count();
        $resultsCount = $testDefinition->resultFields()->count();

        return inertia('TestDefinitions/Show', [
            'testDefinition' => array_merge(
                $this->payload($testDefinition, withAudit: true),
                [
                    'lock'          => $this->lockMeta($testDefinition, $request),
                    'fields_count'  => $fieldsCount,
                    'results_count' => $resultsCount,
                ],
            ),
            'recordAudit'  => $this->recordAuditMeta($testDefinition),
            'activity'     => $activity,
        ]);
    }

    public function create()
    {
        return inertia('TestDefinitions/Form', [
            'testDefinition' => null,
            'groups'         => $this->groupOptions(),
            'families'       => $this->familyOptions(),
        ]);
    }

    public function store(StoreTestDefinitionRequest $request, TestDefinitionService $service): RedirectResponse
    {
        // Limite de registros por modulo segun el plan del tenant.
        // super no tiene tenant → no aplica. -1 = ilimitado.
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && TestDefinition::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $service->create($request->validated());

        return redirect()
            ->route('lab_management.test_definitions.index')
            ->with('success', __('test_definitions.created'));
    }

    /**
     * Alta rápida de prueba desde un select de otra pantalla, sin salir de la
     * página. Misma validación que store() —incluye la unicidad del código—
     * pero responde JSON con la prueba creada para inyectarla en el select.
     * Gated por permission:test_definitions.create (super/admin pasan por sus permisos).
     */
    public function quickStore(StoreTestDefinitionRequest $request, TestDefinitionService $service): \Illuminate\Http\JsonResponse
    {
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && TestDefinition::count() >= $max) {
                return response()->json(['message' => __('plans.limit_records_reached', ['max' => $max])], 422);
            }
        }

        $testDefinition = $service->create($request->validated());

        return response()->json(['id' => $testDefinition->id, 'name' => $testDefinition->name], 201);
    }

    public function edit(TestDefinition $testDefinition)
    {
        // Registro bloqueado (Lockable): ni se abre el formulario de edición.
        abort_if($testDefinition->is_locked, 403, __('locks.cannot_edit_locked'));

        return inertia('TestDefinitions/Form', [
            'testDefinition' => $this->payload($testDefinition),
            'groups'         => $this->groupOptions($testDefinition->test_group_id),
            'families'       => $this->familyOptions(),
        ]);
    }


    public function update(UpdateTestDefinitionRequest $request, TestDefinition $testDefinition, TestDefinitionService $service): RedirectResponse
    {
        $service->update($testDefinition, $request->validated());

        return redirect()
            ->route('lab_management.test_definitions.index')
            ->with('success', __('test_definitions.saved'));
    }

    public function delete(TestDefinition $testDefinition)
    {
        // Registro bloqueado (Lockable): ni se abre la confirmación de borrado.
        abort_if($testDefinition->is_locked, 403, __('locks.cannot_delete_locked'));

        return inertia('TestDefinitions/Delete', [
            'testDefinition' => $this->payload($testDefinition),
        ]);
    }

    public function deleteSave(DeleteTestDefinitionRequest $request, TestDefinition $testDefinition, TestDefinitionService $service): RedirectResponse
    {
        $service->delete($testDefinition, $request->validated()['deleted_description']);

        $this->storeUndoableDelete([$testDefinition->id]);

        return redirect()
            ->route('lab_management.test_definitions.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$testDefinition->id]));
    }

    /** Persiste el claim en sesion por el window de undo (60s). */
    protected function storeUndoableDelete(array $ids): void
    {
        session(['test_definitions.recent_delete' => [
            'ids'        => array_values($ids),
            'expires_at' => now()->addSeconds(60)->toIso8601String(),
        ]]);
    }

    /** Payload que va al frontend via flash para disparar el toast. */
    protected function buildRecentDeletePayload(array $ids): array
    {
        return [
            'count'   => count($ids),
            'seconds' => 60,
        ];
    }

    public function trash(Request $request)
    {
        abort_unless($request->user()?->hasRole('super'), 403);

        $name    = $request->get('name', '');
        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;

        $test_definitions = TestDefinition::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($name !== '', fn ($q) => $q->where('name', 'like', "%{$name}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('TestDefinitions/Trash', [
            'test_definitions' => $test_definitions,
            'filters'   => [
                'name'     => $name,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, TestDefinitionService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = TestDefinition::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('lab_management.test_definitions.trash')
            ->with('success', __('global.restored_success'));
    }

    /**
     * Edit All — pagina con tabla editable in-line de name + is_active.
     * El submit hace batch update en transaccion (editAllUpdate).
     */
    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'asc']);
        }

        $test_definitions = TestDefinition::query()
            ->filter($request)
            ->select('test_definitions.id', 'test_definitions.slug', 'test_definitions.name', 'test_definitions.code', 'test_definitions.is_active')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('TestDefinitions/EditAll', [
            'test_definitions' => $test_definitions,
            'filters'   => [
                'name'      => $request->get('name', ''),
                'is_active' => $request->filled('is_active')
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'sort'      => $request->get('sort', 'id'),
                'direction' => $request->get('direction', 'asc'),
                'per_page'  => $perPage,
            ],
        ]);
    }

    public function editAllUpdate(EditAllUpdateTestDefinitionRequest $request, TestDefinitionService $service): RedirectResponse
    {
        $changes = $request->validated()['changes'];

        // Excluir registros BLOQUEADOS (Lockable) de la edición masiva.
        $ids = array_column($changes, 'id');
        [, $lockedIds] = $this->splitLockedIds(TestDefinition::class, $ids);
        if (!empty($lockedIds)) {
            $lockedSet = array_flip($lockedIds);
            $changes = array_values(array_filter($changes, fn ($c) => !isset($lockedSet[(int) $c['id']])));
        }

        $touched = $service->editAllUpdate($changes);

        $msg = __('global.updated_success') . " ({$touched})";
        if (!empty($lockedIds)) {
            $msg .= ' · ' . __('locks.bulk_skipped_locked', ['count' => count($lockedIds)]);
        }

        return redirect()
            ->route('lab_management.test_definitions.edit_all')
            ->with('success', $msg);
    }

    /**
     * Clona el testDefinition. Sufijo "(copia)" con sanity guard de 100 intentos.
     */
    public function duplicate(Request $request, TestDefinition $testDefinition, TestDefinitionService $service): RedirectResponse
    {
        // Duplicar crea un registro nuevo → respeta el límite del plan (como store).
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && TestDefinition::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $clone = $service->duplicate($testDefinition);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('lab_management.test_definitions.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkRestore(BulkRestoreTestDefinitionRequest $request, TestDefinitionService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('lab_management.test_definitions.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('lab_management.test_definitions.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeleteTestDefinitionRequest $request, $slug, TestDefinitionService $service): RedirectResponse
    {
        $model = TestDefinition::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        if (trim($data['name_confirmation']) !== $model->name) {
            return back()->withErrors(['name_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('lab_management.test_definitions.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    protected function payload(TestDefinition $m, bool $withAudit = false): array
    {
        $base = [
            'id'         => $m->id,
            'slug'       => $m->slug,
            'name'       => $m->name,
            'code'       => $m->code,
            'test_group_id' => $m->test_group_id,
            'group'      => $m->relationLoaded('group') && $m->group
                ? ['id' => $m->group->id, 'name' => $m->group->name, 'code' => $m->group->code]
                : null,
            'description' => $m->description,
            'container'   => $m->container,
            'chart_unit'  => $m->chart_unit,
            // Con qué otras pruebas comparte tabla en el informe.
            'report_comment_group' => $m->report_comment_group,
            'has_control'        => $m->has_control,
            'requires_control'   => $m->requires_control,
            'requires_duplicate' => $m->requires_duplicate,
            'is_grouped'         => $m->is_grouped,
            'replicates' => $m->replicates,
            // Id en el sistema Rails viejo: dato de trazabilidad, SOLO LECTURA.
            // Va al front para mostrarlo en la ficha; el formulario no lo edita
            // y las reglas de validación ni siquiera lo aceptan.
            'legacy_id'  => $m->legacy_id,
            'sort_order' => $m->sort_order,
            'is_active'  => $m->is_active,
            'tenant_id'  => $m->tenant_id,
            'is_locked'  => $m->is_locked,
            'lock_scope' => $m->lock_scope,
            'is_favorite' => (bool) ($m->is_favorite ?? false),
            'created_at' => $m->created_at,
            'updated_at' => $m->updated_at,
            'deleted_at' => $m->deleted_at,
        ];
        if ($withAudit) {
            $base['deleted_description'] = $m->deleted_description;
            $base['creator'] = $m->creator ? ['id' => $m->creator->id, 'name' => $m->creator->name, 'email' => $m->creator->email] : null;
            $base['deleter'] = $m->deleter ? ['id' => $m->deleter->id, 'name' => $m->deleter->name, 'email' => $m->deleter->email] : null;
        }
        return $base;
    }

    // ── EXPORTS ─────────────────────────────────────────────────────────
    // Los 4 formatos van a queue como jobs async (mismo patron que Regions).
    // El job se encarga de la query con scope + render + Download record.

    public function exportCsv(Request $request)
    {
        return $this->dispatchExport($request, 'csv', GenerateTestDefinitionsCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GenerateTestDefinitionsExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GenerateTestDefinitionsPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GenerateTestDefinitionsWordJob::class);
    }

    /**
     * Helper comun de los 4 export endpoints: parse options → limit check →
     * audit → dispatch. Mismo patron que Region.
     */
    protected function dispatchExport(Request $request, string $format, string $jobClass): RedirectResponse
    {
        $options = $this->buildExportOptions($request, $format);
        $this->assertExportLimit($format, $options);
        $this->recordExportAudit($format, $options);
        $jobClass::dispatch(auth()->id(), $options);

        return back()->with('success', __('global.download_in_queue'));
    }

    /**
     * Valida que el dataset no exceda el limite del formato. Usuarios con
     * plan premium (feature flag `export_unlimited_rows`) saltean el limite.
     */
    protected function assertExportLimit(string $format, array $options): void
    {
        if (\App\Support\FeatureGate::allows('export_unlimited_rows', auth()->user())
            && config('features.features.export_unlimited_rows') !== null) {
            return;
        }

        $limit = \App\Models\Setting::getExportLimit('test_definitions', $format);
        if ($limit === 0) return; // CSV streaming, sin limite

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('test_definitions.export_limit_exceeded', [
                'count'  => number_format($count),
                'limit'  => number_format($limit),
                'format' => strtoupper($format),
            ]));
        }
    }

    /** Cuenta filas a exportar segun scope+filters. */
    protected function countForExport(array $options): int
    {
        $scope = $options['scope'] ?? 'filtered';

        if ($scope === 'selected') {
            return count($options['selected_ids'] ?? []);
        }
        if ($scope === 'all') {
            return TestDefinition::query()->count();
        }
        // Filters como Request para reusar scopeFilter.
        $fakeReq = new Request($options['filters'] ?? []);
        return TestDefinition::query()->filter($fakeReq)->count();
    }

    // ── IMPORTS (two-phase: dry_run preview + commit) ────────────────────
    // El frontend sube 2 veces: primero dry_run=true (preview con summary),
    // despues dry_run=false (commit).

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\LabManagement\TestDefinitions\TestDefinitionsImportTemplate(),
            __('test_definitions.import_template_filename')
        );
    }

    public function import(ImportTestDefinitionRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Guardrail multi-tenant: super NO puede importar sin tenant porque
        // el lookup por nombre case-insensitive matchearía test_definitions de cualquier
        // workspace y los update cross-tenant. Si super necesita importar a un
        // tenant específico, debe loguearse impersonando el admin de ese tenant
        // o usar la API directamente con `Auth::onceUsingId(...)`.
        $user = $request->user();
        if ($user && $user->hasRole('super') && empty($user->tenant_id)) {
            return response()->json([
                'ok'      => false,
                'dry_run' => $dryRun,
                'message' => __('test_definitions.import_super_blocked', [], 'Super sin workspace asignado no puede importar — la busqueda por codigo puede actualizar registros de otro workspace.'),
            ], 422);
        }

        $importer = new \App\Imports\LabManagement\TestDefinitions\TestDefinitionsImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('TestDefinitionsImport failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok'      => false,
                'dry_run' => $dryRun,
                'message' => $this->humanizeImportError($e),
            ], 422);
        }

        return response()->json([
            'ok'      => true,
            'dry_run' => $dryRun,
            'summary' => $importer->summary(),
        ], 200);
    }

    /**
     * Convierte una excepcion de import en mensaje legible para el usuario.
     * El detalle tecnico queda en el log, no llega al cliente.
     */
    protected function humanizeImportError(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if ($e instanceof \Illuminate\Database\QueryException) {
            if (str_contains($msg, 'unique') || str_contains($msg, 'duplicate')) {
                return __('imports.err_unique_violation');
            }
            if (str_contains($msg, 'NOT NULL') || str_contains($msg, 'null value')) {
                return __('imports.err_not_null_violation');
            }
            if (str_contains($msg, 'foreign key') || str_contains($msg, 'violates foreign')) {
                return __('imports.err_foreign_key_violation');
            }
        }

        return __('imports.process_failed');
    }

    // ── BULK OPERATIONS ─────────────────────────────────────────────────
    public function bulkDelete(BulkDeleteTestDefinitionRequest $request, TestDefinitionService $service): RedirectResponse
    {
        $data = $request->validated();

        // Excluir registros BLOQUEADOS (Lockable): no se borran en masa.
        [$allowedIds, $lockedIds] = $this->splitLockedIds(TestDefinition::class, $data['ids']);
        if (empty($allowedIds)) {
            return back()->with('error', __('locks.bulk_skipped_locked', ['count' => count($lockedIds)]));
        }

        $result = $service->bulkDelete($allowedIds, $data['deleted_description']);

        if (!empty($result['queued'])) {
            // Async: el delete real ocurre despues del redirect; el undo
            // window de 60s no calza con un job que tarda minutos.
            return back()
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        $deletedIds = $result['deleted'];
        $this->storeUndoableDelete($deletedIds);

        $msg = __('global.deleted_success') . ' (' . count($deletedIds) . ')';
        if (!empty($lockedIds)) {
            $msg .= ' · ' . __('locks.bulk_skipped_locked', ['count' => count($lockedIds)]);
        }

        return back()
            ->with('success', $msg)
            ->with('recentDelete', $this->buildRecentDeletePayload($deletedIds));
    }

    /**
     * Undo dentro del window de 60s. Validamos contra session claim:
     * quien borro puede deshacer su propio error sin permisos extra.
     * Defense in depth: el service solo restaura las filas con
     * deleted_by = current user.
     */
    public function undoLastDelete(Request $request, TestDefinitionService $service): RedirectResponse
    {
        $claim = session('test_definitions.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('test_definitions.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('test_definitions.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('test_definitions.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    public function bulkSetActive(BulkSetActiveTestDefinitionRequest $request, TestDefinitionService $service): RedirectResponse
    {
        $data = $request->validated();

        // Excluir registros BLOQUEADOS (Lockable): no cambian de estado en masa.
        [$allowedIds, $lockedIds] = $this->splitLockedIds(TestDefinition::class, $data['ids']);
        if (empty($allowedIds)) {
            return back()->with('error', __('locks.bulk_skipped_locked', ['count' => count($lockedIds)]));
        }

        $result = $service->bulkSetActive($allowedIds, (bool) $data['is_active']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        $msg = __('global.updated_success') . " ({$result['changed']})";
        if (!empty($lockedIds)) {
            $msg .= ' · ' . __('locks.bulk_skipped_locked', ['count' => count($lockedIds)]);
        }

        return back()->with('success', $msg);
    }

    // ── Export helpers ──────────────────────────────────────────────────

    /**
     * Opciones normalizadas que reciben todos los jobs de export. Allowlist
     * de columnas previene inyeccion de campos sensibles.
     */
    protected function buildExportOptions(Request $request, string $format): array
    {
        // Sin 'id' (no se exporta). La columna `tenant` (workspace) SOLO es
        // exportable por super: el resto ve únicamente pruebas de su propio
        // tenant. Gate de seguridad real (no basta ocultarla en el front).
        $isSuper = $request->user()?->hasRole('super') ?? false;
        $allowedColumns = array_values(array_filter([
            'code', 'name', 'group', 'container', 'chart_unit',
            'has_control', 'requires_control', 'requires_duplicate', 'is_grouped',
            'replicates', 'description', 'legacy_id',
            'sort_order', 'is_active',
            $isSuper ? 'tenant' : null,
            'created_at', 'updated_at', 'creator',
        ]));

        // id y slug: identificadores internos, exportables SOLO por super.
        if ($request->user()?->hasRole('super')) {
            $allowedColumns = array_merge(['id', 'slug'], $allowedColumns);
        }

        $rules = [
            'scope'                   => 'nullable|in:filtered,selected,all',
            'selected_ids'            => 'array',
            'selected_ids.*'          => 'integer',
            'columns'                 => 'array|min:1',
            'columns.*'               => 'in:' . implode(',', $allowedColumns),
            'title'                   => 'nullable|string|max:120',
            'include_filters_summary' => 'boolean',
            'filters'                 => 'array',
        ];
        if ($format === 'pdf') {
            $rules['orientation'] = 'nullable|in:portrait,landscape';
            $rules['paper_size']  = 'nullable|in:a4,letter';
        }
        if ($format === 'excel') {
            $rules['autofilter']    = 'boolean';
            $rules['freeze_header'] = 'boolean';
        }

        $data = $request->validate($rules);

        return [
            'scope'                   => $data['scope']                   ?? 'filtered',
            'selected_ids'            => $data['selected_ids']            ?? [],
            'columns'                 => $data['columns']                 ?? $allowedColumns,
            'title'                   => $data['title']                   ?? __('test_definitions.export_title'),
            'include_filters_summary' => $data['include_filters_summary'] ?? true,
            'filters'                 => $data['filters']                 ?? [],
            'orientation'             => $data['orientation']             ?? 'portrait',
            'paper_size'              => $data['paper_size']              ?? 'a4',
            'autofilter'              => $data['autofilter']              ?? true,
            'freeze_header'           => $data['freeze_header']           ?? true,
        ];
    }

    /**
     * Escribe audit log manual del export. Event = 'export_queued' registra
     * la INTENCION del usuario; el estado final (ready/failed) vive en `downloads`.
     */
    protected function recordExportAudit(string $format, array $options): void
    {
        AuditLog::create([
            'user_id'        => auth()->id(),
            'event'          => 'export_queued',
            'auditable_type' => TestDefinition::class,
            'auditable_id'   => null,
            'module'         => 'test_definitions',
            'old_values'     => null,
            'new_values'     => [
                'format'                  => $format,
                'scope'                   => $options['scope']        ?? 'filtered',
                'columns'                 => $options['columns']      ?? [],
                'title'                   => $options['title']        ?? null,
                'orientation'             => $format === 'pdf'   ? ($options['orientation']    ?? null) : null,
                'paper_size'              => $format === 'pdf'   ? ($options['paper_size']     ?? null) : null,
                'autofilter'              => $format === 'excel' ? ($options['autofilter']     ?? null) : null,
                'freeze_header'           => $format === 'excel' ? ($options['freeze_header']  ?? null) : null,
                'include_filters_summary' => $options['include_filters_summary'] ?? false,
                'filters'                 => $options['filters']      ?? [],
                'selected_ids_count'      => count($options['selected_ids'] ?? []),
            ],
            'url'        => route('lab_management.test_definitions.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
