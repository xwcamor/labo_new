<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Una lectura diaria de las condiciones ambientales de una sala.
 *
 * El sistema anterior lo resolvía con dos tablas gemelas (`cro_temperatures` y
 * `fiq_temperatures`); acá la sala es un dato. Ver la migración para el porqué
 * de que esto exista aparte de las condiciones que guarda la hoja de trabajo.
 */
class AmbientLog extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant;

    protected string $auditModule = 'ambient_logs';

    /** Las salas del laboratorio. Lista cerrada: son áreas físicas. */
    public const ROOM_CHROMATOGRAPHY = 'chromatography';
    public const ROOM_PHYSICOCHEMICAL = 'physicochemical';

    public const ROOMS = [self::ROOM_CHROMATOGRAPHY, self::ROOM_PHYSICOCHEMICAL];

    protected $fillable = [
        'slug', 'room', 'logged_on', 'temperature_c', 'humidity_pct', 'pressure_hpa',
        'notes', 'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'logged_on'     => 'date',
        'temperature_c' => 'decimal:2',
        'humidity_pct'  => 'decimal:2',
        'pressure_hpa'  => 'decimal:2',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Filtros del listado: sala y rango de fechas.
     *
     * El sistema anterior no tenía ninguno —la tabla crecía sin orden ni
     * paginación del lado del servidor—, y una bitácora diaria de dos salas son
     * ~500 filas por año.
     */
    public function scopeFilter(Builder $query, $request): Builder
    {
        return $query
            ->when($request->input('room'), fn ($q, $v) => $q->where('room', $v))
            ->when($request->input('from'), fn ($q, $v) => $q->whereDate('logged_on', '>=', $v))
            ->when($request->input('to'), fn ($q, $v) => $q->whereDate('logged_on', '<=', $v));
    }
}
