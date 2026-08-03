<?php

/*
|--------------------------------------------------------------------------
| Préstamos del almacén
|--------------------------------------------------------------------------
| El «Seguimiento de Equipos» del sistema anterior. Acá el prestatario es
| obligatorio, no se presta más de lo disponible y no se devuelve más de lo
| que falta — las tres cosas que allá no se controlaban.
*/

return [
    'title'    => 'Préstamos del almacén',
    'subtitle' => 'Quién se llevó qué, y qué falta volver',
    'singular' => 'Préstamo',

    'new'    => 'Nuevo préstamo',
    'edit'   => 'Editar préstamo',
    'search' => 'Buscar por prestatario o motivo',

    // Cabecera
    'loaned_on'    => 'Fecha del préstamo',
    'borrower'     => 'Prestatario',
    'borrower_help' => 'Quién se lleva el material: un usuario del sistema, o el nombre escrito si es alguien de afuera. Es obligatorio — sin esto el registro no sirve para dar con lo prestado.',
    'borrower_user' => 'Usuario',
    'borrower_name' => 'Externo',
    'borrower_name_placeholder' => 'Nombre de quien se lo lleva',
    'purpose'      => 'Motivo',
    'purpose_placeholder' => 'Para qué se lleva el material',
    'created_by'   => 'Registró',

    // Líneas
    'lines'      => 'Artículos prestados',
    'item'       => 'Artículo',
    'qty'        => 'Cantidad',
    'notes'      => 'Comentario',
    'add_line'   => 'Agregar artículo',
    'remove_line' => 'Quitar',
    'available_n' => 'disponible: :n',

    // Estado
    'status'     => 'Estado',
    'status_open'     => 'Falta devolver',
    'status_returned' => 'Devuelto',
    'pending'    => 'Pendiente',
    'returned'   => 'Devuelto',
    'returned_at' => 'Cerrado el',

    // Devoluciones
    'returns'        => 'Devoluciones',
    'new_return'     => 'Registrar devolución',
    'returned_on'    => 'Fecha de devolución',
    'return_qty'     => 'Cantidad devuelta',
    'no_returns'     => 'Sin devoluciones todavía.',
    'return_delete_confirm' => '¿Dar de baja esta devolución? El préstamo vuelve a quedar abierto por esa cantidad.',

    'empty'          => 'Todavía no hay préstamos registrados.',
    'delete_confirm' => '¿Dar de baja este préstamo? Se va con sus líneas y sus devoluciones.',

    'created'        => 'Préstamo registrado.',
    'saved'          => 'Préstamo actualizado.',
    'deleted'        => 'Préstamo dado de baja.',
    'return_saved'   => 'Devolución registrada.',
    'return_deleted' => 'Devolución dada de baja.',

    'header_note' => 'Solo se corrigen los datos de cabecera. Si las líneas están mal, se da de baja el préstamo y se vuelve a cargar: cambiar una cantidad ya devuelta dejaría devoluciones colgando de un número que no existe.',

    'errors' => [
        'borrower_required'  => 'Falta decir quién se lleva el material: un usuario o un nombre.',
        'over_available'     => 'No hay tantas unidades de «:item»: quedan :n disponibles.',
        'over_return'        => 'No se puede devolver más de lo que falta: quedan :n unidades por volver.',
        'return_before_loan' => 'La devolución no puede ser anterior al préstamo.',
        'future_loan'        => 'El préstamo no puede tener fecha futura.',
        'future_return'      => 'La devolución no puede tener fecha futura.',
    ],
];
