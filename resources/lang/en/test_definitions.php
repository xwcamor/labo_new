<?php

return [
    'singular'      => 'Test',
    'plural'        => 'Tests',
    'record'        => 'test',
    'records'       => 'tests',
    'new'           => 'Create test',
    'id'            => 'No.',

    'index_title'    => 'Tests',
    'index_subtitle' => 'The tests the laboratory runs and how their worksheet behaves.',
    'create_title'   => 'Create test',
    'create_subtitle'=> 'Fill in the test data. Its worksheet columns are defined afterwards, from the detail page.',
    'edit_title'     => 'Edit test',
    'delete_title'   => 'Delete test',
    'show_title'     => 'Test — Details',
    'trash_title'    => 'Tests trash',
    'form_create_hint' => 'Fill in the test data.',
    'empty_hint'      => 'Create your first test or import the ones from the previous system.',
    'name_placeholder' => 'E.g.: Acid Number, Chromatography, Furans',
    'code_placeholder' => 'E.g.: acid_number',

    // ── Form and detail sections ────────────────────────────────────────
    'section_identification' => 'Identification',
    'section_sampling'       => 'Sample and presentation',
    'section_control'        => 'Worksheet quality control',
    'section_traceability'   => 'Traceability',

    'name'      => 'Name',
    'name_help' => 'Test name as shown in the menu and on the report (e.g. Acid Number).',
    'code'      => 'Code',
    'code_help' => 'Technical identifier of the test (e.g. acid_number). It is written from the name and is unique system-wide: spec limit sets, the analyte map and control charts all point at it.',
    'code_changes_title' => 'Renaming the test changes its code',
    'code_changes_help'  => 'The code goes from “:from” to “:to”. It is the key that spec limit sets, the analyte map, the QC policy and the control charts point at: anything tied to the previous code needs repointing.',
    'group'     => 'Group',
    'group_help'=> 'Category the test belongs to: Physicochemical, Chromatography or Other. It sorts the menu and the report sections.',
    'group_none'=> 'No group',
    'description' => 'Description',
    'description_help' => 'What the test is for or how it is run. Free text for the analyst; it is not used in any calculation.',
    'container' => 'Container',
    'container_help' => 'Container the sample must arrive in for this test (e.g. glass syringe, amber bottle).',
    'report_comment_group' => 'Report table',
    'report_comment_group_help' => 'Which other tests share a table in the report. The thirteen physicochemical tests go together on one page, as in the accredited report; chromatography and furans get their own. Empty = its own page.',
    'report_comment_group_own' => 'Its own page',
    'chart_unit'=> 'Chart unit',
    'chart_unit_help' => 'Axis label on the trend charts (e.g. ppm, mg KOH/g, kV).',

    // ── Quality control flags ───────────────────────────────────────────
    'control_intro' => 'The control standard checks the method is measuring correctly, the duplicate that the result is repeatable. When required, the worksheet cannot be closed without them.',
    'has_control'         => 'Runs with a control standard',
    'has_control_help'    => 'The test accepts control standard rows on its worksheet.',
    'requires_control'    => 'Requires a control standard',
    'requires_control_help' => 'The worksheet is not published without at least one control standard entered. The previous system had the same rule, but it lived inside the form HTML and ended up disabled.',
    'requires_duplicate'  => 'Requires a duplicate',
    'requires_duplicate_help' => 'The worksheet is not published without at least one duplicate entered.',
    'is_grouped'          => 'Exempt from control and duplicate',
    'is_grouped_help'     => 'The test carries no per-run quality control. Ticking it switches off the two checkboxes above. The real values of this exemption in the previous system only exist in its production database, so every test arrives here WITHOUT exemption: tick them yourself.',

    'replicates'      => 'Replicates',
    'replicates_help' => 'How many times the SAME sample is measured to average. Dielectric strength is measured 5 or 6 times; every other test uses 1.',

    'legacy_id'      => 'Id in the previous system',
    'legacy_id_help' => 'Source identifier of the test. It lets the import be re-run without duplicating and keeps historical data traceable. Read-only.',

    'sort_order' => 'Order',
    'sort_order_help' => 'Position of the test within its group. Lower comes first.',
    'is_active' => 'Status',
    'is_active_help' => 'If inactive, the test cannot be requested on a new sample. Worksheets already entered are untouched.',
    'filter_name' => 'Name',

    // ── Worksheet columns (detail page) ─────────────────────────────────
    'fields'        => 'Worksheet columns',
    'fields_hint'   => 'The columns the analyst fills in when running the test, and which of them are a result.',
    'fields_empty'  => 'This test has no columns defined yet.',
    'fields_count'  => 'Columns',
    'results_count' => 'Results',
    'fields_edit'   => 'Configure columns',

    'edit_hint'   => 'Edit this record',
    'delete_hint' => 'Delete (goes to trash)',
    'restore_hint'=> 'Will go back to the main list.',

    'created' => 'Test created.',
    'saved'   => 'Test updated.',
    'deleted' => 'Test deleted.',

    'delete_about'                 => 'You are about to delete ":name". It will go to the trash.',
    'delete_has_fields'            => 'Its worksheet columns are deleted as well.',
    'deleted_description_required' => 'Provide a reason for the deletion.',
    'deleted_description_min'      => 'Reason must be at least 3 characters.',
    'deleted_description_max'      => 'Reason cannot exceed 1000 characters.',

    // Export
    'export_filename'           => 'tests_export',
    'import_template_filename'  => 'tests-template.xlsx',
    'export_title'              => 'Tests Report',
    'export_limit_exceeded'     => 'The :format export exceeds the limit (:count rows vs :limit max). Use CSV for large datasets (no limit).',
    'export_format_limit_hint'  => 'Max :limit rows for this format. Use CSV for large datasets.',
    'export_no_limit_hint'      => 'No limit — recommended for large datasets.',

    // Validation
    'name_required'            => 'The name field is required.',
    'code_required'            => 'The test code is required.',
    'code_unique'              => 'A test with this code already exists.',
    'group_invalid'            => 'The selected group does not exist.',
    'is_active_required'       => 'The status field is required.',
    'import_super_blocked'     => 'A super without an assigned workspace cannot import (code matching could update records from another workspace).',

    // Edit All
    'edit_all_title'    => 'Tests — Edit All',
    'edit_all_subtitle' => 'Edit name and status of several tests at once. The group and the control flags are edited on each test page.',
    'edit_all_changes'  => '{0} No changes|{1} 1 pending change|[2,*] :count pending changes',
    'edit_all_save_all' => 'Save all',
    'edit_all_discard'  => 'Discard changes',
    'edit_all_no_results' => 'No tests match the filter.',

    'table_headers' => [
        'editable_name'   => 'Name (editable)',
        'editable_status' => 'Status (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Welcome to Tests',
        'step1_body'  => 'These are the assays the laboratory runs and the definition of their worksheet. Quick tour in under a minute.',
        'step2_title' => 'Filters',
        'step2_body'  => 'Search and filter by name, code, group, status and the control standard / duplicate flags.',
        'step3_title' => 'Saved views',
        'step3_body'  => 'Save your favorite filter + columns + sort combo and reapply with one click. Per-user.',
        'step4_title' => 'Columns',
        'step4_body'  => 'Show/hide columns; your choice persists. Required ones cannot be hidden.',
        'step5_title' => 'Export & Import',
        'step5_body'  => 'Export to Excel/PDF/Word in the background — you will be notified. Import from Excel/CSV with preview.',
        'step6_title' => 'Edit many at once',
        'step6_body'  => '"Edit all" lets you modify name and status across many tests and save in one go.',
        'step7_title' => 'Favorites',
        'step7_body'  => 'The star marks a row as a favorite. Favorites always show at the top of the list; each user has their own.',
        'step8_title' => 'Bulk operations',
        'step8_body'  => 'Select rows with the checkboxes — a bar appears to activate, deactivate or delete.',
        'step9_title' => 'Need a refresher?',
        'step9_body'  => 'Reopen this tour anytime with the ? button. "Recent" in the avatar menu shows the last records you viewed.',
    ],
];
