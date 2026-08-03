<?php

namespace App\Exports\LabManagement\LabReports;

use App\Models\Reception;
use App\Models\SampleTest;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Formato de Registro de Ingreso de Muestras: una fila por recepción.
 *
 * Es el `fims` del viejo: la planilla plana con fechas, cliente, el desglose
 * por prueba, el estado de los envases y quién autorizó el ingreso.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL DESGLOSE POR PRUEBA CAMBIÓ DE SIGNIFICADO — A PROPÓSITO               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El viejo contaba ENVASES por tipo de ensayo (num_fiq..num_pas: "Botella para
 * FQ", "Jeringa", 15 contadores tipeados a mano al recibir). El sistema nuevo
 * no captura envases por prueba —captura el total (`packages`) y qué pruebas se
 * piden por muestra—, así que acá el grupo se llama "MUESTRAS POR PRUEBA" y
 * cuenta cuántas muestras de la recepción piden cada ensayo. Es el dato real
 * disponible; inventar envases sería mentir en un formato de registro.
 *
 * Las 15 columnas son las mismas familias del viejo (FQ, cromatografía, PCB,
 * furanos, azufre, DP, viscosidad, partículas, metales, inhibidor, DBDS,
 * sedimentos, fluidez, inflamación, pasivador), vía `report_comment_group`.
 */
class ReceptionRegisterExport extends BaseLabReportExport
{
    /** Las 15 familias del viejo, en su orden, por report_comment_group. */
    private const FAMILIAS = [
        'fisicoquimico'          => 'fiqui',
        'analisis_cromatografico' => 'cromas',
        'pcb'                    => 'pcb',
        'furanos'                => 'furanos',
        'azufre_corrosivo'       => 'azufre',
        'grado_de_polimerizacion' => 'polimerizacion',
        'viscocidad'             => 'viscosidad',
        'particulas'             => 'particulas',
        'metales_en_aceite'      => 'metales',
        'inhibidor'              => 'inhibidor',
        'dbds'                   => 'dbds',
        'sedimentos'             => 'sedimentos',
        'fluidez'                => 'fluidez',
        'inflamacion'            => 'inflamacion',
        'pasivador'              => 'pasivador',
    ];

    public function title(): string
    {
        return __('lab_reports.fims.sheet');
    }

    protected function headerRowCount(): int
    {
        return 2;
    }

    protected function merges(): array
    {
        $merges = [];

        foreach ([1, 2, 3, 4, 5, 6, 22, 26, 27] as $col) {
            $letra = Coordinate::stringFromColumnIndex($col);
            $merges[] = "{$letra}1:{$letra}2";
        }

        $merges[] = 'G1:U1';  // Muestras por prueba (15)
        $merges[] = 'W1:Y1';  // Estado de muestras (3)

        return $merges;
    }

    protected function buildRows(): array
    {
        $fila1 = [
            __('lab_reports.fims.date_rec'),
            __('lab_reports.fims.date_due'),
            __('lab_reports.fims.days_left'),
            __('lab_reports.fims.sampled_by'),
            __('lab_reports.service_order'),
            __('lab_reports.customer'),
            __('lab_reports.fims.tests_group'), '', '', '', '', '', '', '', '', '', '', '', '', '', '',
            __('lab_reports.fims.packages'),
            __('lab_reports.fims.state_group'), '', '',
            __('lab_reports.fims.notes'),
            __('lab_reports.fims.authorized_by'),
        ];

        $fila2 = array_fill(0, 6, '');

        foreach (self::FAMILIAS as $key) {
            $fila2[] = __("lab_reports.fims.families.{$key}");
        }

        $fila2 = [...$fila2, '',
            __('lab_reports.fims.container_ok'),
            __('lab_reports.fims.volume_ok'),
            __('lab_reports.fims.label_ok'),
            '', '',
        ];

        $filas = [$fila1, $fila2];

        $recepciones = $this->betweenDates(
            Reception::query()->with(['customer:id,name', 'sampler:id,name', 'authorizer:id,name']),
            'received_at',
        )->orderByDesc('received_at')->get();

        $conteos = $this->testsPorFamilia($recepciones->pluck('id')->all());
        $fila = 2;

        foreach ($recepciones as $recepcion) {
            $fila++;

            $filaDatos = [
                $recepcion->received_at?->format('d-m-Y H:i') ?? '-',
                $recepcion->due_at?->format('d-m-Y') ?? '-',
                $this->diasRestantes($recepcion, $fila),
                $recepcion->samplerLabel() ?? '-',
                $recepcion->service_order ?: __('lab_reports.pending'),
                $recepcion->customer?->name ?? '',
            ];

            foreach (array_keys(self::FAMILIAS) as $grupo) {
                $n = $conteos[$recepcion->id][$grupo] ?? 0;
                $filaDatos[] = $n > 0 ? $n : '';
            }

            $filaDatos = [...$filaDatos,
                $recepcion->packages,
                $this->siNo($recepcion->container_ok),
                $this->siNo($recepcion->volume_ok),
                $this->siNo($recepcion->label_ok),
                $recepcion->notes,
                $recepcion->authorizer?->name ?? '-',
            ];

            $filas[] = $filaDatos;
        }

        return $filas;
    }

    /** @return array<int,array<string,int>> [reception_id][report_comment_group] => muestras */
    private function testsPorFamilia(array $receptionIds): array
    {
        $mapa = [];

        DB::table('sample_tests as st')
            ->join('samples as s', 's.id', '=', 'st.sample_id')
            ->join('test_definitions as td', 'td.id', '=', 'st.test_definition_id')
            ->whereIn('s.reception_id', $receptionIds)
            ->whereNull('s.deleted_at')
            ->where('st.status', '!=', SampleTest::STATUS_CANCELLED)
            ->groupBy('s.reception_id', 'td.report_comment_group')
            ->select('s.reception_id', 'td.report_comment_group', DB::raw('count(distinct st.sample_id) as n'))
            ->get()
            ->each(function ($r) use (&$mapa) {
                $mapa[$r->reception_id][$r->report_comment_group] = (int) $r->n;
            });

        return $mapa;
    }

    /**
     * Días hasta la fecha comprometida, solo mientras la recepción está
     * abierta (confirmada). Negativo = vencida; ≤ 2 se pinta en rojo.
     */
    private function diasRestantes(Reception $recepcion, int $fila): string|int
    {
        if ($recepcion->status !== Reception::STATUS_CONFIRMED || ! $recepcion->due_at) {
            return '';
        }

        $dias = (int) now()->startOfDay()->diffInDays($recepcion->due_at->startOfDay(), false);

        if ($dias <= 2) {
            $this->fills[] = [$fila, 3, self::FILL_BAD];
        }

        return $dias;
    }
}
