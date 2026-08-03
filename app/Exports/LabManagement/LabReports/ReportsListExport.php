<?php

namespace App\Exports\LabManagement\LabReports;

use App\Models\SampleReport;

/**
 * Listado de Reportes: una fila por informe principal, en cualquier estado.
 *
 * Es el `im_management/reports` del viejo: el inventario de informes con su
 * número, muestra, cliente y fechas. El viejo solo listaba los principales
 * (`type_report = 0`) y eso se conserva; su Estado era "Entregado"(=firmado) /
 * "Generado" — acá se traduce al ciclo real: Borrador / Emitido / Entregado.
 */
class ReportsListExport extends BaseLabReportExport
{
    public function title(): string
    {
        return __('lab_reports.listado.sheet');
    }

    protected function buildRows(): array
    {
        $filas = [[
            __('lab_reports.report_number'),
            __('lab_reports.sample_code'),
            __('lab_reports.service_order'),
            __('lab_reports.customer'),
            __('lab_reports.date_rec'),
            __('lab_reports.date_delivered'),
            __('lab_reports.reason'),
            __('lab_reports.status'),
        ]];

        $informes = SampleReport::query()
            ->where('kind', SampleReport::KIND_PRIMARY)
            ->with([
                'sample:id,code,reception_id,sampling_reason',
                'sample.reception:id,received_at,service_order,customer_id',
                'sample.reception.customer:id,name',
            ])
            ->whereHas('sample.reception', fn ($q) => $this->betweenDates($q, 'received_at'))
            ->orderByDesc('id')
            ->get();

        foreach ($informes as $informe) {
            $muestra = $informe->sample;

            $filas[] = [
                $informe->code,
                $muestra?->code ?? '',
                $muestra?->reception?->service_order ?: __('lab_reports.pending'),
                $muestra?->reception?->customer?->name ?? '',
                $muestra?->reception?->received_at?->format('d-m-Y') ?? '-',
                $informe->delivered_at?->format('d-m-Y') ?? '-',
                $muestra?->sampling_reason ?? '-',
                $this->estado($informe),
            ];
        }

        return $filas;
    }

    private function estado(SampleReport $informe): string
    {
        if ($informe->status !== SampleReport::STATUS_ISSUED) {
            return __('lab_reports.state_draft');
        }

        return $informe->delivered_at
            ? __('lab_reports.state_delivered')
            : __('lab_reports.state_issued');
    }
}
