<?php

return [

    'title'     => 'Cartas de control',
    'singular'  => 'Carta de control',
    'create'    => 'Nueva carta de control',
    'edit'      => 'Editar carta de control',
    'show'      => 'Carta de control',
    'trash'     => 'Papelera de cartas de control',

    'intro'     => 'Una carta de control vigila que el laboratorio esté midiendo bien. Se construye con el patrón control, no con las muestras del cliente: mide el método, no el aceite.',

    // ── Campos ────────────────────────────────────────────────────────────
    'test_definition' => 'Prueba',
    'test_field'      => 'Columna',
    'analyte'         => 'Parámetro',
    'label'           => 'Nombre',
    'control_lot'     => 'Lote del patrón',
    'comment'         => 'Observaciones',
    'is_active'       => 'Activa',

    // ── Los cinco límites ─────────────────────────────────────────────────
    // Son una carta de Shewhart, la misma que en laboratorio clínico se conoce
    // como Levey-Jennings.
    'limits' => [
        'lcs' => 'LCS · Límite de control superior',
        'las' => 'LAS · Límite de alerta superior',
        'lc'  => 'LC · Línea central',
        'lai' => 'LAI · Límite de alerta inferior',
        'lci' => 'LCI · Límite de control inferior',
    ],
    'limits_short' => [
        'lcs' => 'LCS',
        'las' => 'LAS',
        'lc'  => 'LC',
        'lai' => 'LAI',
        'lci' => 'LCI',
    ],
    'limits_help' => 'Los cinco se guardan explícitos, no solo la media y el desvío, porque a veces el laboratorio los fija por criterio propio o porque los exige la norma. Si prefiere derivarlos, cargue la media y el desvío y active el cálculo automático.',

    'center'       => 'Media del patrón',
    'sd'           => 'Desvío estándar',
    'is_derived'   => 'Derivar los límites de la media y el desvío',
    'warn_sigma'   => 'Desvíos para la alerta',
    'action_sigma' => 'Desvíos para el control',
    'sigma_help'   => 'El criterio clásico es 2 desvíos para la alerta y 3 para el control. Se dejan configurables porque no todos los parámetros usan ese criterio.',

    // ── Vigencia ──────────────────────────────────────────────────────────
    'effective_from' => 'Vigente desde',
    'effective_to'   => 'Vigente hasta',
    'validity_help'  => 'Al cambiar el lote del patrón, cierre la carta actual y abra una nueva. Así las cartas históricas se siguen dibujando contra los límites que regían ese día, y no contra los de hoy.',

    // ── Repetibilidad ─────────────────────────────────────────────────────
    'repeatability_limit' => 'Criterio de repetibilidad',
    'repeatability_mode'  => 'Modo del criterio',
    'repeatability_help'  => 'Diferencia máxima aceptable entre una muestra y su duplicado. En modo absoluto se compara en las unidades del ensayo; en modo relativo, contra el promedio de las dos lecturas.',
    'mode' => [
        'absolute' => 'Absoluto (en las unidades del ensayo)',
        'relative' => 'Relativo (% del promedio de las dos lecturas)',
    ],

    // ── Puntos y veredicto ────────────────────────────────────────────────
    'points'       => 'Mediciones del patrón',
    'measured_at'  => 'Fecha',
    'value'        => 'Valor',
    'z_score'      => 'Desvíos respecto de la media',
    'flag'         => 'Veredicto',
    'flags' => [
        'ok'   => 'En control',
        'warn' => 'Alerta',
        'out'  => 'Fuera de control',
    ],

    // ── Reglas de Westgard ────────────────────────────────────────────────
    // El sistema viejo dibujaba los límites y no evaluaba nada: el analista
    // tenía que darse cuenta mirando el gráfico.
    'westgard'       => 'Regla de Westgard',
    'westgard_rules' => [
        '1_3s' => '1-3s · un punto fuera de 3 desvíos',
        '2_2s' => '2-2s · dos puntos seguidos fuera de 2 desvíos del mismo lado',
        'R_4s' => 'R-4s · dos puntos seguidos separados por más de 4 desvíos',
        '4_1s' => '4-1s · cuatro puntos seguidos fuera de 1 desvío del mismo lado',
        '10x'  => '10x · diez puntos seguidos del mismo lado de la media',
    ],
    'westgard_meaning' => [
        '1_3s' => 'Rechazo. Error aleatorio grande.',
        '2_2s' => 'Rechazo. Error sistemático: el método se corrió.',
        'R_4s' => 'Rechazo. Error aleatorio: la dispersión creció.',
        '4_1s' => 'Aviso. Sesgo naciente.',
        '10x'  => 'Aviso. El patrón se corrió aunque ningún punto se haya salido.',
    ],

    // ── Duplicados ────────────────────────────────────────────────────────
    'duplicates'          => 'Duplicados',
    'difference'          => 'Diferencia',
    'relative_difference' => 'Diferencia relativa',
    'within_limit'        => 'Dentro del criterio',
    'no_criterion'        => 'Sin criterio cargado: se informa la diferencia pero no se dictamina.',

    'created' => 'Carta de control creada.',
    'updated' => 'Carta de control actualizada.',
    'deleted' => 'Carta de control eliminada.',
    'point_updated' => 'Medición actualizada.',

    'excluded'         => 'Excluida',
    'reinclude'        => 'Reincorporar al cálculo',
    'sigma_invalid'    => 'La alerta tiene que estar por dentro del control: menos desvíos que el límite de control.',
    'derived_server'   => 'Vista previa. El valor definitivo lo calcula el servidor al guardar.',
    'limits_title'     => 'Límites de la carta',
    'validity'         => 'Vigencia',
    'exclude'          => 'Excluir del cálculo',
    'exclusion_reason' => 'Motivo de la exclusión',

    'empty'        => 'Todavía no hay cartas de control.',
    'empty_points' => 'La carta no tiene mediciones. Se llenan al validar una hoja de trabajo que tenga patrón control.',

];
