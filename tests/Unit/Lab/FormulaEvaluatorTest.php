<?php

namespace Tests\Unit\Lab;

use App\Services\Lab\FormulaEvaluator;
use PHPUnit\Framework\TestCase;

/**
 * Fórmulas REALES del laboratorio, con números reales. Las cuatro que se
 * portaron del sistema Rails viejo (Número Ácido, Chendong, promedio y
 * repetibilidad de agua, suma de gases combustibles) más el contrato de
 * "sin dato = null, nunca excepción".
 */
class FormulaEvaluatorTest extends TestCase
{
    private FormulaEvaluator $eval;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eval = new FormulaEvaluator();
    }

    /**
     * Número Ácido, ASTM D974: (V - Vblanco) * factor KOH / peso de muestra.
     * En el viejo era JavaScript sobre document.getElementById('col5').
     * 1.20 mL gastados, 0.10 de blanco, factor 0.561 (0.01 N x 56.1), 20 g.
     */
    public function test_numero_acido_con_valores_reales(): void
    {
        $formula = '(volumen_gastado_ml - vol_blanco) * factor_koh / peso_aceite_g';

        $result = $this->eval->evaluate($formula, [
            'volumen_gastado_ml' => 1.20,
            'vol_blanco' => 0.10,
            'factor_koh' => 0.561,
            'peso_aceite_g' => 20.0,
        ]);

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(0.030855, $result, 1e-9);
        // El valor que se informa lleva 3 decimales, igual que el toFixed(3) viejo.
        $this->assertSame(0.031, round($result, 3));
    }

    /** Los valores llegan de un formulario: son cadenas, no floats. */
    public function test_numero_acido_acepta_valores_como_texto(): void
    {
        $result = $this->eval->evaluate(
            '(volumen_gastado_ml - vol_blanco) * factor_koh / peso_aceite_g',
            ['volumen_gastado_ml' => '1.20', 'vol_blanco' => ' 0.10 ', 'factor_koh' => '0.561', 'peso_aceite_g' => '20']
        );

        $this->assertEqualsWithDelta(0.030855, $result, 1e-9);
    }

    /**
     * Grado de polimerización por furanos, Chendong:
     * DP = (1.51 - log10(2FAL ppb / 1000)) / 0.0035
     */
    public function test_chendong_grado_de_polimerizacion(): void
    {
        $formula = '(1.51 - log10(fal_ppb / 1000)) / 0.0035';

        // 1000 ppb -> log10(1) = 0 -> 1.51 / 0.0035
        $this->assertEqualsWithDelta(431.4285714, $this->eval->evaluate($formula, ['fal_ppb' => 1000]), 1e-6);
        // 100 ppb -> log10(0.1) = -1 -> 2.51 / 0.0035
        $this->assertEqualsWithDelta(717.1428571, $this->eval->evaluate($formula, ['fal_ppb' => 100]), 1e-6);
        // 5000 ppb (papel muy degradado): log10(5) = 0.69897 -> 0.81103 / 0.0035
        $this->assertEqualsWithDelta(231.7228559, $this->eval->evaluate($formula, ['fal_ppb' => 5000]), 1e-6);
    }

    /**
     * "No se detectó furano" no es DP 0: log10(0) está fuera de dominio y el
     * campo se queda sin resultado en vez de imprimir un infinito.
     */
    public function test_chendong_con_fal_en_cero_no_devuelve_infinito(): void
    {
        $result = $this->eval->evaluate('(1.51 - log10(fal_ppb / 1000)) / 0.0035', ['fal_ppb' => 0]);

        $this->assertNull($result);
    }

    /** Agua (Karl Fischer): promedio de las dos lecturas. */
    public function test_agua_promedio_de_dos_lecturas(): void
    {
        $ctx = ['lectura_1' => 12.4, 'lectura_2' => 13.0];

        $this->assertEqualsWithDelta(12.7, $this->eval->evaluate('(lectura_1 + lectura_2) / 2', $ctx), 1e-9);
        // La misma cuenta con avg(), que es la forma que se ofrece en el editor.
        $this->assertEqualsWithDelta(12.7, $this->eval->evaluate('avg(lectura_1, lectura_2)', $ctx), 1e-9);
    }

    /** Agua: repetibilidad = diferencia absoluta entre lecturas. */
    public function test_agua_repetibilidad(): void
    {
        $this->assertEqualsWithDelta(
            0.6,
            $this->eval->evaluate('abs(lectura_1 - lectura_2)', ['lectura_1' => 12.4, 'lectura_2' => 13.0]),
            1e-9
        );
        // El orden de las lecturas no cambia el resultado.
        $this->assertEqualsWithDelta(
            0.6,
            $this->eval->evaluate('abs(lectura_1 - lectura_2)', ['lectura_1' => 13.0, 'lectura_2' => 12.4]),
            1e-9
        );
    }

    /** Cromatografía: total de gases combustibles (TDCG sin CO2). */
    public function test_cromatografia_total_de_gases_combustibles(): void
    {
        $ctx = ['h2' => 120, 'ch4' => 40, 'c2h6' => 25.5, 'c2h4' => 18, 'c2h2' => 2.5, 'co' => 350];

        $this->assertEqualsWithDelta(
            556.0,
            $this->eval->evaluate('sum(h2, ch4, c2h6, c2h4, c2h2, co)', $ctx),
            1e-9
        );
        $this->assertEqualsWithDelta(
            556.0,
            $this->eval->evaluate('h2 + ch4 + c2h6 + c2h4 + c2h2 + co', $ctx),
            1e-9
        );
    }

    /**
     * Un ensayo a medio cargar es el estado NORMAL de la pantalla del analista:
     * tiene que devolver null, no romper la hoja.
     */
    public function test_valor_faltante_devuelve_null_sin_excepcion(): void
    {
        $formula = '(volumen_gastado_ml - vol_blanco) * factor_koh / peso_aceite_g';

        $this->assertNull($this->eval->evaluate($formula, ['volumen_gastado_ml' => 1.2]));
        $this->assertNull($this->eval->evaluate($formula, []));
        $this->assertNull($this->eval->evaluate($formula, [
            'volumen_gastado_ml' => 1.2, 'vol_blanco' => null, 'factor_koh' => 0.561, 'peso_aceite_g' => 20,
        ]));
        // Cadena vacía y texto tampoco son mediciones.
        $this->assertNull($this->eval->evaluate('a + b', ['a' => '', 'b' => 2]));
        $this->assertNull($this->eval->evaluate('a + b', ['a' => 'n/d', 'b' => 2]));
        $this->assertNull($this->eval->evaluate('a + b', ['a' => true, 'b' => 2]));
    }

    /** Un dato faltante contamina toda la expresión, no solo su rama. */
    public function test_el_faltante_contamina_las_funciones(): void
    {
        $this->assertNull($this->eval->evaluate('sum(h2, ch4, c2h2)', ['h2' => 10, 'ch4' => 5]));
        $this->assertNull($this->eval->evaluate('min(a, b)', ['a' => 1]));
    }

    public function test_division_por_cero_devuelve_null(): void
    {
        $this->assertNull($this->eval->evaluate('a / b', ['a' => 10, 'b' => 0]));
        $this->assertNull($this->eval->evaluate('a / b', ['a' => 10, 'b' => '0.0']));
        // El peso de muestra en 0 es el caso real: la balanza todavía no se cargó.
        $this->assertNull($this->eval->evaluate(
            '(volumen_gastado_ml - vol_blanco) * factor_koh / peso_aceite_g',
            ['volumen_gastado_ml' => 1.2, 'vol_blanco' => 0.1, 'factor_koh' => 0.561, 'peso_aceite_g' => 0]
        ));
    }

    public function test_precedencia_parentesis_y_unario(): void
    {
        $this->assertEqualsWithDelta(7.0, $this->eval->evaluate('1 + 2 * 3', []), 1e-9);
        $this->assertEqualsWithDelta(9.0, $this->eval->evaluate('(1 + 2) * 3', []), 1e-9);
        $this->assertEqualsWithDelta(-5.0, $this->eval->evaluate('-5', []), 1e-9);
        $this->assertEqualsWithDelta(-6.0, $this->eval->evaluate('2 * -3', []), 1e-9);
        $this->assertEqualsWithDelta(5.0, $this->eval->evaluate('- -5', []), 1e-9);
        $this->assertEqualsWithDelta(2.0, $this->eval->evaluate('8 / 2 / 2', []), 1e-9);
        $this->assertEqualsWithDelta(1.0, $this->eval->evaluate('10 - 5 - 4', []), 1e-9);
    }

    public function test_funciones_soportadas(): void
    {
        $this->assertEqualsWithDelta(3.0, $this->eval->evaluate('abs(0 - 3)', []), 1e-9);
        $this->assertEqualsWithDelta(2.35, $this->eval->evaluate('round(2.3456, 2)', []), 1e-9);
        $this->assertEqualsWithDelta(2.0, $this->eval->evaluate('round(2.3456)', []), 1e-9);
        $this->assertEqualsWithDelta(1.0, $this->eval->evaluate('min(3, 1, 2)', []), 1e-9);
        $this->assertEqualsWithDelta(3.0, $this->eval->evaluate('max(3, 1, 2)', []), 1e-9);
        $this->assertEqualsWithDelta(4.0, $this->eval->evaluate('sqrt(16)', []), 1e-9);
        $this->assertEqualsWithDelta(2.0, $this->eval->evaluate('log10(100)', []), 1e-9);
        $this->assertEqualsWithDelta(1.0, $this->eval->evaluate('ln(exp(1))', []), 1e-9);
        $this->assertEqualsWithDelta(8.0, $this->eval->evaluate('pow(2, 3)', []), 1e-9);
        $this->assertEqualsWithDelta(6.0, $this->eval->evaluate('sum(1, 2, 3)', []), 1e-9);
        $this->assertEqualsWithDelta(2.0, $this->eval->evaluate('avg(1, 2, 3)', []), 1e-9);
        // Anidadas
        $this->assertEqualsWithDelta(5.0, $this->eval->evaluate('max(min(5, 9), abs(0 - 2))', []), 1e-9);
    }

    /** Fuera de dominio: sin resultado, sin NAN ni INF que se cuelen al informe. */
    public function test_dominios_invalidos_devuelven_null(): void
    {
        $this->assertNull($this->eval->evaluate('sqrt(0 - 4)', []));
        $this->assertNull($this->eval->evaluate('log10(0)', []));
        $this->assertNull($this->eval->evaluate('ln(0)', []));
        $this->assertNull($this->eval->evaluate('exp(100000)', []));
        $this->assertNull($this->eval->evaluate('pow(0, 0 - 1)', []));
    }

    /**
     * SEGURIDAD: ninguna de estas entradas puede ejecutar nada ni lanzar; el
     * evaluador las trata como fórmulas ilegibles y devuelve null.
     */
    public function test_intentos_de_inyeccion_devuelven_null(): void
    {
        $ataques = [
            'system("ls")',
            'system(x)',
            'exec("rm -rf /")',
            'shell_exec(x)',
            'phpinfo()',
            '1;DROP TABLE test_fields',
            '${x}',
            '{$x}',
            '`ls`',
            'a" OR "1"="1',
            "eval('1+1')",
            'file_get_contents(x)',
            '<?php echo 1; ?>',
            'a[0]',
            'a && b',
            'a | b',
            'a @ b',
            'x = 1',
            'require(x)',
            'a\\b',
            "a\x00b",
            // La fórmula literal del sistema viejo: JavaScript sobre el DOM.
            "var col5 = parseFloat(document.getElementById('col5').value); var result = col5;",
        ];

        foreach ($ataques as $ataque) {
            $this->assertNull(
                $this->eval->evaluate($ataque, ['a' => 1, 'b' => 2, 'x' => 3]),
                'No devolvió null para: '.$ataque
            );
        }
    }

    /** Entradas patológicas: se cortan por largo o por anidación, sin desbordar. */
    public function test_entradas_patologicas_se_rechazan(): void
    {
        $larga = implode(' + ', array_fill(0, 400, '1'));
        $this->assertNull($this->eval->evaluate($larga, []));

        $profunda = str_repeat('(', 60).'1'.str_repeat(')', 60);
        $this->assertNull($this->eval->evaluate($profunda, []));

        $this->assertNull($this->eval->evaluate(str_repeat('a', 100).' + 1', ['a' => 1]));
    }

    public function test_sintaxis_rota_devuelve_null(): void
    {
        foreach (['(1 + 2', '1 + 2)', '1 +', '+', '', '   ', '1 2', 'abs()', 'pow(2)', 'a b', '1 , 2', '.5 + 1'] as $rota) {
            $this->assertNull($this->eval->evaluate($rota, ['a' => 1, 'b' => 2]), 'No devolvió null para: '.$rota);
        }
    }
}
