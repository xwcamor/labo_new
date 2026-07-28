/**
 * Agrupar las pruebas del laboratorio por su grupo (`test_groups`).
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL GRUPO ES UN DATO, NO UNA LISTA ESCRITA A MANO                         │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Las pruebas del laboratorio son 29 y siguen creciendo. Ofrecerlas en una
 * lista plana obliga a leerlas todas para encontrar una: quien busca "Etileno"
 * tiene que saber de memoria que vive dentro de Análisis Cromatográfico.
 *
 * La tabla `test_groups` ya dice a qué familia pertenece cada una (Físico
 * Químico · Cromatografías · Otros) y hasta ahora no la miraba ninguna
 * pantalla. Este ayudante es la única pieza que arma esa agrupación, para que
 * el filtro del listado, el alta de la hoja y el pedido de pruebas de una
 * recepción muestren SIEMPRE los mismos grupos en el mismo orden.
 *
 * DEGRADA SOLO: si el servidor no manda `group` (porque esa pantalla todavía no
 * lo pide), todas las pruebas caen en un único bloque sin nombre y `isGrouped`
 * devuelve `false`. La pantalla dibuja entonces la lista plana de siempre, sin
 * encabezados vacíos y sin romperse.
 */

/**
 * @param  {Array<object>} tests         Pruebas tal como las manda el servidor.
 * @param  {string}        fallbackLabel Texto del bloque de las que no tienen grupo.
 * @return {Array<{key: (number|string), label: string, tests: Array<object>}>}
 */
export const groupTests = (tests = [], fallbackLabel = '') => {
    const buckets = new Map();

    for (const test of tests) {
        const group = test?.group ?? null;
        const key = group?.id ?? null;

        if (!buckets.has(key)) {
            buckets.set(key, {
                key: key ?? 'none',
                label: group?.name ?? fallbackLabel,
                // Las que no tienen grupo van al final: son la excepción, no el
                // encabezado con el que se empieza a leer.
                order: group ? Number(group.sort_order ?? 0) : Number.MAX_SAFE_INTEGER,
                tests: [],
            });
        }

        buckets.get(key).tests.push(test);
    }

    // El orden de las pruebas DENTRO del grupo es el que mandó el servidor
    // (`sort_order`): es el que el laboratorio eligió y no se reordena acá.
    return [...buckets.values()].sort(
        (a, b) => a.order - b.order || String(a.label).localeCompare(String(b.label)),
    );
};

/**
 * ¿Vale la pena dibujar los encabezados?
 *
 * Con un solo bloque —o peor, con un solo bloque que ni siquiera tiene nombre
 * porque el servidor no mandó el grupo— el encabezado no separa nada y ocupa
 * una línea que le saca lugar a la lista.
 *
 * @param  {Array<{key: (number|string)}>} groups
 * @return {boolean}
 */
export const isGrouped = (groups = []) =>
    groups.length > 1 || (groups.length === 1 && groups[0].key !== 'none');
