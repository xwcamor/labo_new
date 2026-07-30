<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Sample;
use App\Services\Lab\TestReportPayload;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * El informe de ensayo: lo que recibe el cliente.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA VISTA NO ESCRIBE Y NO DECIDE                                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Abrir el informe del sistema anterior ejecutaba `RemReportDetail.update`
 * desde el propio ERB —para guardar el valor y la condición del grado de
 * polimerización, entre otras cosas— y volvía a interpretar los límites en el
 * momento de imprimir. O sea que el papel podía cambiar entre dos impresiones
 * de la misma muestra, y una lectura modificaba la base.
 *
 * Acá el informe LEE. El veredicto de cada resultado se decidió y se congeló al
 * validar la hoja; lo único que se escribe es el registro de auditoría de que
 * el informe se emitió, que es un hecho aparte del contenido.
 */
class TestReportController extends Controller
{
    public function __construct(private readonly TestReportPayload $payload)
    {
    }

    /**
     * El PDF de un informe REGISTRADO.
     *
     * La diferencia con `pdf()` no es el dibujo —es la misma plantilla— sino
     * qué se imprime: acá manda el informe. Su correlativo va en la banda, y
     * solo salen los ensayos que ese informe publica.
     */
    public function reportPdf(Request $request, \App\Models\SampleReport $report)
    {
        $report->loadMissing(['sample', 'visibilities']);

        return $this->render($request, $report->sample, $report);
    }

    /**
     * El informe en PDF de una muestra, sin registro: la vista previa.
     */
    public function pdf(Request $request, Sample $sample)
    {
        return $this->render($request, $sample, null);
    }

    /**
     * El mismo informe registrado, con la MAQUETA DEL SISTEMA ANTERIOR.
     *
     * Los clientes del laboratorio llevan años recibiendo el papel con esa
     * maqueta (una hoja por prueba, el sello ANAB en las acreditadas, las
     * relaciones de gases al pie del cromatográfico) y hay quien la sigue
     * pidiendo. La plantilla es una ELECCIÓN de quien exporta; los datos son
     * los mismos: si el informe está emitido, salen de su snapshot congelado,
     * igual que en la plantilla moderna.
     *
     * No lleva código de verificación ni QR — la maqueta vieja no los tenía y
     * agregárselos la convertiría en otra cosa. La emisión queda auditada con
     * la plantilla usada.
     */
    public function reportPdfLegacy(Request $request, \App\Models\SampleReport $report)
    {
        $report->loadMissing(['sample']);
        $sample = $report->sample;

        $datos = $report->frozenPayload() ?? $this->payload->forSample($sample, $report);

        AuditLog::create([
            'user_id'        => $request->user()?->id,
            'auditable_type' => Sample::class,
            'auditable_id'   => $sample->id,
            'event'          => 'report_generated',
            'new_values'     => [
                'sample'   => $sample->code,
                'report'   => $report->code,
                'template' => 'legacy',
                'sections' => count($datos['sections'] ?? []),
            ],
            'url'        => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'module'     => 'samples',
        ]);

        $bytes = app(\App\Services\Lab\LegacyReportRenderer::class)
            ->render($sample, $datos, $report->code);

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="informe-clasico-' . $report->code . '.pdf"',
        ]);
    }

    private function render(Request $request, Sample $sample, ?\App\Models\SampleReport $report)
    {
        // Un informe EMITIDO imprime su snapshot, no el estado actual de la
        // muestra: el papel que el cliente tiene en la mano y la reimpresión
        // tienen que decir lo mismo aunque los datos hayan cambiado después.
        // Solo el borrador (y la vista previa sin registro) se calcula en vivo.
        $datos = $report?->frozenPayload() ?? $this->payload->forSample($sample, $report);

        $emitidoEn = now();
        $codigo    = $this->verifyCode($sample, $emitidoEn);

        // El membrete y los firmantes salen del workspace de la MUESTRA, no
        // del usuario que imprime. El informe es del laboratorio que hizo el
        // trabajo: un super sin workspace propio imprimiendo el informe de otro
        // sacaba el papel sin membrete y sin firmas, en silencio.
        $tenant   = $sample->tenant ?? $request->user()?->tenant;
        $firmantes = $this->firmantes($tenant?->id);

        // Que quede constancia de qué se emitió y cuándo. El informe es el
        // producto del laboratorio: ante un reclamo hay que poder decir qué
        // decía el papel que salió y quién lo sacó. El código de verificación
        // se guarda ACÁ y el portal público lo busca contra este registro, así
        // que el papel no puede probar nada que no haya pasado por el sistema.
        AuditLog::create([
            'user_id'        => $request->user()?->id,
            'auditable_type' => Sample::class,
            'auditable_id'   => $sample->id,
            'event'          => 'report_generated',
            'new_values'     => [
                'sample'      => $sample->code,
                // El correlativo del papel, cuando lo hay. Es lo que el cliente
                // cita por teléfono; sin él, la constancia no se puede cruzar
                // con el informe que tiene en la mano.
                'report'      => $report?->code,
                'verify_code' => $codigo,
                'sections'    => count($datos['sections']),
                'notes'       => $datos['notes'],
                // Con la MISMA forma que el informe de transformadores
                // (`title` + `name`): el portal público es uno solo y los
                // recorre igual. Guardarlos como lista de textos rompía esa
                // pantalla al abrir el código de una muestra.
                'signers'     => $firmantes->map(fn ($f) => [
                    'title' => $f->title ?: __('reports.relation.' . $f->relation),
                    'name'  => $f->printedName(),
                ])->values(),
            ],
            'url'        => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'module'     => 'samples',
        ]);

        $pdf = Pdf::loadView('lab_management/reports/test_report', $datos + [
            'generatedAt' => $emitidoEn,
            'generatedBy' => $request->user()?->name,
            'verifyCode'  => $codigo,
            'verifyQr'    => $this->qr(route('report.verify', $codigo)),
            'letterhead'  => [
                'name'       => $tenant?->name,
                'address'    => $tenant?->address,
                'logo'       => $this->logo($tenant?->logo),
                // El sello del organismo que acredita al laboratorio (el ANAB
                // del informe anterior) y el párrafo con su número de
                // certificado. Los dos son DATOS del workspace, no archivos con
                // nombre fijo ni texto dentro de la plantilla: el número vence,
                // el alcance cambia, y otro laboratorio se acredita con otro
                // organismo. Si están vacíos no se dibuja nada — un laboratorio
                // sin acreditar NO puede emitir un papel que insinúe que sí.
                'accreditation_logo' => $this->logo($tenant?->accreditation_logo),
                'accreditation_note' => $tenant?->accreditation_note,
                'disclaimer' => $tenant?->report_disclaimer,
            ],
            'signers'     => $firmantes,
        ])->setPaper('a4');

        $this->numerarPaginas($pdf);

        // El archivo se llama como el papel: el correlativo del informe si lo
        // tiene, y el de la muestra si es la vista previa.
        return $pdf->stream('informe-' . ($report?->code ?? $sample->code) . '.pdf');
    }

    /**
     * "Página 3 de 5" al pie de cada hoja.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ POR QUÉ NO SALE DEL CSS COMO EL RESTO DEL PIE                        │
     * └──────────────────────────────────────────────────────────────────────┘
     * DomPDF resuelve `counter(page)` pero devuelve **0** en `counter(pages)`,
     * así que el papel salía "3 / 0". En un informe de ensayo eso es peor que no
     * numerar: quien recibe tres hojas sueltas no puede saber si le faltan, y el
     * informe se reparte justamente así (una página por ensayo). El lienzo sí
     * conoce el total —sustituye `{PAGE_NUM}` y `{PAGE_COUNT}` al cerrar el
     * documento— y es el mismo camino que ya usa el pie del informe de
     * transformadores.
     *
     * Va DESPUÉS de `render()` a propósito: antes de renderizar todavía no hay
     * lienzo sobre el que dibujar.
     */
    private function numerarPaginas(\Barryvdh\DomPDF\PDF $pdf): void
    {
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $fuente = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');
        $texto  = __('reports.page_of', ['num' => '{PAGE_NUM}', 'total' => '{PAGE_COUNT}']);

        // Centrado sobre el ancho de A4 en puntos (595.28). El ancho del texto
        // se mide con los marcadores puestos, que son más largos que el número
        // final; se usa el del texto ya resuelto con dos dígitos para que el
        // centro no se corra en informes de más de nueve páginas.
        $ancho = $dompdf->getFontMetrics()->getTextWidth(
            __('reports.page_of', ['num' => '00', 'total' => '00']),
            $fuente,
            6.5,
        );

        // La ALTURA importa y ya estuvo mal: a 812 pt el número caía DENTRO del
        // descargo legal —que ocupa el ancho completo del pie— y las dos cosas
        // se leían pisadas. Va a la altura del renglón del código de muestra y
        // del código de verificación (los `bottom: -26px` de la plantilla), que
        // queda por debajo del descargo y libre en el centro.
        $dompdf->getCanvas()->page_text(
            (595.28 - $ancho) / 2,
            827.0,
            $texto,
            $fuente,
            6.5,
            [0.33, 0.33, 0.33],
        );
    }

    /**
     * El código que va impreso y dentro del QR.
     *
     * Doce dígitos hexadecimales derivados de la clave de la aplicación, la
     * muestra y el instante de emisión. No se puede fabricar sin la clave ni
     * adivinar por fuerza bruta (48 bits, y el portal está limitado por
     * throttle), y como lleva el instante, dos emisiones de la misma muestra
     * son dos códigos distintos: se puede saber CUÁL de los papeles que
     * circulan es el que se está mirando.
     */
    private function verifyCode(Sample $sample, \Illuminate\Support\Carbon $momento): string
    {
        $hash = hash_hmac(
            'sha256',
            $sample->id . '|' . $sample->code . '|' . $momento->getTimestampMs(),
            (string) config('app.key'),
        );

        return implode('-', str_split(strtoupper(substr($hash, 0, 12)), 4));
    }

    /**
     * Quiénes firman el informe de este workspace.
     *
     * Es una LISTA DE DATOS (`report_signers`), no dos nombres escritos en la
     * plantilla: cada laboratorio tiene sus cargos y su cadena de revisión, y
     * cambiar quién firma no puede exigir tocar el código.
     */
    private function firmantes(?int $tenantId): \Illuminate\Support\Collection
    {
        // Del módulo FIRMAS. Antes salían de `report_signers`, que se
        // administraba desde una tarjeta escondida dentro de "Mi workspace":
        // el laboratorio mantiene sus firmantes como cualquier otro catálogo.
        return \App\Models\Signature::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with('user:id,name,signature,auto_sign_reports')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(function ($firma) {
                // La imagen viaja ya resuelta a data-URI: dompdf no sale a
                // buscar archivos y el blade no decide de quién es la firma.
                $firma->stamp = $this->logo($firma->imagePath());

                return $firma;
            });
    }

    /**
     * El QR como data-URI. DomPDF no sale a la red a buscar imágenes, así que
     * un `<img src="http://...">` saldría roto en el papel.
     */
    private function qr(string $url): string
    {
        $qr = new \Endroid\QrCode\QrCode($url);
        $png = (new \Endroid\QrCode\Writer\PngWriter())->write($qr);

        return $png->getDataUri();
    }

    /**
     * El logo del membrete, también como data-URI y desde el disco local.
     * Devuelve null si no hay logo cargado: el membrete cae al nombre del
     * laboratorio en texto, que es preferible a un recuadro roto.
     */
    private function logo(?string $ruta): ?string
    {
        if (! $ruta) {
            return null;
        }

        $absoluta = \Illuminate\Support\Facades\Storage::disk('public')->path($ruta);

        if (! is_file($absoluta)) {
            return null;
        }

        $tipo = mime_content_type($absoluta) ?: 'image/png';

        return 'data:' . $tipo . ';base64,' . base64_encode((string) file_get_contents($absoluta));
    }
}
