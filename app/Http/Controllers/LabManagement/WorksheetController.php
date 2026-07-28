<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\Instrument;
use App\Models\TestDefinition;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
use App\Services\Lab\WorksheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * La bancada.
 *
 * Este controlador NO se generó con el scaffold, a diferencia del resto de los
 * módulos, y la diferencia es deliberada: una hoja de trabajo no es un
 * catálogo. No tiene alta masiva, ni edición en lote, ni duplicado, ni
 * importación por planilla. Tiene un flujo —cargar, cerrar, validar— y ese
 * flujo es lo que hay que cuidar.
 *
 * Toda la lógica vive en App\Services\Lab\WorksheetService. Acá solo entran los
 * datos y salen las páginas: el controlador nunca decide si una hoja se puede
 * escribir, eso lo decide el servicio.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL MENÚ NO ES UNA AUTORIZACIÓN                                           │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema Rails viejo la pantalla de validar mostraba su enlace solo a
 * los supervisores, pero la ACCIÓN verificaba el permiso de editar. El botón
 * estaba escondido y la dirección seguía abierta para cualquiera que pudiera
 * editar. Acá `validate` exige `worksheets.validate` en la ruta, y esconder el
 * botón es solo cortesía.
 */
class WorksheetController extends Controller
{
    public function __construct(private readonly WorksheetService $service)
    {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 25;

        $query = Worksheet::query()
            ->with(['definition:id,slug,code,name', 'analyst:id,name', 'validator:id,name'])
            ->withCount([
                'rows',
                'rows as samples_count' => fn ($q) => $q->where('kind', WorksheetRow::KIND_SAMPLE),
            ]);

        $query->when($request->filled('test_definition'), fn ($q) => $q->whereHas(
            'definition',
            fn ($d) => $d->where('slug', $request->test_definition)
        ));

        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->status));

        // El sistema viejo forzaba en silencio un filtro de "últimos tres
        // meses" cuando no se mandaba fecha: los ensayos más viejos eran
        // invisibles y nada en la pantalla lo decía. Acá el rango se pide o no
        // se aplica.
        $query->when($request->filled('from'), fn ($q) => $q->whereDate('run_date', '>=', $request->from));
        $query->when($request->filled('to'), fn ($q) => $q->whereDate('run_date', '<=', $request->to));

        $sort = in_array($request->get('sort'), ['run_date', 'status', 'id'], true)
            ? $request->get('sort') : 'run_date';
        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        return Inertia::render('Worksheets/Index', [
            'worksheets' => $query->orderBy($sort, $direction)->orderBy('id', 'desc')
                ->paginate($perPage)->withQueryString(),
            'tests'      => TestDefinition::where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'slug', 'code', 'name']),
            'statuses'   => Worksheet::STATUSES,
            'filters'    => $request->only(['test_definition', 'status', 'from', 'to', 'sort', 'direction', 'per_page']),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Worksheets/Form', [
            'worksheet' => null,
            'tests'     => TestDefinition::where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'slug', 'code', 'name']),
            'selected'  => $request->get('test'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'test_definition_id' => ['required', 'integer', Rule::exists('test_definitions', 'id')],
            'run_date'           => ['required', 'date'],
            'analyst_id'         => ['nullable', 'integer', Rule::exists('users', 'id')],
            'ambient_temp_c'     => ['nullable', 'numeric'],
            'ambient_humidity'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'              => ['nullable', 'string', 'max:2000'],
        ]);

        // El analista por omisión es quien abre la hoja, que es lo que pasa el
        // 99% de las veces. Se deja cambiar porque el supervisor a veces abre
        // la hoja del turno de otro.
        $data['analyst_id'] ??= $request->user()?->id;
        $data['created_by'] = $request->user()?->id;

        $worksheet = Worksheet::create($data);

        return redirect()
            ->route('lab_management.worksheets.show', $worksheet)
            ->with('success', __('worksheets.created'));
    }

    public function show(Worksheet $worksheet)
    {
        $worksheet->load([
            'definition.fields.options',
            'analyst:id,name',
            'validator:id,name',
            'rows' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'rows.values',
            'rows.instrument:id,name,code',
        ]);

        return Inertia::render('Worksheets/Show', [
            'worksheet'   => $worksheet,
            'fields'      => $worksheet->definition->fields,
            'fieldTypes'  => config('lab_field_types'),
            'instruments' => Instrument::where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'code', 'calibration_due_at']),
            'can'         => [
                'edit'     => $worksheet->isEditable() && $this->allows('worksheets.edit'),
                'close'    => $worksheet->isEditable() && $this->allows('worksheets.edit'),
                'validate' => $worksheet->status === Worksheet::STATUS_CLOSED
                    && $this->allows('worksheets.validate'),
                'void'     => $this->allows('worksheets.delete'),
            ],
            // Lo que le falta a la hoja para admitir muestras. Se manda como
            // dato y no como mensaje armado para que la pantalla explique el
            // porqué en vez de limitarse a deshabilitar una opción, que es lo
            // que hacía el sistema viejo.
            'missing'     => $worksheet->missingPrerequisites(),
        ]);
    }

    /** Alta o edición de una fila con todos sus valores. */
    public function saveRow(Request $request, Worksheet $worksheet): RedirectResponse
    {
        $data = $request->validate([
            'row_id'        => ['nullable', 'integer'],
            'kind'          => ['required', Rule::in(WorksheetRow::KINDS)],
            'sample_code'   => ['nullable', 'string', 'max:60'],
            'position'      => ['nullable', 'integer', 'min:0'],
            'instrument_id' => ['nullable', 'integer', Rule::exists('instruments', 'id')],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'values'        => ['array'],
        ]);

        $row = null;
        if (! empty($data['row_id'])) {
            $row = $worksheet->rows()->findOrFail($data['row_id']);
        }

        $this->service->saveRow(
            $worksheet,
            collect($data)->except(['row_id', 'values'])->all(),
            $data['values'] ?? [],
            $row,
        );

        return back()->with('success', __('worksheets.row_saved'));
    }

    public function destroyRow(Worksheet $worksheet, WorksheetRow $row): RedirectResponse
    {
        abort_unless($row->worksheet_id === $worksheet->id, 404);

        if (! $worksheet->isEditable()) {
            return back()->withErrors(['worksheet' => __('worksheets.errors.not_draft')]);
        }

        $row->delete();

        return back()->with('success', __('worksheets.row_deleted'));
    }

    public function close(Worksheet $worksheet): RedirectResponse
    {
        $this->service->close($worksheet);

        return back()->with('success', __('worksheets.closed'));
    }

    public function validateSheet(Worksheet $worksheet): RedirectResponse
    {
        $this->service->validate($worksheet);

        return back()->with('success', __('worksheets.validated'));
    }

    public function void(Request $request, Worksheet $worksheet): RedirectResponse
    {
        $data = $request->validate([
            'void_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $this->service->void($worksheet, $data['void_reason']);

        return back()->with('success', __('worksheets.voided'));
    }

    private function allows(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }
}
