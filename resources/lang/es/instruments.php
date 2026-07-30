<?php

return [
    'singular'      => 'Instrumento',
    'plural'        => 'Instrumentos',
    'record'        => 'instrumento',
    'records'       => 'instrumentos',
    'new'           => 'Crear instrumento',
    'id'            => 'N°',

    'index_title'    => 'Instrumentos',
    'index_subtitle' => 'Equipamiento de bancada y estado de su calibración.',
    'create_title'   => 'Crear instrumento',
    'create_subtitle'=> 'Complete los datos del equipo y de su última calibración.',
    'edit_title'     => 'Editar instrumento',
    'delete_title'   => 'Eliminar instrumento',
    'show_title'     => 'Instrumento — Información',
    'trash_title'    => 'Papelera de instrumentos',
    'form_create_hint' => 'Complete los datos del equipo y de su última calibración.',
    'empty_hint'      => 'Cree el primer instrumento o importe el inventario desde Excel para empezar.',
    'name_placeholder'        => 'Ej: PP-LA-01C-100',
    'description_placeholder' => 'Ej: Bureta digital de 10 mL, clase A',

    // ── Secciones del formulario y de la ficha ──────────────────────────
    'section_identification' => 'Identificación',
    'section_calibration'    => 'Calibración',
    'section_extra'          => 'Datos adicionales',

    // El NOMBRE es el código de calibración: así se llama el equipo en el
    // laboratorio, es lo que el analista elige en la bancada y lo que hace
    // trazable el resultado. La DESCRIPCIÓN es el tipo de equipo y se repite.
    'name'      => 'Nombre',
    'name_help' => 'Con qué nombre identifica el laboratorio al equipo: el mismo código que figura en su certificado de calibración (ej: PP-LA-01C-100). No se repite dentro del laboratorio.',
    'description'      => 'Descripción',
    'description_help' => 'Qué equipo es (ej: Bureta, Balanza analítica). Puede repetirse: tres buretas se describen las tres igual, y por eso no alcanza para identificarlas.',
    'brand'     => 'Marca',
    'brand_help'=> 'Fabricante del equipo (ej: Mettler Toledo, Megger).',
    'model'     => 'Modelo',
    'model_help'=> 'Modelo del fabricante.',
    'serial'    => 'Número de serie',
    'serial_help' => 'Serie grabada en el equipo. Es lo que lo distingue de otro idéntico.',

    'calibrated_at'      => 'Fecha de calibración',
    'calibrated_at_help' => 'Fecha del certificado de la última calibración.',
    'calibration_due_at' => 'Vence el',
    'calibration_due_at_help' => 'Fecha hasta la que la calibración es válida. Sin esta fecha no se puede saber si el equipo sirve para ensayar, y el estado queda como "sin fecha".',
    'calibration_certificate' => 'Certificado',
    'calibration_certificate_help' => 'Número o referencia del certificado de calibración.',
    'location'  => 'Ubicación',
    'location_help' => 'Dónde está físicamente el equipo (ej: Laboratorio de aceites, Sala 2).',
    'notes'     => 'Observaciones',
    'notes_help'=> 'Notas internas del equipo: incidencias, mantenimiento, restricciones de uso.',

    'sort_order' => 'Orden',
    'sort_order_help' => 'Posición del equipo en los selectores. Menor primero.',
    'tests'      => 'Prueba',
    'tests_none' => 'Sin prueba asignada',
    'tests_help' => 'En qué pruebas se ofrece este instrumento. Sale de las columnas de la plantilla: la bancada solo ofrece los instrumentos de cada columna.',
    'is_active' => 'Estado',
    'is_active_help' => 'Si está inactivo, el instrumento no aparecerá para elegir en las hojas de trabajo.',
    'filter_name' => 'Nombre o descripción',

    // ── Estado de calibración ───────────────────────────────────────────
    'calibration_status'  => 'Calibración',
    'cal_status_valid'    => 'Vigente',
    'cal_status_due_soon' => 'Por vencer',
    'cal_status_expired'  => 'Vencida',
    'cal_status_unknown'  => 'Sin fecha',
    'cal_days_left'       => '{1} Queda 1 día|[2,*] Quedan :count días',
    'cal_days_overdue'    => '{1} Vencida hace 1 día|[2,*] Vencida hace :count días',
    'cal_due_today'       => 'Vence hoy',
    'cal_never'           => 'Sin calibración registrada',
    'cal_warning_days'    => 'Se avisa :days días antes del vencimiento.',
    'cal_expired_warning' => 'Este instrumento tiene la calibración vencida. Un ensayo corrido con él no es trazable.',
    'cal_unknown_warning' => 'Este instrumento no tiene fecha de vencimiento de calibración cargada. No se puede afirmar que estuviera calibrado el día del ensayo.',

    'edit_hint'   => 'Modificar este registro',
    'delete_hint' => 'Eliminar (queda en papelera)',
    'restore_hint'=> 'Volverá a estar disponible en el listado principal.',

    'created' => 'Instrumento creado.',
    'saved'   => 'Instrumento actualizado.',
    'deleted' => 'Instrumento eliminado.',

    'delete_about'                 => 'Va a eliminar ":name". Quedará en papelera.',
    'deleted_description_required' => 'Indique el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Export
    'export_filename'           => 'exportacion_instrumentos',
    'import_template_filename'  => 'plantilla-instrumentos.xlsx',
    'export_title'              => 'Reporte de Instrumentos',
    'export_limit_exceeded'     => 'La exportación en :format excede el límite (:count filas contra :limit como máximo). Use CSV para conjuntos grandes (sin límite).',
    'export_format_limit_hint'  => 'Máximo :limit filas para este formato. Use CSV para conjuntos grandes.',
    'export_no_limit_hint'      => 'Sin límite — recomendado para conjuntos grandes.',

    // Validation
    'name_required'            => 'El nombre del instrumento es obligatorio.',
    'name_unique'              => 'Ya existe un instrumento con este nombre.',
    'due_before_calibrated'    => 'El vencimiento no puede ser anterior a la fecha de calibración.',
    'is_active_required'       => 'El campo estado es obligatorio.',
    'import_super_blocked'     => 'Un super sin workspace asignado no puede importar (la búsqueda por nombre podría actualizar registros de otro workspace).',

    // Edit All
    'edit_all_title'    => 'Instrumentos — Editar Todo',
    'edit_all_subtitle' => 'Edite el nombre y el estado de varios instrumentos a la vez. La descripción y la calibración se editan en la ficha de cada equipo.',
    'edit_all_changes'  => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all' => 'Guardar todo',
    'edit_all_discard'  => 'Descartar cambios',
    'edit_all_no_results' => 'No hay instrumentos que coincidan con el filtro.',

    'table_headers' => [
        'editable_name'   => 'Nombre (editable)',
        'editable_status' => 'Estado (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Bienvenido a Instrumentos',
        'step1_body'  => 'Acá vive el equipamiento de bancada del laboratorio y el estado de su calibración. Le mostramos los puntos clave en menos de 1 minuto.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busque por nombre, descripción, marca o ubicación, y filtre por estado de calibración: vigente, por vencer o vencida.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarde su combinación favorita de filtros, columnas y orden, y aplíquela después con un clic. Cada usuario tiene las suyas.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestre u oculte columnas; su elección se recuerda. Las marcadas como obligatorias no se pueden ocultar.',
        'step5_title' => 'Exportar e importar',
        'step5_body'  => 'Exporte a Excel, PDF o Word en segundo plano; se le avisa cuando esté listo. Importe el inventario desde Excel o CSV con vista previa antes de confirmar.',
        'step6_title' => 'Editar varios a la vez',
        'step6_body'  => '"Editar todo" permite modificar nombre y estado de varios equipos juntos y confirmarlos en un solo guardado.',
        'step7_title' => 'Favoritos',
        'step7_body'  => 'La estrella marca un equipo como favorito. Los favoritos aparecen siempre arriba del listado y cada usuario tiene los suyos.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Seleccione filas con las casillas y aparece una barra para activar, desactivar o eliminar. Los lotes grandes se procesan en segundo plano.',
        'step9_title' => '¿Necesita un repaso?',
        'step9_body'  => 'Reabra este recorrido cuando quiera con el botón ? de arriba. También tiene "Recientes" en el menú del avatar.',
    ],
];
