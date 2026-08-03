<?php

return [
    'title' => 'Listas del informe',
    'intro' => 'Las listas chicas del sistema: las cuatro que llenan el formulario del informe y la unidad del almacén. Antes eran texto libre, y por eso la misma unidad quedó escrita de tres formas distintas.',

    'frozen_note' => 'Lo que se guarda en la muestra es el TEXTO elegido, no la fila del catálogo: corregir o dar de baja una opción acá no cambia ningún informe ya emitido.',

    // Las cinco listas
    'kind' => [
        'sampling_reason' => 'Razón de análisis',
        'sampling_point'  => 'Punto de muestreo',
        'oil_brand'       => 'Marca de aceite',
        'volume_unit'     => 'Unidad de volumen',
        'stock_unit'      => 'Unidad de almacén',
    ],

    'hint' => [
        'sampling_reason' => 'Por qué se pidió el análisis: rutina, evento, tratamiento, cambio de aceite.',
        'sampling_point'  => 'De dónde se extrajo la muestra del equipo: válvula inferior, media, superior.',
        'oil_brand'       => 'La marca comercial del aceite (Nynas, Shell), distinta del tipo de aceite.',
        'volume_unit'     => 'En qué se mide la cantidad de aceite del equipo: L, Gl, Kg, Lb, Cil.',
        'stock_unit'      => 'En qué se cuenta un artículo del almacén: unidad, frasco, litro, kilo, caja.',
    ],

    // Columnas y acciones
    'name'        => 'Nombre',
    'order'       => 'Orden',
    'active'      => 'Activa',
    'active_help' => 'Desactivada deja de ofrecerse en las muestras nuevas. Los informes emitidos no cambian.',
    'add'         => 'Agregar',
    'new_placeholder' => 'Nombre de la nueva opción',
    'empty'       => 'Esta lista todavía no tiene opciones.',
    'delete_confirm' => '¿Eliminar esta opción? Si ya se usó en alguna muestra, conviene desactivarla en vez de eliminarla.',

    // Mensajes
    'created' => 'Opción agregada.',
    'saved'   => 'Opción actualizada.',
    'deleted' => 'Opción eliminada.',
];
