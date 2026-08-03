<?php

/*
|--------------------------------------------------------------------------
| Bitácora de condiciones ambientales
|--------------------------------------------------------------------------
| El «Control de Temperaturas» del sistema anterior, que allá eran dos módulos
| gemelos (cromatografía y fisicoquímico). Acá la sala es un dato.
*/

return [
    'title'    => 'Condiciones ambientales',
    'subtitle' => 'Bitácora diaria de las salas del laboratorio',
    'new'      => 'Registrar lectura',
    'edit'     => 'Editar lectura',

    'room'          => 'Sala',
    'logged_on'     => 'Fecha',
    'temperature_c' => 'Temperatura (°C)',
    'humidity_pct'  => 'Humedad relativa (%HR)',
    'pressure_hpa'  => 'Presión atmosférica (hPa)',
    'notes'         => 'Observaciones',

    'rooms' => [
        'chromatography'  => 'Sala de cromatografía',
        'physicochemical' => 'Sala de fisicoquímico',
    ],

    'from' => 'Desde',
    'to'   => 'Hasta',

    'missing_today' => 'Falta la lectura de hoy',
    'all_today'     => 'Las lecturas de hoy están cargadas.',

    'created' => 'Lectura registrada.',
    'saved'   => 'Lectura actualizada.',
    'deleted' => 'Lectura eliminada.',

    'errors' => [
        // Una lectura por sala y por día: dos lecturas del mismo día no dicen
        // cuál valía cuando se corrió el ensayo.
        'duplicate_day' => 'Esa sala ya tiene la lectura de ese día. Edite la que está en vez de cargar otra.',
        'future'        => 'No se puede registrar una lectura de un día que todavía no ocurrió.',
    ],
];
