<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TestField — una columna de la hoja de trabajo de una prueba.
 *
 * `output_analyte_id` es lo que el sistema viejo no tenía: declara que este
 * campo ES un resultado y a qué parámetro alimenta. Sin eso, el informe tomaba
 * "la última columna por posición" y asumía que era el resultado.
 */
class TestField extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'test_fields';
    protected $guarded = [];
    protected $casts = [
        'is_required'    => 'boolean',
        'is_locked'      => 'boolean',
        'is_reusable'    => 'boolean',
        'report_visible' => 'boolean',
        'sort_order'     => 'integer',
        'legacy_id'      => 'integer',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(TestDefinition::class, 'test_definition_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(TestFieldOption::class)->orderBy('sort_order');
    }

    public function analyte(): BelongsTo
    {
        return $this->belongsTo(Analyte::class, 'output_analyte_id');
    }

    public function isResult(): bool
    {
        return $this->output_analyte_id !== null;
    }
}
