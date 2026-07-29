<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sample — la muestra. "2026-0695".
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ ES ACÁ DONDE VIVE EL EQUIPO, Y NO EN LA FILA DE BANCADA                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 * La fila de la hoja de trabajo referencia la PRUEBA PEDIDA (`sample_test_id`),
 * y de ahí sube a la muestra, al equipo y al cliente. El analista no elige el
 * transformador: lo eligió quien recibió el envase, que es quien tiene el dato.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ EL CÓDIGO SE GUARDA ADEMÁS DE year Y number                      │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `year` y `number` son lo que ordena y lo que hace único. `code` es lo que se
 * imprime en la etiqueta del envase y lo que el cliente cita por teléfono.
 *
 * En el sistema anterior existían las dos primeras y la tercera se armaba al
 * vuelo con `sprintf`, así que buscar por el número que el cliente cita obligaba
 * a partir la cadena en cada consulta — y eso es exactamente lo que hacían sus
 * tres lugares distintos de parseo (`split('-')`, `first(4)`/`last(4)`), cada
 * uno con su propia forma de romperse.
 */
class Sample extends Model
{
    use SoftDeletes;
    use BelongsToTenant;
    use Auditable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REPORTED = 'reported';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_REPORTED,
    ];

    protected $table = 'samples';
    protected $guarded = [];

    protected $attributes = ['status' => self::STATUS_PENDING];

    /**
     * Las cuatro condiciones de campo se castean a float, y lo que importa del
     * casteo es que el NULO siga siendo nulo hasta la vista: el informe imprime
     * raya cuando no se midió. El sistema anterior las guardaba como texto y
     * las imprimía con `to_f.round(2)`, así que un campo vacío salía "0.00" y
     * quedaba indistinguible de una medición real de cero. En un informe de
     * ensayo eso es un dato inventado.
     *
     * `$guarded = []` ya deja mass-assignables las columnas de cabecera nuevas
     * (`report_number`, `description`, `sampling_reason` y estas cuatro): no
     * hace falta agregarlas a ningún `$fillable`.
     */
    protected $casts = [
        'year'       => 'integer',
        'number'     => 'integer',
        'sampled_at' => 'date',
        'is_urgent'  => 'boolean',
        'oil_temp_c'        => 'float',
        'equipment_temp_c'  => 'float',
        'ambient_temp_c'    => 'float',
        'relative_humidity' => 'float',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** El correlativo tal como se imprime: 2026-0695. */
    public static function formatCode(int $year, int $number): string
    {
        return $year . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    // ── Relaciones ───────────────────────────────────────────────────────

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function oilType(): BelongsTo
    {
        return $this->belongsTo(OilType::class);
    }

    public function tests(): HasMany
    {
        return $this->hasMany(SampleTest::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(SampleReport::class);
    }

    // ── Borrado ──────────────────────────────────────────────────────────
    //
    // ┌──────────────────────────────────────────────────────────────────────┐
    // │ UNA MUESTRA CON INFORME EMITIDO NO SE BORRA                          │
    // └──────────────────────────────────────────────────────────────────────┘
    // No es una regla de permisos: es que el cliente tiene en la mano un papel
    // que cita ese número de muestra, y el portal público de verificación
    // responde contra el registro. Borrarla convierte ese papel en un documento
    // que el propio sistema desmiente —"no existe"— y el laboratorio queda sin
    // poder sostener lo que firmó. Por eso el candado alcanza también al super:
    // no hay caso en el que borrarla sea la respuesta correcta.
    //
    // El correlativo, además, nunca se reutiliza (el contador no retrocede), así
    // que borrar tampoco libera el número para nadie.

    /** ¿Salió a la calle algún informe con esta muestra? */
    public function hasIssuedReport(): bool
    {
        return $this->reports()
            ->where('status', SampleReport::STATUS_ISSUED)
            ->exists();
    }

    /**
     * Por qué NO se puede borrar, o null si se puede.
     *
     * Devuelve el motivo y no un booleano porque la pantalla tiene que poder
     * decirlo: un botón deshabilitado sin explicación es el que termina en una
     * llamada preguntando si el sistema se rompió.
     */
    public function deletionBlockedBy(): ?string
    {
        return $this->hasIssuedReport() ? 'issued_report' : null;
    }

    /**
     * ¿Borrarla se lleva puesto trabajo hecho?
     *
     * No lo impide —el laboratorio puede haber cargado la muestra equivocada y
     * darse cuenta con los ensayos ya corridos—, pero la pantalla tiene que
     * avisarlo ANTES, no después.
     */
    public function hasWork(): bool
    {
        return $this->results()->exists() || $this->reports()->exists();
    }

    /**
     * El cliente sale de la recepción, no de una columna propia.
     *
     * En el sistema anterior había DOS caminos al cliente —por la remisión y por
     * el transformador— y podían discrepar, porque el desplegable de equipos
     * cargaba los de TODOS los clientes y el guardado no lo verificaba.
     */
    public function customer(): ?Customer
    {
        return $this->reception?->customer;
    }

    // ── Consultas ────────────────────────────────────────────────────────

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /** Las que todavía tienen alguna prueba sin ensayar. */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    /** Sin equipo asignado: no se puede informar ni graficar su tendencia. */
    public function scopeWithoutEquipment(Builder $query): Builder
    {
        return $query->whereNull('equipment_id');
    }

    public function isComplete(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_REPORTED], true);
    }
}
