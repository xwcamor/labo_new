<?php

namespace App\Jobs\BusinessManagement\Analytes;

use App\Exports\BusinessManagement\Analytes\AnalytesWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateAnalytesWordJob extends BaseAnalyteExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $query     = $this->buildQuery();
        $count     = (clone $query)->count();
        $analytes = $query->cursor();
        $tempFile  = tempnam(sys_get_temp_dir(), 'analytes_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new AnalytesWord())->generate(
            analytes:      $analytes,
            filename:       $tempFile,
            options:        $opts,
            filtersSummary: $this->buildFiltersSummary(),
            generatedBy:    optional(\App\Models\User::find($this->userId))->name ?? '—',
            count:          $count,
        );

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        $path = 'downloads/' . $download->filename;
        Storage::disk($download->disk)->put($path, $content);

        $download->update(['path' => $path, 'status' => 'ready']);
    }
}
