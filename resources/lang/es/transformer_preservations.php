<?php

/*
|--------------------------------------------------------------------------
| Sistemas de preservación del aceite
|--------------------------------------------------------------------------
| Conservador con membrana, tanque sellado con nitrógeno, respiración libre.
| Metadato descriptivo del equipo que sale impreso en la ficha del informe.
*/

return [
    'title'    => 'Sistemas de preservación',
    'subtitle' => 'Cómo se protege del aire el aceite del transformador',
    'singular' => 'Sistema de preservación',

    'new'    => 'Nuevo sistema',
    'edit'   => 'Editar sistema',
    'search' => 'Buscar por nombre o código',

    'name'   => 'Nombre',
    'code'   => 'Código',
    'order'  => 'Orden',
    'in_use' => 'Equipos que lo usan',
    'active' => 'Activo',
    'active_help' => 'Inactivo deja de ofrecerse en los equipos nuevos. Los que ya lo tienen no cambian.',

    'empty'          => 'Todavía no hay sistemas de preservación cargados.',
    'delete_confirm' => '¿Dar de baja este sistema de preservación?',

    'created' => 'Sistema de preservación creado.',
    'saved'   => 'Sistema de preservación actualizado.',
    'deleted' => 'Sistema de preservación dado de baja.',

    'errors' => [
        'duplicate' => 'Ya existe un sistema de preservación con ese nombre.',
        'in_use'    => 'No se puede dar de baja: :n equipos lo tienen asignado. Para sacarlo de circulación, desactivarlo.',
    ],
];
