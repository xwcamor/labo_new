/**
 * Cómo se LEE una fórmula de la plantilla. Acá no se calcula nada.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ ESTO SUSTITUYE TEXTO, NO RESUELVE CUENTAS                                │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Estas funciones cambian los nombres de columna de una fórmula por su
 * etiqueta o por el número que hay cargado, y devuelven una CADENA para
 * mostrar. No hay aritmética: el valor sigue saliendo del servidor, del mismo
 * motor que usa el guardado. Es la regla de fondo de la hoja de trabajo —en el
 * sistema anterior la fórmula era JavaScript guardado en la base y evaluado en
 * el navegador, y cuando operaba sobre un campo vacío dejaba el texto "NaN"
 * guardado como si fuera una medición.
 *
 * Para qué sirve: el analista ve "2.71" en la celda y no tiene forma de saber
 * de dónde salió. Con esto, el encabezado dice qué calcula la columna
 * («(Volumen gastado − Blanco) × Factor KOH / Peso del aceite») y la celda dice
 * con qué números lo hizo («(12.40 − 0.20) × 5.6 / 25.1»). Es lo que se revisa
 * cuando un resultado no cierra, y hasta ahora había que abrir la plantilla.
 */

/**
 * Recorre los identificadores de la fórmula y deja que `mapper` los reemplace.
 *
 * Un identificador SEGUIDO DE PARÉNTESIS es una función del motor (`log10`,
 * `abs`, `sqrt`) y se deja como está: reemplazarlo por una etiqueta convertiría
 * `log10(x)` en algo que no se entiende. Si `mapper` devuelve null o undefined,
 * el nombre queda igual — así una constante o una columna que ya no existe se
 * ven tal cual en vez de desaparecer.
 */
function mapIdentifiers(formula, mapper) {
    const src = String(formula ?? '');
    const re = /[A-Za-z_][A-Za-z0-9_]*/g;

    let salida = '';
    let desde = 0;
    let m;

    while ((m = re.exec(src)) !== null) {
        const nombre = m[0];
        const resto = src.slice(m.index + nombre.length);

        salida += src.slice(desde, m.index);
        salida += /^\s*\(/.test(resto) ? nombre : (mapper(nombre) ?? nombre);
        desde = m.index + nombre.length;
    }

    return salida + src.slice(desde);
}

/**
 * La fórmula con las ETIQUETAS de las columnas, para el encabezado.
 *
 * `(volumen_gastado_ml - vol_blanco) * factor_koh / peso_aceite_g`
 *   → `(Volumen gastado (mL) − Blanco) × Factor KOH / Peso del aceite (g)`
 */
export function formulaInLabels(formula, fields = []) {
    const porCodigo = new Map((fields ?? []).map((f) => [f.code, f.label]));

    return prettySigns(mapIdentifiers(formula, (nombre) => porCodigo.get(nombre)));
}

/**
 * La fórmula con los NÚMEROS de esta fila, para la celda.
 *
 * `resolver(code, replicate)` decide de dónde sale cada valor —lo tipeado, la
 * vista previa del servidor o lo guardado—; acá solo se dibuja. Una columna sin
 * cargar sale como raya: es la diferencia entre «todavía no se midió» y «midió
 * cero», y taparla con un 0 es lo que hacía el sistema anterior.
 */
export function formulaWithValues(formula, fields = [], resolver, replicate = 1) {
    const codigos = new Set((fields ?? []).map((f) => f.code));

    return prettySigns(mapIdentifiers(formula, (nombre) => {
        if (! codigos.has(nombre)) return null;   // constante del motor: se deja

        const valor = resolver(nombre, replicate);

        return (valor === null || valor === undefined || valor === '') ? '—' : String(valor);
    }));
}

/**
 * Los signos como se escriben a mano. `*` y `/` en una fórmula larga se leen
 * mal, y el `-` de teclado se confunde con el guion de una columna vacía.
 */
function prettySigns(texto) {
    return texto
        .replace(/\s*\*\s*/g, ' × ')
        .replace(/\s*\/\s*/g, ' ÷ ')
        .replace(/\s+-\s+/g, ' − ')
        .replace(/\s+\+\s+/g, ' + ');
}
