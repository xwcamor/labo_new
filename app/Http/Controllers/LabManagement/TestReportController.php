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
     * El informe en PDF de una muestra.
     */
    public function pdf(Request $request, Sample $sample)
    {
        $datos = $this->payload->forSample($sample);

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
                'verify_code' => $codigo,
                'sections'    => count($datos['sections']),
                'notes'       => $datos['notes'],
                // Con la MISMA forma que el informe de transformadores
                // (`title` + `name`): el portal público es uno solo y los
                // recorre igual. Guardarlos como lista de textos rompía esa
                // pantalla al abrir el código de una muestra.
                'signers'     => $firmantes->map(fn ($f) => [
                    'title' => $f->title ?: __('reports.relation.' . $f->relation),
                    'name'  => $f->user?->name ?? $f->name,
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
                'disclaimer' => $tenant?->report_disclaimer,
            ],
            'signers'     => $firmantes,
        ])->setPaper('a4');

        return $pdf->stream('informe-' . $sample->code . '.pdf');
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
        return \App\Models\ReportSigner::query()
            ->where('tenant_id', $tenantId)
            ->with('user:id,name')
            ->orderBy('sort_order')
            ->get();
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
