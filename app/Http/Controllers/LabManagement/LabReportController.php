<?php

namespace App\Http\Controllers\LabManagement;

use App\Exports\LabManagement\LabReports\BaseLabReportExport;
use App\Exports\LabManagement\LabReports\DeliveredReportsExport;
use App\Exports\LabManagement\LabReports\LabAnalysisExport;
use App\Exports\LabManagement\LabReports\OtdReportExport;
use App\Exports\LabManagement\LabReports\ReceptionRegisterExport;
use App\Exports\LabManagement\LabReports\ReportsListExport;
use App\Exports\LabManagement\LabReports\SamplesDetailedExport;
use App\Exports\LabManagement\LabReports\SamplesFlatExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * El menú "Reportes de Lab.": los 7 Excel del sistema antiguo.
 *
 * Una sola pantalla con los 7 reportes y su rango de fecha de recepción; cada
 * descarga es un GET síncrono que devuelve el XLSX (el patrón de
 * `ReceptionController::export`). En el viejo eran 7 ítems de menú con la
 * misma pantalla de filtros repetida 7 veces (y tres compartían hasta el
 * nombre del archivo descargado).
 *
 * El "desde" por omisión es el del viejo: inicio del mes de hace 3 meses.
 */
class LabReportController extends Controller
{
    /** ruta => [clase export, prefijo del nombre de archivo] */
    private const REPORTES = [
        'otd'     => [OtdReportExport::class, 'Reporte_OTD'],
        'rlabs'   => [LabAnalysisExport::class, 'Analisis_de_laboratorio'],
        'rems'    => [SamplesDetailedExport::class, 'Registro_de_muestras_detallado'],
        'fims'    => [ReceptionRegisterExport::class, 'Formato_registro_ingreso_muestras'],
        'jobs'    => [SamplesFlatExport::class, 'Registro_de_muestras'],
        'ents'    => [DeliveredReportsExport::class, 'Reportes_entregados'],
        'listado' => [ReportsListExport::class, 'Listado_de_reportes'],
    ];

    public function index(): Response
    {
        return Inertia::render('LabReports/Index', [
            'reports'     => array_keys(self::REPORTES),
            'defaultFrom' => now()->subMonths(3)->startOfMonth()->toDateString(),
        ]);
    }

    public function download(Request $request, string $report): BinaryFileResponse
    {
        abort_unless(isset(self::REPORTES[$report]), 404);

        $datos = $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        [$clase, $prefijo] = self::REPORTES[$report];

        /** @var BaseLabReportExport $export */
        $export = new $clase($datos['from'], $datos['to'] ?? null);

        return Excel::download($export, $prefijo . '_' . now()->format('d_m_Y') . '.xlsx');
    }
}
