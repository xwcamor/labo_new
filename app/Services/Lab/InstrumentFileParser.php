<?php

namespace App\Services\Lab;

/**
 * Lee el archivo crudo de un instrumento y devuelve los valores por código de
 * campo, según el formato declarado en `instrument_formats.column_map`.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ HACÍA EL SISTEMA VIEJO                                               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El parser entero del sistema Rails eran siete líneas dentro de una vista:
 *
 *     @data = @main_model.description.match(/#{word_searched}*(.*)$/)
 *     @value_saved = @data[1].strip.delete! @word_removed
 *
 * De ahí salían cinco problemas, todos reales:
 *
 * 1. La etiqueta buscada se interpolaba CRUDA dentro de una expresión regular.
 *    Un paréntesis o un punto en la configuración cambiaba el significado o la
 *    hacía explotar. Y el `*` colgado al final cuantificaba la última letra de
 *    la etiqueta, así que buscar "Valor medio" también encontraba "Valor medi".
 * 2. `delete!` borra CARACTERES SUELTOS, no la cadena. Quitar "kV" borraba
 *    todas las k y todas las V del valor. Y devuelve nulo si no borró nada,
 *    con lo que el `.strip` siguiente lanzaba una excepción cada vez que la
 *    unidad configurada no aparecía en ese archivo en particular.
 * 3. Solo tomaba la PRIMERA coincidencia. Los archivos del DTL C traen
 *    "Llenado 1 / Llenado 2 / Llenado 3" con las mismas etiquetas repetidas, y
 *    los dos últimos se perdían.
 * 4. El archivo entero se guardaba dentro de una columna de texto de la base y
 *    todo el análisis trabajaba sobre esa cadena.
 * 5. Los furanos no entraban en ese molde, así que había un parche: un UPDATE
 *    global sobre `lab_category_sub_detail_id IN (80,81,82,83,84)` que se
 *    quedaba con el primer token del valor, acompañado del comentario
 *    "DONT MOVE FURANOS COLUMN ORDER".
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ CÓMO SE DECLARA UN FORMATO                                               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `column_map` es un JSON editable desde la pantalla de formatos:
 *
 *   {
 *     "fields": [
 *       {"code": "rig_1",  "mode": "label",  "match": "Medición 1:"},
 *       {"code": "temp",   "mode": "label",  "match": "Temperatura:"},
 *       {"code": "h2",     "mode": "lookup", "match": "H2"},
 *       {"code": "fal",    "mode": "lookup", "match": "2-furaldehyde"}
 *     ]
 *   }
 *
 *   label    la etiqueta está al principio de la línea y el valor es el resto.
 *            Sirve para los protocolos del DPA 75C y del DTL C.
 *   lookup   el archivo es una tabla "nombre  valor" y se busca la fila cuyo
 *            primer campo sea el nombre. Sirve para cromatografía y furanos.
 *
 * Las coincidencias se buscan SIN acentos y sin distinguir mayúsculas, porque
 * los protocolos del instrumento vienen con acentos y el laboratorio no siempre
 * los escribe igual al configurar.
 *
 * La unidad NO se declara: se descarta sola al leer el número. Lo que en el
 * sistema viejo era una lista de caracteres a borrar acá no hace falta.
 */
class InstrumentFileParser
{
    /** Cuántas repeticiones de la misma etiqueta se conservan como máximo. */
    private const MAX_OCCURRENCES = 20;

    /**
     * @param  string $contents Texto del archivo, ya decodificado a UTF-8.
     * @param  array<string,mixed> $columnMap Formato declarado.
     * @return array{values:array<string,array<int,array{raw:string,number:?float,qualifier:?string}>>,unmatched:array<int,string>}
     *         Por cada código de campo, la lista de ocurrencias encontradas en
     *         el orden del archivo. Se devuelven TODAS y no solo la primera:
     *         un protocolo con tres llenados tiene tres valores legítimos, y
     *         quedarse con el primero es lo que hacía perder los otros dos.
     */
    public function parse(string $contents, array $columnMap): array
    {
        $lines = $this->lines($contents);
        $specs = is_array($columnMap['fields'] ?? null) ? $columnMap['fields'] : [];

        $values = [];
        $unmatched = [];

        foreach ($specs as $spec) {
            $code  = is_string($spec['code'] ?? null) ? $spec['code'] : null;
            $match = is_string($spec['match'] ?? null) ? trim($spec['match']) : '';
            $mode  = ($spec['mode'] ?? 'label') === 'lookup' ? 'lookup' : 'label';

            if ($code === null || $code === '' || $match === '') {
                continue;
            }

            $found = $mode === 'lookup'
                ? $this->findByLookup($lines, $match)
                : $this->findByLabel($lines, $match);

            if ($found === []) {
                $unmatched[] = $code;
                continue;
            }

            $values[$code] = $found;
        }

        return ['values' => $values, 'unmatched' => $unmatched];
    }

    /**
     * Normaliza el texto a líneas: quita la marca de orden de bytes, unifica
     * los saltos y convierte los tabuladores en espacios.
     *
     * La marca de orden importa de verdad: uno de los archivos de cromatografía
     * la trae, y en el sistema viejo entraba a la columna de texto y arruinaba
     * la coincidencia de la primera línea.
     *
     * @return array<int,string>
     */
    private function lines(string $contents): array
    {
        $contents = preg_replace('/^\x{FEFF}/u', '', $contents) ?? $contents;
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
        $contents = str_replace("\t", ' ', $contents);

        return explode("\n", $contents);
    }

    /**
     * Etiqueta al principio de la línea, valor en el resto.
     *
     * La comparación es literal (no hay expresión regular armada con el texto
     * del usuario), así que un paréntesis o un punto en la etiqueta no cambian
     * nada.
     *
     * @param  array<int,string> $lines
     * @return array<int,array{raw:string,number:?float,qualifier:?string}>
     */
    private function findByLabel(array $lines, string $label): array
    {
        $needle = $this->fold(rtrim($label, ": \t"));
        $out = [];

        foreach ($lines as $line) {
            $folded = $this->fold($line);
            if (! str_starts_with($folded, $needle)) {
                continue;
            }

            // Se corta por la longitud del texto plegado, que se mantiene
            // alineada con la original porque fold() no cambia la cantidad de
            // caracteres: solo baja mayúsculas y saca acentos.
            $rest = mb_substr($line, mb_strlen($needle));

            // Después de la etiqueta tiene que venir un separador, no otra
            // letra: si no, "Medición 1" también encontraría "Medición 10", y
            // el protocolo del DPA numera las mediciones.
            if ($rest !== '' && ! preg_match('/^[:\s]/u', $rest)) {
                continue;
            }

            $rest = ltrim($rest, ": \t");

            $out[] = $this->readValue($rest);

            if (count($out) >= self::MAX_OCCURRENCES) {
                break;
            }
        }

        return $out;
    }

    /**
     * Tabla de dos columnas: el nombre y su valor, separados por espacios.
     *
     * Se exige que el nombre ocupe el principio de la línea Y que lo que sigue
     * empiece por un número o un signo de censura. Sin esa segunda condición,
     * buscar "H2" en el reporte de cromatografía encontraría antes la línea de
     * encabezado o el nombre de otro gas que empiece igual.
     *
     * @param  array<int,string> $lines
     * @return array<int,array{raw:string,number:?float,qualifier:?string}>
     */
    private function findByLookup(array $lines, string $name): array
    {
        $needle = $this->fold($name);
        $out = [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            $folded = $this->fold($trimmed);

            if (! str_starts_with($folded, $needle)) {
                continue;
            }

            $rest = ltrim(mb_substr($trimmed, mb_strlen($needle)));

            // El resto tiene que empezar por el valor. Si empieza por otra
            // letra, lo que coincidió fue el prefijo de un nombre más largo:
            // "H2" contra "H2O", o "2-furaldehyde" contra
            // "5-methyl-2-furaldehyde" cuando el nombre viene recortado.
            if ($rest === '' || ! preg_match('/^[<>=\-+.\d]/', $rest)) {
                continue;
            }

            $out[] = $this->readValue($rest);

            if (count($out) >= self::MAX_OCCURRENCES) {
                break;
            }
        }

        return $out;
    }

    /**
     * Lee un valor con su posible censura y descarta la unidad.
     *
     * Los tres casos que traen los archivos reales:
     *   "39.1  kV"      número común
     *   ">75.0  kV"     el ensayador llegó a su tope sin que el aceite rompiera
     *   "0.000 BDL"     por debajo del límite de detección del método
     *
     * @return array{raw:string,number:?float,qualifier:?string}
     */
    private function readValue(string $raw): array
    {
        $raw = trim($raw);
        $qualifier = null;

        if (preg_match('/^(>=|<=|>|<)\s*/', $raw, $m)) {
            $qualifier = str_starts_with($m[1], '>') ? 'gt' : 'lt';
        }

        // La coma decimal existe en los protocolos en español.
        $normalized = preg_replace('/(\d),(\d)/', '$1.$2', $raw) ?? $raw;

        $number = null;
        if (preg_match('/-?\d+(?:\.\d+)?(?:[eE][-+]?\d+)?/', $normalized, $m)) {
            $number = (float) $m[0];
        }

        // "BDL" (below detection limit) acompaña un 0.000 que no es un cero
        // medido: es "no se detectó". Tratarlo como cero haría que un aceite
        // sin furanos y uno con furanos justo bajo el límite se vean iguales.
        if ($qualifier === null && preg_match('/\b(BDL|ND|N\.D\.)\b/i', $raw)) {
            $qualifier = 'lt';
        }

        return [
            'raw'       => $raw,
            'number'    => ($number !== null && is_finite($number)) ? $number : null,
            'qualifier' => $qualifier,
        ];
    }

    /**
     * Minúsculas y sin acentos, conservando la cantidad de caracteres para que
     * las posiciones del texto plegado y del original sigan coincidiendo.
     */
    private function fold(string $text): string
    {
        $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ];

        return strtr(mb_strtolower($text, 'UTF-8'), $map);
    }
}
