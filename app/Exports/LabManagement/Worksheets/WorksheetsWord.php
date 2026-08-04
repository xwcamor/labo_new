<?php

namespace App\Exports\LabManagement\Worksheets;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

/**
 * El listado de bancada en .docx, con la maqueta del resto de los módulos:
 * portada con banda, recuadro de filtros aplicados, tabla con cabecera azul y
 * filas alternadas, y pie con la numeración de páginas.
 *
 * Los encabezados y los valores llegan como funciones desde el trabajo que la
 * invoca, que es el mismo origen que usan el CSV, el Excel y el PDF: cuatro
 * copias del mismo `match` serían cuatro lugares donde el estado de una hoja
 * se puede traducir distinto.
 *
 * OJO OOXML: `size` va en PUNTOS y PhpWord los escribe en medios puntos
 * enteros. Un 9.5 sale como `w:sz="19"` y está bien; un 6.8 escribiría
 * `w:sz="13.6"` y deja el archivo inválido para LibreOffice. Solo múltiplos
 * de 0.5.
 */
class WorksheetsWord
{
    private const COLOR_BRAND      = '0A6ED1';
    private const COLOR_BRAND_DARK = '085CAF';
    private const COLOR_SHELL      = '354A5F';
    private const COLOR_TEXT       = '32363A';
    private const COLOR_TEXT_SOFT  = '6A6D70';
    private const COLOR_BORDER     = 'E5E5E5';
    private const COLOR_ZEBRA      = 'F8FAFC';
    private const COLOR_FILTER_BG  = 'F0F6FB';

    public function generate(
        $hojas,
        array $columnas,
        \Closure $encabezado,
        \Closure $celda,
        string $filename,
        array $options = [],
        array $filtersSummary = [],
        string $generatedBy = '—',
        int $count = 0,
    ): void {
        $tz     = $options['timezone'] ?? config('app.timezone', 'UTC');
        $titulo = $options['title'] ?? __('worksheets.title');

        $word = new PhpWord();
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(10);
        $word->setDefaultParagraphStyle(['spaceAfter' => 0, 'lineHeight' => 1.25]);

        $seccion = $word->addSection([
            'marginTop' => 1000, 'marginBottom' => 1000,
            'marginLeft' => 900, 'marginRight' => 900,
            'orientation' => 'landscape',
        ]);

        // ── Pie con la numeración ────────────────────────────────────────
        $pie = $seccion->addFooter();
        $tablaPie = $pie->addTable(['borderTopSize' => 6, 'borderTopColor' => self::COLOR_BORDER]);
        $tablaPie->addRow();
        $tablaPie->addCell(9000)->addText(
            config('app.name') . ' · ' . now()->setTimezone($tz)->format(\App\Support\Tz::DATE_FORMAT),
            ['size' => 8, 'color' => self::COLOR_TEXT_SOFT],
        );
        $derecha = $tablaPie->addCell(3000, ['valign' => 'top'])->addTextRun(['alignment' => Jc::END]);
        $derecha->addText(__('global.page') . ' ', ['size' => 8, 'color' => self::COLOR_TEXT_SOFT]);
        $derecha->addField('PAGE');
        $derecha->addText(' / ', ['size' => 8, 'color' => self::COLOR_TEXT_SOFT]);
        $derecha->addField('NUMPAGES');

        // ── Portada ──────────────────────────────────────────────────────
        $banda = $seccion->addTable(['cellMargin' => 200, 'borderSize' => 0]);
        $banda->addRow(800);
        $celdaBanda = $banda->addCell(12000, ['bgColor' => self::COLOR_SHELL, 'valign' => 'center']);
        $celdaBanda->addText($titulo, [
            'name' => 'Calibri', 'size' => 22, 'bold' => true, 'color' => 'FFFFFF',
        ], ['spaceAfter' => 60]);
        $celdaBanda->addText(
            __('global.generated_at') . ': '
            . now()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT)
            . ' · ' . trans_choice('global.records_in_report', $count, ['count' => $count]),
            ['size' => 10, 'color' => 'CBD5E1'],
        );

        $seccion->addTextBreak(1);
        $seccion->addText(
            __('global.created_by') . ': ' . $generatedBy,
            ['size' => 9, 'color' => self::COLOR_TEXT_SOFT, 'italic' => true],
        );

        // Con qué recorte se generó: una planilla que no lo dice se lee como
        // si fueran todas las hojas del laboratorio.
        if ($filtersSummary !== [] && ($options['include_filters_summary'] ?? true)) {
            $seccion->addTextBreak(1);

            $recuadro = $seccion->addTable(['cellMargin' => 180, 'borderSize' => 0]);
            $recuadro->addRow();
            $celdaFiltros = $recuadro->addCell(12000, [
                'bgColor'         => self::COLOR_FILTER_BG,
                'borderLeftSize'  => 24,
                'borderLeftColor' => self::COLOR_BRAND,
            ]);
            $celdaFiltros->addText(mb_strtoupper(__('global.filters_applied')), [
                'size' => 8, 'bold' => true, 'color' => self::COLOR_BRAND,
            ], ['spaceAfter' => 80]);

            foreach ($filtersSummary as $f) {
                $linea = $celdaFiltros->addTextRun(['spaceAfter' => 40]);
                $linea->addText($f['label'] . ': ', ['size' => 9, 'bold' => true, 'color' => self::COLOR_TEXT]);
                $linea->addText($f['value'], ['size' => 9, 'color' => self::COLOR_TEXT]);
            }
        }

        $seccion->addTextBreak(1);

        // ── La tabla ─────────────────────────────────────────────────────
        if ($count === 0) {
            $seccion->addText(
                __('global.no_matching_records'),
                ['size' => 10, 'italic' => true, 'color' => self::COLOR_TEXT_SOFT],
                ['alignment' => Jc::CENTER, 'spaceBefore' => 400],
            );
        } else {
            $word->addTableStyle('WorksheetsTable', [
                'borderSize'  => 4,
                'borderColor' => self::COLOR_BORDER,
                'cellMargin'  => 80,
                'alignment'   => JcTable::CENTER,
                'unit'        => \PhpOffice\PhpWord\SimpleType\TblWidth::AUTO,
            ]);

            $tabla = $word->getSection(0)->addTable('WorksheetsTable');

            // `tblHeader`: la cabecera se repite en cada página. Sin eso, la
            // segunda hoja de la planilla es una tabla de números sin título.
            $tabla->addRow(420, ['tblHeader' => true]);
            foreach ($columnas as $columna) {
                $tabla->addCell(null, [
                    'bgColor'     => self::COLOR_BRAND,
                    'valign'      => 'center',
                    'borderColor' => self::COLOR_BRAND_DARK,
                    'borderSize'  => 4,
                ])->addText(
                    $encabezado($columna),
                    ['bold' => true, 'color' => 'FFFFFF', 'size' => 10],
                    ['alignment' => Jc::START, 'spaceAfter' => 0],
                );
            }

            $i = 0;
            foreach ($hojas as $hoja) {
                $tabla->addRow(360);
                $fondo = $i % 2 === 1 ? self::COLOR_ZEBRA : 'FFFFFF';

                foreach ($columnas as $columna) {
                    $tabla->addCell(null, [
                        'bgColor'     => $fondo,
                        'valign'      => 'center',
                        'borderColor' => self::COLOR_BORDER,
                        'borderSize'  => 4,
                    ])->addText(
                        $celda($hoja, $columna),
                        ['size' => 9, 'color' => self::COLOR_TEXT],
                        ['alignment' => in_array($columna, ['rows_count', 'samples_count'], true) ? Jc::END : Jc::START],
                    );
                }

                $i++;
            }
        }

        IOFactory::createWriter($word, 'Word2007')->save($filename);
    }
}
