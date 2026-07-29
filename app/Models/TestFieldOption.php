<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Opción de un campo de tipo select (norma, instrumento, tipo de equipo…).
 *
 * `is_hidden` retira la opción de los formularios sin borrarla: los ensayos ya
 * cargados la siguen referenciando por FK y tienen que poder mostrar con qué
 * norma se corrieron.
 *
 * La acreditación va en DOS columnas a propósito. `is_accredited` es el hecho —si
 * el método está dentro del alcance acreditado del laboratorio— y es lo único
 * que decide si el informe estampa el sello del organismo. `accreditation_flag`
 * es apenas el rótulo que se imprime al lado de la norma ("A", "NA"), heredado
 * del `applicability_flag` del sistema anterior, y queda texto libre porque cada
 * organismo identifica su alcance con el código que quiere.
 *
 * Estuvieron en una sola columna y ahí se coló el error: cualquier cadena no
 * vacía contaba como acreditada, "NA" incluido.
 */
class TestFieldOption extends Model
{
    use HasFactory;

    protected $table = 'test_field_options';
    protected $guarded = [];
    protected $casts = [
        'is_hidden'     => 'boolean',
        'is_accredited' => 'boolean',
        'sort_order'    => 'integer',
        'legacy_id'     => 'integer',
        'locked_at'     => 'datetime',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(TestField::class, 'test_field_id');
    }

    /** Valores de bancada que eligieron esta opción. */
    public function values(): HasMany
    {
        return $this->hasMany(WorksheetValue::class, 'option_id');
    }

    /** Lo que se ofrece hoy en el formulario. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('test_field_options.is_hidden', false);
    }

    /** ¿El método está dentro del alcance acreditado? */
    public function isAccredited(): bool
    {
        return (bool) $this->is_accredited;
    }
}
