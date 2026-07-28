<?php

namespace App\Exports\BusinessManagement\Equipment;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Plantilla XLSX descargable para imports de equipment.
 *
 * Columnas:
 *   - name     (obligatorio, max 255) — cómo llama el cliente al equipo
 *   - customer (obligatorio)          — el cliente dueño, por nombre exacto
 *   - serial   (opcional)             — número de serie de la chapa
 *   - tag      (opcional)             — código en planta (TR-01)
 *
 * La plantilla ofrecía una columna `code` que no existe en la tabla, y no
 * ofrecía el cliente: el lote entraba sin dueño y no aparecía en ninguna
 * recepción.
 *
 * No incluye is_active: toda alta importada nace activa (el estado se gestiona desde la UI).
 *
 * No ponemos help-text como filas porque el importer las leeria como datos â€”
 * los tips van en cell comments.
 */
class EquipmentImportTemplate implements FromArray, WithEvents
{
        public function array(): array
    {
        return [
            ['name', 'customer', 'serial', 'tag'],
            ['Transformador de potencia 1', 'ABENGOA PERU SA', '84521-A', 'TR-01'],
            ['Reactor 500 kV', 'ABENGOA PERU SA', '90114-C', 'RE-02'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Header SAP-blue
                $sheet->getStyle('A1:D1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                foreach (['A', 'B', 'C', 'D'] as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // Los tips van como comentario de celda: una fila de ayuda la
                // leería el importador como un equipo más.
                foreach ([
                    'B1' => __('equipment.customer_help'),
                    'C1' => __('equipment.serial_help'),
                    'D1' => __('equipment.tag_help'),
                ] as $celda => $texto) {
                    $comentario = $sheet->getComment($celda);
                    $comentario->setAuthor(__('imports.template_author'));
                    $comentario->getText()->createTextRun($texto);
                    $comentario->setWidth('260pt');
                    $comentario->setHeight('60pt');
                }
            },
        ];
    }
}
