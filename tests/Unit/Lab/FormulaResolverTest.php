<?php

namespace Tests\Unit\Lab;

use App\Services\Lab\FormulaResolver;
use PHPUnit\Framework\TestCase;

/**
 * Resolución de una hoja de trabajo completa. Lo que se prueba acá es lo que el
 * sistema viejo no podía garantizar: que el resultado NO dependa del orden en
 * que están las columnas en la pantalla.
 */
class FormulaResolverTest extends TestCase
{
    private FormulaResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new FormulaResolver();
    }

    /** Hoja real de Número Ácido: cuatro campos cargados y uno calculado. */
    public function test_resuelve_la_hoja_de_numero_acido(): void
    {
        $campos = [
            ['code' => 'factor_koh', 'formula' => null],
            ['code' => 'vol_blanco', 'formula' => null],
            ['code' => 'peso_aceite_g', 'formula' => null],
            ['code' => 'volumen_gastado_ml', 'formula' => null],
            ['code' => 'acidez', 'formula' => '(volumen_gastado_ml - vol_blanco) * factor_koh / peso_aceite_g', 'decimals' => 3],
        ];

        $valores = $this->resolver->resolveAll($campos, [
            'factor_koh' => '0.561',
            'vol_blanco' => '0.10',
            'peso_aceite_g' => '20.0',
            'volumen_gastado_ml' => '1.20',
        ]);

        $this->assertSame(0.031, $valores['acidez']);
        // Los campos cargados a mano quedan normalizados a número.
        $this->assertSame(1.20, $valores['volumen_gastado_ml']);
    }

    /**
     * ORDEN TOPOLÓGICO: 'dp' depende de 'promedio', que también es calculado, y
     * 'clasificacion' depende de 'dp'. La cadena está declarada al REVÉS a
     * propósito — el orden de las columnas no debe influir.
     */
    public function test_resuelve_calculados_que_dependen_de_calculados(): void
    {
        $campos = [
            ['code' => 'vida_restante', 'formula' => 'round(dp - 200, 0)'],
            ['code' => 'dp', 'formula' => '(1.51 - log10(fal_promedio / 1000)) / 0.0035'],
            ['code' => 'fal_promedio', 'formula' => '(fal_1 + fal_2) / 2'],
            ['code' => 'fal_1', 'formula' => null],
            ['code' => 'fal_2', 'formula' => null],
        ];

        $valores = $this->resolver->resolveAll($campos, ['fal_1' => 900, 'fal_2' => 1100]);

        $this->assertEqualsWithDelta(1000.0, $valores['fal_promedio'], 1e-9);
        $this->assertEqualsWithDelta(431.4285714, $valores['dp'], 1e-6);
        $this->assertSame(231.0, $valores['vida_restante']);
    }

    /** El mismo conjunto de campos, en cualquier orden, da el mismo resultado. */
    public function test_el_orden_de_declaracion_no_cambia_el_resultado(): void
    {
        $campos = [
            ['code' => 'a', 'formula' => null],
            ['code' => 'b', 'formula' => 'a * 2'],
            ['code' => 'c', 'formula' => 'b + a'],
            ['code' => 'd', 'formula' => 'c * 10'],
        ];

        $esperado = ['a' => 5.0, 'b' => 10.0, 'c' => 15.0, 'd' => 150.0];

        foreach ([$campos, array_reverse($campos)] as $orden) {
            $valores = $this->resolver->resolveAll($orden, ['a' => 5]);
            foreach ($esperado as $code => $valor) {
                $this->assertEqualsWithDelta($valor, $valores[$code], 1e-9, 'Campo '.$code);
            }
        }
    }

    /** Falta un dato de entrada: la cadena entera queda en null, sin romperse. */
    public function test_dato_faltante_deja_la_cadena_en_null(): void
    {
        $valores = $this->resolver->resolveAll([
            ['code' => 'fal_1', 'formula' => null],
            ['code' => 'fal_2', 'formula' => null],
            ['code' => 'fal_promedio', 'formula' => '(fal_1 + fal_2) / 2'],
            ['code' => 'dp', 'formula' => '(1.51 - log10(fal_promedio / 1000)) / 0.0035'],
        ], ['fal_1' => 900]);

        $this->assertNull($valores['fal_promedio']);
        $this->assertNull($valores['dp']);
        $this->assertSame(900.0, $valores['fal_1']);
    }

    /** Un ciclo deja SOLO esos campos en null y se reporta; el resto se calcula. */
    public function test_ciclo_deja_los_campos_en_null_y_lo_reporta(): void
    {
        $r = $this->resolver->resolveWithDiagnostics([
            ['code' => 'lectura', 'formula' => null],
            ['code' => 'a', 'formula' => 'b + 1'],
            ['code' => 'b', 'formula' => 'a + 1'],
            ['code' => 'sano', 'formula' => 'lectura * 2'],
        ], ['lectura' => 4]);

        $this->assertNull($r['values']['a']);
        $this->assertNull($r['values']['b']);
        $this->assertEqualsWithDelta(8.0, $r['values']['sano'], 1e-9);
        $this->assertSame(['a', 'b', 'a'], $r['cycles'][0]);
        $this->assertContains('a', $r['unresolved']);
        $this->assertContains('b', $r['unresolved']);
        $this->assertNotContains('sano', $r['unresolved']);
    }

    /** Una fórmula rota no tumba la hoja: solo ese campo queda sin valor. */
    public function test_formula_rota_solo_afecta_su_campo(): void
    {
        $r = $this->resolver->resolveWithDiagnostics([
            ['code' => 'x', 'formula' => null],
            ['code' => 'roto', 'formula' => 'system("ls")'],
            ['code' => 'bueno', 'formula' => 'x + 1'],
        ], ['x' => 2]);

        $this->assertNull($r['values']['roto']);
        $this->assertEqualsWithDelta(3.0, $r['values']['bueno'], 1e-9);
        $this->assertArrayHasKey('roto', $r['errors']);
        $this->assertContains('roto', $r['unresolved']);
    }

    /**
     * El valor guardado de un campo calculado NO se respeta: su única fuente es
     * la fórmula. Si no, un resultado viejo sobreviviría a la corrección de la
     * medición que lo originó.
     */
    public function test_el_calculado_ignora_el_valor_crudo_guardado(): void
    {
        $valores = $this->resolver->resolveAll([
            ['code' => 'a', 'formula' => null],
            ['code' => 'total', 'formula' => 'a * 2'],
        ], ['a' => 3, 'total' => 999]);

        $this->assertEqualsWithDelta(6.0, $valores['total'], 1e-9);
    }

    /** Cromatografía: 9 gases cargados y dos totales calculados. */
    public function test_resuelve_la_hoja_de_cromatografia(): void
    {
        $gases = ['h2' => 120, 'ch4' => 40, 'c2h6' => 25.5, 'c2h4' => 18, 'c2h2' => 2.5, 'co' => 350, 'co2' => 2400];

        $campos = [];
        foreach (array_keys($gases) as $code) {
            $campos[] = ['code' => $code, 'formula' => null];
        }
        $campos[] = ['code' => 'tdcg', 'formula' => 'sum(h2, ch4, c2h6, c2h4, c2h2, co)', 'decimals' => 1];
        $campos[] = ['code' => 'total_gases', 'formula' => 'tdcg + co2', 'decimals' => 1];

        $valores = $this->resolver->resolveAll($campos, $gases);

        $this->assertSame(556.0, $valores['tdcg']);
        $this->assertSame(2956.0, $valores['total_gases']);
    }

    /** Atajo código => fórmula, para probar una plantilla suelta. */
    public function test_acepta_el_mapa_codigo_formula(): void
    {
        $valores = $this->resolver->resolveAll(
            ['promedio' => '(lectura_1 + lectura_2) / 2', 'repetibilidad' => 'abs(lectura_1 - lectura_2)'],
            ['lectura_1' => 12.4, 'lectura_2' => 13.0]
        );

        $this->assertEqualsWithDelta(12.7, $valores['promedio'], 1e-9);
        $this->assertEqualsWithDelta(0.6, $valores['repetibilidad'], 1e-9);
    }

    /** Sin 'decimals' no se redondea: el motor no inventa precisión. */
    public function test_sin_decimales_declarados_no_redondea(): void
    {
        $valores = $this->resolver->resolveAll(
            [['code' => 'acidez', 'formula' => '(v - b) * f / p']],
            ['v' => 1.20, 'b' => 0.10, 'f' => 0.561, 'p' => 20.0]
        );

        $this->assertEqualsWithDelta(0.030855, $valores['acidez'], 1e-9);
    }

    /** Objetos (modelos Eloquent) también sirven como entrada. */
    public function test_acepta_objetos_como_campos(): void
    {
        $campo = new \stdClass();
        $campo->code = 'total';
        $campo->formula = 'a + b';
        $campo->decimals = 2;

        $valores = $this->resolver->resolveAll([$campo], ['a' => 1.006, 'b' => 2]);

        $this->assertSame(3.01, $valores['total']);
    }
}
