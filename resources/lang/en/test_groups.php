<?php

return [
    'singular'      => 'Test group',
    'plural'        => 'Test groups',
    'record'        => 'test group',
    'records'       => 'test groups',
    'new'           => 'Create test group',
    'id'            => 'No.',

    'index_title'    => 'Test groups',
    'index_subtitle' => 'Categories the laboratory tests are grouped into.',
    'create_title'   => 'Create test group',
    'create_subtitle'=> 'Fill in the data to create a new test group.',
    'edit_title'     => 'Edit test group',
    'delete_title'   => 'Delete test group',
    'show_title'     => 'Test group — Details',
    'trash_title'    => 'Test groups trash',
    'form_create_hint' => 'Fill in the data to create a new test group.',
    'empty_hint'      => 'Create your first test group or import a batch from Excel.',
    'name_placeholder' => 'E.g.: Physicochemical, Chromatography, Other',
    'code_placeholder' => 'E.g.: physicochemical',

    'name'      => 'Name',
    'name_help' => 'Group name as shown in the test menu and on the report (e.g. Physicochemical).',
    'code'      => 'Code',
    'code_help' => 'Technical identifier of the group (e.g. physicochemical). It is unique system-wide and should not change once tests hang from it.',
    'sort_order' => 'Order',
    'sort_order_help' => 'Position of the group in the test menu and on the report. Lower comes first.',
    'is_active' => 'Status',
    'is_active_help' => 'If inactive, the group will not be offered when classifying a new test.',
    'filter_name' => 'Name',

    // ── Tests in the group (detail page) ────────────────────────────────
    'tests'           => 'Tests in this group',
    'tests_count'     => 'Tests',
    'tests_empty'     => 'There are no tests in this group yet.',
    'tests_hint'      => 'Tests are created and edited from the Tests module.',
    'go_to_tests'     => 'View all tests',

    'edit_hint'   => 'Edit this record',
    'delete_hint' => 'Delete (goes to trash)',
    'restore_hint'=> 'Will go back to the main list.',

    'created' => 'Test group created.',
    'saved'   => 'Test group updated.',
    'deleted' => 'Test group deleted.',

    'delete_about'                 => 'You are about to delete ":name". It will go to the trash.',
    'delete_has_tests'             => 'This group has tests attached: they will be left without a group until another one is assigned.',
    'deleted_description_required' => 'Provide a reason for the deletion.',
    'deleted_description_min'      => 'Reason must be at least 3 characters.',
    'deleted_description_max'      => 'Reason cannot exceed 1000 characters.',

    // Export
    'export_filename'           => 'test_groups_export',
    'import_template_filename'  => 'test-groups-template.xlsx',
    'export_title'              => 'Test Groups Report',
    'export_limit_exceeded'     => 'The :format export exceeds the limit (:count rows vs :limit max). Use CSV for large datasets (no limit).',
    'export_format_limit_hint'  => 'Max :limit rows for this format. Use CSV for large datasets.',
    'export_no_limit_hint'      => 'No limit — recommended for large datasets.',

    // Validation
    'name_required'            => 'The name field is required.',
    'code_required'            => 'The group code is required.',
    'code_unique'              => 'A test group with this code already exists.',
    'is_active_required'       => 'The status field is required.',
    'import_super_blocked'     => 'A super without an assigned workspace cannot import (code matching could update records from another workspace).',

    // Edit All
    'edit_all_title'    => 'Test groups — Edit All',
    'edit_all_subtitle' => 'Edit the order, name and status of several groups at once. Use "Save all" to confirm and "Discard changes" to undo.',
    'edit_all_changes'  => '{0} No changes|{1} 1 pending change|[2,*] :count pending changes',
    'edit_all_save_all' => 'Save all',
    'edit_all_discard'  => 'Discard changes',
    'edit_all_no_results' => 'No test groups match the filter.',

    'table_headers' => [
        'editable_name'   => 'Name (editable)',
        'editable_status' => 'Status (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Welcome to Test groups',
        'step1_body'  => 'These are the categories the laboratory tests are sorted into: Physicochemical, Chromatography and Other. Quick tour in under a minute.',
        'step2_title' => 'Filters',
        'step2_body'  => 'Search and filter by name, code, status and dates. Active filters appear above the table.',
        'step3_title' => 'Saved views',
        'step3_body'  => 'Save your favorite filter + columns + sort combo and reapply with one click. Per-user.',
        'step4_title' => 'Columns',
        'step4_body'  => 'Show/hide columns; your choice persists. Required ones cannot be hidden.',
        'step5_title' => 'Export & Import',
        'step5_body'  => 'Export to Excel/PDF/Word in the background — you will be notified. Import from Excel/CSV with preview.',
        'step6_title' => 'Edit many at once',
        'step6_body'  => '"Edit all" lets you modify name and status across many groups and save in one go.',
        'step7_title' => 'Favorites',
        'step7_body'  => 'The star marks a row as a favorite. Favorites always show at the top of the list; each user has their own.',
        'step8_title' => 'Bulk operations',
        'step8_body'  => 'Select rows with the checkboxes — a bar appears to activate, deactivate or delete.',
        'step9_title' => 'Need a refresher?',
        'step9_body'  => 'Reopen this tour anytime with the ? button. "Recent" in the avatar menu shows the last records you viewed.',
    ],
];
