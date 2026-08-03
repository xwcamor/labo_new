<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una devolución, que puede ser parcial y puede haber varias por línea.
 *
 * Se prestan diez matraces y vuelven seis un día y cuatro la semana siguiente:
 * son dos filas. El sistema anterior ya lo resolvía así, y es lo único de aquel
 * módulo que estaba bien de entrada.
 */
class StockReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'stock_returns';

    protected $fillable = ['stock_loan_line_id', 'returned_on', 'qty', 'notes', 'created_by'];

    protected $casts = [
        'returned_on' => 'date',
        'qty'         => 'integer',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(StockLoanLine::class, 'stock_loan_line_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
