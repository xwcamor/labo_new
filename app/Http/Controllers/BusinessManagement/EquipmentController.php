<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessManagement\Equipment\BulkDeleteEquipmentRequest;
use App\Http\Requests\BusinessManagement\Equipment\BulkRestoreEquipmentRequest;
use App\Http\Requests\BusinessManagement\Equipment\BulkSetActiveEquipmentRequest;
use App\Http\Requests\BusinessManagement\Equipment\DeleteEquipmentRequest;
use App\Http\Requests\BusinessManagement\Equipment\EditAllUpdateEquipmentRequest;
use App\Http\Requests\BusinessManagement\Equipment\ForceDeleteEquipmentRequest;
use App\Http\Requests\BusinessManagement\Equipment\ImportEquipmentRequest;
use App\Http\Requests\BusinessManagement\Equipment\StoreEquipmentRequest;
use App\Http\Requests\BusinessManagement\Equipment\UpdateEquipmentRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\BusinessManagement\Equipment\GenerateEquipmentCsvJob;
use App\Jobs\BusinessManagement\Equipment\GenerateEquipmentExcelJob;
use App\Jobs\BusinessManagement\Equipment\GenerateEquipmentPdfJob;
use App\Jobs\BusinessManagement\Equipment\GenerateEquipmentWordJob;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\OilType;
use App\Models\TapChangerType;
use App\Models\TransformerPreservation;
use App\Services\BusinessManagement\EquipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    use \App\Traits\BuildsRecordAudit;
    use \App\Http\Controllers\Concerns\HandlesRecordLocking;

    /** Pone el candado a la marca (super → nivel sistema; admin → nivel tenant). */
    public function lock(Request $request, Equipment $equipment): RedirectResponse
    {
        return $this->applyLock($equipment, $request);
    }

    /** Saca el candado (un admin no puede quitar un candado del super). */
    public function unlock(Request $request, Equipment $equipment): RedirectResponse
    {
        return $this->applyUnlock($equipment, $request);
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

        // equipment es per-tenant (BelongsToTenant lo scopea solo) — eager-load creator.
        // El super ve cross-tenant: carga el tenant para mostrarlo en el drawer.
        $with = ['creator:id,name,email'];
        if ($isSuper) {
            $with[] = 'tenant:id,name';
        }

        $equipment = Equipment::query()
            ->select('equipment.*')
            ->with($with)
            ->orderByFavoriteFirst($userId)
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = Equipment::count();

        $names = $request->get('name', []);
        if (is_string($names)) $names = $names === '' ? [] : [$names];

        return inertia('Equipment/Index', [
            'equipment' => array_merge($equipment->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            // Limites de export por formato â€” el frontend deshabilita formatos
            // que exceden su limite. CSV con 0 = sin limite (streaming).
            'exportLimits' => \App\Models\Setting::getExportLimits('equipment'),
            'filters' => [
                'name'         => array_values($names),
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
            // Schema de campos filtrables â€” alimenta el drawer "Filtros
            // avanzados" del frontend (selects de field/op + control tipado
            // del valor). Cada modulo declara el suyo en su modelo.
            // Los filtros por cliente / tipo de equipo / tipo de aceite son
            // desplegables: necesitan sus opciones o el constructor los muestra
            // vacíos y no se puede filtrar por ellos.
            'filterSchema'   => Equipment::filterSchema([
                'customers' => Customer::orderBy('name')->get(['id', 'name'])
                    ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
                'types'     => EquipmentType::orderBy('name')->get(['id', 'name'])
                    ->map(fn ($t) => ['value' => $t->id, 'label' => $t->name])->all(),
                'oilTypes'  => OilType::orderBy('name')->get(['id', 'name'])
                    ->map(fn ($o) => ['value' => $o->id, 'label' => $o->name])->all(),
            ]),
        ]);
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

    public function show(Request $request, Equipment $equipment)
    {
        $equipment->load(['creator:id,name,email', 'deleter:id,name,email', 'locker:id,name']);

        $canSeeAudit = $request->user()?->hasAnyRole(['super', 'admin']) ?? false;
        $activity = $canSeeAudit
            ? AuditLogResource::collection(
                AuditLog::query()
                    ->where('auditable_type', Equipment::class)
                    ->where('auditable_id', $equipment->id)
                    ->with('user:id,name,email')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'user_id', 'event', 'old_values', 'new_values', 'created_at'])
            )->resolve()
            : [];

        return inertia('Equipment/Show', [
            'equipment' => array_merge(
                $this->payload($equipment, withAudit: true),
                ['lock' => $this->lockMeta($equipment, $request)],
            ),
            'recordAudit'  => $this->recordAuditMeta($equipment),
            'activity'     => $activity,
        ]);
    }

    public function create()
    {
        return inertia('Equipment/Form', [
            'equipment'        => null,
            ...$this->catalogos(),
        ]);
    }

    /**
     * Lo que el formulario necesita para preguntar de quién es el equipo y qué
     * es.
     *
     * El formulario generado por el scaffold no traía NADA de esto: pedía el
     * nombre y un código que no existe como columna, y guardaba el equipo SIN
     * CLIENTE. Un equipo sin cliente no aparece en ninguna recepción —la
     * recepción solo ofrece los del cliente de la entrega, justamente para no
     * colgarle la muestra de una empresa al transformador de otra—, así que
     * quedaba inservible sin dar ningún aviso.
     *
     * La jerarquía (ubicación → área → subestación) NO viaja acá: son 843
     * ubicaciones, 1940 áreas y 1368 subestaciones sumando todos los clientes.
     * Va solo la del cliente elegido, y se pide al vuelo (`hierarchy`).
     *
     * @return array<string,mixed>
     */
    private function catalogos(): array
    {
        return [
            'customers'       => Customer::orderBy('name')->get(['id', 'name']),
            'equipmentTypes'  => EquipmentType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'oilTypes'        => OilType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'brands'          => Brand::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'tapChangerTypes' => TapChangerType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'preservations'   => TransformerPreservation::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'oilVolumeUnits'  => Equipment::OIL_VOLUME_UNITS,
            'serviceStates'   => Equipment::SERVICE_STATES,
        ];
    }

    /**
     * La jerarquía de UN cliente: sus ubicaciones, con sus áreas y sus
     * subestaciones.
     *
     * Se pide al elegir el cliente en el formulario. Anidada en una sola
     * respuesta y no en tres pedidos encadenados: hasta el cliente más grande
     * tiene decenas de ubicaciones, no miles, y así los desplegables de abajo
     * no esperan un viaje cada uno.
     */
    public function hierarchy(Customer $customer)
    {
        $ubicaciones = $customer->locations()
            ->with(['areas:id,customer_location_id,name', 'areas.substations:id,customer_area_id,name'])
            ->orderBy('name')
            ->get(['id', 'customer_id', 'name']);

        return response()->json($ubicaciones->map(fn ($u) => [
            'id'    => $u->id,
            'name'  => $u->name,
            'areas' => $u->areas->map(fn ($a) => [
                'id'          => $a->id,
                'name'        => $a->name,
                'substations' => $a->substations->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
            ])->values(),
        ])->values());
    }

    public function store(StoreEquipmentRequest $request, EquipmentService $service): RedirectResponse
    {
        // Limite de registros por modulo segun el plan del tenant.
        // super no tiene tenant â†’ no aplica. -1 = ilimitado.
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && Equipment::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $service->create($request->validated());

        return redirect()
            ->route('business_management.equipment.index')
            ->with('success', __('equipment.created'));
    }

    /**
     * Alta rápida de marca desde un select de otro módulo (ej. el form de
     * trafos) sin salir de la página. Misma validación que store() — incluye
     * unicidad insensible a acentos/mayúsculas, así que bloquea duplicados —
     * pero responde JSON con la marca creada para inyectarla en el select.
     * Gated por permission:equipment.create (super/admin pasan por sus permisos).
     */
    public function quickStore(StoreEquipmentRequest $request, EquipmentService $service): \Illuminate\Http\JsonResponse
    {
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && Equipment::count() >= $max) {
                return response()->json(['message' => __('plans.limit_records_reached', ['max' => $max])], 422);
            }
        }

        $equipment = $service->create($request->validated());

        return response()->json(['id' => $equipment->id, 'name' => $equipment->name], 201);
    }

    public function edit(Equipment $equipment)
    {
        // Registro bloqueado (Lockable): ni se abre el formulario de edición.
        abort_if($equipment->is_locked, 403, __('locks.cannot_edit_locked'));

        return inertia('Equipment/Form', [
            'equipment'        => $this->payload($equipment),
            ...$this->catalogos(),
            // La jerarquía del cliente que ya tiene, para que los tres
            // desplegables encadenados abran con su valor puesto en vez de
            // vacíos esperando un viaje al servidor.
            'hierarchy'        => $equipment->customer_id
                ? $this->hierarchy($equipment->customer)->getData(true)
                : [],
        ]);
    }


    public function update(UpdateEquipmentRequest $request, Equipment $equipment, EquipmentService $service): RedirectResponse
    {
        $service->update($equipment, $request->validated());

        return redirect()
            ->route('business_management.equipment.index')
            ->with('success', __('equipment.saved'));
    }

    public function delete(Equipment $equipment)
    {
        // Registro bloqueado (Lockable): ni se abre la confirmación de borrado.
        abort_if($equipment->is_locked, 403, __('locks.cannot_delete_locked'));

        return inertia('Equipment/Delete', [
            'equipment' => $this->payload($equipment),
        ]);
    }

    public function deleteSave(DeleteEquipmentRequest $request, Equipment $equipment, EquipmentService $service): RedirectResponse
    {
        $service->delete($equipment, $request->validated()['deleted_description']);

        $this->storeUndoableDelete([$equipment->id]);

        return redirect()
            ->route('business_management.equipment.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$equipment->id]));
    }

    /** Persiste el claim en sesion por el window de undo (60s). */
    protected function storeUndoableDelete(array $ids): void
    {
        session(['equipment.recent_delete' => [
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

        $equipment = Equipment::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($name !== '', fn ($q) => $q->where('name', 'like', "%{$name}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Equipment/Trash', [
            'equipment' => $equipment,
            'filters'   => [
                'name'     => $name,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, EquipmentService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = Equipment::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('business_management.equipment.trash')
            ->with('success', __('global.restored_success'));
    }

    /**
     * Edit All â€” pagina con tabla editable in-line de name + is_active.
     * El submit hace batch update en transaccion (editAllUpdate).
     */
    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'asc']);
        }

        $equipment = Equipment::query()
            ->filter($request)
            // Sin `code`: no es una columna de esta tabla (ver la migración).
            ->select('equipment.id', 'equipment.slug', 'equipment.name', 'equipment.serial', 'equipment.tag', 'equipment.is_active')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Equipment/EditAll', [
            'equipment' => $equipment,
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

    public function editAllUpdate(EditAllUpdateEquipmentRequest $request, EquipmentService $service): RedirectResponse
    {
        $changes = $request->validated()['changes'];

        // Excluir registros BLOQUEADOS (Lockable) de la edición masiva.
        $ids = array_column($changes, 'id');
        [, $lockedIds] = $this->splitLockedIds(Equipment::class, $ids);
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
            ->route('business_management.equipment.edit_all')
            ->with('success', $msg);
    }

    /**
     * Clona el equipment. Sufijo "(copia)" con sanity guard de 100 intentos.
     */
    public function duplicate(Request $request, Equipment $equipment, EquipmentService $service): RedirectResponse
    {
        // Duplicar crea un registro nuevo → respeta el límite del plan (como store).
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && Equipment::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $clone = $service->duplicate($equipment);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('business_management.equipment.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkRestore(BulkRestoreEquipmentRequest $request, EquipmentService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('business_management.equipment.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('business_management.equipment.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeleteEquipmentRequest $request, $slug, EquipmentService $service): RedirectResponse
    {
        $model = Equipment::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        if (trim($data['name_confirmation']) !== $model->name) {
            return back()->withErrors(['name_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('business_management.equipment.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    protected function payload(Equipment $m, bool $withAudit = false): array
    {
        // Las FKs van en el payload porque el formulario y la ficha las
        // necesitan; el payload del scaffold devolvía solo nombre y estado, así
        // que al editar un equipo cargado por el seeder se perdían el cliente y
        // todo lo demás sin que nadie lo notara.
        $m->loadMissing([
            'customer:id,name', 'location:id,name', 'area:id,name', 'substation:id,name',
            'equipmentType:id,name', 'oilType:id,name', 'brand:id,name',
            'tapChangerType:id,name', 'preservation:id,name',
        ]);

        $base = [
            'id'         => $m->id,
            'slug'       => $m->slug,
            'name'       => $m->name,
            'serial'     => $m->serial,
            'tag'        => $m->tag,

            'customer_id'            => $m->customer_id,
            'customer_location_id'   => $m->customer_location_id,
            'customer_area_id'       => $m->customer_area_id,
            'customer_substation_id' => $m->customer_substation_id,
            'customer'   => $m->customer   ? ['id' => $m->customer->id,   'name' => $m->customer->name]   : null,
            'location'   => $m->location   ? ['id' => $m->location->id,   'name' => $m->location->name]   : null,
            'area'       => $m->area       ? ['id' => $m->area->id,       'name' => $m->area->name]       : null,
            'substation' => $m->substation ? ['id' => $m->substation->id, 'name' => $m->substation->name] : null,

            'equipment_type_id'           => $m->equipment_type_id,
            'oil_type_id'                 => $m->oil_type_id,
            'brand_id'                    => $m->brand_id,
            'tap_changer_type_id'         => $m->tap_changer_type_id,
            'transformer_preservation_id' => $m->transformer_preservation_id,
            'equipment_type' => $m->equipmentType ? ['id' => $m->equipmentType->id, 'name' => $m->equipmentType->name] : null,
            'oil_type'       => $m->oilType        ? ['id' => $m->oilType->id,        'name' => $m->oilType->name]        : null,
            'brand'          => $m->brand          ? ['id' => $m->brand->id,          'name' => $m->brand->name]          : null,
            'tap_changer_type' => $m->tapChangerType ? ['id' => $m->tapChangerType->id, 'name' => $m->tapChangerType->name] : null,
            'preservation'   => $m->preservation   ? ['id' => $m->preservation->id,   'name' => $m->preservation->name]   : null,

            'voltage_kv_hv'    => $m->voltage_kv_hv,
            'voltage_kv_lv'    => $m->voltage_kv_lv,
            'voltage_kv_tv'    => $m->voltage_kv_tv,
            'power_mva'        => $m->power_mva,
            'power_mva_2'      => $m->power_mva_2,
            'power_mva_3'      => $m->power_mva_3,
            // `voltage_label` y `power_label` llegan por $appends del modelo.
            'phases'           => $m->phases,
            'manufacture_year' => $m->manufacture_year,
            'oil_volume'       => $m->oil_volume,
            'oil_volume_unit'  => $m->oil_volume_unit,
            'service_state'    => $m->service_state,
            'external_ref'     => $m->external_ref,

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

    // â”€â”€ EXPORTS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Los 4 formatos van a queue como jobs async (mismo patron que Regions).
    // El job se encarga de la query con scope + render + Download record.

    public function exportCsv(Request $request)
    {
        return $this->dispatchExport($request, 'csv', GenerateEquipmentCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GenerateEquipmentExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GenerateEquipmentPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GenerateEquipmentWordJob::class);
    }

    /**
     * Helper comun de los 4 export endpoints: parse options â†’ limit check â†’
     * audit â†’ dispatch. Mismo patron que Region.
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

        $limit = \App\Models\Setting::getExportLimit('equipment', $format);
        if ($limit === 0) return; // CSV streaming, sin limite

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('equipment.export_limit_exceeded', [
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
            return Equipment::query()->count();
        }
        // Filters como Request para reusar scopeFilter.
        $fakeReq = new Request($options['filters'] ?? []);
        return Equipment::query()->filter($fakeReq)->count();
    }

    // â”€â”€ IMPORTS (two-phase: dry_run preview + commit) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // El frontend sube 2 veces: primero dry_run=true (preview con summary),
    // despues dry_run=false (commit).

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BusinessManagement\Equipment\EquipmentImportTemplate(),
            __('equipment.import_template_filename')
        );
    }

    public function import(ImportEquipmentRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Guardrail multi-tenant: super NO puede importar sin tenant porque
        // el lookup por nombre case-insensitive matchearÃ­a equipment de cualquier
        // workspace y los update cross-tenant. Si super necesita importar a un
        // tenant especÃ­fico, debe loguearse impersonando el admin de ese tenant
        // o usar la API directamente con `Auth::onceUsingId(...)`.
        $user = $request->user();
        if ($user && $user->hasRole('super') && empty($user->tenant_id)) {
            return response()->json([
                'ok'      => false,
                'dry_run' => $dryRun,
                'message' => __('equipment.import_super_blocked', [], 'Super sin workspace asignado no puede importar â€” el match por nombre puede actualizar registros de otro tenant.'),
            ], 422);
        }

        $importer = new \App\Imports\BusinessManagement\Equipment\EquipmentImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('EquipmentImport failed', [
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

    // â”€â”€ BULK OPERATIONS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function bulkDelete(BulkDeleteEquipmentRequest $request, EquipmentService $service): RedirectResponse
    {
        $data = $request->validated();

        // Excluir registros BLOQUEADOS (Lockable): no se borran en masa.
        [$allowedIds, $lockedIds] = $this->splitLockedIds(Equipment::class, $data['ids']);
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
    public function undoLastDelete(Request $request, EquipmentService $service): RedirectResponse
    {
        $claim = session('equipment.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('equipment.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('equipment.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('equipment.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    public function bulkSetActive(BulkSetActiveEquipmentRequest $request, EquipmentService $service): RedirectResponse
    {
        $data = $request->validated();

        // Excluir registros BLOQUEADOS (Lockable): no cambian de estado en masa.
        [$allowedIds, $lockedIds] = $this->splitLockedIds(Equipment::class, $data['ids']);
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

    // â”€â”€ Export helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Opciones normalizadas que reciben todos los jobs de export. Allowlist
     * de columnas previene inyeccion de campos sensibles.
     */
    protected function buildExportOptions(Request $request, string $format): array
    {
        // Sin 'id' (no se exporta). La columna `tenant` (workspace) SOLO es
        // exportable por super: el resto ve únicamente marcas de su propio
        // tenant. Gate de seguridad real (no basta ocultarla en el front).
        $isSuper = $request->user()?->hasRole('super') ?? false;
        $allowedColumns = array_values(array_filter([
            'name', 'serial', 'tag', 'is_active',
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
            'title'                   => $data['title']                   ?? __('equipment.export_title'),
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
            'auditable_type' => Equipment::class,
            'auditable_id'   => null,
            'module'         => 'equipment',
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
            'url'        => route('business_management.equipment.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
