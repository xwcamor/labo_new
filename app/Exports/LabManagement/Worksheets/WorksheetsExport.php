<?php

namespace App\Exports\LabManagement\Worksheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * El listado de bancada en XLSX, con la maqueta del resto de los módulos:
 * título, subtítulo con la fecha de generación, cabecera azul, filas alternadas
 * y filtro automático.
 *
 * Los encabezados y los valores NO se arman acá: los da el trabajo que la
 * invoca (`BaseWorksheetExportJob::heading()` / `cellValue()`), que es el mismo
 * que usan el CSV, el PDF y el Word. Duplicarlos sería tener cuatro lugares
 * donde el estado de una hoja se puede traducir distinto.
 */
class WorksheetsExport implements FromCollection, WithEvents, WithTitle
{
    public function __construct(
        private $hojas,
        private array $columnas,
        private \Closure $encabezado,
        private \Closure $celda,
        private array $options = [],
        private int $count = 0,
        private string $tz = 'UTC',
    ) {
    }

    /** Filas 1-3: título, subtítulo, blanco. Fila 4: cabecera. Fila 5+: datos. */
    public function collection()
    {
        $titulo = $this->options['title'] ?? __('worksheets.title');

        $filas = collect();
        $filas->push([$titulo]);
        $filas->push([sprintf(
            '%s · %s · %s',
            __('global.generated_at'),
            now()->setTimezone($this->tz)->format(\App\Support\Tz::DATETIME_FORMAT),
            trans_choice('global.records_in_report', $this->count, ['count' => $this->count]),
        )]);
        $filas->push(['']);
        $filas->push(array_map(fn ($c) => ($this->encabezado)($c), $this->columnas));

        foreach ($this->hojas as $hoja) {
            $filas->push(array_map(fn ($c) => ($this->celda)($hoja, $c), $this->columnas));
        }

        return $filas;
    }

    public function title(): string
    {
        return mb_substr($this->options['title'] ?? __('worksheets.title'), 0, 31);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $hoja      = $event->sheet->getDelegate();
                $columnas  = count($this->columnas);
                $ultima    = $hoja->getCellByColumnAndRow($columnas, 1)->getColumn();
                $cabecera  = 4;
                $primera   = 5;
                $ultimaFila = $primera + $this->count - 1;

                if ($columnas > 1) {
                    $hoja->mergeCells("A1:{$ultima}1");
                    $hoja->mergeCells("A2:{$ultima}2");
                }

                $hoja->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '32363A']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $hoja->getRowDimension(1)->setRowHeight(28);

                $hoja->getStyle('A2')->applyFromArray([
                    'font'      => ['size' => 10, 'color' => ['rgb' => '6A6D70']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $hoja->getRowDimension(2)->setRowHeight(18);

                $hoja->getStyle("A{$cabecera}:{$ultima}{$cabecera}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
                ]);
                $hoja->getRowDimension($cabecera)->setRowHeight(26);

                if ($ultimaFila >= $primera) {
                    $hoja->getStyle("A{$primera}:{$ultima}{$ultimaFila}")->applyFromArray([
                        'font'      => ['size' => 10, 'color' => ['rgb' => '32363A']],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E5E5']]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    for ($fila = $primera; $fila <= $ultimaFila; $fila++) {
                        if (($fila - $primera) % 2 === 1) {
                            $hoja->getStyle("A{$fila}:{$ultima}{$fila}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                            ]);
                        }
                        $hoja->getRowDimension($fila)->setRowHeight(20);
                    }

                    if ($this->options['autofilter'] ?? true) {
                        $hoja->setAutoFilter("A{$cabecera}:{$ultima}{$ultimaFila}");
                    }
                }

                for ($i = 1; $i <= $columnas; $i++) {
                    $hoja->getColumnDimension($hoja->getCellByColumnAndRow($i, 1)->getColumn())->setAutoSize(true);
                }

                if ($this->options['freeze_header'] ?? true) {
                    $hoja->freezePane('A' . ($cabecera + 1));
                }
            },
        ];
    }
}
