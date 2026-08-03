<?php

namespace App\Exports\LabManagement\LabReports;

use App\Models\Reception;
use App\Models\SampleReport;
use App\Models\SampleTest;
use Illuminate\Support\Facades\DB;

/**
 * Registro de Muestras Detallado: una FICHA por recepción.
 *
 * Es el `rems` del viejo — el facsímil del acta de ingreso: por cada recepción,
 * un bloque numerado con (1) los datos del ingreso, (2) el listado de sus
 * muestras con el avance y (3) el listado de informes emitidos, si los hay.
 * La versión plana (una fila por muestra) es el "Registro de Muestras"
 * (`SamplesFlatExport`); en el viejo eran el mismo controlador con distinta
 * plantilla.
 *
 * Diferencias deliberadas con el viejo: no se copian los 15 contadores de
 * envases por prueba en la cabecera (el desglose por prueba vive en el
 * "Formato de Registro de Ingreso" y las pruebas reales de cada muestra están
 * en la tabla 2), ni la celda de firma que traía pegado un ejemplo de
 * W3Schools ("img_girl.jpg") — el autorizador sale con su nombre.
 */
class SamplesDetailedExport extends BaseLabReportExport
{
    protected bool $globalBorders = false;

    public function title(): string
    {
        return __('lab_reports.rems.sheet');
    }

    protected function headerRowCount(): int
    {
        return 0;
    }

    protected function buildRows(): array
    {
        $filas = [];

        $recepciones = $this->betweenDates(
            Reception::query()->with([
                'customer:id,name',
                'sampler:id,name',
                'authorizer:id,name',
                'samples' => fn ($q) => $q->orderBy('number'),
                'samples.equipment:id,serial',
            ]),
            'received_at',
        )->orderByDesc('received_at')->get();

        $ids = $recepciones->pluck('samples')->flatten()->pluck('id')->all();

        $pruebas = SampleTest::query()
            ->whereIn('sample_id', $ids)
            ->where('status', '!=', SampleTest::STATUS_CANCELLED)
            ->with('definition:id,name')
            ->get()
            ->groupBy('sample_id');

        $fechasEnsayo = DB::table('sample_tests as st')
            ->join('worksheet_rows as wr', 'wr.id', '=', 'st.worksheet_row_id')
            ->join('worksheets as w', 'w.id', '=', 'wr.worksheet_id')
            ->whereIn('st.sample_id', $ids)
            ->whereNull('wr.deleted_at')
            ->pluck('w.run_date', 'st.id')
            ->map(fn ($d) => $d ? date('d-m-Y', strtotime($d)) : null)
            ->all();

        $informes = SampleReport::query()
            ->whereIn('sample_id', $ids)
            ->with('sample:id,code,equipment_id,sampling_reason', 'sample.equipment:id,serial')
            ->get()
            ->groupBy('sample_id');

        foreach ($recepciones as $n => $recepcion) {
            if ($filas !== []) {
                $filas[] = [''];
            }

            // ── 1. Datos del ingreso ─────────────────────────────────────
            $this->titulo($filas, ($n + 1) . '. ' . __('lab_reports.rems.block_title'));
            $this->cabecera($filas, [
                __('lab_reports.fims.date_rec'),
                __('lab_reports.fims.sampled_by'),
                __('lab_reports.service_order'),
                __('lab_reports.customer'),
                __('lab_reports.fims.packages'),
                __('lab_reports.fims.container_ok'),
                __('lab_reports.fims.volume_ok'),
                __('lab_reports.fims.label_ok'),
                __('lab_reports.fims.notes'),
                __('lab_reports.fims.authorized_by'),
            ]);

            $filas[] = [
                $recepcion->received_at?->format('d-m-Y H:i') ?? '-',
                $recepcion->samplerLabel() ?? '-',
                $recepcion->service_order ?: __('lab_reports.pending'),
                $recepcion->customer?->name ?? '',
                $recepcion->packages,
                $this->siNo($recepcion->container_ok),
                $this->siNo($recepcion->volume_ok),
                $this->siNo($recepcion->label_ok),
                $recepcion->notes,
                $recepcion->authorizer?->name ?? '-',
            ];

            // ── 2. Listado de muestras ───────────────────────────────────
            $this->titulo($filas, __('lab_reports.rems.samples_title'));
            $this->cabecera($filas, [
                __('lab_reports.serial'),
                __('lab_reports.sample_code'),
                __('lab_reports.rems.entered_at'),
                __('lab_reports.jobs.tests'),
                __('lab_reports.jobs.run_dates'),
                __('lab_reports.jobs.has_equipment'),
                __('lab_reports.jobs.has_tests'),
                __('lab_reports.jobs.has_values'),
                __('lab_reports.jobs.has_report'),
                __('lab_reports.jobs.priority'),
                __('lab_reports.jobs.date_due'),
            ]);

            foreach ($recepcion->samples as $muestra) {
                $suyas = $pruebas[$muestra->id] ?? collect();
                $completas = $suyas->isNotEmpty() && $suyas->every(fn ($t) => in_array(
                    $t->status,
                    [SampleTest::STATUS_VALIDATED, SampleTest::STATUS_REPORTED],
                    true,
                ));
                $principal = ($informes[$muestra->id] ?? collect())
                    ->contains(fn ($i) => $i->kind === SampleReport::KIND_PRIMARY);

                $filas[] = [
                    $muestra->equipment?->serial ?? __('lab_reports.jobs.no_equipment'),
                    $muestra->code,
                    $muestra->created_at?->format('d-m-Y') ?? '-',
                    $suyas->map(fn ($t) => $t->definition?->name)->filter()->implode("\n"),
                    $suyas->map(fn ($t) => $fechasEnsayo[$t->id] ?? __('lab_reports.no'))->implode("\n"),
                    $this->siNo($muestra->equipment_id !== null),
                    $this->siNo($suyas->isNotEmpty()),
                    $this->siNo($completas),
                    $this->siNo($principal),
                    ($muestra->is_urgent || $recepcion->is_urgent)
                        ? __('lab_reports.jobs.priority_high')
                        : __('lab_reports.jobs.priority_normal'),
                    $recepcion->due_at?->format('d-m-Y') ?? '',
                ];
            }

            // ── 3. Listado de informes (solo si hay) ─────────────────────
            $deLaRecepcion = $recepcion->samples
                ->flatMap(fn ($m) => $informes[$m->id] ?? collect());

            if ($deLaRecepcion->isNotEmpty()) {
                $this->titulo($filas, __('lab_reports.rems.reports_title'));
                $this->cabecera($filas, [
                    __('lab_reports.report_number'),
                    __('lab_reports.sample_code'),
                    __('lab_reports.service_order'),
                    __('lab_reports.customer'),
                    __('lab_reports.serial'),
                    __('lab_reports.date_rec'),
                    __('lab_reports.date_delivered'),
                    __('lab_reports.reason'),
                    __('lab_reports.status'),
                ]);

                foreach ($deLaRecepcion as $informe) {
                    $filas[] = [
                        $informe->code,
                        $informe->sample?->code ?? '',
                        $recepcion->service_order ?: __('lab_reports.pending'),
                        $recepcion->customer?->name ?? '',
                        $informe->sample?->equipment?->serial ?? '-',
                        $recepcion->received_at?->format('d-m-Y') ?? '-',
                        $informe->delivered_at?->format('d-m-Y') ?? '-',
                        $informe->sample?->sampling_reason ?? '-',
                        $informe->status === SampleReport::STATUS_ISSUED
                            ? ($informe->delivered_at ? __('lab_reports.state_delivered') : __('lab_reports.state_issued'))
                            : __('lab_reports.state_draft'),
                    ];
                }
            }
        }

        return $filas === [] ? [[__('lab_reports.no_records')]] : $filas;
    }

    private function titulo(array &$filas, string $texto): void
    {
        $filas[] = [$texto];
        $this->titleRows[] = count($filas);
    }

    private function cabecera(array &$filas, array $celdas): void
    {
        $filas[] = $celdas;
        $this->headerBands[] = [count($filas), count($filas), count($celdas)];
    }
}
