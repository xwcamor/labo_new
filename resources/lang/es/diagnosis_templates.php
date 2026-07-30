<?php

return [

    // El editor de la redaccion que el informe imprime por familia de ensayo.
    // Antes cada frase era un `if` en una vista del sistema anterior; despues un
    // archivo del repositorio que exigia un despliegue. Ahora es una fila.
    'title' => 'Plantillas del analisis de resultados',
    'intro' => 'El parrafo que el informe imprime por cada familia de ensayo. Se edita aca y sale en el papel del cliente.',
    'empty' => 'Todavia no hay plantillas sembradas. Corra el seeder de plantillas de analisis.',

    // Quien edita que. Se dice ANTES de que alguien escriba.
    'scope_super' => 'Esta editando el estandar de fabrica: el cambio aplica a todos los laboratorios que no tengan su propia redaccion.',
    'scope_admin' => 'Al guardar una plantilla de fabrica se crea una copia de este laboratorio con su cambio. El estandar no se toca, y "Restaurar" vuelve a el.',
    'overridden_count' => 'Este laboratorio tiene :count plantilla(s) con redaccion propia. Esas ya no siguen los cambios del estandar.',

    'factory'          => 'De fabrica',
    'overridden'       => 'Redaccion propia',
    'overridden_hint'  => 'Esta plantilla la personalizo este laboratorio. Ya no sigue los cambios del estandar.',
    'by_analyte'       => 'Segun el valor de :analyte',

    'case' => [
        'none' => 'Sin resultados fuera de norma',
        'one'  => 'Un resultado fuera de norma',
        'many' => 'Varios resultados fuera de norma',
        'any'  => 'Cualquier caso',
    ],

    'family' => [
        'fisicoquimico' => 'Fisicoquimico',
        'analisis_cromatografico' => 'Analisis cromatografico',
        'pcb' => 'PCB',
        'furanos' => 'Furanos',
        'particulas' => 'Particulas',
        'azufre_corrosivo' => 'Azufre corrosivo',
        'sedimentos' => 'Sedimentos',
        'metales_en_aceite' => 'Metales en aceite',
        'viscocidad' => 'Viscosidad',
        'dbds' => 'DBDS',
        'inflamacion' => 'Punto de inflamacion',
        'fluidez' => 'Punto de fluidez',
        'inhibidor' => 'Contenido de inhibidor',
        'grado_de_polimerizacion' => 'Grado de polimerizacion',
        'pasivador' => 'Contenido de pasivador',
    ],

    // Los marcadores que el motor reemplaza al redactar.
    'markers_title'  => 'Marcadores que se reemplazan al redactar',
    'marker_ok'      => 'los ensayos dentro de norma',
    'marker_failed'  => 'los ensayos fuera de norma',
    'marker_norm'    => 'la norma del criterio, tomada del resultado',
    'marker_value'   => 'el valor medido',

    'body_placeholder' => 'Texto de la plantilla. Puede usar los marcadores de arriba.',

    // Las bandas: un texto por tramo de valor. Reemplazan a los cuatro `if`
    // seguidos del sistema anterior, con los cortes escritos en el codigo.
    'bands_title' => 'Tramos por valor',
    'bands_hint'  => 'Dejar vacio el minimo o el maximo para un tramo abierto. Un valor tiene que caer en un solo tramo.',
    'band_min'    => 'Desde',
    'band_max'    => 'Hasta',
    'band_body'   => 'Texto de este tramo',
    'add_band'    => 'Agregar tramo',

    'origin' => 'Procedencia',

    'restore'         => 'Restaurar',
    'restore_confirm' => 'Se borra la redaccion propia de este laboratorio y vuelve la de fabrica. Los informes que se emitan despues usaran el texto estandar.',
    'restored'        => 'Plantilla restaurada a la redaccion de fabrica.',
    'restored_reason' => 'Restaurada a la redaccion de fabrica desde el editor.',
    'saved'           => 'Plantilla guardada.',

    'errors' => [
        'empty'           => 'La plantilla necesita un texto o al menos un tramo: sin ninguno de los dos, esa familia saldria sin parrafo en el informe.',
        'no_tenant'       => 'Su usuario no pertenece a un laboratorio, asi que no hay donde guardar una redaccion propia.',
        'factory_restore' => 'La plantilla de fabrica no se restaura desde aca: para volver al texto original del repositorio hay que correr el seeder.',
    ],

];
