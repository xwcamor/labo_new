<?php

namespace App\Services\Lab;

/**
 * Repetibilidad: compara el duplicado contra su original.
 *
 * El sistema Rails viejo YA obligaba a cargar duplicados —el formulario no
 * dejaba registrar una muestra hasta que la hoja tuviera al menos un patrón y
 * un duplicado— y después no hacía absolutamente nada con ellos. Se cargaban,
 * se guardaban, y nadie los comparaba nunca. El analista hacía el trabajo doble
 * y el dato se perdía.
 *
 * La repetibilidad es lo que dice si el método está midiendo consistentemente:
 * dos alícuotas de la misma muestra, medidas por el mismo analista con el mismo
 * equipo el mismo día, tienen que dar prácticamente lo mismo. Cuánto es
 * "prácticamente" lo fija la norma de cada ensayo, y por eso el criterio vive
 * en la carta de control (`qc_charts.repeatability_limit`) y no acá.
 *
 * DOS CRITERIOS, PORQUE LAS NORMAS USAN LOS DOS
 *   absolute   la diferencia no puede pasar de X en las unidades del ensayo.
 *              Sirve donde el valor se mueve poco (agua en ppm, acidez).
 *   relative   la diferencia no puede pasar del X% del promedio de las dos
 *              lecturas. Sirve donde el valor barre órdenes de magnitud: 2 ppm
 *              de diferencia es enorme en un gas que da 5 ppm y despreciable en
 *              uno que da 4000.
 *
 * El porcentaje se toma sobre el PROMEDIO de las dos lecturas y no sobre la
 * primera, porque ninguna de las dos es "la verdadera": son dos medidas del
 * mismo material y elegir una como referencia haría que el resultado cambiara
 * según cuál se cargó primero.
 */
class RepeatabilityEvaluator
{
    public const MODE_ABSOLUTE = 'absolute';
    public const MODE_RELATIVE = 'relative';

    /**
     * @param  float|null $a     Lectura del original.
     * @param  float|null $b     Lectura del duplicado.
     * @param  float|null $limit Criterio. Sin criterio cargado se calcula la
     *                           diferencia igual, pero no se dictamina: es
     *                           mejor mostrar el número que fingir que aprobó.
     * @param  string     $mode  absolute | relative
     * @return array{difference:?float,relative:?float,mean:?float,within:?bool}
     */
    public function compare(?float $a, ?float $b, ?float $limit, string $mode = self::MODE_ABSOLUTE): array
    {
        if ($a === null || $b === null || ! is_finite($a) || ! is_finite($b)) {
            return ['difference' => null, 'relative' => null, 'mean' => null, 'within' => null];
        }

        $difference = abs($a - $b);
        $mean = ($a + $b) / 2.0;

        // Con promedio cero el porcentaje no existe. Pasa de verdad: un gas no
        // detectado da 0.0 en las dos lecturas. Ahí la diferencia es 0 y el
        // ensayo repite perfecto, así que se informa 0% en vez de dejar el
        // dato en nulo, que se leería como "no evaluado".
        $relative = $mean == 0.0
            ? ($difference == 0.0 ? 0.0 : null)
            : ($difference / abs($mean)) * 100.0;

        $within = null;
        if ($limit !== null && $limit >= 0) {
            $within = $mode === self::MODE_RELATIVE
                ? ($relative !== null && $relative <= $limit)
                : ($difference <= $limit);
        }

        return [
            'difference' => round($difference, 8),
            'relative'   => $relative === null ? null : round($relative, 6),
            'mean'       => round($mean, 8),
            'within'     => $within,
        ];
    }
}
