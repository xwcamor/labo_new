<?php

namespace App\Models;

use App\Support\LikeQuery;
use App\Traits\Auditable;
use App\Traits\BelongsToTenantOrGlobal;
use App\Traits\HasFavorites;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Equipment — el equipo del que el laboratorio toma la muestra.
 *
 * NO es `Transformer`: el laboratorio recibe muestras de 20 tipos de equipo
 * (conmutadores, reactores, bushings, cables, interruptores, electrobombas,
 * intercambiadores…). Llamarlo "transformador" es lo que llevó al
 * `if tipo == 10` del sistema viejo.
 *
 * NO tiene campos de diagnóstico. El índice de salud, Duval y la condición
 * IEEE son de TrafoDex; acá solo se evalúa contra el criterio de aceptación.
 *
 * `equipment_type_id` + `oil_type_id` + la banda de tensión son los tres ejes
 * con los que la fase 2 resuelve el cuadro de límites aplicable.
 */
class Equipment extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenantOrGlobal, HasFavorites, \App\Traits\Lockable;

    // 3ª capa de aislamiento: un usuario con clientes asignados (customer_user)
    // solo ve los equipos de su cartera. El default del trait ya apunta a la
    // columna `customer_id` de esta tabla; sin asignaciones no restringe nada.
    use \App\Traits\RestrictedToAssignedCustomers;

    protected string $auditModule = 'equipment';

    protected $table = 'equipment';

    /**
     * Vocabularios cerrados.
     *
     * Son listas de dos y tres valores que no cambian con el cliente ni con el
     * laboratorio: no merecen una tabla de catálogo, pero sí ser una lista
     * cerrada. En el sistema anterior el volumen de aceite venía con la unidad
     * escrita a mano en el mismo campo ("2500 gal", "2500 galones", "2500Gal"),
     * y comparar dos equipos exigía adivinar.
     */
    public const OIL_VOLUME_UNITS = ['L', 'gal'];

    public const SERVICE_STATES = ['new', 'in_service', 'out_of_service'];

    protected $fillable = [
        'slug', 'name', 'serial', 'tag',
        'customer_id', 'customer_location_id', 'customer_area_id', 'customer_substation_id',
        'equipment_type_id', 'oil_type_id', 'brand_id', 'tap_changer_type_id',
        'transformer_preservation_id',
        'voltage_kv_hv', 'voltage_kv_lv', 'voltage_kv_tv',
        'power_mva', 'power_mva_2', 'power_mva_3', 'phases', 'manufacture_year',
        'oil_volume', 'oil_volume_unit', 'service_state',
        'external_ref', 'is_active', 'tenant_id',
        'created_by', 'deleted_by', 'deleted_description',
    ];

    /**
     * Las dos placas viajan siempre. El índice serializa el paginador con
     * `toArray()`, que no ve un accessor si no está declarado acá: sin esto las
     * columnas Tensión y Potencia salían vacías en la tabla.
     */
    protected $appends = ['voltage_label', 'power_label'];

    protected $casts = [
        'is_active'  => 'boolean',
        'voltage_kv_hv' => 'decimal:2',
        'voltage_kv_lv' => 'decimal:2',
        'voltage_kv_tv' => 'decimal:2',
        'power_mva' => 'decimal:2',
        'power_mva_2' => 'decimal:2',
        'power_mva_3' => 'decimal:2',
        'phases' => 'integer',
        'manufacture_year' => 'integer',
        'oil_volume' => 'decimal:2',
    ];

    /**
     * Banda de tensión del equipo, en kV. Es lo que la fase 2 usa para elegir
     * el cuadro de límites (mineral ≤69 / 69-230 / ≥230, etc.).
     *
     * En el sistema viejo esto se recalculaba en cinco lugares distintos con
     * `num_ten.split('/').map(&:to_f).max`, sobre un string. Acá es una sola
     * función sobre columnas numéricas.
     */
    public function getVoltageClassAttribute(): ?float
    {
        return $this->mayor($this->voltages());
    }

    /**
     * Las placas del equipo, en orden y sin los huecos.
     *
     * Un transformador de tres devanados dice "500 / 220 / 33 kV" y uno con
     * refrigeración forzada dice "120 / 160 / 200 MVA". Son las dos placas que
     * el informe imprime tal cual; acá viven como columnas y se arman en un
     * solo lugar para que la ficha, el informe y los exports digan lo mismo.
     */
    public function voltages(): array
    {
        return $this->presentes([$this->voltage_kv_hv, $this->voltage_kv_lv, $this->voltage_kv_tv]);
    }

    public function powers(): array
    {
        return $this->presentes([$this->power_mva, $this->power_mva_2, $this->power_mva_3]);
    }

    public function getVoltageLabelAttribute(): ?string
    {
        return $this->placa($this->voltages());
    }

    public function getPowerLabelAttribute(): ?string
    {
        return $this->placa($this->powers());
    }

    /**
     * El número que se le manda a TrafoDex, que tiene UNA tensión y UNA
     * potencia por equipo. Es el máximo — el mismo criterio que ya aplicaba el
     * sistema viejo al exportar (`num_pot.split('/').map(&:to_f).max`).
     */
    public function getPowerRatingAttribute(): ?float
    {
        return $this->mayor($this->powers());
    }

    /** @param  list<int|float|string|null>  $valores */
    private function presentes(array $valores): array
    {
        return array_values(array_map(
            fn ($v) => (float) $v,
            array_filter($valores, fn ($v) => $v !== null && $v !== ''),
        ));
    }

    private function mayor(array $valores): ?float
    {
        return $valores === [] ? null : max($valores);
    }

    /**
     * "500 / 220 / 33" — sin decimales cuando el valor es redondo, porque la
     * placa dice 500 y no 500.00, pero conservándolos cuando importan (4.16).
     */
    private function placa(array $valores): ?string
    {
        if ($valores === []) {
            return null;
        }

        return implode(' / ', array_map(
            fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.'),
            $valores,
        ));
    }

    // ── Dónde está ──────────────────────────────────────────────────────
    public function customer(): BelongsTo            { return $this->belongsTo(Customer::class); }
    public function location(): BelongsTo            { return $this->belongsTo(CustomerLocation::class, 'customer_location_id'); }
    public function area(): BelongsTo                { return $this->belongsTo(CustomerArea::class, 'customer_area_id'); }
    public function substation(): BelongsTo          { return $this->belongsTo(CustomerSubstation::class, 'customer_substation_id'); }

    // ── Qué es (ejes del cuadro de límites + metadatos) ─────────────────
    public function equipmentType(): BelongsTo       { return $this->belongsTo(EquipmentType::class); }
    public function oilType(): BelongsTo             { return $this->belongsTo(OilType::class); }
    public function brand(): BelongsTo               { return $this->belongsTo(Brand::class); }
    public function tapChangerType(): BelongsTo      { return $this->belongsTo(TapChangerType::class); }
    public function preservation(): BelongsTo        { return $this->belongsTo(TransformerPreservation::class, 'transformer_preservation_id'); }

    // Fase 3: samples() — las muestras tomadas de este equipo.

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

    /**
     * scopeFilter — mismo patrón que Customer, sobre la tabla equipment.
     * Soporta name (multi-tag accent-insensitive), serie y tag (substring),
     * cliente / tipo de equipo / tipo de aceite, is_active (bool), rangos de
     * fecha/id, filtros avanzados y favoritos.
     */
    public function scopeFilter($query, $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        $tbl = 'equipment';

        $query->when($request->filled('name'), function ($q) use ($request, $isPgsql, $tbl) {
            $names = is_array($request->name) ? $request->name : [$request->name];
            $names = array_filter($names, fn ($n) => $n !== '');
            if (empty($names)) return;
            $q->where(function ($qq) use ($names, $isPgsql, $tbl) {
                foreach ($names as $name) {
                    $needle = LikeQuery::contains((string) $name);
                    if ($isPgsql) {
                        $qq->orWhereRaw("unaccent(lower({$tbl}.name)) LIKE unaccent(lower(?))", [$needle]);
                    } else {
                        $qq->orWhereRaw("{$tbl}.name LIKE ? ESCAPE '\\'", [$needle]);
                    }
                }
            });
        });

        // La chapa del equipo. Reemplaza al filtro por `code` que traía el
        // scaffold de catálogos: esa columna no existe en `equipment` (la
        // migración la excluye a propósito), así que filtrar por ella devolvía
        // un error de SQL en vez de un resultado vacío.
        foreach (['serial', 'tag'] as $campo) {
            $query->when($request->filled($campo), function ($q) use ($request, $tbl, $campo, $isPgsql) {
                $needle = LikeQuery::contains((string) $request->input($campo));
                $q->whereRaw(
                    $isPgsql ? "{$tbl}.{$campo} ILIKE ?" : "{$tbl}.{$campo} LIKE ? ESCAPE '\\'",
                    [$needle]
                );
            });
        }

        // Los tres ejes por los que el laboratorio busca de verdad: de quién es
        // el equipo, qué es, y con qué aceite trabaja.
        foreach (['customer_id', 'equipment_type_id', 'oil_type_id'] as $fk) {
            $query->when($request->filled($fk), fn ($q) => $q->where("{$tbl}.{$fk}", (int) $request->input($fk)));
        }

        $query->when($request->filled('is_active'), function ($q) use ($request, $tbl) {
            $q->where("{$tbl}.is_active", filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        });

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
            // `code` y `sort_order` estaban en esta lista y no son columnas de
            // `equipment`: ordenar por ellas devolvía un error de SQL.
        } elseif (in_array($sort, ['id', 'name', 'serial', 'tag', 'is_active', 'created_at', 'updated_at']) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy("{$tbl}.{$sort}", $direction);
        }

        return $query;
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, operators: array<int, string>}>
     */
    public static function filterSchema(array $opts = []): array
    {
        return [
            ['key' => 'name',       'label' => __('equipment.name'),     'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'serial',     'label' => __('equipment.serial'),   'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'tag',        'label' => __('equipment.tag'),      'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'customer_id', 'label' => __('equipment.customer'), 'type' => 'enum', 'operators' => ['=', '!=', 'in'], 'options' => $opts['customers'] ?? []],
            ['key' => 'equipment_type_id', 'label' => __('equipment.equipment_type'), 'type' => 'enum', 'operators' => ['=', '!=', 'in'], 'options' => $opts['types'] ?? []],
            ['key' => 'oil_type_id', 'label' => __('equipment.oil_type'), 'type' => 'enum', 'operators' => ['=', '!=', 'in'], 'options' => $opts['oilTypes'] ?? []],
            ['key' => 'is_active',  'label' => __('equipment.is_active'), 'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'created_at', 'label' => __('global.created_at'),   'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'updated_at', 'label' => __('global.updated_at'),   'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
