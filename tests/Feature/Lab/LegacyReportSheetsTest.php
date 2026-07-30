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
}
