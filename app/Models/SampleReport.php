<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un informe emitido (o en borrador) sobre una muestra.
 *
 * El PRIMERO de una muestra es el principal; los siguientes son adicionales
 * —una prueba que llegó tarde, una corrección—. Esa distinción existía en el
 * sistema anterior (`type_report` 0/1) y se conserva porque el laboratorio la
 * usa: los listados y las estadísticas cuentan principales.
 *
 * Un informe EMITIDO no se edita. Si algo cambia, se emite un adicional. Es la
 * misma razón por la que el correlativo no se recicla: el cliente tiene un papel
 * con ese número y el portal de verificación tiene que seguir encontrándolo.
 */
class SampleReport extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const KIND_PRIMARY    = 'primary';
    public const KIND_ADDITIONAL = 'additional';

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_ISSUED = 'issued';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ISSUED];

    protected $guarded = [];

    protected $casts = [
        'year'         => 'integer',
        'number'       => 'integer',
        'issued_at'    => 'date',
        'delivered_at' => 'date',
        'snapshot'     => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Las filas que dicen qué ensayos salen impresos. */
    public function visibilities(): HasMany
    {
        return $this->hasMany(SampleReportTest::class);
    }

    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(SampleTest::class, 'sample_report_tests')
            ->withPivot('is_visible')
            ->withTimestamps();
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** REP-LAB-2026-0800. */
    public static function formatCode(int $year, int $number): string
    {
        return sprintf('REP-LAB-%d-%04d', $year, $number);
    }

    /**
     * Lo que este informe DEBE imprimir, o null si hay que calcularlo en vivo.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ UN INFORME EMITIDO SE REIMPRIME IGUAL, SIEMPRE                       │
     * └──────────────────────────────────────────────────────────────────────┘
     * Al emitir, el contenido queda congelado en `snapshot`. Desde ese momento
     * el PDF sale de AHÍ: si el laboratorio corrige el TAG del equipo o llega
     * una prueba nueva, el papel reimpreso tiene que seguir diciendo lo que
     * decía el que el cliente tiene en la mano. Reimprimir "con lo último" un
     * documento ya emitido no es una mejora — es un segundo documento
     * circulando con el mismo número.
     *
     * El borrador, en cambio, se calcula en vivo a propósito: todavía se está
     * corrigiendo y tiene que mostrar lo corregido.
     */
    public function frozenPayload(): ?array
    {
        if (! $this->isIssued() || ! is_array($this->snapshot) || $this->snapshot === []) {
            return null;
        }

        return $this->snapshot;
    }

    /**
     * Los ids de las pruebas que este informe publica.
     *
     * @return array<int,int>
     */
    public function visibleTestIds(): array
    {
        return $this->visibilities
            ->where('is_visible', true)
            ->pluck('sample_test_id')
            ->all();
    }
}
