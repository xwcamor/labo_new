<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\OilType;
use App\Models\Reception;
use App\Models\Sample;
use App\Models\Sampler;
use App\Models\SampleTest;
use App\Models\TestDefinition;
use App\Models\User;
use App\Services\Lab\ReceptionService;
use App\Services\Lab\SampleNumberAllocator;
use App\Services\Lab\SampleProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * La recepción de muestras.
 *
 * Como la bancada, este controlador NO se generó con el scaffold: una recepción
 * no es un catálogo, es un flujo —registrar, confirmar, asignar equipo, pedir
 * pruebas— y ese flujo vive en `ReceptionService`.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA FICHA NO ESCRIBE NADA                                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Es la diferencia que más importa respecto del sistema anterior. Allá abrir una
 * remisión ejecutaba `Rem.update` y `update_all` desde el propio ERB de la
 * vista: unas 320 consultas y 40 escrituras para una entrega de 40 muestras, y
 * el estado dependía de que alguien abriera la pantalla — si nadie la abría,
 * quedaba viejo y los filtros del listado mentían.
 *
 * Acá `show()` lee y nada más. El avance de todas las muestras sale de UNA
 * consulta agregada (`SampleProgressService::receptionBreakdown`).
 */
class ReceptionController extends Controller
{
    public function __construct(
        private readonly ReceptionService $service,
        private readonly SampleProgressService $progress = new SampleProgressService(),
        private readonly SampleNumberAllocator $allocator = new SampleNumberAllocator(),
    ) {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200], true) ? $perPage : 25;

        $query = Reception::query()
            ->with(['customer:id,name', 'sampler:id,name'])
            ->withCount([
                'samples',
                // Lo que falta por ensayar, de una. Es lo que el laboratorio
                // mira para saber qué entregas siguen abiertas.
                'sampleTests as outstanding_count' => fn ($q) => $q->whereIn(
                    'sample_tests.status',
                    [SampleTest::STATUS_PENDING, SampleTest::STATUS_IN_PROGRESS]
                ),
            ]);

        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->status));
        $query->when($request->filled('customer'), fn ($q) => $q->whereHas(
            'customer',
            fn ($c) => $c->where('slug', $request->customer)
        ));
        $query->when($request->filled('from'), fn ($q) => $q->whereDate('received_at', '>=', $request->from));
        $query->when($request->filled('to'), fn ($q) => $q->whereDate('received_at', '<=', $request->to));
        $query->when($request->boolean('urgent'), fn ($q) => $q->where('is_urgent', true));

        // Buscar por número de muestra. Es lo que el cliente cita por teléfono,
        // y por eso es una búsqueda por COLUMNA y no un parseo de la cadena:
        // el sistema anterior partía "2026-0695" en tres lugares distintos,
        // cada uno con su propia forma de romperse.
        $query->when($request->filled('sample'), fn ($q) => $q->whereHas(
            'samples',
            fn ($s) => $s->where('code', 'like', '%' . $request->sample . '%')
        ));

        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        $receptions = $query->orderBy('received_at', $direction)->orderByDesc('id')
            ->paginate($perPage)->withQueryString();

        return Inertia::render('Receptions/Index', [
            'receptions' => $receptions,
            'filters'    => $request->only(['status', 'customer', 'from', 'to', 'urgent', 'sample', 'direction', 'per_page']),
            'customers'  => Customer::orderBy('name')->get(['id', 'slug', 'name']),
            'statuses'   => Reception::STATUSES,
        ]);
    }

    public function create()
    {
        return Inertia::render('Receptions/Form', [
            'reception' => null,
            'customers' => Customer::orderBy('name')->get(['id', 'slug', 'name']),
            'samplers'  => Sampler::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code']),
            // Solo para mostrarlo: entre que se ve y se confirma pueden entrar
            // otras recepciones, así que el número real es el que se emite.
            'nextNumber' => Sample::formatCode(
                (int) now()->year,
                $this->allocator->peek(auth()->user()?->tenant_id, (int) now()->year)
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $reception = Reception::create($data + [
            'slug'       => Str::random(22),
            'tenant_id'  => auth()->user()?->tenant_id,
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('lab_management.receptions.show', $reception)
            ->with('success', __('receptions.created'));
    }

    public function show(Reception $reception)
    {
        $reception->load(['customer:id,name', 'sampler:id,name', 'confirmer:id,name']);

        $samples = $reception->samples()
            ->with([
                'equipment:id,name,tag,serial',
                'oilType:id,name',
                'tests:id,sample_id,test_definition_id,status',
                'tests.definition:id,code,name',
            ])
            // Si se puede dar de baja y si hay trabajo hecho, resuelto acá: la
            // pantalla tiene que poder DECIR por qué el botón no está, y
            // contarlo fila por fila desde el navegador serían dos consultas
            // por muestra.
            ->withCount([
                'reports as issued_reports_count' => fn ($q) => $q->where('status', 'issued'),
                'reports as reports_count',
                'results as results_count',
            ])
            ->get();

        // Los informes de la entrega, en su propia pestaña. Son de las MUESTRAS,
        // no de la recepción, pero se listan acá porque es donde el laboratorio
        // trabaja la entrega: el sistema anterior tenía las mismas dos pestañas
        // (Muestras · Reportes) y por la misma razón.
        $informes = \App\Models\SampleReport::query()
            ->whereIn('sample_id', $samples->pluck('id'))
            ->with(['sample:id,code', 'issuer:id,name'])
            ->withCount(['visibilities as tests_count' => fn ($q) => $q->where('is_visible', true)])
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Receptions/Show', [
            'reception' => $reception,
            'samples'   => $samples,
            'reports'   => $informes,
            // UNA consulta para el avance de todas las muestras.
            'progress'  => $this->progress->receptionBreakdown($reception->id),
            'missing'   => $reception->missingData(),
            // Con su grupo (Físico Químico · Cromatografías · Otros): el cuadro
            // de pedido las ofrece agrupadas, y son 29. `test_group_id` va en
            // el select porque sin la clave foránea el eager-load no tiene con
            // qué buscar y el grupo llegaría nulo en todas.
            'tests'     => TestDefinition::where('is_active', true)
                ->with('group:id,name,sort_order')
                ->orderBy('sort_order')->get(['id', 'code', 'name', 'test_group_id']),
            'equipment' => $reception->customer_id
                ? Equipment::where('customer_id', $reception->customer_id)
                    ->orderBy('name')->get(['id', 'name', 'tag', 'serial'])
                // Sin cliente no se ofrece ningún equipo: ofrecer los de todos
                // es lo que permitía en el sistema anterior colgarle la muestra
                // de un cliente al transformador de otro.
                : [],
            'oilTypes'  => OilType::orderBy('name')->get(['id', 'name']),
            'nextNumber' => Sample::formatCode(
                (int) $reception->received_at->year,
                $this->allocator->peek($reception->tenant_id, (int) $reception->received_at->year)
            ),
        ]);
    }

    public function edit(Reception $reception)
    {
        return Inertia::render('Receptions/Form', [
            'reception' => $reception,
            'customers' => Customer::orderBy('name')->get(['id', 'slug', 'name']),
            'samplers'  => Sampler::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code']),
            'nextNumber' => null,
        ]);
    }

    public function update(Request $request, Reception $reception): RedirectResponse
    {
        $data = $this->validated($request);

        // Una recepción confirmada ya emitió sus correlativos: cambiarle el
        // cliente o la fecha dejaría muestras con un número de un ejercicio y
        // un dueño que no corresponden.
        if (! $reception->isDraft()) {
            unset($data['customer_id'], $data['received_at']);
        }

        $reception->update($data);

        // A la FICHA, no de vuelta al formulario. Guardar es el final de la
        // edición: quedarse en el formulario deja al usuario mirando los mismos
        // campos sin saber si el cambio entró, y lo empuja a guardar otra vez.
        // La ficha es además donde está el trabajo real de la entrega: las
        // muestras, sus equipos y las pruebas que se les piden.
        return redirect()
            ->route('lab_management.receptions.show', $reception)
            ->with('success', __('receptions.saved'));
    }

    /**
     * Confirma la entrega y emite los correlativos.
     */
    public function confirm(Request $request, Reception $reception): RedirectResponse
    {
        $request->validate([
            'samples' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $this->service->confirm($reception, (int) $request->integer('samples'));

        return back()->with('success', __('receptions.confirmed', [
            'count' => $request->integer('samples'),
        ]));
    }

    /**
     * De qué equipo se tomó una muestra.
     */
    public function assignEquipment(Request $request, Reception $reception, Sample $sample): RedirectResponse
    {
        abort_unless($sample->reception_id === $reception->id, 404);

        $request->validate([
            'equipment_id' => ['nullable', 'integer', 'exists:equipment,id'],
        ]);

        $this->service->assignEquipment($sample, $request->input('equipment_id'));

        return back()->with('success', __('receptions.equipment_saved'));
    }

    /**
     * Qué pruebas se le piden a una muestra, o a todas las de la entrega.
     */
    public function requestTests(Request $request, Reception $reception): RedirectResponse
    {
        $request->validate([
            'tests'      => ['present', 'array'],
            'tests.*'    => ['integer', 'exists:test_definitions,id'],
            'sample_id'  => ['nullable', 'integer'],
            'apply_all'  => ['nullable', 'boolean'],
        ]);

        $tests = $request->input('tests', []);

        if ($request->boolean('apply_all')) {
            $hechas = $this->service->requestTestsForMany($reception->samples()->get(), $tests);

            return back()->with('success', __('receptions.tests_saved', [
                'added' => $hechas, 'cancelled' => 0,
            ]));
        }

        $sample = $reception->samples()->findOrFail($request->integer('sample_id'));
        $resultado = $this->service->requestTests($sample, $tests);

        return back()->with('success', __('receptions.tests_saved', [
            'added' => $resultado['added'], 'cancelled' => $resultado['cancelled'],
        ]));
    }

    /**
     * Da de baja una muestra de la entrega.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ SI YA SALIÓ UN INFORME, NO SE BORRA                                  │
     * └──────────────────────────────────────────────────────────────────────┘
     * El cliente tiene un papel que cita ese número y el portal de verificación
     * responde contra el registro: borrarla convierte ese papel en un documento
     * que el propio sistema desmiente. El candado alcanza al super — no hay caso
     * en el que borrarla sea la respuesta correcta; lo que corresponde es emitir
     * un informe adicional que corrija.
     *
     * Sin informe emitido sí se puede, con su motivo, y el correlativo queda
     * quemado: nunca se reasigna a otra muestra.
     */
    public function destroySample(Request $request, Reception $reception, Sample $sample): RedirectResponse
    {
        abort_unless($sample->reception_id === $reception->id, 404);

        if ($motivo = $sample->deletionBlockedBy()) {
            return back()->withErrors(['sample' => __('receptions.delete_blocked.' . $motivo, [
                'code' => $sample->code,
            ])]);
        }

        $request->validate([
            'deleted_description' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $sample->update([
            'deleted_by'          => $request->user()?->id,
            'deleted_description' => $request->input('deleted_description'),
        ]);
        $sample->delete();

        return back()->with('success', __('receptions.sample_deleted', ['code' => $sample->code]));
    }

    public function destroy(Request $request, Reception $reception): RedirectResponse
    {
        $request->validate([
            'deleted_description' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $reception->update([
            'deleted_by'          => auth()->id(),
            'deleted_description' => $request->input('deleted_description'),
        ]);
        $reception->delete();

        return redirect()
            ->route('lab_management.receptions.index')
            ->with('success', __('receptions.deleted'));
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'code'          => ['nullable', 'string', 'max:30'],
            'service_order' => ['nullable', 'string', 'max:60'],
            'customer_id'   => ['required', 'integer', 'exists:customers,id'],
            'sampler_id'    => ['nullable', 'integer', 'exists:samplers,id'],
            'sampler_name'  => ['nullable', 'string', 'max:120'],
            'received_at'   => ['required', 'date'],
            'due_at'        => ['nullable', 'date', 'after_or_equal:received_at'],
            'packages'      => ['nullable', 'integer', 'min:0', 'max:9999'],
            'container_ok'  => ['nullable', 'boolean'],
            'volume_ok'     => ['nullable', 'boolean'],
            'label_ok'      => ['nullable', 'boolean'],
            'is_urgent'     => ['nullable', 'boolean'],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'status'        => ['nullable', Rule::in(Reception::STATUSES)],
        ]);
    }
}
