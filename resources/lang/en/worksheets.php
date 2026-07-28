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
        'preview_too_large'     => 'Too much data was sent for calculation. Save the row and try again.',

        'value_not_above'       => ':field: the value must be greater than :min. If the property was not measured, leave the cell empty instead of entering :min.',
        'value_below_min'       => ':field: the value cannot be lower than :min.',
        'value_above_max'       => ':field: the value cannot be higher than :max.',
    ],

    // ── Calculation ───────────────────────────────────────────────────────
    'computed'        => 'Calculated',
    'computed_help'   => 'This value is calculated on the server from the column formula. It is not typed in.',
    'formula_cycle'   => 'The formulas of this test reference each other in a circle (:path). Fix the template.',
    'formula_error'   => 'The formula for :field could not be evaluated.',

    // Preview: what the formula gives for the values currently typed in,
    // calculated by the server and not saved yet.
    'preview_calculating' => 'Calculating…',
    'preview_hint'        => 'Preview calculated by the server from the values entered. It becomes final when the row is saved.',
    'preview_failed'      => 'The server could not be reached for the calculation. The value is resolved anyway when the row is saved.',

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

    // ── Equipment the sample was drawn from ───────────────────────────────
    // Without this link the result is not written: it cannot be queried by
    // equipment and never reaches the customer report. It warns, it does not
    // block: the analyst sometimes fills the bench before the sample intake is
    // on record.
    'equipment'                => 'Equipment',
    'equipment_hint'           => 'Where the sample was drawn from',
    'equipment_placeholder'    => 'Search by name, serial or tag',
    'equipment_missing'        => 'No equipment',
    'equipment_missing_help'   => 'Until the sample states which equipment it came from, this test will not appear in the customer report nor in the equipment trend.',
    'equipment_missing_count'  => '{0} Every sample states its equipment.|{1} 1 sample without equipment: that test will not appear in the customer report.|[2,*] :count samples without equipment: those tests will not appear in the customer report.',
    'equipment_na_short'       => 'Not applicable',
    'equipment_not_applicable' => 'The control standard, the duplicate and the reagent blank are method controls, not customer samples: they do not come from any equipment.',

    'kind_label'     => 'Row kind',
    'instrument'     => 'Instrument',
    'sample_code'    => 'Sample code',
    'date_all'       => 'No date filter: every worksheet is listed, including those from previous years.',
    'no_edit_permission' => 'You do not have permission to enter values in this worksheet.',

    'empty'          => 'No worksheets yet.',
    'empty_rows'     => 'The worksheet has no rows. Add the control standard to start.',

];
