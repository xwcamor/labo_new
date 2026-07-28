<?php

namespace App\Services\Lab;

use App\Models\Sample;
use App\Models\SampleTest;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
use Illuminate\Support\Facades\DB;

/**
 * El avance del laboratorio: qué se ensayó, qué falta y en qué va cada muestra.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA REGLA: DERIVAR UNA VEZ AL ESCRIBIR, LEER BARATO                       │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema anterior el estado se recalculaba AL LEER, desde el propio ERB
 * de la vista:
 *
 *     <% Rem.update(@main_model.id, :series_done => 1 ) %>
 *     <% RemCorrelative.where("id = ?", array.id).update_all(pending_va: 1) %>
 *
 * Nueve escrituras posibles en la cabecera, dieciséis más en la lista de
 * muestras, una consulta por fila y sin paginación. Abrir una remisión de 40
 * muestras costaba unas 320 consultas y 40 escrituras; la pantalla de
 * administración, unos 400 JOIN y 400 UPDATE. Todo dentro de un GET.
 *
 * Y había un efecto peor que la lentitud: **el estado dependía de que alguien
 * abriera la pantalla**. Si nadie abría la remisión, sus banderas quedaban
 * viejas y los filtros del listado mentían.
 *
 * Acá el estado se escribe cuando pasa lo que lo cambia:
 *
 *   se carga la fila de bancada  →  la prueba pedida pasa a "en proceso"
 *   se valida la hoja           →  pasa a "validado"
 *   se anula la hoja            →  vuelve a "pendiente"
 *   se emite el informe         →  pasa a "informado"
 *
 * Abrir la recepción es entonces UNA consulta con GROUP BY que no escribe nada.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL ESTADO NO RETROCEDE SOLO                                              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Volver a guardar una fila de bancada de una hoja ya validada no puede bajar la
 * prueba de "validado" a "en proceso". Por eso los cambios de avance pasan por
 * `advancesTo()` y solo suben. Retroceder es un acto explícito —anular la hoja—
 * y tiene su propio método.
 */
class SampleProgressService
{
    /**
     * Las filas de esta hoja empezaron a cargarse.
     *
     * Se llama al guardar una fila, no al abrir la hoja.
     */
    public function markInProgress(WorksheetRow $row): void
    {
        if ($row->sample_test_id === null) {
            return;
        }

        $prueba = SampleTest::find($row->sample_test_id);

        if (! $prueba || ! $prueba->advancesTo(SampleTest::STATUS_IN_PROGRESS)) {
            return;
        }

        $prueba->update([
            'status'           => SampleTest::STATUS_IN_PROGRESS,
            'worksheet_row_id' => $row->id,
            'started_at'       => $prueba->started_at ?? now(),
        ]);

        $this->refreshSample($prueba->sample);
    }

    /**
     * El supervisor firmó la hoja: sus pruebas quedan validadas.
     *
     * Es UNA actualización masiva sobre las filas de la hoja, no una por fila.
     */
    public function markValidated(Worksheet $worksheet): int
    {
        $pruebas = $this->sampleTestIdsOf($worksheet);

        if ($pruebas === []) {
            return 0;
        }

        $tocadas = SampleTest::whereIn('id', $pruebas)
            // Solo avanza: una prueba ya informada no vuelve a "validado".
            ->whereIn('status', [SampleTest::STATUS_PENDING, SampleTest::STATUS_IN_PROGRESS])
            ->update([
                'status'       => SampleTest::STATUS_VALIDATED,
                'validated_at' => now(),
                'updated_at'   => now(),
            ]);

        $this->refreshSamplesOf($pruebas);

        return $tocadas;
    }

    /**
     * La hoja se anuló: sus pruebas vuelven a estar pendientes.
     *
     * Es el único retroceso admitido, y es explícito. La fila de bancada y sus
     * valores NO se borran —la constancia de lo que se hizo queda—, pero el
     * ensayo vuelve a la cola porque hay que rehacerlo.
     */
    public function markVoided(Worksheet $worksheet): int
    {
        $pruebas = $this->sampleTestIdsOf($worksheet);

        if ($pruebas === []) {
            return 0;
        }

        $tocadas = SampleTest::whereIn('id', $pruebas)
            ->whereIn('status', [SampleTest::STATUS_IN_PROGRESS, SampleTest::STATUS_VALIDATED])
            ->update([
                'status'       => SampleTest::STATUS_PENDING,
                'validated_at' => null,
                'updated_at'   => now(),
            ]);

        $this->refreshSamplesOf($pruebas);

        return $tocadas;
    }

    /**
     * Recalcula el estado de UNA muestra a partir de sus pruebas pedidas.
     *
     * Es una sola consulta agregada, y se llama desde los eventos de arriba —
     * nunca desde una vista.
     */
    public function refreshSample(?Sample $sample): void
    {
        if (! $sample) {
            return;
        }

        $conteo = SampleTest::where('sample_id', $sample->id)
            ->where('status', '!=', SampleTest::STATUS_CANCELLED)
            ->selectRaw('count(*) total')
            ->selectRaw('count(*) filter (where status = ?) pendientes', [SampleTest::STATUS_PENDING])
            ->selectRaw('count(*) filter (where status = ?) en_proceso', [SampleTest::STATUS_IN_PROGRESS])
            ->selectRaw('count(*) filter (where status = ?) informadas', [SampleTest::STATUS_REPORTED])
            ->first();

        $sample->update(['status' => $this->statusFrom(
            (int) ($conteo->total ?? 0),
            (int) ($conteo->pendientes ?? 0),
            (int) ($conteo->en_proceso ?? 0),
            (int) ($conteo->informadas ?? 0),
        )]);
    }

    /**
     * El avance de una recepción entera, en UNA consulta.
     *
     * Es la que reemplaza a las ~320 del sistema anterior. Devuelve, por
     * muestra, cuántas pruebas se le piden y cuántas van en cada estado.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    public function receptionBreakdown(int $receptionId)
    {
        return DB::table('sample_tests as st')
            ->join('samples as s', 's.id', '=', 'st.sample_id')
            ->where('s.reception_id', $receptionId)
            ->whereNull('s.deleted_at')
            ->where('st.status', '!=', SampleTest::STATUS_CANCELLED)
            ->groupBy('st.sample_id')
            ->select('st.sample_id')
            ->selectRaw('count(*) as pedidas')
            ->selectRaw("count(*) filter (where st.status = ?) as pendientes", [SampleTest::STATUS_PENDING])
            ->selectRaw("count(*) filter (where st.status = ?) as en_proceso", [SampleTest::STATUS_IN_PROGRESS])
            ->selectRaw("count(*) filter (where st.status = ?) as validadas", [SampleTest::STATUS_VALIDATED])
            ->selectRaw("count(*) filter (where st.status = ?) as informadas", [SampleTest::STATUS_REPORTED])
            ->get()
            ->keyBy('sample_id');
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * De total / pendientes / informadas al estado de la muestra.
     */
    private function statusFrom(int $total, int $pendientes, int $enProceso, int $informadas): string
    {
        // Sin pruebas pedidas, o ninguna empezada: pendiente.
        if ($total === 0 || $pendientes === $total) {
            return Sample::STATUS_PENDING;
        }

        if ($informadas === $total) {
            return Sample::STATUS_REPORTED;
        }

        // "Ensayada" exige que no quede NADA por hacer. Con `pendientes === 0`
        // a secas, una muestra con su único ensayo a medio cargar figuraba como
        // terminada.
        if ($pendientes === 0 && $enProceso === 0) {
            return Sample::STATUS_COMPLETED;
        }

        return Sample::STATUS_IN_PROGRESS;
    }

    /**
     * @return array<int,int>
     */
    private function sampleTestIdsOf(Worksheet $worksheet): array
    {
        return WorksheetRow::where('worksheet_id', $worksheet->id)
            ->whereNotNull('sample_test_id')
            ->pluck('sample_test_id')
            ->all();
    }

    /**
     * @param array<int,int> $sampleTestIds
     */
    private function refreshSamplesOf(array $sampleTestIds): void
    {
        $muestras = SampleTest::whereIn('id', $sampleTestIds)
            ->distinct()
            ->pluck('sample_id');

        foreach (Sample::whereIn('id', $muestras)->get() as $muestra) {
            $this->refreshSample($muestra);
        }
    }
}
