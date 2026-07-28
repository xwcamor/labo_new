<?php

return [

    'title'     => 'Test columns',
    'singular'  => 'Column',
    'create'    => 'Add column',
    'edit'      => 'Edit column',

    'intro'     => 'The columns are the worksheet of the test: what the analyst sees and fills in at the bench. Adding, removing or reordering columns needs no programming.',

    // ── Fields ────────────────────────────────────────────────────────────
    'code'   => 'Code',
    'code_help' => 'The name formulas use to refer to this column. Lowercase, digits and underscore only. The analyst never sees it.',
    'label'  => 'Label',
    'label_help' => 'The text shown in the worksheet header.',
    'type'   => 'Type',
    'role'   => 'Role',
    'unit'   => 'Unit',
    'decimals' => 'Decimals',
    'min_value' => 'Minimum accepted',
    'max_value' => 'Maximum accepted',
    'range_help' => 'The range of what is physically possible, to catch a typo. Not the limit of the standard: that is defined separately.',
    'is_required'    => 'Required',
    'is_locked'      => 'Read only',
    'is_reusable'    => 'Constant',
    'is_reusable_help' => 'The value carries over to the next sample. Used for the titrant factor or the ambient temperature.',
    'default_value'  => 'Constant value',
    'report_visible' => 'Show in the report',
    'report_visible_help' => 'In the previous system this checkbox could be ticked and did nothing: the report picked columns by number. Here it does decide what gets printed.',
    'replicates'     => 'Readings per sample',
    'replicates_help' => 'How many times this reading is repeated on the same sample. Dielectric strength is measured five or six times and averaged.',
    'sort_order'     => 'Order',
    'options'        => 'Options',
    'accreditation_flag' => 'Accreditation',
    'is_hidden'      => 'Hidden',

    // ── Column types ──────────────────────────────────────────────────────
    'types' => [
        'text'       => 'Text',
        'number'     => 'Number',
        'select'     => 'Selection',
        'date'       => 'Date',
        'computed'   => 'Calculated',
        'instrument' => 'Instrument',
    ],
    'types_help' => [
        'text'       => 'Free text. Takes no part in calculations.',
        'number'     => 'A measured value. Accepts > and < for readings that are not exact.',
        'select'     => 'A closed list of options. The chosen option is stored by reference, so renaming the list does not change what a closed test says.',
        'date'       => 'A date.',
        'computed'   => 'Calculated on the server from its formula. The analyst does not type it.',
        'instrument' => 'Which bench instrument produced the value. Carries its calibration status.',
    ],

    // ── Roles ─────────────────────────────────────────────────────────────
    'roles' => [
        'none'        => 'Ordinary column',
        'sample_code' => 'Sample code',
        'standard'    => 'Test standard',
        'result'      => 'Result',
        'temperature' => 'Test temperature',
        'observation' => 'Analyst note',
    ],
    'roles_help' => [
        'none'        => 'A bench value with no special meaning.',
        'sample_code' => 'Links the row to the customer sample. There must be exactly one.',
        'standard'    => 'Which standard the test was run under. Goes to the report.',
        'result'      => 'A result of the test. Feeds the parameter you select. A test can have several: chromatography has nine.',
        'temperature' => 'A test condition, not a result.',
        'observation' => 'Analyst note about this row.',
    ],
    'role_help_intro' => 'Declaring the role is what makes reordering columns safe.',

    'output_analyte'      => 'Parameter it feeds',
    'output_analyte_help' => 'Which measurable parameter this result corresponds to. This is what lets the report and the trends look up by parameter instead of by column position.',

    // ── Formula ───────────────────────────────────────────────────────────
    'formula'      => 'Formula',
    'formula_help' => 'An expression over the codes of the other columns of this test. For example: (volumen_gastado - volumen_blanco) * factor_koh / peso_aceite',
    'formula_functions' => 'Available functions: abs, sqrt, log10, ln, exp, pow, round, min, max, sum, avg.',
    'formula_check'  => 'Check',
    'formula_valid'  => 'The formula is valid.',
    'formula_uses'   => 'Uses: :fields',
    'formula_server' => 'The formula is evaluated on the server. The stored value is the one the server calculates, not the one the browser shows.',

    // ── Errors ────────────────────────────────────────────────────────────
    'errors' => [
        'used_by_formula' => 'Cannot delete: the formula of :fields uses this column. Fix those formulas first.',
        'formula_cycle'   => 'The formulas reference each other in a circle (:path). None of them could be calculated.',
        'unknown_field'   => 'The formula uses :field, which is not a column of this test.',
        'code_taken'      => 'A column with that code already exists in this test.',
    ],

    // ── Messages ──────────────────────────────────────────────────────────
    'created'   => 'Column added.',
    'updated'   => 'Column updated.',
    'deleted'   => 'Column deleted.',
    'reordered' => 'Order updated.',
    'reorder_safe' => 'Order is presentation only: formulas use codes and roles are declared, so reordering changes no calculation.',

    // ── Constant values ───────────────────────────────────────────────────
    'constants'       => 'Constant values',
    'constants_intro' => 'The values that carry over from one sample to the next. Change them when you titrate a new solution or the room conditions change.',
    'constants_updated' => 'Constant values updated.',
    'constants_empty' => 'This test has no columns marked as constant.',

    'empty' => 'The test has no columns yet.',

];
