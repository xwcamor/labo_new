<?php

return [
    'title'    => 'Ambient conditions',
    'subtitle' => 'Daily log of the laboratory rooms',
    'new'      => 'Log a reading',
    'edit'     => 'Edit reading',

    'room'          => 'Room',
    'logged_on'     => 'Date',
    'temperature_c' => 'Temperature (°C)',
    'humidity_pct'  => 'Relative humidity (%RH)',
    'pressure_hpa'  => 'Atmospheric pressure (hPa)',
    'notes'         => 'Notes',

    'rooms' => [
        'chromatography'  => 'Chromatography room',
        'physicochemical' => 'Physicochemical room',
    ],

    'from' => 'From',
    'to'   => 'To',

    'missing_today' => "Today's reading is missing",
    'all_today'     => "Today's readings are logged.",

    'created' => 'Reading logged.',
    'saved'   => 'Reading updated.',
    'deleted' => 'Reading deleted.',

    'errors' => [
        'duplicate_day' => 'That room already has a reading for that day. Edit the existing one instead of adding another.',
        'future'        => 'A reading cannot be logged for a day that has not happened yet.',
    ],
];
