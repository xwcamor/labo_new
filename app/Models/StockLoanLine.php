<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una línea del préstamo: un artículo y cuántas unidades salieron.
 *
 * No lleva `tenant_id` ni auditoría propia: cuelga del préstamo, que ya los
 * tiene. Es el mismo criterio que las filas de la hoja de trabajo.
 */
class StockLoanLine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['stock_loan_id', 'stock_item_id', 'qty', 'notes'];

    protected $casts = ['qty' => 'integer'];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(StockLoan::class, 'stock_loan_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(StockReturn::class, 'stock_loan_line_id');
    }

    /** Cuánto se devolvió ya de esta línea. */
    public function returned(): int
    {
        return (int) $this->returns->sum('qty');
    }

    /**
     * Cuánto falta volver.
     *
     * Nunca baja de cero: la devolución que superaría lo prestado se rechaza en
     * el controlador, pero si un dato viejo quedara mal cargado, un pendiente
     * negativo restaría de OTRAS líneas al sumar el préstamo y taparía material
     * que sí falta.
     */
    public function pending(): int
    {
        return max(0, $this->qty - $this->returned());
    }
}
