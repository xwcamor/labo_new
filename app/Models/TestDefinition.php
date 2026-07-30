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
 * TestDefinition — una prueba del laboratorio (Número Ácido, Cromatografía,
 * Furanos…). Son 29 y las define el laboratorio, no el código.
 *
 * Equivale a `lab_category_details` del sistema Rails viejo, que es la parte
 * que estaba bien modelada y se conserva: agregar una prueba o una columna de
 * su hoja de trabajo no toca código.
 *
 * Las columnas de la hoja viven en `test_fields` (relación `fields()`); las que
 * además SON un resultado declaran a qué parámetro alimentan
 * (`output_analyte_id`) y salen por `resultFields()`. El sistema viejo no lo
 * declaraba: tomaba "la última columna por posición" y reordenar el cuadro
 * rompía el informe en silencio.
 */
class TestDefinition extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenantOrGlobal, HasFavorites, \App\Traits\Lockable;

    protected string $auditModule = 'test_definitions';

    protected $table = 'test_definitions';

    protected $fillable = [
        'slug', 'name', 'code', 'test_group_id', 'description',
        'container', 'chart_unit', 'report_comment_group',
        'has_control', 'requires_control', 'requires_duplicate', 'is_grouped',
        'qc_policy_set_at',
        'replicates', 'is_active', 'sort_order', 'legacy_id', 'tenant_id',
        'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'has_control'        => 'boolean',
        'requires_control'   => 'boolean',
        'requires_duplicate' => 'boolean',
        'is_grouped'         => 'boolean',
        // Cuándo el laboratorio decidió el control de calidad de esta prueba.
        // Mientras sea nulo, el sembrador de fábrica puede escribirla; una vez
        // escrita, la fila es del laboratorio y el seeder no la vuelve a tocar.
        'qc_policy_set_at'   => 'datetime',
        'replicates'         => 'integer',
        'sort_order'         => 'integer',
        'legacy_id'          => 'integer',
    ];

    /** El grupo al que pertenece (Físico Químico · Cromatografía · Otros). */
    public function group(): BelongsTo
    {
        return $this->belongsTo(TestGroup::class, 'test_group_id');
    }

    /** Las columnas de la hoja de trabajo, en el orden en que se muestran. */
    public function fields(): HasMany
    {
        return $this->hasMany(TestField::class)->orderBy('sort_order');
    }

    /** Los campos que SON un resultado: los que alimentan un parámetro. */
    public function resultFields(): HasMany
    {
        return $this->fields()->whereNotNull('output_analyte_id');
    }

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
     * scopeFilter — filtros del listado sobre la tabla test_definitions.
     * Soporta name (multi-tag accent-insensitive), code (substring), grupo,
     * las banderas de patrón/duplicado, is_active, rangos, filtros avanzados
     * y favoritos.
     */
    public function scopeFilter($query, $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        $tbl = 'test_definitions';

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

        $query->when($request->filled('code'), function ($q) use ($request, $tbl) {
            $q->whereRaw(config('database.default') === 'pgsql' ? "{$tbl}.code LIKE ?" : "{$tbl}.code LIKE ? ESCAPE '\\'", [LikeQuery::contains((string) $request->code)]);
        });

        $query->when($request->filled('test_group_id'), function ($q) use ($request, $tbl) {
            $ids = is_array($request->test_group_id) ? $request->test_group_id : [$request->test_group_id];
            $q->whereIn("{$tbl}.test_group_id", array_map('intval', $ids));
        });

        foreach (['has_control', 'requires_control', 'requires_duplicate', 'is_grouped'] as $flag) {
            $query->when($request->filled($flag), function ($q) use ($request, $tbl, $flag) {
                $q->where("{$tbl}.{$flag}", filter_var($request->input($flag), FILTER_VALIDATE_BOOLEAN));
            });
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
        } elseif ($sort === 'group' && in_array($direction, ['asc', 'desc'])) {
            $query->leftJoin('test_groups', "{$tbl}.test_group_id", '=', 'test_groups.id')
                  ->orderBy('test_groups.name', $direction);
        } elseif (in_array($sort, ['id', 'name', 'code', 'replicates', 'is_active', 'sort_order', 'created_at', 'updated_at']) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy("{$tbl}.{$sort}", $direction);
        }

        return $query;
    }

    /**
     * El cuadro de filtros del listado.
     *
     * `$opts['groups']` son las opciones del filtro por grupo, en el shape
     * `[{value, label}]`. Se reciben en vez de consultarse acá porque este
     * método lo llama también `scopeFilter` en cada consulta: cargar el catálogo
     * para VALIDAR una cláusula sería una consulta de más en cada listado. Sin
     * las opciones el FilterApplier deja pasar el id igual (filtrar por uno
     * inexistente devuelve cero filas, no es un riesgo) — mismo criterio que
     * `Customer::filterSchema` con el país.
     *
     * @param  array<string, mixed> $opts
     * @return array<int, array{key: string, label: string, type: string, operators: array<int, string>}>
     */
    public static function filterSchema(array $opts = []): array
    {
        return [
            ['key' => 'code',               'label' => __('test_definitions.code'),               'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'name',               'label' => __('test_definitions.name'),               'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            // La familia de la prueba (Físico Químico · Cromatografías · Otros).
            // El listado ya MOSTRABA la columna pero no se podía filtrar por
            // ella: el cuadro de filtros que la ofrecía no lo abre ningún botón
            // de la pantalla, así que en la práctica no existía.
            ['key' => 'test_group_id',      'label' => __('test_definitions.group'),              'type' => 'enum',    'operators' => ['=', '!=', 'in'], 'options' => $opts['groups'] ?? []],
            ['key' => 'container',          'label' => __('test_definitions.container'),          'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'chart_unit',         'label' => __('test_definitions.chart_unit'),         'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'replicates',         'label' => __('test_definitions.replicates'),         'type' => 'number',  'operators' => ['=', '!=', '>', '<', '>=', '<=']],
            ['key' => 'has_control',        'label' => __('test_definitions.has_control'),        'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'requires_control',   'label' => __('test_definitions.requires_control'),   'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'requires_duplicate', 'label' => __('test_definitions.requires_duplicate'), 'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'is_active',          'label' => __('test_definitions.is_active'),          'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'created_at',         'label' => __('global.created_at'),                   'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'updated_at',         'label' => __('global.updated_at'),                   'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
