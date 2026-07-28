<?php

return [

    'title'    => 'Archivos de instrumento',
    'singular' => 'Archivo de instrumento',

    'intro'    => 'El archivo crudo que emite el equipo de medición. Se guarda tal como se subió y se interpreta con el formato que corresponda; los valores encontrados se precargan en la hoja para que el analista los confirme.',

    'format'        => 'Formato',
    'original_name' => 'Archivo',
    'size'          => 'Tamaño',
    'rows_parsed'   => 'Valores encontrados',
    'status'        => 'Estado',

    'state' => [
        'uploaded' => 'Subido',
        'parsed'   => 'Interpretado',
        'failed'   => 'Sin coincidencias',
    ],

    'upload'   => 'Subir archivo del instrumento',
    'unmatched' => 'No se encontró en el archivo: :fields',
    'confirm'  => 'Revise los valores antes de guardarlos. El archivo solo precarga el formulario.',

    'errors' => [
        'nothing_matched' => 'No se encontró ninguno de los valores configurados. Revise que el formato elegido corresponda a este equipo.',
        'not_editable'    => 'La hoja no admite cambios.',
    ],

];
