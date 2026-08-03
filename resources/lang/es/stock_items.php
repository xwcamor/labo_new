<?php

/*
|--------------------------------------------------------------------------
| Artículos del almacén
|--------------------------------------------------------------------------
| Reactivos, material de vidrio, equipos prestables. El sistema anterior los
| llamaba «stocks» y los listaba con una columna «Stock» que era el número
| tipeado a mano, sin decir cuánto de eso estaba prestado.
*/

return [
    'title'    => 'Artículos del almacén',
    'subtitle' => 'Qué hay, cuánto está prestado y cuánto queda en el estante',
    'singular' => 'Artículo',

    'new'    => 'Nuevo artículo',
    'edit'   => 'Editar artículo',
    'search' => 'Buscar por código o nombre',

    // Columnas y campos
    'code'      => 'Código',
    'name'      => 'Nombre',
    'unit'      => 'Unidad',
    'on_hand'   => 'Existencia',
    'on_hand_help' => 'Lo que el laboratorio declara tener. No se descuenta solo al prestar: se corrige acá cuando se compra, se consume o se hace inventario.',
    'min_qty'   => 'Mínimo',
    'min_qty_help' => 'Punto de reposición. Con lo disponible en ese número o por debajo, el artículo queda marcado en el listado.',
    'location'  => 'Ubicación',
    'active'    => 'Activo',
    'active_help' => 'Inactivo deja de ofrecerse al armar un préstamo. Lo ya prestado sigue contando.',
    'on_loan'   => 'Prestado',
    'available' => 'Disponible',

    'low'      => 'Bajo mínimo',
    'low_hint' => 'Lo disponible llegó al punto de reposición.',

    'empty'          => 'Todavía no hay artículos cargados.',
    'delete_confirm' => '¿Dar de baja este artículo? Deja de ofrecerse en los préstamos nuevos.',

    'created' => 'Artículo creado.',
    'saved'   => 'Artículo actualizado.',
    'deleted' => 'Artículo dado de baja.',

    'errors' => [
        'on_loan' => 'No se puede dar de baja: hay :n unidades prestadas sin devolver. Primero se registra la devolución.',
    ],
];
