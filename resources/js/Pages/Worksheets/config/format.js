/**
 * Helpers de presentación de la bancada.
 *
 * Viven fuera de los componentes porque el listado, la ficha y la grilla tienen
 * que mostrar el MISMO estado con el mismo color: si cada pantalla arma su
 * propio mapa, el día que cambie una etiqueta quedan dos versiones y el
 * analista ve una hoja "cerrada" en un lado y "en carga" en el otro.
 */

/**
 * Fecha sin hora, tal como se guardó.
 *
 * `run_date` es un DATE: el día en que se corrió el ensayo, no un instante.
 * Pasarlo por el huso horario del usuario lo corre un día para atrás en
 * cualquier zona al oeste de Greenwich, y la fecha de ensayo es un dato de
 * trazabilidad. Por eso se recorta el ISO en vez de convertirlo.
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
 * Número tal como se cargó, sin los ceros de relleno.
 *
 * `value_num` es un decimal(24,8) y llega como cadena ("12.34000000"): se
 * castea a decimal y no a float a propósito, para no redondear en silencio lo
 * que midió el analista. Acá solo se sacan los ceros de la derecha, que es
 * presentación y no cambia el número.
 */
export const numText = (value) => {
    if (value === null || value === undefined || value === '') return '';

    const raw = String(value);
    if (!raw.includes('.')) return raw;

    return raw.replace(/0+$/, '').replace(/\.$/, '');
};

/**
 * Cómo se escribe un valor censurado: ">75", "<0.05".
 *
 * Es el equivalente del accessor `display` del modelo. Se rehace acá porque
 * `display` y `resolved` NO están en `$appends`: al frente llegan las columnas
 * crudas (`value_num` + `qualifier`), no el texto ya armado.
 *
 * El signo va siempre: publicar 75 donde el ensayo dice "más de 75" convierte
 * una cota en una medición.
 */
export const censoredText = (value) => {
    if (!value) return '';

    const sign = value.qualifier === 'gt' ? '>' : (value.qualifier === 'lt' ? '<' : '');

    return sign + numText(value.value_num);
};

/** Color del estado de la hoja. Anulada va en gris y tachada: existe, no cuenta. */
export const statusColor = (status) => ({
    draft:     'blue',
    closed:    'gold',
    validated: 'green',
    voided:    'default',
}[status] ?? 'default');

/**
 * Color del tipo de fila. El patrón, el duplicado y el blanco son el control de
 * calidad de la corrida: se distinguen de la muestra del cliente de un vistazo,
 * que es justamente lo que el supervisor busca cuando abre la hoja.
 */
export const kindColor = (kind) => ({
    sample:    'default',
    control:   'purple',
    duplicate: 'orange',
    blank:     'cyan',
}[kind] ?? 'default');

/**
 * Estado de calibración de un instrumento a partir de su vencimiento.
 *
 * El listado de instrumentos de la bancada llega con `calibration_due_at` y
 * nada más, así que el semáforo se deriva acá. Los 30 días de aviso son los
 * mismos que usa el módulo de Instrumentos.
 */
export const calibrationStatus = (dueAt) => {
    if (!dueAt) return 'unknown';

    const due = new Date(`${String(dueAt).slice(0, 10)}T00:00:00`);
    if (Number.isNaN(due.getTime())) return 'unknown';

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const days = Math.round((due - today) / 86400000);
    if (days < 0)  return 'expired';
    if (days <= 30) return 'due_soon';

    return 'valid';
};
