<?php

return [
    // Items del menú lateral
    'dashboard'          => 'Dashboard',

    // Grupo: Accesos
    'group_access'       => 'Accesos',
    'users'              => 'Usuarios',
    'roles'              => 'Perfiles',
    'permissions'        => 'Permisos',

    // Grupo: Negocio (operación)
    'group_business'     => 'Negocio',
    'customers'          => 'Clientes',
    'equipment'          => 'Equipos',

    // Grupo: Pruebas de Muestras. Los nombres siguen el vocabulario del
    // laboratorio y no el del sistema viejo: allá el menú decía "Módulos" para
    // referirse a las pruebas y "Muestras" para las hojas de trabajo, que es
    // otra cosa (una hoja agrupa muchas muestras del mismo día).
    'group_lab'          => 'Pruebas de Muestras',
    'receptions'         => 'Recepción de muestras',
    'worksheets'         => 'Hojas de trabajo',
    'qc_charts'          => 'Cartas de control',
    'test_definitions'   => 'Pruebas',
    'test_groups'        => 'Grupos de pruebas',
    'analytes'           => 'Parámetros',
    'instruments'        => 'Instrumentos',
    'samplers'           => 'Muestreadores',

    // Catálogos del motor de diagnóstico. La clave faltaba y el menú
    // mostraba "SIDEBAR.GROUP_DIAGNOSTICS" en crudo.
    'group_diagnostics'  => 'Condiciones de diagnóstico',

    'oil_types'          => 'Tipos de aceite',
    'equipment_types'  => 'Tipos de transformador',
    'brands'             => 'Marcas',
    'tap_changer_types'  => 'Tipos de conmutador',
    'laboratories'       => 'Laboratorios',
    'tap_changer_technologies' => 'Tecnología de conmutador',
    'tap_changer_models' => 'Modelo de conmutador',
    'tap_changer_brands' => 'Marca de conmutador',

    // Grupo: Automatizaciones (planes enterprise)
    'group_automation'   => 'Automatizaciones',
    'automations'        => 'Automatizaciones',

    // Grupo: Comunicación
    'group_communication' => 'Comunicación',
    'messages'            => 'Mensajes',
    'inbox'               => 'Bandeja',

    // Grupo: Auditoría. El botón del toolbar usa la misma etiqueta que el
    // item del sidebar para que el usuario sepa que es lo mismo.
    'group_audit'        => 'Logs del sistema',
    'audit_logs'         => 'Logs del sistema',

    // Grupo: Configuración del sistema
    'group_system'       => 'Configuración del sistema',
    'workspace'          => 'Mi workspace',
    'tenants'            => 'Workspaces',
    'plans'              => 'Planes',
    'system_modules'     => 'Módulos',
    'regions'            => 'Regiones',
    'languages'          => 'Idiomas',
    'countries'          => 'Países',
    'locales'            => 'Locales',
    'settings'           => 'Ajustes',


    // Tooltips
    'coming_soon'        => 'Próximamente',
    'report_shares'      => 'Envíos de informes',
];
