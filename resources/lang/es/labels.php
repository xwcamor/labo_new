<?php

/*
|--------------------------------------------------------------------------
| Etiquetas de los envases de muestra
|--------------------------------------------------------------------------
| Las que se pegan al frasco. NO son las etiquetas de la interfaz: son papel.
|
| Es un menú aparte, como el "Control de Stickers" del sistema anterior: quien
| imprime etiquetas va a imprimir etiquetas y nada más. La maqueta imprimible
| (`labels/sticker.blade.php`) replica sus medidas al pixel — la impresora de
| etiquetas del laboratorio está calibrada contra ellas.
*/

return [
    'title'        => 'Etiquetas de muestra',
    'intro'        => 'La etiqueta que se pega al envase: número de muestra, fecha y su código QR. Elija las muestras y se imprime una etiqueta por cada una, con las medidas del sistema anterior.',
    'menu'         => 'Etiquetas',

    'print'        => 'Imprimir',
    'print_selected' => 'Imprimir seleccionadas',
    'print_one'    => 'Imprimir la etiqueta de :code',
    'print_help'   => 'Abre la hoja imprimible con una etiqueta por muestra elegida.',
    'print_count'  => '{1} 1 etiqueta|[2,*] :count etiquetas',

    // Los tres textos de la etiqueta impresa, con las mismas palabras del
    // sistema anterior: el laboratorio las lee de memoria en el envase.
    'sample_no'    => 'Nº Muestra:',
    'date'         => 'Fecha:',
    'comment'      => 'Coment.:',

    'comment_field'=> 'Comentario de la etiqueta',
    'comment_hint' => 'Opcional. Sale en TODAS las etiquetas de esta tanda (por ejemplo, «recontramuestra»).',

    'search'       => 'Buscar por N° de muestra',
    'empty'        => 'No hay muestras para etiquetar.',
    'customer'     => 'Cliente',
    'equipment'    => 'Equipo',
    'reception'    => 'Entrega',
    'received'     => 'Recepción',
];
