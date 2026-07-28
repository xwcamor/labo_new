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

    'verify_code' => 'Código',
    'verify_hint' => 'Verifique este informe escaneando el código',
    'no_signers'  => 'Firma',
    'relation'    => [
        'prepared'  => 'Realizado por',
        'reviewed'  => 'Revisado por',
        'approved'  => 'Aprobado por',
        'authorized'=> 'Autorizado por',
        'verified'  => 'Verificado por',
        'endorsed'  => 'Avalado por',
    ],
    'verify_sample'    => 'Muestra',
    'verify_equipment' => 'Equipo',
    'verify_sections'  => 'Ensayos informados',
    // ── Portal público de verificación (el destino del QR) ──────────────
    'verify_title'      => 'Verificación de informe',
    'verify_ok'         => 'Informe verificado',
    'verify_ok_sub'     => 'Este código corresponde a un informe emitido por el sistema.',
    'verify_fail'       => 'Código no encontrado',
    'verify_fail_sub'   => 'El código :code no corresponde a ningún informe emitido. Revise que esté completo.',
    'verify_form_hint'  => 'Ingrese el código impreso en el informe para comprobar que es auténtico.',
    'verify_form_btn'   => 'Verificar',
    'verify_foot'       => 'Esta página solo confirma que el informe salió del sistema. No publica los resultados del ensayo.',
    'verify_issued_at'  => 'Fecha de emisión',
    'verify_issued_by'  => 'Emitido por',
    'verify_signers'    => 'Firmantes',
    'verify_match_hint' => 'Si algún dato no coincide con el papel que tiene en la mano, el documento fue alterado.',
    'verify_serial'     => 'Serie',
    'code'              => 'Informe',
    'health_index'      => 'Índice de salud',

    'footer_legend' => 'Los resultados corresponden únicamente a la muestra recibida. El valor de orientación es el criterio de aceptación aplicable a este equipo según la norma indicada en cada fila.',
    'footer_accreditation' => '(A) método acreditado · (NA) método no acreditado. La marca corresponde al método con el que se corrió este ensayo.',
    'generated_by'  => 'Emitido por: :name',
];
