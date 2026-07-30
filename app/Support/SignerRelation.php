<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;

/**
 * Con qué relación firma un firmante el informe.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ EXISTE ESTA CLASE                                                │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Había DOS listas de etiquetas para las mismas seis relaciones, y no coincidían:
 *
 *     approvals.relation.prepared = "Elaborado por"
 *     reports.relation.prepared   = "Realizado por"
 *
 *     approvals.relation.endorsed = "Visado por"
 *     reports.relation.endorsed   = "Avalado por"
 *
 * La pantalla de "Mi workspace", la bandeja de Aprobaciones y las solicitudes
 * leían la primera; los DOS PDF, el modal de análisis y el portal de verificación
 * leían la segunda. El efecto para el usuario: cambiaba la etiqueta, la veía
 * cambiada en la pantalla, y el papel seguía diciendo la palabra de antes. Nada
 * en pantalla explicaba por qué, y "no funciona" era la conclusión correcta.
 *
 * Ahora hay UNA lista —`approvals.relation`, la que se ve en pantalla— y todo
 * pasa por acá. Cambiar "Elaborado por" es editar un renglón de un archivo.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ LA LISTA ES CERRADA                                              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Son seis claves y no texto libre porque es lo que se imprime SOBRE la línea de
 * firma, y tiene que decir lo mismo en los dos idiomas. Con texto libre
 * terminarían conviviendo "Aprobado por", "aprobado" y "APROBÓ" como tres
 * relaciones distintas en el mismo informe. El CARGO —"Jefe de Laboratorio"— sí
 * es texto libre, porque es de la persona y no del documento.
 */
final class SignerRelation
{
    /** Las seis relaciones que admite un informe. */
    public const ALL = [
        'prepared', 'reviewed', 'approved', 'authorized', 'verified', 'endorsed',
    ];

    /** La que se usa cuando el firmante no declara ninguna. */
    public const DEFAULT = 'approved';

    /**
     * La etiqueta impresa de una relación, en el idioma activo.
     *
     * Una relación que no esté en la lista cae en la de omisión en vez de
     * imprimir la clave de traducción: un informe que dice
     * "reports.relation.foo" sobre una línea de firma es peor que uno que dice
     * "Aprobado por".
     */
    public static function label(?string $relation): string
    {
        $clave = in_array($relation, self::ALL, true) ? $relation : self::DEFAULT;

        return __('approvals.relation.'.$clave);
    }

    /**
     * Las seis, para armar un desplegable.
     *
     * @return array<string,string> clave => etiqueta
     */
    public static function options(): array
    {
        return collect(self::ALL)
            ->mapWithKeys(fn (string $r) => [$r => self::label($r)])
            ->all();
    }

    /** ¿La lista de etiquetas está cargada para el idioma activo? */
    public static function labelsLoaded(): bool
    {
        return Lang::has('approvals.relation.'.self::DEFAULT);
    }
}
