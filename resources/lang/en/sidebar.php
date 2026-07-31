<?php

return [
    // Sidebar items
    'dashboard'          => 'Dashboard',

    // Group: Access
    'group_access'       => 'Access',
    'users'              => 'Users',
    'roles'              => 'Roles',
    'permissions'        => 'Permissions',

    // Group: Business (operations)
    'group_business'     => 'Business',
    'customers'          => 'Customers',
    'equipment'          => 'Equipment',

    // Group: Sample testing — the heart of the laboratory.
    'group_lab'          => 'Sample testing',
    'receptions'         => 'Sample reception',
    'worksheets'         => 'Worksheets',
    'sample_reports'     => 'Test reports',
    'qc_charts'          => 'Control charts',

    // What the laboratory configures about itself. Split out of "Sample
    // testing": that group had ten items and only three are opened daily.
    'group_lab_setup'    => 'Laboratory setup',
    'test_definitions'   => 'Tests',
    'test_groups'        => 'Test groups',
    'analytes'           => 'Parameters',
    'instruments'        => 'Instruments',
    'samplers'           => 'Samplers',
    'signatures'         => 'Signatures',

    // Diagnostic engine catalogues. The key was missing and the menu showed
    // the raw "SIDEBAR.GROUP_DIAGNOSTICS".
    'group_diagnostics'  => 'Diagnostic settings',

    'oil_types'          => 'Oil types',
    'equipment_types'  => 'Transformer types',
    'brands'             => 'Brands',
    'tap_changer_types'  => 'Tap changer types',
    'laboratories'       => 'Laboratories',
    'tap_changer_technologies' => 'Tap changer technology',
    'tap_changer_models' => 'Tap changer model',
    'tap_changer_brands' => 'Tap changer brand',

    // Group: Automations (enterprise plans)
    'group_automation'   => 'Automations',
    'automations'        => 'Automations',

    // Group: Communication
    'group_communication' => 'Communication',
    'messages'            => 'Messages',
    'inbox'               => 'Inbox',

    // Group: Audit. Toolbar button uses the same label as the sidebar item
    // so the user knows it's the same page.
    'group_audit'        => 'System Logs',
    'audit_logs'         => 'System Logs',

    // Group: System configuration
    'group_system'       => 'System configuration',
    'workspace'          => 'My workspace',
    'tenants'            => 'Workspaces',
    'plans'              => 'Plans',
    'system_modules'     => 'Modules',
    'regions'            => 'Regions',
    'languages'          => 'Languages',
    'countries'          => 'Countries',
    'locales'            => 'Locales',
    'settings'           => 'Settings',


    // Tooltips
    'coming_soon'        => 'Coming soon',
    'report_shares'      => 'Report shares',
    'diagnosis_templates' => 'Analysis templates',
    'report_catalogs'    => 'Report lists',
];
