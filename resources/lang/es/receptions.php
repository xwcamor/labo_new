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
    'index_subtitle' => 'Las entregas de muestras que llegan al laboratorio, con su avance.',
    'create_title'   => 'Registrar recepción',
    'create_subtitle'=> 'Registre la entrega de muestras que llegó al laboratorio.',
    'edit_title'     => 'Editar recepción',
    'show_title'     => 'Recepción — Detalle',
    'trash_title'    => 'Papelera de recepciones',
    'empty_hint'     => 'Registre la primera entrega de muestras para empezar.',

    // ── Cabecera ────────────────────────────────────────────────────────
    'section_header'  => 'Datos de la entrega',
    'code'            => 'N° de recepción',
    'code_help'       => 'Asignado por el sistema.',
    'code_auto'       => 'Se genera al guardar',
    'service_order'   => 'Orden de servicio',
    'customer'        => 'Cliente',
    'customer_help'   => 'De quién son las muestras. Los equipos que se puedan asignar salen de este cliente.',
    'sampler'         => 'Muestreador',
    'sampler_help'    => 'Quién tomó las muestras. Elija de la lista, o escriba el nombre en el campo de al lado.',
    'sampler_name'    => 'Muestreador (externo)',
    'authorized_by'      => 'Autoriza el ingreso de la muestra',
    'authorized_by_help' => 'Quién del laboratorio acepta la entrega. La lista se administra en «Personal que autoriza».',
    'authorized_by_empty' => 'La lista está vacía. Agregue personas en el módulo «Personal que autoriza».',
    'received_at'     => 'Fecha de recepción',
    'received_at_help'=> 'El día en que la muestra llegó al laboratorio.',
    'due_at'          => 'Fecha comprometida',
    'due_at_help'     => 'Para cuándo se compromete el resultado.',
    'days_left'       => 'Días restantes',
    'service_order_pending' => 'Pendiente',
    'packages'        => 'Envases recibidos',
    'packages_help'   => 'Cuántos frascos o envases trae la entrega.',
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
    'confirm'         => 'Confirmar muestras',
    'confirm_help'    => 'Indique cuántas muestras trae la entrega. Al confirmar, cada una recibe su número y queda lista para asignarle equipo y pruebas.',
    'how_many'        => '¿Cuántas muestras trae la entrega?',
    'next_number'     => 'La próxima muestra será la :code',
    'will_issue'      => 'Se crearán :count muestras, con los números :from a :to.',
    'confirmed'       => 'Listo: se registraron :count muestras.',
    'confirmed_at'    => 'Confirmada el',
    // Corregir la cantidad después de confirmar («puse 32 y eran 20»).
    'adjust'          => 'Corregir la cantidad',
    'adjust_title'    => '¿Cuántas muestras eran en realidad?',
    'adjust_help'     => 'Se puede corregir porque los números de esta entrega son los últimos emitidos del año. Si eran menos, las muestras sobrantes se quitan y sus números vuelven a estar disponibles (no deben tener nada cargado). Si eran más, las nuevas se agregan con los números siguientes.',
    'adjusted'        => 'Cantidad corregida: :added agregada(s), :removed quitada(s).',
    'confirmed_by'    => 'Confirmada por',
    'no_samples_yet'  => 'Esta entrega todavía no tiene muestras registradas.',

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
    // El resumen de la celda plegada. Hasta cuatro pruebas se muestran solas;
    // una campaña que pide veinte o cien convertía la fila en una pared de
    // etiquetas, así que pasado ese número la celda se resume y se despliega a
    // pedido — la misma idea del "N muestras registradas" del sistema anterior.
    'tests_count'      => '{1} 1 prueba pedida|[2,*] :count pruebas pedidas',
    'progress'         => 'Avance',
    'outstanding'      => 'Pendientes',

    // El semáforo de avance del listado: los cuatro chequeos que el sistema
    // anterior mostraba como iconos por fila (Series · Trabajos · Datos ·
    // Informes), derivados en la consulta del listado.
    // La etapa de la barra de avance de cada muestra (el semáforo). Cuatro y
    // no tres: "a medio ensayar" es el estado más frecuente de la semana y
    // meterlo en rojo o en amarillo mentiría en las dos direcciones.
    'stage_not_started'     => 'Sin ensayos cargados',
    'stage_in_progress'     => 'Ensayos en curso',
    'stage_awaiting_report' => 'Ensayada — falta emitir el informe',
    'stage_reported'        => 'Informada',
    // Lo que falta, en palabras, al lado de la barra. La cuenta ("5/6") dice
    // cuánto, no QUÉ: con 5 de 6 hay que abrir la fila para saber si falta un
    // ensayo o si ya está todo ensayado y lo que falta es emitir el informe, y
    // son dos trabajos de dos personas distintas.
    'missing_load'          => 'Sin cargar',
    'missing_tests_n'       => '{1} Falta 1 ensayo|[2,*] Faltan :count ensayos',
    'missing_report'        => 'Falta el informe',

    'prog_equipment'         => 'Equipo',
    'prog_equipment_pending' => ':count muestra(s) todavia sin equipo enlazado. Su resultado se informa igual; lo que falta es la tendencia por equipo y los limites por clase de tension. Si la muestra viene de un cilindro o un envase suelto, no hay nada que enlazar.',
    'prog_equipment_done'    => 'Todas las muestras indican su equipo.',
    'prog_tests'             => 'Pruebas',
    'prog_tests_pending'     => ':count muestra(s) sin ningún ensayo pedido.',
    'prog_tests_done'        => 'Todas las muestras tienen sus ensayos pedidos.',
    'prog_data'              => 'Datos',
    'prog_data_pending'      => ':count ensayo(s) sin cargar o a medio cargar en la bancada.',
    'prog_data_done'         => 'Todos los ensayos pedidos están cargados.',
    'prog_reports'           => 'Informes',
    'prog_reports_pending'   => ':count muestra(s) sin informe emitido.',
    'prog_reports_done'      => 'Todas las muestras tienen su informe emitido.',
    'report'      => 'Informe',
    'report_help' => 'Abre el informe de ensayo de esta muestra en PDF. Solo incluye los ensayos ya validados.',

    // ── Baja de una muestra ───────────────────────────────────────────────
    'delete_sample'         => 'Dar de baja la muestra',
    'delete_sample_reason'  => 'Motivo de la baja',
    'delete_sample_confirm' => 'La muestra :code deja de figurar en la entrega. Su correlativo NO se reutiliza: nunca se le va a asignar a otra muestra.',
    'delete_sample_has_work' => 'Esta muestra ya tiene resultados cargados. Darla de baja los saca del sistema; si lo que quiere es corregir un valor, use la hoja de bancada.',
    'sample_deleted'        => 'Muestra :code dada de baja.',
    // El aviso de la baja mientras «Corregir la cantidad» sigue disponible.
    'delete_vs_adjust'      => 'Si lo que pasó es que la cantidad estaba mal, use «Corregir la cantidad»: ahí el número queda disponible para la próxima entrega. Por acá el número se anula para siempre y la corrección de cantidad se cierra para esta entrega.',
    'delete_blocked' => [
        'issued_report' => 'La muestra :code no se puede dar de baja: ya salió un informe con ese número y el cliente lo tiene en la mano. El portal de verificación tiene que seguir encontrándolo. Para corregir, emita un informe adicional.',
        'has_issued' => 'Esta entrega no se puede dar de baja: tiene :count informe(s) ya emitido(s). El cliente los tiene en la mano y el portal de verificación tiene que seguir encontrándolos. Para corregir, emita informes adicionales.',
    ],
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
    // Con la CUENTA: "hay muestras sin equipo" sobre una entrega de 200 no
    // dice nada; "3 muestras sin equipo" sí.
    'missing_equipment_count' => '{1} 1 muestra sin equipo asignado|[2,*] :count muestras sin equipo asignado',
    'missing_tests_count'     => '{1} 1 muestra sin pruebas pedidas|[2,*] :count muestras sin pruebas pedidas',
    'nothing_missing'    => 'La recepción está completa.',

    // La descarga en Excel de la entrega y el aviso de la baja.
    'download_hint' => 'Descarga las muestras de esta entrega en Excel, con su equipo, lo que se les pidió y en qué van.',
    'delete_confirmed_warning' => 'Esta entrega ya emitió sus números de muestra.',
    'delete_confirmed_detail'  => 'Darla de baja NO devuelve los correlativos: esos :count números no se le asignan nunca a otra muestra. Los resultados que ya tengan sus muestras salen del sistema junto con ellas.',

    'created' => 'Recepción registrada.',
    'saved'   => 'Recepción actualizada.',
    'deleted' => 'Recepción eliminada.',
    // La baja masiva desde el listado: lo que se saltó y por qué.
    'bulk_skipped_issued' => ':count con informe emitido se omitieron (el cliente los tiene en la mano).',
    'bulk_none_deleted'   => 'No se eliminó ninguna: :locked bloqueada(s) por candado y :issued con informe emitido.',
    'bulk_cascade_note'   => 'La baja arrastra las muestras y los informes de cada entrega, y los números de muestra ya emitidos NO se devuelven. Las entregas con informes emitidos y las bloqueadas por candado se omiten solas.',

    'errors' => [
        'not_draft'  => 'La recepción ya fue confirmada. Los correlativos se emiten una sola vez.',
        'no_samples' => 'Indique cuántas muestras trae la entrega.',
        'unknown_test' => 'Alguna de las pruebas elegidas no existe. Vuelva a cargar la pantalla.',
        'equipment_not_of_customer' => 'Ese equipo no es del cliente de esta recepción.',
        'confirmed_no_edit' => 'Una recepción confirmada no cambia de cliente ni de fecha: sus correlativos ya están emitidos.',
        'adjust_not_tail' => 'Ya se emitieron números de muestra después de esta entrega (o alguna muestra fue dada de baja), así que la cantidad ya no se puede corregir. Para quitar, dé de baja las muestras una por una; para agregar, registre otra entrega.',
        'adjust_has_work' => 'La muestra :code ya tiene trabajo cargado y no se puede quitar con esta corrección. Revise la bancada, o dela de baja individualmente.',
    ],
    // Los dos datos de la cabecera del informe que se cargan al RECIBIR, no al
    // emitir: quien recibe la muestra tiene el correo del cliente delante.
    'contact_info'      => 'Contacto',
    'contact_info_help' => 'Correo o telefono a quien se le avisa del informe. Sale impreso en la cabecera.',
    'end_user'          => 'Usuario final',
    'end_user_help'     => 'De quien es el equipo, cuando no es el cliente que envia. Una contratista manda muestras del transformador de la minera.',
];
