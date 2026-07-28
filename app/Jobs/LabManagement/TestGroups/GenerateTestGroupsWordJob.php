<?php

namespace App\Jobs\LabManagement\TestGroups;

use App\Exports\LabManagement\TestGroups\TestGroupsWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateTestGroupsWordJob extends BaseTestGroupExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $query     = $this->buildQuery();
        $count     = (clone $query)->count();
        $test_groups = $query->cursor();
        $tempFile  = tempnam(sys_get_temp_dir(), 'test_groups_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new TestGroupsWord())->generate(
            test_groups:      $test_groups,
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
