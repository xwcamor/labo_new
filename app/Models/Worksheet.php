<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use App\Traits\HasFavorites;
use App\Traits\Lockable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Worksheet — la hoja de trabajo de bancada: una prueba, una fecha, un analista.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ CUATRO ESTADOS Y NO EL `state` 0/1 DEL SISTEMA VIEJO             │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema Rails viejo la hoja tenía una sola bandera y terminó cargando
 * dos significados distintos al mismo tiempo:
 *
 *   1. "bloqueada / desbloqueada" — el candado con el que el supervisor
 *      impedía seguir editando;
 *   2. "validada" — la revisión y firma de esa misma persona.
 *
 * El modelo Ruby conserva COMENTADAS las líneas del significado anterior, que
 * es el rastro de esa mudanza a mitad de camino. Con un solo campo no se puede
 * distinguir una hoja que se cerró para revisarla de una que ya fue revisada, y
 * el filtro de búsqueda del listado quedó con la semántica INVERTIDA respecto de
 * lo que muestra la pantalla: pedir "bloqueadas" devolvía las otras.
 *
 * Lo grave, sin embargo, no era la confusión sino que el bloqueo NO SE
 * VERIFICABA EN EL SERVIDOR: la hoja bloqueada se dibujaba con los campos
 * deshabilitados y nada más. Un POST directo al endpoint de guardado modificaba
 * una hoja bloqueada sin ninguna resistencia, que para un laboratorio acreditado
 * es exactamente lo que el bloqueo existe para impedir.
 *
 * Acá son DOS EJES INDEPENDIENTES:
 *
 *   status      dónde está la hoja en el flujo de trabajo
 *               draft → closed → validated, y voided como salida.
 *   locked_at   el candado del supervisor (trait Lockable), ortogonal al flujo.
 *               Una hoja en borrador puede estar bloqueada mientras se audita un
 *               desvío, y una validada puede quedar sin candado.
 *
 * Ambos se verifican del lado del servidor: isEditable() es la única fuente de
 * verdad y el controlador la consulta antes de aceptar cualquier escritura.
 *
 * Usa BelongsToTenant (estricto) y NO BelongsToTenantOrGlobal, que es el que
 * llevan los catálogos del proyecto. La diferencia importa: el trait de
 * catálogo trata el `tenant_id` en nulo como "global compartido", o sea que una
 * hoja sin workspace quedaría visible para todos. Una hoja de bancada es dato
 * operativo privado, nunca un estándar de fábrica.
 */
class Worksheet extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant, Lockable, HasFavorites;

    protected string $auditModule = 'worksheets';

    protected $table = 'worksheets';

    /** Se está cargando; admite escritura. */
    public const STATUS_DRAFT = 'draft';

    /** El analista terminó y la entregó; ya no se edita, falta la revisión. */
    public const STATUS_CLOSED = 'closed';

    /** El supervisor la revisó y firmó; de acá salen los resultados. */
    public const STATUS_VALIDATED = 'validated';

    /** Anulada con motivo. No se borra: el rastro es parte de la trazabilidad. */
    public const STATUS_VOIDED = 'voided';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CLOSED,
        self::STATUS_VALIDATED,
        self::STATUS_VOIDED,
    ];

    protected $fillable = [
        'slug', 'test_definition_id', 'run_date', 'analyst_id',
        'status', 'validated_by', 'validated_at', 'void_reason',
        // `sample_temp_c` es la temperatura de LA MUESTRA al ensayarla, que no
        // es la del laboratorio: el aceite entra frío del transporte y el
        // ensayo espera a que se estabilice. Va impresa en las condiciones de
        // ensayo del informe, así que tiene que poder cargarse.
        'ambient_temp_c', 'ambient_humidity', 'lab_pressure_hpa', 'sample_temp_c', 'notes', 'legacy_id',
        'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    /**
     * Las condiciones ambientales se castean a float porque solo se muestran y
     * se comparan contra un rango de aceptación; su decimal(6,2) entra sobrado
     * en la precisión de un float. El criterio para los valores MEDIDOS es otro
     * — ver el comentario de WorksheetValue::$casts.
     */
    protected $casts = [
        'run_date'         => 'date',
        'validated_at'     => 'datetime',
        'ambient_temp_c'   => 'float',
        'ambient_humidity' => 'float',
        'lab_pressure_hpa'  => 'float',
        'sample_temp_c'    => 'float',
        'legacy_id'        => 'integer',
    ];

    /**
     * El estado inicial se declara acá y no solo como valor por omisión de la
     * columna. La diferencia importa: sin esto, una instancia recién creada
     * tiene `status` en nulo hasta que se la vuelve a leer de la base, y
     * isEditable() —que es la única autorización de escritura de la bancada—
     * daría falso justo sobre la hoja que se acaba de abrir.
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
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

    /**
     * Los filtros del listado: los rápidos, el avanzado y "solo favoritas".
     *
     * Vive en el modelo y no en el controlador, que es el estándar del resto de
     * los índices. El ORDEN no está acá: lo resuelve el controlador por lista
     * blanca, porque lo que llega de la URL no entra al SQL.
     */
    public function scopeFilter(Builder $query, $request): Builder
    {
        // Prefijo `worksheets.` en todo: `orderByFavoriteFirst` agrega un left
        // join a `user_favorites` y las columnas sin calificar quedan ambiguas.
        $query->when($request->filled('test_definition'), fn ($q) => $q->whereHas(
            'definition',
            fn ($d) => $d->where('slug', $request->test_definition)
        ));

        $query->when($request->filled('status'), fn ($q) => $q->where('worksheets.status', $request->status));

        // El sistema viejo forzaba en silencio un "últimos tres meses" cuando no
        // se mandaba fecha: los ensayos más viejos eran invisibles y nada lo
        // decía. Acá el rango se pide o no se aplica.
        $query->when($request->filled('from'), fn ($q) => $q->whereDate('worksheets.run_date', '>=', $request->from));
        $query->when($request->filled('to'), fn ($q) => $q->whereDate('worksheets.run_date', '<=', $request->to));

        // Por analista: es la pregunta "qué corrí yo esta semana", y era la
        // única de las tres columnas del listado que no se podía filtrar.
        $query->when($request->filled('analyst'), fn ($q) => $q->where('worksheets.analyst_id', $request->analyst));

        // Por número de muestra. Se busca por la COLUMNA de la fila de bancada,
        // no partiendo el texto: es lo que se pregunta cuando el cliente llama
        // citando su correlativo y hay que dar con la hoja que lo corrió.
        $query->when($request->filled('sample'), fn ($q) => $q->whereHas(
            'rows',
            fn ($r) => $r->where('sample_code', 'like', '%' . $request->sample . '%')
        ));

        $advanced = $request->input('advanced_where');
        if (is_string($advanced)) {
            $advanced = json_decode($advanced, true) ?: null;
        }
        if (is_array($advanced) && ! empty($advanced)) {
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
                        ->whereColumn('user_favorites.favoritable_id', 'worksheets.id')
                        ->where('user_favorites.favoritable_type', static::class)
                        ->where('user_favorites.user_id', $userId);
                });
            }
        }

        return $query;
    }

    /**
     * Los campos que el filtro avanzado ofrece.
     *
     * Es el mismo contrato que el resto de los índices del sistema: la pantalla
     * arma la consulta con estas claves y `FilterApplier` la traduce. Sin esto,
     * el listado solo se podía acotar por prueba, estado y rango de fechas —
     * tres desplegables fijos—, y buscar "las hojas de agosto que quedaron sin
     * validar y tienen más de diez muestras" no tenía forma.
     *
     * Las condiciones ambientales entran porque son criterio real de auditoría:
     * "mostrame las corridas con la sala por encima de 25 °C".
     *
     * @return array<int, array<string, mixed>>
     */
    public static function filterSchema(): array
    {
        return [
            [
                'key' => 'status', 'label' => __('worksheets.status'), 'type' => 'enum',
                'operators' => ['=', '!=', 'in'],
                'options'   => array_map(fn ($s) => [
                    'value' => $s,
                    'label' => __('worksheets.state.' . $s),
                ], self::STATUSES),
            ],
            ['key' => 'run_date',         'label' => __('worksheets.run_date'),         'type' => 'date',   'operators' => ['=', '>', '<', '>=', '<=']],
            ['key' => 'validated_at',     'label' => __('worksheets.validated_at'),     'type' => 'date',   'operators' => ['>', '<', '>=', '<=']],
            ['key' => 'ambient_temp_c',   'label' => __('worksheets.ambient_temp_c'),   'type' => 'number', 'operators' => ['=', '!=', '>', '<', '>=', '<=']],
            ['key' => 'ambient_humidity', 'label' => __('worksheets.ambient_humidity'), 'type' => 'number', 'operators' => ['=', '!=', '>', '<', '>=', '<=']],
            ['key' => 'lab_pressure_hpa', 'label' => __('worksheets.lab_pressure_hpa'), 'type' => 'number', 'operators' => ['=', '!=', '>', '<', '>=', '<=']],
            ['key' => 'notes',            'label' => __('worksheets.notes'),            'type' => 'string', 'operators' => ['contains']],
            ['key' => 'created_at',       'label' => __('global.created_at'),           'type' => 'date',   'operators' => ['>', '<', '>=', '<=']],
        ];
    }

    // ── Relaciones ───────────────────────────────────────────────────────

    public function definition(): BelongsTo
    {
        return $this->belongsTo(TestDefinition::class, 'test_definition_id');
    }

    /** Quien corrió el ensayo. withTrashed: un analista dado de baja no borra su firma. */
    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analyst_id')->withTrashed();
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by')->withTrashed();
    }

    public function rows(): HasMany
    {
        return $this->hasMany(WorksheetRow::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Todos los valores de la hoja sin pasar por las filas. Sirve para el
     * guardado masivo y para las consultas de control de calidad, que trabajan
     * sobre la hoja entera y no fila por fila.
     */
    public function values(): HasManyThrough
    {
        return $this->hasManyThrough(
            WorksheetValue::class,
            WorksheetRow::class,
            'worksheet_id',
            'worksheet_row_id'
        );
    }

    /** Archivos crudos de instrumento importados a esta hoja. */
    public function files(): HasMany
    {
        return $this->hasMany(InstrumentFile::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    // ── Scopes de estado ─────────────────────────────────────────────────

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('worksheets.status', $status);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('worksheets.status', self::STATUS_DRAFT);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('worksheets.status', self::STATUS_CLOSED);
    }

    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('worksheets.status', self::STATUS_VALIDATED);
    }

    public function scopeVoided(Builder $query): Builder
    {
        return $query->where('worksheets.status', self::STATUS_VOIDED);
    }

    /** Lo que sigue en circulación: ni validado ni anulado. */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('worksheets.status', [self::STATUS_DRAFT, self::STATUS_CLOSED]);
    }

    // ── Estado del flujo ─────────────────────────────────────────────────

    /**
     * Única fuente de verdad para "¿se puede escribir en esta hoja?". El
     * controlador la consulta antes de aceptar el POST; el sistema viejo confiaba
     * en que el formulario viniera deshabilitado y un POST directo lo salteaba.
     */
    public function isEditable(): bool
    {
        // Lo único que cierra la hoja es el CANDADO. No hay botón que la firme:
        // el candado lo pone el sistema a los N meses (ajuste
        // `worksheets.auto_lock_months`) y quitarlo es una decisión explícita
        // que queda auditada. Es como funciona el sistema anterior, y evita el
        // paso intermedio en el que la hoja quedaba congelada porque alguien
        // apretó un botón que nadie sabía cuándo correspondía apretar.
        return $this->locked_at === null && ! $this->isVoided();
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    /**
     * Regla de control de calidad del sistema viejo, ahora del lado del
     * servidor: mientras la prueba exija patrón o duplicado y la hoja no los
     * tenga, no se admiten filas de muestra.
     *
     * En el sistema Rails esto vivía en la VISTA: el select de tipo de fila solo
     * ofrecía "Patrón" y "Duplicado" hasta que hubiera uno de cada. Como la regla
     * estaba en el HTML, un POST directo cargaba muestras sin ningún control
     * cargado, y el ensayo quedaba sin respaldo de calidad sin que nadie se
     * enterara.
     */
    public function canAcceptSamples(): bool
    {
        return $this->missingPrerequisites() === [];
    }

    /**
     * Qué le falta a la hoja para admitir muestras, como lista de `kind` de
     * WorksheetRow. Se devuelve el dato y no el texto para que el controlador
     * arme el mensaje con sus propias claves de idioma.
     *
     * @return array<int,string> por ejemplo ['control', 'duplicate']
     */
    public function missingPrerequisites(): array
    {
        $definition = $this->relationLoaded('definition')
            ? $this->getRelation('definition')
            : $this->definition()->first();

        if ($definition === null) {
            return [];
        }

        $missing = [];

        if ($definition->requires_control && ! $this->hasRowOfKind(WorksheetRow::KIND_CONTROL)) {
            $missing[] = WorksheetRow::KIND_CONTROL;
        }

        if ($definition->requires_duplicate && ! $this->hasRowOfKind(WorksheetRow::KIND_DUPLICATE)) {
            $missing[] = WorksheetRow::KIND_DUPLICATE;
        }

        return $missing;
    }

    /**
     * Se resuelve sobre la colección ya cargada cuando la hay. La pantalla de la
     * hoja trae las filas siempre; consultar la base por cada tipo agregaría dos
     * consultas por hoja en el listado.
     */
    private function hasRowOfKind(string $kind): bool
    {
        if ($this->relationLoaded('rows')) {
            return $this->getRelation('rows')
                ->contains(fn (WorksheetRow $row): bool => $row->kind === $kind);
        }

        return $this->rows()->where('kind', $kind)->exists();
    }
}
