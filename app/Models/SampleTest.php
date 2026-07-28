<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * SampleTest — qué prueba se le pide a una muestra, y en qué estado va.
 *
 * Es la unidad de trabajo del laboratorio: "a la muestra 2026-0695 le
 * corresponde Cromatografía, y todavía no se ensayó".
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ SOLO EXISTEN LAS QUE SE PIDEN                                            │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema anterior creaba una fila por CADA prueba del catálogo para cada
 * muestra —29 filas siempre— y después marcaba a mano cuáles iban de verdad con
 * una bandera `state`. Una remisión de 40 muestras insertaba más de mil filas de
 * las que la mayoría no significaba nada, dentro de un mismo `PUT` y sin
 * transacción envolvente.
 *
 * Peor: su pantalla para marcarlas dibujaba las casillas y los nombres como dos
 * listas independientes en dos columnas, alineadas solo visualmente. Si una
 * prueba se daba de baja del catálogo, o la prueba se creaba después que la
 * muestra, las casillas se corrían respecto de los nombres y el usuario marcaba
 * la prueba equivocada, en silencio.
 *
 * Acá una fila significa una cosa sola: esta prueba SE PIDIÓ.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL ESTADO SE ESCRIBE CUANDO PASA, NO CUANDO SE LEE                       │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema anterior el estado se recalculaba desde la VISTA en cada
 * apertura, con `update_all` dentro de un GET. Abrir una remisión de 40 muestras
 * eran unas 320 consultas y 40 escrituras, y el estado dependía de que alguien
 * abriera la pantalla: si nadie la abría, quedaba viejo y los filtros mentían.
 *
 * Acá lo mueve `SampleProgressService`, desde los eventos que de verdad lo
 * cambian: se carga la fila de bancada, se valida la hoja, se emite el informe.
 */
class SampleTest extends Model
{
    use BelongsToTenant;

    /** Pedida y sin ensayar. */
    public const STATUS_PENDING = 'pending';

    /** Hay fila de bancada cargada; la hoja todavía no se validó. */
    public const STATUS_IN_PROGRESS = 'in_progress';

    /** El supervisor firmó la hoja: el resultado ya es consultable. */
    public const STATUS_VALIDATED = 'validated';

    /** Salió en un informe al cliente. */
    public const STATUS_REPORTED = 'reported';

    /** Se dio de baja el pedido. No se borra: hay que poder explicarlo. */
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_VALIDATED,
        self::STATUS_REPORTED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Orden de avance. Sirve para no retroceder un estado por accidente:
     * volver a guardar una fila de bancada no puede bajar de "validado" a
     * "en proceso".
     */
    public const RANK = [
        self::STATUS_CANCELLED   => -1,
        self::STATUS_PENDING     => 0,
        self::STATUS_IN_PROGRESS => 1,
        self::STATUS_VALIDATED   => 2,
        self::STATUS_REPORTED    => 3,
    ];

    protected $table = 'sample_tests';
    protected $guarded = [];

    protected $attributes = ['status' => self::STATUS_PENDING];

    protected $casts = [
        'started_at'   => 'datetime',
        'validated_at' => 'datetime',
        'reported_at'  => 'datetime',
    ];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(TestDefinition::class, 'test_definition_id');
    }

    public function row(): HasOne
    {
        return $this->hasOne(WorksheetRow::class, 'id', 'worksheet_row_id');
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /** Lo que le falta ensayar al laboratorio. */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function isOutstanding(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS], true);
    }

    /** ¿`$nuevo` avanza respecto de lo que ya está guardado? */
    public function advancesTo(string $nuevo): bool
    {
        return (self::RANK[$nuevo] ?? 0) > (self::RANK[$this->status] ?? 0);
    }
}
