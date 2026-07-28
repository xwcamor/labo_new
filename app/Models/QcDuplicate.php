<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * QcDuplicate — el par de un duplicado: la misma muestra medida dos veces.
 *
 * Es la evidencia de repetibilidad del ensayo. El sistema Rails viejo permitía
 * cargar duplicados (`lab_detail_type_id = 3`) y no hacía absolutamente nada con
 * ellos: el analista repetía la medición, el dato quedaba guardado y ningún
 * lugar del sistema los comparaba. Acá el par se contrasta contra el criterio de
 * la carta (QcChart::repeatabilityWithinLimit()).
 *
 * `within_limit` es nullable de forma deliberada, y el nulo no es "todavía no se
 * evaluó": significa que la carta no declara criterio de repetibilidad, y sin
 * criterio no se puede afirmar que el par cumpla. Registrar un "cumple" en ese
 * caso sería fabricar evidencia de calidad.
 *
 * Igual que QcPoint, no lleva el trait de alcance por workspace: cuelga de la
 * carta y de las filas de bancada, que sí están scopeadas.
 */
class QcDuplicate extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'qc_duplicates';

    protected $fillable = [
        'qc_chart_id', 'original_row_id', 'duplicate_row_id', 'measured_at',
        'value_a', 'value_b', 'difference', 'relative_difference',
        'within_limit', 'tenant_id',
    ];

    /**
     * Las dos lecturas y su diferencia absoluta quedan como decimal (cadena):
     * son mediciones, y el criterio es el de WorksheetValue::$value_num. La
     * diferencia relativa es un porcentaje calculado, así que va como float.
     */
    protected $casts = [
        'measured_at'         => 'datetime',
        'value_a'             => 'decimal:8',
        'value_b'             => 'decimal:8',
        'difference'          => 'decimal:8',
        'relative_difference' => 'float',
        'within_limit'        => 'boolean',
    ];

    public function chart(): BelongsTo
    {
        return $this->belongsTo(QcChart::class, 'qc_chart_id');
    }

    /** La fila de la medición original. */
    public function original(): BelongsTo
    {
        return $this->belongsTo(WorksheetRow::class, 'original_row_id');
    }

    /** La fila de la repetición. */
    public function duplicate(): BelongsTo
    {
        return $this->belongsTo(WorksheetRow::class, 'duplicate_row_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Pares que se evaluaron y NO cumplieron. El nulo queda afuera a propósito. */
    public function scopeOutOfLimit(Builder $query): Builder
    {
        return $query->where('qc_duplicates.within_limit', false);
    }

    /** Pares sin criterio contra el cual compararlos: la carta no lo declara. */
    public function scopeUnevaluated(Builder $query): Builder
    {
        return $query->whereNull('qc_duplicates.within_limit');
    }

    public function isEvaluated(): bool
    {
        return $this->within_limit !== null;
    }
}
