<?php

namespace Tests\Feature\Lab;

use App\Models\QcChart;
use App\Models\TestDefinition;
use Database\Seeders\LabQcChartsSeeder;
use Database\Seeders\LabTestFieldTypesSeeder;
use Database\Seeders\LabTestTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Qué pruebas tienen carta de control: la pantalla "Tendencias" del sistema
 * anterior.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ ERA ESA PANTALLA, PORQUE EL NOMBRE ENGAÑA                            │
 * └──────────────────────────────────────────────────────────────────────────┘
 * No era el histórico de un transformador. Era la carta de control del PATRÓN
 * interno del laboratorio: su consulta traía únicamente las filas de tipo Patrón
 * Control (`tendences_controller.rb:20`), y las líneas equivalentes para Muestra
 * y Duplicado estaban comentadas — la tendencia sobre muestras reales estaba
 * prevista y desactivada.
 *
 * Tampoco salía impresa: ninguno de los dieciséis partials del informe PDF
 * contiene un gráfico. Vivía en pantalla, y la única forma de sacarla era el menú
 * de exportación de amCharts, a mano.
 *
 * Lo que se fija acá es CUÁLES son, porque es la clase de lista que se completa
 * "por prolijidad" hasta abarcar las 29 pruebas — y una carta sin patrón que
 * medir queda vacía, lo que en pantalla se lee como un control que se dejó de
 * correr.
 */
class QcChartsSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenants')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]]);

        $this->seed([LabTestTemplatesSeeder::class, LabTestFieldTypesSeeder::class]);
        $this->seed(LabQcChartsSeeder::class);
    }

    public function test_las_dieciseis_cartas_del_sistema_anterior(): void
    {
        // Ocho pruebas, y la cromatografía con una carta POR GAS: son 7 + 9.
        $this->assertSame(16, QcChart::withoutGlobalScopes()->count());
    }

    public function test_las_pruebas_que_la_tenian_y_solo_esas(): void
    {
        $conCarta = QcChart::withoutGlobalScopes()
            ->join('test_definitions', 'test_definitions.id', '=', 'qc_charts.test_definition_id')
            ->distinct()
            ->orderBy('test_definitions.code')
            ->pluck('test_definitions.code')
            ->all();

        $this->assertSame([
            'analisis_cromatografico',
            'contenido_de_agua',
            'densidad_relativa',
            'factor_de_potencia_100o',
            'factor_de_potencia_25o',
            'numero_acido',
            'rigidez_dielectrica',
            'tension_interfacial',
        ], $conCarta);
    }

    public function test_las_cualitativas_no_tienen_carta(): void
    {
        // Color y Condición Visual quedaban fuera en el sistema anterior por la
        // condición del menú, y con razón: no hay desvío estándar de "claro".
        foreach (['color', 'condicion_visual'] as $codigo) {
            $prueba = TestDefinition::where('code', $codigo)->firstOrFail();

            $this->assertSame(
                0,
                QcChart::withoutGlobalScopes()->where('test_definition_id', $prueba->id)->count(),
                "La prueba cualitativa {$codigo} no debería tener carta de control.",
            );
        }
    }

    public function test_el_factor_de_potencia_a_90_grados_no_la_tenia(): void
    {
        // Es el caso que delata si alguien "completó" la lista por prolijidad: el
        // FP a 90 °C es la misma medición que el de 25 y el de 100, pero su id era
        // 26 y el menú del sistema anterior no lo ofrecía.
        $prueba = TestDefinition::where('code', 'factor_de_potencia_90o')->firstOrFail();

        $this->assertSame(
            0,
            QcChart::withoutGlobalScopes()->where('test_definition_id', $prueba->id)->count(),
        );
    }

    public function test_los_nueve_gases_tienen_su_propia_carta(): void
    {
        $cromas = TestDefinition::where('code', 'analisis_cromatografico')->firstOrFail();

        $this->assertSame(
            9,
            QcChart::withoutGlobalScopes()->where('test_definition_id', $cromas->id)->count(),
        );
    }

    // ─── Los límites ─────────────────────────────────────────────────────

    /**
     * Las cartas nacen con los límites QUE EL LABORATORIO CALIBRÓ.
     *
     * Este test afirmaba lo contrario —que nacían derivadas y sin límites—
     * porque se creía que los cinco valores del sistema anterior no estaban en
     * ninguna parte. Sí están: `patron_tendences` del volcado, 27 filas, 16 con
     * números, justo las 16 cartas de acá.
     *
     * La diferencia no es cosmética: una carta derivada mueve su propio centro
     * con cada punto que se carga, así que una corrida que históricamente estaba
     * fuera de control puede terminar cayendo dentro.
     */
    public function test_las_cartas_nacen_con_los_limites_declarados_del_laboratorio(): void
    {
        foreach (QcChart::withoutGlobalScopes()->get() as $carta) {
            $this->assertFalse(
                (bool) $carta->is_derived,
                "La carta {$carta->label} quedó derivada en vez de usar los límites del laboratorio.",
            );

            foreach (['lcl', 'lwl', 'center', 'uwl', 'ucl'] as $limite) {
                $this->assertNotNull($carta->{$limite}, "A {$carta->label} le falta {$limite}.");
            }
        }
    }

    /** Los cinco valores, exactos, de la carta que el laboratorio mostró en pantalla. */
    public function test_el_factor_de_potencia_25_lleva_sus_cinco_numeros(): void
    {
        $prueba = TestDefinition::where('code', 'factor_de_potencia_25o')->firstOrFail();
        $carta  = QcChart::withoutGlobalScopes()
            ->where('test_definition_id', $prueba->id)
            ->firstOrFail();

        $this->assertEqualsWithDelta(0.0018, (float) $carta->lcl, 1e-9);
        $this->assertEqualsWithDelta(0.0025, (float) $carta->lwl, 1e-9);
        $this->assertEqualsWithDelta(0.0039, (float) $carta->center, 1e-9);
        $this->assertEqualsWithDelta(0.0053, (float) $carta->uwl, 1e-9);
        $this->assertEqualsWithDelta(0.0059, (float) $carta->ucl, 1e-9);
    }

    /**
     * El de CONTROL siempre queda más lejos del centro que el de ADVERTENCIA.
     *
     * Es la invariante que obligó a mapear los cinco valores POR DISTANCIA y no
     * por el nombre de la columna del volcado: quince filas traen el control
     * afuera, pero Densidad Relativa lo trae al revés. Mapeada por nombre, esa
     * carta quedaría con los límites invertidos — o sea dando por buena una
     * corrida que está fuera de control.
     */
    public function test_el_limite_de_control_siempre_queda_por_fuera_del_de_advertencia(): void
    {
        foreach (QcChart::withoutGlobalScopes()->get() as $carta) {
            $centro = (float) $carta->center;

            $this->assertLessThanOrEqual(
                abs($centro - (float) $carta->lcl),
                abs($centro - (float) $carta->lwl),
                "En {$carta->label} el límite inferior de advertencia quedó más lejos que el de control.",
            );
            $this->assertLessThanOrEqual(
                abs((float) $carta->ucl - $centro),
                abs((float) $carta->uwl - $centro),
                "En {$carta->label} el límite superior de advertencia quedó más lejos que el de control.",
            );
        }
    }

    public function test_sembrar_dos_veces_no_duplica_ni_pisa_los_limites(): void
    {
        // El laboratorio cargó sus límites en una carta. Un `db:seed` posterior no
        // puede devolverla a derivada: dejaría de marcar los puntos fuera de
        // control y nadie se enteraría.
        $carta = QcChart::withoutGlobalScopes()->firstOrFail();
        $carta->update([
            'is_derived' => false,
            'center' => 0.15, 'sd' => 0.008,
            'lcl' => 0.126, 'lwl' => 0.134, 'uwl' => 0.166, 'ucl' => 0.174,
        ]);

        (new LabQcChartsSeeder())->run();

        $this->assertSame(16, QcChart::withoutGlobalScopes()->count());

        $carta->refresh();
        $this->assertFalse($carta->is_derived);
        $this->assertEquals(0.15, $carta->center);
    }
}
