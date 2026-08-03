<?php

/*
|--------------------------------------------------------------------------
| Reportes de Lab. — los 7 Excel del sistema antiguo
|--------------------------------------------------------------------------
| Menú "Reportes de Lab." del Rails viejo, portado. Cada reporte conserva
| sus columnas; lo corregido a propósito está anotado en su clase Export.
*/

return [
    'title'    => 'Reportes de Lab.',
    'subtitle' => 'Descargas en Excel sobre la fecha de recepción',
    'download' => 'Descargar Excel',
    'from'     => 'Fecha de recepción desde',
    'to'       => 'Fecha de recepción hasta',
    'no_records' => 'Sin registros en el rango.',

    // Comunes
    'yes'            => 'Sí',
    'no'             => 'No',
    'pending'        => 'Pendiente',
    'service_order'  => 'N° de orden de servicio',
    'customer'       => 'Cliente',
    'sample_code'    => 'N° de muestra',
    'serial'         => 'N° de serie del equipo',
    'report_number'  => 'N° de informe',
    'date_rec'       => 'Fecha de recepción',
    'date_delivered' => 'Fecha de entrega',
    'reason'         => 'Razón de análisis',
    'status'         => 'Estado',
    'state_draft'     => 'Borrador',
    'state_issued'    => 'Emitido',
    'state_delivered' => 'Entregado',

    'reports' => [
        'otd'    => ['name' => 'Reporte OTD (On Time Delivery)', 'desc' => 'Una fila por informe con los tres plazos: recepción→entrega (máx. 5 días), recepción→emisión (máx. 2) y emisión→entrega (máx. 3), con semáforo por columna.'],
        'rlabs'  => ['name' => 'Análisis de Laboratorio', 'desc' => 'Una fila por muestra con todos sus resultados: fisicoquímico, cromatografía y los demás ensayos, más fechas, cliente y equipo.'],
        'rems'   => ['name' => 'Registro de Muestras Detallado', 'desc' => 'Una ficha por recepción: datos del ingreso, listado de muestras con su avance y los informes emitidos.'],
        'fims'   => ['name' => 'Formato de Registro de Ingreso de Muestras', 'desc' => 'Una fila por recepción: fechas, cliente, muestras por prueba, estado de los envases y quién autorizó el ingreso.'],
        'jobs'   => ['name' => 'Registro de Muestras', 'desc' => 'Una fila por muestra con su avance en Sí/No: equipo, pruebas, valores e informe.'],
        'ents'   => ['name' => 'Reportes Entregados', 'desc' => 'Una fila por informe principal emitido, con su fecha de entrega y estado.'],
        'listado' => ['name' => 'Listado de Reportes', 'desc' => 'El inventario de informes principales en cualquier estado, con número, muestra, cliente y fechas.'],
    ],

    'otd' => [
        'sheet'          => 'Reporte On Time Delivery',
        'date_rec'       => 'Fecha de ingreso',
        'date_issued'    => 'Fecha de emisión',
        'date_delivered' => 'Fecha de entrega',
        'otd_days'       => 'OTD (días)',
        'otd_ok'         => 'OTD correcto (máx. :max días)',
        'issue_days'     => 'Tiempo para emitir (días)',
        'issue_ok'       => 'Emisión correcta (máx. :max días)',
        'delivery_days'  => 'Tiempo de entrega (días)',
        'delivery_ok'    => 'Entrega correcta (máx. :max días)',
    ],

    'rlabs' => [
        'sheet'          => 'Análisis de laboratorio',
        'date_rec'       => 'Fecha de recepción',
        'date_due'       => 'Fecha estimada de entrega de resultados',
        'date_delivered' => 'Fecha de entrega del informe',
        'fluid'          => 'Tipo de fluido dieléctrico',
        'fiqui'          => 'Fisicoquímico',
        'chromatography' => 'Cromatografía',
        'tests' => [
            'pcb'            => 'PCB',
            'furano'         => 'Furano (2-FAL)',
            'azufre_1275b'   => 'Azufre 1275B',
            'azufre_48'      => 'Azufre 62535 (48 horas)',
            'azufre_72'      => 'Azufre 62535 (72 horas)',
            'polimerizacion' => 'Grado de polimerización',
            'viscosidad'     => 'Viscosidad',
            'particulas'     => 'Partículas (código ISO)',
            'metales'        => 'Metales',
            'inhibidor'      => 'Inhibidor',
            'dbds'           => 'DBDS',
            'sedimentos'     => 'Sedimentos',
            'fluidez'        => 'Fluidez',
            'inflamacion'    => 'Inflamación',
            'pasivador'      => 'Pasivador',
        ],
    ],

    'fims' => [
        'sheet'         => 'Registro de ingreso de muestras',
        'date_rec'      => 'Fecha de recepción',
        'date_due'      => 'Fecha de entrega comprometida',
        'days_left'     => 'Días restantes',
        'sampled_by'    => 'Muestra extraída por',
        'tests_group'   => 'Muestras por prueba',
        'packages'      => 'Total de envases',
        'state_group'   => 'Estado de muestras',
        'container_ok'  => 'Envases adecuados',
        'volume_ok'     => 'Volumen adecuado',
        'label_ok'      => 'Rotulado correcto',
        'notes'         => 'Observaciones',
        'authorized_by' => 'Autoriza el ingreso',
        'families' => [
            'fiqui'          => 'Fisicoquímico',
            'cromas'         => 'Cromatografía',
            'pcb'            => 'PCB',
            'furanos'        => 'Furanos',
            'azufre'         => 'Azufre corrosivo',
            'polimerizacion' => 'Grado de polimerización',
            'viscosidad'     => 'Viscosidad',
            'particulas'     => 'Partículas',
            'metales'        => 'Metales',
            'inhibidor'      => 'Inhibidor',
            'dbds'           => 'DBDS',
            'sedimentos'     => 'Sedimentos',
            'fluidez'        => 'Fluidez',
            'inflamacion'    => 'Inflamación',
            'pasivador'      => 'Pasivador',
        ],
    ],

    'jobs' => [
        'sheet'          => 'Listado de muestras',
        'date_rec'       => 'Fecha de recepción',
        'sampled_at'     => 'Fecha de muestreo',
        'tests'          => 'Pruebas asignadas',
        'run_dates'      => 'Fecha de ensayo',
        'has_equipment'  => 'Equipo asignado',
        'has_tests'      => 'Pruebas asignadas (Sí/No)',
        'has_values'     => 'Valores cargados',
        'has_report'     => 'Informe creado',
        'priority'       => 'Importancia',
        'priority_high'  => 'Máxima prioridad',
        'priority_normal' => 'Normal',
        'date_due'       => 'Fecha estimada de realización',
        'no_equipment'   => 'Pendiente de asignar',
    ],

    'rems' => [
        'sheet'         => 'Registro detallado',
        'block_title'   => 'Datos de ingreso de la muestra',
        'samples_title' => 'Listado de muestras',
        'reports_title' => 'Listado de informes',
        'entered_at'    => 'Fecha de ingreso',
    ],

    'ents' => [
        'sheet' => 'Entrega de informes',
    ],

    'listado' => [
        'sheet' => 'Listado de informes',
    ],
];
