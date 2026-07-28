<?php

return [

    'title'     => 'Control charts',
    'singular'  => 'Control chart',
    'create'    => 'New control chart',
    'edit'      => 'Edit control chart',
    'show'      => 'Control chart',
    'trash'     => 'Control chart trash',

    'intro'     => 'A control chart watches that the laboratory is measuring correctly. It is built from the control standard, not from customer samples: it measures the method, not the oil.',

    // ── Fields ────────────────────────────────────────────────────────────
    'test_definition' => 'Test',
    'test_field'      => 'Column',
    'analyte'         => 'Parameter',
    'label'           => 'Name',
    'control_lot'     => 'Standard lot',
    'comment'         => 'Notes',
    'is_active'       => 'Active',

    // ── The five limits ───────────────────────────────────────────────────
    'limits' => [
        'lcs' => 'UCL · Upper control limit',
        'las' => 'UWL · Upper warning limit',
        'lc'  => 'CL · Centre line',
        'lai' => 'LWL · Lower warning limit',
        'lci' => 'LCL · Lower control limit',
    ],
    'limits_short' => [
        'lcs' => 'UCL',
        'las' => 'UWL',
        'lc'  => 'CL',
        'lai' => 'LWL',
        'lci' => 'LCL',
    ],
    'limits_help' => 'All five are stored explicitly, not just the mean and the standard deviation, because the laboratory sometimes sets them by its own criterion or because a standard requires it. To derive them instead, enter the mean and the standard deviation and turn on automatic calculation.',

    'center'       => 'Standard mean',
    'sd'           => 'Standard deviation',
    'is_derived'   => 'Derive the limits from the mean and the standard deviation',
    'warn_sigma'   => 'Deviations for the warning limit',
    'action_sigma' => 'Deviations for the control limit',
    'sigma_help'   => 'The classic criterion is 2 deviations for the warning limit and 3 for the control limit. They are configurable because not every parameter uses that criterion.',

    // ── Validity ──────────────────────────────────────────────────────────
    'effective_from' => 'Effective from',
    'effective_to'   => 'Effective to',
    'validity_help'  => 'When the standard lot changes, close the current chart and open a new one. Historical charts then keep being drawn against the limits in force on their own day, not against today\'s.',

    // ── Repeatability ─────────────────────────────────────────────────────
    'repeatability_limit' => 'Repeatability criterion',
    'repeatability_mode'  => 'Criterion mode',
    'repeatability_help'  => 'Maximum acceptable difference between a sample and its duplicate. Absolute mode compares in the units of the test; relative mode compares against the mean of the two readings.',
    'mode' => [
        'absolute' => 'Absolute (in the units of the test)',
        'relative' => 'Relative (% of the mean of the two readings)',
    ],

    // ── Points and verdict ────────────────────────────────────────────────
    'points'       => 'Standard readings',
    'measured_at'  => 'Date',
    'value'        => 'Value',
    'z_score'      => 'Deviations from the mean',
    'flag'         => 'Verdict',
    'flags' => [
        'ok'   => 'In control',
        'warn' => 'Warning',
        'out'  => 'Out of control',
    ],

    // ── Westgard rules ────────────────────────────────────────────────────
    'westgard'       => 'Westgard rule',
    'westgard_rules' => [
        '1_3s' => '1-3s · one point beyond 3 deviations',
        '2_2s' => '2-2s · two consecutive points beyond 2 deviations on the same side',
        'R_4s' => 'R-4s · two consecutive points more than 4 deviations apart',
        '4_1s' => '4-1s · four consecutive points beyond 1 deviation on the same side',
        '10x'  => '10x · ten consecutive points on the same side of the mean',
    ],
    'westgard_meaning' => [
        '1_3s' => 'Rejection. Large random error.',
        '2_2s' => 'Rejection. Systematic error: the method has shifted.',
        'R_4s' => 'Rejection. Random error: dispersion has grown.',
        '4_1s' => 'Warning. Emerging bias.',
        '10x'  => 'Warning. The standard has shifted even though no point went out.',
    ],

    // ── Duplicates ────────────────────────────────────────────────────────
    'duplicates'          => 'Duplicates',
    'difference'          => 'Difference',
    'relative_difference' => 'Relative difference',
    'within_limit'        => 'Within criterion',
    'no_criterion'        => 'No criterion set: the difference is reported but no verdict is given.',

    'created' => 'Control chart created.',
    'updated' => 'Control chart updated.',
    'deleted' => 'Control chart deleted.',
    'point_updated' => 'Reading updated.',

    'excluded'         => 'Excluded',
    'reinclude'        => 'Bring back into the calculation',
    'sigma_invalid'    => 'The warning limit must sit inside the control limit: fewer deviations than the control limit.',
    'derived_server'   => 'Preview. The final value is calculated on the server when you save.',
    'limits_title'     => 'Chart limits',
    'validity'         => 'Validity',
    'exclude'          => 'Exclude from the calculation',
    'exclusion_reason' => 'Reason for exclusion',

    'empty'        => 'No control charts yet.',
    'empty_points' => 'The chart has no readings. They are filled in when a worksheet with a control standard is validated.',

];
