<?php

/**
 * La MAQUETA de cada hoja del informe clásico.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ESTO ES CONFIGURACIÓN Y NO CÓDIGO                                │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema anterior tenía DIECISÉIS partials de Blade —uno por prueba—, cada
 * uno con su propia tabla escrita a mano: `_report_pcbs.erb`, `_report_furanos.erb`,
 * `_report_metales.erb`… Copias con variaciones mínimas (una columna MÉTODO acá,
 * una columna de límite allá) y cada una con sus propios errores: el límite del
 * DBDS estaba escrito `5` dentro del HTML, y el rango del inhibidor
 * `0.08 - 3.00 %` también. Agregar una prueba era copiar un archivo de 60 líneas
 * y editarlo; corregir la cabecera había que corregirla dieciséis veces.
 *
 * Acá el renderizador tiene UNA tabla y cada hoja declara acá cómo se ve: qué
 * columnas lleva, si su método está dentro del alcance acreditado (sello ANAB) y
 * qué norma de referencia cita al pie. Agregar una prueba al informe es agregar
 * una entrada; no se toca el renderizador.
 *
 * Los TÍTULOS no viven acá: son texto y viven en `resources/lang/{es,en}/reports.php`
 * bajo `family.*`. Acá va solo la estructura.
 *
 * La clave es la FAMILIA de la prueba (`test_definitions.family`), que es lo que
 * agrupa las pruebas en hojas: azufre corrosivo son tres ensayos y una sola hoja.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Las columnas disponibles
    |--------------------------------------------------------------------------
    | Cada hoja elige de esta lista, en este orden. RESULTADO va siempre última
    | y es la que se pinta en rojo cuando el valor quedó fuera de norma.
    |
    |   item        el número de ítem dentro de la hoja
    |   norma       la norma del MÉTODO con su marca de acreditación
    |   ensayo      el nombre del parámetro
    |   metodo      la técnica instrumental (HPLC, ICP-AES, GC-MS…)
    |   unidad      la unidad de medida
    |   orientacion el valor de aceptación aplicable, o raya si no hay
    |   resultado   lo medido
    */

    'defaults' => [
        'columns' => ['item', 'norma', 'ensayo', 'unidad', 'orientacion', 'resultado'],
        'anab'    => false,
        'cell_note' => false,
        'standard_note' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Las hojas, en el orden en que se imprimen
    |--------------------------------------------------------------------------
    | El orden es el del sistema anterior (`show.erb`), que es el que el
    | laboratorio y sus clientes conocen. Una hoja solo se imprime si la muestra
    | tiene resultados de esa familia.
    |
    | `anab` marca las TRES familias cuyos métodos están dentro del alcance
    | acreditado, y por eso las únicas tres hojas que llevan el sello: en el
    | sistema anterior eso era la diferencia entre incluir `_report_logo_main`
    | o `_report_logo_parcial`, decidida archivo por archivo.
    */

    'sheets' => [

        'fisicoquimico' => [
            'columns'   => ['item', 'norma', 'ensayo', 'unidad', 'orientacion', 'resultado'],
            'anab'      => true,
            // La leyenda del tipo de celda de la resistividad volumétrica y las
            // dos marcas de acreditación. Solo esta hoja las lleva.
            'cell_note' => true,
            'standard_note' => 'IEEE C57.106-2015',
        ],

        'analisis_cromatografico' => [
            'columns'   => ['item', 'norma', 'ensayo', 'unidad', 'orientacion', 'resultado'],
            'anab'      => true,
            'standard_note' => 'IEC 60599-2022',
            // El bloque RELACIONES (TG, TGC, cocientes de gases) es exclusivo de
            // esta hoja y lo calcula el renderizador.
            'ratios'    => true,
        ],

        'pcb' => [
            'columns' => ['item', 'norma', 'ensayo', 'metodo', 'unidad', 'orientacion', 'resultado'],
            'standard_note' => 'IEC 61619',
        ],

        'furanos' => [
            // Sin columna de orientación: la norma no fija criterio de
            // aceptación para furanos, y el sistema anterior tampoco la tenía.
            // Poner una columna con seis rayas sugiere un dato que falta.
            'columns' => ['item', 'norma', 'ensayo', 'metodo', 'unidad', 'resultado'],
            // El grado de polimerización estimado sale de la correlación de
            // Chendong sobre el 2-FAL, y el papel tiene que decirlo.
            'footnote' => 'reports.legacy_note_chendong',
        ],

        'particulas' => [
            'columns' => ['item', 'norma', 'ensayo', 'metodo', 'unidad', 'resultado'],
        ],

        'azufre_corrosivo' => [
            'columns' => ['item', 'norma', 'ensayo', 'resultado'],
            'anab'    => true,
        ],

        'sedimentos' => [
            'columns' => ['item', 'norma', 'ensayo', 'metodo', 'unidad', 'resultado'],
        ],

        'metales' => [
            'columns' => ['item', 'norma', 'ensayo', 'metodo', 'unidad', 'resultado'],
        ],

        'viscocidad' => [
            'columns' => ['item', 'norma', 'ensayo', 'unidad', 'resultado'],
        ],

        'dbds' => [
            // El límite referencial del DBDS (5 mg/kg según IEC 62697) estaba
            // ESCRITO A MANO dentro del HTML del informe viejo. Acá sale del
            // cuadro de límites como cualquier otro criterio, así que si la
            // norma se revisa se edita el dato y no la plantilla.
            'columns' => ['item', 'norma', 'ensayo', 'metodo', 'unidad', 'orientacion', 'resultado'],
            'standard_note' => 'IEC 62697-1',
        ],

        'inflamacion' => [
            'columns' => ['item', 'norma', 'ensayo', 'metodo', 'unidad', 'resultado'],
        ],

        'fluidez' => [
            'columns' => ['item', 'norma', 'ensayo', 'unidad', 'resultado'],
        ],

        'inhibidor' => [
            // Mismo caso que el DBDS: el rango "0.08 - 3.00 %" estaba clavado en
            // la plantilla vieja.
            'columns' => ['item', 'norma', 'ensayo', 'unidad', 'orientacion', 'resultado'],
            'standard_note' => 'IEC 60666',
        ],

        'grado_de_polimerizacion' => [
            'columns' => ['item', 'norma', 'ensayo', 'orientacion', 'resultado'],
        ],

        'pasivador' => [
            'columns' => ['item', 'norma', 'ensayo', 'metodo', 'unidad', 'orientacion', 'resultado'],
        ],
    ],
];
