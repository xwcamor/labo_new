<?php

namespace App\Jobs\LabManagement\Worksheets;

use App\Exports\LabManagement\Worksheets\WorksheetsExport;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class GenerateWorksheetsExcelJob extends BaseWorksheetExportJob
{
    protected string $type      = 'excel';
    protected string $extension = 'xlsx';

    protected function executeExport(Download $download): void
    {
        $consulta = $this->buildQuery();
        $total    = (clone $consulta)->count();

        $contenido = Excel::raw(
            new WorksheetsExport(
                hojas:      $consulta->cursor(),
                columnas:   $this->activeColumns(),
                encabezado: fn ($c) => $this->heading($c),
                celda:      fn ($h, $c) => $this->cellValue($h, $c),
                options:    $this->options,
                count:      $total,
                tz:         $this->userTimezone,
            ),
            ExcelFormat::XLSX,
        );

        $ruta = 'downloads/' . $download->filename;
        Storage::disk($download->disk)->put($ruta, $contenido);

        $download->update(['path' => $ruta, 'status' => 'ready']);
    }
}
