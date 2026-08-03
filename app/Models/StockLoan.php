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
 * Un préstamo: quién se llevó qué del almacén, y qué falta volver.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL ESTADO SE ESCRIBE CUANDO OCURRE                                       │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `status` vale 'open' mientras falte devolver algo y 'returned' cuando no
 * falta nada, y lo escribe `refreshStatus()` cada vez que cambia una línea o
 * una devolución. El sistema anterior tenía una columna `is_loan` que se
 * llenaba al crear y no se tocaba nunca: el método que la leía no se llamaba
 * desde ninguna pantalla, y cada vista recalculaba el estado sumando a mano.
 * Con el estado escrito, "los préstamos abiertos" es un índice y no un
 * recorrido de todas las devoluciones del laboratorio.
 */
class StockLoan extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant;

    protected string $auditModule = 'stock_loans';

    /** Falta devolver algo. */
    public const STATUS_OPEN = 'open';
    /** No falta nada. */
    public const STATUS_RETURNED = 'returned';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_RETURNED];

    protected $fillable = [
        'slug', 'loaned_on', 'borrower_user_id', 'borrower_name', 'purpose',
        'status', 'returned_at', 'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'loaned_on'   => 'date',
        'returned_at' => 'datetime',
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

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_user_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** El nombre de quien se lo llevó, sea del sistema o de afuera. */
    public function borrowerLabel(): ?string
    {
        return $this->borrower?->name ?? $this->borrower_name;
    }

    /** Cuántas unidades faltan volver en todo el préstamo. */
    public function pending(): int
    {
        return (int) $this->lines->sum(fn (StockLoanLine $linea) => $linea->pending());
    }

    /**
     * Vuelve a fijar el estado según lo que falta.
     *
     * Es reversible a propósito: dar de baja una devolución mal cargada vuelve
     * a abrir el préstamo, igual que quitar una fila de la hoja de trabajo
     * devuelve la prueba a pendiente. Un estado que solo avanza obliga a
     * corregir la base a mano cuando alguien se equivoca al cargar.
     */
    public function refreshStatus(): void
    {
        $this->load('lines.returns');

        $faltan = $this->pending() > 0;

        $this->forceFill([
            'status'      => $faltan ? self::STATUS_OPEN : self::STATUS_RETURNED,
            'returned_at' => $faltan ? null : ($this->returned_at ?? now()),
        ])->saveQuietly();
    }

    public function scopeFilter(Builder $query, $request): Builder
    {
        return $query
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('from'), fn ($q, $v) => $q->whereDate('loaned_on', '>=', $v))
            ->when($request->input('to'), fn ($q, $v) => $q->whereDate('loaned_on', '<=', $v))
            ->when($request->input('item'), fn ($q, $v) => $q->whereHas(
                'lines.item',
                fn ($i) => $i->where('slug', $v)
            ))
            ->when($request->input('q'), function ($q, $texto) {
                $aguja = LikeQuery::contains((string) $texto);
                $q->where(fn ($w) => $w
                    ->where('borrower_name', 'like', $aguja)
                    ->orWhere('purpose', 'like', $aguja)
                    ->orWhereHas('borrower', fn ($u) => $u->where('name', 'like', $aguja)));
            });
    }
}
