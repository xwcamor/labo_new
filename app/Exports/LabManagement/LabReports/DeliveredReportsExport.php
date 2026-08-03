<?php

namespace App\Exports\LabManagement\LabReports;

use App\Models\SampleReport;

/**
 * Reportes Entregados: una fila por informe principal emitido.
 *
 * En el viejo (`ents`) "Entregado" era `state = 0`, que se seteaba al asignarle
 * FIRMA al informe — no al entregarlo—, y su columna ESTADO siempre decía
 * "ENTREGADO" por el propio filtro. Acá el criterio es el equivalente real del
 * sistema nuevo: informes principales EMITIDOS; la columna Estado distingue si
 * además tienen fecha de entrega. La pantalla del viejo mostraba otra cosa
 * (recepciones) — esa divergencia no se copia.
 */
class DeliveredReportsExport extends BaseLabReportExport
{
    public function title(): string
    {
        return __('lab_reports.ents.sheet');
    }

    protected function buildRows(): array
    {
        $filas = [[
            __('lab_reports.report_number'),
            __('lab_reports.sample_code'),
            __('lab_reports.service_order'),
            __('lab_reports.customer'),
            __('lab_reports.serial'),
            __('lab_reports.date_rec'),
            __('lab_reports.date_delivered'),
            __('lab_reports.reason'),
            __('lab_reports.status'),
        ]];

        $informes = SampleReport::query()
            ->where('kind', SampleReport::KIND_PRIMARY)
            ->where('status', SampleReport::STATUS_ISSUED)
            ->with([
                'sample:id,code,reception_id,equipment_id,sampling_reason',
                'sample.reception:id,received_at,service_order,customer_id',
                'sample.reception.customer:id,name',
                'sample.equipment:id,serial',
            ])
            ->whereHas('sample.reception', fn ($q) => $this->betweenDates($q, 'received_at'))
            ->get()
            ->sortByDesc(fn ($i) => $i->sample?->reception?->received_at);

        foreach ($informes as $informe) {
            $muestra = $informe->sample;

            $filas[] = [
                $informe->code,
                $muestra?->code ?? '',
                $muestra?->reception?->service_order ?: __('lab_reports.pending'),
                $muestra?->reception?->customer?->name ?? '',
                $muestra?->equipment?->serial ?? '-',
                $muestra?->reception?->received_at?->format('d-m-Y') ?? '-',
                $informe->delivered_at?->format('d-m-Y') ?? '-',
                $muestra?->sampling_reason ?? '-',
                $informe->delivered_at
                    ? __('lab_reports.state_delivered')
                    : __('lab_reports.state_issued'),
            ];
        }

        return $filas;
    }
}
