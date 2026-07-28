<?php

namespace App\Services\Lab;

/**
 * Evalúa una fórmula de hoja de trabajo contra los valores cargados.
 *
 * CONTRATO: nunca lanza por datos. Un ensayo a medio cargar es el estado normal
 * de la pantalla del analista —escribe una columna y el resto todavía está
 * vacío—, así que "falta un dato" se responde con null, no con una excepción.
 * Lo mismo con la división por cero y con los dominios inválidos (log10 de 0,
 * raíz de un negativo): son mediciones que aún no existen, no fallas del
 * programa. Los errores de ESCRITURA de la fórmula tampoco se propagan acá:
 * quien tiene que verlos es el editor de plantillas, vía FormulaValidator.
 */
class FormulaEvaluator
{
    /**
     * @param  string                    $formula Expresión sobre códigos de campo.
     * @param  array<string,mixed>       $context Mapa código => valor (numérico, string numérico o null).
     */
    public function evaluate(string $formula, array $context): ?float
    {
        try {
            $rpn = FormulaParser::parse($formula);
        } catch (FormulaException) {
            // Fórmula rota o texto no aritmético: para la hoja de trabajo el
            // campo simplemente no tiene valor.
            return null;
        }

        return $this->evaluateCompiled($rpn, $context);
    }

    /**
     * Igual que evaluate(), pero sobre una fórmula ya analizada. El resolver la
     * usa para no re-analizar el mismo texto en cada fila de la hoja.
     *
     * @param  array<int,array<string,mixed>> $rpn
     * @param  array<string,mixed>            $context
     */
    public function evaluateCompiled(array $rpn, array $context): ?float
    {
        $stack = [];

        foreach ($rpn as $node) {
            switch ($node['t']) {
                case 'num':
                    $stack[] = (float) $node['v'];
                    break;

                case 'var':
                    $stack[] = self::toNumber($context[$node['v']] ?? null);
                    break;

                case 'op':
                    if ($node['v'] === 'u-' || $node['v'] === 'u+') {
                        if ($stack === []) {
                            return null;
                        }
                        $a = array_pop($stack);
                        $stack[] = $a === null ? null : ($node['v'] === 'u-' ? -$a : $a);
                        break;
                    }
                    if (count($stack) < 2) {
                        return null;
                    }
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = self::binary($node['v'], $a, $b);
                    break;

                case 'fn':
                    $argc = (int) $node['n'];
                    if (count($stack) < $argc) {
                        return null;
                    }
                    $args = $argc === 0 ? [] : array_splice($stack, -$argc);
                    $stack[] = self::call($node['v'], $args);
                    break;
            }
        }

        if (count($stack) !== 1) {
            return null;
        }

        return self::finite(array_pop($stack));
    }

    /**
     * Convierte un valor cargado a float. Todo lo que no sea un número legible
     * —null, cadena vacía, texto, booleano, arreglo— es "sin dato".
     *
     * El booleano se descarta a propósito: en la base vieja los "0" de campos
     * no medidos ya causaron que un ensayo sin datos puntuara como si tuviera
     * un resultado real. Acá un valor que no es una medición no entra al cálculo.
     */
    public static function toNumber(mixed $value): ?float
    {
        if ($value === null || is_bool($value) || is_array($value) || is_object($value)) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }
        if (is_string($value)) {
            $v = trim($value);
            if ($v === '' || ! is_numeric($v)) {
                return null;
            }

            return is_finite((float) $v) ? (float) $v : null;
        }

        return null;
    }

    /**
     * Cualquier operando faltante contamina el resultado entero. Es deliberado:
     * preferimos "sin resultado" antes que un total calculado sobre la mitad de
     * las mediciones, que en un informe de ensayo se leería como un dato bueno.
     */
    private static function binary(string $op, ?float $a, ?float $b): ?float
    {
        if ($a === null || $b === null) {
            return null;
        }

        return self::finite(match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
            // La división por cero no es una excepción: es un denominador que
            // todavía no se cargó (peso de muestra en 0, por ejemplo).
            '/' => $b == 0.0 ? null : $a / $b,
            default => null,
        });
    }

    /**
     * @param array<int,?float> $args
     */
    private static function call(string $fn, array $args): ?float
    {
        foreach ($args as $arg) {
            if ($arg === null) {
                return null;
            }
        }

        $result = match ($fn) {
            'abs'   => abs($args[0]),
            'sqrt'  => $args[0] < 0 ? null : sqrt($args[0]),
            // Fuera del dominio no hay resultado: en Chendong, fal en 0 ppb
            // significa "no se detectó furano", no un grado de polimerización.
            'log10' => $args[0] <= 0 ? null : log10($args[0]),
            'ln'    => $args[0] <= 0 ? null : log($args[0]),
            'exp'   => exp($args[0]),
            // base 0 con exponente negativo es una división por cero encubierta
            // (PHP la marca como obsoleta y devuelve INF): sin resultado.
            'pow'   => ($args[0] == 0.0 && $args[1] < 0) ? null : pow($args[0], $args[1]),
            'round' => round($args[0], isset($args[1]) ? max(-10, min(10, (int) $args[1])) : 0),
            'min'   => min($args),
            'max'   => max($args),
            'sum'   => array_sum($args),
            'avg'   => array_sum($args) / count($args),
            default => null,
        };

        return self::finite($result === null ? null : (float) $result);
    }

    /**
     * INF y NAN no se dejan salir del motor: un exp() desbordado o un pow()
     * imposible se leería como número en el informe.
     */
    private static function finite(?float $value): ?float
    {
        return ($value === null || ! is_finite($value)) ? null : $value;
    }
}
