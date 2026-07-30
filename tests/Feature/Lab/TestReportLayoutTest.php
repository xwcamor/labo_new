<?php

namespace Tests\Feature\Lab;

use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La MAQUETA del informe moderno, no su contenido.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ SE FIJA ACÁ Y POR QUÉ                                                │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El informe salía como tres impresiones engrapadas: una hoja por ensayo, y en
 * cada hoja la ficha del cliente Y la del equipo completas —treinta líneas— más
 * las firmas. Eso venía del sistema anterior, donde cada ensayo era una
 * impresión independiente.
 *
 * Las tres reglas de la maqueta nueva son fáciles de deshacer sin darse cuenta
 * (basta mover un `@if` de página), y una de ellas es NORMATIVA, así que se
 * fijan acá:
 *
 *   1. Las fichas del cliente y del equipo van UNA vez.
 *   2. Las firmas y el QR van UNA vez, al cierre.
 *   3. Dos secciones con distinto estado de ACREDITACIÓN nunca comparten
 *      página. Ésta es la normativa: el sello del organismo acreditador se
 *      estampa por hoja, así que una hoja con un método dentro del alcance y
 *      otro fuera afirmaría por escrito una acreditación que el laboratorio no
 *      tiene para ese ensayo.
 *
 * Se prueba sobre el HTML de la plantilla, no sobre el PDF: lo que se verifica
 * es cuántas veces aparece cada bloque y dónde cae el salto de página, y eso
 * está en el HTML. Renderizar con dompdf sumaría medio segundo por caso sin
 * agregar nada.
 */
class TestReportLayoutTest extends TestCase
{
    /** El HTML del informe con las secciones que se le pasen. */
    private function html(array $secciones, array $extra = []): string
    {
        return view('lab_management/reports/test_report', array_merge([
            'sections' => $secciones,
            'analysis' => [],
            'notes'    => [],
            'sample'   => [
                'code' => '2026-0001', 'report_number' => 'INF-1', 'received_at' => '2026-01-09',
                'sampled_at' => '2026-01-09', 'issued_at' => null, 'service_order' => 'OS-1',
                'sampling_point' => 'Válvula inferior', 'sampling_reason' => null,
                'description' => null, 'contact_info' => null, 'end_user' => null,
                'sampler' => null, 'oil_temp_c' => null, 'equipment_temp_c' => null,
                'ambient_temp_c' => null, 'relative_humidity' => null,
            ],
            'customer'  => ['name' => 'Energía del Sur', 'address' => 'San Isidro'],
            'equipment' => ['serial' => 'SN-1', 'tag' => 'T-01'],
            'letterhead' => [
                'name' => 'Laboratorio', 'address' => 'Lima', 'logo' => null,
                'accreditation_logo' => null, 'accreditation_note' => null, 'disclaimer' => null,
            ],
            'signers'     => collect(),
            'generatedAt' => Carbon::parse('2026-07-30 10:00:00'),
            'generatedBy' => 'Prueba',
            'verifyCode'  => 'ABC123',
            'verifyQr'    => '',
        ], $extra))->render();
    }

    /**
     * Una sección de resultados con `$filas` filas.
     *
     * `$acreditada` es lo que decide el agrupamiento por página, y es el único
     * parámetro que importa para lo que se prueba acá.
     */
    private function seccion(string $nombre, bool $acreditada, int $filas = 1): array
    {
        return [
            'test' => $nombre,
            'family' => strtolower($nombre),
            'group' => null,
            'accredited' => $acreditada,
            'not_accredited' => ! $acreditada,
            'no_criteria' => 0,
            'footnote' => null,
            'conditions' => [
                'standard' => 'ASTM D974', 'run_date' => '2026-01-10',
                'sample_temp_c' => 25, 'ambient_temp_c' => 22,
                'ambient_humidity' => 55, 'lab_pressure_hpa' => 1013,
            ],
            'rows' => array_map(fn ($i) => [
                'item' => $i + 1, 'analyte' => "Parámetro {$i}", 'unit' => 'ppm',
                'method' => 'ASTM D974', 'accreditation' => $acreditada ? 'A' : 'NA',
                'limit' => '150', 'criterion' => 'IEEE C57.106',
                'value' => '12.3', 'status' => 'in_spec',
            ], range(0, $filas - 1)),
        ];
    }

    /** Cuántas páginas dibuja la plantilla: la primera + un `.brk` por salto. */
    private function paginas(string $html): int
    {
        return substr_count($html, 'class="brk"') + 1;
    }

    // ─── Las fichas y las firmas, una sola vez ───────────────────────────

    public function test_la_ficha_del_cliente_y_del_equipo_van_una_sola_vez(): void
    {
        $html = $this->html([
            $this->seccion('Fisicoquímico', acreditada: false),
            $this->seccion('Cromatografía', acreditada: false, filas: 40),
            $this->seccion('Furanos', acreditada: false),
        ]);

        // Más de una página: si el agrupamiento no partiera nada, el caso no
        // probaría nada.
        $this->assertGreaterThan(1, $this->paginas($html));

        $this->assertSame(1, substr_count($html, __('reports.customer_info')));
        $this->assertSame(1, substr_count($html, __('reports.equipment_info')));

        // Lo que las reemplaza en las páginas siguientes: la banda de una línea
        // que identifica la hoja. Sin ella una hoja suelta no se identifica y
        // ahí sí se rompe la ISO 17025.
        $this->assertStringContainsString('class="run"', $html);
    }

    public function test_las_firmas_y_el_qr_van_una_sola_vez_al_cierre(): void
    {
        $html = $this->html([
            $this->seccion('Fisicoquímico', acreditada: true),
            $this->seccion('Cromatografía', acreditada: false),
        ]);

        // Se cuenta el BLOQUE de firmas, no un rótulo suelto: el rótulo
        // "Reportado por:" que iba a la izquierda se quitó —cada firma dice su
        // propia relación debajo de la línea— y anclar el test a un texto que
        // puede desaparecer hace que el test caiga por el motivo equivocado.
        $this->assertSame(1, substr_count($html, '<table class="sign">'));
        $this->assertSame(1, substr_count($html, __('reports.verify_hint')));

        // Y en el CIERRE: después del último salto de página no hay otro.
        $posFirma = strpos($html, '<table class="sign">');
        $posUltimoSalto = strrpos($html, 'class="brk"');
        $this->assertGreaterThan($posUltimoSalto, $posFirma);
    }

    // ─── La regla normativa del sello ────────────────────────────────────

    public function test_una_pagina_nunca_mezcla_acreditado_con_no_acreditado(): void
    {
        $html = $this->html([
            $this->seccion('Fisicoquímico', acreditada: true),
            $this->seccion('Cromatografía', acreditada: false),
            $this->seccion('Furanos', acreditada: false),
        ]);

        // Dos páginas de ensayos (acreditada | las dos sin acreditar) + la del
        // análisis. Las dos sin acreditar SÍ comparten: eso es lo que ahorra
        // papel sin tocar el sello.
        $this->assertSame(3, $this->paginas($html));

        // La página de la acreditada no puede contener a las otras dos.
        [$primera] = explode('class="brk"', $html, 2);
        $this->assertStringContainsString('FISICOQUÍMICO', $primera);
        $this->assertStringNotContainsString('CROMATOGRAFÍA', $primera);
    }

    public function test_dos_secciones_acreditadas_comparten_pagina(): void
    {
        $html = $this->html([
            $this->seccion('Fisicoquímico', acreditada: true),
            $this->seccion('Rigidez', acreditada: true),
        ]);

        // Una sola página de ensayos + la del análisis.
        $this->assertSame(2, $this->paginas($html));

        [$primera] = explode('class="brk"', $html, 2);
        $this->assertStringContainsString('FISICOQUÍMICO', $primera);
        $this->assertStringContainsString('RIGIDEZ', $primera);
    }

    public function test_el_sello_de_acreditacion_solo_sale_en_la_pagina_acreditada(): void
    {
        $html = $this->html(
            [
                $this->seccion('Fisicoquímico', acreditada: true),
                $this->seccion('Cromatografía', acreditada: false),
            ],
            ['letterhead' => [
                'name' => 'Laboratorio', 'address' => 'Lima', 'logo' => null,
                'accreditation_logo' => 'data:image/png;base64,SELLO',
                'accreditation_note' => null, 'disclaimer' => null,
            ]],
        );

        // Una vez: en la hoja de la sección acreditada. Ni en la de la
        // cromatografía sin acreditar ni en la del análisis —una opinión queda
        // siempre fuera del alcance—.
        $this->assertSame(1, substr_count($html, 'SELLO'));

        [$primera] = explode('class="brk"', $html, 2);
        $this->assertStringContainsString('SELLO', $primera);
    }

    // ─── El tope de filas por hoja ───────────────────────────────────────

    public function test_una_seccion_larga_no_se_apila_con_otra_en_la_misma_hoja(): void
    {
        // Treinta filas pasan el tope de la hoja; la segunda sección arranca en
        // otra. DomPDF no mide antes de maquetar, así que el tope es lo único
        // que evita que la tabla se derrame a una hoja de continuación sin
        // membrete.
        $html = $this->html([
            $this->seccion('Cromatografía', acreditada: false, filas: 30),
            $this->seccion('Furanos', acreditada: false),
        ]);

        $this->assertSame(3, $this->paginas($html));

        [$primera] = explode('class="brk"', $html, 2);
        $this->assertStringNotContainsString('FURANOS', $primera);
    }

    // ─── La columna del veredicto ────────────────────────────────────────

    public function test_cada_fila_dice_su_condicion_con_palabras(): void
    {
        $seccion = $this->seccion('Fisicoquímico', acreditada: true, filas: 3);
        $seccion['rows'][1]['status'] = 'out_of_spec';
        $seccion['rows'][2]['status'] = null;

        $html = $this->html([$seccion]);

        // El color solo no sobrevive a una fotocopia, y este papel se fotocopia:
        // el veredicto va escrito en su columna, no insinuado por el tono del
        // número.
        $this->assertStringContainsString(__('reports.in_spec'), $html);
        $this->assertStringContainsString(__('reports.out_of_spec'), $html);
    }

    /**
     * El parámetro sin criterio de aceptación sale en RAYA: ni la palabra "sin
     * criterio" en su celda, ni el recuadro de aviso al pie.
     *
     * Lo que hay que garantizar es que NO se lea como conforme, y eso lo da la
     * raya: raya en el límite y raya en el veredicto. Escribirlo además en
     * letras y contarlo en un recuadro al pie convertía en advertencia lo que es
     * normal —el cliente pide medir un parámetro que su norma no acota— y una
     * advertencia que sale en todos los informes deja de leerse, incluidas las
     * dos que sí importan (ensayos pendientes, muestra sin equipo).
     */
    public function test_el_parametro_sin_criterio_sale_en_raya_y_nunca_conforme(): void
    {
        $seccion = $this->seccion('Fisicoquímico', acreditada: true, filas: 1);
        $seccion['rows'][0]['status'] = null;
        $seccion['rows'][0]['limit']  = '—';
        $seccion['no_criteria'] = 1;

        $html = $this->html([$seccion]);

        $this->assertStringNotContainsString('sin criterio', mb_strtolower($html));
        // Ni la etiqueta gris de la celda ni el recuadro del pie existen ya.
        $this->assertStringNotContainsString('pill--none', $html);
        $this->assertStringNotContainsString('foot__warn', $html);
        // Y sobre todo: no se le inventa una conformidad.
        $this->assertStringNotContainsString(__('reports.in_spec'), $html);
    }

    // ─── La norma y la trama de la tabla ─────────────────────────────────

    /**
     * La norma del método es una COLUMNA, no una línea chica debajo del nombre
     * del ensayo, y las filas no llevan banda alterna.
     */
    public function test_la_norma_es_columna_y_las_filas_no_alternan_color(): void
    {
        $html = $this->html([$this->seccion('Fisicoquímico', acreditada: true, filas: 4)]);

        $this->assertStringContainsString(__('reports.col_method'), $html);
        // La cebra competía con el rojo del valor fuera de norma, que es el
        // único color que en este papel tiene que significar algo.
        $this->assertStringNotContainsString('zebra', $html);
    }
}
