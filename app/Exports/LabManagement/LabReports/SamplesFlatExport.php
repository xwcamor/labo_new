<?php

namespace App\Exports\LabManagement\LabReports;

use App\Models\Sample;
use App\Models\SampleReport;
use App\Models\SampleTest;
use Illuminate\Support\Facades\DB;

/**
 * Registro de Muestras: una fila por muestra, con su avance en SI/NO.
 *
 * Es el `jobs` del viejo: la lista operativa plana ("qué falta asignar,
 * ensayar y reportar"), apta para filtrar en Excel. La versión ficha por
 * recepción es el "Registro de Muestras Detallado" (`SamplesDetailedExport`).
 *
 * Corrección deliberada: la columna que el viejo titulaba "FECHA DE MUESTREO"
 * mostraba en realidad la fecha de ENSAYO en el laboratorio (labs.date_rehearsal).
 * Acá se titula "Fecha de ensayo" — y la de muestreo real, que el nuevo sí
 * captura, va en su propia columna.
 */
class SamplesFlatExport extends BaseLabReportExport
{
    public function title(): string
    {
        return __('lab_reports.jobs.sheet');
    }

    protected function buildRows(): array
    {
        $filas = [[
            __('lab_reports.jobs.date_rec'),
            __('lab_reports.service_order'),
            __('lab_reports.customer'),
            __('lab_reports.serial'),
            __('lab_reports.sample_code'),
            __('lab_reports.jobs.sampled_at'),
            __('lab_reports.jobs.tests'),
            __('lab_reports.jobs.run_dates'),
            __('lab_reports.jobs.has_equipment'),
            __('lab_reports.jobs.has_tests'),
            __('lab_reports.jobs.has_values'),
            __('lab_reports.jobs.has_report'),
            __('lab_reports.jobs.priority'),
            __('lab_reports.jobs.date_due'),
        ]];

        $muestras = Sample::query()
            ->with([
                'reception:id,received_at,due_at,service_order,customer_id',
                'reception.customer:id,name',
                'equipment:id,serial',
            ])
            ->whereHas('reception', fn ($q) => $this->betweenDates($q, 'received_at'))
            ->orderByDesc('year')->orderByDesc('number')
            ->get();

        $pruebas = SampleTest::query()
            ->whereIn('sample_id', $muestras->pluck('id'))
            ->where('status', '!=', SampleTest::STATUS_CANCELLED)
            ->with('definition:id,name')
            ->get()
            ->groupBy('sample_id');

        $fechasEnsayo = $this->runDates($muestras->pluck('id')->all());

        $conInforme = SampleReport::query()
            ->whereIn('sample_id', $muestras->pluck('id'))
            ->where('kind', SampleReport::KIND_PRIMARY)
            ->distinct()->pluck('sample_id')->flip();

        $fila = 1;

        foreach ($muestras as $muestra) {
            $fila++;
            $suyas = $pruebas[$muestra->id] ?? collect();

            $completas = $suyas->isNotEmpty() && $suyas->every(fn ($t) => in_array(
                $t->status,
                [SampleTest::STATUS_VALIDATED, SampleTest::STATUS_REPORTED],
                true,
            ));

            $urgente = $muestra->is_urgent || $muestra->reception?->is_urgent;

            if ($urgente) {
                $this->fills[] = [$fila, 13, self::FILL_BAD];
            }

            $filas[] = [
                $muestra->reception?->received_at?->format('d-m-Y H:i') ?? '-',
                $muestra->reception?->service_order ?: __('lab_reports.pending'),
                $muestra->reception?->customer?->name ?? '',
                $muestra->equipment?->serial ?? __('lab_reports.jobs.no_equipment'),
                $muestra->code,
                $muestra->sampled_at?->format('d-m-Y') ?? '-',
                $suyas->map(fn ($t) => $t->definition?->name)->filter()->implode("\n"),
                $suyas->map(fn ($t) => $fechasEnsayo[$t->id] ?? __('lab_reports.no'))->implode("\n"),
                $this->siNo($muestra->equipment_id !== null),
                $this->siNo($suyas->isNotEmpty()),
                $this->siNo($completas),
                $this->siNo($conInforme->has($muestra->id)),
                $urgente ? __('lab_reports.jobs.priority_high') : __('lab_reports.jobs.priority_normal'),
                $muestra->reception?->due_at?->format('d-m-Y') ?? '',
            ];
        }

        return $filas;
    }

    /** Fecha de corrida de la hoja de trabajo que respalda cada prueba. */
    private function runDates(array $sampleIds): array
    {
        return DB::table('sample_tests as st')
            ->join('worksheet_rows as wr', 'wr.id', '=', 'st.worksheet_row_id')
            ->join('worksheets as w', 'w.id', '=', 'wr.worksheet_id')
            ->whereIn('st.sample_id', $sampleIds)
            ->whereNull('wr.deleted_at')
            ->pluck('w.run_date', 'st.id')
            ->map(fn ($d) => $d ? date('d-m-Y', strtotime($d)) : null)
            ->all();
    }
}
