<?php

namespace App\Jobs\LabManagement\Worksheets;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

/**
 * CSV por lotes: escribe fila por fila con `chunkById`, así el consumo de
 * memoria no depende de cuántas hojas haya. Los otros tres formatos arman el
 * documento entero antes de guardarlo, y por eso llevan tope por plan.
 */
class GenerateWorksheetsCsvJob extends BaseWorksheetExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columnas = $this->activeColumns();

        $temporal = tempnam(sys_get_temp_dir(), 'worksheets_csv') . '.csv';
        $archivo  = fopen($temporal, 'w');

        try {
            // La marca de orden de bytes: sin ella Excel abre el CSV en
            // latin-1 y "Número Ácido" llega roto.
            fwrite($archivo, "\xEF\xBB\xBF");

            fputcsv($archivo, array_map(fn ($c) => $this->heading($c), $columnas));

            $this->buildQuery()->chunkById(500, function ($hojas) use ($archivo, $columnas) {
                foreach ($hojas as $hoja) {
                    fputcsv($archivo, array_map(fn ($c) => $this->cellValue($hoja, $c), $columnas));
                }
            }, 'worksheets.id', 'id');

            fclose($archivo);
            $archivo = null;

            $ruta = 'downloads/' . $download->filename;

            // Guardar el archivo y marcar la descarga como lista, juntos: si se
            // separan, un fallo entre medio deja una descarga "lista" que
            // apunta a un archivo que no existe.
            \DB::transaction(function () use ($download, $ruta, $temporal) {
                Storage::disk($download->disk)->put($ruta, file_get_contents($temporal));
                $download->update(['path' => $ruta, 'status' => 'ready']);
            });
        } finally {
            if (is_resource($archivo)) {
                @fclose($archivo);
            }
            if (file_exists($temporal)) {
                @unlink($temporal);
            }
        }
    }
}
