<?php

namespace Tests\Unit\Lab;

use App\Services\Lab\InstrumentFileParser;
use PHPUnit\Framework\TestCase;

/**
 * Los textos de prueba reproducen la FORMA de los archivos reales de los
 * instrumentos del laboratorio (ensayador disruptivo DPA 75C, medidor de factor
 * de disipación DTL C, cromatógrafo y HPLC de furanos), no los archivos en sí:
 * el repositorio es público y los originales llevan números de muestra de
 * clientes.
 *
 * Lo que sí se reproduce al detalle es todo lo que rompía el parser viejo:
 * los tabuladores en cantidad variable, los acentos, la marca de orden de
 * bytes, las etiquetas repetidas y los valores censurados.
 */
class InstrumentFileParserTest extends TestCase
{
    private InstrumentFileParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new InstrumentFileParser();
    }

    private function dpa(): string
    {
        return implode("\n", [
            'Protocolo de medición',
            '',
            'Ensayador disruptivo',
            'DPA 75C Versión:  1.07',
            '_____________________________',
            '',
            'Medición según norma: '."\t\t".'ASTM D1816:2004 2mm',
            'Distancia entre electrodos: '."\t\t".'2 mm',
            '',
            'Valores de medición',
            '',
            'Temperatura: '."\t\t".'20 °C',
            '',
            'Medición 1:'."\t\t\t".'39.1  kV',
            'Medición 2:'."\t\t\t".'31.8  kV',
            'Medición 5:'."\t\t\t".'>75.0  kV',
            '',
            'Valor medio:'."\t\t\t\t".'46.4  kV',
            'Desviación estándar: '."\t\t\t".'16.7  kV',
        ]);
    }

    private function cromas(): string
    {
        // Con marca de orden de bytes al principio, como el archivo real.
        return "\u{FEFF}".implode("\n", [
            'REPORTE DGA',
            '',
            'Sample name:        2025-0054',
            'Method Name         ASTM 3612.amx',
            '',
            'Name         Concentration PPM',
            'CO2         876.4',
            'C2H4         2.4',
            'C2H2         0.1',
            'H2         151.1',
            'O2         2557.4',
        ]);
    }

    private function furanos(): string
    {
        return implode("\n", [
            ' DAD-CH2 274 nm Results',
            ' Name                            ESTD concentration    Area   Retention Time',
            ' 5-hidroxymethyl-2-furalde                 1.585       2772   2.687',
            ' hyde',
            ' 2-furaldehyde                            11.658      26946   4.427',
            ' 2-acetylfuran                         0.000 BDL',
        ]);
    }

    public function test_protocolo_con_etiqueta_y_tabuladores(): void
    {
        $r = $this->parser->parse($this->dpa(), ['fields' => [
            ['code' => 'rig_1', 'mode' => 'label', 'match' => 'Medición 1:'],
            ['code' => 'temp',  'mode' => 'label', 'match' => 'Temperatura:'],
            ['code' => 'media', 'mode' => 'label', 'match' => 'Valor medio:'],
        ]]);

        $this->assertSame(39.1, $r['values']['rig_1'][0]['number']);
        $this->assertSame(20.0, $r['values']['temp'][0]['number']);
        $this->assertSame(46.4, $r['values']['media'][0]['number']);
        $this->assertSame([], $r['unmatched']);
    }

    public function test_la_unidad_no_hay_que_declararla(): void
    {
        // El parser viejo pedía configurar los caracteres a borrar y los
        // borraba de a uno, así que quitar "kV" borraba todas las k y las V.
        $r = $this->parser->parse($this->dpa(), ['fields' => [
            ['code' => 'rig_1', 'mode' => 'label', 'match' => 'Medición 1:'],
        ]]);

        $this->assertSame(39.1, $r['values']['rig_1'][0]['number']);
        $this->assertNull($r['values']['rig_1'][0]['qualifier']);
    }

    public function test_valor_por_encima_del_tope_del_instrumento(): void
    {
        // ">75.0 kV" significa que el aceite no rompió: la rigidez es AL MENOS
        // 75, no exactamente 75.
        $r = $this->parser->parse($this->dpa(), ['fields' => [
            ['code' => 'rig_5', 'mode' => 'label', 'match' => 'Medición 5:'],
        ]]);

        $this->assertSame(75.0, $r['values']['rig_5'][0]['number']);
        $this->assertSame('gt', $r['values']['rig_5'][0]['qualifier']);
    }

    public function test_la_etiqueta_no_encuentra_su_propio_prefijo(): void
    {
        // "Medición 1" no debe encontrar "Medición 10", ni "Valor medio" debe
        // quedarse con "Valor medi". El parser viejo hacía las dos cosas.
        $texto = "Medición 10:\t\t50.0  kV\nMedición 1:\t\t39.1  kV";
        $r = $this->parser->parse($texto, ['fields' => [
            ['code' => 'rig_1', 'mode' => 'label', 'match' => 'Medición 1:'],
        ]]);

        $this->assertCount(1, $r['values']['rig_1']);
        $this->assertSame(39.1, $r['values']['rig_1'][0]['number']);
    }

    public function test_etiquetas_repetidas_devuelven_todas_las_ocurrencias(): void
    {
        // El medidor de factor de disipación escribe "Llenado 1/2/3" con las
        // mismas etiquetas. El parser viejo se quedaba con la primera y las
        // otras dos se perdían sin aviso.
        $texto = implode("\n", [
            'Llenado 1', 'en 50 Hz:'."\t\t".'1.0040 %',
            'Llenado 2', 'en 50 Hz:'."\t\t".'1.0120 %',
            'Llenado 3', 'en 50 Hz:'."\t\t".'1.0080 %',
        ]);

        $r = $this->parser->parse($texto, ['fields' => [
            ['code' => 'fp50', 'mode' => 'label', 'match' => 'en 50 Hz:'],
        ]]);

        $this->assertCount(3, $r['values']['fp50']);
        $this->assertSame(
            [1.004, 1.012, 1.008],
            array_column($r['values']['fp50'], 'number')
        );
    }

    public function test_tabla_de_cromatografia_con_marca_de_orden_de_bytes(): void
    {
        $r = $this->parser->parse($this->cromas(), ['fields' => [
            ['code' => 'h2',   'mode' => 'lookup', 'match' => 'H2'],
            ['code' => 'co2',  'mode' => 'lookup', 'match' => 'CO2'],
            ['code' => 'c2h2', 'mode' => 'lookup', 'match' => 'C2H2'],
        ]]);

        $this->assertSame(151.1, $r['values']['h2'][0]['number']);
        $this->assertSame(876.4, $r['values']['co2'][0]['number']);
        $this->assertSame(0.1, $r['values']['c2h2'][0]['number']);
    }

    public function test_la_busqueda_en_tabla_no_confunde_gases_de_nombre_parecido(): void
    {
        // "C2H4" no debe traer el valor de "C2H4..." ni "H2" el de "H2O".
        $texto = "Name         Concentration PPM\nH2O         12.0\nH2         151.1";
        $r = $this->parser->parse($texto, ['fields' => [
            ['code' => 'h2', 'mode' => 'lookup', 'match' => 'H2'],
        ]]);

        $this->assertCount(1, $r['values']['h2']);
        $this->assertSame(151.1, $r['values']['h2'][0]['number']);
    }

    public function test_furanos_no_detectado_no_es_cero(): void
    {
        // "0.000 BDL" es "por debajo del límite de detección". Guardarlo como
        // un cero haría que un aceite sin furanos y uno con furanos justo bajo
        // el límite se vean iguales.
        $r = $this->parser->parse($this->furanos(), ['fields' => [
            ['code' => 'fal',  'mode' => 'lookup', 'match' => '2-furaldehyde'],
            ['code' => 'acet', 'mode' => 'lookup', 'match' => '2-acetylfuran'],
        ]]);

        $this->assertSame(11.658, $r['values']['fal'][0]['number']);
        $this->assertNull($r['values']['fal'][0]['qualifier']);

        $this->assertSame(0.0, $r['values']['acet'][0]['number']);
        $this->assertSame('lt', $r['values']['acet'][0]['qualifier']);
    }

    public function test_nombre_cortado_en_dos_lineas(): void
    {
        // El cromatógrafo de furanos parte los nombres largos: la fila dice
        // "5-hidroxymethyl-2-furalde" y la siguiente línea, solo "hyde". Se
        // configura con el nombre tal como aparece, cortado.
        $r = $this->parser->parse($this->furanos(), ['fields' => [
            ['code' => 'hmf', 'mode' => 'lookup', 'match' => '5-hidroxymethyl-2-furalde'],
        ]]);

        $this->assertSame(1.585, $r['values']['hmf'][0]['number']);
    }

    public function test_lo_que_no_aparece_se_informa_en_vez_de_fallar(): void
    {
        // El parser viejo lanzaba una excepción cuando la unidad configurada no
        // estaba en ese archivo en particular, y decidía si dejaba guardar
        // mirando SOLO la última columna.
        $r = $this->parser->parse($this->dpa(), ['fields' => [
            ['code' => 'rig_1',    'mode' => 'label', 'match' => 'Medición 1:'],
            ['code' => 'inexista', 'mode' => 'label', 'match' => 'Presión atmosférica:'],
        ]]);

        $this->assertSame(39.1, $r['values']['rig_1'][0]['number']);
        $this->assertSame(['inexista'], $r['unmatched']);
        $this->assertArrayNotHasKey('inexista', $r['values']);
    }

    public function test_la_etiqueta_es_literal_y_no_una_expresion_regular(): void
    {
        // En el sistema viejo la etiqueta se interpolaba cruda dentro de una
        // expresión regular: un paréntesis cambiaba el significado o la rompía.
        $texto = "Factor (a 25 °C):\t\t0.045 %";
        $r = $this->parser->parse($texto, ['fields' => [
            ['code' => 'fp', 'mode' => 'label', 'match' => 'Factor (a 25 °C):'],
        ]]);

        $this->assertSame(0.045, $r['values']['fp'][0]['number']);
    }

    public function test_coma_decimal(): void
    {
        $texto = "Valor medio:\t\t46,4  kV";
        $r = $this->parser->parse($texto, ['fields' => [
            ['code' => 'media', 'mode' => 'label', 'match' => 'Valor medio:'],
        ]]);

        $this->assertSame(46.4, $r['values']['media'][0]['number']);
    }

    public function test_los_saltos_de_windows_no_ensucian_el_valor(): void
    {
        $texto = "Medición 1:\t\t39.1  kV\r\nMedición 2:\t\t31.8  kV\r\n";
        $r = $this->parser->parse($texto, ['fields' => [
            ['code' => 'rig_1', 'mode' => 'label', 'match' => 'Medición 1:'],
        ]]);

        $this->assertSame(39.1, $r['values']['rig_1'][0]['number']);
        $this->assertSame('39.1  kV', $r['values']['rig_1'][0]['raw']);
    }

    public function test_un_mapa_vacio_no_rompe(): void
    {
        $r = $this->parser->parse($this->dpa(), []);

        $this->assertSame([], $r['values']);
        $this->assertSame([], $r['unmatched']);
    }
}
