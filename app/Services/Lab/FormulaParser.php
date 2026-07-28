<?php

namespace App\Services\Lab;

/**
 * Analizador de fórmulas del laboratorio: texto -> notación polaca inversa (RPN).
 *
 * POR QUÉ UN PARSER PROPIO Y NO eval()
 * El sistema Rails viejo guardaba JavaScript crudo en la columna
 * `blur_calculation` y lo inyectaba en la página. Eso significaba tres cosas:
 * el servidor no podía recalcular ni validar nada, el cálculo dependía de los
 * id del DOM (reordenar una columna lo rompía en silencio), y una fórmula —que
 * la edita un usuario y vive en la base— era ejecución de código arbitrario.
 *
 * Acá la fórmula es un DATO que se analiza carácter por carácter. Lo único que
 * puede aparecer es: números, códigos de campo, las cuatro operaciones,
 * paréntesis, comas y una lista cerrada de funciones. Cualquier otro carácter
 * (comillas, punto y coma, $, backtick, corchete) corta el análisis con
 * FormulaException. No hay ninguna ruta por la que un texto de la base llegue a
 * ejecutarse: el resultado del análisis es un arreglo de tokens que solo el
 * evaluador sabe recorrer.
 *
 * El algoritmo es shunting-yard (Dijkstra) porque produce RPN reutilizable: se
 * analiza una vez y se evalúa N veces (el resolver evalúa el mismo campo para
 * cada fila de una hoja de trabajo).
 */
class FormulaParser
{
    /**
     * Topes contra entradas patológicas. Una fórmula de laboratorio real ronda
     * los 60 caracteres; estos números son holgados a propósito, están para que
     * un texto absurdo (o generado) no consuma memoria ni pila.
     */
    public const MAX_LENGTH = 1000;
    public const MAX_TOKENS = 400;
    public const MAX_DEPTH = 24;
    public const MAX_IDENTIFIER = 64;

    /**
     * Lista CERRADA de funciones permitidas: nombre => [mínimo, máximo] de
     * argumentos (null = sin tope). Agregar una función es agregar una fila
     * acá y su caso en el evaluador; no hay resolución dinámica de nombres,
     * justamente para que no exista forma de llamar a una función de PHP.
     */
    public const FUNCTIONS = [
        'abs'   => [1, 1],
        'round' => [1, 2],
        'min'   => [1, null],
        'max'   => [1, null],
        'sqrt'  => [1, 1],
        'log10' => [1, 1],
        'ln'    => [1, 1],
        'exp'   => [1, 1],
        'pow'   => [2, 2],
        'avg'   => [1, null],
        'sum'   => [1, null],
    ];

    /** Precedencia. Los unarios van por encima de todo lo binario. */
    private const PRECEDENCE = ['+' => 1, '-' => 1, '*' => 2, '/' => 2, 'u-' => 3, 'u+' => 3];

    /**
     * Divide el texto en tokens. Es la única capa que mira caracteres sueltos,
     * y por eso es la que rechaza todo lo que no sea aritmética.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function tokenize(string $formula): array
    {
        if (mb_strlen($formula) > self::MAX_LENGTH) {
            throw new FormulaException(
                'La fórmula supera el largo máximo de '.self::MAX_LENGTH.' caracteres.',
                'too_long'
            );
        }

        $tokens = [];
        $len = strlen($formula);

        for ($i = 0; $i < $len; $i++) {
            $ch = $formula[$i];

            if ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r") {
                continue;
            }

            // Número: 12 · 1.51 · 3.4e-2. Se exige dígito inicial (".5" se
            // rechaza) para que un punto suelto nunca pase por válido.
            if ($ch >= '0' && $ch <= '9') {
                $start = $i;
                while ($i < $len && $formula[$i] >= '0' && $formula[$i] <= '9') {
                    $i++;
                }
                if ($i < $len && $formula[$i] === '.') {
                    $i++;
                    if ($i >= $len || $formula[$i] < '0' || $formula[$i] > '9') {
                        throw new FormulaException('Número mal escrito en la posición '.$start.'.', 'syntax');
                    }
                    while ($i < $len && $formula[$i] >= '0' && $formula[$i] <= '9') {
                        $i++;
                    }
                }
                if ($i < $len && ($formula[$i] === 'e' || $formula[$i] === 'E')) {
                    $save = $i;
                    $i++;
                    if ($i < $len && ($formula[$i] === '+' || $formula[$i] === '-')) {
                        $i++;
                    }
                    if ($i < $len && $formula[$i] >= '0' && $formula[$i] <= '9') {
                        while ($i < $len && $formula[$i] >= '0' && $formula[$i] <= '9') {
                            $i++;
                        }
                    } else {
                        // "2e" no es exponente: se devuelve la 'e' al flujo y
                        // fallará más adelante como identificador pegado.
                        $i = $save;
                    }
                }
                $tokens[] = ['k' => 'num', 'v' => (float) substr($formula, $start, $i - $start), 'pos' => $start];
                $i--;
                continue;
            }

            // Identificador: código de campo o nombre de función.
            if ($ch === '_' || ($ch >= 'a' && $ch <= 'z') || ($ch >= 'A' && $ch <= 'Z')) {
                $start = $i;
                while ($i < $len && (
                    $formula[$i] === '_'
                    || ($formula[$i] >= 'a' && $formula[$i] <= 'z')
                    || ($formula[$i] >= 'A' && $formula[$i] <= 'Z')
                    || ($formula[$i] >= '0' && $formula[$i] <= '9')
                )) {
                    $i++;
                }
                $name = substr($formula, $start, $i - $start);
                if (strlen($name) > self::MAX_IDENTIFIER) {
                    throw new FormulaException(
                        'El nombre "'.substr($name, 0, 20).'…" supera los '.self::MAX_IDENTIFIER.' caracteres.',
                        'identifier_too_long'
                    );
                }
                // Es llamada a función solo si le sigue un paréntesis.
                $j = $i;
                while ($j < $len && ($formula[$j] === ' ' || $formula[$j] === "\t")) {
                    $j++;
                }
                $isCall = $j < $len && $formula[$j] === '(';
                $tokens[] = ['k' => 'id', 'v' => $name, 'call' => $isCall, 'pos' => $start];
                $i--;
                continue;
            }

            $simple = match ($ch) {
                '+', '-', '*', '/' => ['k' => 'op', 'v' => $ch, 'pos' => $i],
                '('               => ['k' => 'lp', 'pos' => $i],
                ')'               => ['k' => 'rp', 'pos' => $i],
                ','               => ['k' => 'comma', 'pos' => $i],
                default           => null,
            };

            if ($simple === null) {
                throw new FormulaException(
                    'Carácter no permitido "'.$ch.'" en la posición '.$i.'.',
                    'invalid_char'
                );
            }

            $tokens[] = $simple;

            if (count($tokens) > self::MAX_TOKENS) {
                throw new FormulaException('La fórmula tiene demasiados elementos.', 'too_long');
            }
        }

        if (count($tokens) > self::MAX_TOKENS) {
            throw new FormulaException('La fórmula tiene demasiados elementos.', 'too_long');
        }

        return $tokens;
    }

    /**
     * Devuelve la fórmula en RPN, lista para evaluar.
     *
     * Cada elemento es uno de:
     *   ['t'=>'num','v'=>float] · ['t'=>'var','v'=>código]
     *   ['t'=>'op','v'=>'+'|'-'|'*'|'/'|'u-'|'u+'] · ['t'=>'fn','v'=>nombre,'n'=>argc]
     *
     * @return array<int,array<string,mixed>>
     */
    public static function parse(string $formula): array
    {
        $tokens = self::tokenize($formula);

        if ($tokens === []) {
            throw new FormulaException('La fórmula está vacía.', 'empty');
        }

        $out = [];
        $ops = [];
        $depth = 0;
        // Estado del shunting-yard: distingue el menos binario (a-b) del unario
        // (-a), que es la única ambigüedad real de esta gramática.
        $expectOperand = true;
        $n = count($tokens);

        for ($i = 0; $i < $n; $i++) {
            $t = $tokens[$i];

            switch ($t['k']) {
                case 'num':
                    if (! $expectOperand) {
                        throw new FormulaException('Falta un operador antes del número en la posición '.$t['pos'].'.', 'syntax');
                    }
                    $out[] = ['t' => 'num', 'v' => $t['v']];
                    $expectOperand = false;
                    break;

                case 'id':
                    if (! $expectOperand) {
                        throw new FormulaException('Falta un operador antes de "'.$t['v'].'".', 'syntax');
                    }
                    if ($t['call']) {
                        $name = strtolower($t['v']);
                        if (! isset(self::FUNCTIONS[$name])) {
                            throw new FormulaException('Función desconocida: "'.$t['v'].'".', 'unknown_function');
                        }
                        $i++; // consume el '(' que el lexer ya confirmó
                        if (++$depth > self::MAX_DEPTH) {
                            throw new FormulaException('La fórmula anida más de '.self::MAX_DEPTH.' niveles.', 'too_deep');
                        }
                        // argc arranca en 1 salvo llamada vacía; cada coma suma.
                        $argc = (isset($tokens[$i + 1]) && $tokens[$i + 1]['k'] === 'rp') ? 0 : 1;
                        $ops[] = ['k' => 'lp', 'fn' => $name, 'argc' => $argc, 'pos' => $t['pos']];
                        $expectOperand = $argc > 0;
                    } else {
                        $out[] = ['t' => 'var', 'v' => $t['v']];
                        $expectOperand = false;
                    }
                    break;

                case 'op':
                    if ($expectOperand) {
                        if ($t['v'] === '-' || $t['v'] === '+') {
                            // Unario: es el de mayor precedencia y asocia a la
                            // derecha, así que se apila sin desalojar nada.
                            $ops[] = ['k' => 'op', 'v' => 'u'.$t['v']];
                        } else {
                            throw new FormulaException('El operador "'.$t['v'].'" no tiene un valor a la izquierda.', 'syntax');
                        }
                    } else {
                        $p = self::PRECEDENCE[$t['v']];
                        while ($ops !== []) {
                            $top = $ops[count($ops) - 1];
                            if ($top['k'] !== 'op' || self::PRECEDENCE[$top['v']] < $p) {
                                break;
                            }
                            $out[] = ['t' => 'op', 'v' => array_pop($ops)['v']];
                        }
                        $ops[] = ['k' => 'op', 'v' => $t['v']];
                        $expectOperand = true;
                    }
                    break;

                case 'lp':
                    if (! $expectOperand) {
                        throw new FormulaException('Falta un operador antes del paréntesis en la posición '.$t['pos'].'.', 'syntax');
                    }
                    if (++$depth > self::MAX_DEPTH) {
                        throw new FormulaException('La fórmula anida más de '.self::MAX_DEPTH.' niveles.', 'too_deep');
                    }
                    $ops[] = ['k' => 'lp', 'fn' => null, 'argc' => 0, 'pos' => $t['pos']];
                    $expectOperand = true;
                    break;

                case 'rp':
                    if ($expectOperand) {
                        throw new FormulaException('Paréntesis cerrado sin contenido en la posición '.$t['pos'].'.', 'syntax');
                    }
                    while ($ops !== [] && $ops[count($ops) - 1]['k'] !== 'lp') {
                        $out[] = ['t' => 'op', 'v' => array_pop($ops)['v']];
                    }
                    if ($ops === []) {
                        throw new FormulaException('Paréntesis desbalanceados: sobra un ")".', 'unbalanced');
                    }
                    $lp = array_pop($ops);
                    $depth--;
                    if ($lp['fn'] !== null) {
                        self::assertArity($lp['fn'], $lp['argc']);
                        $out[] = ['t' => 'fn', 'v' => $lp['fn'], 'n' => $lp['argc']];
                    }
                    $expectOperand = false;
                    break;

                case 'comma':
                    if ($expectOperand) {
                        throw new FormulaException('Argumento vacío en la posición '.$t['pos'].'.', 'syntax');
                    }
                    while ($ops !== [] && $ops[count($ops) - 1]['k'] !== 'lp') {
                        $out[] = ['t' => 'op', 'v' => array_pop($ops)['v']];
                    }
                    if ($ops === [] || $ops[count($ops) - 1]['fn'] === null) {
                        throw new FormulaException('La coma de la posición '.$t['pos'].' no está dentro de una función.', 'syntax');
                    }
                    $ops[count($ops) - 1]['argc']++;
                    $expectOperand = true;
                    break;
            }
        }

        if ($expectOperand) {
            throw new FormulaException('La fórmula termina de forma incompleta.', 'syntax');
        }

        while ($ops !== []) {
            $o = array_pop($ops);
            if ($o['k'] === 'lp') {
                throw new FormulaException('Paréntesis desbalanceados: falta cerrar un "(".', 'unbalanced');
            }
            $out[] = ['t' => 'op', 'v' => $o['v']];
        }

        return $out;
    }

    /**
     * Códigos de campo referenciados, sin repetir y en orden de aparición.
     *
     * @param  array<int,array<string,mixed>> $rpn
     * @return array<int,string>
     */
    public static function variables(array $rpn): array
    {
        $seen = [];
        foreach ($rpn as $node) {
            if ($node['t'] === 'var') {
                $seen[$node['v']] = true;
            }
        }

        return array_keys($seen);
    }

    private static function assertArity(string $fn, int $argc): void
    {
        [$min, $max] = self::FUNCTIONS[$fn];

        if ($argc < $min) {
            throw new FormulaException(
                'La función "'.$fn.'" necesita al menos '.$min.' argumento(s) y recibió '.$argc.'.',
                'arity'
            );
        }
        if ($max !== null && $argc > $max) {
            throw new FormulaException(
                'La función "'.$fn.'" admite hasta '.$max.' argumento(s) y recibió '.$argc.'.',
                'arity'
            );
        }
    }
}
