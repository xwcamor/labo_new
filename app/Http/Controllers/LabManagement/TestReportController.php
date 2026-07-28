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

        // Que quede constancia de qué se emitió y cuándo. El informe es el
        // producto del laboratorio: ante un reclamo hay que poder decir qué
        // decía el papel que salió y quién lo sacó.
        AuditLog::create([
            'user_id'        => $request->user()?->id,
            'auditable_type' => Sample::class,
            'auditable_id'   => $sample->id,
            'event'          => 'report_generated',
            'new_values'     => [
                'sample'   => $sample->code,
                'sections' => count($datos['sections']),
                'notes'    => $datos['notes'],
            ],
            'url'        => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'module'     => 'samples',
        ]);

        $pdf = Pdf::loadView('lab_management/reports/test_report', $datos + [
            'generatedAt' => now(),
            'generatedBy' => $request->user()?->name,
        ])->setPaper('a4');

        return $pdf->stream('informe-' . $sample->code . '.pdf');
    }
}
