<?php

namespace App\Exports\BusinessManagement\Instruments;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Plantilla XLSX descargable para importar el inventario de instrumentos.
 *
 * El caso real es una planilla de calibración que el laboratorio ya tiene: por
 * eso las columnas son las de esa planilla (nombre del equipo, tipo, marca,
 * modelo, serie, fechas y certificado).
 *
 * `name` —el código de calibración, PP-LA-01C-100— es la clave: el importador
 * busca por ahí, así que volver a subir la misma planilla con las fechas nuevas
 * ACTUALIZA los equipos en vez de duplicarlos. Es el flujo esperado cada vez que
 * se recalibra el laboratorio.
 *
 * No incluye is_active: toda alta importada nace activa (el estado se gestiona
 * desde la interfaz). Los tips van como comentarios de celda, no como filas,
 * porque el importador leería una fila de ayuda como datos.
 */
class InstrumentsImportTemplate implements FromArray, WithEvents
{
    /** Columnas de la plantilla, en orden. */
    public const COLUMNS = [
        'name', 'description', 'brand', 'model', 'serial',
        'calibrated_at', 'calibration_due_at', 'calibration_certificate', 'location',
    ];

    public function array(): array
    {
        return [
            self::COLUMNS,
            ['PP-LA-01C-023', 'Bureta',            'Brand',          'B-25',  'SN-11021', '2026-03-12', '2027-03-12', 'CAL-2026-118', 'Laboratorio de aceites'],
            ['PP-LA-01C-056', 'Balanza analítica', 'Mettler Toledo', 'ME204', 'SN-77410', '2026-01-30', '2027-01-30', 'CAL-2026-034', 'Laboratorio de aceites'],
            ['PP-LA-01C-107', 'Cromatógrafo',      'Agilent',        '7890B', 'SN-30512', '2025-11-05', '2026-11-05', 'CAL-2025-902', 'Sala de cromatografía'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastCol = $sheet->getCellByColumnAndRow(count(self::COLUMNS), 1)->getColumn();

                // Header SAP-blue
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                for ($i = 1; $i <= count(self::COLUMNS); $i++) {
                    $letter = $sheet->getCellByColumnAndRow($i, 1)->getColumn();
                    $sheet->getColumnDimension($letter)->setAutoSize(true);
                }

                // Tooltips en los encabezados que se prestan a confusión
                // (triangulo rojo en la celda, no ensucia los datos).
                $tips = [
                    'name'               => __('instruments.name_help'),
                    'description'        => __('instruments.description_help'),
                    'calibrated_at'      => __('instruments.calibrated_at_help'),
                    'calibration_due_at' => __('instruments.calibration_due_at_help'),
                ];
                foreach ($tips as $column => $text) {
                    $idx    = array_search($column, self::COLUMNS, true) + 1;
                    $letter = $sheet->getCellByColumnAndRow($idx, 1)->getColumn();
                    $comment = $sheet->getComment("{$letter}1");
                    $comment->setAuthor(__('imports.template_author'));
                    $comment->getText()->createTextRun($text);
                    $comment->setWidth('280pt');
                    $comment->setHeight('70pt');
                }
            },
        ];
    }
}
