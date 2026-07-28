<?php

return [
    'singular'      => 'Recepción',
    'plural'        => 'Recepciones',
    'record'        => 'recepción',
    'records'       => 'recepciones',
    'new'           => 'Registrar recepción',
    'id'            => 'N°',

    'search_sample'  => 'Buscar por N° de muestra',
    'urgent_only'    => 'Solo urgentes',

    'index_title'    => 'Recepción de muestras',
    'index_subtitle' => 'Lo que entra al laboratorio. De acá salen los correlativos y de acá cuelga cada muestra con su equipo.',
    'create_title'   => 'Registrar recepción',
    'create_subtitle'=> 'Los datos de la entrega. Los correlativos se emiten al confirmar, no ahora.',
    'edit_title'     => 'Editar recepción',
    'show_title'     => 'Recepción — Detalle',
    'trash_title'    => 'Papelera de recepciones',
    'empty_hint'     => 'Registre la primera entrega de muestras para empezar.',

    // ── Cabecera ────────────────────────────────────────────────────────
    'section_header'  => 'Datos de la entrega',
    'code'            => 'N° de recepción',
    'code_help'       => 'El número con el que el laboratorio identifica la entrega. No es el de las muestras.',
    'service_order'   => 'Orden de servicio',
    'customer'        => 'Cliente',
    'customer_help'   => 'De quién son las muestras. Los equipos que se puedan asignar salen de este cliente.',
    'sampler'         => 'Muestreador',
    'sampler_help'    => 'Quién tomó las muestras. Puede ser alguien del laboratorio o del cliente.',
    'sampler_name'    => 'Muestreador (externo)',
    'received_at'     => 'Fecha de recepción',
    'received_at_help'=> 'El año del correlativo sale de esta fecha, no del día en que se confirma.',
    'due_at'          => 'Fecha comprometida',
    'packages'        => 'Envases recibidos',
    'is_urgent'       => 'Urgente',
    'notes'           => 'Observaciones',

    // ── Verificación del envase ─────────────────────────────────────────
    'section_check'  => 'Verificación al recibir',
    'check_help'     => 'Lo que se mira antes de aceptar la muestra. Si algo falla, el ensayo puede quedar observado y tiene que constar.',
    'container_ok'   => 'Envase adecuado',
    'volume_ok'      => 'Volumen suficiente',
    'label_ok'       => 'Datos de la etiqueta completos',

    // ── Confirmación y correlativos ─────────────────────────────────────
    'section_samples' => 'Muestras',
    'confirm'         => 'Confirmar y emitir correlativos',
    'confirm_help'    => 'Al confirmar se emiten los correlativos y la recepción deja de ser un borrador. Un número emitido no se reutiliza, aunque después se dé de baja la muestra.',
    'how_many'        => '¿Cuántas muestras trae la entrega?',
    'next_number'     => 'El próximo correlativo es :code',
    'will_issue'      => 'Se emitirán :count correlativos, del :from al :to.',
    'confirmed'       => 'Recepción confirmada. Se emitieron :count correlativos.',
    'confirmed_at'    => 'Confirmada el',
    'confirmed_by'    => 'Confirmada por',
    'no_samples_yet'  => 'Todavía no se emitieron correlativos para esta entrega.',

    // ── Estados ─────────────────────────────────────────────────────────
    'status'            => 'Estado',
    'status_draft'      => 'Borrador',
    'status_confirmed'  => 'Confirmada',
    'status_closed'     => 'Cerrada',
    'status_cancelled'  => 'Anulada',

    'sample_status_pending'     => 'Pendiente',
    'sample_status_in_progress' => 'En proceso',
    'sample_status_completed'   => 'Ensayada',
    'sample_status_reported'    => 'Informada',

    'test_status_pending'     => 'Pendiente',
    'test_status_in_progress' => 'En proceso',
    'test_status_validated'   => 'Validada',
    'test_status_reported'    => 'Informada',
    'test_status_cancelled'   => 'Dada de baja',

    // ── Muestra ─────────────────────────────────────────────────────────
    'sample'           => 'Muestra',
    'samples'          => 'Muestras',
    'sample_code'      => 'N° de muestra',
    'equipment'        => 'Equipo',
    'equipment_help'   => 'De qué equipo se tomó. Sin esto el resultado no se puede informar ni graficar.',
    'no_equipment'     => 'Sin equipo asignado',
    'oil_type'         => 'Tipo de fluido',
    'sampled_at'       => 'Fecha de toma',
    'sampling_point'   => 'Punto de muestreo',
    'container'        => 'Envase',
    'requested_tests'  => 'Pruebas pedidas',
    'no_tests'         => 'Sin pruebas pedidas',
    'progress'         => 'Avance',
    'outstanding'      => 'Pendientes',
    'report'      => 'Informe',
    'report_help' => 'Abre el informe de ensayo de esta muestra en PDF. Solo incluye los ensayos ya validados.',
    'assign_tests'     => 'Asignar pruebas',
    'assign_to_all'    => 'Aplicar a todas las muestras',
    'assign_to_all_hint' => 'Lo que se marque REEMPLAZA el pedido de todas las muestras de esta entrega. Las pruebas que ya tienen trabajo hecho se conservan.',
    'tests_of_sample'  => 'Pruebas de la muestra :code',
    'tests_of_all'     => 'Pruebas para todas las muestras',
    // Encabezado del bloque de pruebas sin grupo asignado en el catálogo.
    'tests_no_group'   => 'Sin grupo',
    'tests_saved'      => 'Pruebas actualizadas: :added agregadas, :cancelled dadas de baja.',
    'equipment_saved'  => 'Equipo asignado.',

    // ── Lo que falta ────────────────────────────────────────────────────
    'missing'            => 'Falta',
    'missing_equipment'  => 'Hay muestras sin equipo asignado.',
    'missing_tests'      => 'Hay muestras sin pruebas pedidas.',
    'nothing_missing'    => 'La recepción está completa.',

    'created' => 'Recepción registrada.',
    'saved'   => 'Recepción actualizada.',
    'deleted' => 'Recepción eliminada.',

    'errors' => [
        'not_draft'  => 'La recepción ya fue confirmada. Los correlativos se emiten una sola vez.',
        'no_samples' => 'Indique cuántas muestras trae la entrega.',
        'unknown_test' => 'Alguna de las pruebas elegidas no existe. Vuelva a cargar la pantalla.',
        'equipment_not_of_customer' => 'Ese equipo no es del cliente de esta recepción.',
        'confirmed_no_edit' => 'Una recepción confirmada no cambia de cliente ni de fecha: sus correlativos ya están emitidos.',
    ],
];
