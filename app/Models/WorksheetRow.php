<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * WorksheetRow — una fila de la hoja de trabajo.
 *
 * `kind` es lo que sostiene el control de calidad analítica: separa la muestra
 * del cliente del material de control con el que se demuestra que la corrida fue
 * válida. En el sistema Rails viejo era `lab_detail_type_id` con tres valores
 * numéricos sin tabla que los explicara (1 = Patrón, 2 = Muestra, 3 = Duplicado);
 * acá son cadenas legibles y el blanco de reactivos se agrega, porque el
 * laboratorio lo corre igual y no tenía dónde registrarlo.
 *
 * La fila NO lleva `tenant_id` ni candado propio: hereda los de su hoja. El
 * alcance se resuelve entrando siempre por Worksheet, que sí está scopeada.
 */
class WorksheetRow extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'worksheet_rows';

    /** Muestra del cliente. */
    public const KIND_SAMPLE = 'sample';

    /** Patrón de referencia: alimenta la carta de control. */
    public const KIND_CONTROL = 'control';

    /** Repetición de la misma muestra: alimenta la repetibilidad. */
    public const KIND_DUPLICATE = 'duplicate';

    /** Blanco de reactivos. No existía en el sistema viejo. */
    public const KIND_BLANK = 'blank';

    /** @var array<int,string> */
    public const KINDS = [
        self::KIND_SAMPLE,
        self::KIND_CONTROL,
        self::KIND_DUPLICATE,
        self::KIND_BLANK,
    ];

    protected $fillable = [
        'worksheet_id', 'kind', 'sample_code', 'sample_id', 'sample_test_id',
        'equipment_id',
        'position', 'instrument_id', 'instrument_file_id', 'notes', 'legacy_id',
    ];

    protected $casts = [
        'position'   => 'integer',
        'legacy_id'  => 'integer',
    ];

    /**
     * De qué equipo del cliente es la muestra de esta fila.
     *
     * Sin esto, el resultado no se puede consultar por equipo, que es la única
     * pregunta para la que existe la capa `results`. En el destino final el
     * equipo viene de la MUESTRA (fase 3); mientras tanto la fila lo apunta
     * directamente. Ver el docblock de la migración que agregó la columna.
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    /** Los resultados que produjo esta fila al validarse la hoja. */
    public function results(): HasMany
    {
        return $this->hasMany(Result::class, 'worksheet_row_id');
    }

    // ── Relaciones ───────────────────────────────────────────────────────

    public function worksheet(): BelongsTo
    {
        return $this->belongsTo(Worksheet::class);
    }

    /** Con qué equipo se midió esta fila (ISO 17025). */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    /** De qué archivo crudo salió la fila, si vino importada. */
    public function file(): BelongsTo
    {
        return $this->belongsTo(InstrumentFile::class, 'instrument_file_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(WorksheetValue::class);
    }

    // ── Scopes por tipo de fila ──────────────────────────────────────────

    public function scopeKind(Builder $query, string $kind): Builder
    {
        return $query->where('worksheet_rows.kind', $kind);
    }

    public function scopeSamples(Builder $query): Builder
    {
        return $query->where('worksheet_rows.kind', self::KIND_SAMPLE);
    }

    public function scopeControls(Builder $query): Builder
    {
        return $query->where('worksheet_rows.kind', self::KIND_CONTROL);
    }

    public function scopeDuplicates(Builder $query): Builder
    {
        return $query->where('worksheet_rows.kind', self::KIND_DUPLICATE);
    }

    public function scopeBlanks(Builder $query): Builder
    {
        return $query->where('worksheet_rows.kind', self::KIND_BLANK);
    }

    // ── Acceso a los valores ─────────────────────────────────────────────

    /**
     * El valor de una columna en esta fila. Se resuelve sobre la relación ya
     * cargada cuando la hay: la pantalla de la hoja recorre todas las celdas y
     * una consulta por celda sería una consulta por columna por fila.
     */
    public function valueFor(TestField $field, int $replicate = 1): ?WorksheetValue
    {
        if ($this->relationLoaded('values')) {
            return $this->getRelation('values')->first(
                fn (WorksheetValue $value): bool => (int) $value->test_field_id === (int) $field->id
                    && (int) $value->replicate_no === $replicate
            );
        }

        return $this->values()
            ->where('test_field_id', $field->id)
            ->where('replicate_no', $replicate)
            ->first();
    }

    /**
     * Mapa `código de campo => valor`, en el formato que consume
     * App\Services\Lab\FormulaResolver::resolveAll(): claves por CÓDIGO, no por
     * posición ni por id del DOM. El resolver convierte a número lo que puede y
     * deja en nulo lo demás, así que se le entrega el valor tipado tal cual
     * (WorksheetValue::$resolved) sin normalizar nada de este lado.
     *
     * Se devuelve una réplica por vez. El promedio de una medición repetida no
     * es un caso de este mapa sino un campo calculado con su propia fórmula: si
     * se mezclaran las réplicas en una sola clave, el resolver evaluaría la
     * fórmula contra una de ellas al azar, que es justamente el comportamiento
     * dependiente del orden que el resolver existe para eliminar.
     *
     * LAS COLUMNAS DE VALOR ÚNICO VALEN PARA TODAS LAS RÉPLICAS. El factor de
     * la solución titulante y el peso de la muestra se cargan una sola vez y se
     * aplican a las cinco mediciones de rigidez; no son un dato distinto por
     * medición. Sin este respaldo, la réplica 2 veía ese factor en nulo y su
     * resultado salía vacío: la primera medición calculaba y las demás no.
     *
     * @return array<string,mixed>
     */
    public function valuesByFieldCode(int $replicate = 1): array
    {
        $values = $this->relationLoaded('values')
            ? $this->getRelation('values')
            : $this->values()->with('field')->get();

        $map = [];
        $respaldo = [];

        foreach ($values as $value) {
            $code = $value->field?->code;
            if (! is_string($code) || $code === '') {
                continue;
            }

            $suya = (int) $value->replicate_no;

            if ($suya === $replicate) {
                $map[$code] = $value->resolved;
                continue;
            }

            // La réplica 1 es el respaldo de las demás, y solo ella: si una
            // columna tiene tres mediciones cargadas, la réplica 2 usa la 2, no
            // la 1. El respaldo cubre el caso de la columna de valor único.
            if ($suya === 1) {
                $respaldo[$code] = $value->resolved;
            }
        }

        foreach ($respaldo as $code => $valor) {
            $map[$code] ??= $valor;
        }

        return $map;
    }
}
