<?php

namespace App\Exports\LabManagement\LabReports;

use App\Models\SampleReport;

/**
 * Reporte OTD (On Time Delivery): una fila por informe, con los tres plazos.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL CÁLCULO — Y POR QUÉ HAY UNA SOLA VERSIÓN                              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El viejo tenía CUATRO definiciones de "OTD" conviviendo (la pantalla restaba
 * emisión−entrega, el XLSX visible restaba entrega−recepción y su semáforo solo
 * comparaba emisión vs entrega, y un XLS huérfano sin botón usaba los métodos
 * del modelo). Acá se implementa UNA: la del modelo viejo (su export más
 * completo), en días calendario:
 *
 *   OTD               = entrega − recepción   (aceptable ≤ 5 días)
 *   Tiempo para emitir = emisión − recepción  (aceptable ≤ 2 días)
 *   Tiempo de entrega  = entrega − emisión    (aceptable ≤ 3 días)
 *
 * Los umbrales 5/2/3 son los del sistema viejo (constantes de su RemReport).
 * Una fecha faltante deja la celda en "-" y el cálculo "sin dato" — el estilo
 * del viejo reventaba con 500 ante un NULL; eso no se copia.
 */
class OtdReportExport extends BaseLabReportExport
{
    public const ACCEPTABLE_OTD_DAYS      = 5;
    public const ACCEPTABLE_ISSUE_DAYS    = 2;
    public const ACCEPTABLE_DELIVERY_DAYS = 3;

    public function title(): string
    {
        return __('lab_reports.otd.sheet');
    }

    protected function buildRows(): array
    {
        $filas = [[
            __('lab_reports.otd.date_rec'),
            __('lab_reports.otd.date_issued'),
            __('lab_reports.otd.date_delivered'),
            __('lab_reports.otd.otd_days'),
            __('lab_reports.otd.otd_ok', ['max' => self::ACCEPTABLE_OTD_DAYS]),
            __('lab_reports.otd.issue_days'),
            __('lab_reports.otd.issue_ok', ['max' => self::ACCEPTABLE_ISSUE_DAYS]),
            __('lab_reports.otd.delivery_days'),
            __('lab_reports.otd.delivery_ok', ['max' => self::ACCEPTABLE_DELIVERY_DAYS]),
            __('lab_reports.service_order'),
            __('lab_reports.customer'),
            __('lab_reports.sample_code'),
        ]];

        $informes = SampleReport::query()
            ->with(['sample:id,code,reception_id', 'sample.reception:id,received_at,service_order,customer_id', 'sample.reception.customer:id,name'])
            ->whereHas('sample.reception', fn ($q) => $this->betweenDates($q, 'received_at'))
            ->get()
            ->sortByDesc(fn ($i) => $i->sample?->reception?->received_at);

        $fila = 1;

        foreach ($informes as $informe) {
            $fila++;
            $recepcion = $informe->sample?->reception;

            $rec = $recepcion?->received_at?->startOfDay();
            $emi = $informe->issued_at?->startOfDay();
            $ent = $informe->delivered_at?->startOfDay();

            $otd     = ($rec && $ent) ? (int) $rec->diffInDays($ent, false) : null;
            $emision = ($rec && $emi) ? (int) $rec->diffInDays($emi, false) : null;
            $entrega = ($emi && $ent) ? (int) $emi->diffInDays($ent, false) : null;

            $filas[] = [
                $rec?->format('d/m/Y') ?? '-',
                $emi?->format('d/m/Y') ?? '-',
                $ent?->format('d/m/Y') ?? '-',
                $otd ?? '-',
                $this->flag($fila, 5, $otd, self::ACCEPTABLE_OTD_DAYS),
                $emision ?? '-',
                $this->flag($fila, 7, $emision, self::ACCEPTABLE_ISSUE_DAYS),
                $entrega ?? '-',
                $this->flag($fila, 9, $entrega, self::ACCEPTABLE_DELIVERY_DAYS),
                $recepcion?->service_order ?: __('lab_reports.pending'),
                $recepcion?->customer?->name ?? '',
                $informe->sample?->code ?? '',
            ];
        }

        return $filas;
    }

    /** Sí/No contra el umbral, pintando la celda (verde/rojo) como el viejo. */
    private function flag(int $fila, int $col, ?int $dias, int $max): string
    {
        if ($dias === null) {
            return '-';
        }

        $ok = $dias <= $max;
        $this->fills[] = [$fila, $col, $ok ? self::FILL_OK : self::FILL_BAD];

        return $this->siNo($ok);
    }
}
