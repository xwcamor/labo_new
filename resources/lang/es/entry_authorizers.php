<?php

// El «Personal de Laboratorio» del sistema anterior (`rem_user_signatures`):
// las personas del laboratorio que AUTORIZAN el ingreso de muestras. Es un
// catálogo propio — NO son los firmantes de informes (módulo Firmas).
return [
    'singular'      => 'Personal que autoriza',
    'plural'        => 'Personal que autoriza',
    'record'        => 'persona',
    'records'       => 'personas',
    'new'           => 'Agregar persona',
    'id'            => 'N°',

    'index_title'    => 'Personal que autoriza ingresos',
    'index_subtitle' => 'Quiénes del laboratorio pueden autorizar el ingreso de muestras. Son los que ofrece el formulario de recepción.',
    'create_title'   => 'Agregar persona',
    'create_subtitle'=> 'Nombre completo y su firma. La firma sale en el acta de recepción.',
    'edit_title'     => 'Editar persona',
    'delete_title'   => 'Eliminar persona',
    'show_title'     => 'Personal que autoriza — Información',
    'trash_title'    => 'Papelera de personal que autoriza',
    'form_create_hint' => 'Nombre completo y su firma.',
    'empty_hint'      => 'Agregue a la primera persona del laboratorio que autoriza ingresos.',
    'name_placeholder' => 'Nombre y apellidos',

    'name'      => 'Nombre completo',
    'name_help' => 'Como se imprime en el acta de recepción.',
    'code'      => 'Código',
    'code_help' => 'Identificador técnico interno. Se genera del nombre si se deja vacío.',
    'image'      => 'Firma',
    'image_help' => 'La firma escaneada o dibujada. Sin ella, el acta deja la línea para firmar a mano.',
    'no_image'   => 'Sin firma cargada',
    'sort_order' => 'Orden',
    'is_active' => 'Estado',
    'is_active_help' => 'Si está inactivo, no aparece en el formulario de recepción.',
    'filter_name' => 'Nombre',

    'edit_hint'   => 'Modificar este registro',
    'delete_hint' => 'Eliminar (queda en papelera)',
    'restore_hint'=> 'Volverá a estar disponible en el listado principal.',

    'created' => 'Persona agregada.',
    'saved'   => 'Persona actualizada.',
    'deleted' => 'Persona eliminada.',

    'delete_about'                 => 'Va a eliminar ":name". Quedará en papelera.',
    'deleted_description_required' => 'Indica el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Export
    'export_filename'           => 'exportacion_personal_autoriza',
    'import_template_filename'  => 'plantilla-personal-autoriza.xlsx',
    'export_title'              => 'Personal que autoriza ingresos',
    'export_limit_exceeded'     => 'El export en :format excede el límite (:count filas vs :limit máximo). Usa CSV para datasets grandes (sin límite).',
    'export_format_limit_hint'  => 'Máximo :limit filas para este formato. Usa CSV para datasets grandes.',
    'export_no_limit_hint'      => 'Sin límite — recomendado para datasets grandes.',

    // Validation
    'name_required'            => 'El nombre completo es obligatorio.',
    'name_unique'              => 'Esta persona ya está en el catálogo.',
    'code_unique'              => 'Ya existe una persona con este código.',
    'name_duplicate_in_batch'  => 'Nombre duplicado dentro del mismo batch.',
    'is_active_required'       => 'El campo estado es obligatorio.',
    'import_super_blocked'     => 'Un super sin workspace asignado no puede importar (el match por nombre podría actualizar registros de otro workspace).',

    // Edit All
    'edit_all_title'    => 'Personal que autoriza — Editar Todo',
    'edit_all_subtitle' => 'Edita nombre y estado de varias personas a la vez. Click "Guardar todo" para confirmar, "Cancelar" para descartar.',
    'edit_all_changes'  => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all' => 'Guardar todo',
    'edit_all_discard'  => 'Descartar cambios',
    'edit_all_no_results' => 'No hay personas que coincidan con el filtro.',

    'table_headers' => [
        'editable_name'   => 'Nombre (editable)',
        'editable_status' => 'Estado (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Personal que autoriza ingresos',
        'step1_body'  => 'Las personas del laboratorio que pueden autorizar el ingreso de muestras. Este catálogo alimenta el formulario de recepción.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busca y filtra por nombre, estado y fechas. Los filtros activos aparecen como chips arriba de la tabla.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarda tu combinación favorita de filtros + columnas + orden y aplícala después con un clic. Cada usuario tiene las suyas propias.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestra/oculta columnas y se recuerda tu elección. Las marcadas como "obligatorias" no se pueden ocultar.',
        'step5_title' => 'Exportar & Importar',
        'step5_body'  => 'Exporta a Excel/PDF/Word en segundo plano — se te notificará cuando esté listo. Importa desde Excel/CSV con vista previa antes de confirmar.',
        'step6_title' => 'Editar muchos a la vez',
        'step6_body'  => '"Editar todo" permite modificar nombre y estado de varios registros juntos. Después se confirman todos los cambios en un solo guardado.',
        'step7_title' => 'Favoritos ★',
        'step7_body'  => 'La estrella ★ marca un registro como favorito. Los favoritos aparecen siempre arriba del listado y cada usuario tiene los suyos.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Selecciona filas con los checkboxes — aparece una barra para activar, desactivar, eliminar o restaurar. Funciona con cientos de filas; los lotes grandes se procesan en segundo plano.',
        'step9_title' => '¿Necesitas un repaso?',
        'step9_body'  => 'Reabre este tour cuando quieras con el botón ? aquí arriba. También tienes "Recientes" en el menú del avatar — los últimos registros que viste en cualquier módulo.',
    ],
];
