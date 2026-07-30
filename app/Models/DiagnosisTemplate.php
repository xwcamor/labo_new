<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenantOrGlobal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Una plantilla del análisis de resultados del informe.
 *
 * `tenant_id` nulo = la plantilla de fábrica que publica el super. Con valor =
 * la redacción propia de ese laboratorio, que gana sobre la global. Restaurar es
 * borrar la fila del workspace.
 */
class DiagnosisTemplate extends Model
{
    use SoftDeletes, Auditable, BelongsToTenantOrGlobal;

    /** Los cuatro casos que cubre una plantilla, y qué significa cada uno. */
    public const CASE_NONE = 'none';   // ningún resultado fuera de norma
    public const CASE_ONE  = 'one';    // exactamente uno
    public const CASE_MANY = 'many';   // varios
    public const CASE_ANY  = 'any';    // cualquiera (incluye las graduadas)

    public const CASES = [
        self::CASE_NONE,
        self::CASE_ONE,
        self::CASE_MANY,
        self::CASE_ANY,
    ];

    protected $fillable = [
        'slug', 'tenant_id', 'family', 'case', 'oil_types', 'equipment_types',
        'analyte', 'threshold', 'bands', 'body', 'origin', 'notes',
        'sort_order', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'oil_types'       => 'array',
        'equipment_types' => 'array',
        'bands'           => 'array',
        'threshold'       => 'decimal:6',
        'is_active'       => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** ¿Es la plantilla de fábrica (la que ven todos los laboratorios)? */
    public function isFactory(): bool
    {
        return $this->tenant_id === null;
    }

    /** Las graduadas resuelven su texto por tramo de valor, no por caso. */
    public function isGraded(): bool
    {
        return ! empty($this->bands);
    }

    /**
     * Las plantillas que le corresponden a un workspace: las suyas y, para las
     * familias donde no tiene ninguna, las de fábrica.
     *
     * El orden importa y no es cosmético: `tenant_id` descendente pone las
     * propias ANTES de las globales, y el resolvedor se queda con la primera
     * que encuentra por familia y caso.
     */
    public function scopeForTenant(Builder $query, ?int $tenantId): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('tenant_id')->when(
                $tenantId !== null,
                fn ($w) => $w->orWhere('tenant_id', $tenantId),
            ))
            ->orderByRaw('tenant_id IS NULL')   // primero las del workspace
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
