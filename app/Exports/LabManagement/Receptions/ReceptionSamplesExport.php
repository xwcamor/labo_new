<?php

namespace App\Exports\LabManagement\Receptions;

use App\Models\Reception;
use App\Services\Lab\SampleProgressService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Las muestras de una entrega, en Excel.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ PREGUNTA CONTESTA                                                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * "¿En qué van mis muestras?". El sistema anterior tenía esta descarga y es lo
 * que el laboratorio adjunta a un correo cuando el cliente llama. Sin ella, la
 * respuesta era una captura de pantalla.
 *
 * Va con el AVANCE ya resuelto —cuántas pruebas se pidieron, cuántas están
 * validadas, cuántas informadas y qué falta— porque el que abre la planilla no
 * puede recontar nada: si la columna no lo dice, no está.
 *
 * El avance sale de UNA consulta agregada (`SampleProgressService`), la misma que
 * usa la pantalla. Se pasa por constructor y no se recalcula por fila: con las
 * 40 muestras de una entrega grande, un conteo por fila son 160 consultas para
 * producir un archivo.
 */
class ReceptionSamplesExport implements FromArray, WithEvents
{
    /** @var array<int,array<string,mixed>> */
    private array $avance;

    public function __construct(private Reception $reception)
    {
        $this->avance = app(SampleProgressService::class)
            ->receptionBreakdown($reception->id);
    }

    /** @return array<int,array<int,mixed>> */
    public function array(): array
    {
        $filas = [[
            __('receptions.sample_code'),
            __('receptions.equipment'),
            __('equipment.tag'),
            __('receptions.oil_type'),
            __('receptions.sampled_at'),
            __('receptions.sampling_point'),
            __('receptions.requested_tests'),
            __('receptions.test_status_validated'),
            __('receptions.test_status_reported'),
            __('receptions.progress'),
        ]];

        $muestras = $this->reception->samples()
            ->with(['equipment:id,name,tag,oil_type_id', 'equipment.oilType:id,name'])
            ->orderBy('number')
            ->get();

        foreach ($muestras as $muestra) {
            $stats = $this->avance[$muestra->id] ?? [];

            $pedidas   = (int) ($stats['pedidas'] ?? 0);
            $validadas = (int) ($stats['validadas'] ?? 0);
            $informadas = (int) ($stats['informadas'] ?? 0);

            $filas[] = [
                $muestra->code,
                // Sin equipo se escribe el motivo, no una celda vacía: una
                // muestra de un cilindro no viene de ningún equipo, y una celda
                // en blanco se lee como un dato que falta.
                $muestra->equipment?->name ?? __('receptions.no_equipment'),
                $muestra->equipment?->tag,
                $muestra->equipment?->oilType?->name,
                $muestra->sampled_at?->format('d-m-Y'),
                $muestra->sampling_point,
                $pedidas,
                $validadas,
                $informadas,
                $this->etapa($pedidas, $validadas, $informadas),
            ];
        }

        return $filas;
    }

    /**
     * La etapa en palabras, la misma que muestra la pantalla.
     *
     * En texto y no como una fracción: "5/6" en una celda de Excel obliga a
     * quien la lee a deducir si falta un ensayo o si falta emitir el informe, y
     * son dos cosas distintas que se le piden a dos personas distintas.
     */
    private function etapa(int $pedidas, int $validadas, int $informadas): string
    {
        $hechas = $validadas + $informadas;

        return match (true) {
            $pedidas === 0        => __('receptions.no_tests'),
            $informadas >= $pedidas => __('receptions.stage_reported'),
            $hechas >= $pedidas  => __('receptions.missing_report'),
            $hechas > 0          => trans_choice('receptions.missing_tests_n', $pedidas - $hechas, ['count' => $pedidas - $hechas]),
            default              => __('receptions.missing_load'),
        };
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $hoja = $event->sheet->getDelegate();
                $ultima = $hoja->getCellByColumnAndRow(10, 1)->getColumn();

                $hoja->getStyle("A1:{$ultima}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
                ]);
                $hoja->getRowDimension(1)->setRowHeight(26);
                $hoja->freezePane('A2');

                for ($i = 1; $i <= 10; $i++) {
                    $letra = $hoja->getCellByColumnAndRow($i, 1)->getColumn();
                    $hoja->getColumnDimension($letra)->setAutoSize(true);
                }
            },
        ];
    }
}
