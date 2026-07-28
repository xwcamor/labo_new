/**
 * Los cinco límites de una carta de control, del lado del navegador.
 *
 * Espeja `QcChart::limits()` y `QcChart::derive()` del servidor, y existe por
 * dos motivos concretos:
 *
 *  - el listado no recibe los límites resueltos (el controlador solo los manda
 *    en la ficha), y mostrar `lcl` crudo cuando la carta los deriva mentiría;
 *  - el formulario tiene que anticipar lo que va a calcular el servidor
 *    mientras el supervisor mueve la media y el desvío.
 *
 * Es una VISTA PREVIA. El número que queda guardado siempre lo calcula el
 * servidor: si esta cuenta y la de PHP alguna vez discreparan, manda PHP.
 */

/** Un número o nada. Rechaza null, '', 'NaN' y cualquier texto no numérico. */
export function toNumber(raw) {
    if (raw === null || raw === undefined || raw === '') return null;
    const n = typeof raw === 'number' ? raw : Number(String(raw).trim());
    return Number.isFinite(n) ? n : null;
}

/** Formato corto sin arrastrar el ruido decimal del punto flotante. */
export function fmtNumber(raw, dash = '—') {
    const n = toNumber(raw);
    return n === null ? dash : String(parseFloat(n.toFixed(6)));
}

/**
 * Los cuatro extremos derivados de la media y el desvío.
 *
 * Devuelve un objeto vacío si falta cualquiera de los dos, igual que el
 * servidor: una carta sin límites se ve y se corrige; una carta con límites
 * inventados sobre un desvío ausente no se ve.
 */
export function deriveLimits(chart) {
    const center = toNumber(chart?.center);
    const sd = toNumber(chart?.sd);

    if (center === null || sd === null || sd <= 0) return {};

    const warn = toNumber(chart?.warn_sigma) ?? 2;
    const action = toNumber(chart?.action_sigma) ?? 3;

    return {
        lcl: center - action * sd,
        lwl: center - warn * sd,
        uwl: center + warn * sd,
        ucl: center + action * sd,
    };
}

/**
 * Los cinco límites con los nombres del dominio (lci/lai/lc/las/lcs). Cuando la
 * carta declara `is_derived` manda el cálculo, para que lo que se muestra no
 * pueda diferir de lo que se declaró.
 */
export function resolveLimits(chart) {
    const derived = chart?.is_derived ? deriveLimits(chart) : {};

    return {
        lci: derived.lcl ?? toNumber(chart?.lcl),
        lai: derived.lwl ?? toNumber(chart?.lwl),
        lc:  toNumber(chart?.center),
        las: derived.uwl ?? toNumber(chart?.uwl),
        lcs: derived.ucl ?? toNumber(chart?.ucl),
    };
}

/** Orden de presentación: de arriba hacia abajo, como se leen en el gráfico. */
export const LIMIT_KEYS = ['lcs', 'las', 'lc', 'lai', 'lci'];
