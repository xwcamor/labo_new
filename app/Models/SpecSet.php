<?php

namespace App\Models;

use App\Traits\BelongsToTenantOrGlobal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SpecSet — un cuadro de valores de orientación.
 *
 * "Fisicoquímico · Mineral · hasta 69 kV" con sus diez límites. Es la
 * traducción a datos de cada rama del árbol de `if/elsif` que el sistema
 * anterior tenía escrito —dos veces, y ya divergido— en el código del informe.
 *
 * Los criterios en nulo significan "cualquiera". Un cuadro sin tipo de equipo
 * aplica a todos; uno con `equipment_type_id = 10` aplica solo al conmutador. La
 * resolución prefiere SIEMPRE el más específico (ver `SpecSetResolver`).
 */
class SpecSet extends Model
{
    use SoftDeletes;
    use BelongsToTenantOrGlobal;

    public const GROUP_FIQUI = 'fiqui';
    public const GROUP_DGA = 'dga';
    public const GROUP_PAPER = 'papel';
    public const GROUP_OTHER = 'otros';

    protected $table = 'spec_sets';
    protected $guarded = [];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'is_active'      => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(Standard::class);
    }

    public function oilType(): BelongsTo
    {
        return $this->belongsTo(OilType::class);
    }

    public function equipmentType(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class);
    }

    public function limits(): HasMany
    {
        return $this->hasMany(SpecLimit::class);
    }

    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    /**
     * Vigentes a una fecha.
     *
     * La fecha por defecto NO es hoy: es la de la muestra. Un ensayo de 2019 se
     * evalúa con la norma que regía en 2019, y eso es lo que hace que un
     * informe emitido siga diciendo lo que decía.
     */
    public function scopeEffectiveAt(Builder $query, $date): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    /**
     * Cuántos criterios declara este cuadro.
     *
     * Es lo que decide entre varios candidatos: gana el que más criterios
     * cumple, o sea el más específico. Sin esto, un cuadro genérico de mineral
     * le ganaría al cuadro específico de mineral en conmutador según el orden en
     * que la base los devuelva — que es exactamente la clase de resultado
     * impredecible que hay que evitar en un informe.
     */
    public function specificity(): int
    {
        return collect([
            $this->oil_type_id,
            $this->equipment_type_id,
            $this->service_state,
            $this->voltage_from ?? $this->voltage_to,
            $this->power_from ?? $this->power_to,
        ])->filter(fn ($v) => $v !== null)->count();
    }
}
