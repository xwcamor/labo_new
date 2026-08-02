<?php

namespace Database\Seeders;

use App\Models\Analyte;
use App\Models\Equipment;
use App\Models\Instrument;
use App\Models\QcChart;
use App\Models\Reception;
use App\Models\Sample;
use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\User;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
use App\Services\Lab\ReceptionService;
use App\Services\Lab\WorksheetService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Datos de demostración: equipos, hojas de trabajo cargadas y validadas.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ EXISTE                                                           │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Sin esto, después de sembrar quedaban las 29 pruebas cargadas y todas las
 * pantallas de trabajo vacías: cero hojas, cero resultados, cero puntos de
 * control. Un sistema donde todas las pantallas están en blanco no se puede
 * evaluar —no se distingue "no hay datos" de "no funciona"—, y las tres piezas
 * que de verdad hay que ver funcionando son justamente las que necesitan datos:
 * el cálculo del servidor, la materialización de resultados al validar, y la
 * carta de control.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ ESTO ES LO ÚNICO INVENTADO DE TODO EL SEED                               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Las pruebas, sus columnas, sus opciones, sus fórmulas, los instrumentos y los
 * clientes son datos REALES del sistema anterior. Los equipos y las mediciones
 * de acá NO: son inventados, y por eso van con un `external_ref` que empieza con
 * DEMO y con una nota en cada hoja. Se borran con:
 *
 *     php artisan lab:demo --limpiar
 *
 * Los datos reales de equipos y muestras del laboratorio no se versionan en
 * este repositorio, que es público: entran por el volcado privado, en la fase
 * de migración histórica.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ CÓMO SE CARGAN                                                           │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Por el MISMO servicio que usa la pantalla (`WorksheetService`), no con
 * inserciones directas. Es a propósito: así el seed ejercita el camino real —el
 * servidor calcula las fórmulas, exige el patrón antes de las muestras, cierra,
 * valida y materializa— y si algo de esa cadena se rompe, se rompe el seed y se
 * nota acá, no en la bancada.
 *
 * Los números salen de un generador con semilla fija, así que dos corridas dan
 * exactamente lo mismo. Están dentro de rangos plausibles para aceite mineral en
 * servicio, con una deriva leve en el tiempo para que las tendencias tengan
 * forma, pero NO pretenden representar ningún equipo real.
 */
class LabDemoWorksheetsSeeder extends Seeder
{
    private const TENANT_ID = 1;

    /** Marca de agua: todo lo que este sembrador crea la lleva. */
    public const MARCA = 'DEMO';

    /** Cuántas campañas de muestreo, hacia atrás desde el mes pasado. */
    private const CAMPANAS = 6;

    /** Semilla del generador. Fija para que el seed sea reproducible. */
    private int $semilla = 20260728;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->line('  Datos de demostración: omitidos en producción.');

            return;
        }

        $analista = User::where('email', 'joe@example.com')->first()
            ?? User::where('tenant_id', self::TENANT_ID)->first();

        if (! $analista) {
            $this->command?->warn('No hay usuario en el workspace 1; se omite la demostración.');

            return;
        }

        if (TestDefinition::where('code', 'analisis_cromatografico')->doesntExist()) {
            $this->command?->warn('Las pruebas no están cargadas; se omite la demostración.');

            return;
        }

        // Las hojas se cargan por el servicio real, y el servicio sella quién
        // cargó y quién validó cada valor. Sin sesión esos campos quedarían en
        // nulo y la hoja se vería como si la hubiera escrito nadie.
        Auth::login($analista);

        try {
            $equipos = $this->equipos();

            if ($equipos === []) {
                $this->command?->warn('No hay clientes en el workspace 1; se omite la demostración.');

                return;
            }

            $this->cartaDeControl();

            // La recepción va PRIMERO, como en el laboratorio: primero entra la
            // muestra y se le emite su correlativo, después se ensaya. Devuelve
            // las pruebas pedidas, que es lo que la bancada va a cargar.
            $pedidos = $this->recepciones($equipos);

            $hojas = 0;
            $hojas += $this->campanas('analisis_cromatografico', $equipos, $pedidos, fn (int $i, int $c) => $this->cromas($i, $c));
            $hojas += $this->campanas('numero_acido', $equipos, $pedidos, fn (int $i, int $c) => $this->acidez($i, $c));
            $hojas += $this->campanas('contenido_de_agua', $equipos, $pedidos, fn (int $i, int $c) => $this->agua($i, $c));
            $hojas += $this->campanas('rigidez_dielectrica', $equipos, $pedidos, fn (int $i, int $c) => $this->rigidez($i, $c));
            // Furanos entra para que la muestra de demostración tenga las TRES
            // familias que el informe imprime (fisicoquímico, cromatografía y
            // furanos). Con una sola familia el informe no se puede evaluar: el
            // conteo de páginas, el bloque de diagnóstico y la conclusión son
            // justamente lo que cambia cuando hay varias.
            $hojas += $this->campanas('furanos', $equipos, $pedidos, fn (int $i, int $c) => $this->furanos($i, $c));

            // Lo último del circuito: el informe. Sin esto el listado de
            // informes quedaba con una fila y no se podía evaluar —ni el orden,
            // ni la búsqueda, ni el semáforo de estados—.
            $informes = $this->informes($analista);

            $this->command?->info(sprintf(
                'Demostración: %d equipos, %d recepciones, %d muestras, %d hojas validadas, %d resultados, %d informes.',
                count($equipos),
                \App\Models\Reception::withoutGlobalScopes()->count(),
                \App\Models\Sample::withoutGlobalScopes()->count(),
                $hojas,
                \App\Models\Result::count(),
                $informes,
            ));
        } finally {
            Auth::logout();
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Equipos
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Seis transformadores repartidos entre los primeros clientes reales.
     *
     * Cuelgan de clientes reales porque el informe y el tablero se miran por
     * cliente: seis equipos de un cliente inventado no muestran nada de lo que
     * hay que ver.
     *
     * @return array<int,Equipment>
     */
    private function equipos(): array
    {
        $clientes = \App\Models\Customer::withoutGlobalScopes()
            ->where('tenant_id', self::TENANT_ID)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->limit(3)
            ->pluck('id')
            ->all();

        if ($clientes === []) {
            return [];
        }

        // La placa COMPLETA y el resto de los datos que el informe imprime.
        //
        // Antes esta plantilla traía cinco valores y el informe de demostración
        // salía con catorce campos en raya: marca, locación, sistema de
        // preservación, conmutador, volumen de aceite, estado de servicio,
        // terciario, segunda potencia. Con el papel así no se puede juzgar ni el
        // diseño ni la maqueta —una raya no ocupa lo que ocupa un dato— y
        // tampoco se ve si algún campo quedó sin cablear.
        //
        // Los dos autotransformadores llevan TERCIARIO y tres etapas de
        // enfriamiento a propósito: es el caso que la placa con barras tiene que
        // saber mostrar ("220 / 138 / 13.8" y "100 / 133 / 167").
        $plantilla = [
            // tag, nombre, alta, baja, terciario, mva1, mva2, mva3, litros
            ['T-01', 'Transformador de potencia 1',    138.00, 13.80, null,   30.00,  40.00,  50.00, 12500],
            ['T-02', 'Transformador de potencia 2',    138.00, 13.80, null,   20.00,  26.60,  null,   9800],
            ['T-03', 'Transformador de distribución 1', 23.00,  0.40, null,    1.00,  null,   null,    450],
            ['T-04', 'Transformador de distribución 2', 23.00,  0.40, null,    0.50,  null,   null,    320],
            ['T-05', 'Autotransformador 1',            220.00, 138.00, 13.80, 100.00, 133.00, 167.00, 42000],
            ['T-06', 'Transformador de horno 1',        33.00,  0.80, null,   12.00,  null,   null,   6200],
        ];

        // Los catálogos de la placa. Se toman los primeros de cada uno: la
        // demostración no depende de que exista una marca concreta, pero sí de
        // que el informe muestre ALGO en esos campos.
        $marcas       = \App\Models\Brand::withoutGlobalScopes()->orderBy('id')->limit(3)->pluck('id')->all();
        $preservacion = \App\Models\TransformerPreservation::withoutGlobalScopes()->orderBy('id')->pluck('id')->all();
        $conmutadores = \App\Models\TapChangerType::withoutGlobalScopes()->orderBy('id')->pluck('id')->all();

        $equipos = [];

        foreach ($plantilla as $i => [$tag, $nombre, $alta, $baja, $terciario, $mva, $mva2, $mva3, $litros]) {
            $ref = self::MARCA . '-' . $tag;

            // updateOrCreate y no firstOrCreate: estas filas son de
            // DEMOSTRACIÓN y el sembrador es su dueño. Con firstOrCreate, cada
            // dato que se agregaba a la plantilla no llegaba nunca a los equipos
            // ya sembrados, y el informe de demostración seguía saliendo con los
            // mismos campos en raya por más que se completara el sembrador.
            $equipos[] = Equipment::withoutGlobalScopes()->updateOrCreate(
                ['external_ref' => $ref, 'tenant_id' => self::TENANT_ID],
                [
                    'slug'             => Str::random(22),
                    'name'             => $nombre,
                    'tag'              => $tag,
                    'serial'           => sprintf('%s-SN-%04d', self::MARCA, 1000 + $i),
                    'customer_id'      => $clientes[$i % count($clientes)],
                    'equipment_type_id' => $i >= 2 && $i <= 3 ? 2 : ($i === 5 ? 3 : 1),
                    'oil_type_id'      => 1,      // mineral
                    'voltage_kv_hv'    => $alta,
                    'voltage_kv_lv'    => $baja,
                    'voltage_kv_tv'    => $terciario,
                    'power_mva'        => $mva,
                    'power_mva_2'      => $mva2,
                    'power_mva_3'      => $mva3,
                    'phases'           => 3,
                    'manufacture_year' => 2005 + $i,
                    // El resto de la chapa, que el informe imprime en el bloque
                    // "Información del equipo".
                    // La locación tiene que ser del MISMO cliente dueño del
                    // equipo: poner una de otro cliente no sería un hueco menos,
                    // sería un dato falso en el informe.
                    'customer_location_id' => \App\Models\CustomerLocation::withoutGlobalScopes()
                        ->where('customer_id', $clientes[$i % count($clientes)])
                        ->orderBy('id')->value('id'),
                    'brand_id'                    => $marcas[$i % max(count($marcas), 1)] ?? null,
                    'transformer_preservation_id' => $preservacion[$i % max(count($preservacion), 1)] ?? null,
                    'tap_changer_type_id'         => $conmutadores[$i % max(count($conmutadores), 1)] ?? null,
                    'oil_brand'        => ['Nynas', 'Shell', 'Ergon'][$i % 3],
                    'oil_volume'       => $litros,
                    'oil_volume_unit'  => 'L',
                    // Un equipo fuera de servicio entre los seis: el informe
                    // tiene que poder decirlo, y es el caso que en el sistema
                    // anterior salía como "-" sin distinguirlo de "no se sabe".
                    'service_state'    => $i === 3 ? 'out_of_service' : 'in_service',
                    'is_active'        => true,
                ],
            );
        }

        return $equipos;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Informes
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Un informe por cada muestra que ya tenga ensayos validados.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ POR QUÉ NO SE EMITEN TODOS                                           │
     * └──────────────────────────────────────────────────────────────────────┘
     * Las campañas más viejas salen EMITIDAS y las dos más recientes quedan en
     * BORRADOR. Es el estado real de un laboratorio en cualquier martes: lo del
     * mes pasado ya salió, lo de esta semana está en revisión. Emitir todo
     * dejaría el listado con una sola columna de estado y el semáforo, el
     * candado y el filtro de estado no se podrían mirar.
     *
     * La emisión pasa por `SampleReportService::issue()`, la MISMA vía que la
     * pantalla: si mañana emitir implica un paso más, la demostración lo hereda
     * en lugar de quedar con datos que el sistema real no podría producir.
     */
    private function informes(User $analista): int
    {
        $servicio = app(\App\Services\Lab\SampleReportService::class);

        // Las muestras con algo validado, de la más vieja a la más nueva: el
        // correlativo del informe tiene que seguir el orden en que se emitieron.
        $muestras = Sample::withoutGlobalScopes()
            ->where('tenant_id', self::TENANT_ID)
            ->whereDoesntHave('reports')
            ->whereHas('tests', fn ($q) => $q->whereIn('status', [
                \App\Models\SampleTest::STATUS_VALIDATED,
                \App\Models\SampleTest::STATUS_REPORTED,
            ]))
            ->orderBy('sampled_at')
            ->orderBy('number')
            ->get();

        if ($muestras->isEmpty()) {
            return 0;
        }

        // El corte: el último quinto queda sin emitir.
        $enBorrador = max(1, (int) ceil($muestras->count() / 5));
        $emitirHasta = $muestras->count() - $enBorrador;

        $creados = 0;

        foreach ($muestras->values() as $indice => $muestra) {
            $informe = $servicio->create($muestra, [
                'sampling_reason' => $this->razon($indice),
            ], $analista->id);
            $creados++;

            if ($indice >= $emitirHasta) {
                continue;
            }

            // La fecha de emisión y la de entrega, unos días después de la toma:
            // el listado ordena por esas columnas y con todas en el mismo día no
            // se vería si el orden funciona.
            $emision = ($muestra->sampled_at ?? now())->copy()->addDays(4);
            $informe->update([
                'issued_at'    => $emision->toDateString(),
                'delivered_at' => $emision->copy()->addDays(2)->toDateString(),
            ]);

            // El análisis y su confirmación, ANTES de emitir: es el camino de
            // la pantalla, y desde que `issue()` lo exige, sin esto la
            // demostración grande no emitiría ni un informe. Ver el comentario
            // equivalente en `LabFullReportSeeder::informe()`.
            app(\App\Services\Lab\DiagnosisTextService::class)->generate($muestra);

            $informe->forceFill([
                'analysis_confirmed_at' => now(),
                'analysis_confirmed_by' => $analista->id,
            ])->save();

            // El idioma queda congelado en el snapshot. Ver el comentario largo
            // en `LabFullReportSeeder::informe()`.
            $idioma = app()->getLocale();
            app()->setLocale('es');

            try {
                $servicio->issue($informe->fresh(), $analista->id);
            } finally {
                app()->setLocale($idioma);
            }
        }

        return $creados;
    }

    /**
     * La razón del análisis, que el informe imprime y el listado ordena.
     *
     * Las cuatro que el laboratorio usa de verdad; se reparten en ciclo para que
     * la columna tenga variedad sin inventar categorías nuevas.
     */
    private function razon(int $indice): string
    {
        return [
            'Mantenimiento programado',
            'Control periódico',
            'Puesta en servicio',
            'Diagnóstico por falla',
        ][$indice % 4];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Carta de control
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Una carta para el Número Ácido, sobre un lote de patrón.
     *
     * Los límites son los del patrón que se usa en la demostración, no un
     * estándar de nada. Van declarados y NO derivados de los puntos: la carta
     * tiene que existir ANTES de que haya mediciones, que es justamente lo que
     * el sistema viejo no permitía —allá los límites se pisaban al cambiar de
     * lote y las cartas históricas quedaban dibujadas contra el criterio de hoy—.
     */
    private function cartaDeControl(): void
    {
        $prueba = TestDefinition::where('code', 'numero_acido')->first();

        if (! $prueba) {
            return;
        }

        $columna = TestField::where('test_definition_id', $prueba->id)
            ->where('code', 'resultado_mgkohg_aceite')
            ->first();

        if (! $columna) {
            return;
        }

        $centro = 0.150;
        $sd = 0.008;

        QcChart::withoutGlobalScopes()->firstOrCreate(
            ['test_definition_id' => $prueba->id, 'control_lot' => self::MARCA . '-LOTE-01'],
            [
                'slug'           => Str::random(22),
                'test_field_id'  => $columna->id,
                'analyte_id'     => Analyte::withoutGlobalScopes()->where('code', 'acid')->value('id'),
                'label'          => 'Número Ácido — patrón ' . self::MARCA . '-LOTE-01',
                'center'         => $centro,
                'sd'             => $sd,
                'lwl'            => $centro - 2 * $sd,
                'uwl'            => $centro + 2 * $sd,
                'lcl'            => $centro - 3 * $sd,
                'ucl'            => $centro + 3 * $sd,
                'is_derived'     => false,
                'warn_sigma'     => 2,
                'action_sigma'   => 3,
                'effective_from' => Carbon::now()->subMonths(self::CAMPANAS + 1)->startOfMonth(),
                'is_active'      => true,
                'tenant_id'      => self::TENANT_ID,
                'comment'        => 'Carta de demostración.',
            ],
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Hojas
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Una recepción por campaña: entra la entrega del cliente, se emiten los
     * correlativos, se le asigna el equipo a cada muestra y se piden las cuatro
     * pruebas.
     *
     * Es el orden real del laboratorio y por eso el sembrador lo respeta: si la
     * recepción no existe, la bancada no tiene qué cargar.
     *
     * @param  array<int,Equipment> $equipos
     * @return array<int,array<int,array<string,int>>>  [campaña][equipo][prueba] => sample_test_id
     */
    private function recepciones(array $equipos): array
    {
        $servicio = new ReceptionService();
        $pruebas = TestDefinition::whereIn('code', [
            'analisis_cromatografico', 'numero_acido', 'contenido_de_agua', 'rigidez_dielectrica',
            'furanos',
        ])->pluck('id', 'code');

        // Una recepción es de UN cliente, así que los equipos se agrupan por
        // cliente y cada grupo entra en su propia entrega. No es un detalle
        // cosmético: el servicio rechaza asignarle a una muestra un equipo que
        // no sea del cliente de su recepción, que es justo el agujero que tenía
        // el sistema anterior.
        $porCliente = [];

        foreach ($equipos as $i => $equipo) {
            $porCliente[$equipo->customer_id][$i] = $equipo;
        }

        $pedidos = [];

        for ($campana = 0; $campana < self::CAMPANAS; $campana++) {
            $fecha = Carbon::now()->subMonths(self::CAMPANAS - $campana)->startOfMonth()->addDays(8);
            $orden = 0;

            foreach ($porCliente as $clienteId => $delCliente) {
                $orden++;
                $codigo = sprintf('%s-REM-%02d-%d', self::MARCA, $campana + 1, $orden);

                $recepcion = Reception::withoutGlobalScopes()->where('code', $codigo)->first();

                if (! $recepcion) {
                    $recepcion = Reception::create([
                        'slug'          => Str::random(22),
                        'code'          => $codigo,
                        'customer_id'   => $clienteId,
                        'received_at'   => $fecha,
                        'due_at'        => $fecha->copy()->addDays(10)->toDateString(),
                        'packages'      => count($delCliente),
                        'container_ok'  => true,
                        'volume_ok'     => true,
                        'label_ok'      => true,
                        'sampler_name'  => 'Personal del cliente',
                        // Los tres datos de la cabecera del informe que antes
                        // salían en raya. La orden de servicio es lo primero que
                        // el cliente busca en el papel para conciliar la factura.
                        'service_order' => sprintf('OS-%d-%04d', $fecha->year, ($campana + 1) * 100 + $orden),
                        'contact_info'  => 'contacto.laboratorio@ejemplo.com',
                        'end_user'      => 'Gerencia de Mantenimiento',
                        'notes'         => self::MARCA . ' — recepción de demostración generada por el sembrador.',
                        'tenant_id'     => self::TENANT_ID,
                        'created_by'    => Auth::id(),
                    ]);

                    $servicio->confirm($recepcion, count($delCliente));
                }

                // La cabecera del informe, también en las recepciones ya
                // sembradas: se completa solo si está vacía, para no pisar algo
                // que alguien haya cargado a mano sobre la demostración.
                if ($recepcion->service_order === null) {
                    $recepcion->update([
                        'service_order' => sprintf('OS-%d-%04d', $fecha->year, ($campana + 1) * 100 + $orden),
                        'contact_info'  => 'contacto.laboratorio@ejemplo.com',
                        'end_user'      => 'Gerencia de Mantenimiento',
                    ]);
                }

                $muestras = $recepcion->samples()->orderBy('number')->get();
                $indices = array_keys($delCliente);

                foreach ($muestras as $n => $muestra) {
                    $i = $indices[$n] ?? null;

                    if ($i === null) {
                        continue;
                    }

                    if ($muestra->equipment_id === null) {
                        $servicio->assignEquipment($muestra, $equipos[$i]->id);
                    }

                    $servicio->requestTests($muestra, $pruebas->values()->all());

                    // Las CONDICIONES DE LA TOMA. Son datos de campo: los trae
                    // quien extrae la muestra, y el informe los imprime en el
                    // bloque del equipo. Sin ellos el papel de demostración
                    // salía con siete rayas seguidas.
                    //
                    // Se varían por muestra y por campaña para que las
                    // tendencias del informe no salgan planas, que es lo que
                    // pasa cuando el sembrador escribe el mismo número en todas.
                    if ($muestra->sampling_point === null) {
                        $muestra->update([
                            'description'       => 'Aceite mineral en servicio, tomado del equipo en operación.',
                            'sampling_point'    => ['Válvula inferior', 'Válvula superior', 'Conservador'][$n % 3],
                            'sampling_reason'   => $campana === 0 ? 'Puesta en servicio' : 'Mantenimiento programado',
                            // La del aceite es la más alta: sale del equipo
                            // caliente. La ambiente es la del lugar de la toma.
                            'oil_temp_c'        => 52.0 + $n * 1.5 + $campana,
                            'equipment_temp_c'  => 48.0 + $n * 1.2 + $campana,
                            'ambient_temp_c'    => 21.0 + ($campana % 4),
                            'relative_humidity' => 58 + ($n % 5) * 3,
                        ]);
                    }

                    // El correlativo se guarda junto a los ids: la columna
                    // "Nº de Muestra" de la plantilla lo muestra, y es lo que va
                    // impreso en el envase.
                    $pedidos[$campana][$i]['_code'] = $muestra->code;

                    foreach ($muestra->tests()->with('definition:id,code')->get() as $pedido) {
                        $pedidos[$campana][$i][$pedido->definition->code] = $pedido->id;
                    }
                }
            }
        }

        return $pedidos;
    }

    /**
     * Una hoja por campaña, con una fila por muestra recibida.
     *
     * @param  array<int,Equipment> $equipos
     * @param  array<int,array<int,array<string,int>>> $pedidos
     * @param  callable(int,int):array<string,mixed> $valores  (indiceEquipo, campana) => celdas
     */
    private function campanas(string $codigoPrueba, array $equipos, array $pedidos, callable $valores): int
    {
        $prueba = TestDefinition::where('code', $codigoPrueba)->first();

        if (! $prueba) {
            return 0;
        }

        $servicio = new WorksheetService();
        $hechas = 0;

        for ($campana = 0; $campana < self::CAMPANAS; $campana++) {
            $fecha = Carbon::now()->subMonths(self::CAMPANAS - $campana)->startOfMonth()->addDays(9);

            $hoja = Worksheet::withoutGlobalScopes()
                ->where('test_definition_id', $prueba->id)
                ->whereDate('run_date', $fecha)
                ->where('notes', 'like', self::MARCA . '%')
                ->first();

            if ($hoja) {
                continue;   // ya sembrada
            }

            $hoja = Worksheet::create([
                'slug'               => Str::random(22),
                'test_definition_id' => $prueba->id,
                'run_date'           => $fecha,
                'analyst_id'         => Auth::id(),
                'status'             => Worksheet::STATUS_DRAFT,
                'ambient_temp_c'     => round(21 + $this->azar(0, 4), 1),
                'ambient_humidity'   => round(55 + $this->azar(0, 10)),
                // La temperatura de la MUESTRA al momento del ensayo, que el
                // informe imprime en el bloque de condiciones. Es distinta de la
                // ambiente: la muestra llega y se atempera, y para varios
                // parámetros el valor depende de a qué temperatura se midió.
                'sample_temp_c'      => round(24 + $this->azar(0, 3), 1),
                // La presión del laboratorio. Lima está casi a nivel del mar:
                // del orden de 1013 hPa, con la variación del día.
                'lab_pressure_hpa'   => round(1010 + $this->azar(0, 6), 1),
                'notes'              => self::MARCA . ' — hoja de demostración generada por el sembrador.',
                'tenant_id'          => self::TENANT_ID,
            ]);

            $porDefecto = $this->porDefecto($prueba, $fecha);

            // El patrón va PRIMERO: mientras la prueba lo exija, el servicio no
            // acepta muestras sin él. Se siembra en ese orden a propósito, para
            // que el seed pase por la misma regla que la bancada.
            if ($prueba->requires_control || $codigoPrueba === 'numero_acido') {
                $servicio->saveRow(
                    $hoja,
                    ['kind' => WorksheetRow::KIND_CONTROL],
                    $this->patronAcidez($campana) + $porDefecto,
                );
            }

            // Cada fila referencia la PRUEBA PEDIDA. El código de muestra y el
            // equipo no se tipean: se heredan de la muestra que se recibió.
            foreach ($equipos as $i => $equipo) {
                $pedidoId = $pedidos[$campana][$i][$codigoPrueba] ?? null;

                if ($pedidoId === null) {
                    continue;
                }

                // Una sola llamada con TODAS las celdas: guardar una fila es
                // guardarla entera, así que un segundo guardado parcial dejaría
                // en nulo lo que no viniera en él.
                $servicio->saveRow($hoja, [
                    'kind'           => WorksheetRow::KIND_SAMPLE,
                    'sample_test_id' => $pedidoId,
                ], $valores($i, $campana)
                   + ['no_de_muestra' => $pedidos[$campana][$i]['_code'] ?? null]
                   + $porDefecto);
            }

            // La hoja publica sola al quedar completa. Esta llamada queda como
            // respaldo para las que traen columnas opcionales sin llenar y por
            // eso no dispararon la publicación al guardar la última fila.
            // Antes había acá un `close()` que ya no existe: el estado
            // intermedio se sacó del flujo.
            if ($hoja->fresh()->status !== \App\Models\Worksheet::STATUS_VALIDATED) {
                $servicio->validate($hoja->fresh());
            }
            $hechas++;
        }

        return $hechas;
    }

    /**
     * Las celdas que la prueba exige y que no son la medición: la norma, el
     * instrumento con el que se midió, el tipo de fluido, las fechas.
     *
     * Se resuelven MIRANDO LA PLANTILLA y no con una lista escrita a mano por
     * prueba. Es lo que hace que agregar una columna obligatoria a una prueba no
     * rompa el sembrador, y de paso es la prueba de que el editor de columnas y
     * la bancada están de acuerdo: si una columna quedara obligatoria y sin
     * forma de llenarse, el seed se cae acá y no en producción.
     *
     * @return array<string,mixed>
     */
    private function porDefecto(TestDefinition $prueba, Carbon $fecha): array
    {
        static $cache = [];

        if (! isset($cache[$prueba->id])) {
            $fijas = [];
            $fechas = [];

            // Las obligatorias, MÁS la norma del método aunque sea opcional. En
            // furanos la norma no es obligatoria y quedaba vacía, y de ahí el
            // informe imprimía "(*) Norma de referencia —": una demostración que
            // parece incompleta se lee como un sistema que no guarda el dato.
            $columnas = TestField::with('options')
                ->where('test_definition_id', $prueba->id)
                ->where(fn ($q) => $q
                    ->where('is_required', true)
                    ->orWhere('role', TestField::ROLE_STANDARD))
                ->get();

            foreach ($columnas as $columna) {
                match ($columna->type) {
                    // Una columna de selección guarda clave foránea, no texto.
                    // Es el arreglo del sistema viejo, que metía el id de la
                    // opción adentro de la misma columna de texto donde iba
                    // todo lo demás.
                    'select'     => $fijas[$columna->code] = $columna->options->first()?->id,
                    'instrument' => $fijas[$columna->code] = $this->instrumentoDe($columna),
                    'date'       => $fechas[] = $columna->code,
                    // El resto lo llena la medición o el código de muestra.
                    default      => null,
                };
            }

            $cache[$prueba->id] = [
                'fijas'  => array_filter($fijas, fn ($v) => $v !== null),
                'fechas' => $fechas,
            ];
        }

        $celdas = $cache[$prueba->id]['fijas'];

        foreach ($cache[$prueba->id]['fechas'] as $code) {
            $celdas[$code] = $fecha->toDateString();
        }

        return $celdas;
    }

    /**
     * El instrumento que corresponde a ESA columna.
     *
     * Sale de las opciones que traía la plantilla del sistema viejo, que se
     * conservan justamente para esto: la columna "Bureta PP-LA-01C" ofrecía los
     * códigos de las buretas, y el sembrador de instrumentos dio de alta un
     * equipo por cada uno. Buscar el equipo por ese código es lo que evita
     * poner un tensiómetro donde va una balanza.
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

        // Por NOMBRE: el nombre del instrumento ES su código de calibración.
        return $cache[$columna->id] = Instrument::withoutGlobalScopes()
            ->where('tenant_id', self::TENANT_ID)
            ->whereIn('name', $codigos)
            ->orderBy('id')
            ->value('id');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Los números
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Cromatografía: nueve gases en ppm.
     *
     * El equipo 4 (índice 3) lleva una deriva marcada de acetileno y etileno
     * para que el tablero y las tendencias tengan un caso que mirar. Los otros
     * cinco quedan en valores tranquilos.
     *
     * Las dos columnas de total NO se cargan: las calcula el servidor con la
     * fórmula portada. Si algo de esa cadena estuviera roto, el seed dejaría
     * esas celdas vacías y se vería.
     *
     * @return array<string,mixed>
     */
    private function cromas(int $equipo, int $campana): array
    {
        $deriva = $equipo === 3 ? $campana * 1.0 : $campana * 0.1;

        return [
            'hidrogeno_h2_ppm'   => round(18 + $this->azar(0, 12) + $deriva * 6, 2),
            'oxigeno_o2_ppm'     => round(9000 + $this->azar(0, 3000), 2),
            'nitrogeno_n2_ppm'   => round(45000 + $this->azar(0, 12000), 2),
            'metano_ch4_ppm'     => round(6 + $this->azar(0, 5) + $deriva * 3, 2),
            'mcarbono_co_ppm'    => round(220 + $this->azar(0, 90) + $deriva * 20, 2),
            'dcarbono_co2_ppm'   => round(2400 + $this->azar(0, 900) + $deriva * 120, 2),
            'etileno_c2h4_ppm'   => round(3 + $this->azar(0, 4) + $deriva * 9, 2),
            'etano_c2h6_ppm'     => round(4 + $this->azar(0, 3) + $deriva * 2, 2),
            'acetileno_c2h2_ppm' => round(0.2 + $this->azar(0, 1) + $deriva * 3, 2),
        ];
    }

    /**
     * Número ácido: las cuatro entradas de la titulación. El resultado en
     * mgKOH/g lo calcula el servidor.
     *
     * @return array<string,mixed>
     */
    private function acidez(int $equipo, int $campana): array
    {
        $gastado = 1.10 + $equipo * 0.28 + $campana * 0.06 + $this->azar(0, 0.08);

        return [
            'factor_koh'          => 5.61,
            'vol_blanco'          => 0.05,
            'peso_aceite_g'       => 20.00,
            'volumen_gastado_ml'  => round($gastado, 2),
        ];
    }

    /**
     * El patrón del Número Ácido: dos determinaciones alrededor del centro de
     * la carta, con una campaña fuera de los límites de advertencia para que la
     * carta muestre lo que tiene que mostrar. Una carta donde todos los puntos
     * están en el centro no demuestra que las reglas de Westgard funcionen.
     *
     * @return array<string,mixed>
     */
    private function patronAcidez(int $campana): array
    {
        // Centro 0.150 con desvío 0.008: el gastado que lo produce, dado el
        // factor y la masa de la fórmula, es (0.150*20/5.61)+0.05.
        $objetivo = $campana === 3 ? 0.169 : 0.150 + $this->azar(-0.006, 0.006);

        return [
            'factor_koh'         => 5.61,
            'vol_blanco'         => 0.05,
            'peso_aceite_g'      => 20.00,
            'volumen_gastado_ml' => round($objetivo * 20.00 / 5.61 + 0.05, 3),
        ];
    }

    /**
     * Contenido de agua: las dos determinaciones de Karl Fischer. El promedio y
     * la repetibilidad los calcula el servidor.
     *
     * @return array<string,mixed>
     */
    private function agua(int $equipo, int $campana): array
    {
        $base = 8 + $equipo * 3 + $campana * 1.2;

        return [
            'r1' => round($base + $this->azar(0, 1.5), 1),
            'r2' => round($base + $this->azar(0, 1.5), 1),
        ];
    }

    /**
     * Rigidez dieléctrica en kV. Va al revés que los demás: acá más es mejor,
     * y el aceite se degrada, así que baja con el tiempo.
     *
     * @return array<string,mixed>
     */
    private function rigidez(int $equipo, int $campana): array
    {
        return [
            'resultado_kv'                => round(62 - $equipo * 2.5 - $campana * 1.1 + $this->azar(0, 3), 1),
            'temperatura_ambiente_oc'     => round(21 + $this->azar(0, 4), 1),
            'temperatura_muestra_oc'      => round(23 + $this->azar(0, 3), 1),
            'humedad_ambiente'            => round(55 + $this->azar(0, 10), 0),
        ];
    }

    /**
     * Furanos: los cinco compuestos en ppb.
     *
     * El grado de polimerización del papel NO se carga: lo calcula el servidor
     * con la correlación de Chendong sobre el 2-FAL, que es la fórmula portada
     * del sistema anterior. Si esa cadena estuviera rota, la columna quedaría
     * vacía en el informe y se vería.
     *
     * El 2-FAL sube con la edad del papel, y por eso sube con el equipo y con la
     * campaña: es el único parámetro del informe que no vuelve a bajar cuando se
     * regenera el aceite, porque mide el papel y no el aceite. Los otros cuatro
     * compuestos quedan en trazas, que es lo normal.
     *
     * @return array<string,mixed>
     */
    private function furanos(int $equipo, int $campana): array
    {
        $fal = 240 + $equipo * 170 + $campana * 35 + $this->azar(0, 60);

        return [
            'furfuraldehido_2'               => round($fal, 1),
            'hidroxi_metil_furfuraldehido_5' => round(12 + $this->azar(0, 18), 1),
            'acetilfurano_2'                 => round($this->azar(0, 6), 1),
            'metil_2_furfuraldehido_5'       => round(4 + $this->azar(0, 9), 1),
            'furfuril_alcohol_2'             => round(8 + $this->azar(0, 22), 1),
        ];
    }

    /**
     * Generador con semilla fija (congruencial lineal).
     *
     * No se usa `rand()` a propósito: un seed que da números distintos en cada
     * corrida no se puede comparar entre dos instalaciones ni contra una
     * captura de pantalla de ayer.
     */
    private function azar(float $desde, float $hasta): float
    {
        $this->semilla = ($this->semilla * 1103515245 + 12345) & 0x7FFFFFFF;

        return $desde + ($this->semilla / 0x7FFFFFFF) * ($hasta - $desde);
    }
}
