/**
 * La placa del transformador escrita como la escribe la gente: "220/60/10".
 *
 * Es el gemelo en el navegador de `App\Support\PlateValue` (PHP), y tiene que
 * partir igual: uno reparte lo que se pega en el formulario y el otro lo que
 * llega por importación, y si difirieran el mismo texto entraría distinto según
 * por dónde entró.
 *
 * El sistema GUARDA números separados —la clase de tensión que decide el criterio
 * del IEEE C57.106 necesita un número comparable— pero nadie tipea la placa en
 * tres casillas: la chapa dice "220/60/10". Esto convierte esa cadena; lo que se
 * guarda sigue siendo números.
 */

/**
 * Los números de una placa escrita como cadena, en el orden en que vienen.
 *
 * @param {unknown} valor
 * @returns {number[]}
 */
export const parsePlate = (valor) => {
    // El cero se descarta también en el atajo del número suelto: la regla tiene
    // que ser una sola para todos los caminos de entrada.
    if (typeof valor === 'number' && Number.isFinite(valor)) return valor === 0 ? [] : [valor];
    if (typeof valor !== 'string' || valor.trim() === '') return [];

    // Fuera las unidades y cualquier palabra ("220 kV / 60 kV"): se hace ANTES
    // de partir, para no producir segmentos vacíos.
    let texto = valor.replace(/\p{L}+/gu, ' ');

    // LA COMA: en la escritura local es el separador decimal ("13,8"), pero en
    // una placa también puede separar devanados ("220, 60"). Si está pegada a
    // dígitos por los dos lados y no hay ningún punto, es un decimal; si no,
    // separa. Sin esta regla "13,8" entraba como dos tensiones y la clase de
    // tensión salía del tramo equivocado.
    if (!texto.includes('.') && /\d,\d/.test(texto)) {
        texto = texto.replace(/(\d),(\d)/g, '$1.$2');
    }

    return texto
        .split(/[/\-;,\s]+/)
        .map((parte) => parte.trim())
        .filter((parte) => parte !== '' && Number.isFinite(Number(parte)))
        .map(Number)
        // EL CERO NO ES UN VALOR DE PLACA. En el sistema anterior "sin dato" se
        // escribia `-` o `0`, el `.to_i` lo volvia 0 kV, y un 0 entra en el
        // cuadro de "hasta 69 kV", que es el criterio MAS LAXO: no saber la
        // tension hacia que el aceite se juzgara con la vara mas blanda.
        .filter((numero) => numero !== 0);
};

/** El divisor de fase: `500/1.73` es una division, no dos devanados. */
const DIVISOR_DE_FASE = 1.73;

/**
 * ¿La barra se esta usando como DIVISION? Caso real de los reactores: `500/1.73`
 * es 500 partido por raiz de tres. Repartirlo escribe 1.73 kV como tension de
 * baja, que es un dato inventado y decide con que cuadro de limites se juzga el
 * ensayo. Hay que preguntar, no elegir.
 */
export const looksLikeDivision = (valor) => parsePlate(valor)
    .slice(1)
    .some((numero) => Math.abs(numero - DIVISOR_DE_FASE) < 0.05);

/**
 * Las tensiones de mayor a menor.
 *
 * Los tres campos son ROLES (alta / baja / terciario) y el de ALTA es el que
 * decide la clase de tension del cuadro de limites. La POTENCIA no se ordena: sus
 * valores son los escalones de enfriamiento y ahi el orden es el dato.
 */
export const sortVoltages = (valores) => {
    const numeros = valores.filter((v) => v !== null && v !== undefined && v !== '').sort((a, b) => b - a);

    return Array.from({ length: valores.length }, (_, i) => (
        numeros[i] === undefined ? null : numeros[i]
    ));
};

/**
 * Reparte la placa en N casillas y devuelve además lo que no entró.
 *
 * Lo que sobra se DEVUELVE en vez de descartarse: si una placa trae cuatro
 * devanados y el formulario tiene tres campos, hay que poder decírselo a quien
 * está cargando en vez de perder el cuarto sin aviso.
 *
 * @param {unknown} valor
 * @param {number} casillas
 * @returns {{ values: (number|null)[], extra: number[] }}
 */
export const splitPlate = (valor, casillas) => {
    const numeros = parsePlate(valor);
    const values = Array.from({ length: casillas }, (_, i) => (
        numeros[i] === undefined ? null : numeros[i]
    ));

    return { values, extra: numeros.slice(casillas) };
};

/** ¿Este texto trae más de un número? Es lo que decide si hay algo que repartir. */
export const looksLikePlate = (valor) => parsePlate(valor).length > 1;
