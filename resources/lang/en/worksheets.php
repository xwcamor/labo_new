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
    'group_none'      => 'Ungrouped',
    'run_date'        => 'Run date',
    'analyst'         => 'Analyst',
    'status'          => 'Status',
    'validated_by'    => 'Validated by',
    'validated_at'    => 'Validated at',
    'void_reason'     => 'Void reason',
    'ambient_temp_c'  => 'Ambient temperature',
    'ambient_humidity'=> 'Ambient humidity',
    // Printed in the report's test-conditions block.
    'sample_temp_c'   => 'Sample temperature',
    'notes'           => 'Notes',
    'rows_count'      => 'Rows',
    'samples_count'   => 'Samples',

    // ── Statuses ──────────────────────────────────────────────────────────
    'state' => [
        'draft'     => 'In progress',
        'closed'    => 'Closed',   // legacy: no longer produced
        'validated' => 'Complete',
        'voided'    => 'Removed',
    ],
    'state_help' => [
        'draft'     => 'Being filled in. The worksheet accepts changes until the system locks it by age; unlocking it afterwards is an explicit, audited decision.',
        'closed'    => 'Legacy state: the analyst is done and awaiting review. No longer produced — validation is now a single step.',
        'validated' => 'Complete: its results are published and controls feed the control chart. It still accepts corrections until the system locks it.',
        'voided'    => 'Removed with a reason. Its raw values are kept and it can be restored: the laboratory answers for it under audit.',
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
        'validate'      => 'Validate',
        'reopen'        => 'Reopen',
        'import_file'   => 'Read instrument file',
        'recalculate'   => 'Recalculate',
    ],
    'confirm' => [
        'validate' => 'On validation, the controls in this worksheet start feeding the control chart.',
        'void'     => 'Removing requires a reason. The worksheet stays on record with its values.',
    ],

    // ── Errors ────────────────────────────────────────────────────────────
    'pick_sample' => 'Pick the sample…',
    'errors' => [
        'locked'                => 'The worksheet is locked by the supervisor. No changes allowed.',
        'not_draft'             => 'The worksheet is no longer in progress. Only an in-progress worksheet is writable.',
        'not_open'              => 'Only an open worksheet can be validated.',
        'missing_required'      => ':count required values are missing. Fill them in before validating the worksheet.',
        'missing_prerequisites' => 'This test requires entering first: :kinds. No samples are accepted until then.',
        'already_voided'        => 'The worksheet is already removed. The original reason is not replaced.',
        'void_reason_required'  => 'State the reason for removal.',
        'preview_too_large'     => 'Too much data was sent for calculation. Save the row and try again.',

        'value_not_above'       => ':field: the value must be greater than :min. If the property was not measured, leave the cell empty instead of entering :min.',
        'value_below_min'       => ':field: the value cannot be lower than :min.',
        'value_above_max'       => ':field: the value cannot be higher than :max.',
        'unknown_sample_test'   => 'That requested test does not exist. Pick the sample again.',
        'sample_test_other_definition' => 'That sample has a different test requested. Load it in the matching worksheet.',
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
    'saved'       => 'Worksheet header saved.',
    'row_saved'   => 'Row saved.',
    'row_deleted' => 'Row deleted.',
    'validated'   => 'Worksheet validated. Controls now feed the control chart.',
    'deleted'     => 'Worksheet removed. Its values are kept along with the reason.',

    // ── Equipment the sample was drawn from ───────────────────────────────
    // The link is OPTIONAL, confirmed by the laboratory: the sample number is
    // associated with the transformer later, and some samples come from no
    // equipment at all (a drum of new oil, a loose container). The result is
    // written and reported anyway. What cannot be done
    // equipment and never reaches the customer report. It warns, it does not
    // block: the analyst sometimes fills the bench before the sample intake is
    // on record.
    'equipment'                => 'Equipment',
    'equipment_hint'           => 'Where the sample was drawn from',
    'equipment_no_customer' => 'No customer',
    'equipment_placeholder'    => 'Search by name, serial or tag',
    'equipment_missing'        => 'No equipment',
    // The result IS reported without equipment: confirmed by the laboratory
    // (2026-07-30). The sample number is associated with a transformer later, and
    // sometimes never — a sample from a drum of new oil comes from no equipment.
    'equipment_missing_help'   => 'Without equipment the result is still reported, but it feeds no equipment trend and cannot take limits that depend on a voltage class. If the sample comes from a drum or a loose container, leaving it empty is correct.',
    'equipment_missing_count'  => '{0} Every sample states its equipment.|{1} 1 sample without equipment: its result is reported, but with no equipment trend and no voltage-class limits.|[2,*] :count samples without equipment: their results are reported, but with no equipment trend and no voltage-class limits.',
    'equipment_na_short'       => 'Not applicable',
    'equipment_not_applicable' => 'The control standard, the duplicate and the reagent blank are method controls, not customer samples: they do not come from any equipment.',

    'kind_label'     => 'Row kind',
    'instrument'     => 'Instrument',
    'sample_code'    => 'Sample code',
    'date_all'       => 'No date filter: every worksheet is listed, including those from previous years.',
    'no_edit_permission' => 'You do not have permission to enter values in this worksheet.',

    'configure_columns_hint' => 'Defines the columns of the :test test. The change applies to every worksheet of that test, not just this one.',

    'empty'          => 'No worksheets yet.',
    'empty_rows'     => 'The worksheet has no rows. Add the control standard to start.',

    // The chromatography report prints it: a gas volume depends on the pressure
    // it was measured at.
    'lab_pressure_hpa' => 'Laboratory atmospheric pressure',
];
