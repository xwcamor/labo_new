<?php

return [
    'title'    => 'Informe de ensayo',
    'subtitle' => 'Resultados de ensayo de aceite dieléctrico',
    'emitted'  => 'Emitido',

    'customer'      => 'Cliente y muestra',
    'customer_name' => 'Cliente',
    'service_order' => 'Orden de servicio',
    'sampled_at'    => 'Fecha de muestreo',
    'received_at'   => 'Fecha de recepción',
    'sampler'       => 'Tomada por',

    'equipment'      => 'Equipo',
    'equipment_name' => 'Equipo',
    'serial'         => 'Serie · Etiqueta',
    'equipment_type' => 'Tipo',
    'oil_type'       => 'Aceite',
    'voltage'        => 'Tensión',

    'col_item'     => 'Ítem',
    'col_standard' => 'Norma',
    'col_test'     => 'Ensayo',
    'col_unit'     => 'Unidad',
    'col_limit'    => 'Valor de orientación',
    'col_result'   => 'Resultado',

    'limit_max'   => '(máximo)',
    'limit_min'   => '(mínimo)',
    'out_of_spec' => 'fuera de norma',

    'no_results' => 'Esta muestra todavía no tiene ensayos validados.',

    // Advertencias impresas. Un informe que calla lo que le falta se lee como
    // completo, y ahí es donde un valor sin criterio pasa por conforme.
    'note_no_criteria' => 'Hay :count resultado(s) sin criterio de aceptación aplicable: se muestran sin comparar contra ningún límite. NO deben leerse como conformes.',
    'note_pending'     => 'Quedan :count ensayo(s) pedidos y todavía no validados: este informe es parcial.',
    'note_no_equipment' => 'La muestra no tiene equipo asignado.',

    'footer_legend' => 'Los resultados corresponden únicamente a la muestra recibida. El valor de orientación es el criterio de aceptación aplicable a este equipo según la norma indicada en cada fila.',
    'footer_accreditation' => '(A) método acreditado · (NA) método no acreditado. La marca corresponde al método con el que se corrió este ensayo.',
    'generated_by'  => 'Emitido por: :name',
];
