<?php

return [
    'plural'   => 'Informes',
    'singular' => 'Informe',
    'tab'      => 'Informes',
    'new'      => 'Nuevo informe',
    'edit'     => 'Editar informe',
    'empty'    => 'Esta entrega todavía no tiene informes emitidos.',
    'empty_hint' => 'El informe se crea desde la muestra, una vez que hay ensayos validados.',

    // ── Estados y tipo ────────────────────────────────────────────────────
    'status'   => 'Estado',
    'status_draft'  => 'Borrador',
    'status_issued' => 'Emitido',
    'kind'       => 'Tipo',
    'kind_primary'    => 'Principal',
    'kind_additional' => 'Adicional',
    'kind_help' => 'El primer informe de una muestra es el principal. Los que se emiten después —una prueba que llegó tarde, una corrección— son adicionales.',

    // ── Columnas del listado ──────────────────────────────────────────────
    'code'         => 'Nº de informe',
    'sample'       => 'Muestra',
    'issued_at'    => 'Fecha de emisión',
    'delivered_at' => 'Fecha de entrega',
    'issued_by'    => 'Emitido por',
    'tests_count'  => 'Ensayos publicados',

    // ── Acciones ──────────────────────────────────────────────────────────
    'create'   => 'Crear informe',
    'issue'    => 'Emitir',
    'issue_confirm' => '¿Emitir el informe :code? Una vez emitido no se puede editar: su contenido queda congelado y el papel sale con ese número. Si algo cambia después, se emite un informe adicional.',
    'download' => 'Descargar PDF',
    'preview'  => 'Vista previa',

    // ── Mensajes ──────────────────────────────────────────────────────────
    'created' => 'Informe :code creado como borrador.',
    'saved'   => 'Informe guardado.',
    'issued'  => 'Informe :code emitido.',
    'deleted' => 'Informe dado de baja.',
    'already_issued'      => 'El informe ya está emitido.',
    'issued_is_final'     => 'Un informe emitido no se edita. Emita un informe adicional.',
    'issued_cannot_delete' => 'Un informe emitido no se puede dar de baja: el cliente tiene un papel con ese código y el portal de verificación tiene que seguir encontrándolo.',

    // ── Secciones del formulario ──────────────────────────────────────────
    'section_reference' => 'Referencia',
    'section_equipment' => 'Datos del equipo',
    'section_sample'    => 'Datos de la muestra',
    'section_tests'     => 'Análisis',

    'header_note' => 'Los datos del cliente, de la muestra y del equipo se guardan donde corresponde, no como copia dentro del informe: corregir el TAG del transformador acá lo corrige para las próximas muestras del mismo equipo.',

    // ── Campos ────────────────────────────────────────────────────────────
    'service_order' => 'Orden de servicio',
    'contact_info'  => 'Contacto del cliente',
    'end_user'      => 'Usuario final',
    'end_user_help' => 'De quién es el equipo, cuando no es el que envía la muestra.',
    'received_at'   => 'Fecha de recepción',
    'sampler'       => 'Muestra extraída por',
    'description'   => 'Descripción de la muestra',
    'sampling_reason' => 'Razón de análisis',
    'sampling_point'  => 'Punto de muestreo',
    'sampled_at'    => 'Fecha de muestreo',
    'report_number' => 'Nº de informe declarado',
    'report_number_help' => 'Solo para informes cargados desde el sistema anterior. Los nuevos toman su correlativo del sistema.',
    'customer'      => 'Cliente',
    'serial'        => 'Nº de serie',
    'location'      => 'Locación',
    'tag'           => 'Código del cliente / TAG',
    'equipment_type' => 'Tipo de equipo',
    'brand'         => 'Fabricante',
    'oil_type'      => 'Tipo de aceite',
    'oil_brand'     => 'Marca de aceite',
    'voltage'       => 'Tensión (kV)',
    'power'         => 'Potencia (MVA)',
    'manufacture_year' => 'Año de fabricación',
    'tap_changer'   => 'Conmutador',
    'preservation'  => 'Sistema de expansión',
    'oil_volume'    => 'Cantidad de aceite',
    'oil_temp_c'        => 'Temp. aceite transformador (°C)',
    'equipment_temp_c'  => 'Temp. aceite campo (°C)',
    'ambient_temp_c'    => 'Temp. ambiente campo (°C)',
    'relative_humidity' => 'Humedad relativa campo (%HR)',
    'notes'         => 'Observaciones internas',
    'notes_help'    => 'No salen impresas.',

    'show_in_report' => 'Mostrar en el informe',
    'test'           => 'Ensayo',
    'not_validated'  => 'Sin validar: no sale impreso aunque quede marcado.',
    'no_tests'       => 'La muestra todavía no tiene ensayos pedidos.',

    'no_equipment' => 'La muestra no tiene equipo asignado, así que la cabecera del informe sale sin los datos del transformador. Se asigna desde la pestaña de muestras.',
];
