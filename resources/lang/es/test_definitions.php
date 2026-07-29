<?php

return [
    'singular'      => 'Prueba',
    'plural'        => 'Pruebas',
    'record'        => 'prueba',
    'records'       => 'pruebas',
    'new'           => 'Crear prueba',
    'id'            => 'N°',

    'index_title'    => 'Pruebas',
    'index_subtitle' => 'Las pruebas que corre el laboratorio y cómo se comporta su hoja de trabajo.',
    'create_title'   => 'Crear prueba',
    'create_subtitle'=> 'Complete los datos de la prueba. Las columnas de su hoja de trabajo se definen después, desde la ficha.',
    'edit_title'     => 'Editar prueba',
    'delete_title'   => 'Eliminar prueba',
    'show_title'     => 'Prueba — Información',
    'trash_title'    => 'Papelera de pruebas',
    'form_create_hint' => 'Complete los datos de la prueba.',
    'empty_hint'      => 'Cree la primera prueba o importe las del sistema anterior para empezar.',
    'name_placeholder' => 'Ej: Número Ácido, Cromatografía, Furanos',
    'code_placeholder' => 'Ej: numero_acido',

    // ── Secciones del formulario y de la ficha ──────────────────────────
    'section_identification' => 'Identificación',
    'section_sampling'       => 'Muestra y presentación',
    'section_control'        => 'Control de calidad de la hoja',
    'section_traceability'   => 'Trazabilidad',

    'name'      => 'Nombre',
    'name_help' => 'Nombre de la prueba tal como se ve en el menú y en el informe (ej: Número Ácido).',
    'code'      => 'Código',
    'code_help' => 'Identificador técnico de la prueba (ej: numero_acido). Es único en todo el sistema; si se deja vacío se deriva del nombre.',
    'group'     => 'Grupo',
    'group_help'=> 'Categoría a la que pertenece la prueba: Físico Químico, Cromatografía u Otros. Ordena el menú y las secciones del informe.',
    'group_none'=> 'Sin grupo',
    'description' => 'Descripción',
    'description_help' => 'Para qué sirve la prueba o cómo se corre. Es texto libre para el analista, no se usa en ningún cálculo.',
    'container' => 'Envase',
    'container_help' => 'Envase en el que se debe recibir la muestra para esta prueba (ej: jeringa de vidrio, frasco ámbar).',
    'report_comment_group' => 'Tabla del informe',
    'report_comment_group_help' => 'Con qué otras pruebas comparte tabla en el informe. Las trece fisicoquímicas van juntas en una sola página, como en el informe acreditado; la cromatografía y los furanos tienen la suya. Vacío = página propia.',
    'report_comment_group_own' => 'Página propia',
    'chart_unit'=> 'Unidad del gráfico',
    'chart_unit_help' => 'Rótulo del eje en los gráficos de tendencia (ej: ppm, mg KOH/g, kV).',

    // ── Banderas de control de calidad ──────────────────────────────────
    'control_intro' => 'El patrón control verifica que el método esté midiendo bien y el duplicado, que el resultado sea repetible. Si se exigen, la hoja no se puede cerrar sin ellos.',
    'has_control'         => 'Corre con patrón control',
    'has_control_help'    => 'La prueba admite filas de patrón control en su hoja de trabajo.',
    'requires_control'    => 'Exige patrón control',
    'requires_control_help' => 'La hoja no acepta muestras hasta que tenga cargado al menos un patrón control.',
    'requires_duplicate'  => 'Exige duplicado',
    'requires_duplicate_help' => 'La hoja no acepta muestras hasta que tenga cargado al menos un duplicado.',
    'is_grouped'          => 'Agrupada',
    'is_grouped_help'     => 'Marca de la importación. No decide nada: quien manda son las dos casillas de arriba.',

    'replicates'      => 'Repeticiones',
    'replicates_help' => 'Cuántas veces se mide la MISMA muestra para promediar. La rigidez dieléctrica se mide 5 o 6 veces; el resto de las pruebas usa 1.',

    'legacy_id'      => 'Id en el sistema anterior',
    'legacy_id_help' => 'Identificador de origen de la prueba. Permite volver a importar sin duplicar y rastrear los datos históricos. Es de solo lectura.',

    'sort_order' => 'Orden',
    'sort_order_help' => 'Posición de la prueba dentro de su grupo. Menor primero.',
    'is_active' => 'Estado',
    'is_active_help' => 'Si está inactiva, la prueba no se puede pedir en una muestra nueva. Las hojas ya cargadas no se tocan.',
    'filter_name' => 'Nombre',

    // ── Columnas de la hoja de trabajo (ficha) ──────────────────────────
    'fields'        => 'Columnas de la hoja de trabajo',
    'fields_hint'   => 'Las columnas que el analista completa al correr la prueba, y cuáles de ellas son un resultado.',
    'fields_empty'  => 'Esta prueba todavía no tiene columnas definidas.',
    'fields_count'  => 'Columnas',
    'results_count' => 'Resultados',

    'edit_hint'   => 'Modificar este registro',
    'delete_hint' => 'Eliminar (queda en papelera)',
    'restore_hint'=> 'Volverá a estar disponible en el listado principal.',

    'created' => 'Prueba creada.',
    'saved'   => 'Prueba actualizada.',
    'deleted' => 'Prueba eliminada.',

    'delete_about'                 => 'Va a eliminar ":name". Quedará en papelera.',
    'delete_has_fields'            => 'Se eliminan también las columnas de su hoja de trabajo.',
    'deleted_description_required' => 'Indique el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Export
    'export_filename'           => 'exportacion_pruebas',
    'import_template_filename'  => 'plantilla-pruebas.xlsx',
    'export_title'              => 'Reporte de Pruebas',
    'export_limit_exceeded'     => 'La exportación en :format excede el límite (:count filas contra :limit como máximo). Use CSV para conjuntos grandes (sin límite).',
    'export_format_limit_hint'  => 'Máximo :limit filas para este formato. Use CSV para conjuntos grandes.',
    'export_no_limit_hint'      => 'Sin límite — recomendado para conjuntos grandes.',

    // Validation
    'name_required'            => 'El campo nombre es obligatorio.',
    'code_required'            => 'El código de la prueba es obligatorio.',
    'code_unique'              => 'Ya existe una prueba con este código.',
    'group_invalid'            => 'El grupo seleccionado no existe.',
    'is_active_required'       => 'El campo estado es obligatorio.',
    'import_super_blocked'     => 'Un super sin workspace asignado no puede importar (la búsqueda por código podría actualizar registros de otro workspace).',

    // Edit All
    'edit_all_title'    => 'Pruebas — Editar Todo',
    'edit_all_subtitle' => 'Edite el nombre y el estado de varias pruebas a la vez. El grupo y las banderas de control se editan en la ficha de cada prueba.',
    'edit_all_changes'  => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all' => 'Guardar todo',
    'edit_all_discard'  => 'Descartar cambios',
    'edit_all_no_results' => 'No hay pruebas que coincidan con el filtro.',

    'table_headers' => [
        'editable_name'   => 'Nombre (editable)',
        'editable_status' => 'Estado (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Bienvenido a Pruebas',
        'step1_body'  => 'Son los ensayos que corre el laboratorio y la definición de su hoja de trabajo. Le mostramos los puntos clave en menos de 1 minuto.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busque y filtre por nombre, código, grupo, estado y las banderas de patrón control y duplicado.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarde su combinación favorita de filtros, columnas y orden, y aplíquela después con un clic. Cada usuario tiene las suyas.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestre u oculte columnas; su elección se recuerda. Las marcadas como obligatorias no se pueden ocultar.',
        'step5_title' => 'Exportar e importar',
        'step5_body'  => 'Exporte a Excel, PDF o Word en segundo plano; se le avisa cuando esté listo. Importe desde Excel o CSV con vista previa antes de confirmar.',
        'step6_title' => 'Editar varias a la vez',
        'step6_body'  => '"Editar todo" permite modificar nombre y estado de varias pruebas juntas y confirmarlas en un solo guardado.',
        'step7_title' => 'Favoritos',
        'step7_body'  => 'La estrella marca un registro como favorito. Los favoritos aparecen siempre arriba del listado y cada usuario tiene los suyos.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Seleccione filas con las casillas y aparece una barra para activar, desactivar o eliminar.',
        'step9_title' => '¿Necesita un repaso?',
        'step9_body'  => 'Reabra este recorrido cuando quiera con el botón ? de arriba. También tiene "Recientes" en el menú del avatar.',
    ],
];
