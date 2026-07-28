<?php

namespace Tests\Unit\Lab;

use App\Services\Lab\FormulaValidator;
use PHPUnit\Framework\TestCase;

/**
 * Lo que el editor de plantillas de ensayo usa para no dejar guardar una
 * fórmula rota. Al contrario del evaluador, acá SÍ hay que ver el error.
 */
class FormulaValidatorTest extends TestCase
{
    private FormulaValidator $validator;

    /** Campos reales de la hoja de Número Ácido. */
    private array $campos = ['volumen_gastado_ml', 'vol_blanco', 'factor_koh', 'peso_aceite_g', 'resultado'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FormulaValidator();
    }

    public function test_acepta_las_formulas_reales_y_lista_los_campos_usados(): void
    {
        $r = $this->validator->validate(
            '(volumen_gastado_ml - vol_blanco) * factor_koh / peso_aceite_g',
            $this->campos
        );

        $this->assertTrue($r['ok'], implode(' | ', $r['errors']));
        $this->assertSame([], $r['errors']);
        $this->assertSame(['volumen_gastado_ml', 'vol_blanco', 'factor_koh', 'peso_aceite_g'], $r['uses']);
    }

    public function test_acepta_chendong_y_no_confunde_la_funcion_con_un_campo(): void
    {
        $r = $this->validator->validate('(1.51 - log10(fal_ppb / 1000)) / 0.0035', ['fal_ppb', 'dp']);

        $this->assertTrue($r['ok'], implode(' | ', $r['errors']));
        // log10 es función, no campo: no puede aparecer en 'uses'.
        $this->assertSame(['fal_ppb'], $r['uses']);
    }

    public function test_rechaza_funcion_desconocida(): void
    {
        $r = $this->validator->validate('system(a) + 1', ['a']);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Función desconocida', implode(' ', $r['errors']));
        $this->assertStringContainsString('system', implode(' ', $r['errors']));
    }

    public function test_rechaza_campo_inexistente(): void
    {
        $r = $this->validator->validate('peso_aceite_g + humedad_relativa', $this->campos);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('humedad_relativa', implode(' ', $r['errors']));
        $this->assertStringContainsString('no existe en esta prueba', implode(' ', $r['errors']));
        // Aun con error, informa qué se intentó usar: el editor lo muestra.
        $this->assertSame(['peso_aceite_g', 'humedad_relativa'], $r['uses']);
    }

    public function test_reporta_todos_los_desconocidos_de_una_sola_pasada(): void
    {
        $r = $this->validator->validate('foo(a) + b + c', ['a']);

        $this->assertFalse($r['ok']);
        $errores = implode(' ', $r['errors']);
        $this->assertStringContainsString('foo', $errores);
        $this->assertStringContainsString('"b"', $errores);
        $this->assertStringContainsString('"c"', $errores);
    }

    public function test_rechaza_parentesis_desbalanceados(): void
    {
        foreach (['(a + b', 'a + b)', '((a)', 'abs(a'] as $rota) {
            $r = $this->validator->validate($rota, ['a', 'b']);
            $this->assertFalse($r['ok'], 'Debía rechazar: '.$rota);
            $this->assertStringContainsString('aréntesis', implode(' ', $r['errors']), 'Mensaje flojo para: '.$rota);
        }
    }

    public function test_rechaza_sintaxis_invalida(): void
    {
        foreach (['a +', 'a b', '* a', 'a 2', 'a,b', 'a + + ', ''] as $rota) {
            $r = $this->validator->validate($rota, ['a', 'b']);
            $this->assertFalse($r['ok'], 'Debía rechazar: '.$rota);
            $this->assertNotSame([], $r['errors']);
        }
    }

    public function test_rechaza_aridad_incorrecta(): void
    {
        $this->assertFalse($this->validator->validate('pow(a)', ['a'])['ok']);
        $this->assertFalse($this->validator->validate('abs(a, b)', ['a', 'b'])['ok']);
        $this->assertTrue($this->validator->validate('round(a, 3)', ['a'])['ok']);
    }

    /** SEGURIDAD: el editor no puede guardar nada de esto. */
    public function test_rechaza_intentos_de_inyeccion(): void
    {
        $ataques = [
            'system("ls")',
            'exec(a)',
            '1;DROP TABLE test_fields',
            '${a}',
            '{$a}',
            '`ls`',
            "eval('1+1')",
            'a; a',
            'a && b',
            'a = 1',
            'a[0]',
            '<?= a ?>',
            'require("/etc/passwd")',
            "var col5 = parseFloat(document.getElementById('col5').value);",
        ];

        foreach ($ataques as $ataque) {
            $r = $this->validator->validate($ataque, ['a', 'b']);
            $this->assertFalse($r['ok'], 'Debía rechazar: '.$ataque);
        }
    }

    public function test_rechaza_entradas_patologicas(): void
    {
        $this->assertFalse($this->validator->validate(str_repeat('1 + ', 500).'1', [])['ok']);
        $this->assertFalse($this->validator->validate(str_repeat('(', 60).'1'.str_repeat(')', 60), [])['ok']);
        $this->assertFalse($this->validator->validate(str_repeat('x', 100), [])['ok']);
    }

    /** Sin lista de campos no se puede juzgar la existencia: solo la sintaxis. */
    public function test_sin_lista_de_campos_solo_valida_sintaxis(): void
    {
        $this->assertTrue($this->validator->validate('cualquier_campo * 2', [])['ok']);
        $this->assertFalse($this->validator->validate('cualquier_campo *', [])['ok']);
    }

    public function test_detecta_ciclo_de_dos_campos(): void
    {
        $ciclos = $this->validator->detectCycles([
            'a' => 'b + 1',
            'b' => 'a * 2',
            'c' => 'a + 10',
        ]);

        $this->assertCount(1, $ciclos);
        $this->assertSame(['a', 'b', 'a'], $ciclos[0]);
    }

    public function test_detecta_autorreferencia(): void
    {
        $ciclos = $this->validator->detectCycles(['dp' => 'dp + 1']);

        $this->assertCount(1, $ciclos);
        $this->assertSame(['dp', 'dp'], $ciclos[0]);
    }

    public function test_detecta_ciclo_de_tres_campos(): void
    {
        $ciclos = $this->validator->detectCycles([
            'a' => 'b + 1',
            'b' => 'c + 1',
            'c' => 'a + 1',
        ]);

        $this->assertCount(1, $ciclos);
        $this->assertSame(['a', 'b', 'c', 'a'], $ciclos[0]);
    }

    public function test_no_inventa_ciclos_en_una_cadena_valida(): void
    {
        $ciclos = $this->validator->detectCycles([
            'promedio' => '(lectura_1 + lectura_2) / 2',
            'dp' => '(1.51 - log10(promedio / 1000)) / 0.0035',
            'redondeado' => 'round(dp, 0)',
        ]);

        $this->assertSame([], $ciclos);
    }

    /** El ciclo puede venir en la lista de campos tal como sale de la base. */
    public function test_detecta_ciclos_recibiendo_la_lista_de_campos(): void
    {
        $ciclos = $this->validator->detectCycles([
            ['code' => 'a', 'formula' => 'b + 1'],
            ['code' => 'b', 'formula' => 'a + 1'],
            ['code' => 'manual', 'formula' => null],
        ]);

        $this->assertCount(1, $ciclos);
        $this->assertSame(['a', 'b', 'a'], $ciclos[0]);
    }

    /** Dos ciclos independientes se reportan por separado, sin duplicar rotaciones. */
    public function test_reporta_ciclos_independientes_una_sola_vez(): void
    {
        $ciclos = $this->validator->detectCycles([
            'a' => 'b', 'b' => 'a',
            'x' => 'y', 'y' => 'x',
        ]);

        $this->assertCount(2, $ciclos);
    }

    /** Un campo que el analista carga a mano nunca cierra un ciclo. */
    public function test_una_referencia_a_campo_manual_no_es_ciclo(): void
    {
        $this->assertSame([], $this->validator->detectCycles(['total' => 'h2 + ch4']));
    }
}
