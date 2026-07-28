<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TestDefinition — una prueba del laboratorio (Número Ácido, Cromatografía…).
 *
 * Equivale a `lab_category_details` del sistema Rails viejo, que es la parte
 * que estaba bien modelada: el laboratorio agrega una prueba sin tocar código.
 */
class TestDefinition extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditModule = 'test_definitions';
    protected $table = 'test_definitions';
    protected $guarded = [];
    protected $casts = [
        'is_active'   => 'boolean',
        'is_grouped'  => 'boolean',
        'has_control' => 'boolean',
        'sort_order'  => 'integer',
        'legacy_id'   => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(TestGroup::class, 'test_group_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(TestField::class)->orderBy('sort_order');
    }

    /** Los campos que SON un resultado: los que alimentan un parámetro. */
    public function resultFields(): HasMany
    {
        return $this->fields()->whereNotNull('output_analyte_id');
    }

    public function getRouteKeyName(): string { return 'slug'; }
}
