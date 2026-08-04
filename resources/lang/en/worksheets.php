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
    'void_reason'     => 'Reason for voiding',
    'ambient_temp_c'  => 'Ambient temperature',
    'ambient_humidity'=> 'Ambient humidity',
    // Printed in the report's test-conditions block.
    'sample_temp_c'   => 'Sample temperature',
    'notes'           => 'Notes',
    'entered_by'      => 'Entered by',
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
    'required_kind'      => 'Required',
    'required_kind_help' => 'This test requires this control row. It cannot be deleted while it is the only one of its kind.',
    'no_pending_samples'      => 'No samples with this test',
    'already_in_sheet'        => 'already on this sheet',
    'all_samples_loaded'      => 'Every sample is already loaded',
    'no_pending_samples_help' => 'No sample has this test requested. Request it in the sample intake and it will show up here.',
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
        'duplicate_sample'      => 'Sample :code already has its row on this sheet. To fix a value, edit that row; for a second control measurement, use the Duplicate row.',
        'sample_test_other_definition' => 'That sample has a different test requested. Load it in the matching worksheet.',
        'row_reported'          => 'This row cannot be deleted: its result was printed on an issued report. Withdraw that report first (unlock it) and try again.',
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
    'created_with_rows' => 'Worksheet created, with its :kinds already in place.',
    'rows_saved'  => ':count rows saved.',
    'filled'      => ':count pending samples added.',
    'fill_none'   => 'No pending samples left for this test.',

    'fill_pending'      => 'Bring pending samples',
    'fill_pending_help' => 'Adds every sample this test is still waiting for, at once. They come in empty: you enter the numbers.',
    'save_all'          => 'Save all',
    'save_all_help'     => 'Saves only the rows with changes, together. The per-row button is still there.',
    'incomplete_row'    => 'This row is missing :count required value.|This row is missing :count required values.',
    'incomplete_sheet'  => ':count required value missing.|:count required values missing.',
    'incomplete_why'    => 'The sheet still saves, but it does not publish results until they are filled in.',
    'complete_sheet'    => 'No required value is missing: the sheet publishes its results.',
    'required_to_publish' => 'Required to publish. It can be saved empty, but the sheet does not publish until it is filled in.',
    'kind_change'       => 'Change the row type',
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
    // The result IS reported without equipment: confirmed by the laboratory
    // (2026-07-30). The sample number is associated with a transformer later, and
    // sometimes never — a sample from a drum of new oil comes from no equipment.
    'equipment_missing_count'  => '{0} Every sample states its equipment.|{1} 1 sample without equipment: its result is reported, but with no equipment trend and no voltage-class limits.|[2,*] :count samples without equipment: their results are reported, but with no equipment trend and no voltage-class limits.',

    'kind_label'     => 'Row kind',
    'instrument'     => 'Instrument',
    'sample_code'    => 'Sample code',
    'date_all'       => 'No date filter: every worksheet is listed, including those from previous years.',
    'no_edit_permission' => 'You do not have permission to enter values in this worksheet.',

    'configure_columns_hint' => 'Defines the columns of the :test test. The change applies to every worksheet of that test, not just this one.',
    'constants'       => 'Constant values',
    'constants_title' => 'Constant values · :test',
    'constants_hint'  => 'The values that repeat on every :test run: the titrant factor, the room temperature. Change them here without leaving the sheet.',
    'constants_scope' => 'They belong to the test, not to this sheet: the change applies to every sheet of that test from now on. Rows already loaded keep the value they were calculated with.',

    'empty'          => 'No worksheets yet.',
    'empty_rows'     => 'The worksheet has no rows. Add the control standard to start.',

    // The chromatography report prints it: a gas volume depends on the pressure
    // it was measured at.
    'lab_pressure_hpa' => 'Laboratory atmospheric pressure',

    // ── Index: search, bulk delete and row actions ──────────────────────
    'search_sample'   => 'Search by sample no.',
    'analyst_filter'  => 'Analyst',
    'record'          => 'worksheet',
    'records'         => 'worksheets',
    'bulk_cascade_note' => 'Voiding pulls each sheet\'s results out of the queryable layer, flags its quality-control points and sends its tests back to the queue. The values entered stay stored along with the reason.',
    'bulk_none_deleted' => 'No worksheet was voided: :locked locked, :voided already voided.',
    'bulk_skipped_voided' => ':count were already voided.',

    // ── Trash and list export ────────────────────────────────────────────
    'restored'        => 'Worksheet restored. Its results are reported again.',
    'trash_title'     => 'Worksheet trash',
    'trash_intro'     => 'Voided worksheets, with their reason. They can be restored: doing so brings their results back to the queryable layer and their tests back to the state they had. There is no permanent delete — a worksheet is the record of a test the laboratory ran.',
    'trash_empty'     => 'No voided worksheets.',
    'trash_search'    => 'Search by test',
    'deleted_at'      => 'Voided on',
    'deleted_by'      => 'Voided by',
    'export_note'     => 'What comes out is the list —what was run, on what day, by whom and in what state—, not the measured values: a test result is reported through its report, with signature and verification code.',
];
