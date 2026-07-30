<?php

namespace Tests\Unit\Lab;

use App\Services\Lab\TestReportPayload;
use App\Support\PdfText;
use Tests\TestCase;

/**
 * Cómo se imprimen los números y los símbolos del informe.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LOS DOS DEFECTOS QUE ESTO FIJA, VISTOS EN UN PDF REAL                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * 1. La resistividad volumétrica salía impresa `8650000000000.00`. Son
 *    8,65 × 10¹² Ω·cm: catorce cifras que nadie lee ni compara con la muestra
 *    anterior, con dos decimales que además fingen precisión de centésimas de
 *    ohm sobre un número del orden del billón.
 * 2. Su UNIDAD salía impresa `?-cm`. Helvetica —la única fuente que dompdf
 *    dibuja sin embeber 700 kB— no tiene la omega, y dompdf no sustituye la
 *    fuente: pinta un `?`. Un informe acreditado con un `?` en la unidad de un
 *    ensayo no se puede entregar.
 */
class ReportNumberFormatTest extends TestCase
{
    /** @param array<int,mixed> $argumentos */
    private function comoTexto(float $valor, ?int $decimales = null): string
    {
        $metodo = new \ReflectionMethod(TestReportPayload::class, 'comoTexto');
        $metodo->setAccessible(true);

        return $metodo->invoke(new TestReportPayload(), $valor, $decimales);
    }

    // ─── Notación científica solo donde el decimal no sirve ──────────────

    public function test_una_magnitud_enorme_va_en_notacion_cientifica(): void
    {
        // El caso real: resistividad volumétrica de un aceite bueno.
        $this->assertSame('8.65 x 10^12', $this->comoTexto(8.65e12, 2));
    }

    public function test_una_magnitud_minuscula_tambien(): void
    {
        $this->assertSame('3.2 x 10^-6', $this->comoTexto(0.0000032, 2));
    }

    public function test_los_ensayos_normales_no_cambian(): void
    {
        // Todo lo que el laboratorio mide a diario cae entre los dos topes y se
        // imprime igual que antes. Si esto se rompiera, el cambio de arriba
        // habría reescrito el informe entero.
        $this->assertSame('0.281', $this->comoTexto(0.281, 3));
        $this->assertSame('42', $this->comoTexto(42.0, 0));
        $this->assertSame('65.80', $this->comoTexto(65.8, 2));
        $this->assertSame('4.94', $this->comoTexto(4.94, 2));
    }

    public function test_el_cero_no_se_va_a_notacion_cientifica(): void
    {
        // log10(0) es -INF. Sin la guarda, un cero medido imprimía "-inf".
        $this->assertSame('0.00', $this->comoTexto(0.0, 2));
    }

    public function test_la_mantisa_nunca_queda_sin_decimales(): void
    {
        // Con `decimals = 0` en el catálogo, redondear la mantisa a cero cifras
        // convertía 8.65 × 10¹² en "9 x 10^12": un 4 % de error de puro formato.
        $this->assertSame('8.7 x 10^12', $this->comoTexto(8.65e12, 0));
    }

    public function test_el_negativo_conserva_su_signo(): void
    {
        $this->assertSame('-8.65 x 10^12', $this->comoTexto(-8.65e12, 2));
    }

    // ─── Símbolos que Helvetica no dibuja ────────────────────────────────

    public function test_la_omega_de_la_unidad_no_sale_como_interrogacion(): void
    {
        $this->assertSame('ohm·cm', PdfText::safe('Ω·cm'));
    }

    public function test_los_comparadores_se_escriben_con_ascii(): void
    {
        $this->assertSame('<= 0.5', PdfText::safe('≤ 0.5'));
        $this->assertSame('>= 40', PdfText::safe('≥ 40'));
        $this->assertSame('+/- 2', PdfText::safe('± 2'));
    }

    public function test_los_subindices_de_los_gases_se_bajan_a_texto(): void
    {
        $this->assertSame('C2H2', PdfText::safe('C₂H₂'));
        $this->assertSame('mm2', PdfText::safe('mm²'));
    }

    public function test_el_grado_y_la_raya_se_conservan(): void
    {
        // Los dos SÍ están en WinAnsi y el informe los usa en todas las celdas
        // vacías: cambiar la raya por un guion pelado la confundiría con un
        // signo menos.
        $this->assertSame('25 °C', PdfText::safe('25 °C'));
        $this->assertSame('—', PdfText::safe('—'));
    }
}
