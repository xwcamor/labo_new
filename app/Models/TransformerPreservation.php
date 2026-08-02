<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * TransformerPreservation — catálogo global de sistemas de preservación del
 * aceite (conservador con membrana, tanque sellado con nitrógeno, respiración
 * libre, …). Metadato descriptivo del transformador (NO eje de diagnóstico).
 * Catálogo GLOBAL (sin tenant). Sin módulo CRUD por ahora.
 */
class TransformerPreservation extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditModule = 'transformer_preservations';

    protected $fillable = [
        'slug', 'name', 'code', 'is_active', 'sort_order',
        'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
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
        return $this->hasMany(Equipment::class, 'transformer_preservation_id');
    }
}
