<?php

namespace App\Jobs\LabManagement\Worksheets;

use App\Exports\LabManagement\Worksheets\WorksheetsWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateWorksheetsWordJob extends BaseWorksheetExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $consulta = $this->buildQuery();
        $total    = (clone $consulta)->count();

        // `tempnam` CREA el archivo: si no se borra al final quedan huérfanos
        // de cero bytes en el temporal del sistema.
        $temporal = tempnam(sys_get_temp_dir(), 'worksheets_word') . '.docx';

        try {
            (new WorksheetsWord())->generate(
                hojas:          $consulta->cursor(),
                columnas:       $this->activeColumns(),
                encabezado:     fn ($c) => $this->heading($c),
                celda:          fn ($h, $c) => $this->cellValue($h, $c),
                filename:       $temporal,
                options:        $this->options + ['timezone' => $this->userTimezone],
                filtersSummary: $this->buildFiltersSummary(),
                generatedBy:    \App\Models\User::find($this->userId)?->name ?? '—',
                count:          $total,
            );

            $ruta = 'downloads/' . $download->filename;
            Storage::disk($download->disk)->put($ruta, file_get_contents($temporal));

            $download->update(['path' => $ruta, 'status' => 'ready']);
        } finally {
            if (file_exists($temporal)) {
                @unlink($temporal);
            }
            // La semilla que dejó tempnam(), sin la extensión.
            $semilla = substr($temporal, 0, -5);
            if (file_exists($semilla)) {
                @unlink($semilla);
            }
        }
    }
}
