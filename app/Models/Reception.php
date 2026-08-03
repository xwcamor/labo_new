<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use App\Traits\HasFavorites;
use App\Traits\Lockable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reception — la remisión: una entrega física de muestras de un cliente.
 *
 * Es la puerta de entrada del laboratorio y la pieza de la que cuelga todo lo
 * demás: la muestra sabe de qué equipo es porque lo dice la recepción, no
 * porque el analista lo elija con el envase en la mano.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ HAY UN BORRADOR ANTES DE CONFIRMAR                               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El correlativo se emite al CONFIRMAR, no al crear. Mientras la recepción está
 * en borrador se corrige el cliente, la cantidad de envases o el muestreador sin
 * quemar números. Una vez confirmada, los correlativos existen y no se
 * devuelven: si una muestra se da de baja, su número queda anulado, no vuelve a
 * la bolsa.
 *
 * Eso último es deliberado y va contra lo que hacía el sistema anterior, que
 * buscaba el último número filtrando por `deleted = 0` y por lo tanto reemitía
 * el número de una muestra dada de baja para otra muestra distinta. En un
 * laboratorio eso es un resultado atribuido al equipo equivocado.
 */
class Reception extends Model
{
    use SoftDeletes;
    use BelongsToTenant;
    use Auditable;
    use Lockable;
    use HasFavorites;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CONFIRMED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'receptions';

    // `$guarded = []` cubre también las dos columnas de cabecera del informe
    // —`contact_info` y `end_user`—: no hay `$fillable` que ampliar.
    protected $guarded = [];

    protected $attributes = ['status' => self::STATUS_DRAFT];

    protected $casts = [
        'received_at'   => 'datetime',
        'due_at'        => 'date',
        'confirmed_at'  => 'datetime',
        'container_ok'  => 'boolean',
        'volume_ok'     => 'boolean',
        'label_ok'      => 'boolean',
        'is_urgent'     => 'boolean',
        'packages'      => 'integer',
        'locked_at'     => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ── Relaciones ───────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sampler(): BelongsTo
    {
        return $this->belongsTo(Sampler::class, 'sampler_id');
    }

    /**
     * Quién autorizó el ingreso de la muestra.
     *
     * Sale del catálogo `entry_authorizers` — el «Personal de Laboratorio» del
     * sistema anterior (`rem_user_signatures`), que es una lista PROPIA,
     * separada de los firmantes de informes. Su firma es la que se imprime en
     * el acta de recepción.
     */
    public function authorizer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EntryAuthorizer::class, 'authorized_by_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class)->orderBy('number');
    }

    public function sampleTests(): HasManyThrough
    {
        return $this->hasManyThrough(SampleTest::class, Sample::class);
    }

    // ── Estado ───────────────────────────────────────────────────────────

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    // ── Filtros del listado ──────────────────────────────────────────────

    /**
     * Los filtros del listado, en el modelo — el estándar de los índices
     * (mismo patrón que Sampler/Customer). Los rápidos son los que la pantalla
     * siempre tuvo (estado, cliente, fechas, urgente, número de muestra); los
     * avanzados llegan como cláusulas estructuradas (`advanced_where`) y los
     * aplica FilterApplier contra `filterSchema()`. El ORDEN no vive acá: el
     * controlador lo resuelve por lista blanca.
     */
    public function scopeFilter(Builder $query, $request): Builder
    {
        // Prefijo `receptions.` en todo: `orderByFavoriteFirst` agrega un left
        // join a `user_favorites` y las columnas sin calificar quedan ambiguas.
        $query->when($request->filled('status'), fn ($q) => $q->where('receptions.status', $request->status));
        $query->when($request->filled('customer'), fn ($q) => $q->whereHas(
            'customer',
            fn ($c) => $c->where('slug', $request->customer)
        ));
        $query->when($request->filled('from'), fn ($q) => $q->whereDate('receptions.received_at', '>=', $request->from));
        $query->when($request->filled('to'), fn ($q) => $q->whereDate('receptions.received_at', '<=', $request->to));
        $query->when($request->boolean('urgent'), fn ($q) => $q->where('receptions.is_urgent', true));

        // Buscar por número de muestra. Es lo que el cliente cita por teléfono,
        // y por eso es una búsqueda por COLUMNA y no un parseo de la cadena:
        // el sistema anterior partía "2026-0695" en tres lugares distintos,
        // cada uno con su propia forma de romperse.
        $query->when($request->filled('sample'), fn ($q) => $q->whereHas(
            'samples',
            fn ($s) => $s->where('code', 'like', '%' . $request->sample . '%')
        ));

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
                $query->whereExists(function ($q) use ($userId) {
                    $q->select(\DB::raw(1))
                      ->from('user_favorites')
                      ->whereColumn('user_favorites.favoritable_id', 'receptions.id')
                      ->where('user_favorites.favoritable_type', static::class)
                      ->where('user_favorites.user_id', $userId);
                });
            }
        }

        return $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filterSchema(): array
    {
        return [
            ['key' => 'code',          'label' => __('receptions.code'),          'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            ['key' => 'service_order', 'label' => __('receptions.service_order'), 'type' => 'string',  'operators' => ['=', '!=', 'contains']],
            [
                'key' => 'status', 'label' => __('receptions.status'), 'type' => 'enum',
                'operators' => ['=', '!=', 'in'],
                'options'   => array_map(fn ($s) => [
                    'value' => $s,
                    'label' => __('receptions.status_' . $s),
                ], self::STATUSES),
            ],
            ['key' => 'is_urgent',   'label' => __('receptions.is_urgent'),   'type' => 'boolean', 'operators' => ['=']],
            ['key' => 'packages',    'label' => __('receptions.packages'),    'type' => 'number',  'operators' => ['=', '!=', '>', '<', '>=', '<=']],
            ['key' => 'received_at', 'label' => __('receptions.received_at'), 'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'due_at',      'label' => __('receptions.due_at'),      'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'created_at',  'label' => __('global.created_at'),      'type' => 'date',    'operators' => ['>', '<', '>=', '<=']],
        ];
    }

    /**
     * Quién tomó la muestra.
     *
     * Sale del CATÁLOGO de muestreadores, no de los usuarios del sistema. El
     * catálogo real del laboratorio tiene doce entradas y ninguna es una
     * persona: LABORATORIO, SERVICE CAMPO, REPARACIONES, CLIENTE INTERNO,
     * CLIENTE, PPMV, PPHV, PA, PS, DM, ABB, SUBCONTRATISTA. Son áreas propias,
     * el cliente y terceros, y es lo que imprime el informe acreditado
     * ("Muestra extraída por: Cliente").
     *
     * Esto estuvo apuntando a `users`, con un texto libre al lado como escape.
     * Con eso, "Cliente", "cliente" y "CLIENTE " eran tres muestreadores
     * distintos: no se podía filtrar ni contar, y el informe imprimía lo que
     * alguien tipeó ese día. `sampler_name` queda solo hasta traspasar los datos
     * ya cargados.
     */
    public function samplerLabel(): ?string
    {
        return $this->sampler?->name ?: $this->sampler_name;
    }

    /**
     * Lo que le falta a la recepción para poder trabajarse.
     *
     * Se calcula al pedirlo y NO se guarda porque no se lista por esto: la
     * pantalla lo muestra en la ficha de una sola recepción. Lo que sí se
     * guarda —porque se lista y se filtra— es el estado de cada prueba pedida.
     *
     * @return array<int,string>
     */
    public function missingData(): array
    {
        $faltantes = [];

        if ($this->samples()->whereNull('equipment_id')->exists()) {
            $faltantes[] = 'equipment';
        }

        if ($this->samples()->doesntHave('tests')->exists()) {
            $faltantes[] = 'tests';
        }

        return $faltantes;
    }
}
