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
 * Signature — catálogo de marcas/fabricantes de transformadores (ABB, Siemens…).
 *
 * Metadato descriptivo del transformador (NO es eje de diagnóstico). Es un
 * catálogo PER-TENANT: cada workspace tiene su propio catálogo de marcas, por
 * eso usa BelongsToTenantOrGlobal (con bypass de super). Mantiene SoftDeletes + Auditable
 * + HasFavorites. Campos: `name`, `code` (slug técnico), `sort_order`, `is_active`,
 * `tenant_id`.
 */
class Signature extends Model
{
    /**
     * Con qué relación firma. Lista cerrada y traducible (`approvals.relation.*`,
     * vía `App\Support\SignerRelation`):
     * es lo que se imprime sobre la línea y tiene que decir lo mismo en los dos
     * idiomas. Texto libre acá daría "Aprobado por", "aprobado" y "APROBÓ" como
     * tres relaciones distintas en el mismo informe.
     */
    public const RELATIONS = [
        'prepared', 'reviewed', 'approved', 'authorized', 'verified', 'endorsed',
    ];

    use HasFactory, SoftDeletes, Auditable, BelongsToTenantOrGlobal, HasFavorites, \App\Traits\Lockable;

    protected string $auditModule = 'signatures';

    protected $fillable = [
        'slug', 'name', 'code', 'is_active', 'sort_order', 'tenant_id',
        'title', 'image', 'user_id', 'relation', 'authorizes_entry',
        'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        // Si esta persona puede AUTORIZAR EL INGRESO de una muestra, además de
        // (o en lugar de) firmar informes. Ver la migración
        // `2026_08_02_140000_add_entry_authorizer_to_receptions`.
        'authorizes_entry' => 'boolean',
    ];

    /** Los que pueden autorizar el ingreso, activos y en su orden. */
    public function scopeAuthorizers(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('authorizes_entry', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /** El usuario del sistema, si la firma corresponde a uno. */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** El nombre que se imprime bajo la línea. */
    public function printedName(): string
    {
        return $this->user?->name ?: (string) $this->name;
    }

    /**
     * La ruta de la imagen a estampar, o null si el informe debe dejar la línea
     * para firmar a mano.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ LA DEL USUARIO GANA, Y NO ES UN DETALLE                              │
     * └──────────────────────────────────────────────────────────────────────┘
     * Si la firma está enlazada a un usuario, se usa la que ESA persona cargó
     * en su perfil y solo si activó la auto-firma: es la única que existe con
     * su consentimiento, y ese consentimiento queda auditado. La imagen subida
     * desde este módulo es para el firmante que no tiene cuenta en el sistema
     * —el laboratorio igual necesita su firma en el papel— y es responsabilidad
     * de quien la sube.
     */
    public function imagePath(): ?string
    {
        if ($this->user) {
            return $this->user->auto_sign_reports ? ($this->user->signature ?: null) : null;
        }

        return $this->image ?: null;
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
     * scopeFilter — mismo patrón que Customer, sobre la tabla signatures.
     * Soporta name (multi-tag accent-insensitive), code (substring),
     * is_active (bool), rangos de fecha/id, filtros avanzados y favoritos.
     */
    public function scopeFilter($query, $request)
    {
        $isPgsql = config('database.default') === 'pgsql';
        $tbl = 'signatures';

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
        } elseif (in_array($sort, ['id', 'name', 'code', 'is_active', 'sort_order', 'created_at', 'updated_at']) && in_array($direction, ['asc', 'desc'])) {
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
            ['key' => 'name',       'label' => __('signatures.name'),     'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'code',       'label' => __('signatures.code'),     'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'is_active',  'label' => __('signatures.is_active'), 'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'created_at', 'label' => __('global.created_at'),   'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'updated_at', 'label' => __('global.updated_at'),   'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }
}
