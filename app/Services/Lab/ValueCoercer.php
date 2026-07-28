<?php

namespace App\Services\Lab;

use App\Models\TestField;

/**
 * Traduce lo que el analista TIPEA a lo que se guarda y a lo que ve el motor.
 *
 * Existe porque esa traducción tiene que ocurrir en un solo lugar. Hay dos
 * caminos que la necesitan y no pueden dar resultados distintos:
 *
 *   · el GUARDADO (WorksheetService), que reparte el valor en su columna
 *     tipada y separa la censura del número;
 *   · la VISTA PREVIA (el cálculo en vivo), que tiene que anticipar
 *     exactamente lo que el guardado va a producir.
 *
 * Si los dos hicieran su propia versión, la pantalla mostraría un número
 * mientras se escribe y otro después de guardar, y el analista dejaría de
 * creerle a la pantalla. Tener el criterio escrito dos veces es la forma segura
 * de que se desincronicen.
 *
 * Las tres traducciones que importan, y por qué:
 *
 *   ">75"   El instrumento llegó a su tope. Se guarda 75 con el signo aparte, y
 *           la fórmula opera sobre 75: es lo único que el equipo llegó a medir.
 *   "0,5"   El analista escribe con coma decimal.
 *   opción  Una columna de selección se guarda por clave foránea y se lee por
 *           su TEXTO. Pasarle el id al motor le daría un número que no está en
 *           la hoja.
 */
class ValueCoercer
{
    /**
     * Reparte un valor tipeado en las columnas de `worksheet_values`.
     *
     * @return array{value_num:?float,value_text:?string,option_id:?int,instrument_id:?int,qualifier:?string}
     */
    public function toColumns(TestField $field, mixed $raw): array
    {
        $blank = [
            'value_num'     => null,
            'value_text'    => null,
            'option_id'     => null,
            'instrument_id' => null,
            'qualifier'     => null,
        ];

        if ($raw === null || $raw === '' || is_array($raw) || is_bool($raw)) {
            return $blank;
        }

        $storage = $this->storageOf($field);

        if ($storage === 'option_id') {
            return array_merge($blank, ['option_id' => (int) $raw]);
        }

        if ($storage === 'instrument_id') {
            return array_merge($blank, ['instrument_id' => (int) $raw]);
        }

        if ($storage === 'value_num') {
            [$number, $qualifier] = $this->readCensored((string) $raw);

            return array_merge($blank, [
                'value_num'  => $number,
                'qualifier'  => $qualifier,
                // Sin número legible se conserva el texto tal como se escribió,
                // en vez de descartarlo: perder lo que cargó el analista es peor
                // que guardar algo que después hay que corregir.
                'value_text' => $number === null ? (string) $raw : null,
            ]);
        }

        return array_merge($blank, ['value_text' => (string) $raw]);
    }

    /**
     * Lo que el motor de fórmulas vería si ese valor YA estuviera guardado.
     *
     * Es `toColumns()` leído como lo lee `WorksheetValue::$resolved`: primero el
     * número, después el texto de la opción, después el texto suelto.
     */
    public function toFormulaInput(TestField $field, mixed $raw): float|string|null
    {
        $columns = $this->toColumns($field, $raw);

        if ($columns['value_num'] !== null) {
            return $columns['value_num'];
        }

        if ($columns['option_id'] !== null) {
            // La relación puede venir precargada; si no, se busca. Quien llame
            // esto en un bucle debería traer `options` con eager loading.
            return $field->relationLoaded('options')
                ? $field->options->firstWhere('id', $columns['option_id'])?->value
                : $field->options()->whereKey($columns['option_id'])->value('value');
        }

        // El instrumento con el que se midió es trazabilidad, no una medición:
        // no entra en ninguna fórmula.
        if ($columns['instrument_id'] !== null) {
            return null;
        }

        return $columns['value_text'];
    }

    /**
     * Separa el número de su signo de censura: ">75" es "al menos 75", no 75.
     *
     * @return array{0:?float,1:?string}
     */
    public function readCensored(string $value): array
    {
        $value = trim($value);
        $qualifier = null;

        if (preg_match('/^(>=|<=|>|<)\s*/', $value, $m)) {
            $qualifier = str_starts_with($m[1], '>') ? 'gt' : 'lt';
            $value = trim(substr($value, strlen($m[0])));
        }

        $value = str_replace(',', '.', $value);

        return [is_numeric($value) ? (float) $value : null, $qualifier];
    }

    private function storageOf(TestField $field): string
    {
        return config("lab_field_types.{$field->type}.storage", 'value_text');
    }
}
