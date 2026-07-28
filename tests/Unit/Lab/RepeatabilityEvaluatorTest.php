<?php

namespace Tests\Unit\Lab;

use App\Services\Lab\RepeatabilityEvaluator;
use PHPUnit\Framework\TestCase;

class RepeatabilityEvaluatorTest extends TestCase
{
    private RepeatabilityEvaluator $ev;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ev = new RepeatabilityEvaluator();
    }

    public function test_criterio_absoluto(): void
    {
        $r = $this->ev->compare(0.031, 0.034, 0.005);

        $this->assertEqualsWithDelta(0.003, $r['difference'], 1e-9);
        $this->assertTrue($r['within']);
    }

    public function test_criterio_absoluto_fuera_de_limite(): void
    {
        $r = $this->ev->compare(0.031, 0.045, 0.005);

        $this->assertFalse($r['within']);
    }

    public function test_criterio_relativo_toma_el_promedio_de_las_dos_lecturas(): void
    {
        // 10 y 12: diferencia 2 sobre promedio 11 = 18.18%.
        $r = $this->ev->compare(10.0, 12.0, 20.0, RepeatabilityEvaluator::MODE_RELATIVE);

        $this->assertEqualsWithDelta(18.181818, $r['relative'], 1e-5);
        $this->assertTrue($r['within']);
    }

    public function test_el_orden_de_las_lecturas_no_cambia_el_resultado(): void
    {
        // Es la razón por la que el porcentaje se toma sobre el promedio y no
        // sobre la primera lectura: ninguna de las dos es la referencia.
        $ab = $this->ev->compare(10.0, 12.0, 20.0, RepeatabilityEvaluator::MODE_RELATIVE);
        $ba = $this->ev->compare(12.0, 10.0, 20.0, RepeatabilityEvaluator::MODE_RELATIVE);

        $this->assertSame($ab, $ba);
    }

    public function test_la_misma_diferencia_pesa_distinto_segun_la_magnitud(): void
    {
        // 2 ppm sobre un gas que da 5 es enorme; sobre uno que da 4000 no es nada.
        $bajo = $this->ev->compare(4.0, 6.0, 10.0, RepeatabilityEvaluator::MODE_RELATIVE);
        $alto = $this->ev->compare(3999.0, 4001.0, 10.0, RepeatabilityEvaluator::MODE_RELATIVE);

        $this->assertFalse($bajo['within']);
        $this->assertTrue($alto['within']);
    }

    public function test_dos_ceros_repiten_perfecto(): void
    {
        // Gas no detectado en las dos lecturas. Devolver nulo se leería como
        // "no evaluado" cuando en realidad el ensayo repitió exacto.
        $r = $this->ev->compare(0.0, 0.0, 5.0, RepeatabilityEvaluator::MODE_RELATIVE);

        $this->assertSame(0.0, $r['relative']);
        $this->assertTrue($r['within']);
    }

    public function test_sin_criterio_cargado_calcula_pero_no_dictamina(): void
    {
        $r = $this->ev->compare(10.0, 12.0, null);

        $this->assertEqualsWithDelta(2.0, $r['difference'], 1e-9);
        $this->assertNull($r['within']);
    }

    public function test_falta_una_lectura(): void
    {
        $r = $this->ev->compare(10.0, null, 1.0);

        $this->assertNull($r['difference']);
        $this->assertNull($r['within']);
    }
}
