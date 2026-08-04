<?php

namespace App\Jobs\LabManagement\Worksheets;

use App\Models\Download;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GenerateWorksheetsPdfJob extends BaseWorksheetExportJob
{
    protected string $type      = 'pdf';
    protected string $extension = 'pdf';

    protected function executeExport(Download $download): void
    {
        $consulta = $this->buildQuery();
        $total    = (clone $consulta)->count();
        $columnas = $this->activeColumns();

        $pdf = Pdf::loadView('lab_management.worksheets.pdf.listado', [
            'hojas'          => $consulta->cursor(),
            'columnas'       => $columnas,
            'encabezado'     => fn ($c) => $this->heading($c),
            'celda'          => fn ($h, $c) => $this->cellValue($h, $c),
            'titulo'         => $this->options['title'] ?? __('worksheets.title'),
            'filtros'        => ($this->options['include_filters_summary'] ?? true) ? $this->buildFiltersSummary() : [],
            'generadoPor'    => \App\Models\User::find($this->userId)?->name ?? '—',
            'total'          => $total,
            'tz'             => $this->userTimezone,
        ])
            ->setPaper($this->options['paper_size'] ?? 'a4', $this->options['orientation'] ?? 'landscape')
            ->setOptions([
                // dompdf sin fuentes instaladas solo conoce las básicas.
                'defaultFont'          => 'Helvetica',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
                'dpi'                  => 110,
            ]);

        $ruta = 'downloads/' . $download->filename;
        Storage::disk($download->disk)->put($ruta, $pdf->output());

        $download->update(['path' => $ruta, 'status' => 'ready']);
    }
}
