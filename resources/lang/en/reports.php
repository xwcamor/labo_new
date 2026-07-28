<?php

return [
    'title'    => 'Test report',
    'subtitle' => 'Dielectric oil test results',
    'emitted'  => 'Issued',

    'customer'      => 'Customer and sample',
    'customer_name' => 'Customer',
    'service_order' => 'Service order',
    'sampled_at'    => 'Sampling date',
    'received_at'   => 'Reception date',
    'sampler'       => 'Sampled by',

    'equipment'      => 'Equipment',
    'equipment_name' => 'Equipment',
    'serial'         => 'Serial · Tag',
    'equipment_type' => 'Type',
    'oil_type'       => 'Oil',
    'voltage'        => 'Voltage',

    'col_item'     => 'Item',
    'col_standard' => 'Standard',
    'col_test'     => 'Test',
    'col_unit'     => 'Unit',
    'col_limit'    => 'Acceptance value',
    'col_result'   => 'Result',

    'limit_max'   => '(max)',
    'limit_min'   => '(min)',
    'out_of_spec' => 'out of spec',

    'no_results' => 'This sample has no validated tests yet.',

    'note_no_criteria' => ':count result(s) have no applicable acceptance criteria: they are shown without comparison against any limit. They must NOT be read as compliant.',
    'note_pending'     => ':count requested test(s) are not validated yet: this report is partial.',
    'note_no_equipment' => 'The sample has no equipment assigned.',

    'verify_code' => 'Code',
    'verify_hint' => 'Verify this report by scanning the code',
    'no_signers'  => 'Signature',
    'relation'    => [
        'prepared'  => 'Prepared by',
        'reviewed'  => 'Reviewed by',
        'approved'  => 'Approved by',
        'authorized'=> 'Authorized by',
        'verified'  => 'Verified by',
        'endorsed'  => 'Endorsed by',
    ],
    'verify_sample'    => 'Sample',
    'verify_equipment' => 'Equipment',
    'verify_sections'  => 'Reported tests',
    // ── Public verification portal (where the QR points) ────────────────
    'verify_title'      => 'Report verification',
    'verify_ok'         => 'Report verified',
    'verify_ok_sub'     => 'This code matches a report issued by the system.',
    'verify_fail'       => 'Code not found',
    'verify_fail_sub'   => 'Code :code does not match any issued report. Check that it is complete.',
    'verify_form_hint'  => 'Enter the code printed on the report to check that it is genuine.',
    'verify_form_btn'   => 'Verify',
    'verify_foot'       => 'This page only confirms the report came from the system. It does not publish test results.',
    'verify_issued_at'  => 'Issue date',
    'verify_issued_by'  => 'Issued by',
    'verify_signers'    => 'Signed by',
    'verify_match_hint' => 'If any detail does not match the paper in your hand, the document was altered.',
    'verify_serial'     => 'Serial',
    'code'              => 'Report',
    'health_index'      => 'Health index',

    'footer_legend' => 'Results apply only to the sample as received. The acceptance value is the criterion applicable to this equipment under the standard shown on each row.',
    'footer_accreditation' => '(A) accredited method · (NA) non-accredited method. The mark refers to the method actually used for this test.',
    'generated_by'  => 'Issued by: :name',
];
