<?php

namespace Tests\Unit\Lab;

use App\Models\QcChart;
use App\Models\QcPoint;
use App\Models\TestFieldOption;
use App\Models\WorksheetValue;
use Tests\TestCase;

/**
 * Los límites de la carta de control y la lectura del valor de bancada, sin
 * tocar la base: son cuentas del modelo, y probarlas contra tablas montadas
 * escondería un error de cálculo detrás de un error de esquema. Los modelos se
 * instancian sin guardar; la aplicación se levanta solo porque el casteo de
 * fechas de Eloquent pide el formato a la conexión.
 */
class QcChartLimitsTest extends TestCase
{
    private function chart(array $attributes = []): QcChart
    {
        return new QcChart(array_merge([
            'center'       => 10.0,
            'sd'           => 0.5,
            'is_derived'   => true,
            'warn_sigma'   => 2,
            'action_sigma' => 3,
        ], $attributes));
    }

    public function test_los_limites_derivados_salen_de_la_media_y_el_desvio(): void
    {
        $limits = $this->chart()->limits();

        $this->assertSame(8.5, $limits['lci']);
        $this->assertSame(9.0, $limits['lai']);
        $this->assertSame(10.0, $limits['lc']);
        $this->assertSame(11.0, $limits['las']);
        $this->assertSame(11.5, $limits['lcs']);
    }

    /** Los múltiplos son configurables: no todo parámetro usa 2 y 3 desvíos. */
    public function test_los_multiplos_de_sigma_son_configurables(): void
    {
        $limits = $this->chart(['warn_sigma' => 1.5, 'action_sigma' => 2.5])->limits();

        $this->assertSame(9.25, $limits['lai']);
        $this->assertSame(8.75, $limits['lci']);
    }

    /**
     * Sin desvío no hay carta. Una carta sin límites se ve y se corrige; una con
     * límites inventados sobre un desvío ausente pasa por buena.
     */
    public function test_sin_desvio_no_se_derivan_limites(): void
    {
        $chart = $this->chart(['sd' => null]);

        $this->assertSame([], $chart->derive());
        $this->assertNull($chart->limits()['lcs']);
    }

    /** Sin derivar manda lo que el laboratorio cargó a mano. */
    public function test_los_limites_fijados_a_mano_no_se_recalculan(): void
    {
        $chart = $this->chart(['is_derived' => false, 'ucl' => 20.0, 'lcl' => 1.0]);

        $this->assertSame(20.0, $chart->limits()['lcs']);
        $this->assertSame(1.0, $chart->limits()['lci']);
    }

    public function test_classify_distingue_alerta_de_fuera_de_control(): void
    {
        $chart = $this->chart();

        $this->assertSame(QcPoint::FLAG_OK, $chart->classify(10.2)['flag']);
        $this->assertSame(QcPoint::FLAG_WARN, $chart->classify(11.2)['flag']);
        $this->assertSame(QcPoint::FLAG_OUT, $chart->classify(12.0)['flag']);
        $this->assertSame(QcPoint::FLAG_OUT, $chart->classify(8.0)['flag']);
        $this->assertEqualsWithDelta(4.0, $chart->classify(12.0)['z'], 1e-9);
    }

    public function test_la_vigencia_cierra_por_los_dos_extremos(): void
    {
        $chart = $this->chart(['effective_from' => '2026-01-01', 'effective_to' => '2026-06-30']);

        $this->assertTrue($chart->estabaVigenteAl('2026-03-01'));
        $this->assertTrue($chart->estabaVigenteAl('2026-06-30'));
        $this->assertFalse($chart->estabaVigenteAl('2025-12-31'));
        $this->assertFalse($chart->estabaVigenteAl('2026-07-01'));
    }

    /** Sin criterio cargado no se dictamina: el nulo no es "cumple". */
    public function test_sin_criterio_de_repetibilidad_no_se_afirma_nada(): void
    {
        $this->assertNull($this->chart()->repeatabilityWithinLimit(10.0, 10.9));
        $this->assertTrue($this->chart(['repeatability_limit' => 0.4])->repeatabilityWithinLimit(10.0, 10.3));
        $this->assertFalse($this->chart(['repeatability_limit' => 0.4])->repeatabilityWithinLimit(10.0, 10.9));
    }

    /**
     * El valor tipado gana al texto, y la opción se lee por su TEXTO. En el
     * sistema viejo la celda guardaba el id de la opción como cadena, así que
     * renombrar la lista cambiaba lo que decía un ensayo ya cerrado.
     */
    public function test_resolved_devuelve_el_valor_que_corresponde(): void
    {
        $numeric = new WorksheetValue(['value_num' => 1.25, 'value_text' => 'ignorado']);
        $this->assertSame(1.25, $numeric->resolved);

        $text = new WorksheetValue(['value_text' => 'muestra turbia']);
        $this->assertSame('muestra turbia', $text->resolved);

        $select = new WorksheetValue(['option_id' => 7]);
        $select->setRelation('option', new TestFieldOption(['value' => 'ASTM D877']));
        $this->assertSame('ASTM D877', $select->resolved);

        $this->assertTrue((new WorksheetValue())->isEmpty());
        $this->assertFalse((new WorksheetValue(['value_num' => 0]))->isEmpty());
    }
}
