<?php

namespace App\Exports\LabManagement\TestDefinitions;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Plantilla XLSX descargable para importar pruebas.
 *
 * Columnas:
 *   code                obligatorio, max 60, clave natural (unico en el sistema)
 *   name                obligatorio, max 150
 *   group_code          opcional, `code` del grupo al que pertenece la prueba
 *   container           opcional, envase requerido para la muestra
 *   chart_unit          opcional, rotulo del eje en las tendencias
 *   has_control         opcional, si/no
 *   requires_control    opcional, si/no
 *   requires_duplicate  opcional, si/no
 *   replicates          opcional, entero (mediciones sobre la misma muestra)
 *   sort_order          opcional, entero
 *   description         opcional, texto libre
 *
 * El grupo se referencia por CODIGO y no por id: los id no son estables entre
 * instalaciones y una planilla llena de numeros no la puede revisar nadie.
 *
 * Las columnas de la hoja de trabajo NO se importan desde aca: son otra tabla y
 * las trae `php artisan import:legacy-tests` desde el volcado del sistema viejo.
 *
 * No incluye is_active: toda alta importada nace activa (el estado se gestiona
 * desde la interfaz).
 */
class TestDefinitionsImportTemplate implements FromArray, WithEvents
{
    /** Columnas de la plantilla, en orden. */
    public const COLUMNS = [
        'code', 'name', 'group_code', 'container', 'chart_unit',
        'has_control', 'requires_control', 'requires_duplicate',
        'replicates', 'sort_order', 'description',
    ];

    public function array(): array
    {
        return [
            self::COLUMNS,
            ['numero_acido',  'Número Ácido',         'fisico_quimico', 'Frasco ámbar 250 ml',     'mg KOH/g', 'si', 'si', 'si', 1, 10, 'Acidez total del aceite por titulación.'],
            ['rigidez',       'Rigidez dieléctrica',  'fisico_quimico', 'Frasco de vidrio 500 ml', 'kV',       'no', 'no', 'no', 6, 20, 'Se mide 6 veces y se promedia.'],
            ['cromatografia', 'Cromatografía',        'cromatografia',  'Jeringa de vidrio 50 ml', 'ppm',      'si', 'si', 'no', 1, 10, 'Gases disueltos en el aceite.'],
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
                    'code'               => __('test_definitions.code_help'),
                    'group_code'         => __('test_definitions.group_help'),
                    'has_control'        => __('test_definitions.has_control_help'),
                    'requires_control'   => __('test_definitions.requires_control_help'),
                    'requires_duplicate' => __('test_definitions.requires_duplicate_help'),
                    'replicates'         => __('test_definitions.replicates_help'),
                ];
                foreach ($tips as $column => $text) {
                    $idx    = array_search($column, self::COLUMNS, true) + 1;
                    $letter = $sheet->getCellByColumnAndRow($idx, 1)->getColumn();
                    $comment = $sheet->getComment("{$letter}1");
                    $comment->setAuthor(__('imports.template_author'));
                    $comment->getText()->createTextRun($text);
                    $comment->setWidth('300pt');
                    $comment->setHeight('80pt');
                }
            },
        ];
    }
}
