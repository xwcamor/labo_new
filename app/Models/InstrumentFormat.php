<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenantOrGlobal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * InstrumentFormat — cómo se lee el archivo que emite un equipo de medición.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL MAPEO ES UN DATO, NO UNA CONSULTA ESCRITA A MANO                      │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema Rails viejo la correspondencia entre las columnas del archivo y
 * los campos de la hoja estaba clavada en SQL, con los identificadores escritos
 * literalmente y acompañada de un comentario en mayúsculas que decía
 * "DONT MOVE FURANOS COLUMN ORDER":
 *
 *     update lab_file_details SET name = substring_index(name, ' ', 1)
 *     WHERE lab_category_sub_detail_id IN (80, 81, 82, 83, 84)
 *
 * Esos cinco números son las cinco columnas de furanos, identificadas por id. El
 * aviso no era una advertencia de estilo: reordenar las columnas de la plantilla
 * de furanos hacía que la importación escribiera cada resultado en el campo
 * equivocado, con los valores igual de verosímiles y sin ningún error. Y el
 * mapeo solo existía para ese formato, así que cada equipo nuevo pedía otra
 * consulta más en el código.
 *
 * Acá el formato es una fila: `column_map` dice de qué columna del archivo sale
 * cada `code` de campo y qué transformación se le aplica (recortar, tomar el
 * primer token —lo que hacía aquel substring_index—, multiplicar por un factor).
 * Sumar un equipo es cargar una fila, y reordenar la plantilla no rompe nada
 * porque el vínculo es por código, no por posición.
 */
class InstrumentFormat extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenantOrGlobal;

    protected string $auditModule = 'instrument_formats';

    protected $table = 'instrument_formats';

    /** Separado por un carácter (CSV, TSV, el volcado de una impresora serie). */
    public const KIND_DELIMITED = 'delimited';

    /** Ancho fijo por columna. */
    public const KIND_FIXED = 'fixed';

    /** Planilla de cálculo. */
    public const KIND_XLSX = 'xlsx';

    /** @var array<int,string> */
    public const KINDS = [
        self::KIND_DELIMITED,
        self::KIND_FIXED,
        self::KIND_XLSX,
    ];

    protected $fillable = [
        'slug', 'code', 'name', 'test_definition_id', 'kind', 'delimiter',
        'header_row', 'first_data_row', 'encoding', 'column_map', 'is_active',
        'tenant_id',
    ];

    protected $casts = [
        'column_map'     => 'array',
        'is_active'      => 'boolean',
        'header_row'     => 'integer',
        'first_data_row' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                do {
                    $slug = Str::random(22);
                } while (static::withTrashed()->where('slug', $slug)->exists());
                $model->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(TestDefinition::class, 'test_definition_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(InstrumentFile::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('instrument_formats.is_active', true);
    }

    /**
     * Las reglas de mapeo, normalizadas a una lista. Se admiten las dos formas en
     * las que resulta natural cargarlo, porque el editor de formatos todavía no
     * existe y el importador de la fase 4 escribe el JSON a mano:
     *
     *   [{"source": "H2", "code": "h2", "transform": "first_token"}, ...]
     *   {"H2": "h2", "CH4": "ch4"}                      (atajo sin transformación)
     *
     * @return array<int,array{source:string,code:string,transform:?string,factor:?float}>
     */
    public function mappings(): array
    {
        $map = $this->column_map;
        if (! is_array($map) || $map === []) {
            return [];
        }

        $rules = [];

        foreach ($map as $key => $entry) {
            if (is_string($entry)) {
                $rules[] = [
                    'source'    => (string) $key,
                    'code'      => $entry,
                    'transform' => null,
                    'factor'    => null,
                ];
                continue;
            }

            if (! is_array($entry)) {
                continue;
            }

            $source = $entry['source'] ?? $entry['column'] ?? (is_string($key) ? $key : null);
            $code   = $entry['code'] ?? $entry['field'] ?? null;
            if (! is_string($source) || ! is_string($code) || $code === '') {
                continue;
            }

            $rules[] = [
                'source'    => $source,
                'code'      => $code,
                'transform' => isset($entry['transform']) ? (string) $entry['transform'] : null,
                'factor'    => isset($entry['factor']) && is_numeric($entry['factor'])
                    ? (float) $entry['factor']
                    : null,
            ];
        }

        return $rules;
    }

    /** A qué campo de la plantilla va una columna del archivo. */
    public function fieldCodeFor(string $sourceColumn): ?string
    {
        foreach ($this->mappings() as $rule) {
            if (strcasecmp($rule['source'], $sourceColumn) === 0) {
                return $rule['code'];
            }
        }

        return null;
    }
}
