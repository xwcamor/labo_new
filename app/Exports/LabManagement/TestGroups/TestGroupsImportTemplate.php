<?php

namespace App\Exports\LabManagement\TestGroups;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Plantilla XLSX descargable para importar grupos de pruebas.
 *
 * Columnas:
 *   - code (obligatorio, max 40, clave natural, unico en todo el sistema)
 *   - name (obligatorio, max 100)
 *
 * La clave es el CODIGO: volver a subir la misma planilla actualiza los grupos
 * en vez de duplicarlos.
 *
 * No incluye is_active: toda alta importada nace activa (el estado se gestiona
 * desde la interfaz).
 *
 * No hay filas de ayuda porque el importador las leeria como datos — los tips
 * van como comentarios de celda.
 */
class TestGroupsImportTemplate implements FromArray, WithEvents
{
        public function array(): array
    {
        return [
            ['code', 'name'],
            ['fisico_quimico', 'Físico Químico'],
            ['cromatografia',  'Cromatografía'],
            ['otros',          'Otros'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Header SAP-blue
                $sheet->getStyle('A1:B1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                foreach (['A', 'B'] as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // Tooltip en el header de code (columna A: es la clave).
                $commentCode = $sheet->getComment('A1');
                $commentCode->setAuthor(__('imports.template_author'));
                $commentCode->getText()->createTextRun(
                    __('test_groups.code_help')
                );
                $commentCode->setWidth('260pt');
                $commentCode->setHeight('60pt');
            },
        ];
    }
}
