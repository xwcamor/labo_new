<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenantOrGlobal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Las listas chicas que llenan el formulario del informe.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ RESUELVE                                                             │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Cuatro campos del informe eran TEXTO LIBRE, y por eso la base termina con
 * «2500 gal», «2500 galones» y «2500Gal» para la misma unidad: después no se
 * puede filtrar, ni agrupar, ni sumar. En el sistema anterior eran cuatro
 * tablas sin pantalla, cargadas por base.
 *
 * Las cuatro tienen la misma forma —nombre, activo, orden— y se administran
 * juntas, así que van en una tabla con una columna que dice de cuál lista es.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LO QUE SE GUARDA EN LA MUESTRA ES EL TEXTO, NO EL ID                     │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `samples.sampling_reason` y sus hermanas siguen siendo texto. Es deliberado:
 * el informe imprime la frase, y un informe EMITIDO no puede cambiar porque
 * alguien renombró una fila del catálogo tres años después. El catálogo ofrece
 * las opciones; el papel se queda con la que se eligió, congelada — el mismo
 * criterio que el veredicto y el texto del límite.
 */
class ReportCatalog extends Model
{
    use HasFactory, SoftDeletes, Auditable, BelongsToTenantOrGlobal;

    protected string $auditModule = 'report_catalogs';

    /** Motivo por el que se pidió el análisis. */
    public const KIND_REASON = 'sampling_reason';
    /** De dónde se extrajo la muestra del equipo. */
    public const KIND_POINT = 'sampling_point';
    /** Marca comercial del aceite. */
    public const KIND_OIL_BRAND = 'oil_brand';
    /** Unidad en que se mide el volumen de aceite. */
    public const KIND_VOLUME_UNIT = 'volume_unit';

    /** Las cuatro listas, en el orden en que se muestran. */
    public const KINDS = [
        self::KIND_REASON,
        self::KIND_POINT,
        self::KIND_OIL_BRAND,
        self::KIND_VOLUME_UNIT,
    ];

    protected $fillable = [
        'slug', 'kind', 'name', 'code', 'is_active', 'sort_order',
        'legacy_id', 'tenant_id', 'created_by', 'deleted_by', 'deleted_description',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Una sola lista, activa y en su orden: es lo que consume un desplegable. */
    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Las opciones de una lista, listas para un `<Select>`.
     *
     * Devuelve el TEXTO como valor, no el id: es lo que se guarda en la muestra
     * y lo que imprime el informe. Ver el bloque de arriba.
     *
     * @return array<int,array{value:string,label:string}>
     */
    public static function options(string $kind): array
    {
        return static::ofKind($kind)
            ->get(['name'])
            ->map(fn (self $c) => ['value' => $c->name, 'label' => $c->name])
            ->all();
    }
}
