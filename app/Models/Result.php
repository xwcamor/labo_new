<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Result — un parámetro medido sobre un equipo en una fecha.
 *
 * Es la capa que consultan el informe, las tendencias, el tablero y la API
 * hacia TrafoDex. La constancia de lo que hizo el analista vive en
 * `worksheet_values`; esto es su lectura tipada, y se puede reconstruir entero
 * desde allá. El razonamiento completo está en la migración
 * `create_results_table`.
 *
 * REGLA DE ORO AL CONSULTAR: entrar siempre por `equipment_id` y `analyte_id`.
 * Los índices están armados para eso. Una consulta que filtre por el texto de
 * la prueba o por la posición de una columna está usando la tabla equivocada:
 * eso se pregunta en la plantilla, no acá.
 */
class Result extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'results';

    /** Dentro de norma. */
    public const SPEC_IN = 'in_spec';
    /** Cumple, pero pegado al límite. Es el ámbar del semáforo. */
    public const SPEC_NEAR = 'near_limit';
    /** Fuera de norma. */
    public const SPEC_OUT = 'out_of_spec';

    public const SPEC_STATUSES = [self::SPEC_IN, self::SPEC_NEAR, self::SPEC_OUT];

    /** El instrumento llegó a su tope: el valor es AL MENOS `value_num`. */
    public const QUALIFIER_GT = 'gt';
    /** Por debajo del límite de detección: NO es cero. */
    public const QUALIFIER_LT = 'lt';

    protected $fillable = [
        'tenant_id', 'equipment_id', 'analyte_id', 'measured_at',
        'worksheet_row_id', 'test_definition_id', 'test_field_id', 'sample_id',
        'value_num', 'value_text', 'qualifier', 'unit', 'replicate_no',
        'spec_status', 'spec_min', 'spec_max', 'spec_display', 'spec_source',
    ];

    /**
     * `value_num` queda como decimal (cadena) y no como float por la misma
     * razón que en WorksheetValue: la columna es decimal(24,8) y un float de
     * PHP tiene entre 15 y 17 cifras significativas, así que castearlo
     * redondearía en silencio un resultado que va a un certificado. Los
     * límites de norma sí van a float: existen solo para entrar en una
     * comparación.
     */
    protected $casts = [
        'measured_at'  => 'date',
        'value_num'    => 'decimal:8',
        'spec_min'     => 'float',
        'spec_max'     => 'float',
        'replicate_no' => 'integer',
    ];

    // ── Relaciones ───────────────────────────────────────────────────────

    public function analyte(): BelongsTo
    {
        return $this->belongsTo(Analyte::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(TestDefinition::class, 'test_definition_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(TestField::class, 'test_field_id');
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(WorksheetRow::class, 'worksheet_row_id');
    }

    // ── Consultas ────────────────────────────────────────────────────────

    /**
     * La serie histórica de un parámetro de un equipo.
     *
     * Es la consulta más frecuente del sistema y la que dicta el orden de
     * columnas del índice `idx_results_equipo_parametro_fecha`: las dos
     * igualdades primero y el rango de fecha al final, que es lo único que
     * permite resolver el filtro y el orden en un solo recorrido del índice.
     */
    public function scopeTrend(Builder $query, int $equipmentId, int $analyteId): Builder
    {
        return $query->where('equipment_id', $equipmentId)
            ->where('analyte_id', $analyteId)
            ->orderBy('measured_at');
    }

    /** Todos los parámetros de un equipo, del más nuevo al más viejo. */
    public function scopeForEquipment(Builder $query, int $equipmentId): Builder
    {
        return $query->where('equipment_id', $equipmentId)
            ->orderByDesc('measured_at');
    }

    public function scopeOutOfSpec(Builder $query): Builder
    {
        return $query->where('spec_status', self::SPEC_OUT);
    }

    public function scopeMeasuredBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('measured_at', [$from, $to]);
    }

    // ── Lectura ──────────────────────────────────────────────────────────

    /** ¿El instrumento no pudo dar el número exacto? */
    public function isCensored(): bool
    {
        return $this->qualifier !== null;
    }

    /**
     * Cómo se imprime el valor: ">75", "<0.05", "12.34".
     *
     * El signo va SIEMPRE, también en el certificado. Publicar 75 a secas donde
     * el ensayo dice "más de 75" convierte una cota en una medición y pierde la
     * única información que el instrumento sí dio.
     */
    public function getDisplayAttribute(): ?string
    {
        if ($this->value_num === null) {
            return $this->value_text;
        }

        $decimals = $this->analyte?->decimals;
        $number = $decimals === null
            ? rtrim(rtrim((string) $this->value_num, '0'), '.')
            : number_format((float) $this->value_num, (int) $decimals, '.', '');

        return match ($this->qualifier) {
            self::QUALIFIER_GT => '>' . $number,
            self::QUALIFIER_LT => '<' . $number,
            default            => $number,
        };
    }

    /**
     * Un valor censurado NO entra en promedios ni en tendencias.
     *
     * Un ">75" tratado como 75 baja el promedio de una serie de aceites sanos,
     * y un "no detectado" tratado como 0 hace que un aceite sin furanos y uno
     * con furanos justo bajo el límite se vean iguales. Quien agrega decide qué
     * hacer con ellos, pero tiene que poder distinguirlos primero.
     */
    public function isUsableInStatistics(): bool
    {
        return $this->value_num !== null && $this->qualifier === null;
    }
}
