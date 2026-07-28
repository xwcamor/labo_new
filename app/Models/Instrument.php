<?php

namespace App\Models;

use App\Support\LikeQuery;
use App\Traits\Auditable;
use App\Traits\BelongsToTenantOrGlobal;
use App\Traits\HasFavorites;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Instrument — el equipamiento de bancada con el que se corre cada ensayo.
 *
 * ISO 17025 exige poder decir con QUÉ se midió y si ese equipo estaba calibrado
 * el día del ensayo. En el sistema Rails viejo el instrumento era una OPCIÓN
 * SUELTA de un select de la plantilla ("Bureta PP-LA-01C"), con el código de
 * calibración metido dentro del texto del nombre y sin fecha de vencimiento: no
 * había forma de saber si el equipo seguía vigente. Acá es una entidad con su
 * calibración, y la hoja de trabajo lo referencia por FK.
 *
 * CLAVE NATURAL = `code` (PP-LA-01C), NO el nombre: dos buretas se llaman las
 * dos "Bureta" y son equipos distintos. El índice único parcial de la tabla es
 * (tenant_id, LOWER(code)) — las reglas de los FormRequests lo replican.
 */
class Instrument extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenantOrGlobal, HasFavorites, \App\Traits\Lockable;

    protected string $auditModule = 'instruments';

    protected $fillable = [
        'slug', 'name', 'code', 'is_active', 'sort_order', 'tenant_id',
        // Trazabilidad metrológica.
        'brand', 'model', 'serial',
        'calibrated_at', 'calibration_due_at', 'calibration_certificate',
        'location', 'notes',
        'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'sort_order'         => 'integer',
        'calibrated_at'      => 'date',
        'calibration_due_at' => 'date',
    ];

    /** Se serializan al front en cada payload sin tener que repetirlos a mano. */
    protected $appends = ['calibration_status', 'calibration_days_left'];

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

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    /** Texto traducido del estado — consumido por exports (CSV/Excel/PDF/Word). */
    public function getStateTextAttribute(): string
    {
        return $this->is_active ? __('global.active') : __('global.inactive');
    }

    // ── Calibración ─────────────────────────────────────────────────────
    //
    // Es lo único que este módulo agrega sobre un catálogo cualquiera, y es su
    // razón de existir: que el analista vea de un vistazo si el equipo con el
    // que va a medir tiene la calibración vencida. El umbral de aviso vive en
    // config (no clavado) porque cada laboratorio pide el suyo.

    public const CAL_UNKNOWN  = 'unknown';   // sin fecha de vencimiento cargada
    public const CAL_VALID    = 'valid';     // vigente
    public const CAL_DUE_SOON = 'due_soon';  // vence dentro de la ventana de aviso
    public const CAL_EXPIRED  = 'expired';   // vencida

    /** Días de anticipación con los que se avisa que una calibración vence. */
    public static function calibrationWarningDays(): int
    {
        return (int) config('instruments.calibration_warning_days', 30);
    }

    /**
     * Estado de la calibración: unknown | valid | due_soon | expired.
     *
     * Sin `calibration_due_at` devuelve `unknown` en vez de `valid`: no saber
     * cuándo vence NO es lo mismo que estar vigente, y darlo por bueno es
     * exactamente el agujero que tenía el sistema viejo.
     */
    public function getCalibrationStatusAttribute(): string
    {
        $due = $this->calibration_due_at;
        if (! $due) {
            return self::CAL_UNKNOWN;
        }

        $days = $this->calibration_days_left;

        if ($days < 0)                              return self::CAL_EXPIRED;
        if ($days <= self::calibrationWarningDays()) return self::CAL_DUE_SOON;

        return self::CAL_VALID;
    }

    /** Días que faltan para el vencimiento (negativo = vencida). Null si no hay fecha. */
    public function getCalibrationDaysLeftAttribute(): ?int
    {
        $due = $this->calibration_due_at;
        if (! $due) {
            return null;
        }

        $dueDay = $due instanceof \DateTimeInterface
            ? Carbon::instance($due)->startOfDay()
            : Carbon::parse($due)->startOfDay();

        // Carbon 3 devuelve float acá; el consumidor quiere días enteros.
        return (int) Carbon::today()->diffInDays($dueDay, false);
    }

    /** Texto traducido del estado de calibración — consumido por los exports. */
    public function getCalibrationStatusTextAttribute(): string
    {
        return __('instruments.cal_status_' . $this->calibration_status);
    }

    /**
     * scopeFilter — filtros del listado sobre la tabla instruments.
     * Soporta name (multi-tag accent-insensitive), code/brand/model/serial/
     * location (substring), is_active, estado de calibración, rangos de
     * fecha/id, filtros avanzados y favoritos.
     */
    public function scopeFilter($query, $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        $tbl = 'instruments';

        $query->when($request->filled('name'), function ($q) use ($request, $isPgsql, $tbl) {
            $names = is_array($request->name) ? $request->name : [$request->name];
            $names = array_filter($names, fn ($n) => $n !== '');
            if (empty($names)) return;
            $q->where(function ($qq) use ($names, $isPgsql, $tbl) {
                foreach ($names as $name) {
                    $needle = LikeQuery::contains((string) $name);
                    if ($isPgsql) {
                        // El buscador rápido escribe el código tanto como el
                        // nombre: acá la clave natural es el código, así que
                        // buscar "PP-LA-01C" tiene que encontrar el equipo.
                        $qq->orWhereRaw("unaccent(lower({$tbl}.name)) LIKE unaccent(lower(?))", [$needle])
                           ->orWhereRaw("lower({$tbl}.code) LIKE lower(?)", [$needle]);
                    } else {
                        $qq->orWhereRaw("{$tbl}.name LIKE ? ESCAPE '\\'", [$needle])
                           ->orWhereRaw("{$tbl}.code LIKE ? ESCAPE '\\'", [$needle]);
                    }
                }
            });
        });

        foreach (['code', 'brand', 'model', 'serial', 'location'] as $field) {
            $query->when($request->filled($field), function ($q) use ($request, $tbl, $field) {
                $q->whereRaw(
                    config('database.default') === 'pgsql'
                        ? "lower({$tbl}.{$field}) LIKE lower(?)"
                        : "{$tbl}.{$field} LIKE ? ESCAPE '\\'",
                    [LikeQuery::contains((string) $request->input($field))]
                );
            });
        }

        $query->when($request->filled('is_active'), function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.is_active", filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        });

        // Estado de calibración: se resuelve en SQL sobre la fecha, no en PHP,
        // para que pagine y ordene igual que cualquier otro filtro.
        $query->when($request->filled('calibration_status'), function ($q) use ($request, $tbl) {
            $warn = static::calibrationWarningDays();
            $today = Carbon::today()->toDateString();
            $limit = Carbon::today()->addDays($warn)->toDateString();

            match ($request->input('calibration_status')) {
                self::CAL_EXPIRED  => $q->whereNotNull("{$tbl}.calibration_due_at")
                                        ->where("{$tbl}.calibration_due_at", '<', $today),
                self::CAL_DUE_SOON => $q->whereNotNull("{$tbl}.calibration_due_at")
                                        ->whereBetween("{$tbl}.calibration_due_at", [$today, $limit]),
                self::CAL_VALID    => $q->whereNotNull("{$tbl}.calibration_due_at")
                                        ->where("{$tbl}.calibration_due_at", '>', $limit),
                self::CAL_UNKNOWN  => $q->whereNull("{$tbl}.calibration_due_at"),
                default            => null,
            };
        });

        $query->when($request->filled('due_from'), fn ($q) => $q->where("{$tbl}.calibration_due_at", '>=', $request->due_from));
        $query->when($request->filled('due_to'),   fn ($q) => $q->where("{$tbl}.calibration_due_at", '<=', $request->due_to));

        $query->when($request->filled('created_from'), fn ($q) => $q->where("{$tbl}.created_at", '>=', $request->created_from . ' 00:00:00'));
        $query->when($request->filled('created_to'),   fn ($q) => $q->where("{$tbl}.created_at", '<=', $request->created_to . ' 23:59:59'));
        $query->when($request->filled('updated_from'), fn ($q) => $q->where("{$tbl}.updated_at", '>=', $request->updated_from . ' 00:00:00'));
        $query->when($request->filled('updated_to'),   fn ($q) => $q->where("{$tbl}.updated_at", '<=', $request->updated_to . ' 23:59:59'));
        $query->when($request->filled('id_from'), fn ($q) => $q->where("{$tbl}.id", '>=', (int) $request->id_from));
        $query->when($request->filled('id_to'),   fn ($q) => $q->where("{$tbl}.id", '<=', (int) $request->id_to));

        $advanced = $request->input('advanced_where');
        if (is_string($advanced)) {
            $advanced = json_decode($advanced, true) ?: null;
        }
        if (is_array($advanced) && !empty($advanced)) {
            \App\Services\Automations\Support\FilterApplier::apply(
                $query,
                ['where' => $advanced],
                static::filterSchema()
            );
        }

        if ($request->filled('only_favorites') && filter_var($request->only_favorites, FILTER_VALIDATE_BOOLEAN)) {
            $userId = auth()->id();
            if ($userId) {
                $query->whereExists(function ($q) use ($userId, $tbl) {
                    $q->select(\DB::raw(1))
                      ->from('user_favorites')
                      ->whereColumn('user_favorites.favoritable_id', "{$tbl}.id")
                      ->where('user_favorites.favoritable_type', static::class)
                      ->where('user_favorites.user_id', $userId);
                });
            }
        }

        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'desc');
        if ($sort === 'tenant' && in_array($direction, ['asc', 'desc'])) {
            // Orden por workspace: nombre vía left join (nulls = global).
            $query->leftJoin('tenants', "{$tbl}.tenant_id", '=', 'tenants.id')
                  ->orderBy('tenants.name', $direction);
        } elseif (in_array($sort, ['id', 'name', 'code', 'brand', 'model', 'serial', 'location', 'calibrated_at', 'calibration_due_at', 'is_active', 'sort_order', 'created_at', 'updated_at']) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy("{$tbl}.{$sort}", $direction);
        }

        return $query;
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, operators: array<int, string>}>
     */
    public static function filterSchema(): array
    {
        return [
            ['key' => 'code',               'label' => __('instruments.code'),               'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'name',               'label' => __('instruments.name'),               'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'brand',              'label' => __('instruments.brand'),              'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'model',              'label' => __('instruments.model'),              'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'serial',             'label' => __('instruments.serial'),             'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'location',           'label' => __('instruments.location'),           'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'calibration_certificate', 'label' => __('instruments.calibration_certificate'), 'type' => 'string', 'operators' => ['=', '!=', 'contains']],
            ['key' => 'calibrated_at',      'label' => __('instruments.calibrated_at'),      'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'calibration_due_at', 'label' => __('instruments.calibration_due_at'), 'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'is_active',          'label' => __('instruments.is_active'),          'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'created_at',         'label' => __('global.created_at'),              'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'updated_at',         'label' => __('global.updated_at'),              'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
