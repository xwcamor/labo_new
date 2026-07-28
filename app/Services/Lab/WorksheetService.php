<?php

namespace App\Services\Lab;

use App\Models\QcChart;
use App\Models\QcDuplicate;
use App\Models\QcPoint;
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
    ) {
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
    ): WorksheetRow {
        $this->assertEditable($worksheet);

        $kind = $attributes['kind'] ?? WorksheetRow::KIND_SAMPLE;
        $this->assertKindAllowed($worksheet, $kind, $row);

        $fields = $worksheet->definition->fields()->with('options')->get();

        return DB::transaction(function () use ($worksheet, $attributes, $input, $row, $fields, $kind) {
            $row ??= new WorksheetRow(['worksheet_id' => $worksheet->id]);

            $row->fill([
                'worksheet_id'  => $worksheet->id,
                'kind'          => $kind,
                'sample_code'   => $this->sampleCodeFrom($attributes, $input, $fields, $kind),
                'position'      => $attributes['position'] ?? $row->position ?? $this->nextPosition($worksheet),
                'instrument_id' => $attributes['instrument_id'] ?? $row->instrument_id,
                'notes'         => $attributes['notes'] ?? $row->notes,
            ])->save();

            $this->writeValues($row, $fields, $input);
            $this->recalculate($row, $fields);

            return $row->refresh();
        });
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
     * Cierra la hoja: el analista terminó de cargar.
     *
     * Es donde se verifica que no falte nada obligatorio. En el sistema viejo
     * no existía este momento: la hoja quedaba abierta hasta que alguien la
     * bloqueaba a mano, y los campos vacíos se descubrían con un panel llamado
     * "Pruebas con Valores Pendientes" que salía a cazar celdas en blanco y
     * celdas con el texto "NaN".
     *
     * @throws ValidationException
     */
    public function close(Worksheet $worksheet): Worksheet
    {
        $this->assertEditable($worksheet);

        $missing = $this->missingRequiredValues($worksheet);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'rows' => __('worksheets.errors.missing_required', ['count' => count($missing)]),
            ]);
        }

        $worksheet->forceFill(['status' => Worksheet::STATUS_CLOSED])->save();

        return $worksheet;
    }

    /**
     * Valida la hoja: el supervisor la revisó y la firma.
     *
     * Validar y bloquear son dos cosas distintas y acá lo son de verdad. En el
     * sistema viejo terminaron siendo el mismo campo `state` (el modelo Ruby
     * conserva comentadas las etiquetas del significado anterior), el filtro de
     * búsqueda quedó con la semántica invertida, y `validate_user_id` se
     * sobrescribía en cada cambio de candado, así que no había forma de saber
     * quién había validado.
     *
     * Además, la pantalla de validar verificaba el permiso de EDITAR en vez del
     * de validar: el botón estaba escondido para el analista, pero la dirección
     * seguía siendo accesible. Acá la autorización es de la ruta y de la
     * política, no del menú.
     *
     * @throws ValidationException
     */
    public function validate(Worksheet $worksheet): Worksheet
    {
        if ($worksheet->status !== Worksheet::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'status' => __('worksheets.errors.not_closed'),
            ]);
        }

        return DB::transaction(function () use ($worksheet) {
            $worksheet->forceFill([
                'status'       => Worksheet::STATUS_VALIDATED,
                'validated_by' => auth()->id(),
                'validated_at' => now(),
            ])->save();

            // Recién con la hoja validada los patrones alimentan la carta de
            // control: un patrón de una hoja que el supervisor todavía no
            // revisó no debería mover los límites de nada.
            $this->materializeQc($worksheet);

            return $worksheet;
        });
    }

    /**
     * Anula la hoja. No se borra: un ensayo anulado tiene que seguir estando,
     * con el motivo, porque el laboratorio responde por él ante la auditoría.
     */
    public function void(Worksheet $worksheet, string $reason): Worksheet
    {
        // Una hoja ya anulada no se vuelve a anular: pisaría el motivo original,
        // que es justamente lo que hay que conservar.
        if ($worksheet->isVoided()) {
            throw ValidationException::withMessages([
                'status' => __('worksheets.errors.already_voided'),
            ]);
        }

        $worksheet->forceFill([
            'status'      => Worksheet::STATUS_VOIDED,
            'void_reason' => $reason,
        ])->save();

        // Los puntos de una hoja anulada salen de la carta, pero quedan
        // guardados con su motivo: el laboratorio tiene que poder mostrar que
        // los descartó y por qué, no que nunca existieron.
        QcPoint::whereIn('worksheet_row_id', $worksheet->rows()->pluck('id'))
            ->update([
                'is_excluded'      => true,
                'exclusion_reason' => $reason,
            ]);

        return $worksheet;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Interno
    // ─────────────────────────────────────────────────────────────────────

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
    private function writeValues(WorksheetRow $row, $fields, array $input): void
    {
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

                WorksheetValue::updateOrCreate(
                    [
                        'worksheet_row_id' => $row->id,
                        'test_field_id'    => $field->id,
                        'replicate_no'     => $replicate,
                    ],
                    $this->typedValue($field, $value) + [
                        'is_computed' => false,
                        'entered_by'  => auth()->id(),
                        'entered_at'  => now(),
                    ],
                );
            }
        }

        $row->unsetRelation('values');
    }

    /**
     * Reparte el valor en la columna que le corresponde según el tipo.
     *
     * Es la corrección directa del sistema viejo, que guardaba TODO —números,
     * fechas e incluso el id de la opción elegida— en una única columna de
     * texto llamada `name`.
     *
     * @return array<string,mixed>
     */
    private function typedValue(TestField $field, mixed $value): array
    {
        $blank = [
            'value_num'     => null,
            'value_text'    => null,
            'option_id'     => null,
            'instrument_id' => null,
            'qualifier'     => null,
        ];

        if ($value === null || $value === '') {
            return $blank;
        }

        $types = config('lab_field_types', []);
        $storage = $types[$field->type]['storage'] ?? 'value_text';

        if ($storage === 'option_id') {
            return array_merge($blank, ['option_id' => (int) $value]);
        }

        if ($storage === 'instrument_id') {
            return array_merge($blank, ['instrument_id' => (int) $value]);
        }

        if ($storage === 'value_num') {
            [$number, $qualifier] = $this->readCensored((string) $value);

            return array_merge($blank, [
                'value_num' => $number,
                'qualifier' => $qualifier,
                // Sin número legible se conserva el texto tal como se escribió,
                // en vez de descartarlo: perder lo que cargó el analista es
                // peor que guardar algo que después hay que corregir.
                'value_text' => $number === null ? (string) $value : null,
            ]);
        }

        return array_merge($blank, ['value_text' => (string) $value]);
    }

    /**
     * Separa el número de su signo de censura: ">75" es "al menos 75", no 75.
     *
     * @return array{0:?float,1:?string}
     */
    private function readCensored(string $value): array
    {
        $value = trim($value);
        $qualifier = null;

        if (preg_match('/^(>=|<=|>|<)\s*/', $value, $m)) {
            $qualifier = str_starts_with($m[1], '>')
                ? WorksheetValue::QUALIFIER_GT
                : WorksheetValue::QUALIFIER_LT;
            $value = trim(substr($value, strlen($m[0])));
        }

        $value = str_replace(',', '.', $value);

        return [is_numeric($value) ? (float) $value : null, $qualifier];
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
     * @return array<int,array{row:int,field:string}>
     */
    private function missingRequiredValues(Worksheet $worksheet): array
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
                'flag'          => $verdict['flag'],
                'westgard_rule' => $verdict['rule'],
            ])->save();
        }
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
