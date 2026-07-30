<?php

namespace Tests\Feature\Lab;

use App\Models\Analyte;
use App\Models\Equipment;
use App\Models\SpecLimit;
use App\Models\SpecSet;
use App\Models\Standard;
use App\Services\Lab\SpecEvaluator;
use App\Services\Lab\SpecSetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Contra qué se compara un resultado, y qué se afirma cuando no hay contra qué.
 *
 * Cada prueba de acá corresponde a algo que el sistema anterior resolvía con un
 * `if/elsif` escrito a mano, duplicado en dos archivos y ya divergido.
 */
class SpecEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private SpecSetResolver $resolver;
    private SpecEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParentRows();
        $this->resolver = new SpecSetResolver();
        $this->evaluator = new SpecEvaluator();
    }

    // ─── La resolución del cuadro ────────────────────────────────────────

    public function test_gana_el_cuadro_mas_especifico(): void
    {
        // Entre "mineral" y "mineral en conmutador", para un conmutador gana el
        // segundo. Sin esta regla el ganador dependería del orden en que la base
        // devuelva las filas — impredecible, y en un informe eso no se admite.
        $this->makeSet('generico', ['oil_type_id' => 1]);
        $especifico = $this->makeSet('conmutador', ['oil_type_id' => 1, 'equipment_type_id' => 10]);

        $equipo = $this->makeEquipment(['oil_type_id' => 1, 'equipment_type_id' => 10]);

        $elegido = $this->resolver->resolve(group: 'fiqui', oilTypeId: 1, equipment: $equipo);

        $this->assertSame($especifico->id, $elegido->id);
    }

    public function test_el_cuadro_del_workspace_le_gana_al_global(): void
    {
        $this->makeSet('global', ['oil_type_id' => 1]);
        $propio = $this->makeSet('propio', ['oil_type_id' => 1, 'tenant_id' => 1]);

        $elegido = $this->resolver->resolve(
            group: 'fiqui', oilTypeId: 1,
            equipment: $this->makeEquipment(['oil_type_id' => 1]),
            tenantId: 1,
        );

        $this->assertSame($propio->id, $elegido->id);
    }

    public function test_el_rango_de_tension_incluye_su_tope(): void
    {
        // "hasta 69 kV" incluye los 69 kV, como está escrito en el sistema
        // anterior (`@num_ten <= 69`). Cambiarlo movería de cuadro a todos los
        // equipos que están justo en el corte.
        $bajo = $this->makeSet('hasta_69', ['oil_type_id' => 1, 'voltage_to' => 69]);
        $this->makeSet('sobre_69', ['oil_type_id' => 1, 'voltage_from' => 69.01, 'voltage_to' => 230]);

        $elegido = $this->resolver->resolve(
            group: 'fiqui', oilTypeId: 1,
            equipment: $this->makeEquipment(['oil_type_id' => 1, 'voltage_kv_hv' => 69]),
        );

        $this->assertSame($bajo->id, $elegido->id);
    }

    public function test_un_equipo_sin_tension_no_cae_por_descarte_en_un_cuadro_con_rango(): void
    {
        // Un cuadro que exige un dato que el equipo no tiene NO aplica. Meterlo
        // igual sería inventar que el transformador es de baja tensión porque
        // nadie cargó su tensión.
        $this->makeSet('hasta_69', ['oil_type_id' => 1, 'voltage_to' => 69]);

        $elegido = $this->resolver->resolve(
            group: 'fiqui', oilTypeId: 1,
            equipment: $this->makeEquipment(['oil_type_id' => 1, 'voltage_kv_hv' => null]),
        );

        $this->assertNull($elegido);
    }

    public function test_sin_cuadro_devuelve_nulo_y_no_un_cuadro_cualquiera(): void
    {
        // El aceite de girasol es justo el caso que el sistema anterior perdía:
        // uno de sus dos archivos lo contemplaba y el otro no.
        $this->makeSet('mineral', ['oil_type_id' => 1]);

        $elegido = $this->resolver->resolve(
            group: 'fiqui', oilTypeId: 6,
            equipment: $this->makeEquipment(['oil_type_id' => 6]),
        );

        $this->assertNull($elegido);
    }

    public function test_manda_la_norma_vigente_a_la_fecha_del_ensayo(): void
    {
        // Un ensayo de 2019 se evalúa con la norma que regía en 2019. Es lo que
        // permite adoptar una edición nueva sin reescribir la historia.
        $vieja = $this->makeSet('ed_2015', [
            'oil_type_id' => 1, 'effective_from' => '2015-01-01', 'effective_to' => '2020-03-31',
        ]);
        $nueva = $this->makeSet('ed_2020', [
            'oil_type_id' => 1, 'effective_from' => '2020-04-01',
        ]);

        $equipo = $this->makeEquipment(['oil_type_id' => 1]);

        $this->assertSame($vieja->id, $this->resolver->resolve(
            group: 'fiqui', oilTypeId: 1, equipment: $equipo, at: Carbon::parse('2019-06-01')
        )->id);

        $this->assertSame($nueva->id, $this->resolver->resolve(
            group: 'fiqui', oilTypeId: 1, equipment: $equipo, at: Carbon::parse('2026-06-01')
        )->id);
    }

    // ─── El veredicto ────────────────────────────────────────────────────

    public function test_un_maximo_dictamina_dentro_y_fuera(): void
    {
        $cuadro = $this->makeSet('mineral', ['oil_type_id' => 1]);
        $this->makeLimit($cuadro, 'acid', ['operator' => '<=', 'max_value' => 0.20]);

        $this->assertSame(SpecLimit::IN_SPEC, $this->verdict($cuadro, 'acid', 0.15));
        $this->assertSame(SpecLimit::IN_SPEC, $this->verdict($cuadro, 'acid', 0.20));
        $this->assertSame(SpecLimit::OUT_OF_SPEC, $this->verdict($cuadro, 'acid', 0.21));
    }

    public function test_un_minimo_dictamina_al_reves(): void
    {
        // La rigidez es "más es mejor": el límite es un piso, no un techo.
        $cuadro = $this->makeSet('mineral', ['oil_type_id' => 1]);
        $this->makeLimit($cuadro, 'rig', ['operator' => '>=', 'min_value' => 40.0]);

        $this->assertSame(SpecLimit::IN_SPEC, $this->verdict($cuadro, 'rig', 47.0));
        $this->assertSame(SpecLimit::OUT_OF_SPEC, $this->verdict($cuadro, 'rig', 33.0));
    }

    public function test_la_banda_de_aviso_avisa_antes_de_salir_de_norma(): void
    {
        // El sistema anterior pasaba de "cumple" a "no cumple" sin escalón. Un
        // laboratorio quiere ver el aceite ACERCÁNDOSE al límite.
        $cuadro = $this->makeSet('mineral', ['oil_type_id' => 1]);
        $this->makeLimit($cuadro, 'acid', [
            'operator' => '<=', 'max_value' => 0.20, 'warn_max' => 0.15,
        ]);

        $this->assertSame(SpecLimit::IN_SPEC, $this->verdict($cuadro, 'acid', 0.10));
        $this->assertSame(SpecLimit::NEAR_LIMIT, $this->verdict($cuadro, 'acid', 0.18));
        $this->assertSame(SpecLimit::OUT_OF_SPEC, $this->verdict($cuadro, 'acid', 0.25));
    }

    public function test_un_valor_censurado_no_se_juzga_como_el_numero_de_al_lado(): void
    {
        // ">75 kV" es "el ensayador llegó a su tope sin que el aceite rompiera".
        // Contra un MÍNIMO de 40 cumple con seguridad. Contra un MÁXIMO no se
        // puede afirmar nada, porque el valor real es mayor y no se sabe cuánto.
        //
        // El sistema anterior limpiaba el signo antes de convertir a número
        // (`.split.join.to_f`), así que ">75" y "75" quedaban iguales.
        $cuadro = $this->makeSet('mineral', ['oil_type_id' => 1]);
        $this->makeLimit($cuadro, 'rig', ['operator' => '>=', 'min_value' => 40.0]);
        $this->makeLimit($cuadro, 'pcb', ['operator' => '<=', 'max_value' => 2.0]);

        $this->assertSame(SpecLimit::IN_SPEC, $this->verdict($cuadro, 'rig', 75.0, qualifier: 'gt'));
        $this->assertNull($this->verdict($cuadro, 'pcb', 5.0, qualifier: 'gt'));

        // "<2 ppm" es por debajo del límite de detección: contra un MÁXIMO de 2
        // cumple; contra un mínimo no se puede afirmar nada.
        $this->assertSame(SpecLimit::IN_SPEC, $this->verdict($cuadro, 'pcb', 2.0, qualifier: 'lt'));
        $this->assertNull($this->verdict($cuadro, 'rig', 30.0, qualifier: 'lt'));
    }

    public function test_un_parametro_que_el_cuadro_no_declara_queda_sin_criterio(): void
    {
        // Y "sin criterio" NO es "cumple". Rellenar con un guion —que es lo que
        // hacía el sistema anterior— convierte una falta de criterio en una
        // aprobación tácita.
        $cuadro = $this->makeSet('mineral', ['oil_type_id' => 1]);
        $this->makeLimit($cuadro, 'acid', ['operator' => '<=', 'max_value' => 0.20]);

        $veredicto = $this->evaluator->verdictFor(
            $cuadro->fresh()->load('limits'),
            Analyte::withoutGlobalScopes()->where('code', 'wat')->value('id'),
            35.0,
        );

        $this->assertNull($veredicto['spec_status']);
        $this->assertNull($veredicto['spec_source']);
    }

    public function test_el_veredicto_guarda_los_limites_y_la_norma_que_aplico(): void
    {
        // Es lo que congela el dictamen: un ensayo de 2019 sigue diciendo contra
        // qué se lo comparó, aunque la norma haya cambiado después.
        $norma = Standard::create([
            'slug' => Str::random(22), 'code' => 'IEEE C57.106', 'edition' => '2015',
            'kind' => Standard::KIND_ACCEPTANCE,
        ]);
        $cuadro = $this->makeSet('mineral', ['oil_type_id' => 1, 'standard_id' => $norma->id]);
        $this->makeLimit($cuadro, 'acid', ['operator' => '<=', 'max_value' => 0.20]);

        $veredicto = $this->evaluator->verdictFor(
            $cuadro->fresh()->load('limits', 'standard'),
            Analyte::withoutGlobalScopes()->where('code', 'acid')->value('id'),
            0.15,
        );

        $this->assertSame(SpecLimit::IN_SPEC, $veredicto['spec_status']);
        $this->assertSame(0.20, $veredicto['spec_max']);
        $this->assertNull($veredicto['spec_min']);
        $this->assertSame('IEEE C57.106-2015', $veredicto['spec_source']);
    }

    public function test_un_resultado_cualitativo_se_compara_por_texto(): void
    {
        $cuadro = $this->makeSet('mineral', ['oil_type_id' => 1]);
        $this->makeLimit($cuadro, 'color', [
            'operator' => 'text', 'text_value' => 'Brillante y Claro',
        ]);

        $id = Analyte::withoutGlobalScopes()->where('code', 'color')->value('id');
        $set = $cuadro->fresh()->load('limits');

        $this->assertSame(
            SpecLimit::IN_SPEC,
            $this->evaluator->verdictFor($set, $id, null, 'brillante y claro')['spec_status']
        );
        $this->assertSame(
            SpecLimit::OUT_OF_SPEC,
            $this->evaluator->verdictFor($set, $id, null, 'Turbio')['spec_status']
        );
    }

    // ─── El TEXTO del valor de orientación ───────────────────────────────

    /**
     * El texto del límite es un DATO y viaja congelado con el resultado.
     *
     * El informe lo rearmaba desde `spec_min`/`spec_max`, y así perdía lo que el
     * laboratorio había escrito: `47.0 - mínimo` salía `47 - mínimo`, porque el
     * formateador recorta el cero. Ese cero está en el cuadro a propósito. Es el
     * mismo criterio que el veredicto: el papel dice contra qué se juzgó ESA
     * muestra, no lo que el cuadro diga hoy.
     */
    public function test_el_texto_del_limite_se_congela_tal_como_lo_escribio_el_laboratorio(): void
    {
        $cuadro = $this->makeSet('mineral', ['oil_type_id' => 1]);
        $this->makeLimit($cuadro, 'rig', [
            'operator' => '>=', 'min_value' => 47.0, 'display' => '47.0 - mínimo',
        ]);

        $veredicto = $this->evaluator->verdictFor(
            $cuadro->fresh()->load('limits'),
            Analyte::withoutGlobalScopes()->where('code', 'rig')->value('id'),
            50.0,
        );

        $this->assertSame('47.0 - mínimo', $veredicto['spec_display']);
    }

    /**
     * Un criterio CUALITATIVO también tiene texto, y es el único que tiene.
     *
     * La condición visual se juzga contra una frase: no hay mínimo ni máximo que
     * rearmar. Rearmando desde los números, esa fila imprimía una raya — o sea,
     * el papel decía que no había criterio cuando el cuadro sí lo tenía.
     */
    public function test_un_limite_de_texto_lleva_su_frase_y_no_una_raya(): void
    {
        $cuadro = $this->makeSet('mineral', ['oil_type_id' => 1]);
        $this->makeLimit($cuadro, 'color', [
            'operator' => 'text', 'text_value' => 'Brillante y Claro',
            'display'  => 'Brillante y Claro',
        ]);

        $veredicto = $this->evaluator->verdictFor(
            $cuadro->fresh()->load('limits'),
            Analyte::withoutGlobalScopes()->where('code', 'color')->value('id'),
            null,
            'brillante y claro',
        );

        $this->assertSame('Brillante y Claro', $veredicto['spec_display']);
        $this->assertNull($veredicto['spec_min']);
        $this->assertNull($veredicto['spec_max']);
    }

    /** Un límite sin texto escrito sigue imprimiendo algo legible. */
    public function test_sin_texto_escrito_el_limite_se_arma_desde_el_numero(): void
    {
        $cuadro = $this->makeSet('mineral', ['oil_type_id' => 1]);
        $this->makeLimit($cuadro, 'wat', ['operator' => '<=', 'max_value' => 25.0]);

        $veredicto = $this->evaluator->verdictFor(
            $cuadro->fresh()->load('limits'),
            Analyte::withoutGlobalScopes()->where('code', 'wat')->value('id'),
            10.0,
        );

        $this->assertSame('25 - máximo', $veredicto['spec_display']);
    }

    /** Sin cuadro no se inventa criterio: tampoco un texto de límite. */
    public function test_sin_cuadro_no_hay_texto_de_limite(): void
    {
        $veredicto = $this->evaluator->verdictFor(
            null,
            Analyte::withoutGlobalScopes()->where('code', 'acid')->value('id'),
            0.10,
        );

        $this->assertNull($veredicto['spec_display']);
        $this->assertNull($veredicto['spec_status']);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function verdict(SpecSet $cuadro, string $analyte, float $valor, ?string $qualifier = null): ?string
    {
        return $this->evaluator->verdictFor(
            $cuadro->fresh()->load('limits', 'standard'),
            Analyte::withoutGlobalScopes()->where('code', $analyte)->value('id'),
            $valor,
            null,
            $qualifier,
        )['spec_status'];
    }

    private function makeSet(string $code, array $overrides = []): SpecSet
    {
        return SpecSet::create(array_merge([
            'slug'  => Str::random(22),
            'code'  => $code,
            'label' => $code,
            'group' => 'fiqui',
        ], $overrides));
    }

    private function makeLimit(SpecSet $set, string $analyte, array $attributes): SpecLimit
    {
        return SpecLimit::create($attributes + [
            'spec_set_id' => $set->id,
            'analyte_id'  => Analyte::withoutGlobalScopes()->where('code', $analyte)->value('id'),
        ]);
    }

    private function makeEquipment(array $overrides = []): Equipment
    {
        return Equipment::create(array_merge([
            'slug' => Str::random(22), 'name' => 'Transformador ' . Str::random(4),
            'tenant_id' => 1,
        ], $overrides));
    }

    private function seedParentRows(): void
    {
        DB::table('tenants')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]]);

        foreach ([[1, 'Mineral'], [6, 'Éster natural (girasol)']] as [$id, $name]) {
            DB::table('oil_types')->insertOrIgnore([[
                'id' => $id, 'slug' => Str::random(22), 'name' => $name,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]]);
        }

        foreach ([[1, 'Potencia'], [10, 'Conmutador']] as [$id, $name]) {
            DB::table('equipment_types')->insertOrIgnore([[
                'id' => $id, 'slug' => Str::random(22), 'name' => $name,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]]);
        }

        foreach ([['acid', 'fiqui'], ['rig', 'fiqui'], ['wat', 'fiqui'], ['color', 'fiqui'], ['pcb', 'otros']] as [$code, $group]) {
            DB::table('analytes')->insertOrIgnore([[
                'slug' => Str::random(22), 'code' => $code, 'name' => $code,
                'group' => $group, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]]);
        }
    }
}
