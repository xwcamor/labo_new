<?php

namespace App\Models;

use App\Support\LikeQuery;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Un artículo del almacén: reactivo, material de vidrio, equipo prestable.
 *
 * `on_hand` es lo que el laboratorio DECLARA tener y se corrige a mano; el
 * porqué está en la migración `create_stock_tables`. Lo prestado se calcula
 * desde los préstamos abiertos, y de ahí sale lo disponible.
 */
class StockItem extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant;

    protected string $auditModule = 'stock_items';

    protected $fillable = [
        'slug', 'code', 'name', 'unit', 'on_hand', 'min_qty', 'location',
        'is_active', 'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'on_hand'   => 'integer',
        'min_qty'   => 'integer',
        'is_active' => 'boolean',
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

    public function lines(): HasMany
    {
        return $this->hasMany(StockLoanLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Cuántas unidades hay afuera ahora mismo.
     *
     * Suma lo que falta volver de las líneas VIVAS de los préstamos VIVOS. El
     * sistema anterior calculaba esto en la vista y sumaba también las líneas
     * dadas de baja, así que dar de baja una línea cambiaba el listado y no la
     * cuenta.
     */
    public function onLoan(): int
    {
        return (int) $this->lines()
            ->whereHas('loan', fn ($q) => $q->where('status', StockLoan::STATUS_OPEN))
            ->get()
            ->sum(fn (StockLoanLine $linea) => $linea->pending());
    }

    /** Lo que queda en el estante: lo declarado menos lo que está afuera. */
    public function available(): int
    {
        return $this->on_hand - $this->onLoan();
    }

    /** ¿Cayó por debajo del punto de reposición? */
    public function isLow(): bool
    {
        return $this->min_qty !== null && $this->available() <= $this->min_qty;
    }

    public function scopeFilter(Builder $query, $request): Builder
    {
        return $query
            ->when($request->input('q'), function ($q, $texto) {
                $aguja = LikeQuery::contains((string) $texto);
                $q->where(fn ($w) => $w->where('name', 'like', $aguja)->orWhere('code', 'like', $aguja));
            })
            ->when($request->input('unit'), fn ($q, $v) => $q->where('unit', $v))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN)));
    }
}
