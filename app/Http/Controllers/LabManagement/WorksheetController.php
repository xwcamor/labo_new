<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Instrument;
use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
use App\Services\Lab\FormulaResolver;
use App\Services\Lab\ValueCoercer;
use App\Services\Lab\WorksheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * La bancada.
 *
 * Este controlador NO se generó con el scaffold, a diferencia del resto de los
 * módulos, y la diferencia es deliberada: una hoja de trabajo no es un
 * catálogo. No tiene alta masiva, ni edición en lote, ni duplicado, ni
 * importación por planilla. Tiene un flujo —se carga, publica sola en cuanto
 * está completa, y el sistema la bloquea a los N meses— y ese flujo es lo que
 * hay que cuidar.
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
 * editar. Acá cada ruta declara su permiso y esconder el botón es solo
 * cortesía.
 */
class WorksheetController extends Controller
{
    use \App\Traits\BuildsRecordAudit;
    use \App\Http\Controllers\Concerns\HandlesRecordLocking;

    public function __construct(
        private readonly WorksheetService $service,
        private readonly ValueCoercer $coercer = new ValueCoercer(),
    ) {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 25;

        $query = Worksheet::query()
            // `select` explícito ANTES de `orderByFavoriteFirst`: ese scope hace
            // left join a `user_favorites` y sin acotar el select las columnas
            // del join (id, created_at…) pisarían las de la hoja.
            ->select('worksheets.*')
            ->orderByFavoriteFirst(auth()->id())
            ->filter($request)
            ->with(['definition:id,slug,code,name', 'analyst:id,name', 'validator:id,name', 'locker:id,name'])
            ->withCount([
                'rows',
                'rows as samples_count' => fn ($q) => $q->where('kind', WorksheetRow::KIND_SAMPLE),
            ]);

        // Los filtros (rápidos + avanzados + solo favoritas) viven en el modelo
        // (`Worksheet::scopeFilter`), ya aplicados arriba — el estándar de los
        // índices, con FilterApplier contra `filterSchema()`.

        // El orden, por LISTA BLANCA: lo que llega de la URL no entra al SQL.
        $ordenables = ['run_date', 'status', 'validated_at', 'id'];
        $pedido = (string) $request->get('sort', '');
        $sort = in_array($pedido, $ordenables, true) ? $pedido : 'run_date';
        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        // `advanced_where` viaja como JSON en la URL y la pantalla lo necesita
        // como array para rehidratar el builder al recargar o compartir el enlace.
        $advanced = $request->input('advanced_where');
        if (is_string($advanced)) {
            $advanced = json_decode($advanced, true) ?: null;
        }

        return Inertia::render('Worksheets/Index', [
            'worksheets' => $query->orderBy('worksheets.' . $sort, $direction)->orderByDesc('worksheets.id')
                ->paginate($perPage)->withQueryString(),
            'filterSchema' => Worksheet::filterSchema(),
            // Los analistas que EFECTIVAMENTE corrieron alguna hoja. Ofrecer los
            // 40 usuarios del sistema en un desplegable donde 35 no tienen ni
            // una hoja es ofrecer 35 filtros que devuelven vacío.
            'analysts'   => \App\Models\User::query()
                ->whereIn('id', Worksheet::query()->whereNotNull('analyst_id')->distinct()->pluck('analyst_id'))
                ->orderBy('name')->get(['id', 'name']),
            'tests'      => TestDefinition::where('is_active', true)
                // Con su grupo, para ofrecerlas agrupadas (Físico Químico ·
                // Cromatografías · Otros): son 29 y en lista plana no se
                // encuentran. `test_group_id` va en el select porque sin la
                // clave foránea el eager-load no tiene con qué buscar y el
                // grupo llegaría nulo en todas.
                ->with('group:id,name,sort_order')
                ->orderBy('sort_order')->get(['id', 'slug', 'code', 'name', 'test_group_id']),
            'statuses'   => Worksheet::STATUSES,
            'filters'    => array_merge(
                $request->only([
                    'test_definition', 'status', 'from', 'to', 'analyst', 'sample',
                    'only_favorites', 'per_page',
                ]),
                [
                    'advanced_where' => is_array($advanced) ? $advanced : null,
                    // El orden que se devuelve es el RESUELTO, no el pedido: la
                    // pantalla lo reenvía en cada navegación siguiente, así que
                    // devolverle una columna que el servidor descartó la haría
                    // insistir con ella para siempre.
                    'sort'      => $sort,
                    'direction' => $direction,
                ],
            ),
            'can'        => [
                'create' => $this->allows('worksheets.create'),
                'edit'   => $this->allows('worksheets.edit'),
                'delete' => $this->allows('worksheets.delete'),
            ],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Worksheets/Form', [
            'worksheet' => null,
            'tests'     => TestDefinition::where('is_active', true)
                // Con su grupo, para ofrecerlas agrupadas (Físico Químico ·
                // Cromatografías · Otros): son 29 y en lista plana no se
                // encuentran. `test_group_id` va en el select porque sin la
                // clave foránea el eager-load no tiene con qué buscar y el
                // grupo llegaría nulo en todas.
                ->with('group:id,name,sort_order')
                ->orderBy('sort_order')->get(['id', 'slug', 'code', 'name', 'test_group_id']),
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
            // La presión del laboratorio, que el informe de cromatografía
            // imprime. El rango cubre desde el nivel del mar (del orden de
            // 1013 hPa) hasta la sierra alta: fuera de eso es un error de dedo.
            'lab_pressure_hpa'   => ['nullable', 'numeric', 'min:500', 'max:1100'],
            'sample_temp_c'      => ['nullable', 'numeric'],
            'notes'              => ['nullable', 'string', 'max:2000'],
        ]);

        // El analista por omisión es quien abre la hoja, que es lo que pasa el
        // 99% de las veces. Se deja cambiar porque el supervisor a veces abre
        // la hoja del turno de otro.
        $data['analyst_id'] ??= $request->user()?->id;
        $data['created_by'] = $request->user()?->id;

        $worksheet = Worksheet::create($data);

        // El patrón y el duplicado que la prueba EXIGE quedan puestos, vacíos.
        // El porqué está en `WorksheetService::seedRequiredRows`: el sistema ya
        // los reclama antes de admitir la primera muestra, así que hacérselos
        // agregar de a uno era un trámite que él mismo imponía.
        $puestas = $this->service->seedRequiredRows($worksheet->load('definition'));

        return redirect()
            ->route('lab_management.worksheets.show', $worksheet)
            ->with('success', $puestas === []
                ? __('worksheets.created')
                : __('worksheets.created_with_rows', [
                    'kinds' => implode(', ', array_map(
                        fn (string $k) => __("worksheets.kind.{$k}"),
                        $puestas
                    )),
                ]));
    }

    /**
     * Qué equipos ofrece cada columna de equipo de esta prueba.
     *
     * La columna sin equipos declarados no aparece en el mapa, y la pantalla cae
     * a la lista completa. Declarar "ninguno" y "todos" con la misma ausencia
     * sería ambiguo, pero acá no lo es: una columna de equipo SIEMPRE tiene que
     * ofrecer algo, o el analista no puede registrar con qué midió.
     *
     * @return array<int,\Illuminate\Support\Collection>
     */
    private function instrumentsByField(Worksheet $worksheet): array
    {
        $salida = [];

        foreach ($worksheet->definition->fields as $field) {
            if ($field->type !== 'instrument') {
                continue;
            }

            $propios = $field->instruments;

            if ($propios->isNotEmpty()) {
                $salida[$field->id] = $propios->map(fn ($i) => $i->only([
                    'id', 'name', 'description', 'calibration_due_at',
                ]))->values();
            }
        }

        return $salida;
    }

    /**
     * Editar la CABECERA de la hoja: fecha, analista, condiciones y notas.
     *
     * Los VALORES no se editan acá —viven en la grilla de la ficha, que es la
     * bancada—. Esto existe porque la cabecera también se corrige (la humedad
     * mal tipeada, el analista del turno equivocado) y porque la temperatura de
     * la muestra se imprime en el informe y no tenía ningún campo donde
     * cargarse.
     */
    public function edit(Worksheet $worksheet)
    {
        abort_if($worksheet->is_locked, 403, __('locks.cannot_edit_locked'));

        return Inertia::render('Worksheets/Form', [
            // Con su prueba: el formulario la muestra fija (no se cambia), y no
            // puede depender de la lista de activas — la prueba de una hoja
            // vieja pudo haberse desactivado después.
            'worksheet' => $worksheet->load(['analyst:id,name', 'definition:id,name']),
            'tests'     => TestDefinition::where('is_active', true)
                ->with('group:id,name,sort_order')
                ->orderBy('sort_order')->get(['id', 'slug', 'code', 'name', 'test_group_id']),
            'selected'  => null,
        ]);
    }

    public function update(Request $request, Worksheet $worksheet): RedirectResponse
    {
        abort_if($worksheet->is_locked, 403, __('locks.cannot_edit_locked'));

        // La PRUEBA no se cambia: la hoja ya tiene filas cargadas contra las
        // columnas de esa plantilla, y moverla a otra dejaría los valores
        // apuntando a columnas que no existen.
        $data = $request->validate([
            'run_date'         => ['required', 'date'],
            'analyst_id'       => ['nullable', 'integer', Rule::exists('users', 'id')],
            'ambient_temp_c'   => ['nullable', 'numeric'],
            'ambient_humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lab_pressure_hpa' => ['nullable', 'numeric', 'min:500', 'max:1100'],
            'sample_temp_c'    => ['nullable', 'numeric'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ]);

        $worksheet->update($data);

        return redirect()
            ->route('lab_management.worksheets.show', $worksheet)
            ->with('success', __('worksheets.saved'));
    }

    /** La confirmación de la baja, con su motivo — el estándar de los módulos. */
    public function delete(Worksheet $worksheet)
    {
        abort_if($worksheet->is_locked, 403, __('locks.cannot_delete_locked'));

        return Inertia::render('Worksheets/Delete', [
            'worksheet' => $worksheet->load(['definition:id,name', 'analyst:id,name']),
        ]);
    }

    // El candado del supervisor (trait Lockable): congela la grilla y la
    // cabecera. Es el mismo candado que el bloqueo automático por antigüedad
    // pone solo; acá se pone y se saca a mano, y queda auditado.
    public function lock(Request $request, Worksheet $worksheet): RedirectResponse
    {
        return $this->applyLock($worksheet, $request);
    }

    public function unlock(Request $request, Worksheet $worksheet): RedirectResponse
    {
        return $this->applyUnlock($worksheet, $request);
    }

    public function show(Request $request, Worksheet $worksheet)
    {
        $worksheet->load([
            'definition.fields.options',
            // Qué equipos ofrece CADA columna. Sin esto la grilla ofrecía todos
            // los del laboratorio en todas las columnas de equipo, y en la
            // columna "Bureta" del Número Ácido aparecía el Colorímetro.
            'definition.fields.instruments:id,name,description,calibration_due_at',
            'analyst:id,name',
            'validator:id,name',
            'rows' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'rows.values',
            'rows.instrument:id,name,description',
            'rows.equipment:id,name,serial,tag',
            // El equipo VIVO de la muestra, para la celda de equipo: la fila
            // guarda una foto de cuando se creó, pero el que manda —y el que
            // usa el materializador— es el de la muestra, que la recepción
            // asigna DESPUÉS de cargar la bancada. Sin esto la celda mostraba
            // "Sin equipo" sobre una muestra que ya tenía su transformador.
            'rows.sample:id,code,equipment_id',
            'rows.sample.equipment:id,name,serial,tag',
        ]);

        return Inertia::render('Worksheets/Show', [
            'worksheet'   => $worksheet,
            // En un laboratorio acreditado la trazabilidad de la bancada no es
            // un adorno: quién cargó cada valor y cuándo se cerró la hoja es lo
            // primero que pide una auditoría.
            'recordAudit' => $this->recordAuditMeta($worksheet),
            'activity'    => $this->recordActivity($worksheet, $request),
            'fields'      => $worksheet->definition->fields,
            'fieldTypes'  => config('lab_field_types'),
            // Quién registró cada fila. El dato estaba en cada celda desde el
            // principio (`worksheet_values.entered_by`) y no se mostraba en
            // ningún lado.
            'enteredBy'   => $this->enteredBy($worksheet),
            // Los tipos de fila que la PRUEBA exige. La grilla los abre solos
            // cuando faltan y les quita el botón de borrar: son parte de la
            // corrida, no una opción escondida en un menú.
            'requiredKinds' => array_values(array_filter([
                $worksheet->definition->requires_control ? WorksheetRow::KIND_CONTROL : null,
                $worksheet->definition->requires_duplicate ? WorksheetRow::KIND_DUPLICATE : null,
            ])),
            // Las pruebas de ESTA definición que todavía esperan resultado, para
            // que el analista elija la muestra en vez de tipear su código.
            'pendingTests' => $this->pendingTests($worksheet),
            // Cuántas celdas obligatorias le faltan a CADA fila, y a la hoja.
            //
            // Guardar una fila incompleta está permitido a propósito (el
            // analista mide la rigidez a la mañana y termina a la tarde), pero
            // eso no se decía en ningún lado: guardaba, veía el tilde verde y
            // creía que había terminado. La hoja no publica hasta que no falte
            // ninguna, así que el número tiene que estar a la vista.
            'incomplete'   => $this->incomplete($worksheet),
            // Los equipos que cada columna ofrece, indexados por columna. La
            // columna que no declara ninguno cae a la lista completa: es lo
            // correcto para las que el sistema anterior dejó como texto libre,
            // porque ofrecer de más es mejor que no ofrecer nada.
            'instrumentsByField' => $this->instrumentsByField($worksheet),
            'instruments' => Instrument::where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'description', 'calibration_due_at']),
            // Sin lista de equipos: la columna Equipo se quitó de la grilla
            // (2026-08-03, pedido del laboratorio). El equipo de una muestra lo
            // define la RECEPCIÓN y ya se ve en la celda del Nº de muestra; acá
            // se mandaban hasta 2000 equipos para un selector que no decidía
            // nada.
            // El candado, con quién puede ponerlo y sacarlo: es lo que dibuja
            // el botón Bloquear del encabezado, igual que en los catálogos.
            'lock'        => $this->lockMeta($worksheet->load('locker:id,name'), $request),
            'can'         => [
                'edit'     => $worksheet->isEditable() && $this->allows('worksheets.edit'),
                // Editar la CABECERA pide el permiso de edición y que el
                // candado no esté puesto; el estado del flujo no importa (una
                // humedad mal tipeada se corrige aunque la hoja ya publique).
                'edit_header' => ! $worksheet->is_locked && $this->allows('worksheets.edit'),
                // No hay botón que firme la hoja: publica sola en cuanto está
                // completa y deja de admitir cambios cuando el candado la
                // cierra. Lo único que queda como acción es darla de baja.
                'delete'   => ! $worksheet->isVoided() && $this->allows('worksheets.delete'),
            ],
            // Lo que le falta a la hoja para admitir muestras. Se manda como
            // dato y no como mensaje armado para que la pantalla explique el
            // porqué en vez de limitarse a deshabilitar una opción, que es lo
            // que hacía el sistema viejo.
            'missing'     => $worksheet->missingPrerequisites(),
        ]);
    }

    /**
     * Las muestras que esperan ESTA prueba, para el selector de la grilla.
     *
     * Se ofrecen las pendientes y las en proceso —una hoja se corrige, y la
     * fila que ya se cargó tiene que seguir apareciendo para poder editarla— y
     * también las que ya están en otra fila de esta misma hoja, porque el
     * analista puede querer moverlas de sitio.
     *
     * @return array<int,array{id:int,code:string,customer:?string,equipment:?string}>
     */
    /**
     * Quién cargó cada fila y cuándo, para la columna «Cargado por».
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ EL DATO YA EXISTÍA Y NO SE VEÍA                                      │
     * └──────────────────────────────────────────────────────────────────────┘
     * Cada celda guarda su `entered_by` desde el primer día —es lo que pide una
     * acreditación—, pero la grilla nunca lo mostraba: para saber quién había
     * registrado una muestra en la bancada había que ir a la base. Se toma el
     * ÚLTIMO que escribió en la fila, que es quien responde por lo que se ve.
     *
     * En UNA consulta agregada, no una por fila: una hoja de cromatografía con
     * 40 muestras son 40 consultas para dibujar una columna.
     *
     * @return array<int,array{name:string,at:?string}>
     */
    private function enteredBy(Worksheet $worksheet): array
    {
        $filas = $worksheet->rows->pluck('id');

        if ($filas->isEmpty()) {
            return [];
        }

        return \App\Models\WorksheetValue::query()
            ->whereIn('worksheet_row_id', $filas)
            ->whereNotNull('entered_by')
            ->orderBy('entered_at')
            ->orderBy('id')
            ->with('enteredBy:id,name')
            ->get(['id', 'worksheet_row_id', 'entered_by', 'entered_at'])
            ->reduce(function (array $mapa, $valor) {
                if ($valor->enteredBy) {
                    $mapa[$valor->worksheet_row_id] = [
                        'name' => $valor->enteredBy->name,
                        'at'   => $valor->entered_at?->toIso8601String(),
                    ];
                }

                return $mapa;
            }, []);
    }

    /**
     * Qué le falta a la hoja para poder publicar.
     *
     * `rows` es el mapa fila → cuántas celdas obligatorias tiene vacías, y
     * `total` la suma. La grilla pinta la fila en ámbar y pone la cuenta al
     * pie: el estado real de la hoja deja de ser algo que hay que deducir
     * mirando columna por columna.
     *
     * @return array{rows: array<int,int>, total: int}
     */
    private function incomplete(Worksheet $worksheet): array
    {
        $porFila = [];

        foreach ($this->service->missingRequiredValues($worksheet) as $falta) {
            $porFila[$falta['row']] = ($porFila[$falta['row']] ?? 0) + 1;
        }

        return ['rows' => $porFila, 'total' => array_sum($porFila)];
    }

    private function pendingTests(Worksheet $worksheet): array
    {
        // Las que YA están en una fila de esta hoja entran siempre, sin mirar
        // su estado. Si no, el selector no encuentra su propia opción y Ant
        // Design cae a mostrar el número crudo del identificador en vez del
        // correlativo: la hoja ya cargada se leería "124" donde dice
        // "2026-0018".
        $enLaHoja = $worksheet->rows()->whereNotNull('sample_test_id')->pluck('sample_test_id');

        return \App\Models\SampleTest::query()
            ->where('test_definition_id', $worksheet->test_definition_id)
            ->where(fn ($q) => $q
                ->whereIn('status', [
                    \App\Models\SampleTest::STATUS_PENDING,
                    \App\Models\SampleTest::STATUS_IN_PROGRESS,
                ])
                ->orWhereIn('id', $enLaHoja))
            ->with([
                'sample:id,code,equipment_id,reception_id',
                'sample.equipment:id,name,tag,serial',
                'sample.reception:id,customer_id',
                'sample.reception.customer:id,name',
            ])
            ->get()
            ->filter(fn ($p) => $p->sample !== null)
            ->sortByDesc(fn ($p) => $p->sample->code)
            ->map(fn ($p) => [
                'id'        => $p->id,
                'code'      => $p->sample->code,
                'customer'  => $p->sample->reception?->customer?->name,
                'equipment' => $p->sample->equipment?->tag ?: $p->sample->equipment?->name,
            ])
            ->values()
            ->all();
    }

    /** Alta o edición de una fila con todos sus valores. */
    public function saveRow(Request $request, Worksheet $worksheet): RedirectResponse
    {
        $data = $request->validate([
            'row_id'        => ['nullable', 'integer'],
            'kind'          => ['required', Rule::in(WorksheetRow::KINDS)],
            'sample_code'   => ['nullable', 'string', 'max:60'],
            // CUÁL de las pruebas pedidas es esta fila. Es el enlace real con
            // la muestra: sin él, `worksheet_rows.sample_id` queda nulo y con
            // eso se caen tres cosas ya construidas — el avance de la muestra,
            // el equipo del resultado y el bloque de condiciones del informe,
            // que se busca por ese identificador y sale vacío. El sistema
            // anterior enlazaba partiendo el código tipeado e interpolándolo en
            // SQL, y cuando el texto no coincidía el resultado no llegaba nunca
            // al informe, sin que nada avisara.
            'sample_test_id' => ['nullable', 'integer'],
            'position'      => ['nullable', 'integer', 'min:0'],
            'instrument_id' => ['nullable', 'integer', Rule::exists('instruments', 'id')],
            // De qué equipo del cliente es la muestra. Sin esto el resultado no
            // se puede consultar por equipo, que es para lo único que existe la
            // capa `results`.
            'equipment_id'  => ['nullable', 'integer', Rule::exists('equipment', 'id')],
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

    /**
     * Guarda VARIAS filas de una vez. Es el "Guardar todo" de la grilla.
     *
     * La pantalla manda SOLO las filas con cambios. El guardado por fila sigue
     * existiendo y sigue siendo el camino normal en bancada —se termina una
     * muestra, se guarda, queda a salvo—; esto es para cerrar la corrida sin
     * apretar el botón quince veces.
     */
    public function saveRows(Request $request, Worksheet $worksheet): RedirectResponse
    {
        $data = $request->validate([
            'rows'                  => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.row_id'         => ['nullable', 'integer'],
            'rows.*.kind'           => ['required', Rule::in(WorksheetRow::KINDS)],
            'rows.*.sample_code'    => ['nullable', 'string', 'max:60'],
            'rows.*.sample_test_id' => ['nullable', 'integer'],
            'rows.*.position'       => ['nullable', 'integer', 'min:0'],
            'rows.*.instrument_id'  => ['nullable', 'integer', Rule::exists('instruments', 'id')],
            'rows.*.equipment_id'   => ['nullable', 'integer', Rule::exists('equipment', 'id')],
            'rows.*.notes'          => ['nullable', 'string', 'max:2000'],
            'rows.*.values'         => ['array'],
        ]);

        $guardadas = $this->service->saveRows($worksheet, $data['rows']);

        return back()->with('success', __('worksheets.rows_saved', ['count' => $guardadas]));
    }

    /**
     * Pone en la hoja todas las muestras que esta corrida espera.
     *
     * No recibe la lista: la resuelve el servicio. Ver
     * `WorksheetService::fillPendingSamples` para el porqué.
     */
    public function fillPending(Worksheet $worksheet): RedirectResponse
    {
        $puestas = $this->service->fillPendingSamples($worksheet);

        return back()->with('success', $puestas === 0
            ? __('worksheets.fill_none')
            : __('worksheets.filled', ['count' => $puestas]));
    }

    /**
     * Vista previa del cálculo: qué daría la hoja con lo que hay tipeado, sin
     * guardar absolutamente nada.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ POR QUÉ ESTO ES UN ENDPOINT Y NO UNA FUNCIÓN DEL NAVEGADOR           │
     * └──────────────────────────────────────────────────────────────────────┘
     * En el sistema Rails viejo el analista veía el resultado mientras escribía
     * porque la fórmula era JavaScript guardado en una columna de la base e
     * inyectado con `html_safe` en la página. El servidor no calculaba ni
     * verificaba nada: el campo resultado tenía `readonly` (que un envío directo
     * saltea) y cuando la fórmula operaba sobre un campo vacío quedaba el texto
     * "NaN" guardado.
     *
     * Ver el número mientras se mide es una necesidad real del analista —es
     * cómo se da cuenta de que la titulación le salió mal antes de tirar la
     * muestra—, así que no alcanza con calcular al guardar. Lo que se corrige es
     * QUIÉN calcula: la vista previa la resuelve el MISMO motor que el guardado
     * (App\Services\Lab\FormulaResolver), sobre el mismo criterio de redondeo y
     * la misma resolución por réplica. El navegador solo dibuja lo que vuelve.
     *
     * NO ESCRIBE NADA. Ni la fila, ni los valores, ni la hoja. Es cálculo puro:
     * el que se guarda sigue siendo el número que produce saveRow().
     *
     * Respuesta:
     *   values      { código: { nro de réplica: número|null } } solo de las
     *               columnas calculadas.
     *   unresolved  códigos que el motor no pudo resolver.
     *   errors      { código: [mensajes] } de fórmulas que ni siquiera se
     *               pudieron analizar.
     *   cycles      fórmulas que se referencian entre sí en círculo.
     */
    public function preview(Request $request, Worksheet $worksheet): JsonResponse
    {
        // Antes de mirar el cuerpo: sobre una hoja cerrada, validada o
        // bloqueada la respuesta no depende de lo que se mande, y calcular
        // gastaría trabajo para algo que no se va a poder guardar.
        if (! $worksheet->isEditable()) {
            throw ValidationException::withMessages([
                'worksheet' => $worksheet->locked_at !== null
                    ? __('worksheets.errors.locked')
                    : __('worksheets.errors.not_draft'),
            ]);
        }

        // TOPE DE TAMAÑO: 64 KB. La plantilla más grande del laboratorio tiene
        // del orden de 30 columnas por 6 réplicas de unos pocos caracteres cada
        // una, o sea menos de 2 KB de cuerpo real. 64 KB deja más de un orden de
        // magnitud de holgura para la celda de observaciones más larga y a la
        // vez corta de raíz un cuerpo de megabytes contra un endpoint que se
        // llama a cada tecla. Se mide el cuerpo crudo y no el arreglo ya
        // validado porque el gasto de memoria ocurre al decodificarlo.
        if (strlen((string) $request->getContent()) > 64 * 1024) {
            throw ValidationException::withMessages([
                'values' => __('worksheets.errors.preview_too_large'),
            ]);
        }

        $data = $request->validate([
            // 120 columnas: la prueba más ancha del laboratorio no llega a 30.
            'values'     => ['array', 'max:120'],
            'values.*'   => ['nullable'],
            'values.*.*' => ['nullable'],
            // Varias filas en UNA petición: `rows` es `{ clave de fila: values }`.
            // El tope de 200 es el mismo que el del guardado en lote.
            'rows'       => ['array', 'max:200'],
            'rows.*'     => ['array', 'max:120'],
        ]);

        $fields = $worksheet->definition->fields()->with('options')->get();
        $computed = $fields->filter(fn (TestField $field) => filled($field->formula));

        // Con `rows` la respuesta viene indexada por fila; sin él, plana. Se
        // acepta una sola clave de las dos y no las dos mezcladas.
        $enLote = array_key_exists('rows', $data);

        if ($computed->isEmpty()) {
            $vacio = ['values' => [], 'unresolved' => [], 'errors' => [], 'cycles' => []];

            return response()->json($enLote
                ? ['rows' => array_map(fn () => $vacio, $data['rows'])]
                : $vacio);
        }

        if ($enLote) {
            return response()->json([
                'rows' => array_map(
                    fn (array $valores) => $this->previewOne($fields, $computed, $valores),
                    $data['rows'],
                ),
            ]);
        }

        return response()->json($this->previewOne($fields, $computed, $data['values'] ?? []));
    }

    /**
     * El cálculo de UNA fila. Es el cuerpo que antes vivía dentro de preview();
     * se separó para poder resolver varias filas en la misma petición.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ POR QUÉ VARIAS FILAS EN UNA PETICIÓN                                 │
     * └──────────────────────────────────────────────────────────────────────┘
     * La grilla pide la previa por fila, 400 ms después de que el analista deja
     * de tipear. Con la hoja cargada de a una eso es una petición cada tanto;
     * desde que se pueden traer todas las muestras pendientes de golpe, son
     * veinte filas en pantalla y veinte peticiones casi simultáneas — que
     * además chocan contra el límite de 120 por minuto del propio endpoint.
     *
     * @param  \Illuminate\Support\Collection<int,TestField> $fields
     * @param  \Illuminate\Support\Collection<int,TestField> $computed
     * @param  array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function previewOne($fields, $computed, array $input): array
    {
        $resolver = new FormulaResolver();
        $replicates = max(1, (int) $fields->max('replicates'));

        $values = [];
        $unresolved = [];
        $errors = [];
        $cycles = [];

        // Réplica por réplica, igual que WorksheetService::recalculate(): la
        // medición 3 se calcula con los datos de la medición 3. Mezclarlas daría
        // un número distinto al que va a quedar guardado, y una vista previa que
        // no coincide con lo guardado es peor que no tener vista previa.
        for ($replicate = 1; $replicate <= $replicates; $replicate++) {
            $result = $resolver->resolveWithDiagnostics(
                $fields->all(),
                $this->previewContext($fields, $input, $replicate),
            );

            foreach ($computed as $field) {
                if ($replicate > max(1, (int) $field->replicates)) {
                    continue;
                }

                $values[$field->code][$replicate] = $result['values'][$field->code] ?? null;
            }

            // Los ciclos y las fórmulas rotas dependen de la PLANTILLA, no de
            // los datos: son los mismos en todas las réplicas.
            $cycles = $result['cycles'];
            $errors = $result['errors'];
            $unresolved = array_unique(array_merge($unresolved, $result['unresolved']));
        }

        return [
            'values'     => $values,
            'unresolved' => array_values($unresolved),
            'errors'     => $errors,
            'cycles'     => $cycles,
        ];
    }

    public function destroyRow(Worksheet $worksheet, WorksheetRow $row): RedirectResponse
    {
        abort_unless($row->worksheet_id === $worksheet->id, 404);

        if (! $worksheet->isEditable()) {
            return back()->withErrors(['worksheet' => __('worksheets.errors.not_draft')]);
        }

        // El servicio, no un `delete()` pelado: la baja tiene que retirar
        // también el resultado que la fila ya había publicado, o el informe
        // sigue imprimiendo un valor sin ninguna fila detrás.
        $this->service->deleteRow($worksheet, $row);

        return back()->with('success', __('worksheets.row_deleted'));
    }

    /**
     * Da de baja la hoja, con su motivo.
     *
     * Era "anular" y ahora es borrar, que es lo que tenía el sistema anterior.
     * El motivo sigue siendo obligatorio: una hoja que desaparece sin decir por
     * qué no sirve ante una auditoría.
     */
    public function destroy(Request $request, Worksheet $worksheet): RedirectResponse
    {
        // El mismo candado que corta `delete()`: sin esto la pantalla de
        // confirmación se negaba a abrir pero la ruta DELETE seguía aceptando
        // la baja de una hoja congelada.
        abort_if($worksheet->is_locked, 403, __('locks.cannot_delete_locked'));

        $data = $request->validate([
            'void_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $this->service->void($worksheet, $data['void_reason']);

        return redirect()
            ->route('lab_management.worksheets.index')
            ->with('success', __('worksheets.deleted'));
    }

    /**
     * Baja masiva desde el listado, con motivo — el estándar de los índices.
     *
     * Cada hoja pasa por las MISMAS reglas que la baja individual, y por la
     * misma puerta: `WorksheetService::void()`. Eso importa más acá que en un
     * catálogo, porque dar de baja una hoja no es borrar una fila — retira sus
     * resultados de la capa consultable, marca sus puntos de control de calidad
     * y devuelve sus ensayos a la cola. Un `delete()` masivo pelado dejaría
     * veinte informes imprimiendo valores sin hoja detrás.
     *
     * Las bloqueadas por candado y las ya dadas de baja se SALTAN en vez de
     * abortar la operación: quien selecciona veinte no tiene por qué perderla
     * por una.
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids'                 => ['required', 'array', 'min:1', 'max:200'],
            'ids.*'               => ['integer'],
            'deleted_description' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        // La consulta pasa por el scope de tenant del modelo: los ids ajenos
        // simplemente no aparecen.
        $hojas = Worksheet::query()->whereIn('id', $data['ids'])->get();

        $borradas = 0;
        $saltadasCandado = 0;
        $saltadasEstado = 0;

        foreach ($hojas as $hoja) {
            if ($hoja->is_locked) {
                $saltadasCandado++;
                continue;
            }

            try {
                $this->service->void($hoja, $data['deleted_description']);
                $borradas++;
            } catch (ValidationException) {
                // Ya estaba dada de baja: su motivo original se conserva.
                $saltadasEstado++;
            }
        }

        if ($borradas === 0) {
            return back()->withErrors([
                'ids' => __('worksheets.bulk_none_deleted', [
                    'locked' => $saltadasCandado,
                    'voided' => $saltadasEstado,
                ]),
            ]);
        }

        $msg = __('global.deleted_success') . ' (' . $borradas . ')';
        if ($saltadasCandado > 0) {
            $msg .= ' · ' . __('locks.bulk_skipped_locked', ['count' => $saltadasCandado]);
        }
        if ($saltadasEstado > 0) {
            $msg .= ' · ' . __('worksheets.bulk_skipped_voided', ['count' => $saltadasEstado]);
        }

        return back()->with('success', $msg);
    }

    /**
     * El contexto de una réplica, en el mismo formato que consume el motor al
     * guardar (WorksheetRow::valuesByFieldCode()).
     *
     * Las columnas calculadas no entran: su valor lo produce la fórmula y lo
     * que venga del formulario para ellas se descarta, exactamente como en
     * WorksheetService::writeValues().
     *
     * UNA COLUMNA DE VALOR ÚNICO VALE PARA TODAS LAS RÉPLICAS. El factor de la
     * solución titulante y el peso de la muestra se cargan una vez y se aplican
     * a las cinco mediciones de rigidez. Es el mismo respaldo que hace
     * WorksheetRow::valuesByFieldCode() sobre la hoja guardada, y tiene que ser
     * el mismo o la vista previa diría algo distinto de lo que va a quedar.
     *
     * @param  \Illuminate\Support\Collection<int,TestField> $fields
     * @param  array<string,mixed>                           $input
     * @return array<string,mixed>
     */
    private function previewContext($fields, array $input, int $replicate): array
    {
        $context = [];

        foreach ($fields as $field) {
            if (filled($field->formula)) {
                continue;
            }

            $raw = $input[$field->code] ?? null;

            if (is_array($raw)) {
                // La réplica pedida si está; si no, la primera, que es el valor
                // único de la columna.
                $raw = $raw[$replicate] ?? $raw[(string) $replicate] ?? $raw[1] ?? $raw['1'] ?? null;
            }

            $context[$field->code] = $this->numericValueOf($field, $raw);
        }

        return $context;
    }

    /**
     * Lo que el motor vería si ese valor YA estuviera guardado.
     *
     * La traducción NO se hace acá: la hace App\Services\Lab\ValueCoercer, que
     * es la misma pieza que usa el guardado. Es a propósito. El motor no recibe
     * lo que se tipeó sino lo que quedó en la base, y entre una cosa y la otra
     * hay tres traducciones que cambian el número (">75" a 75, "0,5" a 0.5, y
     * una selección leída por su texto y no por su id). Con ese criterio
     * escrito en dos lugares, la vista previa mostraría un número mientras se
     * escribe y otro después de guardar.
     */
    private function numericValueOf(TestField $field, mixed $raw): float|string|null
    {
        return $this->coercer->toFormulaInput($field, $raw);
    }

    private function allows(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }
}
