<?php

namespace App\Services\Lab;

/**
 * Valida fórmulas ANTES de guardarlas. Es la contraparte del evaluador: allá
 * un problema se responde con null y silencio, acá se responde con la lista de
 * errores, porque el destinatario es el editor de plantillas de ensayo y lo que
 * tiene que pasar es que no deje guardar.
 *
 * Cubre los cuatro modos de rotura que el sistema viejo dejaba pasar hasta que
 * alguien miraba un informe con la celda vacía: sintaxis inválida, paréntesis
 * desbalanceados, función inexistente y —el más silencioso— referencias a un
 * campo que esa prueba no tiene.
 */
class FormulaValidator
{
    /**
     * Si $availableCodes viene vacío no se verifican los códigos: significa que
     * el llamador no sabe qué campos tiene la prueba (validación suelta), no
     * que la prueba no tenga ninguno.
     *
     * @param  string             $formula
     * @param  array<int|string,string> $availableCodes Códigos de campo de la prueba.
     * @return array{ok:bool,errors:array<int,string>,uses:array<int,string>}
     */
    public function validate(string $formula, array $availableCodes = []): array
    {
        $errors = [];
        $uses = [];

        if (trim($formula) === '') {
            return ['ok' => false, 'errors' => ['La fórmula está vacía.'], 'uses' => []];
        }

        // Primero se tokeniza aparte del análisis sintáctico para poder listar
        // TODAS las funciones y campos desconocidos de una sola pasada: al
        // usuario le sirve más ver los tres errores juntos que uno por guardado.
        try {
            $tokens = FormulaParser::tokenize($formula);
        } catch (FormulaException $e) {
            return ['ok' => false, 'errors' => [$e->getMessage()], 'uses' => []];
        }

        $known = self::codeSet($availableCodes);

        foreach ($tokens as $token) {
            if ($token['k'] !== 'id') {
                continue;
            }
            if ($token['call']) {
                if (! isset(FormulaParser::FUNCTIONS[strtolower($token['v'])])) {
                    $errors[] = 'Función desconocida: "'.$token['v'].'".';
                }
                continue;
            }
            if (! in_array($token['v'], $uses, true)) {
                $uses[] = $token['v'];
            }
            if ($known !== [] && ! isset($known[$token['v']])) {
                $errors[] = 'El campo "'.$token['v'].'" no existe en esta prueba.';
            }
        }

        $errors = array_values(array_unique($errors));

        try {
            FormulaParser::parse($formula);
        } catch (FormulaException $e) {
            // La función desconocida ya se reportó arriba con todas sus
            // hermanas; no se duplica el mismo mensaje.
            if ($e->reason !== 'unknown_function') {
                $errors[] = $e->getMessage();
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'uses' => $uses,
        ];
    }

    /**
     * Detecta dependencias circulares entre campos calculados.
     *
     * Sin esto, un campo A cuya fórmula usa B y un B que usa A dejan al
     * resolver sin punto de partida. Se detecta al GUARDAR, no al calcular,
     * porque el ciclo es un error de diseño de la plantilla.
     *
     * @param  array<int|string,mixed> $fieldsWithFormulas Mapa código => fórmula,
     *         o lista de ['code' => …, 'formula' => …].
     * @return array<int,array<int,string>> Cada ciclo como camino cerrado,
     *         por ejemplo ['a', 'b', 'a'].
     */
    public function detectCycles(array $fieldsWithFormulas): array
    {
        $formulas = self::normalizeFormulas($fieldsWithFormulas);

        // Aristas solo hacia campos que TAMBIÉN son calculados: un campo que el
        // analista escribe a mano nunca puede cerrar un ciclo.
        $edges = [];
        foreach ($formulas as $code => $formula) {
            $edges[$code] = [];
            try {
                $vars = FormulaParser::variables(FormulaParser::parse($formula));
            } catch (FormulaException) {
                // Fórmula rota: su error lo reporta validate(), acá solo
                // interesa que no aporte aristas.
                continue;
            }
            foreach ($vars as $var) {
                if (isset($formulas[$var]) && ! in_array($var, $edges[$code], true)) {
                    $edges[$code][] = $var;
                }
            }
        }

        $cycles = [];
        $seenKeys = [];
        $state = [];   // 0 sin visitar · 1 en la rama actual · 2 terminado
        $path = [];

        $visit = function (string $node) use (&$visit, &$edges, &$state, &$path, &$cycles, &$seenKeys): void {
            $state[$node] = 1;
            $path[] = $node;

            foreach ($edges[$node] ?? [] as $next) {
                if (($state[$next] ?? 0) === 1) {
                    $start = array_search($next, $path, true);
                    $cycle = array_slice($path, $start);
                    $cycle[] = $next;                    // se cierra el camino
                    $key = self::cycleKey($cycle);
                    if (! isset($seenKeys[$key])) {
                        $seenKeys[$key] = true;
                        $cycles[] = $cycle;
                    }
                } elseif (($state[$next] ?? 0) === 0) {
                    $visit($next);
                }
            }

            array_pop($path);
            $state[$node] = 2;
        };

        foreach (array_keys($edges) as $node) {
            if (($state[$node] ?? 0) === 0) {
                $visit($node);
            }
        }

        return $cycles;
    }

    /**
     * Acepta tanto un mapa código => fórmula como una lista de campos (arreglos
     * o modelos), para que el llamador no tenga que preparar la entrada.
     *
     * @param  array<int|string,mixed> $fields
     * @return array<string,string>
     */
    private static function normalizeFormulas(array $fields): array
    {
        $out = [];

        foreach ($fields as $key => $value) {
            if (is_string($key) && (is_string($value) || $value === null)) {
                $formula = trim((string) $value);
                if ($formula !== '') {
                    $out[$key] = $formula;
                }
                continue;
            }

            $code = self::read($value, 'code');
            $formula = self::read($value, 'formula');
            if (is_string($code) && $code !== '' && is_string($formula) && trim($formula) !== '') {
                $out[$code] = trim($formula);
            }
        }

        return $out;
    }

    private static function read(mixed $field, string $key): mixed
    {
        if (is_array($field)) {
            return $field[$key] ?? null;
        }
        if (is_object($field)) {
            return $field->{$key} ?? null;
        }

        return null;
    }

    /**
     * Clave invariante a la rotación: a->b->a y b->a->b son el mismo ciclo y no
     * hay que reportarlo dos veces.
     *
     * @param array<int,string> $cycle
     */
    private static function cycleKey(array $cycle): string
    {
        $nodes = array_slice($cycle, 0, -1);
        $min = array_search(min($nodes), $nodes, true);
        $rotated = array_merge(array_slice($nodes, $min), array_slice($nodes, 0, $min));

        return implode('>', $rotated);
    }

    /**
     * @param  array<int|string,string> $codes
     * @return array<string,true>
     */
    private static function codeSet(array $codes): array
    {
        $set = [];
        $source = array_is_list($codes) ? $codes : array_keys($codes);

        foreach ($source as $code) {
            if (is_string($code) && $code !== '') {
                $set[$code] = true;
            }
        }

        return $set;
    }
}
