<?php

/*
|--------------------------------------------------------------------------
| Etiquetas de los envases de muestra
|--------------------------------------------------------------------------
| Las que se pegan al frasco. NO son las etiquetas de la interfaz: son papel.
| El pliego se arma en `SampleLabelController` y se imprime en A4.
*/

return [
    'sheet_title' => 'Etiquetas de muestra',

    'print'       => 'Imprimir etiquetas',
    'print_one'   => 'Imprimir la etiqueta de :code',
    'print_help'  => 'Arma un pliego A4 con la etiqueta de cada muestra de la entrega, con su código y su QR.',

    'equipment'   => 'Equipo',
    'oil'         => 'Aceite',
    'sampled'     => 'Muestreo',
    'received'    => 'Recepción',
    'urgent'      => 'URGENTE',
];
