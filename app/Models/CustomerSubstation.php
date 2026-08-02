<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenantOrGlobal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * CustomerSubstation — Subestación de un área (nivel 3). Cuelgan transformadores.
 */
class CustomerSubstation extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenantOrGlobal;

    protected string $auditModule = 'customer_substations';

    protected $fillable = [
        'slug', 'customer_area_id', 'name',
        'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
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

    public function area(): BelongsTo
    {
        return $this->belongsTo(CustomerArea::class, 'customer_area_id');
    }

    /**
     * Los EQUIPOS que usan esta fila.
     *
     * Apuntaba a `Transformer::class`, una clase que NO EXISTE en este
     * repositorio: quedó del scaffold, copiada del sistema de diagnóstico
     * donde el modelo se llama así. Acá el modelo es `Equipment`, y
     * cualquier código que tocara esta relación moría con un error fatal de
     * clase inexistente. La clave foránea se declara explícita porque
     * Eloquent la derivaría del nombre del modelo (`equipment_id`) y no es
     * esa.
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'customer_substation_id')->orderBy('serial');
    }
}
