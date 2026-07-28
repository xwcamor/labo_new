<?php

namespace Tests\Unit\Lab;

use App\Services\Lab\WestgardEvaluator;
use PHPUnit\Framework\TestCase;

/**
 * Las reglas se prueban con series armadas a mano alrededor de una media de
 * 100 con desvío 2, para que el z de cada punto se lea de un vistazo:
 * 102 = +1s, 104 = +2s, 106 = +3s.
 */
class WestgardEvaluatorTest extends TestCase
{
    private WestgardEvaluator $ev;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ev = new WestgardEvaluator();
    }

    /** @return array<int,string|null> */
    private function rules(array $values): array
    {
        return array_map(
            fn ($r) => $r['rule'],
            $this->ev->evaluate($values, 100.0, 2.0)
        );
    }

    public function test_serie_dentro_de_control_no_dispara_nada(): void
    {
        $rules = $this->rules([100, 101, 99, 100.5, 99.5, 101]);

        $this->assertSame([null, null, null, null, null, null], $rules);
    }

    public function test_un_punto_fuera_de_tres_desvios_rechaza(): void
    {
        $rules = $this->rules([100, 99, 107]);   // 107 = +3.5s

        $this->assertSame('1_3s', $rules[2]);
    }

    public function test_dos_puntos_seguidos_fuera_de_dos_desvios_del_mismo_lado(): void
    {
        $rules = $this->rules([100, 105, 105.5]);   // +2.5s y +2.75s

        $this->assertNull($rules[1], 'un punto solo fuera de 2s todavía no rechaza');
        $this->assertSame('2_2s', $rules[2]);
    }

    public function test_dos_puntos_fuera_de_dos_desvios_en_lados_opuestos_no_es_2_2s(): void
    {
        // +2.5s y −2.5s: no es sesgo, es dispersión. Y como están separados por
        // 5 desvíos, la que corresponde es R_4s.
        $rules = $this->rules([100, 105, 95]);

        $this->assertSame('R_4s', $rules[2]);
    }

    public function test_r_4s_necesita_mas_de_cuatro_desvios_de_separacion(): void
    {
        // +1.5s y −1.5s: 3 desvíos de separación, no alcanza.
        $rules = $this->rules([100, 103, 97]);

        $this->assertNull($rules[2]);
    }

    public function test_cuatro_puntos_seguidos_fuera_de_un_desvio_avisan(): void
    {
        $rules = $this->rules([100, 102.5, 103, 102.4, 103.5]);

        $this->assertNull($rules[3], 'con tres seguidos todavía no');
        $this->assertSame('4_1s', $rules[4]);
    }

    public function test_diez_puntos_del_mismo_lado_avisan_aunque_ninguno_se_salga(): void
    {
        // Todos apenas por encima de la media: ningún punto se sale de ningún
        // límite y sin embargo el patrón se corrió. Es exactamente el caso que
        // el gráfico del sistema viejo no permitía ver.
        $values = array_fill(0, 10, 100.4);
        $rules = $this->rules($values);

        $this->assertNull($rules[8]);
        $this->assertSame('10x', $rules[9]);
    }

    public function test_un_hueco_corta_la_racha(): void
    {
        // Nueve puntos altos, una corrida sin dato, y uno más. No son diez
        // consecutivos: encadenarlos inventaría un sesgo que no está.
        $values = array_merge(array_fill(0, 9, 100.4), [null], [100.4]);
        $rules = $this->rules($values);

        $this->assertNotContains('10x', $rules);
    }

    public function test_la_regla_mas_grave_gana(): void
    {
        // El último punto viola 1_3s y además cierra una racha de 4_1s.
        // Se reporta el rechazo, no el aviso.
        $rules = $this->rules([102.5, 103, 102.4, 107]);

        $this->assertSame('1_3s', $rules[3]);
    }

    public function test_desvio_cero_no_produce_carta(): void
    {
        // Sin dispersión no hay z posible. Devolver 'out' en todo sería peor
        // que no dictaminar: llenaría la carta de rechazos falsos.
        $result = $this->ev->evaluate([100, 140, 60], 100.0, 0.0);

        foreach ($result as $point) {
            $this->assertSame('ok', $point['flag']);
            $this->assertNull($point['z']);
            $this->assertNull($point['rule']);
        }
    }

    public function test_los_puntos_sin_dato_no_se_evaluan(): void
    {
        $result = $this->ev->evaluate([100, null, 107], 100.0, 2.0);

        $this->assertNull($result[1]['z']);
        $this->assertSame('ok', $result[1]['flag']);
        $this->assertSame('1_3s', $result[2]['rule']);
    }

    public function test_el_flag_sale_del_reparto_declarado_en_la_clase(): void
    {
        $rechazo = $this->ev->evaluate([100, 99, 107], 100.0, 2.0);
        $aviso   = $this->ev->evaluate(array_fill(0, 10, 100.4), 100.0, 2.0);

        $this->assertSame('out',  $rechazo[2]['flag']);
        $this->assertSame('warn', $aviso[9]['flag']);
    }
}
