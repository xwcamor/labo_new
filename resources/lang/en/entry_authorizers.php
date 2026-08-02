<?php

// The old system's "Personal de Laboratorio" (`rem_user_signatures`): the
// laboratory people who AUTHORIZE sample intake. Its own catalog — NOT the
// report signers (Signatures module).
return [
    'singular'      => 'Intake authorizer',
    'plural'        => 'Intake authorizers',
    'record'        => 'person',
    'records'       => 'people',
    'new'           => 'Add person',
    'id'            => 'No.',

    'index_title'    => 'Intake authorizing staff',
    'index_subtitle' => 'Who at the laboratory can authorize sample intake. These are the people the reception form offers.',
    'create_title'   => 'Add person',
    'create_subtitle'=> 'Full name and their signature. The signature prints on the reception record.',
    'edit_title'     => 'Edit person',
    'delete_title'   => 'Delete person',
    'show_title'     => 'Intake authorizer — Details',
    'trash_title'    => 'Intake authorizers trash',
    'form_create_hint' => 'Full name and their signature.',
    'empty_hint'      => 'Add the first laboratory person who authorizes intake.',
    'name_placeholder' => 'First and last name',

    'name'      => 'Full name',
    'name_help' => 'As printed on the reception record.',
    'code'      => 'Code',
    'code_help' => 'Internal technical identifier. Derived from the name if left empty.',
    'image'      => 'Signature',
    'image_help' => 'The scanned or drawn signature. Without it, the record leaves the line to sign by hand.',
    'no_image'   => 'No signature uploaded',
    'sort_order' => 'Order',
    'is_active' => 'Status',
    'is_active_help' => 'If inactive, the person is not offered on the reception form.',
    'filter_name' => 'Name',

    'edit_hint'   => 'Modify this record',
    'delete_hint' => 'Delete (goes to trash)',
    'restore_hint'=> 'It will be available again in the main list.',

    'created' => 'Person added.',
    'saved'   => 'Person updated.',
    'deleted' => 'Person deleted.',

    'delete_about'                 => 'You are about to delete ":name". It will go to the trash.',
    'deleted_description_required' => 'State the reason for the deletion.',
    'deleted_description_min'      => 'The reason must be at least 3 characters.',
    'deleted_description_max'      => 'The reason cannot exceed 1000 characters.',

    // Export
    'export_filename'           => 'intake_authorizers_export',
    'import_template_filename'  => 'intake-authorizers-template.xlsx',
    'export_title'              => 'Intake authorizing staff',
    'export_limit_exceeded'     => 'The :format export exceeds the limit (:count rows vs :limit max). Use CSV for large datasets (no limit).',
    'export_format_limit_hint'  => 'Maximum :limit rows for this format. Use CSV for large datasets.',
    'export_no_limit_hint'      => 'No limit — recommended for large datasets.',

    // Validation
    'name_required'            => 'The full name is required.',
    'name_unique'              => 'This person is already in the catalog.',
    'code_unique'              => 'A person with this code already exists.',
    'name_duplicate_in_batch'  => 'Duplicate name within the same batch.',
    'is_active_required'       => 'The status field is required.',
    'import_super_blocked'     => 'A super without an assigned workspace cannot import (the name match could update records of another workspace).',

    // Edit All
    'edit_all_title'    => 'Intake authorizers — Edit All',
    'edit_all_subtitle' => 'Edit name and status of several people at once. Click "Save all" to confirm, "Cancel" to discard.',
    'edit_all_changes'  => '{0} No changes|{1} 1 pending change|[2,*] :count pending changes',
    'edit_all_save_all' => 'Save all',
    'edit_all_discard'  => 'Discard changes',
    'edit_all_no_results' => 'No people match the filter.',

    'table_headers' => [
        'editable_name'   => 'Name (editable)',
        'editable_status' => 'Status (editable)',
    ],

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Intake authorizing staff',
        'step1_body'  => 'The laboratory people who can authorize sample intake. This catalog feeds the reception form.',
        'step2_title' => 'Filters',
        'step2_body'  => 'Search and filter by name, status and dates. Active filters appear as chips above the table.',
        'step3_title' => 'Saved views',
        'step3_body'  => 'Save your favorite combination of filters + columns + order and apply it later with one click. Each user has their own.',
        'step4_title' => 'Columns',
        'step4_body'  => 'Show/hide columns and your choice is remembered. Columns marked "required" cannot be hidden.',
        'step5_title' => 'Export & Import',
        'step5_body'  => 'Export to Excel/PDF/Word in the background — you will be notified when ready. Import from Excel/CSV with a preview before confirming.',
        'step6_title' => 'Edit many at once',
        'step6_body'  => '"Edit all" lets you modify name and status of several records together. All changes are confirmed in a single save.',
        'step7_title' => 'Favorites ★',
        'step7_body'  => 'The ★ star marks a record as favorite. Favorites always appear at the top of the list and each user has their own.',
        'step8_title' => 'Bulk operations',
        'step8_body'  => 'Select rows with the checkboxes — a bar appears to activate, deactivate, delete or restore. Works with hundreds of rows; large batches run in the background.',
        'step9_title' => 'Need a refresher?',
        'step9_body'  => 'Reopen this tour any time with the ? button above. You also have "Recent" in the avatar menu — the last records you viewed in any module.',
    ],
];
