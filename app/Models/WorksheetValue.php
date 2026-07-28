<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WorksheetValue — el valor de una columna en una fila de la hoja de trabajo.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ TRES COLUMNAS DE VALOR Y NO UNA                                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema Rails viejo TODO se guardaba como texto en una sola columna,
 * `lab_sub_details.name`: el número medido, la observación del analista y hasta
 * el id de la opción elegida en un campo de tipo select. De ahí salían tres
 * problemas que no se podían arreglar sin separar las columnas:
 *
 *   - Ordenar o promediar exigía convertir el texto en cada consulta, y un valor
 *     tipeado con coma o con un espacio de más se convertía a cero en silencio.
 *   - Un select guardaba el ID de la opción, no su texto; si alguien editaba la
 *     lista de opciones, los ensayos ya cargados pasaban a leer otra cosa.
 *   - No había forma de distinguir "no se midió" de "se midió y dio cero", que
 *     en un ensayo son afirmaciones muy distintas.
 *
 * Acá cada tipo de valor tiene su columna y `resolved` decide cuál corresponde.
 */
class WorksheetValue extends Model
{
    use HasFactory;

    protected $table = 'worksheet_values';

    protected $fillable = [
        'worksheet_row_id', 'test_field_id', 'replicate_no',
        'value_num', 'value_text', 'option_id', 'instrument_id', 'qualifier',
        'is_computed', 'entered_by', 'entered_at', 'legacy_id',
    ];

    /**
     * Valor censurado: el instrumento no pudo dar el número exacto.
     *   gt  mayor que value_num — el ensayador llegó a su tope sin que el
     *       aceite rompiera. Una rigidez de ">75 kV" no es 75.
     *   lt  menor que value_num — por debajo del límite de detección del
     *       método. Un furano "0.000 BDL" no es cero: es "no se detectó".
     */
    public const QUALIFIER_GT = 'gt';
    public const QUALIFIER_LT = 'lt';

    /**
     * `value_num` se castea a decimal (cadena) y NO a float a propósito. La
     * columna es decimal(24,8): un float de PHP tiene entre 15 y 17 cifras
     * significativas, así que castearlo redondearía en silencio lo que cargó el
     * analista, y este registro es la constancia legal de lo que se midió. Lo
     * que necesita el número lo convierte explícitamente
     * (FormulaEvaluator::toNumber, que ya acepta la cadena numérica).
     *
     * El criterio inverso vale para los límites y estadísticos de las cartas de
     * control (QcChart, QcPoint::$z_score): esos existen SOLO para calcular, y
     * ahí sí se castea a float.
     */
    protected $casts = [
        'replicate_no' => 'integer',
        'value_num'    => 'decimal:8',
        'is_computed'  => 'boolean',
        'entered_at'   => 'datetime',
        'legacy_id'    => 'integer',
    ];

    public function row(): BelongsTo
    {
        return $this->belongsTo(WorksheetRow::class, 'worksheet_row_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(TestField::class, 'test_field_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(TestFieldOption::class, 'option_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by')->withTrashed();
    }

    /**
     * El valor que corresponde según cómo se cargó, en el orden en que las
     * columnas son concluyentes: el número, después la opción elegida y por
     * último el texto libre.
     *
     * Devuelve el TEXTO de la opción y no su id, que es la corrección directa
     * del sistema viejo: la opción se guarda por FK y se lee por texto, así que
     * reordenar o renombrar la lista de opciones ya no cambia lo que dice un
     * ensayo cerrado.
     *
     * No se declara en $appends: la opción es una relación y agregarla al
     * serializado dispararía una consulta por celda. Quien lo use en un listado
     * debe traer `option` con eager loading.
     */
    public function getResolvedAttribute(): float|string|null
    {
        if ($this->value_num !== null) {
            return (float) $this->value_num;
        }

        if ($this->option_id !== null) {
            return $this->option?->value;
        }

        return $this->value_text;
    }

    /** ¿Hay algo cargado? Distinto de "cargado en cero", que sí es un dato. */
    public function isEmpty(): bool
    {
        return $this->value_num === null
            && $this->option_id === null
            && $this->instrument_id === null
            && ($this->value_text === null || trim($this->value_text) === '');
    }

    /** ¿El instrumento no pudo dar el número exacto? */
    public function isCensored(): bool
    {
        return $this->qualifier !== null;
    }

    /**
     * Cómo se muestra un valor censurado: ">75", "<0.05".
     *
     * El signo va SIEMPRE, incluso en el informe. Publicar 75 a secas donde el
     * ensayo dice "más de 75" convierte una cota en una medición, y ahí se
     * pierde la única información que el instrumento sí dio.
     */
    public function getDisplayAttribute(): float|string|null
    {
        $value = $this->resolved;

        if ($this->qualifier === null || $value === null) {
            return $value;
        }

        return ($this->qualifier === self::QUALIFIER_GT ? '>' : '<') . $value;
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class, 'instrument_id');
    }
}
