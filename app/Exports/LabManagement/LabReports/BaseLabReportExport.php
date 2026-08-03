<?php

namespace App\Exports\LabManagement\LabReports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Base de los reportes Excel del menú "Reportes de Lab.".
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ DE DÓNDE VIENEN ESTOS REPORTES                                           │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Son los 7 Excel del menú "Reportes de Lab." del sistema Rails viejo
 * (report_management/{otds,rlabs,rems,fims,jobs,ents} + im_management/reports).
 * Allá eran HTML con extensión .xls (Excel los abría con advertencia); acá son
 * XLSX reales. Las COLUMNAS y el criterio de cada reporte se conservan; lo que
 * se corrigió a propósito está anotado en cada export (el viejo, por ejemplo,
 * titulaba PASIVADOR y nunca emitía el valor, o repartía el mismo nombre de
 * archivo entre tres reportes distintos).
 *
 * El filtro es el mismo del viejo: rango de FECHA DE RECEPCIÓN, con el "desde"
 * por omisión en el inicio del mes de hace 3 meses.
 *
 * Las subclases arman `rows()` y describen su cabecera con `headerRowCount()`
 * y `merges()`; el estilo (banda gris de cabecera, bordes, freeze) es común.
 */
abstract class BaseLabReportExport implements FromArray, WithEvents, WithTitle
{
    /** Relleno de las celdas Sí/No que se pintan (verde/rojo suaves). */
    protected const FILL_OK  = 'DCE9D5';
    protected const FILL_BAD = 'F5D3D0';

    /** @var array<int,array<int,mixed>> */
    protected array $rows = [];

    /** Celdas a pintar: [ [fila 1-based, col 1-based, rgb], ... ] */
    protected array $fills = [];

    /** Filas de TÍTULO de sección (para los reportes tipo ficha). */
    protected array $titleRows = [];

    /** Rangos de cabecera adicionales: [ [filaInicial, filaFinal, colFinal], ... ] */
    protected array $headerBands = [];

    /** Los reportes tipo ficha apagan el borde global (queda feo en filas vacías). */
    protected bool $globalBorders = true;

    public function __construct(
        protected readonly string $from,
        protected readonly ?string $to,
    ) {
    }

    /** @return array<int,array<int,mixed>> */
    abstract protected function buildRows(): array;

    /** Cuántas filas ocupa la cabecera inicial (1 o 2). */
    protected function headerRowCount(): int
    {
        return 1;
    }

    /** Rangos a combinar, en notación A1 ("A1:A2", "G1:U1"...). */
    protected function merges(): array
    {
        return [];
    }

    public function array(): array
    {
        return $this->rows = $this->buildRows();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $hoja = $event->sheet->getDelegate();
                $cols = max(1, max(array_map('count', $this->rows ?: [[1]])));
                $ultima = Coordinate::stringFromColumnIndex($cols);
                $filas = count($this->rows);

                foreach ($this->merges() as $rango) {
                    $hoja->mergeCells($rango);
                }

                if ($this->headerRowCount() > 0) {
                    $this->styleHeaderBand($hoja, 1, $this->headerRowCount(), $ultima);
                }

                foreach ($this->headerBands as [$desde, $hasta, $colFinal]) {
                    $this->styleHeaderBand($hoja, $desde, $hasta, Coordinate::stringFromColumnIndex($colFinal));
                }

                foreach ($this->titleRows as $fila) {
                    $hoja->getStyle("A{$fila}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '354A5F']],
                    ]);
                }

                if ($filas > 1) {
                    if ($this->globalBorders) {
                        $hoja->getStyle("A1:{$ultima}{$filas}")->applyFromArray([
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D5DCE4']]],
                        ]);
                    }
                    $hoja->getStyle("A1:{$ultima}{$filas}")
                        ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                }

                foreach ($this->fills as [$fila, $col, $rgb]) {
                    $hoja->getStyle(Coordinate::stringFromColumnIndex($col) . $fila)->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]],
                    ]);
                }

                if ($this->headerRowCount() > 0) {
                    $hoja->freezePane('A' . ($this->headerRowCount() + 1));
                }

                for ($i = 1; $i <= $cols; $i++) {
                    $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
                }
            },
        ];
    }

    private function styleHeaderBand(Worksheet $hoja, int $desde, int $hasta, string $colFinal): void
    {
        $hoja->getStyle("A{$desde}:{$colFinal}{$hasta}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
        ]);

        for ($f = $desde; $f <= $hasta; $f++) {
            $hoja->getRowDimension($f)->setRowHeight(24);
        }
    }

    /** El rango [desde, hasta] sobre la fecha de RECEPCIÓN, como el viejo. */
    protected function betweenDates($query, string $column)
    {
        return $query->whereDate($column, '>=', $this->from)
            ->when($this->to, fn ($q) => $q->whereDate($column, '<=', $this->to));
    }

    protected function siNo(?bool $valor): string
    {
        if ($valor === null) {
            return '-';
        }

        return $valor ? __('lab_reports.yes') : __('lab_reports.no');
    }
}
