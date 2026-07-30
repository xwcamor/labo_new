<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\Sample;
use App\Models\SampleReport;
use App\Services\Lab\SampleReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edición y emisión del informe de una muestra.
 *
 * El PDF sigue viviendo en `TestReportController`: acá se administra el
 * REGISTRO —su correlativo, sus fechas, qué ensayos publica— y allá se dibuja
 * el papel.
 */
class SampleReportController extends Controller
{
    public function __construct(private readonly SampleReportService $service)
    {
    }

    /**
     * Los datos con los que se abre el formulario de alta.
     *
     * Llega por AJAX desde la ficha de la entrega: el formulario es un modal a
     * pantalla completa y pedir cuarenta campos de cabecera en el payload de
     * TODAS las muestras de la recepción sería cargar cuarenta veces algo que
     * se usa una.
     */
    public function create(Sample $sample): JsonResponse
    {
        $sample->loadMissing([
            'reception.customer:id,name,address',
            'reception.sampler:id,name',
            'equipment.equipmentType:id,name',
            'equipment.oilType:id,name',
            'equipment.brand:id,name',
            'equipment.tapChangerType:id,name',
            'equipment.preservation:id,name',
            'equipment.location:id,name',
            'tests.definition:id,code,name',
        ]);

        return response()->json($this->formulario($sample, null));
    }

    /** Los mismos datos, pero de un informe existente. */
    public function edit(SampleReport $report): JsonResponse
    {
        $report->loadMissing(['visibilities', 'sample']);
        $sample = $report->sample;

        $sample->loadMissing([
            'reception.customer:id,name,address',
            'reception.sampler:id,name',
            'equipment.equipmentType:id,name',
            'equipment.oilType:id,name',
            'equipment.brand:id,name',
            'equipment.tapChangerType:id,name',
            'equipment.preservation:id,name',
            'equipment.location:id,name',
            'tests.definition:id,code,name',
        ]);

        return response()->json($this->formulario($sample, $report));
    }

    /**
     * El LISTADO de informes del laboratorio, con las columnas del sistema
     * anterior.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ TODO EN SQL, Y NO POR UNA CUESTIÓN DE ELEGANCIA                      │
     * └──────────────────────────────────────────────────────────────────────┘
     * El sistema anterior tenía esta pantalla con DataTables, o sea trayendo las
     * filas enteras al navegador para que él las ordene y las filtre. Eso costó
     * un incidente real: el estado de cada muestra se recalculaba AL LEER, así
     * que abrir el listado de un cliente con 130 pruebas pedidas disparaba un
     * recorrido completo con escrituras, cada vez que alguien entraba.
     *
     * Acá se pagina, se ordena y se busca en la base. La pantalla recibe UNA
     * página de filas ya resueltas y no recorre nada; y sobre todo NO se escribe
     * nada por el hecho de mirar: el estado se escribió cuando ocurrió (al
     * validar la hoja, al emitir el informe).
     *
     * Los datos del equipo se leen por JOIN y no por relación anidada: son diez
     * columnas de cinco tablas distintas y con eager-load la pantalla no podría
     * ordenar por "Tipo de transformador" —el orden tiene que resolverlo el
     * motor, no el navegador—.
     */
    public function index(Request $request)
    {
        $porPagina = (int) $request->get('per_page', 25);
        $porPagina = in_array($porPagina, [10, 25, 50, 100], true) ? $porPagina : 25;

        $consulta = SampleReport::query()
            ->join('samples', 'samples.id', '=', 'sample_reports.sample_id')
            ->join('receptions', 'receptions.id', '=', 'samples.reception_id')
            ->leftJoin('customers', 'customers.id', '=', 'receptions.customer_id')
            ->leftJoin('equipment', 'equipment.id', '=', 'samples.equipment_id')
            ->leftJoin('equipment_types', 'equipment_types.id', '=', 'equipment.equipment_type_id')
            // El tipo de fluido de LA MUESTRA cuando lo declara, y si no el del
            // equipo: se puede recibir una muestra de aceite nuevo de un
            // transformador que tiene otro cargado, y el informe la juzga con los
            // límites del fluido que se ensayó.
            ->leftJoin('oil_types', fn ($j) => $j->on(
                'oil_types.id',
                '=',
                DB::raw('coalesce(samples.oil_type_id, equipment.oil_type_id)'),
            ))
            ->whereNull('samples.deleted_at')
            ->select([
                'sample_reports.id',
                'sample_reports.slug',
                'sample_reports.code',
                'sample_reports.kind',
                'sample_reports.status',
                'sample_reports.issued_at',
                'sample_reports.delivered_at',
                'samples.code as sample_code',
                'samples.sampling_reason',
                'receptions.service_order',
                'receptions.received_at',
                'customers.name as customer_name',
                'equipment.serial as equipment_serial',
                'equipment.tag as equipment_tag',
                'equipment_types.name as equipment_type',
                'oil_types.name as oil_type',
                DB::raw($this->mayorDe(['voltage_kv_hv', 'voltage_kv_lv', 'voltage_kv_tv']).' as voltage_kv'),
                DB::raw($this->mayorDe(['power_mva', 'power_mva_2', 'power_mva_3']).' as power_mva'),
            ]);

        $this->aislarPorTenant($consulta);
        $this->aplicarBusqueda($consulta, $request);
        $this->aplicarOrden($consulta, $request);

        $informes = $consulta->paginate($porPagina)->withQueryString();

        return \Inertia\Inertia::render('SampleReports/Index', [
            'reports'  => $informes,
            // `(object)` y no el array pelado: sin filtros, `only()` devuelve un
            // array VACÍO, que en JSON es `[]` y en la pantalla llega como un
            // Array de JavaScript. `filters.sort` entonces no es `undefined`,
            // es `Array.prototype.sort` —la función— y se va a la URL como
            // `sort=function sort() { [native code] }`. Un objeto vacío es un
            // objeto vacío en los dos lados.
            'filters'  => (object) $request->only(array_merge(
                array_keys(self::BUSCABLES),
                ['q', 'status', 'kind', 'sort', 'direction', 'per_page'],
            )),
            'statuses' => SampleReport::STATUSES,
            'kinds'    => [SampleReport::KIND_PRIMARY, SampleReport::KIND_ADDITIONAL],
        ]);
    }

    /**
     * El aislamiento por workspace, a mano y no por el scope global del modelo.
     *
     * `SampleReport` no lleva `BelongsToTenant` —lo lleva `Sample`, de quien
     * cuelga— y acá las muestras entran por JOIN, no por relación: un scope
     * global de `Sample` no se aplica a un JOIN. O sea que sin este filtro el
     * listado sería cross-tenant. Se filtra por la columna del INFORME, que la
     * tiene, con el mismo bypass de super que el trait.
     */
    private function aislarPorTenant($consulta): void
    {
        $usuario = auth()->user();

        if (! $usuario || $usuario->hasRole('super')) {
            return;
        }

        $consulta->where('sample_reports.tenant_id', $usuario->tenant_id);
    }

    /**
     * Las columnas por las que se busca, y CON QUÉ.
     *
     * Cada una declara su expresión SQL una sola vez: la búsqueda y el orden la
     * comparten, así que no pueden desincronizarse (que es lo que pasa cuando la
     * lista de ordenables se escribe aparte y alguien agrega una columna).
     */
    private const BUSCABLES = [
        'sample_code'      => 'samples.code',
        'code'             => 'sample_reports.code',
        'service_order'    => 'receptions.service_order',
        'customer_name'    => 'customers.name',
        'equipment_serial' => 'equipment.serial',
        'equipment_type'   => 'equipment_types.name',
        'oil_type'         => 'oil_types.name',
        'sampling_reason'  => 'samples.sampling_reason',
    ];

    /** Las que se ordenan pero no se buscan por texto (fechas y estados). */
    private const ORDENABLES = [
        'status'       => 'sample_reports.status',
        'kind'         => 'sample_reports.kind',
        'issued_at'    => 'sample_reports.issued_at',
        'delivered_at' => 'sample_reports.delivered_at',
        'received_at'  => 'receptions.received_at',
    ];

    /**
     * La MAYOR de las tensiones o de las potencias declaradas en la placa.
     *
     * El sistema anterior guardaba la placa entera en un texto ("220/60/10") y
     * en el listado imprimía `num_ten.split('/').map(&:to_f).max`. Acá cada
     * devanado tiene su columna, así que el máximo se calcula en SQL —y no en la
     * pantalla— porque esta columna se ORDENA: si el máximo lo resolviera el
     * navegador, ordenaría solo la página que tiene delante.
     *
     * `NULLIF(...,0)` para que un equipo sin placa cargada no muestre un 0 que se
     * lee como "cero kV" en vez de "no está declarado". Y `MAX` en SQLite es la
     * misma función escalar que `GREATEST` en Postgres: la suite corre en SQLite
     * y el nombre no es el mismo.
     */
    private function mayorDe(array $columnas): string
    {
        $mayor = config('database.default') === 'sqlite' ? 'max' : 'greatest';
        $partes = array_map(fn (string $c) => "coalesce(equipment.{$c}, 0)", $columnas);

        return 'nullif('.$mayor.'('.implode(', ', $partes).'), 0)';
    }

    /**
     * La búsqueda POR COLUMNA, como la del sistema anterior (una casilla debajo
     * de cada encabezado), más una global.
     *
     * Insensible a mayúsculas y a acentos en Postgres: quien busca "energia" no
     * tiene por qué escribir la tilde de "RED DE ENERGÍA".
     */
    private function aplicarBusqueda($consulta, Request $request): void
    {
        $pg = config('database.default') === 'pgsql';

        $como = function ($q, string $columna, string $valor) use ($pg) {
            $aguja = \App\Support\LikeQuery::contains($valor);

            // El `ESCAPE '\'` es lo que hace que quien busca "50%" encuentre el
            // literal "50%" y no cualquier cosa que tenga un 50. En Postgres la
            // barra ya es el escape por omisión y declararlo dentro de
            // `unaccent(...)` no está permitido.
            return $pg
                ? $q->whereRaw("unaccent(lower({$columna})) LIKE unaccent(lower(?))", [$aguja])
                : $q->whereRaw("lower({$columna}) LIKE lower(?) ESCAPE '\\'", [$aguja]);
        };

        foreach (self::BUSCABLES as $clave => $columna) {
            $consulta->when(
                $request->filled($clave),
                fn ($q) => $como($q, $columna, (string) $request->input($clave)),
            );
        }

        // El buscador de arriba mira todas las columnas de texto a la vez: es
        // como se busca cuando se tiene el número de muestra en un correo y no
        // se sabe en qué columna cae.
        $consulta->when($request->filled('q'), function ($q) use ($request, $como) {
            $q->where(function ($qq) use ($request, $como) {
                foreach (self::BUSCABLES as $columna) {
                    $qq->orWhere(fn ($x) => $como($x, $columna, (string) $request->input('q')));
                }
            });
        });

        $consulta->when($request->filled('status'), fn ($q) => $q->where('sample_reports.status', $request->input('status')));
        $consulta->when($request->filled('kind'),   fn ($q) => $q->where('sample_reports.kind', $request->input('kind')));
    }

    /** El orden. Solo columnas de la lista blanca: el resto cae al id. */
    private function aplicarOrden($consulta, Request $request): void
    {
        $columnas = array_merge(self::BUSCABLES, self::ORDENABLES, [
            'voltage_kv' => $this->mayorDe(['voltage_kv_hv', 'voltage_kv_lv', 'voltage_kv_tv']),
            'power_mva'  => $this->mayorDe(['power_mva', 'power_mva_2', 'power_mva_3']),
        ]);
        $pedido = (string) $request->get('sort', '');
        $direccion = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        // `orderByRaw` y no `orderBy`: dos de las columnas son expresiones. La
        // dirección no viene del pedido sino de la comparación de arriba, así que
        // no hay nada del usuario dentro del SQL.
        $consulta->orderByRaw(($columnas[$pedido] ?? 'sample_reports.id').' '.$direccion);

        // Desempate por id: sin él, dos informes con la misma fecha pueden caer
        // en distinto orden entre una página y la siguiente, y una fila se ve dos
        // veces mientras otra no aparece nunca.
        if ($pedido !== '') {
            $consulta->orderBy('sample_reports.id', 'desc');
        }
    }

    /**
     * Los valores que se detectaron y el análisis de resultados.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ EL TEXTO SE COMPONE SOLO, PERO LO FIRMA UNA PERSONA                  │
     * └──────────────────────────────────────────────────────────────────────┘
     * Es la pantalla "Análisis de Resultado de Resultados" del sistema
     * anterior: por familia de ensayo, los valores medidos con su valor de
     * orientación, y debajo el párrafo que va impreso. El párrafo lo propone el
     * motor a partir de qué parámetros quedaron dentro y fuera de norma, y el
     * analista lo corrige si el caso lo pide.
     *
     * Lo que sí cambia respecto del anterior: el motor no inventa. Si ninguna
     * plantilla cubre la combinación de aceite y equipo, devuelve vacío y lo
     * dice, en vez de escribir una frase genérica que después se firma sin
     * leerla.
     */
    public function analysis(SampleReport $report): JsonResponse
    {
        $report->loadMissing(['sample', 'visibilities']);

        // El BORRADOR compone el autodiagnóstico al abrirse, sin que haya que
        // pulsar nada: la pantalla existe para revisar el texto propuesto, y
        // abrirla vacía obligaba a un clic extra que además pisa lo editado (el
        // botón "Diagnóstico automático" queda para RE-generar a propósito).
        // Esta llamada respeta lo escrito a mano (`pisarEditados: false`) y no
        // toca los emitidos, que muestran su snapshot.
        if ($report->isDraft()) {
            app(\App\Services\Lab\DiagnosisTextService::class)->generate($report->sample);
        }

        // El informe emitido muestra su snapshot: esta pantalla en modo lectura
        // existe para ver QUÉ SE FIRMÓ, y recalcularlo en vivo mostraría otra
        // cosa si los datos cambiaron después de emitir.
        $datos = $report->frozenPayload()
            ?? app(\App\Services\Lab\TestReportPayload::class)->forSample($report->sample, $report);

        return response()->json([
            'code'     => $report->code,
            'sample'   => $report->sample->code,
            'editable' => $report->isDraft(),
            // Una hoja por familia, con sus filas: es el mismo corte que usa el
            // informe impreso, así que la pantalla y el papel dicen lo mismo.
            'sections' => $datos['sections'],
            'analysis' => $datos['analysis'],
            'notes'    => $datos['notes'],
            // Quiénes van a firmar el papel: la lista del módulo Firmas, en su
            // orden. Se muestra acá porque el que revisa el análisis es el que
            // responde por lo que sale con su firma — y si falta un firmante,
            // que se note ANTES de emitir, no en el PDF.
            'signers'  => \App\Models\Signature::query()
                ->where('tenant_id', $report->sample->tenant_id)
                ->where('is_active', true)
                ->with('user:id,name')
                ->orderBy('sort_order')->orderBy('id')
                ->get()
                ->map(fn ($firma) => [
                    'id'       => $firma->id,
                    'relation' => $firma->relation,
                    'name'     => $firma->printedName(),
                    'title'    => $firma->title,
                ])->values(),
        ]);
    }

    /**
     * Vuelve a componer los párrafos desde los resultados.
     *
     * Pisa lo escrito a mano: es lo que se le pide al botón. Sin ese pedido
     * explícito el motor respeta siempre lo que redactó una persona.
     */
    public function autodiagnose(SampleReport $report): RedirectResponse
    {
        if ($report->isIssued()) {
            return back()->withErrors(['status' => __('sample_reports.issued_is_final')]);
        }

        app(\App\Services\Lab\DiagnosisTextService::class)
            ->generate($report->sample, pisarEditados: true);

        return back()->with('success', __('sample_reports.diagnosed'));
    }

    /**
     * Guarda los párrafos corregidos a mano.
     *
     * Quedan marcados como editados (`is_edited`), y por eso el motor no los
     * vuelve a pisar por su cuenta: el papel dice lo que el analista decidió,
     * no lo último que compuso una fórmula.
     */
    public function saveAnalysis(Request $request, SampleReport $report): RedirectResponse
    {
        if ($report->isIssued()) {
            return back()->withErrors(['status' => __('sample_reports.issued_is_final')]);
        }

        $datos = $request->validate([
            'bodies'   => ['present', 'array'],
            'bodies.*' => ['nullable', 'string', 'max:4000'],
        ]);

        foreach ($datos['bodies'] as $familia => $texto) {
            \App\Models\SampleDiagnosis::updateOrCreate(
                ['sample_id' => $report->sample_id, 'family' => (string) $familia],
                [
                    'body'      => $texto,
                    'is_edited' => true,
                    'edited_by' => $request->user()?->id,
                    'tenant_id' => $report->tenant_id,
                ],
            );
        }

        return back()->with('success', __('sample_reports.analysis_saved'));
    }

    public function store(Request $request, Sample $sample): RedirectResponse
    {
        $datos = $this->validated($request);

        $informe = $this->service->create($sample, $datos, $request->user()?->id);

        return back()->with('success', __('sample_reports.created', ['code' => $informe->code]));
    }

    public function update(Request $request, SampleReport $report): RedirectResponse
    {
        // Un informe emitido no se edita: el papel ya salió con ese contenido y
        // ese número. Lo que corresponde es emitir un adicional.
        if ($report->isIssued()) {
            return back()->withErrors(['status' => __('sample_reports.issued_is_final')]);
        }

        $this->service->update($report, $this->validated($request));

        return back()->with('success', __('sample_reports.saved'));
    }

    /**
     * Emitir: el papel sale a la calle.
     *
     * A partir de acá el informe deja de ser editable y su contenido queda
     * congelado en `snapshot`, para que reimprimirlo dentro de dos años dé el
     * mismo papel aunque el equipo haya cambiado de TAG.
     */
    public function issue(Request $request, SampleReport $report): RedirectResponse
    {
        // La emisión entera vive en el servicio: congelar el contenido, marcar
        // informados los ensayos y auditar tienen que pasar juntos, y hay más de
        // una vía que emite (esta pantalla, el sembrador de demostración).
        $emitido = $this->service->issue($report, $request->user()?->id, [
            'url'        => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        if (! $emitido) {
            return back()->withErrors(['status' => __('sample_reports.already_issued')]);
        }

        return back()->with('success', __('sample_reports.issued', ['code' => $report->code]));
    }

    /**
     * Dar de baja un informe.
     *
     * Un informe EMITIDO no se borra ni siquiera acá: el cliente tiene un papel
     * con ese código y el portal de verificación tiene que seguir
     * encontrándolo. Un borrador sí, con su motivo.
     */
    public function destroy(Request $request, SampleReport $report): RedirectResponse
    {
        if ($report->isIssued()) {
            return back()->withErrors(['status' => __('sample_reports.issued_cannot_delete')]);
        }

        $request->validate([
            'deleted_description' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $report->update([
            'deleted_by'          => $request->user()?->id,
            'deleted_description' => $request->input('deleted_description'),
        ]);
        $report->delete();

        return back()->with('success', __('sample_reports.deleted'));
    }

    /**
     * La cabecera con la que se abre el formulario.
     *
     * Sale de donde vive cada dato —recepción, muestra, equipo— y no de una
     * copia guardada en el informe: así el formulario muestra lo último que se
     * sabe y no una fotocopia de cuando se creó el borrador.
     *
     * @return array<string,mixed>
     */
    private function formulario(Sample $sample, ?SampleReport $report): array
    {
        $eq = $sample->equipment;
        $re = $sample->reception;

        $visibles = $report
            ? $report->visibilities->pluck('is_visible', 'sample_test_id')->all()
            : [];

        return [
            'report' => $report ? [
                'slug'         => $report->slug,
                'code'         => $report->code,
                'kind'         => $report->kind,
                'status'       => $report->status,
                'issued_at'    => $report->issued_at?->toDateString(),
                'delivered_at' => $report->delivered_at?->toDateString(),
                'notes'        => $report->notes,
            ] : null,

            // Lo que NO se edita: identifica al registro y cambiarlo rompería la
            // trazabilidad. Se muestra para que el operador confirme que está en
            // la muestra correcta.
            'readonly' => [
                'sample_code'   => $sample->code,
                'customer_name' => ($re?->customer?->name) ?? $eq?->customer?->name,
                'serial'        => $eq?->serial,
                'reception'     => $re?->code,
            ],

            'header' => [
                // De la entrega
                'service_order' => $re?->service_order,
                'contact_info'  => $re?->contact_info,
                'end_user'      => $re?->end_user,
                'received_at'   => $re?->received_at?->toDateString(),
                'sampler'       => $re?->sampler?->name ?? $re?->sampler_name,

                // De la muestra
                'report_number'     => $sample->report_number,
                'description'       => $sample->description,
                'sampling_reason'   => $sample->sampling_reason,
                'sampling_point'    => $sample->sampling_point,
                'sampled_at'        => $sample->sampled_at?->toDateString(),
                'oil_temp_c'        => $sample->oil_temp_c,
                'equipment_temp_c'  => $sample->equipment_temp_c,
                'ambient_temp_c'    => $sample->ambient_temp_c,
                'relative_humidity' => $sample->relative_humidity,

                // Del equipo
                'location'         => $eq?->location?->name,
                'tag'              => $eq?->tag,
                'equipment_type'   => $eq?->equipmentType?->name,
                'brand'            => $eq?->brand?->name,
                'oil_type'         => $eq?->oilType?->name,
                'oil_brand'        => $eq?->oil_brand,
                'voltage_kv_hv'    => $eq?->voltage_kv_hv,
                'voltage_kv_lv'    => $eq?->voltage_kv_lv,
                'voltage_kv_tv'    => $eq?->voltage_kv_tv,
                'power_mva'        => $eq?->power_mva,
                'voltage_label'    => $eq?->voltage_label,
                'power_label'      => $eq?->power_label,
                'manufacture_year' => $eq?->manufacture_year,
                'tap_changer'      => $eq?->tapChangerType?->name,
                'preservation'     => $eq?->preservation?->name,
                'oil_volume'       => $eq?->oil_volume,
                'oil_volume_unit'  => $eq?->oil_volume_unit,
                'has_equipment'    => $eq !== null,
            ],

            // Las pruebas pedidas, con su estado: el operador ve cuáles todavía
            // no están validadas y por qué no van a salir impresas aunque las
            // deje marcadas.
            'tests' => $sample->tests->map(fn ($t) => [
                'id'         => $t->id,
                'name'       => $t->definition?->name,
                'code'       => $t->definition?->code,
                'status'     => $t->status,
                'is_visible' => $visibles[$t->id] ?? true,
            ])->values(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'issued_at'    => ['nullable', 'date'],
            'delivered_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'notes'        => ['nullable', 'string', 'max:2000'],

            'service_order' => ['nullable', 'string', 'max:60'],
            'contact_info'  => ['nullable', 'string', 'max:1000'],
            'end_user'      => ['nullable', 'string', 'max:255'],

            'report_number'   => ['nullable', 'string', 'max:40'],
            'description'     => ['nullable', 'string', 'max:1000'],
            'sampling_reason' => ['nullable', 'string', 'max:80'],
            'sampling_point'  => ['nullable', 'string', 'max:80'],
            'sampled_at'      => ['nullable', 'date'],

            // Las condiciones de campo son medidas, no casillas: el rango evita
            // el 30000 de un dedo pesado, y el nulo se distingue del cero. En el
            // sistema anterior se guardaban como texto y se imprimían con
            // `to_f`, así que un campo vacío salía "0.00".
            'oil_temp_c'        => ['nullable', 'numeric', 'between:-50,250'],
            'equipment_temp_c'  => ['nullable', 'numeric', 'between:-50,250'],
            'ambient_temp_c'    => ['nullable', 'numeric', 'between:-50,80'],
            'relative_humidity' => ['nullable', 'numeric', 'between:0,100'],

            'oil_brand'        => ['nullable', 'string', 'max:120'],
            'manufacture_year' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'oil_volume'       => ['nullable', 'numeric', 'min:0', 'max:999999'],

            'tests'   => ['nullable', 'array'],
            'tests.*' => ['integer'],
        ]);
    }
}
