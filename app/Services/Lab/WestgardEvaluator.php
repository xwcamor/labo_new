<?php

namespace App\Services\Lab;

/**
 * Reglas de Westgard sobre una carta de control.
 *
 * QUÉ AGREGA ESTO
 * El sistema Rails viejo dibujaba los cinco límites (LCI/LAI/LC/LAS/LCS) como
 * cinco líneas del gráfico y ahí terminaba: no evaluaba nada, no avisaba nada.
 * Si el patrón control se salía de control, el analista tenía que darse cuenta
 * mirando. Y como los valores se interpolaban sin escapar dentro del bloque de
 * JavaScript del gráfico, un valor vacío o el texto "NaN" —que esa base tiene,
 * porque las fórmulas fallidas se guardaban así— rompía el gráfico ENTERO y no
 * se veía ninguna línea.
 *
 * Las reglas de Westgard son el criterio estándar de laboratorio para decidir
 * si una corrida se acepta o se rechaza. Se evalúan sobre la SERIE, no sobre el
 * punto suelto: ahí está la diferencia con el semáforo simple de un solo valor.
 *
 * QUÉ REGLA SIGNIFICA QUÉ
 *   1_3s   un punto fuera de 3 desvíos. Rechazo. Error aleatorio grande.
 *   2_2s   dos puntos seguidos fuera de 2 desvíos DEL MISMO LADO. Rechazo.
 *          Es error sistemático: el método se corrió.
 *   R_4s   dos puntos seguidos separados por más de 4 desvíos, uno de cada
 *          lado. Rechazo. Error aleatorio: la dispersión creció.
 *   4_1s   cuatro puntos seguidos fuera de 1 desvío del mismo lado. Aviso.
 *          Sesgo naciente.
 *   10x    diez puntos seguidos del mismo lado de la media. Aviso. El patrón
 *          se corrió aunque ninguno se haya salido.
 *
 * Las tres primeras rechazan la corrida; las dos últimas avisan. El criterio de
 * cuál rechaza y cuál avisa lo fija el laboratorio en su procedimiento; acá se
 * usa el reparto clásico de Westgard y queda declarado en RULES para poder
 * cambiarlo en un solo lugar.
 *
 * NO evalúa contra los límites publicados por una norma: eso es otra cosa. Esto
 * mide si el LABORATORIO está midiendo bien, no si el aceite está bien.
 */
class WestgardEvaluator
{
    /** Regla => si su violación rechaza la corrida o solo avisa. */
    public const RULES = [
        '1_3s' => 'out',
        '2_2s' => 'out',
        'R_4s' => 'out',
        '4_1s' => 'warn',
        '10x'  => 'warn',
    ];

    /**
     * Evalúa una serie de puntos, del más viejo al más nuevo.
     *
     * Se devuelve un veredicto POR PUNTO y no uno solo para la serie porque la
     * carta se dibuja punto a punto: cada uno tiene que saber de qué color
     * pintarse y qué leyenda mostrar.
     *
     * @param  array<int,float|null> $values Valores en orden cronológico. Un
     *         null es un punto sin dato: no se evalúa y CORTA las rachas, para
     *         no encadenar puntos que en realidad no son consecutivos.
     * @param  float $center Línea central (la media del patrón).
     * @param  float $sd     Desvío estándar. Si es cero o negativo no hay carta
     *                       posible y se devuelve todo en 'ok' sin z.
     * @return array<int,array{z:?float,flag:string,rule:?string}>
     */
    public function evaluate(array $values, float $center, float $sd): array
    {
        $values = array_values($values);
        $n = count($values);

        if ($sd <= 0.0) {
            return array_fill(0, $n, ['z' => null, 'flag' => 'ok', 'rule' => null]);
        }

        // z = cuántos desvíos separan al punto de la línea central.
        $z = [];
        foreach ($values as $v) {
            $z[] = ($v === null || ! is_finite($v)) ? null : ($v - $center) / $sd;
        }

        $out = [];

        for ($i = 0; $i < $n; $i++) {
            if ($z[$i] === null) {
                $out[] = ['z' => null, 'flag' => 'ok', 'rule' => null];
                continue;
            }

            $rule = $this->ruleFor($z, $i);

            $out[] = [
                'z'    => round($z[$i], 6),
                'flag' => $rule === null ? 'ok' : self::RULES[$rule],
                'rule' => $rule,
            ];
        }

        return $out;
    }

    /**
     * La primera regla violada en el punto $i, mirando hacia atrás.
     *
     * El orden de evaluación importa y es el del procedimiento: se busca
     * primero la falla más grave, porque un punto que viola 1_3s también suele
     * violar 4_1s y lo que hay que reportar es el rechazo, no el aviso.
     *
     * @param array<int,?float> $z
     */
    private function ruleFor(array $z, int $i): ?string
    {
        $zi = $z[$i];

        if (abs($zi) > 3.0) {
            return '1_3s';
        }

        // 2_2s — este punto y el anterior, ambos fuera de 2 desvíos y del
        // mismo lado. El "mismo lado" es lo que la distingue de R_4s.
        $prev = $z[$i - 1] ?? null;
        if ($prev !== null && abs($zi) > 2.0 && abs($prev) > 2.0 && $this->sameSide($zi, $prev)) {
            return '2_2s';
        }

        // R_4s — este punto y el anterior separados por más de 4 desvíos, en
        // lados opuestos. No mide sesgo sino dispersión: la corrida se volvió
        // imprecisa aunque la media siga bien.
        if ($prev !== null && ! $this->sameSide($zi, $prev) && abs($zi - $prev) > 4.0) {
            return 'R_4s';
        }

        if ($this->runOfSameSide($z, $i, 4, 1.0)) {
            return '4_1s';
        }

        if ($this->runOfSameSide($z, $i, 10, 0.0)) {
            return '10x';
        }

        return null;
    }

    /**
     * ¿Los últimos $length puntos que terminan en $i están todos del mismo lado
     * y todos más allá de $threshold desvíos?
     *
     * Un hueco (valor nulo) corta la racha en vez de saltearse: dos puntos
     * separados por una corrida que no se hizo no son consecutivos, y tratarlos
     * como si lo fueran inventa un sesgo que no está.
     *
     * @param array<int,?float> $z
     */
    private function runOfSameSide(array $z, int $i, int $length, float $threshold): bool
    {
        if ($i + 1 < $length) {
            return false;
        }

        $positive = $z[$i] > 0;

        for ($k = $i; $k > $i - $length; $k--) {
            $v = $z[$k] ?? null;
            if ($v === null) {
                return false;
            }
            if (($v > 0) !== $positive) {
                return false;
            }
            if (abs($v) <= $threshold) {
                return false;
            }
        }

        return true;
    }

    /**
     * El cero se considera del lado positivo por convención, para que la
     * comparación sea total. Un punto exactamente en la línea central igual
     * nunca dispara nada, porque no supera ningún umbral.
     */
    private function sameSide(float $a, float $b): bool
    {
        return ($a > 0) === ($b > 0);
    }
}
