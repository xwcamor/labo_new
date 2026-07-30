<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\Instrument;
use App\Models\Reception;
use App\Models\Sample;
use App\Models\SpecLimit;
use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\User;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
use App\Services\Lab\ReceptionService;
use App\Services\Lab\SpecSetResolver;
use App\Services\Lab\WorksheetService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * UNA muestra con LAS 29 PRUEBAS del catálogo, cargadas y validadas.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ PARA QUÉ                                                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sembrador de demostración carga cinco pruebas, que alcanza para ver la
 * bancada y la carta de control pero no para ver EL INFORME: el informe cambia
 * de forma con el volumen —cuántas páginas salen, cómo se agrupan las familias,
 * qué dice el diagnóstico, dónde cae el sello de acreditación— y con cinco
 * pruebas nada de eso se puede juzgar.
 *
 * Acá se arma el caso máximo: una muestra con todo pedido, todo ensayado y todo
 * validado. Es el informe más largo que el laboratorio puede emitir.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LOS VALORES NO ESTÁN ESCRITOS A MANO: SALEN DE LOS LÍMITES               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Escribir a mano un valor plausible para las 207 columnas del catálogo sería
 * inventar 207 números, y quedarían viejos en cuanto el laboratorio ajuste una
 * columna. Cada valor se deriva de lo que el propio sistema declara:
 *
 *   · Columna de RESULTADO → del cuadro de límites que le toca a esta muestra
 *     (`spec_limits`, resuelto por aceite y clase de tensión, el mismo que usa
 *     el informe para juzgarla). Se apunta al 60 % de un máximo o al 140 % de
 *     un mínimo, o sea bien dentro de norma.
 *   · Columna de ENTRADA de una fórmula → de la tabla `ENTRADAS`, que es la
 *     única parte escrita a mano y solo cubre las siete pruebas cuyo resultado
 *     lo calcula el servidor. Ahí los números tienen que ser coherentes entre
 *     sí o la fórmula devuelve un disparate.
 *   · Selección, instrumento, fecha, código de muestra → como en la bancada.
 *   · Columna CALCULADA → no se carga: la calcula el servidor. Si esa cadena
 *     estuviera rota, el informe saldría con la celda vacía y se vería.
 *
 * `FUERA_DE_NORMA` deja tres parámetros pasados a propósito: un informe donde
 * todo está conforme no muestra el rojo, ni la columna de condición, ni el
 * párrafo de diagnóstico que habla de lo que excede.
 *
 * Idempotente: se ancla al código de la recepción. Se borra con
 * `php artisan lab:full-report --limpiar`.
 */
class LabFullReportSeeder extends Seeder
{
    private const TENANT_ID = 1;

    /** Marca de agua: todo lo que este sembrador crea la lleva. */
    public const MARCA = 'FULL';

    /** El código de la recepción, que es su ancla de idempotencia. */
    private const CODIGO = 'FULL-REM-01';

    /**
     * Los tres parámetros que salen FUERA de norma a propósito, por código de
     * analito. Sin ninguno, el informe no muestra el rojo ni el diagnóstico
     * habla de nada.
     */
    private const FUERA_DE_NORMA = ['acid', 'wat', 'c2h2'];

    /**
     * Las entradas de las pruebas cuyo RESULTADO lo calcula el servidor.
     *
     * Es lo único escrito a mano de todo el sembrador, y tiene que estarlo: los
     * números de una titulación se relacionan entre sí por la fórmula, así que
     * un valor al azar en cada casilla daría un resultado sin sentido. Están
     * elegidos para que el resultado calculado caiga donde corresponde.
     *
     * @var array<string, array<string, float>>
     */
    private const ENTRADAS = [
        // Número ácido: (gastado - blanco) * factor / masa → ~0.28 mgKOH/g.
        'numero_acido' => [
            'factor_koh' => 5.61, 'vol_blanco' => 0.05,
            'peso_aceite_g' => 20.00, 'volumen_gastado_ml' => 1.05,
        ],
        // Contenido de agua: promedio de las dos determinaciones Karl Fischer.
        'contenido_de_agua' => ['r1' => 41.2, 'r2' => 42.6],
        // Resistividad: promedio de las dos lecturas, a su temperatura.
        'resistividad_volumetrica_25o'  => ['temperatura_oc' => 25.0, 'rho_ocm' => 8.4e12, 'rho_ocm_2' => 8.9e12],
        'resistividad_volumetrica_100o' => ['temperatura_oc' => 100.0, 'rho_ocm' => 2.1e11, 'rho_ocm_2' => 2.3e11],
        // Sedimentos: la suma de las tres fracciones.
        'sedimentos' => [
            'sedimentos_organicos' => 0.004, 'sedimentos_inorganicos' => 0.002,
            'lodos_solubles' => 0.003,
        ],
        // Color: dos lecturas del colorímetro.
        'color' => ['lec_1' => 1.5, 'lec_2' => 1.5],
        // PCB: los tres aroclores que se suman.
        'pcb' => ['aroclor_1242' => 0.0, 'aroclor_1254' => 0.0, 'aroclor_1260' => 0.0],
        // Viscosidad: constante del viscosímetro × tiempo de caída.
        'viscocidad' => ['temperatura_oc' => 40.0, 'constante' => 0.0093, 'tiempo_segundos' => 1150.0],
        // Grado de polimerización por viscometría: la cadena completa de la
        // norma, desde la masa pesada hasta la viscosidad intrínseca.
        'grado_de_polimerizacion' => [
            'masa_g' => 0.1250, 'tiempo_muestra_s' => 212.4,
            'constante_viscosimetro_muestra' => 0.00934, 'tiempo_blanco' => 96.8,
            'constante_viscosimetro_blanco' => 0.00934,
            'viscosidad_de_muestra_t' => 1.984, 'viscosidad_de_solventet0' => 0.904,
            'concetracion_muestra_g100ml' => 0.125, 'viscosidad_especifica_ns' => 1.195,
            'k_de_martin' => 0.14, 'viscosidad_intrinseca_n' => 8.42,
            'promedio' => 8.42,
        ],
        // Factor de potencia y rigidez: las condiciones de sala, no la medición
        // (el valor medido sí sale del cuadro de límites).
        'factor_de_potencia_25o'  => ['temperatura_ambiente_oc' => 22.5, 'humedad_ambiente' => 58, 'temperatura_muestra_oc' => 25.0],
        'factor_de_potencia_90o'  => ['temperatura_ambiente_oc' => 22.5, 'humedad_ambiente' => 58, 'temperatura_muestra_oc' => 90.0],
        'factor_de_potencia_100o' => ['temperatura_ambiente_oc' => 22.5, 'humedad_ambiente' => 58, 'temperatura_muestra_oc' => 100.0],
        'rigidez_dielectrica'     => ['temperatura_ambiente_oc' => 22.5, 'humedad_ambiente' => 58, 'temperatura_muestra_oc' => 25.0],
        'rigidez_dielectrica_electrodos_planos' => ['temperatura_ambiente_oc' => 22.5, 'humedad_ambiente' => 58, 'temperatura_muestra_oc' => 25.0],
    ];

    /** Semilla fija: dos corridas dan exactamente lo mismo. */
    private int $semilla = 20260730;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('No se siembra el informe completo en producción.');

            return;
        }

        $analista = User::withoutGlobalScopes()
            ->where('tenant_id', self::TENANT_ID)
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->first()
            ?? User::withoutGlobalScopes()->where('tenant_id', self::TENANT_ID)->first();

        if (! $analista) {
            $this->command?->warn('No hay usuarios en el workspace 1; se omite el informe completo.');

            return;
        }

        // El servicio sella quién cargó y quién validó cada valor. Sin sesión
        // esos campos quedarían en nulo y la hoja se vería escrita por nadie.
        Auth::login($analista);

        try {
            $muestra = $this->muestra();

            if (! $muestra) {
                return;
            }

            $pruebas = TestDefinition::with(['fields.options', 'group'])
                ->orderBy('sort_order')->get();

            $hechas = 0;
            $sinResultado = [];

            foreach ($pruebas as $prueba) {
                if ($this->cargar($prueba, $muestra)) {
                    $hechas++;
                }

                if ($prueba->fields->whereNotNull('output_analyte_id')->isEmpty()) {
                    $sinResultado[] = $prueba->code;
                }
            }

            $informe = $this->informe($muestra, $analista);

            $this->command?->info(sprintf(
                'Informe completo: muestra %s con %d de %d pruebas cargadas y validadas → informe %s.',
                $muestra->code,
                $hechas,
                $pruebas->count(),
                $informe?->code ?? '(ya existía)',
            ));

            if ($sinResultado !== []) {
                $this->command?->warn(
                    'Estas pruebas se cargan pero NO imprimen filas: todavía no declaran '
                    . 'qué parámetro alimenta cada columna (decisión pendiente del '
                    . 'laboratorio, ver analyte_map.json): ' . implode(', ', $sinResultado)
                );
            }
        } finally {
            Auth::logout();
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // El equipo
    // ─────────────────────────────────────────────────────────────────────

    /**
     * El transformador del que se tomó la muestra.
     *
     * Uno solo, y con la chapa COMPLETA porque el informe la imprime entera en el
     * bloque "Información del equipo": si faltara la mitad, el papel de
     * demostración saldría con media docena de rayas y no se podría juzgar la
     * maqueta.
     *
     * Los tres datos que NO son decorativos son el tipo de equipo, el tipo de
     * aceite y la clase de tensión: de ellos depende qué cuadro de límites le
     * toca. Un transformador de potencia, mineral, en servicio y de 138 kV cae en
     * el cuadro más usado del laboratorio, que es el que conviene mostrar.
     */
    private function equipo(): ?Equipment
    {
        $cliente = \App\Models\Customer::withoutGlobalScopes()
            ->where('tenant_id', self::TENANT_ID)
            ->orderBy('id')
            ->first();

        if (! $cliente) {
            return null;
        }

        return Equipment::withoutGlobalScopes()->updateOrCreate(
            ['external_ref' => self::MARCA . '-EQ-01', 'tenant_id' => self::TENANT_ID],
            [
                'slug'              => Str::random(22),
                'name'              => 'Transformador de potencia ' . self::MARCA,
                'tag'               => 'T-01',
                'serial'            => self::MARCA . '-SN-1000',
                'customer_id'       => $cliente->id,
                'equipment_type_id' => 1,   // potencia
                'oil_type_id'       => 1,   // mineral
                'voltage_kv_hv'     => 138.0,
                'voltage_kv_lv'     => 13.8,
                'power_mva'         => 30.0,
                'power_mva_2'       => 40.0,
                'power_mva_3'       => 50.0,
                'phases'            => 3,
                'manufacture_year'  => 2005,
                // La locación tiene que ser del MISMO cliente dueño del equipo:
                // una de otro cliente no sería un hueco menos, sería un dato falso
                // impreso en el informe.
                'customer_location_id' => \App\Models\CustomerLocation::withoutGlobalScopes()
                    ->where('customer_id', $cliente->id)->orderBy('id')->value('id'),
                'brand_id' => \App\Models\Brand::withoutGlobalScopes()->orderBy('id')->value('id'),
                'transformer_preservation_id' => \App\Models\TransformerPreservation::withoutGlobalScopes()
                    ->orderBy('id')->value('id'),
                'tap_changer_type_id' => \App\Models\TapChangerType::withoutGlobalScopes()
                    ->orderBy('id')->value('id'),
                'oil_brand'       => 'Nynas',
                'oil_volume'      => 12500,
                'oil_volume_unit' => 'L',
                'service_state'   => 'in_service',
                'is_active'       => true,
            ],
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // El informe
    // ─────────────────────────────────────────────────────────────────────

    /**
     * El informe de la muestra, emitido.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ POR QUÉ EMITIDO Y NO EN BORRADOR                                     │
     * └──────────────────────────────────────────────────────────────────────┘
     * Emitido es el estado en que el papel existe de verdad: tiene correlativo
     * (`REP-LAB-año-NNNN`, que es lo que sale impreso en la cabecera), tiene
     * código de verificación —así el portal público `/verify/{code}` encuentra
     * algo— y muestra el candado en la lista. Un borrador no tiene número, y el
     * informe saldría rotulado con el código de la muestra.
     *
     * Para iterar sobre la maqueta del PDF sin re-sembrar está la VISTA PREVIA
     * desde la muestra, que se renderiza en vivo. El informe emitido imprime
     * desde su snapshot a propósito: reimprimirlo dentro de dos años tiene que
     * dar el mismo papel.
     *
     * Se emite por `SampleReportService`, la misma vía que la pantalla: si mañana
     * emitir implica un paso más, la demostración lo hereda en vez de quedar con
     * datos que el sistema real no podría producir.
     */
    private function informe(Sample $muestra, User $analista): ?\App\Models\SampleReport
    {
        // Idempotente: volver a correr el seed no emite un segundo correlativo.
        if ($muestra->reports()->exists()) {
            return $muestra->reports()->first();
        }

        $servicio = app(\App\Services\Lab\SampleReportService::class);

        $informe = $servicio->create($muestra, [
            'sampling_reason' => $muestra->sampling_reason,
        ], $analista->id);

        $servicio->issue($informe->fresh(), $analista->id);

        return $informe->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────
    // La muestra
    // ─────────────────────────────────────────────────────────────────────

    /**
     * La recepción de una sola muestra, con equipo, por el servicio real.
     *
     * Con equipo y con aceite declarado a propósito: de eso depende QUÉ cuadro
     * de límites le toca, y sin cuadro el informe saldría entero "sin criterio".
     */
    private function muestra(): ?Sample
    {
        $existente = Reception::withoutGlobalScopes()->where('code', self::CODIGO)->first();

        if ($existente) {
            return $existente->samples()->first();
        }

        // ┌──────────────────────────────────────────────────────────────────┐
        // │ EL EQUIPO SE SIEMBRA ACÁ, NO SE TOMA PRESTADO                     │
        // └──────────────────────────────────────────────────────────────────┘
        // Este sembrador buscaba el primer equipo con tipo de aceite y avisaba
        // "corra primero LabDemoWorksheetsSeeder". Al pasar a ser el ÚNICO
        // registro del seed base, esa dependencia lo dejaba sin hacer nada: el
        // seed terminaba con el catálogo completo y cero muestras.
        //
        // El equipo tiene que declarar tipo de aceite, tipo de equipo y clase de
        // tensión: de esos tres datos depende QUÉ cuadro de límites le toca, y sin
        // cuadro el informe saldría entero en raya y no serviría para ver nada.
        $equipo = $this->equipo();

        if (! $equipo) {
            $this->command?->warn('No hay clientes en el workspace 1; se omite el informe completo.');

            return null;
        }

        $fecha = Carbon::now()->startOfMonth()->addDays(4);

        $recepcion = Reception::create([
            'slug'          => Str::random(22),
            'code'          => self::CODIGO,
            'customer_id'   => $equipo->customer_id,
            'received_at'   => $fecha,
            'due_at'        => $fecha->copy()->addDays(10)->toDateString(),
            'packages'      => 1,
            'container_ok'  => true,
            'volume_ok'     => true,
            'label_ok'      => true,
            'service_order' => 'OS-FULL-0001',
            'contact_info'  => 'laboratorio@ejemplo.com',
            'end_user'      => 'Gerencia de Mantenimiento',
            'notes'         => self::MARCA . ' — muestra con las 29 pruebas, para ver el informe completo.',
            'status'        => Reception::STATUS_DRAFT,
            'tenant_id'     => self::TENANT_ID,
        ]);

        // El correlativo se emite por el servicio, igual que en la pantalla.
        (new ReceptionService())->confirm($recepcion, 1);

        $muestra = $recepcion->samples()->first();

        $muestra->update([
            'equipment_id'       => $equipo->id,
            'oil_type_id'        => $equipo->oil_type_id,
            'sampled_at'         => $fecha->copy()->subDays(2)->toDateString(),
            'description'        => 'Aceite mineral en servicio, tomado del equipo en operación.',
            'sampling_point'     => 'Válvula inferior',
            'sampling_reason'    => 'Mantenimiento programado',
            'oil_temp_c'         => 52.0,
            'equipment_temp_c'   => 48.0,
            'ambient_temp_c'     => 21.0,
            'relative_humidity'  => 58.0,
        ]);

        // Se le pide TODO el catálogo: es el caso máximo.
        $muestra->tests()->createMany(
            TestDefinition::pluck('id')->map(fn ($id) => [
                'test_definition_id' => $id,
                'status'             => \App\Models\SampleTest::STATUS_PENDING,
                'tenant_id'          => self::TENANT_ID,
            ])->all()
        );

        return $muestra->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────
    // La carga de una prueba
    // ─────────────────────────────────────────────────────────────────────

    /** Una hoja para esta prueba, con la fila de la muestra, y validada. */
    private function cargar(TestDefinition $prueba, Sample $muestra): bool
    {
        $pedido = $muestra->tests()
            ->where('test_definition_id', $prueba->id)
            ->first();

        if (! $pedido) {
            return false;
        }

        $fecha = Carbon::parse($muestra->sampled_at)->addDays(3);

        $hoja = Worksheet::withoutGlobalScopes()
            ->where('test_definition_id', $prueba->id)
            ->where('notes', 'like', self::MARCA . '%')
            ->first();

        if ($hoja) {
            return false;   // ya sembrada
        }

        $servicio = new WorksheetService();

        $hoja = Worksheet::create([
            'slug'               => Str::random(22),
            'test_definition_id' => $prueba->id,
            'run_date'           => $fecha,
            'analyst_id'         => Auth::id(),
            'status'             => Worksheet::STATUS_DRAFT,
            'ambient_temp_c'     => 22.5,
            'ambient_humidity'   => 58,
            'sample_temp_c'      => 25.0,
            'lab_pressure_hpa'   => 1013.2,
            'notes'              => self::MARCA . ' — hoja del informe completo.',
            'tenant_id'          => self::TENANT_ID,
        ]);

        $celdas = $this->celdas($prueba, $muestra, $fecha);

        // El patrón va PRIMERO cuando la prueba lo exige: el servicio no acepta
        // muestras sin él, y el sembrador pasa por la misma regla que la
        // bancada en vez de saltearla.
        if ($prueba->requires_control) {
            $servicio->saveRow($hoja, ['kind' => WorksheetRow::KIND_CONTROL], $celdas);
        }

        if ($prueba->requires_duplicate) {
            $servicio->saveRow($hoja, ['kind' => WorksheetRow::KIND_DUPLICATE], $celdas);
        }

        $servicio->saveRow($hoja, [
            'kind'           => WorksheetRow::KIND_SAMPLE,
            'sample_test_id' => $pedido->id,
        ], $celdas);

        if ($hoja->fresh()->status !== Worksheet::STATUS_VALIDATED) {
            $servicio->validate($hoja->fresh());
        }

        return true;
    }

    /**
     * Todas las celdas de la fila, resueltas columna por columna.
     *
     * @return array<string,mixed>
     */
    private function celdas(TestDefinition $prueba, Sample $muestra, Carbon $fecha): array
    {
        $entradas = self::ENTRADAS[$prueba->code] ?? [];
        $celdas = [];

        foreach ($prueba->fields as $columna) {
            // La calculada la resuelve el servidor con su fórmula.
            if ($columna->type === 'computed' || trim((string) $columna->formula) !== '') {
                continue;
            }

            // Lo escrito a mano gana: son los números que la fórmula relaciona
            // entre sí, y un valor derivado del límite los rompería.
            if (array_key_exists($columna->code, $entradas)) {
                // Sin dispersión: varias de estas son CONSTANTES del método (la
                // constante del viscosímetro, el factor del KOH, la masa
                // pesada). Un mismo viscosímetro no cambia de constante entre
                // réplica y réplica, y variarla haría que el cálculo mienta.
                $celdas[$columna->code] = $this->porReplica($columna, $entradas[$columna->code], dispersar: false);
                continue;
            }

            $valor = match ($columna->type) {
                'select'     => $columna->options->first()?->id,
                'instrument' => $this->instrumentoDe($columna),
                'date'       => $fecha->toDateString(),
                'number'     => $this->numero($columna, $muestra),
                default      => $columna->role === TestField::ROLE_SAMPLE_CODE
                    ? $muestra->code
                    : $this->texto($columna),
            };

            $celdas[$columna->code] = $this->porReplica($columna, $valor);

        }

        return array_filter($celdas, fn ($v) => $v !== null);
    }

    /**
     * Una columna puede pedir VARIAS mediciones sobre la misma muestra
     * (`replicates`): la rigidez se mide cinco o seis veces, y el grado de
     * polimerización cuatro.
     *
     * Un escalar solo llena la réplica 1 y la hoja queda sin validar por
     * "faltan valores obligatorios" — con las 16 columnas de la viscometría, sin
     * esto la prueba más larga del catálogo era justamente la que no entraba al
     * informe. Se reparte el mismo valor en todas las casillas, con una
     * dispersión mínima en las numéricas: seis mediciones idénticas al centésimo
     * no existen en una bancada real, y son las que alimentan el cálculo de
     * repetibilidad.
     */
    private function porReplica(TestField $columna, mixed $valor, bool $dispersar = true): mixed
    {
        $replicas = max(1, (int) ($columna->replicates ?? 1));

        if ($valor === null || $replicas === 1) {
            return $valor;
        }

        $celdas = [];

        for ($i = 1; $i <= $replicas; $i++) {
            $celdas[$i] = $dispersar && (is_float($valor) || is_int($valor))
                ? $this->redondear((float) $valor * (1 + $this->azar(-0.01, 0.01)), $columna)
                : $valor;
        }

        return $celdas;
    }

    /**
     * El valor de una columna numérica.
     *
     * Si es un RESULTADO, sale del cuadro de límites que le toca a esta muestra
     * —el mismo con el que el informe la va a juzgar—, así que el veredicto
     * impreso es real y no una casualidad. Si no hay límite declarado, cae al
     * rango de la columna, y si tampoco lo hay, a un número plausible: esas
     * columnas son entradas intermedias que el informe no imprime.
     */
    private function numero(TestField $columna, Sample $muestra): ?float
    {
        $limite = $columna->output_analyte_id
            ? $this->limite($columna, $muestra)
            : null;

        if ($limite) {
            $fuera = in_array($limite->analyte?->code, self::FUERA_DE_NORMA, true);

            if ($limite->max_value !== null) {
                // Bien dentro (60 % del techo) o pasado a propósito (130 %).
                return $this->redondear((float) $limite->max_value * ($fuera ? 1.3 : 0.6), $columna);
            }

            if ($limite->min_value !== null) {
                // Los de mínimo van al revés: 140 % del piso está conforme.
                return $this->redondear((float) $limite->min_value * ($fuera ? 0.7 : 1.4), $columna);
            }
        }

        if ($columna->min_value !== null && $columna->max_value !== null) {
            return $this->redondear(
                (float) $columna->min_value
                    + ((float) $columna->max_value - (float) $columna->min_value) * 0.5,
                $columna,
            );
        }

        // Sin límite ni rango: un número chico y positivo. Son entradas
        // intermedias que no se imprimen; el resultado sale de la fórmula.
        return $this->redondear(1 + $this->azar(0, 9), $columna);
    }

    /**
     * El límite de norma del parámetro de esta columna, para esta muestra.
     *
     * El grupo del cuadro lo declara el ANALITO (`analytes.group`: fiqui o dga),
     * no la prueba. Acá se usaba `report_comment_group`, que es otra cosa —el
     * corte de PÁGINAS del informe— y con ese nombre el resolver no encontraba
     * ningún cuadro: los valores caían todos al respaldo genérico y el papel
     * salía con un factor de potencia de 4.94 % contra un máximo de 0.5. Es el
     * mismo grupo que usa `ResultMaterializer` al congelar el veredicto, así que
     * el número generado y el juicio impreso miran la misma tabla.
     */
    private function limite(TestField $columna, Sample $muestra): ?SpecLimit
    {
        static $cache = [];

        $grupo = $columna->analyte?->group ?? 'fiqui';

        if (! array_key_exists($grupo, $cache)) {
            $cache[$grupo] = app(SpecSetResolver::class)->forSample($muestra, $grupo);
        }

        return $cache[$grupo]?->limits
            ->firstWhere('analyte_id', $columna->output_analyte_id);
    }

    /** Con los decimales que declara la columna, no con los del azar. */
    private function redondear(float $valor, TestField $columna): float
    {
        return round($valor, $columna->decimals ?? 2);
    }

    /** Un texto corto para las columnas de texto que no son el correlativo. */
    private function texto(TestField $columna): ?string
    {
        return $columna->is_required ? '—' : null;
    }

    /**
     * El instrumento que corresponde a ESA columna.
     *
     * Sale de los códigos que la plantilla del sistema viejo ofrecía en esa
     * columna, que son el NOMBRE de los instrumentos del catálogo. Buscarlo así
     * es lo que evita poner un tensiómetro donde va una balanza.
     */
    private function instrumentoDe(TestField $columna): ?int
    {
        static $cache = [];

        if (array_key_exists($columna->id, $cache)) {
            return $cache[$columna->id];
        }

        $codigos = $columna->options
            ->map(fn ($o) => preg_match('/\b(PP-LA-\d+[A-Z](?:-\d+)?)\b/', (string) $o->value, $m) ? $m[1] : null)
            ->filter()
            ->all();

        $id = Instrument::withoutGlobalScopes()
            ->where('tenant_id', self::TENANT_ID)
            ->whereIn('name', $codigos)
            ->orderBy('id')
            ->value('id');

        // Sin códigos declarados, cualquiera del laboratorio: la columna quedó
        // como texto libre en el sistema viejo y no declara sus equipos.
        return $cache[$columna->id] = $id ?? Instrument::withoutGlobalScopes()
            ->where('tenant_id', self::TENANT_ID)
            ->orderBy('id')
            ->value('id');
    }

    /** Generador con semilla fija (congruencial lineal). */
    private function azar(float $desde, float $hasta): float
    {
        $this->semilla = ($this->semilla * 1103515245 + 12345) & 0x7FFFFFFF;

        return $desde + ($this->semilla / 0x7FFFFFFF) * ($hasta - $desde);
    }
}
