<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * QcPoint — un punto medido del patrón sobre una carta de control.
 *
 * Se materializa desde las filas `kind = control` de las hojas de trabajo y se
 * guarda con su `z_score` y su `flag` YA CALCULADOS. No se recalculan al
 * dibujar: la evaluación tiene que quedar congelada contra los límites que
 * regían el día de la medición, y recalcular al vuelo la haría depender de la
 * carta vigente hoy, que es el defecto que se corrige de raíz (ver QcChart).
 *
 * El punto lleva `tenant_id` propio Y el trait de alcance por workspace, aunque
 * pertenezca a una carta que ya está acotada. Es a propósito: el gráfico de
 * tendencias consulta los puntos directamente, por rango de fechas y sin unir
 * con la carta, y sin el trait esa consulta devolvería puntos de otros
 * workspaces. La columna se completa desde `qc_chart.tenant_id` al materializar.
 */
class QcPoint extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'qc_points';

    /** Dentro de los límites de alerta. */
    public const FLAG_OK = 'ok';

    /** Pasó un límite de alerta (2 desvíos por omisión), no el de control. */
    public const FLAG_WARN = 'warn';

    /** Pasó un límite de control (3 desvíos por omisión): la corrida se cuestiona. */
    public const FLAG_OUT = 'out';

    /** @var array<int,string> */
    public const FLAGS = [
        self::FLAG_OK,
        self::FLAG_WARN,
        self::FLAG_OUT,
    ];

    /**
     * Reglas de Westgard que puede violar un punto. Se enumeran acá para que el
     * valor guardado en `westgard_rule` sea un conjunto cerrado y no texto libre;
     * quien las EVALÚA es el servicio de control de calidad, no este modelo (ver
     * QcChart::classify()).
     *
     * @var array<int,string>
     */
    public const WESTGARD_RULES = ['1_3s', '2_2s', 'R_4s', '4_1s', '10x'];

    protected $fillable = [
        'qc_chart_id', 'worksheet_row_id', 'measured_at', 'value', 'z_score',
        'flag', 'westgard_rule', 'is_excluded', 'exclusion_reason', 'tenant_id',
    ];

    /**
     * `value` queda como decimal (cadena) por el mismo motivo que
     * WorksheetValue::$value_num: es la medición tal como se registró. El
     * `z_score` sí es float porque es un estadístico derivado, existe solo para
     * comparar y no representa nada que se haya medido.
     */
    protected $casts = [
        'measured_at' => 'datetime',
        'value'       => 'decimal:8',
        'z_score'     => 'float',
        'is_excluded' => 'boolean',
    ];

    public function chart(): BelongsTo
    {
        return $this->belongsTo(QcChart::class, 'qc_chart_id');
    }

    /** La fila de bancada de la que salió el punto. */
    public function row(): BelongsTo
    {
        return $this->belongsTo(WorksheetRow::class, 'worksheet_row_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Los puntos que entran en el cálculo. Un punto excluido se conserva —con su
     * motivo— porque descartarlo del gráfico sin dejar rastro es exactamente lo
     * que una auditoría busca; pero no pesa en la media ni en el desvío.
     */
    public function scopeIncluded(Builder $query): Builder
    {
        return $query->where('qc_points.is_excluded', false);
    }

    public function scopeFlag(Builder $query, string $flag): Builder
    {
        return $query->where('qc_points.flag', $flag);
    }

    /** Todo lo que no está en control: alerta y fuera de control. */
    public function scopeFlagged(Builder $query): Builder
    {
        return $query->whereIn('qc_points.flag', [self::FLAG_WARN, self::FLAG_OUT]);
    }

    public function isOut(): bool
    {
        return $this->flag === self::FLAG_OUT;
    }
}
