<?php

namespace App\Services\Lab;

use App\Models\Instrument;
use App\Models\QcChart;
use App\Models\QcDuplicate;
use App\Models\QcPoint;
use App\Models\Result;
use App\Models\SampleTest;
use App\Models\TestField;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
use App\Models\WorksheetValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * La bancada: guardar una fila de la hoja de trabajo, calcularla y dejarla
 * enlazada con el control de calidad.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LO QUE ESTE SERVICIO EXISTE PARA IMPEDIR                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema Rails viejo TODAS las reglas de esta pantalla vivían en el
 * HTML, y ninguna en el servidor:
 *
 *   · El cálculo lo hacía JavaScript guardado en la base. El campo resultado
 *     tenía `readonly`, que es una sugerencia del navegador: un envío directo
 *     escribía en él cualquier número.
 *   · Los campos obligatorios se validaban con una biblioteca del navegador.
 *     El modelo tenía la validación escrita y COMENTADA, así que un envío
 *     directo guardaba vacíos.
 *   · El bloqueo de la hoja solo escondía botones. Los controladores nunca
 *     miraban el estado, así que una hoja bloqueada por el supervisor se
 *     modificaba igual.
 *   · La regla de "primero patrón y duplicado" estaba en las opciones de un
 *     select. Un envío directo cargaba muestras sin ningún control corrido.
 *
 * Acá las cuatro se verifican del lado del servidor, y el cálculo es la única
 * fuente del valor de los campos calculados: lo que venga del formulario para
 * un campo con fórmula se descarta.
 */
class WorksheetService
{
    public function __construct(
        private readonly FormulaResolver $resolver = new FormulaResolver(),
        private readonly WestgardEvaluator $westgard = new WestgardEvaluator(),
        private readonly RepeatabilityEvaluator $repeatability = new RepeatabilityEvaluator(),
        private readonly ResultMaterializer $materializer = new ResultMaterializer(),
        private readonly ValueCoercer $coercer = new ValueCoercer(),
        private readonly SampleProgressService $progress = new SampleProgressService(),
    ) {
    }

    /**
     * Deja puestas las filas de control de calidad que la prueba EXIGE.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ POR QUÉ SE CREAN EN LA BASE Y NO SE DIBUJAN EN PANTALLA              │
     * └──────────────────────────────────────────────────────────────────────┘
     * La plantilla ya declara si la corrida lleva patrón y duplicado
     * (`requires_control`, `requires_duplicate`), y el sistema ya EXIGE que
     * estén antes de admitir la primera muestra: `assertKindAllowed` rechaza la
     * fila de muestra mientras falten. O sea que el analista está obligado a
     * agregarlas a mano, de a una, en toda hoja nueva — un trámite que el
     * sistema le impone y que él no decide.
     *
     * Si son parte de la corrida, las pone la corrida. Se crean como un pliego
     * de bancada preimpreso: los renglones están y las CONSTANTES de la prueba
     * ya vienen puestas —el factor del titulante, el volumen del blanco—; los
     * números medidos los pone el analista.
     *
     * Se crean en la BASE y no como filas de adorno en la pantalla porque el
     * estado de la hoja tiene que ser real desde el minuto cero: una fila de
     * patrón vacía deja la hoja INCOMPLETA y por lo tanto sin publicar. Si
     * fueran solo visuales, un analista que nunca las toca publicaría una hoja
     * sin control de calidad y nadie se enteraría.
     *
     * Idempotente: solo crea el tipo que falta. Volver a llamarla no duplica.
     *
     * @return array<int,string> Los tipos que se crearon.
     */
    public function seedRequiredRows(Worksheet $worksheet): array
    {
        $faltan = $worksheet->missingPrerequisites();

        foreach ($faltan as $kind) {
            $this->saveRow($worksheet, ['kind' => $kind], []);
        }

        return $faltan;
    }

    /**
     * Pone en la hoja TODAS las muestras que esta corrida espera.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ EL SISTEMA YA SABE CUÁLES SON                                        │
     * └──────────────────────────────────────────────────────────────────────┘
     * La recepción registró qué pruebas se le pidieron a cada muestra. La hoja
     * de trabajo de una prueba tiene, por definición, una lista cerrada de
     * muestras que la esperan — es la misma lista que alimenta el desplegable
     * del Nº de muestra. Hacer que el analista las elija de a una es pedirle
     * que copie a mano algo que la máquina ya tiene resuelto, con el riesgo de
     * que se saltee una y esa muestra quede sin ensayar.
     *
     * Las filas salen con las CONSTANTES de la prueba puestas y atadas a su
     * prueba pedida: los números medidos los pone el analista, el enlace con la
     * muestra y los valores que no cambian los pone el sistema.
     *
     * La lista se resuelve ACÁ y no llega desde el navegador. Si viniera del
     * cliente, un envío armado a mano podría meter en esta hoja pruebas de otra
     * definición o muestras de otro workspace.
     *
     * @return int Cuántas filas se agregaron.
     */
    public function fillPendingSamples(Worksheet $worksheet): int
    {
        $this->assertEditable($worksheet);

        // El patrón y el duplicado primero: el propio sistema los exige antes
        // de admitir una muestra, así que sin esto la primera fila fallaría.
        $this->seedRequiredRows($worksheet);

        $yaEstan = $worksheet->rows()->whereNotNull('sample_test_id')->pluck('sample_test_id')->all();

        $pendientes = SampleTest::query()
            ->where('test_definition_id', $worksheet->test_definition_id)
            ->whereIn('status', [SampleTest::STATUS_PENDING, SampleTest::STATUS_IN_PROGRESS])
            ->whereNotIn('id', $yaEstan ?: [0])
            ->whereHas('sample')
            ->with('sample:id,code')
            ->get()
            ->sortBy(fn (SampleTest $p) => $p->sample->code);

        $puestas = 0;

        foreach ($pendientes as $prueba) {
            $this->saveRow(
                $worksheet,
                ['kind' => WorksheetRow::KIND_SAMPLE, 'sample_test_id' => $prueba->id],
                [],
                null,
                publicar: false,
            );

            $puestas++;
        }

        // Una sola vez, al final: ver el comentario de `$publicar` en saveRow.
        $this->publishIfComplete($worksheet->fresh());

        return $puestas;
    }

    /**
     * Guarda VARIAS filas en una sola transacción.
     *
     * Es el "Guardar todo" de la grilla. Manda solo lo que cambió, y o entran
     * todas o no entra ninguna: si la fila catorce choca con la regla de una
     * muestra por fila, dejar guardadas las trece anteriores le deja al
     * analista una hoja a medio escribir sin decirle dónde quedó.
     *
     * Cada fila es `['row_id' => ?int, 'kind' => string, ..., 'values' => []]`.
     *
     * @param  array<int,array<string,mixed>> $filas
     * @return int Cuántas se guardaron.
     *
     * @throws ValidationException
     */
    public function saveRows(Worksheet $worksheet, array $filas): int
    {
        $this->assertEditable($worksheet);

        return DB::transaction(function () use ($worksheet, $filas) {
            foreach ($filas as $fila) {
                $row = ! empty($fila['row_id'])
                    ? $worksheet->rows()->findOrFail($fila['row_id'])
                    : null;

                $this->saveRow(
                    $worksheet,
                    collect($fila)->except(['row_id', 'values'])->all(),
                    $fila['values'] ?? [],
                    $row,
                    publicar: false,
                );
            }

            $this->publishIfComplete($worksheet->fresh());

            return count($filas);
        });
    }

    /**
     * Guarda una fila completa con sus valores.
     *
     * @param  array<string,mixed> $attributes kind, sample_code, position,
     *         instrument_id, notes.
     * @param  array<string,array<int,mixed>> $input Mapa
     *         `código de campo => [nro de réplica => valor]`. Se acepta también
     *         `código => valor` para el caso de una sola réplica.
     *
     * @throws ValidationException
     */
    public function saveRow(
        Worksheet $worksheet,
        array $attributes,
        array $input,
        ?WorksheetRow $row = null,
        bool $publicar = true,
    ): WorksheetRow {
        $this->assertEditable($worksheet);

        $kind = $attributes['kind'] ?? WorksheetRow::KIND_SAMPLE;
        $this->assertKindAllowed($worksheet, $kind, $row);

        $fields = $worksheet->definition->fields()->with(['options', 'instruments:id'])->get();

        return DB::transaction(function () use ($worksheet, $attributes, $input, $row, $fields, $kind, $publicar) {
            $row ??= new WorksheetRow(['worksheet_id' => $worksheet->id]);

            // La muestra manda. Si la fila referencia una prueba pedida, el
            // código de muestra y el equipo se HEREDAN de ella y no se tipean:
            // el analista tiene el envase en la mano, no el dato de a qué
            // transformador pertenece — ése lo puso quien lo recibió.
            $desdeLaMuestra = $this->inheritFromSampleTest($attributes, $row);

            // ┌──────────────────────────────────────────────────────────────┐
            // │ UNA MUESTRA, UNA FILA                                        │
            // └──────────────────────────────────────────────────────────────┘
            // La misma muestra dos veces como fila tipo "muestra" son DOS
            // resultados oficiales para la misma medición, y el informe los
            // imprime a los dos sin poder decidir cuál vale. Una segunda
            // medición es el DUPLICADO (control de calidad, no se informa) o
            // una corrección editando la fila que ya está.
            if ($kind === WorksheetRow::KIND_SAMPLE) {
                $this->assertSampleNotRepeated($worksheet, $row, $desdeLaMuestra, $attributes, $input, $fields);
            }

            $esNueva = ! $row->exists;

            $codigo = $desdeLaMuestra['sample_code']
                ?? $this->sampleCodeFrom($attributes, $input, $fields, $kind);

            // ┌──────────────────────────────────────────────────────────────┐
            // │ UN CÓDIGO TIPEADO SE ATA A SU MUESTRA SI ESA MUESTRA EXISTE  │
            // └──────────────────────────────────────────────────────────────┘
            // La pantalla hace elegir la muestra de una lista, pero el código
            // todavía puede llegar como TEXTO por otros caminos (la carga de
            // datos históricos, una hoja armada antes de que existiera la
            // recepción). Sin esto la fila quedaba con el número escrito y
            // `sample_id` en nulo: su resultado no aparece en ningún informe,
            // porque el informe busca por muestra, no por texto.
            //
            // Se resuelve por código exacto y solo si la muestra tiene PEDIDA
            // esta prueba: atarla a otra cosa sería inventar el enlace.
            if ($kind === WorksheetRow::KIND_SAMPLE
                && $codigo !== null
                && ($desdeLaMuestra['sample_test_id'] ?? $row->sample_test_id) === null) {
                $prueba = SampleTest::query()
                    ->where('test_definition_id', $worksheet->test_definition_id)
                    ->whereHas('sample', fn ($q) => $q->where('code', $codigo))
                    ->first();

                if ($prueba) {
                    $desdeLaMuestra['sample_test_id'] = $prueba->id;
                    $desdeLaMuestra['sample_id']      = $prueba->sample_id;
                }
            }

            $row->fill([
                'worksheet_id'  => $worksheet->id,
                'kind'          => $kind,
                'sample_code'   => $codigo,
                'position'      => $attributes['position'] ?? $row->position ?? $this->nextPosition($worksheet),

                'sample_id'      => $desdeLaMuestra['sample_id'] ?? $row->sample_id,
                'sample_test_id' => $desdeLaMuestra['sample_test_id'] ?? $row->sample_test_id,

                // Estos tres se resuelven con array_key_exists y NO con `??`.
                // La diferencia importa: con `??`, mandar el campo en nulo
                // dejaba el valor anterior, así que la pantalla podía ofrecer
                // un botón de limpiar que no limpiaba nada. Acá "no vino la
                // clave" significa no tocar, y "vino en nulo" significa borrar.
                'instrument_id' => $this->resolve($attributes, 'instrument_id', $row->instrument_id),
                // De qué equipo del cliente es esta muestra. Cuando la fila
                // viene de una muestra, sale de ella; se admite a mano solo
                // mientras la recepción no esté cargada.
                'equipment_id'  => $desdeLaMuestra['equipment_id']
                    ?? $this->resolve($attributes, 'equipment_id', $row->equipment_id),
                'notes'         => $this->resolve($attributes, 'notes', $row->notes),
            ])->save();

            // Las CONSTANTES de la prueba y las listas de una sola opción, en
            // las filas que nacen sin pasar por la pantalla. Ver
            // `applyConstants` y `applySingleChoice`.
            if ($esNueva) {
                $input = $this->applyConstants($fields, $input);
                $input = $this->applySingleChoice($fields, $input);
            }

            $this->writeValues($row, $fields, $input);
            $this->recalculate($row, $fields);

            // Quién cargó esta fila queda en el historial de la hoja. Antes solo
            // se auditaba la CABECERA (la hoja como registro), así que la
            // pregunta "¿quién registró esta muestra en la bancada?" no tenía
            // respuesta en pantalla: el dato estaba en `worksheet_values.
            // entered_by`, celda por celda, y nadie lo mostraba.
            $this->auditRow($worksheet, $row, $esNueva ? 'row_added' : 'row_updated');

            // La prueba pedida pasa a "en proceso". Se escribe acá, cuando
            // ocurre, y no al abrir la pantalla de la recepción.
            $this->progress->markInProgress($row);

            // ┌──────────────────────────────────────────────────────────────┐
            // │ EL RESULTADO SE PUBLICA AL ESTAR COMPLETO, NO AL APRETAR UN  │
            // │ BOTÓN                                                        │
            // └──────────────────────────────────────────────────────────────┘
            // Hubo un botón "Validar" en la franja de la hoja. No existía en el
            // sistema anterior y creaba un limbo: la hoja quedaba cargada pero
            // sus resultados no existían para nadie hasta que alguien se
            // acordara de apretarlo, y ese alguien no estaba definido.
            //
            // El momento en que un resultado se vuelve oficial NO es un clic
            // sobre la hoja: es la EMISIÓN del informe, que lleva número,
            // firmantes y queda auditada. La hoja solo tiene que dejar el dato
            // consultable en cuanto esté completo, y dejar de admitir cambios
            // cuando el candado la cierre.
            // En la carga en LOTE esto se saltea y se corre una sola vez al
            // final: el chequeo recorre todas las filas y todas sus celdas
            // obligatorias, así que hacerlo por fila convierte veinte muestras
            // en veinte recorridos de la hoja entera.
            if ($publicar) {
                $this->publishIfComplete($worksheet);
            }

            return $row->refresh();
        });
    }

    /**
     * Da de baja una fila de la hoja Y retira lo que había publicado.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ EL DEFECTO QUE ESTO CORRIGE                                          │
     * └──────────────────────────────────────────────────────────────────────┘
     * El borrado era `$row->delete()` a secas: la fila desaparecía de la
     * grilla pero su resultado ya materializado quedaba vivo en `results`, y
     * el informe seguía imprimiendo un valor que no estaba detrás de ninguna
     * fila — el analista lo rehacía con otros números y el papel mostraba el
     * viejo. `results` es una capa DERIVADA de la hoja: lo que la hoja ya no
     * tiene, la capa tampoco.
     */
    public function deleteRow(Worksheet $worksheet, WorksheetRow $row): void
    {
        $this->assertEditable($worksheet);

        // Con la prueba ya INFORMADA la fila no se borra: su resultado está
        // impreso en un papel que el cliente tiene en la mano. Primero se
        // retira el informe (desbloquear / adicional); la fila, después.
        if ($row->sample_test_id !== null
            && SampleTest::whereKey($row->sample_test_id)
                ->where('status', SampleTest::STATUS_REPORTED)->exists()) {
            throw ValidationException::withMessages([
                'row' => __('worksheets.errors.row_reported'),
            ]);
        }

        DB::transaction(function () use ($worksheet, $row) {
            // Se audita ANTES de borrar: después la fila ya no tiene de dónde
            // leer su código de muestra.
            $this->auditRow($worksheet, $row, 'row_removed');

            $row->delete();
            Result::where('worksheet_row_id', $row->id)->delete();

            // La prueba pedida vuelve a la cola si esta era su única fila:
            // sin esto quedaba "validada" en verde con cero mediciones detrás.
            $this->progress->markRowRemoved($row);

            // Lo que queda en la hoja se republica si sigue completa: los
            // resultados de las otras filas no cambian, pero el estado de la
            // prueba pedida y el control de calidad sí pueden.
            $this->publishIfComplete($worksheet->refresh());
        });
    }

    /**
     * Deja constancia en el historial de la HOJA de lo que pasó con una fila.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ POR QUÉ CUELGA DE LA HOJA Y NO DE LA FILA                            │
     * └──────────────────────────────────────────────────────────────────────┘
     * La fila no tiene pantalla propia: no hay dónde abrir "el historial de la
     * fila 3". Quien pregunta "¿quién cargó esta muestra?" está mirando la hoja,
     * así que el rastro tiene que estar ahí. Además una fila borrada se lleva su
     * historial si el evento cuelga de ella, y el borrado es justamente lo que
     * hay que poder auditar.
     *
     * Se guarda el CÓDIGO de la muestra y no su id: el id no le dice nada a
     * quien lee el historial, y si la fila se borró el id ya no resuelve nada.
     */
    private function auditRow(Worksheet $worksheet, WorksheetRow $row, string $evento): void
    {
        \App\Models\AuditLog::create([
            'user_id'        => auth()->id(),
            'auditable_type' => $worksheet->getMorphClass(),
            'auditable_id'   => $worksheet->getKey(),
            'event'          => $evento,
            'old_values'     => null,
            'new_values'     => [
                'sample_code' => $row->sample_code,
                'kind'        => $row->kind,
                'position'    => $row->position,
            ],
        ]);
    }

    /**
     * @throws ValidationException si la muestra ya tiene su fila en esta hoja
     */
    private function assertSampleNotRepeated(
        Worksheet $worksheet,
        WorksheetRow $row,
        array $desdeLaMuestra,
        array $attributes,
        array $input,
        $fields,
    ): void {
        $sampleId = $desdeLaMuestra['sample_id'] ?? $row->sample_id;
        $codigo   = $desdeLaMuestra['sample_code']
            ?? $this->sampleCodeFrom($attributes, $input, $fields, WorksheetRow::KIND_SAMPLE)
            ?? $row->sample_code;

        // Una fila suelta sin muestra ni código todavía no repite nada.
        if ($sampleId === null && ($codigo === null || $codigo === '')) {
            return;
        }

        $repetida = WorksheetRow::where('worksheet_id', $worksheet->id)
            ->where('kind', WorksheetRow::KIND_SAMPLE)
            ->when($row->exists, fn ($q) => $q->whereKeyNot($row->id))
            ->where(function ($q) use ($sampleId, $codigo) {
                if ($sampleId !== null) {
                    $q->orWhere('sample_id', $sampleId);
                }
                if ($codigo !== null && $codigo !== '') {
                    $q->orWhere('sample_code', $codigo);
                }
            })
            ->exists();

        if ($repetida) {
            throw ValidationException::withMessages([
                'sample_code' => __('worksheets.errors.duplicate_sample', ['code' => $codigo ?? '#' . $sampleId]),
            ]);
        }
    }

    /**
     * Vuelca a la capa consultable lo que la hoja ya tiene completo.
     *
     * Se ejecuta después de cada guardado y es idempotente: rematerializar
     * reescribe los mismos resultados. Una hoja incompleta no publica nada —
     * un obligatorio vacío significa que la medición no terminó, y publicarla
     * a medias la haría aparecer en el informe de un cliente con un hueco que
     * nadie decidió dejar.
     */
    private function publishIfComplete(Worksheet $worksheet): void
    {
        if ($this->missingRequiredValues($worksheet) !== []) {
            return;
        }

        // QUIÉN la completó, además de cuándo. Faltaba: `validated_by` solo lo
        // llenaba `validate()`, el paso manual de supervisión que este flujo ya
        // no usa —la hoja publica sola en cuanto no le falta ningún dato—, así
        // que la columna quedaba vacía en TODAS las hojas y la pantalla mostraba
        // un guion donde tenía que haber un nombre.
        //
        // Es quien guardó la última fila que dejó la hoja completa. No "validó"
        // nada: la terminó. Por eso la etiqueta dice "Completada por" y no
        // "Validada por" — decir que alguien revisó lo que no revisó es peor
        // que no decir nada.
        $worksheet->forceFill([
            'status'       => Worksheet::STATUS_VALIDATED,
            'validated_at' => $worksheet->validated_at ?? now(),
            'validated_by' => $worksheet->validated_by ?? auth()->id(),
        ])->save();

        // Los patrones alimentan la carta de control, las muestras pasan a la
        // capa consultable, y las pruebas pedidas quedan al día.
        $this->materializeQc($worksheet);
        $this->materializer->forWorksheet($worksheet);
        $this->progress->markValidated($worksheet);
    }

    /**
     * Recalcula los campos con fórmula de una fila y los persiste.
     *
     * Se ejecuta SIEMPRE en el servidor, incluso si el navegador ya mostró el
     * resultado: el número que queda guardado es el que calculó el servidor.
     *
     * @param  \Illuminate\Support\Collection<int,TestField>|null $fields
     * @return array{values:array<string,?float>,cycles:array<int,array<int,string>>,unresolved:array<int,string>,errors:array<string,array<int,string>>}
     */
    public function recalculate(WorksheetRow $row, $fields = null): array
    {
        $fields ??= $row->worksheet->definition->fields()->get();
        $computed = $fields->filter(fn (TestField $f) => filled($f->formula));

        if ($computed->isEmpty()) {
            return ['values' => [], 'cycles' => [], 'unresolved' => [], 'errors' => []];
        }

        $replicates = max(1, (int) $fields->max('replicates'));
        $last = ['values' => [], 'cycles' => [], 'unresolved' => [], 'errors' => []];

        // Cada réplica se resuelve por separado: la medición 3 se calcula con
        // los datos de la medición 3, no con una mezcla de todas.
        for ($replicate = 1; $replicate <= $replicates; $replicate++) {
            $context = $row->valuesByFieldCode($replicate);
            $result = $this->resolver->resolveWithDiagnostics($fields->all(), $context);
            $last = $result;

            foreach ($computed as $field) {
                if ($replicate > max(1, (int) $field->replicates)) {
                    continue;
                }

                $value = $result['values'][$field->code] ?? null;

                WorksheetValue::updateOrCreate(
                    [
                        'worksheet_row_id' => $row->id,
                        'test_field_id'    => $field->id,
                        'replicate_no'     => $replicate,
                    ],
                    [
                        'value_num'   => $value,
                        'value_text'  => null,
                        'option_id'   => null,
                        'is_computed' => true,
                        'entered_by'  => auth()->id(),
                        'entered_at'  => now(),
                    ],
                );
            }
        }

        $row->unsetRelation('values');

        return $last;
    }

    /**
     * Publica a mano lo que la hoja ya tiene completo.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ YA NO ES UN BOTÓN                                                    │
     * └──────────────────────────────────────────────────────────────────────┘
     * Hubo un botón "Validar" en la franja de la hoja, y antes de él uno de
     * "Cerrar hoja". Ninguno de los dos existía en el sistema anterior y entre
     * los dos creaban un limbo: la hoja quedaba cargada pero sus resultados no
     * existían para nadie hasta que alguien apretara los dos botones, y ese
     * alguien no estaba definido.
     *
     * Hoy la hoja publica sola en cuanto está completa (`publishIfComplete`,
     * dentro de `saveRow`) y deja de admitir cambios cuando el candado la
     * cierra. El momento en que un resultado se vuelve oficial es la EMISIÓN
     * del informe, que lleva número, firmantes y queda auditada.
     *
     * Este método queda como el mismo trabajo hecho a pedido —lo usan las
     * pruebas y sirve de respaldo para rematerializar una hoja vieja—, pero no
     * cuelga de ninguna pantalla.
     *
     * @throws ValidationException
     */
    public function validate(Worksheet $worksheet): Worksheet
    {
        if ($worksheet->locked_at !== null) {
            throw ValidationException::withMessages([
                'status' => __('worksheets.errors.locked'),
            ]);
        }

        $missing = $this->missingRequiredValues($worksheet);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'rows' => __('worksheets.errors.missing_required', ['count' => count($missing)]),
            ]);
        }

        return DB::transaction(function () use ($worksheet) {
            $worksheet->forceFill([
                'status'       => Worksheet::STATUS_VALIDATED,
                'validated_by' => auth()->id(),
                'validated_at' => now(),
            ])->save();

            $this->materializeQc($worksheet);
            $this->materializer->forWorksheet($worksheet);
            $this->progress->markValidated($worksheet);

            return $worksheet;
        });
    }

    /**
     * Da de baja la hoja, con su motivo.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ ERA "ANULAR" Y NO EXISTÍA EN EL SISTEMA ANTERIOR                     │
     * └──────────────────────────────────────────────────────────────────────┘
     * Allá había ELIMINAR, y era un borrado lógico (`deleted = 1`) sin motivo.
     * "Anular" fue un invento de esta reescritura que agregaba un estado más al
     * flujo sin agregar ninguna capacidad: hacía exactamente lo mismo que un
     * borrado lógico bien hecho.
     *
     * Así que se llama borrar y se comporta como borrar —desaparece de los
     * listados, se puede restaurar desde la papelera— pero conserva TODO: la
     * hoja, sus valores crudos y el motivo. Un ensayo dado de baja tiene que
     * seguir estando, porque el laboratorio responde por él ante la auditoría.
     * El estado `voided` se mantiene para las hojas que ya lo tenían.
     */
    public function void(Worksheet $worksheet, string $reason): Worksheet
    {
        // Una hoja ya dada de baja no se vuelve a dar de baja: pisaría el motivo
        // original, que es justamente lo que hay que conservar.
        if ($worksheet->isVoided()) {
            throw ValidationException::withMessages([
                'status' => __('worksheets.errors.already_voided'),
            ]);
        }

        // QUIÉN la dio de baja, además del motivo. Faltaba: la papelera decía
        // por qué desapareció una hoja pero no de quién fue la decisión, que es
        // justo lo que se pregunta cuando el ensayo hay que rehacerlo.
        $worksheet->forceFill([
            'status'              => Worksheet::STATUS_VOIDED,
            'void_reason'         => $reason,
            'deleted_by'          => auth()->id(),
            'deleted_description' => $reason,
        ])->save();

        // Los resultados SÍ se retiran de la capa consultable: un ensayo dado de
        // baja no puede seguir apareciendo en el informe de un cliente ni
        // moviendo la tendencia de un equipo. La hoja y sus valores crudos
        // quedan intactos con su motivo, así que la constancia no se pierde y
        // el resultado se puede reconstruir si la baja fue un error.
        $this->materializer->clearWorksheet($worksheet);

        // Los puntos de la carta de control, en cambio, se marcan y NO se
        // borran: el laboratorio tiene que poder mostrar que detectó un patrón
        // fuera de control y por qué lo descartó, no que nunca existió.
        QcPoint::whereIn('worksheet_row_id', $worksheet->rows()->pluck('id'))
            ->update([
                'is_excluded'      => true,
                'exclusion_reason' => $reason,
            ]);

        // Y sus pruebas pedidas vuelven a la cola: el ensayo hay que rehacerlo.
        // Es el único retroceso de estado admitido, y es explícito.
        $this->progress->markVoided($worksheet);

        // El borrado lógico va DESPUÉS de limpiar: sale de los listados pero la
        // fila y sus valores siguen ahí, y la papelera la restaura.
        $worksheet->delete();

        return $worksheet;
    }

    /**
     * Deshace la baja: la hoja vuelve, y con ella todo lo que la baja retiró.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ NO ALCANZA CON RESTAURAR LA FILA                                     │
     * └──────────────────────────────────────────────────────────────────────┘
     * `void()` no borra una fila: retira los resultados de la capa consultable,
     * marca los puntos de la carta de control y devuelve los ensayos a la cola.
     * Un `restore()` pelado traería la hoja de vuelta al listado con sus
     * resultados fuera del informe, sus puntos excluidos de la carta y sus
     * pruebas otra vez pendientes — o sea, una hoja que se ve viva y no lo está.
     *
     * El estado anterior NO se guarda en ninguna columna y no hace falta: se
     * DERIVA. `publishIfComplete()` es la misma puerta por la que la hoja pasó
     * a validada la primera vez, así que una hoja completa vuelve a validada y
     * re-materializa sus resultados, y una a medio cargar vuelve a borrador —
     * que es exactamente lo que era.
     *
     * De la carta de control solo se des-marcan los puntos que excluyó ESTA
     * baja (los que llevan su mismo motivo). Un patrón que Westgard descartó
     * antes sigue descartado: esa constancia no la puede borrar un "deshacer".
     */
    public function unvoid(Worksheet $worksheet): Worksheet
    {
        if (! $worksheet->trashed()) {
            return $worksheet;
        }

        $motivo = $worksheet->void_reason;

        $worksheet->restore();

        // `validated_at` y `validated_by` NO se tocan: la baja tampoco los tocó,
        // y quién validó la hoja es constancia, no estado de la pantalla. Lo
        // que SÍ se limpia es el rastro de la baja: la hoja volvió, así que
        // decir que sigue "dada de baja por Fulano" sería mentir.
        $worksheet->forceFill([
            'status'              => Worksheet::STATUS_DRAFT,
            'void_reason'         => null,
            'deleted_by'          => null,
            'deleted_description' => null,
        ])->save();

        if (filled($motivo)) {
            QcPoint::whereIn('worksheet_row_id', $worksheet->rows()->pluck('id'))
                ->where('is_excluded', true)
                ->where('exclusion_reason', $motivo)
                ->update(['is_excluded' => false, 'exclusion_reason' => null]);
        }

        // La misma puerta de siempre: si la hoja está completa vuelve a
        // validada y sus resultados vuelven al informe; si no, queda borrador.
        $this->publishIfComplete($worksheet->fresh()->load('definition'));

        return $worksheet->fresh();
    }

    /**
     * Bloquea las hojas que ya cumplieron su antigüedad.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ EL CANDADO LO PONE EL SISTEMA, NO UNA PERSONA                        │
     * └──────────────────────────────────────────────────────────────────────┘
     * Es como funcionaba el sistema anterior y es lo correcto: un ensayo de hace
     * cuatro meses ya se informó, ya se facturó y ya salió del laboratorio.
     * Dejarlo editable indefinidamente porque nadie se acordó de bloquearlo es
     * exactamente el agujero por el que un resultado cambia después de que el
     * cliente recibió el papel.
     *
     * Bloqueado no es intocable: se desbloquea a mano y ese desbloqueo queda
     * auditado, con quién y cuándo. Lo que cambia es que la edición pasa a ser
     * una decisión explícita en vez del estado por omisión.
     *
     * Los meses son un AJUSTE (`worksheets.auto_lock_months`), no un número
     * escrito acá: cada laboratorio tiene su plazo y ninguno debería necesitar
     * un despliegue para cambiarlo.
     *
     * @return int cuántas hojas se bloquearon
     */
    public function autoLockAged(?int $meses = null): int
    {
        $meses ??= \App\Models\Setting::getInt('worksheets.auto_lock_months', 4);

        // Cero o negativo apaga el bloqueo automático. Es la forma de decir "en
        // este laboratorio no", sin tener que sacar la tarea programada.
        if ($meses < 1) {
            return 0;
        }

        $corte = now()->subMonths($meses);

        return Worksheet::query()
            ->whereNull('locked_at')
            ->whereNull('deleted_at')
            ->where('run_date', '<=', $corte->toDateString())
            ->update([
                'locked_at'  => now(),
                'lock_scope' => 'auto',
                'updated_at' => now(),
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Interno
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Un atributo opcional que SÍ se puede vaciar.
     *
     * Si la clave no viene, se conserva lo que había. Si viene en nulo, se
     * borra. Con el operador `??` las dos cosas eran indistinguibles y no había
     * forma de desasignar un equipo mal elegido.
     *
     * @param array<string,mixed> $attributes
     */
    private function resolve(array $attributes, string $key, mixed $current): mixed
    {
        return array_key_exists($key, $attributes) ? $attributes[$key] : $current;
    }

    /**
     * Lo que la fila hereda de la prueba pedida: la muestra, su código y su
     * equipo.
     *
     * Éste es el arreglo de fondo respecto del sistema anterior. Allá la fila de
     * bancada guardaba el número de muestra como TEXTO, copiado por jQuery desde
     * la primera celda, y para encontrar la muestra se partía la cadena
     * ("2026-0695" → año 2026, número 695) y se interpolaba cruda en SQL. Sin
     * clave foránea, sin índice y sin garantía de que la muestra existiera; el
     * propio autor lo dejó anotado: "No funciona si el usuario crea antes de que
     * registre el ingreso de la muestra".
     *
     * Acá la relación es una clave foránea y el número de muestra pasa a ser lo
     * que siempre debió ser: una etiqueta que se muestra.
     *
     * @param  array<string,mixed> $attributes
     * @return array<string,mixed>
     * @throws ValidationException
     */
    private function inheritFromSampleTest(array $attributes, WorksheetRow $row): array
    {
        if (! array_key_exists('sample_test_id', $attributes)) {
            return [];
        }

        $id = $attributes['sample_test_id'];

        if ($id === null) {
            return ['sample_test_id' => null, 'sample_id' => null];
        }

        $prueba = SampleTest::with('sample')->find($id);

        if (! $prueba) {
            throw ValidationException::withMessages([
                'sample_test_id' => __('worksheets.errors.unknown_sample_test'),
            ]);
        }

        // La prueba pedida tiene que ser la MISMA que corre esta hoja. Sin esta
        // verificación se podría cargar una cromatografía en la hoja de número
        // ácido y el resultado saldría informado bajo el parámetro equivocado.
        $hoja = $row->worksheet_id
            ? Worksheet::find($row->worksheet_id)
            : null;

        if ($hoja && (int) $prueba->test_definition_id !== (int) $hoja->test_definition_id) {
            throw ValidationException::withMessages([
                'sample_test_id' => __('worksheets.errors.sample_test_other_definition'),
            ]);
        }

        return [
            'sample_test_id' => $prueba->id,
            'sample_id'      => $prueba->sample_id,
            'sample_code'    => $prueba->sample?->code,
            'equipment_id'   => $prueba->sample?->equipment_id,
        ];
    }

    /** @throws ValidationException */
    private function assertEditable(Worksheet $worksheet): void
    {
        if ($worksheet->isEditable()) {
            return;
        }

        throw ValidationException::withMessages([
            'worksheet' => $worksheet->locked_at !== null
                ? __('worksheets.errors.locked')
                : __('worksheets.errors.not_draft'),
        ]);
    }

    /**
     * La regla de patrón y duplicado, del lado del servidor.
     *
     * @throws ValidationException
     */
    private function assertKindAllowed(Worksheet $worksheet, string $kind, ?WorksheetRow $row): void
    {
        if ($kind !== WorksheetRow::KIND_SAMPLE) {
            return;
        }

        // Al editar una muestra que ya existe no se vuelve a exigir: si está
        // cargada es porque en su momento se cumplió, y bloquear su edición
        // dejaría la fila inaccesible si después se borró el patrón.
        if ($row !== null && $row->exists && $row->kind === WorksheetRow::KIND_SAMPLE) {
            return;
        }

        $missing = $worksheet->missingPrerequisites();

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'kind' => __('worksheets.errors.missing_prerequisites', [
                    'kinds' => implode(', ', array_map(
                        fn (string $k) => __("worksheets.kind.{$k}"),
                        $missing
                    )),
                ]),
            ]);
        }
    }

    /**
     * Escribe los valores cargados a mano. Los campos con fórmula se saltean a
     * propósito: su valor lo produce recalculate() y nada más.
     *
     * @param \Illuminate\Support\Collection<int,TestField> $fields
     * @param array<string,mixed> $input
     */
    /**
     * Los valores constantes de la prueba, en una fila que recién nace.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ EL DEFECTO QUE ESTO CORRIGE                                          │
     * └──────────────────────────────────────────────────────────────────────┘
     * Las columnas marcadas como CONSTANTES (`is_reusable`, con su valor en
     * `default_value`) son las que no cambian dentro de una corrida: el factor
     * de la solución titulante, el volumen del blanco. Se cargan una vez en
     * "Valores constantes" y valen para toda la tanda.
     *
     * Ese valor lo ponía SOLO la pantalla, al armar el borrador de una fila
     * nueva. Y hay filas que no nacen en la pantalla:
     *
     *   · el patrón y el duplicado que la prueba EXIGE, que se crean solos al
     *     abrir la hoja (`seedRequiredRows`);
     *   · las que trae "Traer muestras pendientes" (`fillPendingSamples`).
     *
     * Esas nacían VACÍAS. El analista abría una hoja de Número Ácido con el
     * factor KOH y el volumen del blanco ya cargados en Valores constantes, y
     * las dos primeras filas —justo las obligatorias— le pedían tipearlos otra
     * vez. Peor: la hoja contaba esas celdas como datos faltantes y no
     * publicaba, sin que nada explicara por qué si el valor "ya estaba puesto".
     *
     * Ahora lo pone el SERVIDOR, que es el único punto por el que pasan las
     * tres vías. La pantalla sigue arrastrando lo TIPEADO de la fila anterior
     * —que es otra cosa: la corrida en la que el analista cambia el factor a
     * mitad de tanda—, y como lo que viene del formulario tiene prioridad, ese
     * arrastre nunca se pisa.
     *
     * @param  \Illuminate\Support\Collection<int,TestField> $fields
     * @param  array<string,mixed>                            $input
     * @return array<string,mixed>
     */
    private function applyConstants($fields, array $input): array
    {
        foreach ($fields as $field) {
            if (! $field->is_reusable || blank($field->default_value) || filled($field->formula)) {
                continue;
            }

            // Lo que vino del formulario manda: si el analista escribió algo
            // —incluso vacío a propósito— la constante no lo reemplaza.
            if (array_key_exists($field->code, $input)) {
                continue;
            }

            // Una constante vale para TODAS las réplicas de la columna: el
            // factor del titulante es el mismo para las cinco mediciones.
            $porReplica = [];
            for ($replica = 1; $replica <= max(1, (int) $field->replicates); $replica++) {
                $porReplica[$replica] = $field->default_value;
            }

            $input[$field->code] = $porReplica;
        }

        return $input;
    }

    /**
     * Las listas de UNA sola opción, ya elegidas.
     *
     * La norma de la prueba suele ser una y nada más (Contenido de Agua se
     * corre con ASTM D1533 y con ninguna otra), y de varios equipos el
     * laboratorio tiene uno solo. Desplegar para elegir lo único que se puede
     * elegir es trabajo que no decide nada, repetido en cada fila, y la casilla
     * que se olvida deja la hoja sin publicar por un dato que no tenía
     * alternativa.
     *
     * Queda FUERA la columna del Nº de muestra: ahí lo que se elige es a qué
     * equipo del cliente pertenece la fila, y esa decisión es del analista
     * aunque quede una sola muestra pendiente. Fue el pedido explícito del
     * laboratorio.
     *
     * Va junto a `applyConstants` y por el mismo motivo: la pantalla ya lo
     * resuelve para las filas que el analista agrega, pero el patrón y el
     * duplicado los crea el servidor y no pasan por ahí.
     *
     * @param  \Illuminate\Support\Collection<int,TestField> $fields
     * @param  array<string,mixed>                            $input
     * @return array<string,mixed>
     */
    private function applySingleChoice($fields, array $input): array
    {
        $catalogo = null;   // se consulta una vez, y solo si hace falta

        foreach ($fields as $field) {
            if ($field->role === TestField::ROLE_SAMPLE_CODE || filled($field->formula)) {
                continue;
            }

            // Lo que vino del formulario manda, igual que con las constantes.
            if (array_key_exists($field->code, $input)) {
                continue;
            }

            $unica = null;

            if ($field->type === 'select') {
                $visibles = $field->options->where('is_hidden', false)->values();
                $unica = $visibles->count() === 1 ? $visibles->first()->id : null;
            }

            if ($field->type === 'instrument') {
                // La misma lista que ve el analista: la propia de la columna y,
                // si no declara ninguna, el catálogo entero (`instrumentsByField`
                // en el controlador hace ese mismo respaldo).
                $ofrecidos = $field->instruments;

                if ($ofrecidos->isEmpty()) {
                    $catalogo ??= Instrument::where('is_active', true)->pluck('id');
                    $unica = $catalogo->count() === 1 ? $catalogo->first() : null;
                } else {
                    $unica = $ofrecidos->count() === 1 ? $ofrecidos->first()->id : null;
                }
            }

            if ($unica === null) {
                continue;
            }

            $porReplica = [];
            for ($replica = 1; $replica <= max(1, (int) $field->replicates); $replica++) {
                $porReplica[$replica] = $unica;
            }

            $input[$field->code] = $porReplica;
        }

        return $input;
    }

    private function writeValues(WorksheetRow $row, $fields, array $input): void
    {
        $fueraDeRango = [];

        foreach ($fields as $field) {
            if (filled($field->formula)) {
                continue;   // lo calcula el servidor, no el formulario
            }

            $raw = $input[$field->code] ?? null;
            $perReplicate = is_array($raw) ? $raw : [1 => $raw];

            foreach ($perReplicate as $replicate => $value) {
                $replicate = max(1, (int) $replicate);

                if ($replicate > max(1, (int) $field->replicates)) {
                    continue;
                }

                $columnas = $this->typedValue($field, $value);

                // El rango declarado de la columna, aplicado. Hasta ahora
                // `min_value`/`max_value` vivían en la definición y no los leía
                // NADIE: se podía declarar que la rigidez va de 0 a 80 kV y
                // guardar 800. Y el caso que de verdad importa es el cero — en
                // varias propiedades no es una medición sino el "no medido" del
                // sistema anterior, que obligaba a llenar la celda.
                if ($columnas['value_num'] !== null) {
                    $motivo = $field->porQueNoAdmite((float) $columnas['value_num']);

                    if ($motivo !== null) {
                        $fueraDeRango[$field->code][] = $motivo;
                        continue;
                    }
                }

                WorksheetValue::updateOrCreate(
                    [
                        'worksheet_row_id' => $row->id,
                        'test_field_id'    => $field->id,
                        'replicate_no'     => $replicate,
                    ],
                    $columnas + [
                        'is_computed' => false,
                        'entered_by'  => auth()->id(),
                        'entered_at'  => now(),
                    ],
                );
            }
        }

        if ($fueraDeRango !== []) {
            // Se rechaza la fila entera y no solo la celda: guardar la mitad de
            // una medición deja un ensayo a medias que después nadie sabe leer.
            throw ValidationException::withMessages($fueraDeRango);
        }

        $row->unsetRelation('values');
    }

    /**
     * Reparte el valor en la columna que le corresponde según el tipo.
     *
     * La traducción vive en ValueCoercer y NO acá, porque la vista previa del
     * cálculo en vivo tiene que anticipar exactamente lo que este guardado va a
     * producir. Con el criterio escrito en dos lugares, la pantalla mostraría
     * un número mientras se escribe y otro después de guardar.
     *
     * Es la corrección directa del sistema viejo, que guardaba TODO —números,
     * fechas e incluso el id de la opción elegida— en una única columna de
     * texto llamada `name`.
     *
     * @return array<string,mixed>
     */
    private function typedValue(TestField $field, mixed $value): array
    {
        return $this->coercer->toColumns($field, $value);
    }

    /**
     * El código de la muestra sale del campo que DECLARA ser el código, no de
     * la primera columna.
     *
     * En el sistema viejo lo copiaba JavaScript desde el input `#col1`, con el
     * destino en solo lectura. Si el analista pegaba el código sin disparar el
     * evento del teclado, el campo quedaba vacío, el resultado nunca se
     * enlazaba con el informe y la celda salía en blanco sin ningún aviso.
     *
     * @param \Illuminate\Support\Collection<int,TestField> $fields
     * @param array<string,mixed> $attributes
     * @param array<string,mixed> $input
     */
    private function sampleCodeFrom(array $attributes, array $input, $fields, string $kind): ?string
    {
        if (array_key_exists('sample_code', $attributes)) {
            return $attributes['sample_code'];
        }

        // Un patrón, un duplicado o un blanco no son muestras de un cliente:
        // no llevan código.
        if ($kind !== WorksheetRow::KIND_SAMPLE) {
            return null;
        }

        $field = $fields->firstWhere('role', TestField::ROLE_SAMPLE_CODE);

        if ($field === null) {
            return null;
        }

        $raw = $input[$field->code] ?? null;
        $raw = is_array($raw) ? ($raw[1] ?? null) : $raw;

        return is_string($raw) && trim($raw) !== '' ? trim($raw) : null;
    }

    private function nextPosition(Worksheet $worksheet): int
    {
        return (int) $worksheet->rows()->max('position') + 1;
    }

    /**
     * Qué celdas obligatorias quedaron vacías.
     *
     * Es PÚBLICA porque la pantalla la necesita: guardar una fila incompleta
     * está permitido a propósito —el analista mide la rigidez a las diez, se va
     * y vuelve a la tarde— pero hasta ahora eso no se decía en ningún lado. El
     * analista guardaba, veía el tilde y creía que había terminado, cuando en
     * realidad la hoja no publica hasta que no falte ninguna.
     *
     * @return array<int,array{row:int,field:string}>
     */
    public function missingRequiredValues(Worksheet $worksheet): array
    {
        $required = $worksheet->definition->fields()
            ->where('is_required', true)
            ->get();

        if ($required->isEmpty()) {
            return [];
        }

        $missing = [];

        foreach ($worksheet->rows()->with('values')->get() as $row) {
            foreach ($required as $field) {
                // Un patrón, un duplicado o un blanco no son la muestra de un
                // cliente y no llevan código de muestra: sampleCodeFrom() ya lo
                // da por sentado y devuelve nulo para esas filas. Exigirlo acá
                // dejaba la hoja sin poder cerrarse, reclamando una celda que la
                // propia hoja no deja llenar.
                if ($field->role === TestField::ROLE_SAMPLE_CODE
                    && $row->kind !== WorksheetRow::KIND_SAMPLE) {
                    continue;
                }

                for ($r = 1; $r <= max(1, (int) $field->replicates); $r++) {
                    $value = $row->valueFor($field, $r);

                    if ($value === null || $value->isEmpty()) {
                        $missing[] = ['row' => $row->id, 'field' => $field->code];
                    }
                }
            }
        }

        return $missing;
    }

    /**
     * Vuelca los patrones y los duplicados de la hoja al control de calidad.
     *
     * Los puntos se guardan calculados y no se recalculan al dibujar: el z de
     * un punto tiene que quedar congelado contra los límites que regían el día
     * de la medición. El sistema viejo pisaba los límites al cambiar el lote
     * del patrón y las cartas históricas quedaban dibujadas contra los límites
     * de hoy, o sea contra un criterio que no era el de ese ensayo.
     */
    private function materializeQc(Worksheet $worksheet): void
    {
        $charts = QcChart::where('test_definition_id', $worksheet->test_definition_id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (QcChart $c) => $c->estabaVigenteAl($worksheet->run_date));

        if ($charts->isEmpty()) {
            return;
        }

        $rows = $worksheet->rows()->with('values.field')->get();
        $controls = $rows->where('kind', WorksheetRow::KIND_CONTROL);

        foreach ($charts as $chart) {
            $field = $chart->test_field_id;

            foreach ($controls as $row) {
                $value = $row->values->firstWhere('test_field_id', $field);

                if ($value === null || $value->value_num === null) {
                    continue;
                }

                $number = (float) $value->value_num;
                $verdict = $chart->classify($number);

                QcPoint::updateOrCreate(
                    ['qc_chart_id' => $chart->id, 'worksheet_row_id' => $row->id],
                    [
                        'measured_at' => $worksheet->run_date,
                        'value'       => $number,
                        'z_score'     => $verdict['z'] ?? null,
                        'flag'        => $verdict['flag'] ?? 'ok',
                        'tenant_id'   => $chart->tenant_id,
                    ],
                );
            }

            $this->evaluateSeries($chart);
            $this->materializeDuplicates($chart, $worksheet, $rows);
        }
    }

    /**
     * Corre las reglas de Westgard sobre la serie completa de la carta y
     * actualiza el veredicto de cada punto.
     *
     * Hace falta releer la serie entera porque las reglas miran hacia atrás:
     * un punto que hoy está dentro de todos los límites puede ser el décimo
     * seguido del mismo lado de la media, y recién ahí se vuelve un aviso.
     */
    private function evaluateSeries(QcChart $chart): void
    {
        $points = $chart->points()
            ->where('is_excluded', false)
            ->orderBy('measured_at')
            ->orderBy('id')
            ->get();

        $center = $chart->limits()['lc'] ?? null;
        $sd = $chart->sd !== null ? (float) $chart->sd : null;

        if ($center === null || $sd === null || $sd <= 0) {
            return;
        }

        $verdicts = $this->westgard->evaluate(
            $points->map(fn (QcPoint $p) => (float) $p->value)->all(),
            (float) $center,
            $sd,
        );

        foreach ($points as $i => $point) {
            $verdict = $verdicts[$i] ?? null;

            if ($verdict === null) {
                continue;
            }

            $point->forceFill([
                'z_score'       => $verdict['z'],
                // Manda el veredicto MÁS GRAVE de los dos, no el último en
                // escribirse. Son dos criterios distintos y ninguno reemplaza al
                // otro: los límites de la carta responden por el punto suelto
                // ("se pasó de la línea de alerta que declaró el laboratorio") y
                // las reglas de Westgard responden por la serie ("cuatro
                // seguidos del mismo lado"). Sin esto, Westgard pisaba lo
                // anterior y un punto a 2,4 desvíos —fuera de la línea de
                // alerta— se dibujaba en verde, porque no rompe ninguna regla
                // de la serie por sí solo.
                'flag'          => $this->peorBandera($point->flag, $verdict['flag']),
                'westgard_rule' => $verdict['rule'],
            ])->save();
        }
    }

    /** ok < warn < out. */
    private function peorBandera(?string $a, ?string $b): string
    {
        $orden = [QcPoint::FLAG_OK => 0, QcPoint::FLAG_WARN => 1, QcPoint::FLAG_OUT => 2];

        return ($orden[$a] ?? 0) >= ($orden[$b] ?? 0)
            ? ($a ?? QcPoint::FLAG_OK)
            : ($b ?? QcPoint::FLAG_OK);
    }

    /**
     * Empareja cada duplicado con su original y guarda la comparación.
     *
     * El sistema viejo obligaba a cargar duplicados y no los comparaba nunca:
     * el analista hacía el trabajo doble y el dato se perdía.
     *
     * @param \Illuminate\Support\Collection<int,WorksheetRow> $rows
     */
    private function materializeDuplicates(QcChart $chart, Worksheet $worksheet, $rows): void
    {
        $duplicates = $rows->where('kind', WorksheetRow::KIND_DUPLICATE);

        foreach ($duplicates as $duplicate) {
            // El duplicado se aparea por el código de muestra. Sin código no
            // hay con qué comparar: se informa, no se adivina.
            $original = $rows->first(
                fn (WorksheetRow $r) => $r->kind === WorksheetRow::KIND_SAMPLE
                    && $r->sample_code !== null
                    && $r->sample_code === $duplicate->sample_code
            );

            if ($original === null) {
                continue;
            }

            $a = $original->values->firstWhere('test_field_id', $chart->test_field_id)?->value_num;
            $b = $duplicate->values->firstWhere('test_field_id', $chart->test_field_id)?->value_num;

            $comparison = $this->repeatability->compare(
                $a === null ? null : (float) $a,
                $b === null ? null : (float) $b,
                $chart->repeatability_limit === null ? null : (float) $chart->repeatability_limit,
                $chart->repeatability_mode ?? RepeatabilityEvaluator::MODE_ABSOLUTE,
            );

            QcDuplicate::updateOrCreate(
                [
                    'qc_chart_id'      => $chart->id,
                    'original_row_id'  => $original->id,
                    'duplicate_row_id' => $duplicate->id,
                ],
                [
                    'measured_at'         => $worksheet->run_date,
                    'value_a'             => $a,
                    'value_b'             => $b,
                    'difference'          => $comparison['difference'],
                    'relative_difference' => $comparison['relative'],
                    'within_limit'        => $comparison['within'],
                    'tenant_id'           => $chart->tenant_id,
                ],
            );
        }
    }
}
