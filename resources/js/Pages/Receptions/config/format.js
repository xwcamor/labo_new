/**
 * Helpers de presentación de la recepción.
 *
 * Viven fuera de los componentes por el mismo motivo que los de la bancada: el
 * listado, la ficha y la tabla de muestras tienen que decir el MISMO estado con
 * el mismo color. Si cada pantalla arma su propio mapa, el día que cambie una
 * etiqueta quedan dos versiones y una entrega figura "confirmada" en un lado y
 * "borrador" en el otro.
 */

/**
 * Fecha sin hora, tal como se guardó.
 *
 * La fecha de recepción es un dato de trazabilidad —de ella sale el año del
 * correlativo—, así que se recorta el ISO en vez de convertirlo: pasarla por el
 * huso horario del navegador la corre un día para atrás en cualquier zona al
 * oeste de Greenwich.
 */
export const plainDate = (value) => {
    if (!value) return '';
    const iso = String(value).slice(0, 10);
    const [year, month, day] = iso.split('-');

    return (year && month && day) ? `${day}-${month}-${year}` : iso;
};

/** El ISO recortado (YYYY-MM-DD), que es lo que consumen los DatePicker. */
export const isoDate = (value) => (value ? String(value).slice(0, 10) : null);

/**
 * Color del estado de la entrega.
 *
 * El borrador va en gris porque todavía no es trabajo del laboratorio; la
 * confirmada en azul porque está en curso; la cerrada en verde. La anulada va
 * en gris y tachada: existe —el laboratorio responde por ella— pero no cuenta.
 */
export const statusColor = (status) => ({
    draft:     'default',
    confirmed: 'blue',
    closed:    'green',
    cancelled: 'default',
}[status] ?? 'default');

/**
 * Color del estado de una prueba pedida.
 *
 * PENDIENTE va en ROJO, no en gris. El gris se lee como "no aplica" y se pierde
 * entre las verdes: una prueba pedida y sin cargar es lo único de esa lista que
 * le falta trabajo, así que es lo único que tiene que resaltar. Es la misma
 * lógica del semáforo de avance de la fila.
 *
 * DADA DE BAJA sí queda en gris: existe —el laboratorio responde por ella— pero
 * ya no se espera nada de ella.
 */
export const testStatusColor = (status) => ({
    pending:     'red',
    in_progress: 'blue',
    validated:   'green',
    reported:    'purple',
    cancelled:   'default',
}[status] ?? 'default');

/**
 * El correlativo tal como se imprime: 2026-0695.
 *
 * Es el espejo de `Sample::formatCode()`. Se rehace acá —y no se pide otro dato
 * al servidor— solo para ANTICIPAR qué números se van a emitir al confirmar. El
 * número real lo emite el servidor: entre que esta pantalla se dibuja y alguien
 * confirma pueden entrar otras recepciones.
 */
export const formatCode = (year, number) =>
    `${year}-${String(number).padStart(4, '0')}`;

/**
 * El rango de correlativos que se emitiría, a partir del próximo y la cantidad.
 *
 * Devuelve `null` si no hay próximo número: sin eso no se puede prometer nada,
 * y prometer un rango equivocado es peor que no decir nada.
 */
export const codeRange = (nextNumber, count) => {
    if (!nextNumber || !count || count < 1) return null;

    const [year, num] = String(nextNumber).split('-');
    const first = Number(num);

    if (!year || Number.isNaN(first)) return null;

    return { from: formatCode(year, first), to: formatCode(year, first + count - 1) };
};
