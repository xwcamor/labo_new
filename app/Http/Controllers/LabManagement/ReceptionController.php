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
    use \App\Traits\BuildsRecordAudit;
    use \App\Http\Controllers\Concerns\HandlesRecordLocking;

    /**
     * El candado de la recepción (super → nivel sistema; admin → su workspace).
     *
     * El modelo ya usaba el trait `Lockable` —tiene las columnas y todo— pero no
     * había rutas ni botón: el candado existía y no se podía poner. Una recepción
     * confirmada con sus correlativos emitidos es justamente el registro que hay
     * que poder congelar.
     */
    public function lock(Request $request, Reception $reception): RedirectResponse
    {
        return $this->applyLock($reception, $request);
    }

    /** Saca el candado (un admin no puede quitar el candado del super). */
    public function unlock(Request $request, Reception $reception): RedirectResponse
    {
        return $this->applyUnlock($reception, $request);
    }

    public function __construct(
        private readonly ReceptionService $service,
        private readonly SampleProgressService $progress = new SampleProgressService(),
        private readonly SampleNumberAllocator $allocator = new SampleNumberAllocator(),
        private readonly \App\Services\Lab\ReceptionNumberAllocator $receptionNumbers = new \App\Services\Lab\ReceptionNumberAllocator(),
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
                // El semáforo de avance por fila — los cuatro chequeos que el
                // sistema anterior mostraba como iconos (Series · Trabajos ·
                // Datos · Informes), pero derivados en LA MISMA consulta del
                // listado. Allá eran banderas cacheadas en `rems` que solo se
                // refrescaban cuando alguien ABRÍA la remisión (los Rem.update
                // vivían en el ERB de la ficha), así que el listado mentía
                // hasta la próxima visita.
                //
                // Muestras sin equipo enlazado (el "Series" del viejo: su
                // correlativo sin transformador). Sin el enlace, el resultado
                // no llega al informe ni a la tendencia del equipo.
                'samples as unlinked_count' => fn ($q) => $q->whereNull('equipment_id'),
                // Muestras a las que nadie les pidió ningún ensayo (el
                // "Trabajos" del viejo). Un correlativo emitido sin pruebas
                // pedidas es un frasco que nadie va a procesar.
                'samples as untested_count' => fn ($q) => $q->whereDoesntHave(
                    'tests',
                    fn ($t) => $t->where('status', '!=', SampleTest::STATUS_CANCELLED)
                ),
                // Muestras con TODO informado (el "Informes" del viejo, que
                // comparaba informes emitidos contra la cantidad pactada).
                'samples as reported_count' => fn ($q) => $q->where('status', Sample::STATUS_REPORTED),
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

        // ┌──────────────────────────────────────────────────────────────────┐
        // │ EL ORDEN, POR LISTA BLANCA                                       │
        // └──────────────────────────────────────────────────────────────────┘
        // Acá se ordenaba SIEMPRE por fecha de recepción y se ignoraba qué
        // columna había pulsado el usuario. Con una sola columna ordenable eso
        // no se notaba; al agregar la fecha comprometida, pulsar su encabezado
        // reordenaba por otra cosa —la tabla marcaba la flecha en una columna y
        // las filas salían ordenadas por otra—, que es peor que no dejar
        // ordenar.
        //
        // Lista blanca y no la columna cruda del pedido: lo que llega de la URL
        // no entra al SQL.
        $ordenables = ['received_at', 'due_at', 'status'];
        $pedido = (string) $request->get('sort', '');
        $columna = in_array($pedido, $ordenables, true) ? $pedido : 'received_at';

        $receptions = $query->orderBy($columna, $direction)->orderByDesc('id')
            ->paginate($perPage)->withQueryString();

        return Inertia::render('Receptions/Index', [
            'receptions' => $receptions,
            'filters'    => $request->only(['status', 'customer', 'from', 'to', 'urgent', 'sample', 'sort', 'direction', 'per_page']),
            'customers'  => Customer::orderBy('name')->get(['id', 'slug', 'name']),
            'statuses'   => Reception::STATUSES,
        ]);
    }

    public function create()
    {
        return Inertia::render('Receptions/Form', [
            'reception' => null,
            'customers' => Customer::orderBy('name')->get(['id', 'slug', 'name']),
            // Sin `catalogs` ni descripción por omisión: el motivo, el punto de
            // muestreo y la descripción son de la MUESTRA, y la muestra todavía
            // no existe acá (se crea al confirmar). Se cargan en el formulario
            // del informe, que es donde el laboratorio los completa.
            'samplers'  => Sampler::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code']),
            // Quiénes pueden autorizar el ingreso: el catálogo «Personal que
            // autoriza» (el `rem_user_signatures` del sistema anterior, donde
            // este campo era obligatorio y su firma salía en el acta).
            'authorizers' => \App\Models\EntryAuthorizer::where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        // ┌──────────────────────────────────────────────────────────────────┐
        // │ EL N° DE RECEPCIÓN SE GENERA, NO SE ESCRIBE                      │
        // └──────────────────────────────────────────────────────────────────┘
        // Era un campo de texto libre que el operador tenía que inventar (el
        // sistema anterior directamente no lo tenía). Ahora sale de su propio
        // contador por workspace y año, DENTRO de la transacción que crea la
        // entrega: si la creación falla, la reserva se deshace con ella.
        //
        // El año sale de la FECHA DE RECEPCIÓN, igual que el correlativo de
        // las muestras: una entrega recibida el 30 de diciembre y registrada
        // el 2 de enero pertenece al ejercicio en que entró al laboratorio.
        $reception = \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $tenantId = auth()->user()?->tenant_id;
            $year     = \Illuminate\Support\Carbon::parse($data['received_at'])->year;
            $number   = $this->receptionNumbers->next($tenantId, $year);

            return Reception::create($data + [
                'slug'       => Str::random(22),
                'tenant_id'  => $tenantId,
                'created_by' => auth()->id(),
                'year'       => $year,
                'number'     => $number,
                'code'       => \App\Services\Lab\ReceptionNumberAllocator::format($year, $number),
            ]);
        });

        return redirect()
            ->route('lab_management.receptions.show', $reception)
            ->with('success', __('receptions.created'));
    }

    public function show(Request $request, Reception $reception)
    {
        $reception->load(['customer:id,name', 'sampler:id,name', 'authorizer:id,name', 'confirmer:id,name']);

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
        // Las columnas son las del "Listado de Nº de Reportes" del sistema
        // anterior: además del correlativo y el estado, el equipo del que salió
        // la muestra —serie y tipo—, el fluido, la razón del análisis y las TRES
        // fechas (recepción, emisión, entrega). Esta pestaña traía seis columnas
        // y faltaban justamente las que se usan para encontrar un informe cuando
        // el cliente llama citando el transformador y no el número.
        $informes = \App\Models\SampleReport::query()
            ->whereIn('sample_id', $samples->pluck('id'))
            ->with([
                'sample:id,code,sampling_reason,equipment_id,oil_type_id,reception_id',
                'sample.equipment:id,serial,tag,equipment_type_id',
                'sample.equipment.equipmentType:id,name',
                'sample.oilType:id,name',
                'sample.equipment.oilType:id,name',
                'issuer:id,name',
            ])
            ->withCount(['visibilities as tests_count' => fn ($q) => $q->where('is_visible', true)])
            ->orderByDesc('id')
            ->get()
            // Se aplanan acá y no en la pantalla: la tabla ORDENA por estas
            // columnas, y una ruta anidada (`sample.equipment.serial`) no se
            // puede ordenar ni buscar como celda.
            ->map(function ($informe) use ($reception) {
                $muestra = $informe->sample;

                return array_merge($informe->toArray(), [
                    'sample_code'      => $muestra?->code,
                    'sampling_reason'  => $muestra?->sampling_reason,
                    'equipment_serial' => $muestra?->equipment?->serial,
                    'equipment_tag'    => $muestra?->equipment?->tag,
                    'equipment_type'   => $muestra?->equipment?->equipmentType?->name,
                    // El fluido de LA MUESTRA cuando lo declara, y si no el del
                    // equipo: se puede recibir aceite nuevo de un transformador
                    // que tiene otro cargado.
                    'oil_type'         => $muestra?->oilType?->name ?? $muestra?->equipment?->oilType?->name,
                    'received_at'      => $reception->received_at?->toDateString(),
                    // De esto depende que aparezca el botón de emitir: un
                    // informe cuyo análisis nadie dio por bueno no sale.
                    'analysis_confirmed' => $informe->analysisIsConfirmed(),
                ]);
            });

        return Inertia::render('Receptions/Show', [
            'reception' => $reception,
            // La entrega es un registro trazable como cualquier otro: quién la
            // registró, quién la confirmó, qué se le cambió después. El modelo
            // ya escribía la auditoría; la ficha no la mostraba.
            'recordAudit' => $this->recordAuditMeta($reception),
            'activity'    => $this->recordActivity($reception, $request),
            // El candado: el modelo usa `Lockable` desde el principio, pero la
            // ficha no traía su estado y por eso el botón no existía.
            'lock'        => $this->lockMeta($reception->load('locker:id,name'), $request),
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
            // Sin `catalogs`: ver el comentario en `create()`.
            'samplers'  => Sampler::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code']),
            // Al editar, además del catálogo vigente, el autorizador YA elegido
            // aunque después lo hayan puesto inactivo: si no, abrir el
            // formulario lo mostraría vacío y guardarlo lo perdería.
            'authorizers' => \App\Models\EntryAuthorizer::where('is_active', true)
                ->when($reception->authorized_by_id, fn ($q) => $q->orWhere('id', $reception->authorized_by_id))
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
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

    /**
     * La entrega en Excel: sus muestras con el equipo, lo pedido y el avance.
     *
     * Es la descarga que el sistema anterior tenía y que el laboratorio adjunta
     * a un correo cuando el cliente pregunta en qué van sus muestras. Sin ella la
     * respuesta era una captura de pantalla.
     */
    public function export(Reception $reception): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $nombre = 'recepcion-' . ($reception->code ?: $reception->id) . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\LabManagement\Receptions\ReceptionSamplesExport($reception),
            $nombre,
        );
    }

    /**
     * La confirmación de la baja, con su motivo — el estándar de los módulos.
     *
     * Faltaba: la ficha solo ofrecía "Editar", así que la única forma de dar de
     * baja una entrega era desde el listado. Y una entrega CONFIRMADA se avisa
     * aparte: sus correlativos ya están emitidos y no se reutilizan, así que la
     * baja no los devuelve al pozo.
     */
    public function delete(Reception $reception)
    {
        abort_if($reception->is_locked, 403, __('locks.cannot_delete_locked'));

        return Inertia::render('Receptions/Delete', [
            'reception' => $reception->loadCount('samples')->load('customer:id,name'),
        ]);
    }

    public function destroy(Request $request, Reception $reception): RedirectResponse
    {
        abort_if($reception->is_locked, 403, __('locks.cannot_delete_locked'));

        $request->validate([
            'deleted_description' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        // ┌──────────────────────────────────────────────────────────────────┐
        // │ CON UN INFORME EMITIDO, LA ENTREGA NO SE BORRA                   │
        // └──────────────────────────────────────────────────────────────────┘
        // Es la misma regla que ya protegía a la MUESTRA, que no se aplicaba un
        // nivel más arriba: se podía borrar la entrega y su informe emitido
        // seguía vivo, en el listado global y en el portal de verificación. El
        // cliente tiene ese papel en la mano.
        $emitidos = \App\Models\SampleReport::query()
            ->whereIn('sample_id', $reception->samples()->pluck('id'))
            ->where('status', \App\Models\SampleReport::STATUS_ISSUED)
            ->count();

        if ($emitidos > 0) {
            return back()->withErrors([
                'deleted_description' => __('receptions.delete_blocked.has_issued', ['count' => $emitidos]),
            ]);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($reception, $request) {
            $motivo = $request->input('deleted_description');

            // ┌──────────────────────────────────────────────────────────────┐
            // │ LA BAJA ARRASTRA LO QUE CUELGA                               │
            // └──────────────────────────────────────────────────────────────┘
            // Acá se daba de baja SOLO la fila de la entrega. Sus muestras
            // quedaban vivas: la bancada las seguía ofreciendo para cargar y el
            // listado global seguía mostrando sus informes. O sea que se podía
            // trabajar y emitir el papel de una entrega que ya no existe.
            //
            // El sistema anterior sí arrastraba (`rem.rb:327-339`). Se hace en
            // una transacción para que no quede a medias, y con el MISMO motivo:
            // quien mire la muestra en la papelera tiene que leer por qué se fue.
            $muestras = $reception->samples()->get();

            foreach ($muestras as $muestra) {
                \App\Models\SampleReport::where('sample_id', $muestra->id)
                    ->get()
                    ->each(function ($informe) use ($motivo) {
                        $informe->update([
                            'deleted_by'          => auth()->id(),
                            'deleted_description' => $motivo,
                        ]);
                        $informe->delete();
                    });

                $muestra->update([
                    'deleted_by'          => auth()->id(),
                    'deleted_description' => $motivo,
                ]);
                $muestra->delete();
            }

            $reception->update([
                'deleted_by'          => auth()->id(),
                'deleted_description' => $motivo,
            ]);
            $reception->delete();
        });

        return redirect()
            ->route('lab_management.receptions.index')
            ->with('success', __('receptions.deleted'));
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request): array
    {
        // Sin `code`: el N° de recepción se GENERA al crear (contador propio
        // por workspace y año) y no se acepta del formulario — un identificador
        // que se puede tipear es un identificador que se puede repetir.
        return $request->validate([
            'service_order' => ['nullable', 'string', 'max:60'],
            // El CONTACTO se anota al recibir (quien recibe tiene el correo del
            // cliente delante) y el informe lo imprime en su cabecera. El
            // USUARIO FINAL, en cambio, NO se pide acá: es un dato del informe
            // y se carga en su formulario (pedido del laboratorio, 2026-08-02).
            'contact_info'  => ['nullable', 'string', 'max:190'],
            'customer_id'   => ['required', 'integer', 'exists:customers,id'],
            // El muestreador es OBLIGATORIO (en el viejo también lo era), pero
            // por cualquiera de sus dos formas: del catálogo o el nombre suelto
            // de un externo. Exigir solo el catálogo obligaría a dar de alta a
            // cada tercero que alguna vez trae un frasco.
            'sampler_id'    => ['required_without:sampler_name', 'nullable', 'integer', 'exists:samplers,id'],
            'sampler_name'  => ['nullable', 'string', 'max:120'],
            // Quién autoriza el ingreso: obligatorio, como en el viejo
            // (`_form_new.html.erb:69`). Sale del catálogo «Personal que
            // autoriza» (`entry_authorizers`); el exists descarta borrados.
            'authorized_by_id' => [
                'required', 'integer',
                Rule::exists('entry_authorizers', 'id')->whereNull('deleted_at'),
            ],
            'received_at'   => ['required', 'date'],
            'due_at'        => ['required', 'date', 'after_or_equal:received_at'],
            // `min:1`: cero envases no es una entrega. Antes el campo era
            // opcional y aceptaba 0, y ese "no sé cuántos" reaparecía después
            // como una confirmación de muestras sin referencia.
            'packages'      => ['required', 'integer', 'min:1', 'max:9999'],
            'container_ok'  => ['nullable', 'boolean'],
            'volume_ok'     => ['nullable', 'boolean'],
            'label_ok'      => ['nullable', 'boolean'],
            'is_urgent'     => ['nullable', 'boolean'],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'status'        => ['nullable', Rule::in(Reception::STATUSES)],
        ]);
    }
}
