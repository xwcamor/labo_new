<?php

namespace App\Services\Lab;

/**
 * Resuelve una hoja de trabajo completa: toma los campos de la prueba con sus
 * fórmulas y los valores que cargó el analista, y devuelve el mapa con los
 * calculados ya resueltos.
 *
 * POR QUÉ ORDEN TOPOLÓGICO Y NO "de arriba hacia abajo"
 * El sistema viejo calculaba en el orden en que estaban las columnas en la
 * pantalla, porque el cálculo lo disparaba el blur de cada input. Un campo
 * calculado que dependía de otro calculado a su derecha salía vacío o con el
 * valor de la corrida anterior, y reordenar columnas cambiaba el resultado.
 * Acá el orden lo dicta el grafo de dependencias, no la posición: el mismo
 * conjunto de campos da el mismo resultado sin importar cómo se los ordene en
 * la hoja.
 */
class FormulaResolver
{
    public function __construct(
        private readonly FormulaEvaluator $evaluator = new FormulaEvaluator(),
        private readonly FormulaValidator $validator = new FormulaValidator(),
    ) {
    }

    /**
     * @param  array<int|string,mixed> $fields    Campos de la prueba: arreglos o
     *         modelos con 'code', 'formula' y opcionalmente 'decimals'.
     * @param  array<string,mixed>     $rawValues Mapa código => valor cargado.
     * @return array<string,?float>    Mapa completo, con los calculados resueltos.
     */
    public function resolveAll(array $fields, array $rawValues): array
    {
        return $this->resolveWithDiagnostics($fields, $rawValues)['values'];
    }

    /**
     * Igual que resolveAll(), pero además informa qué no se pudo resolver. Lo
     * usa el editor de plantillas y el guardado del ensayo, que sí quieren
     * saber si quedó un ciclo o una fórmula rota.
     *
     * @param  array<int|string,mixed> $fields
     * @param  array<string,mixed>     $rawValues
     * @return array{values:array<string,?float>,cycles:array<int,array<int,string>>,unresolved:array<int,string>,errors:array<string,array<int,string>>}
     */
    public function resolveWithDiagnostics(array $fields, array $rawValues): array
    {
        $computed = [];   // código => ['formula' => string, 'rpn' => array|null, 'decimals' => ?int]
        $order = [];      // códigos en el orden en que llegaron, para desempatar

        foreach ($fields as $key => $field) {
            // Se acepta tanto la lista de campos como el atajo código => fórmula,
            // que es lo que tiene a mano quien prueba una plantilla suelta.
            $isShortcut = is_string($key) && (is_string($field) || $field === null);
            $code = $isShortcut ? $key : self::read($field, 'code');
            if (! is_string($code) || $code === '') {
                continue;
            }
            $order[] = $code;
            $formula = $isShortcut ? $field : self::read($field, 'formula');
            if (! is_string($formula) || trim($formula) === '') {
                continue;
            }
            $decimals = $isShortcut ? null : self::read($field, 'decimals');
            $computed[$code] = [
                'formula' => trim($formula),
                'decimals' => is_numeric($decimals) ? (int) $decimals : null,
                'rpn' => null,
                'error' => null,
            ];
        }

        // El contexto arranca con lo cargado a mano. Un campo calculado ignora
        // su valor crudo: es de solo lectura (is_locked) y su única fuente
        // legítima es la fórmula, no lo que haya quedado guardado antes.
        $values = [];
        foreach ($rawValues as $code => $value) {
            if (is_string($code)) {
                $values[$code] = FormulaEvaluator::toNumber($value);
            }
        }
        foreach ($order as $code) {
            if (! array_key_exists($code, $values)) {
                $values[$code] = null;
            }
        }

        $errors = [];
        foreach ($computed as $code => $meta) {
            try {
                $computed[$code]['rpn'] = FormulaParser::parse($meta['formula']);
            } catch (FormulaException $e) {
                $computed[$code]['rpn'] = null;
                $errors[$code] = [$e->getMessage()];
                $values[$code] = null;
            }
        }

        $cycles = $this->validator->detectCycles(
            array_map(static fn (array $m): string => $m['formula'], $computed)
        );

        $inCycle = [];
        foreach ($cycles as $cycle) {
            foreach ($cycle as $node) {
                $inCycle[$node] = true;
            }
        }

        $unresolved = [];
        foreach ($this->topologicalOrder($computed, $inCycle) as $code) {
            $meta = $computed[$code];
            if ($meta['rpn'] === null) {
                $unresolved[] = $code;
                continue;
            }
            $result = $this->evaluator->evaluateCompiled($meta['rpn'], $values);
            // El redondeo declarado del campo se aplica ACÁ y no solo al
            // mostrar: el número que se imprime en el certificado es el que
            // usan los campos que dependen de este, así que la hoja y el
            // informe no pueden diferir en el último decimal.
            if ($result !== null && $meta['decimals'] !== null) {
                $result = round($result, max(0, min(10, $meta['decimals'])));
            }
            $values[$code] = $result;
        }

        foreach (array_keys($computed) as $code) {
            if (isset($inCycle[$code])) {
                $values[$code] = null;
                $unresolved[] = $code;
            }
        }

        return [
            'values' => $values,
            'cycles' => $cycles,
            'unresolved' => array_values(array_unique($unresolved)),
            'errors' => $errors,
        ];
    }

    /**
     * Kahn: primero los calculados que no dependen de ningún otro calculado.
     * Los que quedan dentro de un ciclo se excluyen de entrada — evaluarlos
     * daría un resultado que depende del orden de recorrido, que es
     * exactamente el problema que este resolver existe para eliminar.
     *
     * @param  array<string,array<string,mixed>> $computed
     * @param  array<string,true>                $inCycle
     * @return array<int,string>
     */
    private function topologicalOrder(array $computed, array $inCycle): array
    {
        $deps = [];
        foreach ($computed as $code => $meta) {
            if (isset($inCycle[$code])) {
                continue;
            }
            $deps[$code] = [];
            if ($meta['rpn'] === null) {
                continue;
            }
            foreach (FormulaParser::variables($meta['rpn']) as $var) {
                if ($var !== $code && isset($computed[$var]) && ! isset($inCycle[$var])) {
                    $deps[$code][$var] = true;
                }
            }
        }

        $ordered = [];
        $pending = $deps;

        while ($pending !== []) {
            $ready = [];
            foreach ($pending as $code => $needs) {
                if ($needs === []) {
                    $ready[] = $code;
                }
            }
            if ($ready === []) {
                // No debería ocurrir: los ciclos ya se filtraron. Se corta sin
                // lanzar para no romper la hoja del analista por un caso raro.
                foreach (array_keys($pending) as $code) {
                    $ordered[] = $code;
                }
                break;
            }
            foreach ($ready as $code) {
                $ordered[] = $code;
                unset($pending[$code]);
            }
            foreach ($pending as $code => $needs) {
                foreach ($ready as $done) {
                    unset($pending[$code][$done]);
                }
            }
        }

        return $ordered;
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
}
