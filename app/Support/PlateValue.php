<?php

namespace App\Support;

/**
 * La placa del transformador escrita como la escribe la gente: "220/60/10".
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ HACE FALTA PARTIR UNA CADENA                                     │
 * └──────────────────────────────────────────────────────────────────────────┘
 * La tensión y la potencia se GUARDAN como números separados —uno por devanado,
 * uno por etapa de enfriamiento— y eso no se discute: la clase de tensión que
 * decide el criterio del IEEE C57.106 necesita un número comparable, y el
 * sistema anterior la tenía como texto libre, con lo que no se podía comparar
 * nada.
 *
 * Pero NADIE escribe la placa en tres casillas. La chapa del equipo dice
 * "220/60/10", el correo del cliente dice "220 / 60", y la planilla que llega
 * para importar trae una sola columna. Rechazar eso sin explicar por qué —que es
 * lo que hacía el formulario, con tres controles numéricos que descartan en
 * silencio lo que se pega— obliga a tipear a mano lo que ya estaba escrito, y
 * ahí es donde se cambia un dígito.
 *
 * Esta clase hace UNA cosa: convertir esa cadena en la lista de números. Lo que
 * se guarda sigue siendo números.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ SEPARADORES SE ACEPTAN, Y POR QUÉ ESOS                               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Barra, guion, punto y coma, y el espacio. Salen de cómo aparece el dato en la
 * práctica: "220/60", "220-60", "138 13.8". La COMA es el caso delicado y por
 * eso tiene su propia regla, abajo.
 *
 * Las unidades pegadas ("220 kV", "30 MVA", "10MVA") se descartan: son un
 * rótulo, no parte del número.
 */
class PlateValue
{
    /**
     * Cuántos segmentos guarda el modelo, por magnitud.
     *
     * Tres, y no es una elección de diseño: se midió sobre los 480 valores del
     * volcado real del sistema anterior y NO hay un solo caso de más de tres
     * (tensión: 53 con uno, 117 con dos, 70 con tres; potencia: 186, 22, 32).
     */
    public const CASILLAS = 3;

    /**
     * El divisor de fase, y por qué merece una constante.
     *
     * En el volcado real hay 3 equipos de 100 —reactores— cuya tensión está
     * escrita `500/1.73`: ahí la barra es una DIVISIÓN (500 partido por raíz de
     * tres), no dos devanados. Un repartidor ciego escribe 1.73 kV como tensión
     * de baja, que es un dato inventado, y peor: ese número decide después con
     * qué cuadro de límites se juzga el ensayo.
     */
    private const DIVISOR_DE_FASE = 1.73;

    /**
     * Los números de una placa escrita como cadena, en el orden en que vienen.
     *
     * Devuelve lista vacía si no hay ningún número reconocible: quien llama
     * decide si eso es un error de validación o simplemente un campo en blanco.
     *
     * EL CERO NO ES UN VALOR DE PLACA y se descarta. No es una sutileza: en el
     * sistema anterior "sin dato" se escribía `-` o `0` (5 de 100 equipos), el
     * `.to_i` lo volvía 0 kV, y un 0 entra en el cuadro de "hasta 69 kV" — el
     * criterio MÁS LAXO de todos. O sea que no saber la tensión hacía que el
     * aceite se juzgara con la vara más blanda. Con el campo en nulo, en cambio,
     * el resolvedor de cuadros no le asigna ninguna clase y el hueco se ve.
     *
     * @return array<int,float>
     */
    public static function parse(mixed $valor): array
    {
        if (is_numeric($valor)) {
            // El atajo del número suelto también descarta el cero: si no, un
            // `0` que llega ya convertido (de una celda numérica del archivo,
            // por ejemplo) se colaba por acá y volvía a caer en el cuadro más
            // laxo. La regla tiene que ser una sola para todos los caminos.
            return (float) $valor === 0.0 ? [] : [(float) $valor];
        }

        if (! is_string($valor) || trim($valor) === '') {
            return [];
        }

        $texto = trim($valor);

        // Fuera las unidades y cualquier palabra: quedan los números y los
        // separadores. Se hace ANTES de partir para que "220 kV / 60 kV" no
        // produzca segmentos vacíos.
        $texto = preg_replace('/[\p{L}]+/u', ' ', $texto) ?? '';

        // LA COMA: en la escritura local es separador DECIMAL ("13,8"), pero en
        // una placa también puede separar devanados ("220, 60"). La regla que
        // resuelve los dos casos sin adivinar: si la coma está pegada a dígitos
        // por los dos lados y NO hay ningún punto en el texto, es un decimal;
        // en cualquier otro caso separa. Sin esto, "13,8" entraba como dos
        // tensiones (13 y 8) y la clase de tensión salía mal.
        if (! str_contains($texto, '.') && preg_match('/\d,\d/', $texto)) {
            $texto = preg_replace('/(\d),(\d)/', '$1.$2', $texto) ?? $texto;
        }

        $partes = preg_split('#[/\-;,\s]+#', $texto) ?: [];

        $numeros = [];

        foreach ($partes as $parte) {
            $parte = trim($parte);

            if ($parte === '' || ! is_numeric($parte)) {
                continue;
            }

            // El cero se descarta acá, en la raíz, para que ningún camino de
            // entrada lo guarde. Ver la explicación en el bloque de arriba.
            if ((float) $parte === 0.0) {
                continue;
            }

            $numeros[] = (float) $parte;
        }

        return $numeros;
    }

    /**
     * ¿Esta cadena usa la barra como DIVISIÓN y no como separador de devanados?
     *
     * El caso real: `500/1.73` en los reactores, que es 500 partido por raíz de
     * tres. Se reconoce por el divisor, no por adivinar: un segundo segmento de
     * 1.73 no es una tensión de servicio de ningún equipo.
     *
     * Quien llama tiene que RECHAZAR y preguntar, no elegir por su cuenta: puede
     * ser que el equipo sea de 500 kV, o de 289 kV (el resultado de la
     * división), y esa diferencia cambia el cuadro de límites con el que se
     * juzga el aceite.
     */
    public static function looksLikeDivision(mixed $valor): bool
    {
        $numeros = self::parse($valor);

        if (count($numeros) < 2) {
            return false;
        }

        foreach (array_slice($numeros, 1) as $numero) {
            if (abs($numero - self::DIVISOR_DE_FASE) < 0.05) {
                return true;
            }
        }

        return false;
    }

    /**
     * Las tensiones ordenadas de mayor a menor.
     *
     * Los tres campos de tensión son ROLES —alta, baja, terciario—, no
     * posiciones de una lista, y `voltage_kv_hv` es el que después decide la
     * clase de tensión del cuadro de límites. En el volcado real 181 de 187
     * placas ya vienen en orden descendente; las 6 que no, si se guardaran tal
     * cual, dejarían la tensión más baja en el campo de ALTA y el ensayo se
     * juzgaría contra el criterio de otra clase.
     *
     * La POTENCIA no se ordena, y es deliberado: sus tres valores son los
     * escalones de enfriamiento (ONAN / ONAF / OFAF) y ahí el orden es el dato.
     * En la planilla del cliente los 12 casos vienen en orden ascendente.
     *
     * @param  array<int,float|null> $tensiones
     * @return array<int,float|null>
     */
    public static function sortVoltages(array $tensiones): array
    {
        $valores = array_values(array_filter($tensiones, fn ($v) => $v !== null && $v !== ''));

        rsort($valores, SORT_NUMERIC);

        return array_pad($valores, count($tensiones), null);
    }

    /**
     * Los números de la placa repartidos en N casillas, rellenando con null.
     *
     * Se usa para volcar la cadena en las columnas del equipo. Los segmentos que
     * sobran se DEVUELVEN aparte en vez de descartarse en silencio: si una placa
     * trae cuatro tensiones y el modelo guarda tres, el sistema tiene que poder
     * decirlo — perder el cuarto devanado sin avisar es exactamente el tipo de
     * pérdida silenciosa que este proyecto vino a corregir.
     *
     * En los datos reales del sistema anterior eso NO pasa nunca (0 casos de más
     * de tres segmentos en 480 valores), así que un sobrante es señal de que la
     * celda trae otra cosa —una nota, dos placas juntas, una división— y hay que
     * mirarla, no truncarla.
     *
     * @return array{0:array<int,float|null>,1:array<int,float>} [las casillas, lo que sobró]
     */
    public static function split(mixed $valor, int $casillas): array
    {
        $numeros = self::parse($valor);
        $usados  = array_slice($numeros, 0, $casillas);
        $sobra   = array_slice($numeros, $casillas);

        return [
            array_pad($usados, $casillas, null),
            $sobra,
        ];
    }

    /**
     * La placa de vuelta a texto, como se imprime: "220 / 60 / 10".
     *
     * Sin ceros de relleno a la derecha: 13.80 se lee 13.8, y 220.00 se lee 220.
     *
     * @param array<int,float|string|null> $numeros
     */
    public static function label(array $numeros): ?string
    {
        $limpios = [];

        foreach ($numeros as $numero) {
            if ($numero === null || $numero === '') {
                continue;
            }

            $limpios[] = rtrim(rtrim(number_format((float) $numero, 2, '.', ''), '0'), '.');
        }

        return $limpios === [] ? null : implode(' / ', $limpios);
    }
}
