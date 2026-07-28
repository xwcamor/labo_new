<?php

namespace App\Services\Lab;

use App\Models\Equipment;
use App\Models\Sample;
use App\Models\SpecLimit;
use App\Models\SpecSet;
use Illuminate\Support\Carbon;

/**
 * El veredicto de un resultado contra la norma.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ EL VEREDICTO SE GUARDA Y NO SE CALCULA AL LEER                   │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Las normas cambian y los criterios del laboratorio también. Un ensayo de 2019
 * tiene que seguir diciendo lo que decía en 2019: si el veredicto se recalculara
 * al abrir el informe, un cambio de límite reescribiría la historia en silencio
 * y un certificado ya emitido dejaría de coincidir con lo que muestra la
 * pantalla.
 *
 * Por eso `results` guarda el estado, los dos límites que se aplicaron y de qué
 * norma salieron. Es el mismo criterio que ya usan los puntos de las cartas de
 * control.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ SIN CRITERIO NO ES "CUMPLE"                                              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Cuando no hay cuadro para ese fluido y ese equipo, o el cuadro no declara ese
 * parámetro, el estado queda en NULO y el informe tiene que decirlo. El sistema
 * anterior rellenaba con un guion, que en una tabla de resultados se lee como
 * "no hay nada que observar".
 */
class SpecEvaluator
{
    /** @var array<string,\App\Models\SpecSet|null> */
    private array $cache = [];

    public function __construct(
        private readonly SpecSetResolver $resolver = new SpecSetResolver(),
    ) {
    }

    /**
     * Las cuatro columnas del veredicto para un valor medido.
     *
     * No escribe ni recibe un Result guardado: devuelve el arreglo para que
     * quien materializa lo guarde EN LA MISMA operación, sin una segunda
     * escritura por fila.
     *
     * @return array{spec_status:?string,spec_min:?float,spec_max:?float,spec_source:?string}
     */
    public function verdictFor(
        ?SpecSet $set,
        int $analyteId,
        ?float $valueNum,
        ?string $valueText = null,
        ?string $qualifier = null,
    ): array {
        $vacio = [
            'spec_status' => null,
            'spec_min'    => null,
            'spec_max'    => null,
            'spec_source' => null,
        ];

        if (! $set) {
            return $vacio;
        }

        $limite = $set->limits->first(
            fn (SpecLimit $l) => (int) $l->analyte_id === $analyteId
        );

        if (! $limite) {
            // El cuadro existe pero no dice nada de este parámetro. Es
            // información: significa que la norma no fija un valor típico para
            // él, no que el valor esté bien.
            return $vacio;
        }

        $estado = $valueNum !== null
            ? $this->verdictForNumber($limite, $valueNum, $qualifier)
            : $limite->verdictForText($valueText);

        return [
            'spec_status' => $estado,
            'spec_min'    => $limite->min_value !== null ? (float) $limite->min_value : null,
            'spec_max'    => $limite->max_value !== null ? (float) $limite->max_value : null,
            // De qué salió el límite. La norma si el cuadro la declara; si no,
            // el nombre del cuadro — que es lo que el sistema anterior tenía, y
            // decirlo así es más honesto que citar una norma que nadie asignó.
            'spec_source' => $set->standard?->label() ?? $set->label ?? $set->code,
        ];
    }

    /**
     * El cuadro que le toca a una fila de bancada, para una familia de ensayos.
     *
     * Se guarda en memoria por (muestra o equipo, familia): una hoja de
     * cromatografía con 6 muestras y 11 parámetros son 66 resultados, y
     * resolver el cuadro en cada uno serían 66 consultas para llegar a los
     * mismos 6 cuadros.
     */
    public function setFor(
        ?int $sampleId,
        ?int $equipmentId,
        ?int $tenantId,
        string $group,
        ?Carbon $at = null,
    ): ?SpecSet {
        $clave = implode('|', [$sampleId ?? 'e' . $equipmentId, $group, $at?->toDateString() ?? '']);

        if (array_key_exists($clave, $this->cache)) {
            return $this->cache[$clave];
        }

        if ($sampleId) {
            $muestra = Sample::withoutGlobalScopes()->with('equipment')->find($sampleId);

            if ($muestra) {
                return $this->cache[$clave] = $this->resolver->forSample($muestra, $group, $at);
            }
        }

        // Sin muestra —el camino de las filas cargadas antes de que existiera la
        // recepción— se resuelve por el equipo directo de la fila.
        $equipo = $equipmentId ? Equipment::withoutGlobalScopes()->find($equipmentId) : null;

        return $this->cache[$clave] = $this->resolver->resolve(
            group: $group,
            oilTypeId: $equipo?->oil_type_id,
            equipment: $equipo,
            tenantId: $tenantId,
            at: $at,
        );
    }

    /**
     * El veredicto de un valor numérico, teniendo en cuenta la censura.
     *
     * Un valor censurado no se juzga como el número que lleva al lado:
     *
     *   ">75 kV"  el ensayador llegó a su tope sin que el aceite rompiera. Si el
     *             límite es un MÍNIMO, cumple con seguridad. Si fuera un máximo,
     *             no se puede afirmar nada.
     *   "<2 ppm"  por debajo del límite de detección. Contra un MÁXIMO cumple;
     *             contra un mínimo no se puede afirmar nada.
     *
     * El sistema anterior limpiaba el signo antes de convertir a número
     * (`@pcb_value.split.join.to_f`), así que ">75" y "75" quedaban iguales.
     */
    private function verdictForNumber(SpecLimit $limite, float $valor, ?string $qualifier): ?string
    {
        $tieneMin = $limite->min_value !== null;
        $tieneMax = $limite->max_value !== null;

        if ($qualifier === 'gt') {
            // "al menos $valor": cumple si el límite es un mínimo y lo alcanza.
            if ($tieneMax) {
                return null;
            }

            return $valor >= (float) $limite->min_value
                ? SpecLimit::IN_SPEC
                : null;   // ni siquiera el piso del rango alcanza para afirmar
        }

        if ($qualifier === 'lt') {
            // "a lo sumo $valor": cumple si el límite es un máximo.
            if ($tieneMin) {
                return null;
            }

            return $valor <= (float) $limite->max_value
                ? SpecLimit::IN_SPEC
                : null;
        }

        return $limite->verdict($valor);
    }
}
