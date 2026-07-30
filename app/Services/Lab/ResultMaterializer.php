<?php

namespace App\Services\Lab;

use App\Models\Result;
use App\Models\Sample;
use App\Models\TestField;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
use Illuminate\Support\Facades\DB;

/**
 * Convierte una hoja de trabajo validada en filas de `results`.
 *
 * Es el puente entre las dos capas: de lo que escribió el analista, guiado por
 * la plantilla, a lo que consultan el informe y las tendencias, indexado por
 * parámetro. El porqué de las dos capas está en la migración
 * `create_results_table`.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ TRES REGLAS QUE PARECEN DETALLE Y NO LO SON                              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * 1. SOLO LAS MUESTRAS producen resultados. El patrón control, el duplicado y
 *    el blanco de reactivos son control de calidad del laboratorio: miden si el
 *    método está midiendo bien, no el aceite del cliente. Van a `qc_points` y a
 *    `qc_duplicates`, y meterlos acá contaminaría la tendencia de un equipo con
 *    lecturas que no son suyas.
 *
 * 2. SOLO LAS COLUMNAS QUE DECLARAN SER UN RESULTADO. Una hoja tiene columnas
 *    que son insumo (el volumen gastado, el peso del aceite, el factor de la
 *    solución) y columnas que son resultado. La diferencia la declara
 *    `output_analyte_id`, y ese es exactamente el dato que el sistema viejo no
 *    tenía: allá el informe tomaba "la última columna por posición" y asumía
 *    que era el resultado.
 *
 * 3. LO QUE NO SE PUEDE RESOLVER NO SE INVENTA. Si una columna es un resultado
 *    pero no declara a qué parámetro alimenta, o si la fila no tiene equipo, se
 *    informa y se saltea. Materializar una fila con el equipo en nulo la deja
 *    fuera de toda consulta y nadie se entera; es peor que no escribirla.
 */
class ResultMaterializer
{
    public function __construct(
        private readonly SpecEvaluator $specs = new SpecEvaluator(),
    ) {
    }

    /**
     * Materializa una hoja completa.
     *
     * Es idempotente: volver a validar la misma hoja actualiza sus resultados,
     * no los duplica. Lo garantiza el índice único
     * (worksheet_row_id, analyte_id, replicate_no).
     *
     * @return array{written:int,skipped:array<int,array{row:int,reason:string}>}
     */
    public function forWorksheet(Worksheet $worksheet): array
    {
        $definition = $worksheet->definition;

        // Solo las columnas que declaran alimentar un parámetro.
        $resultFields = $definition->fields()
            ->whereNotNull('output_analyte_id')
            ->with('analyte:id,code,unit,decimals,group')
            ->get();

        if ($resultFields->isEmpty()) {
            return [
                'written'  => 0,
                'skipped'  => [[
                    'row'    => 0,
                    'reason' => 'sin_columnas_de_resultado',
                ]],
            ];
        }

        $rows = $worksheet->rows()
            ->where('kind', WorksheetRow::KIND_SAMPLE)
            ->with('values')
            ->get();

        $written = 0;
        $skipped = [];

        DB::transaction(function () use ($worksheet, $rows, $resultFields, &$written, &$skipped) {
            foreach ($rows as $row) {
                $equipmentId = $this->equipmentFor($row);

                // ┌──────────────────────────────────────────────────────────┐
                // │ SIN EQUIPO EL RESULTADO SE ESCRIBE IGUAL                 │
                // └──────────────────────────────────────────────────────────┘
                // Antes acá había una compuerta: sin equipo la fila se SALTEABA
                // y el resultado no se escribía nunca. Estaba mal, y la corrige
                // el propio laboratorio (2026-07-30):
                //
                //   "cuando se ingresan los datos de croma es con el número que
                //    le ponemos y luego ese número lo asociamos a un
                //    transformador o a lo que sea. En el reporte no
                //    necesariamente; a veces solamente es el dato de la muestra
                //    nada más, o datos de un cilindro."
                //
                // O sea: el equipo se asocia DESPUÉS, y a veces no se asocia
                // nunca —una muestra de un cilindro de aceite nuevo no viene de
                // ningún equipo y se informa igual—. Con la compuerta puesta,
                // ese ensayo se corría, se cargaba, y el resultado se descartaba
                // en silencio: el trabajo del analista desaparecía.
                //
                // El informe busca los resultados por MUESTRA (`sample_id`), no
                // por equipo, así que llegan al papel sin problema. Lo único que
                // un resultado sin equipo no puede hacer es alimentar la
                // tendencia de ese equipo —no hay equipo— ni tomar los límites
                // que dependen de su clase de tensión. `results.equipment_id`
                // es nullable desde su migración justamente para esto.

                foreach ($resultFields as $field) {
                    $written += $this->writeField($worksheet, $row, $field, $equipmentId);
                }
            }
        });

        return ['written' => $written, 'skipped' => $skipped];
    }

    /**
     * Borra los resultados de una hoja.
     *
     * Se usa al anular: un ensayo anulado no puede seguir alimentando la
     * tendencia de un equipo ni el informe. La hoja y sus valores crudos NO se
     * tocan — la constancia de lo que se hizo queda, con su motivo.
     */
    public function clearWorksheet(Worksheet $worksheet): int
    {
        return Result::whereIn('worksheet_row_id', $worksheet->rows()->pluck('id'))->delete();
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * De dónde sale el equipo de una fila.
     *
     * Manda la MUESTRA si existe, porque es donde el equipo vive
     * conceptualmente: una muestra se extrae de un equipo y después se le
     * corren varias pruebas. El campo directo de la fila es el camino
     * provisorio mientras `samples` no exista (fase 3).
     */
    private function equipmentFor(WorksheetRow $row): ?int
    {
        // La muestra manda: es donde el equipo vive conceptualmente, y lo eligió
        // quien recibió el envase. El campo directo de la fila queda como
        // respaldo para las filas cargadas antes de que existiera la recepción.
        if ($row->sample_id) {
            $deLaMuestra = Sample::withoutGlobalScopes()->whereKey($row->sample_id)->value('equipment_id');

            if ($deLaMuestra !== null) {
                return (int) $deLaMuestra;
            }
        }

        return $row->equipment_id;
    }

    /**
     * Escribe (o actualiza) el resultado de una columna, por cada réplica.
     *
     * @return int cuántas filas se escribieron
     */
    private function writeField(
        Worksheet $worksheet,
        WorksheetRow $row,
        TestField $field,
        ?int $equipmentId,
    ): int {
        $written = 0;
        $replicates = max(1, (int) $field->replicates);

        for ($replicate = 1; $replicate <= $replicates; $replicate++) {
            $value = $row->valueFor($field, $replicate);

            // Una celda vacía no es un resultado. Escribirla como nulo llenaría
            // la tendencia de puntos inexistentes.
            if ($value === null || $value->isEmpty()) {
                continue;
            }

            // El veredicto contra la norma, CONGELADO en la misma escritura. Se
            // calcula acá, al materializar, y NO al leer el informe: si se
            // recalculara, un cambio de límite reescribiría en silencio un
            // certificado ya emitido.
            //
            // Cuando no hay cuadro para ese fluido y ese equipo, o el cuadro no
            // declara ese parámetro, el estado queda en NULO y el informe tiene
            // que decirlo. Rellenar con "cumple" lo que no se pudo evaluar es
            // fabricar una afirmación.
            $cuadro = $this->specs->setFor(
                $row->sample_id,
                $equipmentId,
                $worksheet->tenant_id,
                $field->analyte?->group ?? 'fiqui',
                $worksheet->run_date ? \Illuminate\Support\Carbon::parse($worksheet->run_date) : null,
            );

            $veredicto = $this->specs->verdictFor(
                $cuadro,
                (int) $field->output_analyte_id,
                $value->value_num !== null ? (float) $value->value_num : null,
                $value->value_text,
                $value->qualifier,
            );

            Result::updateOrCreate(
                [
                    'worksheet_row_id' => $row->id,
                    'analyte_id'       => $field->output_analyte_id,
                    'replicate_no'     => $replicate,
                ],
                [
                    'tenant_id'          => $worksheet->tenant_id,
                    'equipment_id'       => $equipmentId,
                    'measured_at'        => $worksheet->run_date,
                    'test_definition_id' => $worksheet->test_definition_id,
                    'test_field_id'      => $field->id,
                    'sample_id'          => $row->sample_id,

                    'value_num'  => $value->value_num,
                    'value_text' => $value->value_num === null ? $value->value_text : null,
                    'qualifier'  => $value->qualifier,
                    // La unidad se copia del parámetro y no de la columna: la
                    // columna la rotula para el analista, el parámetro la
                    // define para el sistema. Si difieren, manda el parámetro.
                    'unit'       => $field->analyte?->unit ?? $field->unit,

                ] + $veredicto,
            );

            $written++;
        }

        return $written;
    }
}
