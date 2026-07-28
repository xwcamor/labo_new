<?php

namespace App\Models;

use App\Services\Lab\RepeatabilityEvaluator;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * QcChart — la carta de control de un parámetro dentro de una prueba.
 *
 * Es una carta de Shewhart, la misma que en laboratorio clínico se conoce como
 * Levey-Jennings. Las siglas del dominio, de arriba hacia abajo:
 *
 *     LCS  límite de control superior   (media + 3 desvíos)
 *     LAS  límite de alerta superior    (media + 2 desvíos)
 *     LC   línea central                (la media del patrón)
 *     LAI  límite de alerta inferior    (media − 2 desvíos)
 *     LCI  límite de control inferior   (media − 3 desvíos)
 *
 * En el sistema Rails viejo esto era la tabla `patron_tendences`: cinco límites
 * por cada uno de los nueve gases como columnas fijas (`lci`, `oxi_lci`,
 * `nit_lci`…), 45 columnas de tipo texto, y solo para cromatografía. Acá una
 * carta es una fila y sumar un parámetro es insertar otra.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA VIGENCIA NO ES UN ADORNO                                              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Cambiar el lote del patrón cambia los límites. El sistema viejo los pisaba en
 * la misma fila, así que las cartas históricas se redibujaban contra los límites
 * de hoy: un punto que en su momento estaba fuera de control aparecía adentro, o
 * al revés, y la evidencia que respalda un ensayo ya emitido cambiaba sola. Por
 * eso al cambiar el lote se cierra la carta (`effective_to`) y se abre una
 * nueva; scopeVigenteAl() elige la que regía ESE día.
 */
class QcChart extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenant;

    protected string $auditModule = 'qc_charts';

    protected $table = 'qc_charts';

    /** El criterio de repetibilidad se compara en unidades del parámetro. */
    public const REPEATABILITY_ABSOLUTE = 'absolute';

    /** El criterio se compara como porcentaje de la media del par. */
    public const REPEATABILITY_RELATIVE = 'relative';

    /** @var array<int,string> */
    public const REPEATABILITY_MODES = [
        self::REPEATABILITY_ABSOLUTE,
        self::REPEATABILITY_RELATIVE,
    ];

    protected $fillable = [
        'slug', 'test_definition_id', 'test_field_id', 'analyte_id',
        'label', 'control_lot',
        'lcl', 'lwl', 'center', 'uwl', 'ucl',
        'sd', 'is_derived', 'warn_sigma', 'action_sigma',
        'repeatability_limit', 'repeatability_mode',
        'effective_from', 'effective_to',
        'comment', 'is_active', 'legacy_id',
        'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    /**
     * Los límites y los sigmas se castean a float, al revés que
     * WorksheetValue::$value_num, y la razón es que cumplen funciones
     * distintas: el valor medido es la constancia de lo que informó el equipo y
     * no se lo debe redondear al leerlo; estos números existen SOLO para entrar
     * en una cuenta (el z de classify() y la derivación de derive()), donde
     * trabajar con cadenas obligaría a convertir en cada uso.
     */
    protected $casts = [
        'lcl'                 => 'float',
        'lwl'                 => 'float',
        'center'              => 'float',
        'uwl'                 => 'float',
        'ucl'                 => 'float',
        'sd'                  => 'float',
        'is_derived'          => 'boolean',
        'warn_sigma'          => 'float',
        'action_sigma'        => 'float',
        'repeatability_limit' => 'float',
        'effective_from'      => 'date',
        'effective_to'        => 'date',
        'is_active'           => 'boolean',
        'legacy_id'           => 'integer',
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

    // ── Relaciones ───────────────────────────────────────────────────────

    public function definition(): BelongsTo
    {
        return $this->belongsTo(TestDefinition::class, 'test_definition_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(TestField::class, 'test_field_id');
    }

    public function analyte(): BelongsTo
    {
        return $this->belongsTo(Analyte::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(QcPoint::class)->orderBy('measured_at');
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(QcDuplicate::class)->orderBy('measured_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    // ── Vigencia ─────────────────────────────────────────────────────────

    /**
     * Las cartas que regían en una fecha dada. Un extremo en nulo es un intervalo
     * abierto: sin `effective_from` la carta rige desde siempre, y sin
     * `effective_to` sigue vigente.
     *
     * Un punto histórico se evalúa SIEMPRE contra la carta vigente a su fecha de
     * medición, nunca contra la carta actual.
     */
    public function scopeVigenteAl(Builder $query, DateTimeInterface|string|null $fecha = null): Builder
    {
        $day = Carbon::parse($fecha ?? now())->toDateString();

        return $query
            ->where(function (Builder $q) use ($day) {
                $q->whereNull('qc_charts.effective_from')
                    ->orWhere('qc_charts.effective_from', '<=', $day);
            })
            ->where(function (Builder $q) use ($day) {
                $q->whereNull('qc_charts.effective_to')
                    ->orWhere('qc_charts.effective_to', '>=', $day);
            });
    }

    public function estabaVigenteAl(DateTimeInterface|string|null $fecha = null): bool
    {
        $day = Carbon::parse($fecha ?? now())->startOfDay();

        if ($this->effective_from !== null && $this->effective_from->startOfDay()->gt($day)) {
            return false;
        }

        if ($this->effective_to !== null && $this->effective_to->startOfDay()->lt($day)) {
            return false;
        }

        return true;
    }

    // ── Límites ──────────────────────────────────────────────────────────

    /**
     * Los cinco límites con los nombres del dominio. Cuando `is_derived` está en
     * verdadero manda el cálculo sobre `center` y `sd`, de modo que lo que se
     * dibuja no pueda diferir de lo que se declaró: si las columnas guardadas
     * quedaron desactualizadas frente a la media y el desvío, el que vale es el
     * cálculo.
     *
     * @return array{lci:?float,lai:?float,lc:?float,las:?float,lcs:?float}
     */
    public function limits(): array
    {
        $derived = $this->is_derived ? $this->derive() : [];

        return [
            'lci' => $derived['lcl'] ?? $this->lcl,
            'lai' => $derived['lwl'] ?? $this->lwl,
            'lc'  => $this->center,
            'las' => $derived['uwl'] ?? $this->uwl,
            'lcs' => $derived['ucl'] ?? $this->ucl,
        ];
    }

    /**
     * Los cuatro límites calculados desde la media y el desvío. Devuelve un
     * arreglo vacío si falta cualquiera de los dos: es preferible una carta sin
     * límites, que se ve, a una carta con límites inventados sobre un desvío
     * ausente, que no se ve.
     *
     * Los múltiplos son configurables (`warn_sigma` / `action_sigma`) y no fijos
     * en 2 y 3 porque algunos parámetros del laboratorio usan otros criterios.
     *
     * @return array{lcl:float,lwl:float,uwl:float,ucl:float}|array{}
     */
    public function derive(): array
    {
        $center = $this->center;
        $sd     = $this->sd;

        if ($center === null || $sd === null || $sd <= 0.0) {
            return [];
        }

        $warn   = (float) ($this->warn_sigma ?? 2);
        $action = (float) ($this->action_sigma ?? 3);

        return [
            'lcl' => $center - $action * $sd,
            'lwl' => $center - $warn * $sd,
            'uwl' => $center + $warn * $sd,
            'ucl' => $center + $action * $sd,
        ];
    }

    /**
     * Clasifica UN punto contra los límites de esta carta.
     *
     * `flag` responde solo por ese punto: `out` si pasó un límite de control,
     * `warn` si pasó uno de alerta, `ok` si está adentro. El z se informa aparte
     * porque es lo que consumen las reglas multi-punto.
     *
     * ACÁ NO VAN LAS REGLAS DE WESTGARD. Las que miran la serie —2_2s, R_4s,
     * 4_1s, 10x— necesitan los puntos anteriores de la carta y no se pueden
     * decidir con un valor suelto. Esa evaluación es de
     * App\Services\Lab\WestgardEvaluator, que recibe la serie completa y escribe
     * su veredicto en QcPoint::$westgard_rule. Meterlas en el modelo obligaría a
     * que cada llamada arrastrara el historial.
     *
     * @return array{flag:string,z:?float}
     */
    public function classify(float $valor): array
    {
        $limits = $this->limits();
        $z      = null;

        if ($this->center !== null && $this->sd !== null && $this->sd > 0.0) {
            $z = ($valor - $this->center) / $this->sd;
        }

        $flag = QcPoint::FLAG_OK;

        if (($limits['lcs'] !== null && $valor > $limits['lcs'])
            || ($limits['lci'] !== null && $valor < $limits['lci'])) {
            $flag = QcPoint::FLAG_OUT;
        } elseif (($limits['las'] !== null && $valor > $limits['las'])
            || ($limits['lai'] !== null && $valor < $limits['lai'])) {
            $flag = QcPoint::FLAG_WARN;
        }

        return ['flag' => $flag, 'z' => $z];
    }

    /**
     * El par de un duplicado contra el criterio de esta carta.
     *
     * La cuenta la hace RepeatabilityEvaluator y no este modelo: es la misma que
     * usa el motor al materializar los QcDuplicate, y tenerla escrita dos veces
     * es lo que hace que la pantalla y el registro guardado terminen
     * discrepando.
     *
     * @return array{difference:?float,relative:?float,mean:?float,within:?bool}
     */
    public function repeatability(?float $a, ?float $b): array
    {
        return (new RepeatabilityEvaluator())->compare(
            $a,
            $b,
            $this->repeatability_limit,
            $this->repeatability_mode ?? self::REPEATABILITY_ABSOLUTE
        );
    }

    /**
     * Atajo del anterior. El nulo NO significa "no se evaluó todavía": significa
     * que la carta no declara criterio de repetibilidad, y sin criterio no hay
     * nada que afirmar. Decir "cumple" ahí sería fabricar evidencia.
     */
    public function repeatabilityWithinLimit(?float $a, ?float $b): ?bool
    {
        return $this->repeatability($a, $b)['within'];
    }
}
