<?php

return [

    'title'          => 'Worksheets',
    'singular'       => 'Worksheet',
    'create'         => 'New worksheet',
    'edit'           => 'Edit worksheet',
    'show'           => 'Worksheet',
    'trash'          => 'Worksheet trash',

    'intro'          => 'A worksheet is one test run on one day by one analyst. It groups the customer samples, the control standard and the duplicates of that shift.',

    // ── Fields ────────────────────────────────────────────────────────────
    'test_definition' => 'Test',
    'run_date'        => 'Run date',
    'analyst'         => 'Analyst',
    'status'          => 'Status',
    'validated_by'    => 'Validated by',
    'validated_at'    => 'Validated at',
    'void_reason'     => 'Void reason',
    'ambient_temp_c'  => 'Ambient temperature',
    'ambient_humidity'=> 'Ambient humidity',
    'notes'           => 'Notes',
    'rows_count'      => 'Rows',
    'samples_count'   => 'Samples',

    // ── Statuses ──────────────────────────────────────────────────────────
    'state' => [
        'draft'     => 'In progress',
        'closed'    => 'Closed',
        'validated' => 'Validated',
        'voided'    => 'Voided',
    ],
    'state_help' => [
        'draft'     => 'The analyst is still entering data. The only writable state.',
        'closed'    => 'The analyst is done. Awaiting supervisor review.',
        'validated' => 'Reviewed and signed by the supervisor. Controls now feed the control chart.',
        'voided'    => 'Voided with a reason. Not deleted: the laboratory answers for it under audit.',
    ],

    // ── Row kinds ─────────────────────────────────────────────────────────
    'kind' => [
        'sample'    => 'Sample',
        'control'   => 'Control standard',
        'duplicate' => 'Duplicate',
        'blank'     => 'Reagent blank',
    ],
    'kind_help' => [
        'sample'    => 'A customer sample.',
        'control'   => 'Reference material of known value. This is what the control chart plots.',
        'duplicate' => 'Second reading of a sample already entered. Measures method repeatability.',
        'blank'     => 'Reagents only, no sample. Detects contamination of the run.',
    ],

    // ── Actions ───────────────────────────────────────────────────────────
    'actions' => [
        'add_row'       => 'Add row',
        'close'         => 'Close worksheet',
        'validate'      => 'Validate',
        'void'          => 'Void',
        'reopen'        => 'Reopen',
        'import_file'   => 'Read instrument file',
        'recalculate'   => 'Recalculate',
    ],
    'confirm' => [
        'close'    => 'Close the worksheet? No more values can be entered after closing.',
        'validate' => 'On validation, the controls in this worksheet start feeding the control chart.',
        'void'     => 'Voiding requires a reason. The worksheet stays on record, it is not deleted.',
    ],

    // ── Errors ────────────────────────────────────────────────────────────
    'errors' => [
        'locked'                => 'The worksheet is locked by the supervisor. No changes allowed.',
        'not_draft'             => 'The worksheet is no longer in progress. Only an in-progress worksheet is writable.',
        'not_closed'            => 'Only a closed worksheet can be validated.',
        'missing_required'      => ':count required values are missing. Fill them in before closing the worksheet.',
        'missing_prerequisites' => 'This test requires entering first: :kinds. No samples are accepted until then.',
        'already_voided'        => 'The worksheet is already voided. The original reason is not replaced.',
        'void_reason_required'  => 'State the reason for voiding.',
    ],

    // ── Calculation ───────────────────────────────────────────────────────
    'computed'        => 'Calculated',
    'computed_help'   => 'This value is calculated on the server from the column formula. It is not typed in.',
    'formula_cycle'   => 'The formulas of this test reference each other in a circle (:path). Fix the template.',
    'formula_error'   => 'The formula for :field could not be evaluated.',

    // ── Censored values ───────────────────────────────────────────────────
    'censored' => [
        'gt'    => 'Greater than the value shown. The instrument reached its ceiling.',
        'lt'    => 'Less than the value shown. Below the detection limit.',
        'hint'  => 'Type > or < before the number when the reading is not exact (for example, >75).',
    ],

    // Action result messages.
    'created'     => 'Worksheet created.',
    'row_saved'   => 'Row saved.',
    'row_deleted' => 'Row deleted.',
    'closed'      => 'Worksheet closed. Awaiting supervisor validation.',
    'validated'   => 'Worksheet validated. Controls now feed the control chart.',
    'voided'      => 'Worksheet voided.',

    'kind_label'     => 'Row kind',
    'instrument'     => 'Instrument',
    'sample_code'    => 'Sample code',
    'date_all'       => 'No date filter: every worksheet is listed, including those from previous years.',
    'no_edit_permission' => 'You do not have permission to enter values in this worksheet.',

    'empty'          => 'No worksheets yet.',
    'empty_rows'     => 'The worksheet has no rows. Add the control standard to start.',

];
