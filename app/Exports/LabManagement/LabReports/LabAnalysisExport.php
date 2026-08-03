<?php

namespace App\Exports\LabManagement\LabReports;

use App\Models\Analyte;
use App\Models\Result;
use App\Models\Sample;
use App\Models\SampleReport;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Análisis de Laboratorio: una fila por muestra con TODOS sus resultados.
 *
 * Es la planilla ancha del viejo `rlabs` (43 columnas): identificación de la
 * muestra + fisicoquímico (11) + cromatografía (9 gases) + los demás ensayos.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ CORRECCIONES DELIBERADAS SOBRE EL VIEJO                                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 * - PASIVADOR: el viejo lo titulaba y NUNCA emitía el valor (43 cabeceras,
 *   42 celdas). Acá el valor sale.
 * - METALES: el viejo mostraba una sola columna `met_val` sin decir qué metal
 *   era. Acá la celda lista los metales medidos ("Cu: 0.3 · Fe: 1.2").
 * - Los valores salen de la capa `results` (derivada de la bancada), no de una
 *   fila ancha indeterminada (`rem_report_details.first` sin orden).
 */
class LabAnalysisExport extends BaseLabReportExport
{
    /** Sub-columnas de fisicoquímico, en el orden del viejo. */
    private const FIQUI = ['acid', 'fp25', 'fp90', 'fp100', 'rig', 'rig_ep', 'ift', 'wat', 'color', 'con', 'dens'];

    /** Los 9 gases, en el orden del viejo (cro_val..cro9_val). */
    private const GASES = ['h2', 'o2', 'n2', 'ch4', 'co', 'co2', 'c2h4', 'c2h6', 'c2h2'];

    /** Ensayos de una columna, en el orden del viejo. `metales` es especial. */
    private const OTROS = [
        'pcb'       => 'pcb',
        'fal'       => 'furano',
        's1275b'    => 'azufre_1275b',
        's62535_48' => 'azufre_48',
        's62535_72' => 'azufre_72',
        'dp'        => 'polimerizacion',
        'visc'      => 'viscosidad',
        'par_iso'   => 'particulas',
        'metales'   => 'metales',
        'inhibitor' => 'inhibidor',
        'dbds'      => 'dbds',
        'sediments' => 'sedimentos',
        'pour'      => 'fluidez',
        'flash'     => 'inflamacion',
        'passivator' => 'pasivador',
    ];

    private const METALES = ['met_al', 'met_cu', 'met_fe', 'met_pb', 'met_ag', 'met_sn', 'met_zn', 'met_si'];

    public function title(): string
    {
        return __('lab_reports.rlabs.sheet');
    }

    protected function headerRowCount(): int
    {
        return 2;
    }

    protected function merges(): array
    {
        $merges = [];

        // Identificación (8) y ensayos de una columna: celda vertical 1:2.
        foreach ([...range(1, 8), ...range(29, 43)] as $col) {
            $letra = Coordinate::stringFromColumnIndex($col);
            $merges[] = "{$letra}1:{$letra}2";
        }

        // Los dos grupos con sub-cabecera.
        $merges[] = 'I1:S1';   // Fisicoquímico (11)
        $merges[] = 'T1:AB1';  // Cromatografía (9)

        return $merges;
    }

    protected function buildRows(): array
    {
        $nombres = Analyte::query()->pluck('name', 'code');

        $fila1 = [
            __('lab_reports.service_order'),
            __('lab_reports.rlabs.date_rec'),
            __('lab_reports.rlabs.date_due'),
            __('lab_reports.rlabs.date_delivered'),
            __('lab_reports.customer'),
            __('lab_reports.sample_code'),
            __('lab_reports.serial'),
            __('lab_reports.rlabs.fluid'),
            __('lab_reports.rlabs.fiqui'), '', '', '', '', '', '', '', '', '', '',
            __('lab_reports.rlabs.chromatography'), '', '', '', '', '', '', '', '',
        ];

        foreach (self::OTROS as $code => $key) {
            $fila1[] = __("lab_reports.rlabs.tests.{$key}");
        }

        $fila2 = array_fill(0, 8, '');

        foreach ([...self::FIQUI, ...self::GASES] as $code) {
            $fila2[] = $nombres[$code] ?? $code;
        }

        $fila2 = [...$fila2, ...array_fill(0, count(self::OTROS), '')];

        $filas = [$fila1, $fila2];

        $muestras = Sample::query()
            ->with(['reception:id,received_at,due_at,service_order,customer_id', 'reception.customer:id,name', 'equipment:id,serial', 'oilType:id,name'])
            ->whereHas('reception', fn ($q) => $this->betweenDates($q, 'received_at'))
            ->orderByDesc('year')->orderByDesc('number')
            ->get();

        $valores = $this->resultsBySample($muestras->pluck('id')->all());
        $entregas = $this->deliveredBySample($muestras->pluck('id')->all());

        foreach ($muestras as $muestra) {
            $v = $valores[$muestra->id] ?? [];

            $fila = [
                $muestra->reception?->service_order ?: __('lab_reports.pending'),
                $muestra->reception?->received_at?->format('d-m-Y') ?? '-',
                $muestra->reception?->due_at?->format('d-m-Y') ?? '-',
                $entregas[$muestra->id] ?? '-',
                $muestra->reception?->customer?->name ?? '',
                $muestra->code,
                $muestra->equipment?->serial ?? '-',
                $muestra->oilType?->name ?? '-',
            ];

            foreach ([...self::FIQUI, ...self::GASES] as $code) {
                $fila[] = $v[$code] ?? '-';
            }

            foreach (array_keys(self::OTROS) as $code) {
                $fila[] = $code === 'metales' ? $this->metales($v) : ($v[$code] ?? '-');
            }

            $filas[] = $fila;
        }

        return $filas;
    }

    /** @return array<int,array<string,string>> [sample_id][analyte_code] => valor mostrable */
    private function resultsBySample(array $sampleIds): array
    {
        $mapa = [];

        Result::query()
            ->whereIn('sample_id', $sampleIds)
            ->where('replicate_no', 1)
            ->with('analyte:id,code')
            ->get()
            ->each(function (Result $r) use (&$mapa) {
                $code = $r->analyte?->code;

                if ($code) {
                    $mapa[$r->sample_id][$code] = (string) ($r->display ?? $r->value_text ?? '-');
                }
            });

        return $mapa;
    }

    /** Fecha de entrega del último informe emitido, por muestra. */
    private function deliveredBySample(array $sampleIds): array
    {
        return SampleReport::query()
            ->whereIn('sample_id', $sampleIds)
            ->where('status', SampleReport::STATUS_ISSUED)
            ->whereNotNull('delivered_at')
            ->orderBy('delivered_at')
            ->get(['sample_id', 'delivered_at'])
            ->mapWithKeys(fn ($i) => [$i->sample_id => $i->delivered_at->format('d-m-Y')])
            ->all();
    }

    /** Los metales medidos, compactos en una celda. */
    private function metales(array $valores): string
    {
        $partes = [];

        foreach (self::METALES as $code) {
            if (isset($valores[$code])) {
                $simbolo = strtoupper(substr($code, 4, 1)) . substr($code, 5);
                $partes[] = "{$simbolo}: {$valores[$code]}";
            }
        }

        return $partes === [] ? '-' : implode(' · ', $partes);
    }
}
