<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Opción de un campo de tipo select (norma, instrumento, tipo de equipo…). */
class TestFieldOption extends Model
{
    use HasFactory;

    protected $table = 'test_field_options';
    protected $guarded = [];
    protected $casts = ['is_hidden' => 'boolean', 'sort_order' => 'integer', 'legacy_id' => 'integer'];

    public function field(): BelongsTo
    {
        return $this->belongsTo(TestField::class, 'test_field_id');
    }
}
