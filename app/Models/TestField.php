<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TestField — una columna de la hoja de trabajo de una prueba.
 *
 * `output_analyte_id` es lo que el sistema viejo no tenía: declara que este
 * campo ES un resultado y a qué parámetro alimenta. Sin eso, el informe tomaba
 * "la última columna por posición" y asumía que era el resultado.
 *
 * `role` completa esa idea para las demás columnas con significado. El sistema
 * Rails viejo deducía tres cosas de la POSICIÓN —la primera columna era el
 * número de muestra, la segunda la norma y la última el resultado—, sin
 * declararlo en ningún lado. De ahí el aviso en mayúsculas de su README ("LA
 * COLUMNA RESULTADO SIEMPRE ES LA ÚLTIMA"): insertar una columna en el medio
 * rompía en silencio el enlace con la muestra, la norma del informe y el
 * gráfico de tendencias. Con el rol declarado, ordenar las columnas vuelve a
 * ser lo que siempre debió ser: una decisión estética.
 */
class TestField extends Model
{
    use HasFactory, SoftDeletes;

    /** Columna común de la bancada: se carga y se guarda, nada más. */
    public const ROLE_NONE = 'none';

    /** El código de la muestra; es lo que enlaza la fila con el ensayo. */
    public const ROLE_SAMPLE_CODE = 'sample_code';

    /** La norma con la que se ensayó (ASTM D877, D1816…). */
    public const ROLE_STANDARD = 'standard';

    /** Un resultado. Además lleva `output_analyte_id`. */
    public const ROLE_RESULT = 'result';

    /** Temperatura del ensayo: es una condición, no un resultado. */
    public const ROLE_TEMPERATURE = 'temperature';

    /** Observación del analista. */
    public const ROLE_OBSERVATION = 'observation';

    /** @var array<int,string> */
    public const ROLES = [
        self::ROLE_NONE,
        self::ROLE_SAMPLE_CODE,
        self::ROLE_STANDARD,
        self::ROLE_RESULT,
        self::ROLE_TEMPERATURE,
        self::ROLE_OBSERVATION,
    ];

    /** @var array<int,string> Valores admitidos en `type`. */
    public const TYPES = ['text', 'number', 'select', 'date', 'computed', 'instrument'];

    public const TYPE_COMPUTED = 'computed';

    protected $table = 'test_fields';
    protected $guarded = [];
    protected $casts = [
        'is_required'    => 'boolean',
        'is_locked'      => 'boolean',
        'is_reusable'    => 'boolean',
        'report_visible' => 'boolean',
        'sort_order'     => 'integer',
        'legacy_id'      => 'integer',
        // Cuántas réplicas admite la columna. Entero y no cadena porque se
        // recorre para armar las celdas de la hoja (1..replicates).
        'replicates'     => 'integer',
        'decimals'       => 'integer',
        // Cota inferior ABIERTA: el mínimo no se admite, solo lo que lo supere.
        // Es el caso del cero, que en varias propiedades no es una medición
        // sino el "no medido" del sistema anterior.
        'min_exclusive'  => 'boolean',
        'locked_at'      => 'datetime',
    ];

    /**
     * Por qué este valor no entra en la columna, o null si entra.
     *
     * El rango ya vivía en la definición de la columna y NO lo leía nadie: se
     * podía declarar que la rigidez va de 0 a 80 kV y guardar 800 igual. Acá es
     * donde el rango pasa a significar algo.
     */
    public function porQueNoAdmite(float $valor): ?string
    {
        $formato = fn (float $n) => rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');

        if ($this->min_value !== null) {
            $min = (float) $this->min_value;

            if ($this->min_exclusive && $valor <= $min) {
                return __('worksheets.errors.value_not_above', [
                    'field' => $this->label,
                    'min'   => $formato($min),
                ]);
            }

            if (! $this->min_exclusive && $valor < $min) {
                return __('worksheets.errors.value_below_min', [
                    'field' => $this->label,
                    'min'   => $formato($min),
                ]);
            }
        }

        if ($this->max_value !== null && $valor > (float) $this->max_value) {
            return __('worksheets.errors.value_above_max', [
                'field' => $this->label,
                'max'   => $formato((float) $this->max_value),
            ]);
        }

        return null;
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(TestDefinition::class, 'test_definition_id');
    }

    /**
     * Los instrumentos que ESTA columna ofrece.
     *
     * Sin esto la grilla ofrecía todos los equipos del laboratorio en todas las
     * columnas de equipo, y en la columna "Bureta" del Número Ácido aparecía el
     * Colorímetro. El dato existía en la plantilla del sistema anterior —cada
     * columna declaraba sus códigos— y se estaba descartando al importar.
     *
     * Vacío significa "cualquiera": es lo correcto para las columnas de equipo
     * que el sistema anterior dejó como texto libre y no declaran nada.
     */
    public function instruments(): BelongsToMany
    {
        return $this->belongsToMany(Instrument::class, 'test_field_instrument')
            ->withPivot(['is_default', 'sort_order'])
            ->orderBy('instruments.code');
    }

    public function options(): HasMany
    {
        return $this->hasMany(TestFieldOption::class)->orderBy('sort_order');
    }

    public function analyte(): BelongsTo
    {
        return $this->belongsTo(Analyte::class, 'output_analyte_id');
    }

    /** Valores de este campo en todas las hojas de trabajo. */
    public function values(): HasMany
    {
        return $this->hasMany(WorksheetValue::class);
    }

    public function scopeRole(Builder $query, string $role): Builder
    {
        return $query->where('test_fields.role', $role);
    }

    public function scopeResults(Builder $query): Builder
    {
        return $query->where('test_fields.role', self::ROLE_RESULT);
    }

    /**
     * El campo lo calcula el servidor y no el analista. Manda la fórmula por
     * sobre el `type`: en la importación desde el sistema viejo hay columnas que
     * traen fórmula sin estar rotuladas como calculadas, porque allá el cálculo
     * era JavaScript pegado en `blur_calculation` y el tipo seguía siendo el
     * original. Si tiene fórmula, es calculado.
     */
    public function isComputed(): bool
    {
        return $this->type === self::TYPE_COMPUTED
            || (is_string($this->formula) && trim($this->formula) !== '');
    }

    /**
     * Se acepta cualquiera de las dos declaraciones. El rol es lo que se edita
     * en la plantilla; `output_analyte_id` es lo que hace falta para materializar
     * el resultado. Un campo con parámetro asignado y el rol sin actualizar sigue
     * siendo un resultado.
     */
    public function isResult(): bool
    {
        return $this->role === self::ROLE_RESULT || $this->output_analyte_id !== null;
    }

    /** El campo que enlaza la fila con la muestra del cliente. */
    public function isSampleCode(): bool
    {
        return $this->role === self::ROLE_SAMPLE_CODE;
    }

    public function isStandard(): bool
    {
        return $this->role === self::ROLE_STANDARD;
    }

    /** Cuántas veces se repite la medición en esta columna. Nunca menos de una. */
    public function replicateCount(): int
    {
        return max(1, (int) ($this->replicates ?? 1));
    }
}
