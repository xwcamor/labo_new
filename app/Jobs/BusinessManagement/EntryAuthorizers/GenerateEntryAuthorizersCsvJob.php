<?php

namespace App\Jobs\BusinessManagement\EntryAuthorizers;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

/**
 * Export streaming a CSV. A diferencia de Excel/PDF/Word (cargan en memoria),
 * este job escribe fila por fila con `fputcsv` y `chunkById(1000)`. Soporta
 * cualquier volumen sin OOM-ear.
 */
class GenerateEntryAuthorizersCsvJob extends BaseEntryAuthorizerExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columns = $this->options['columns'] ?? ['id', 'name', 'code', 'is_active', 'created_at'];

        $tempFile = tempnam(sys_get_temp_dir(), 'entry_authorizers_csv') . '.csv';
        $handle   = fopen($tempFile, 'w');

        // try/finally garantiza cleanup del tempfile incluso si una excepcion
        // ocurre durante el chunk loop (OOM, disk lleno, etc.).
        try {
            // BOM para que Excel detecte UTF-8 al abrir.
            fwrite($handle, "\xEF\xBB\xBF");

            $headings = [
                'id'         => __('entry_authorizers.id'),
                'name'       => __('entry_authorizers.name'),
                'code'       => __('entry_authorizers.code'),
                'sort_order' => __('entry_authorizers.sort_order'),
                'is_active'  => __('entry_authorizers.is_active'),
                'slug'       => 'Slug',
                'created_at' => __('global.created_at'),
                'updated_at' => __('global.updated_at'),
                'creator'    => __('global.created_by'),
            ];
            fputcsv($handle, array_map(fn ($k) => $headings[$k] ?? $k, $columns));

            $tz = $this->userTimezone;
            // chunkById usa cursor (WHERE id > X), constante en memoria.
            $this->buildQuery()->chunkById(1000, function ($entry_authorizers) use ($handle, $columns, $tz) {
                foreach ($entry_authorizers as $entryAuthorizer) {
                    $row = array_map(fn ($col) => match ($col) {
                        'id'         => $entryAuthorizer->id,
                        'name'       => $entryAuthorizer->name,
                        'code'       => $entryAuthorizer->code ?? '',
                        'sort_order' => $entryAuthorizer->sort_order ?? '',
                        'is_active'  => $entryAuthorizer->is_active ? '1' : '0',
                        'slug'       => $entryAuthorizer->slug,
                        'created_at' => $entryAuthorizer->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'updated_at' => $entryAuthorizer->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'creator'    => $entryAuthorizer->creator?->name ?? '',
                        default      => $entryAuthorizer->{$col} ?? '',
                    }, $columns);
                    fputcsv($handle, $row);
                }
            }, 'entry_authorizers.id', 'id');

            fclose($handle);
            $handle = null;

            $content = file_get_contents($tempFile);
            $path    = 'downloads/' . $download->filename;

            // Storage::put + Download update en transaccion para no dejar
            // un Download `ready` apuntando a un path inexistente.
            \DB::transaction(function () use ($download, $path, $content) {
                Storage::disk($download->disk)->put($path, $content);
                $download->update(['path' => $path, 'status' => 'ready']);
            });
        } finally {
            if (is_resource($handle)) @fclose($handle);
            if (file_exists($tempFile)) @unlink($tempFile);
        }
    }
}
