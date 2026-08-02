<?php

namespace Tests\Feature\Lab;

use Tests\TestCase;

/**
 * Las hojas del informe CLÁSICO.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL DEFECTO QUE ESTO FIJA                                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El informe clásico imprimía DOS hojas de ensayo —fisicoquímico y
 * cromatografía— y nada más. Las otras trece pruebas del catálogo se pedían, se
 * cargaban, se validaban, salían en el PDF moderno… y en el clásico no existían:
 * el cliente que pidió furanos recibía un papel sin furanos, y nada avisaba.
 *
 * El sistema anterior sí las tenía: dieciséis partials ERB, uno por prueba, con
 * la misma tabla copiada y cada uno con sus variaciones. Por eso el límite del
 * DBDS y el rango del inhibidor terminaron escritos a mano dentro del HTML.
 *
 * Acá se verifica la MAQUETA, que es lo que se rompe al agregar una prueba: que
 * la hoja exista, que lleve las columnas que le corresponden, que el sello de
 * acreditación salga solo en las tres familias del alcance, y que nada quede
 * escrito en la plantilla que debería ser un dato.
 */
class LegacyReportSheetsTest extends TestCase
{
    /** @return array<string,mixed> */
    private function hoja(string $familia, array $filas = [], ?array $seccion = null): ?array
    {
        $renderer = new \App\Services\Lab\LegacyReportRenderer();

        $metodo = new \ReflectionMethod($renderer, 'paginaGenerica');
        $metodo->setAccessible(true);

        $seccion ??= [
            'family' => $familia,
            'conditions' => ['run_date' => '2026-01-10'],
            'rows' => $filas !== [] ? $filas : [[
                'analyte' => 'Parámetro', 'unit' => 'ppm', 'method' => 'ASTM D1234',
                'accreditation' => 'NA', 'limit' => '—', 'value' => '1.5', 'status' => null,
            ]],
        ];

        return $metodo->invoke($renderer, $familia, $seccion, [
            'temp' => '22.5 °C', 'humedad' => '58 %HR',
            'presion' => '1013 hPa', 'sample_temp' => '25.0 °C',
        ]);
    }

    // ─── Las trece hojas que faltaban ────────────────────────────────────

    public function test_las_trece_familias_del_sistema_anterior_tienen_hoja(): void
    {
        // La lista es la del `show.erb` del sistema anterior, sin las dos que ya
        // existían. Si mañana alguien borra una entrada de la configuración, el
        // informe deja de imprimir esa prueba en silencio; esto lo delata.
        $familias = [
            'pcb', 'furanos', 'particulas', 'azufre_corrosivo', 'sedimentos',
            'metales', 'viscocidad', 'dbds', 'inflamacion', 'fluidez',
            'inhibidor', 'grado_de_polimerizacion', 'pasivador',
        ];

        foreach ($familias as $familia) {
            $this->assertArrayHasKey(
                $familia,
                config('legacy_report.sheets'),
                "La familia {$familia} no tiene hoja declarada en el informe clásico.",
            );

            $hoja = $this->hoja($familia);
            $this->assertNotNull($hoja, "La hoja de {$familia} no se armó.");
            $this->assertNotSame('', $hoja['titulo']);
            // El título tiene que ser texto legible, nunca la clave de traducción.
            $this->assertStringNotContainsString('reports.', $hoja['titulo']);
        }
    }

    public function test_las_hojas_salen_en_el_orden_del_sistema_anterior(): void
    {
        // El orden es el que el laboratorio y sus clientes conocen: primero el
        // fisicoquímico, después la cromatografía, y de ahí las demás.
        $orden = array_keys(config('legacy_report.sheets'));

        $this->assertSame('fisicoquimico', $orden[0]);
        $this->assertSame('analisis_cromatografico', $orden[1]);
        $this->assertSame('pcb', $orden[2]);
    }

    // ─── Las columnas de cada hoja ───────────────────────────────────────

    public function test_furanos_no_lleva_columna_de_valor_de_orientacion(): void
    {
        // La norma no fija criterio de aceptación para furanos, y el sistema
        // anterior tampoco tenía la columna. Dibujarla con seis rayas sugiere un
        // dato que alguien olvidó cargar.
        $hoja = $this->hoja('furanos');

        $this->assertNotContains('orientacion', $hoja['columnas']);
        $this->assertContains('metodo', $hoja['columnas']);
    }

    public function test_azufre_corrosivo_lleva_solo_sus_tres_columnas(): void
    {
        // Es un ensayo cualitativo: no tiene unidad ni valor de orientación.
        $hoja = $this->hoja('azufre_corrosivo');

        $this->assertSame(['item', 'norma', 'ensayo', 'resultado'], $hoja['columnas']);
    }

    public function test_el_rotulo_de_la_columna_del_parametro_cambia_por_familia(): void
    {
        // Contra el archivo de idioma, no contra el texto en español: la suite
        // corre en inglés y clavar la palabra acá haría fallar el test por el
        // idioma en vez de por el rótulo.
        $this->assertSame(__('reports.legacy_col3.furanos'), $this->hoja('furanos')['col3']);
        $this->assertSame(__('reports.legacy_col3.particulas'), $this->hoja('particulas')['col3']);
        // Sin entrada propia cae en ENSAYO, no en una clave de traducción.
        $this->assertSame('ENSAYO', $this->hoja('viscocidad')['col3']);
        $this->assertStringNotContainsString('reports.', $this->hoja('viscocidad')['col3']);
    }

    // ─── El sello de acreditación ────────────────────────────────────────

    public function test_solo_tres_familias_llevan_sello_de_acreditacion(): void
    {
        // Es normativo: el sello va únicamente en hojas cuyos métodos están dentro
        // del alcance. En el sistema anterior eso se decidía archivo por archivo
        // —incluir `_report_logo_main` o `_report_logo_parcial`— y no había forma
        // de auditarlo sin abrir los dieciséis.
        $conSello = collect(config('legacy_report.sheets'))
            ->filter(fn ($h) => ($h['anab'] ?? false) === true)
            ->keys()
            ->all();

        $this->assertSame(
            ['fisicoquimico', 'analisis_cromatografico', 'azufre_corrosivo'],
            $conSello,
        );
    }

    public function test_una_hoja_sin_alcance_no_lleva_sello(): void
    {
        $this->assertFalse($this->hoja('furanos')['anab']);
        $this->assertFalse($this->hoja('metales')['anab']);
    }

    // ─── Lo que ya no está escrito en la plantilla ───────────────────────

    public function test_el_limite_del_dbds_sale_del_cuadro_y_no_de_la_plantilla(): void
    {
        // En el sistema anterior el `5` del DBDS estaba escrito dentro del HTML
        // (`_report_dbds.erb:34`), igual que el rango `0.08 - 3.00 %` del
        // inhibidor. Si la norma se revisa, hay que editar la plantilla.
        $hoja = $this->hoja('dbds', [[
            'analyte' => 'Dibencil disulfuro', 'unit' => 'mg/kg', 'method' => 'IEC 62697',
            'accreditation' => 'NA', 'limit' => '5 (máximo)', 'value' => '2.1', 'status' => 'in_spec',
        ]]);

        $this->assertContains('orientacion', $hoja['columnas']);
        $this->assertSame('5 (máximo)', $hoja['filas'][0]['orientacion']);

        // Y la plantilla no tiene ningún número de norma escrito.
        $blade = file_get_contents(resource_path('views/lab_management/reports/legacy/report.blade.php'));
        $this->assertStringNotContainsString('0.08 - 3.00', $blade);
    }

    public function test_sin_criterio_la_celda_va_en_raya_y_no_en_cero(): void
    {
        $hoja = $this->hoja('pcb', [[
            'analyte' => 'Contenido total de PCB', 'unit' => 'ppm', 'method' => 'ASTM D4059',
            'accreditation' => 'NA', 'limit' => '—', 'value' => '6.37', 'status' => null,
        ]]);

        $this->assertSame('-', $hoja['filas'][0]['orientacion']);
        $this->assertFalse($hoja['filas'][0]['fuera']);
    }

    public function test_la_norma_de_referencia_no_se_imprime_si_la_familia_no_la_tiene(): void
    {
        // El sistema anterior imprimía "(*) Norma de referencia -" en las hojas
        // sin criterio: un asterisco que no lleva a ninguna parte.
        $this->assertArrayNotHasKey('(*) Norma de referencia', $this->hoja('furanos')['condiciones']);
        $this->assertArrayHasKey('(*) Norma de referencia', $this->hoja('pcb')['condiciones']);
    }

    public function test_la_fecha_de_analisis_es_la_de_esa_prueba(): void
    {
        // Dos ensayos de la misma muestra se corren días distintos. El informe
        // clásico ponía `now()` en todas las hojas, o sea la fecha en que alguien
        // apretó "descargar".
        $hoja = $this->hoja('pcb', [], [
            'family' => 'pcb',
            'conditions' => ['run_date' => '2026-03-15'],
            'rows' => [[
                'analyte' => 'PCB', 'unit' => 'ppm', 'method' => 'ASTM D4059',
                'accreditation' => 'NA', 'limit' => '—', 'value' => '1', 'status' => null,
            ]],
        ]);

        $this->assertSame('15-03-2026', $hoja['condiciones']['Fecha de Análisis']);
    }

    public function test_una_familia_sin_resultados_no_dibuja_hoja(): void
    {
        // Un informe con una hoja vacía es peor que uno sin la hoja: parece que el
        // ensayo se corrió y no dio nada.
        $this->assertNull($this->hoja('metales', [], [
            'family' => 'metales', 'conditions' => [], 'rows' => [],
        ]));
    }

    // ─── El orden fijo del fisicoquímico ─────────────────────────────────

    /**
     * La hoja del fisicoquímico imprime sus trece parámetros SIEMPRE en el
     * mismo orden, y ese orden lo declara una constante del renderizador donde
     * la clave de cada entrada es el código del analito.
     *
     * De las trece claves, CINCO estaban mal escritas —`fp*` con otro nombre,
     * `rig`/`rig_ep` invertidos, `visual` por `con`— y el efecto era que la hoja
     * imprimía SEIS filas de trece sin decir nada: el parámetro medido y
     * validado simplemente no aparecía en el papel. Un código mal escrito no
     * rompe nada visible, así que se fija acá contra la lista de analitos del
     * seed, que es de dónde salen los códigos reales.
     */
    public function test_los_codigos_del_fisicoquimico_existen_en_el_catalogo(): void
    {
        $renderer = new \App\Services\Lab\LegacyReportRenderer();
        $constante = (new \ReflectionClass($renderer))->getConstant('FIQUIS');

        $catalogo = json_decode(
            file_get_contents(database_path('seeders/data/analytes.json')),
            true,
        );
        $codigos = array_column($catalogo['fiqui'], 'code');

        foreach (array_keys($constante) as $codigo) {
            $this->assertContains(
                $codigo,
                $codigos,
                "El código «{$codigo}» de la hoja del fisicoquímico no existe en el "
                . 'catálogo de analitos: esa fila nunca se va a imprimir.',
            );
        }

        // Y los trece, no seis: la hoja del sistema anterior tenía trece ítems
        // numerados y el laboratorio los conoce por su número.
        $this->assertCount(13, $constante);
        $this->assertSame(range(1, 13), array_column($constante, 0));
    }

    /**
     * Los trece parámetros del fisicoquímico tienen QUIÉN los alimente.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ EL DEFECTO QUE ESTO FIJA                                             │
     * └──────────────────────────────────────────────────────────────────────┘
     * La prueba de arriba verifica que los trece códigos EXISTAN en el catálogo
     * de parámetros, y pasaba en verde mientras la hoja imprimía ONCE filas. Un
     * parámetro que existe pero al que ninguna columna alimenta no produce
     * resultado, y sin resultado el informe no dibuja su fila. Eso es lo que
     * pasó con tensión interfacial y condición visual: los dos estaban anotados
     * en `analyte_map.json` bajo «pendientes», y la hoja salió con dos filas
     * menos que el papel del sistema anterior sin que nada avisara.
     *
     * Se verifica sobre el JSON y no sobre la base porque el JSON es la FUENTE:
     * dejar de declarar una columna ahí es el error que hay que atajar.
     */
    public function test_los_trece_parametros_del_fisicoquimico_tienen_columna_declarada(): void
    {
        $renderer = new \App\Services\Lab\LegacyReportRenderer();
        $constante = (new \ReflectionClass($renderer))->getConstant('FIQUIS');

        $mapa = json_decode(
            file_get_contents(database_path('seeders/data/analyte_map.json')),
            true,
        );
        $declarados = array_values($mapa['map']);

        foreach (array_keys($constante) as $codigo) {
            $this->assertContains(
                $codigo,
                $declarados,
                "Ninguna columna alimenta el parámetro «{$codigo}», así que la hoja del "
                . 'fisicoquímico sale con una fila menos que el papel del sistema '
                . 'anterior. Declararla en analyte_map.json → map.',
            );
        }
    }

    // ─── Dónde va la firma en cada hoja ──────────────────────────────────

    /**
     * La firma va debajo del cuadro de condiciones, salvo en la hoja de
     * cromatografía, que la lleva AL LADO.
     *
     * No es una preferencia estética: la hoja de cromatografía es la única que
     * además de la tabla de resultados lleva la grilla de relaciones de gases, y
     * con la firma debajo se derramaba a una segunda página. Ahí se rompe la
     * regla que el laboratorio da por sentada —una hoja por prueba— y el cliente
     * recibe una página huérfana con dos firmas y ningún resultado.
     *
     * El sistema anterior resolvía lo mismo del mismo modo: `_report_cromas.erb`
     * pone el cuadro en un `col-5` y la firma en el `col-7` de al lado, mientras
     * `_report_physicals.erb` la deja abajo.
     */
    public function test_solo_la_hoja_de_cromatografia_lleva_la_firma_al_costado(): void
    {
        $blade = file_get_contents(
            resource_path('views/lab_management/reports/legacy/report.blade.php'),
        );

        // La condición es la presencia de la grilla de relaciones, que solo trae
        // la cromatografía. Si alguien la cambia por el nombre de la familia, el
        // día que la familia se renombre la hoja vuelve a partirse en dos.
        $this->assertStringContainsString(
            '$firmasAlLado = ! empty($pagina[\'relaciones\'])',
            $blade,
        );

        // Y el bloque de firmas está UNA sola vez en la plantilla —es un
        // parcial—, así que no puede quedar dibujado en los dos lugares.
        $this->assertSame(1, substr_count($blade, '\'compacto\' => true'));
        $this->assertStringContainsString('@unless ($firmasAlLado)', $blade);
    }
}
