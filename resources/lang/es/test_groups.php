<?php

return [
    'singular'      => 'Grupo de pruebas',
    'plural'        => 'Grupos de pruebas',
    'record'        => 'grupo de pruebas',
    'records'       => 'grupos de pruebas',
    'new'           => 'Crear grupo de pruebas',
    'id'            => 'N°',

    'index_title'    => 'Grupos de pruebas',
    'index_subtitle' => 'Categorías en las que se agrupan las pruebas del laboratorio.',
    'create_title'   => 'Crear grupo de pruebas',
    'create_subtitle'=> 'Complete los datos para crear un nuevo grupo de pruebas.',
    'edit_title'     => 'Editar grupo de pruebas',
    'delete_title'   => 'Eliminar grupo de pruebas',
    'show_title'     => 'Grupo de pruebas — Información',
    'trash_title'    => 'Papelera de grupos de pruebas',
    'form_create_hint' => 'Complete los datos para crear un nuevo grupo de pruebas.',
    'empty_hint'      => 'Cree el primer grupo de pruebas o importe un lote desde Excel para empezar.',
    'name_placeholder' => 'Ej: Físico Químico, Cromatografía, Otros',
    'code_placeholder' => 'Ej: fisico_quimico',

    'name'      => 'Nombre',
    'name_help' => 'Nombre del grupo tal como se ve en el menú de pruebas y en el informe (ej: Físico Químico).',
    'code'      => 'Código',
    'code_help' => 'Identificador técnico del grupo (ej: fisico_quimico). Es único en todo el sistema y no debería cambiar una vez que hay pruebas colgando de él.',
    'sort_order' => 'Orden',
    'sort_order_help' => 'Posición del grupo en el menú de pruebas y en el informe. Menor primero.',
    'is_active' => 'Estado',
    'is_active_help' => 'Si está inactivo, el grupo no aparecerá al clasificar una prueba nueva.',
    'filter_name' => 'Nombre',

    // ── Pruebas del grupo (ficha) ───────────────────────────────────────
    'tests'           => 'Pruebas del grupo',
    'tests_count'     => 'Pruebas',
    'tests_empty'     => 'Todavía no hay pruebas en este grupo.',
    'tests_hint'      => 'Las pruebas se crean y se editan desde el módulo Pruebas.',
    'go_to_tests'     => 'Ver todas las pruebas',

    'edit_hint'   => 'Modificar este registro',
    'delete_hint' => 'Eliminar (queda en papelera)',
    'restore_hint'=> 'Volverá a estar disponible en el listado principal.',

    'created' => 'Grupo de pruebas creado.',
    'saved'   => 'Grupo de pruebas actualizado.',
    'deleted' => 'Grupo de pruebas eliminado.',

    'delete_about'                 => 'Va a eliminar ":name". Quedará en papelera.',
    'delete_has_tests'             => 'Este grupo tiene pruebas asociadas: quedarán sin grupo hasta que se les asigne otro.',
    'deleted_description_required' => 'Indique el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Export
    'export_filename'           => 'exportacion_grupos_de_pruebas',
    'import_template_filename'  => 'plantilla-grupos-de-pruebas.xlsx',
    'export_title'              => 'Reporte de Grupos de Pruebas',
    'export_limit_exceeded'     => 'La exportación en :format excede el límite (:count filas contra :limit como máximo). Use CSV para conjuntos grandes (sin límite).',
    'export_format_limit_hint'  => 'Máximo :limit filas para este formato. Use CSV para conjuntos grandes.',
    'export_no_limit_hint'      => 'Sin límite — recomendado para conjuntos grandes.',

    // Validation
    'name_required'            => 'El campo nombre es obligatorio.',
    'code_required'            => 'El código del grupo es obligatorio.',
    'code_unique'              => 'Ya existe un grupo de pruebas con este código.',
    'is_active_required'       => 'El campo estado es obligatorio.',
    'import_super_blocked'     => 'Un super sin workspace asignado no puede importar (la búsqueda por código podría actualizar registros de otro workspace).',

    // Edit All
    'edit_all_title'    => 'Grupos de pruebas — Editar Todo',
    'edit_all_subtitle' => 'Edite el orden, el nombre y el estado de varios grupos a la vez. Use "Guardar todo" para confirmar y "Descartar cambios" para deshacer.',
    'edit_all_changes'  => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all' => 'Guardar todo',
    'edit_all_discard'  => 'Descartar cambios',
    'edit_all_no_results' => 'No hay grupos de pruebas que coincidan con el filtro.',

    'table_headers' => [
        'editable_name'   => 'Nombre (editable)',
        'editable_status' => 'Estado (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Bienvenido a Grupos de pruebas',
        'step1_body'  => 'Son las categorías con las que se ordenan las pruebas del laboratorio: Físico Químico, Cromatografía y Otros. Le mostramos los puntos clave en menos de 1 minuto.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busque y filtre por nombre, código, estado y fechas. Los filtros activos aparecen sobre la tabla.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarde su combinación favorita de filtros, columnas y orden, y aplíquela después con un clic. Cada usuario tiene las suyas.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestre u oculte columnas; su elección se recuerda. Las marcadas como obligatorias no se pueden ocultar.',
        'step5_title' => 'Exportar e importar',
        'step5_body'  => 'Exporte a Excel, PDF o Word en segundo plano; se le avisa cuando esté listo. Importe desde Excel o CSV con vista previa antes de confirmar.',
        'step6_title' => 'Editar varios a la vez',
        'step6_body'  => '"Editar todo" permite modificar nombre y estado de varios grupos juntos y confirmarlos en un solo guardado.',
        'step7_title' => 'Favoritos',
        'step7_body'  => 'La estrella marca un registro como favorito. Los favoritos aparecen siempre arriba del listado y cada usuario tiene los suyos.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Seleccione filas con las casillas y aparece una barra para activar, desactivar o eliminar.',
        'step9_title' => '¿Necesita un repaso?',
        'step9_body'  => 'Reabra este recorrido cuando quiera con el botón ? de arriba. También tiene "Recientes" en el menú del avatar.',
    ],
];
