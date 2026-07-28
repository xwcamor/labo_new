<?php

return [
    'title'    => 'Test report',
    'subtitle' => 'Dielectric oil test results',
    'emitted'  => 'Issued',

    // ── The band and the two header tables ──────────────────────────────
    // They repeat on EVERY page: a loose sheet must identify itself. Labels
    // follow the accredited report.
    'header_title'   => 'TEST RESULTS REPORT',
    'customer_info'  => 'CUSTOMER INFORMATION',
    'equipment_info' => 'EQUIPMENT INFORMATION (DATA PROVIDED BY THE CUSTOMER)',

    'customer'      => 'Customer and sample',
    'customer_name' => 'Customer',
    'address'       => 'Address',
    'contact'       => 'Contact',
    'end_user'      => 'End user',
    'service_order' => 'Service order no.',
    'sampled_at'    => 'Sampling date',
    'received_at'   => 'Reception date (dd-mm-yy)',
    'issued_at'     => 'Issue date (dd-mm-yy)',
    'sampler'       => 'Sampled by',
    'sample_description' => 'Sample description',

    'equipment'      => 'Equipment',
    'equipment_name' => 'Equipment',
    'serial'         => 'Serial',
    'tag'            => 'Customer code / TAG',
    'equipment_type' => 'Equipment type',
    'oil_type'       => 'Oil type',
    'voltage'        => 'Voltage (kV)',
    'power'          => 'Power (MVA)',
    'location'       => 'Location',
    'sampling_point' => 'Sampling point',
    'preservation'   => 'Preservation system',
    'sampling_reason' => 'Sampling reason',
    'brand'          => 'Manufacturer',
    'oil_brand'      => 'Oil brand',
    'manufacture_year' => 'Year of manufacture',
    'oil_qty'        => 'Oil quantity',
    'tap_changer'    => 'Tap changer',
    'in_service'     => 'In operation',
    // The four FIELD conditions, recorded when the sample was taken.
    'oil_temp'       => 'Transf. oil temp. (°C)',
    'equipment_temp' => 'Field oil temp. (°C)',
    'ambient_temp'   => 'Field ambient temp. (°C)',
    'humidity'       => 'Field rel. humidity (%RH)',

    // ── The results table ───────────────────────────────────────────────
    'results_title' => 'TEST RESULTS',
    'col_item'     => 'Item',
    'col_standard' => 'Standard',
    'col_test'     => 'Test',
    'col_unit'     => 'Unit',
    'col_limit'    => 'Acceptance value (*)',
    'col_result'   => 'Result',

    'limit_max'   => '(max)',
    'limit_min'   => '(min)',
    'out_of_spec' => 'out of spec',
    // A value nobody compared against anything carries the word, not just the
    // grey: colour does not survive a black and white photocopy.
    'no_criterion' => 'no criteria',

    // ── Footnotes on every test page ────────────────────────────────────
    // Legend for the method accreditation marks. It explains the superscript in
    // the STANDARD column, so it only prints when there is one.
    'foot_accredited'     => '(A) Accredited',
    'foot_not_accredited' => '(NA) Not accredited',
    // The accreditation paragraph (body, certificate and scope) does NOT live
    // here: it is lab data, `tenants.accreditation_note`. Certificates expire
    // and another lab is accredited by another body.

    /*
     * Footnotes that belong to ONE test, keyed by test code.
     *
     * In the previous system the test-cell note lived inside the physicals
     * partial, so changing the spark gap cell —a lab decision, not a program
     * one— meant editing HTML and redeploying. Here it is one line in this
     * file: no migration, no seeder. A test that is not listed prints no line.
     */
    'test_footnote' => [
        'rigidez_dielectrica' => '(1) Test cell: MC2A, voltage (RMS): 2000 VAC / 500 VDC',
    ],

    // ── Test conditions (one table per page, from its worksheet) ─────────
    'cond_standard'      => '(*) Reference standard',
    'cond_run_date'      => 'Analysis date',
    'cond_sample_temp'   => 'Sample temperature in lab',
    'cond_lab_temp'      => 'Lab temperature',
    'cond_lab_humidity'  => 'Lab relative humidity',

    'page_of' => 'Page :num of :total',
    'reported_by' => 'Reported by:',

    // ── The last page ───────────────────────────────────────────────────
    // The title and the "no analysis" line live further down, with the rest of
    // the diagnosis engine keys: they are shared with the screen.
    // Why this page carries no accreditation mark: an opinion is not a test
    // result and falls outside the accredited scope. The previous report did
    // the same (it used the "partial" logo, without the body's mark).
    'analysis_scope' => 'Opinions and interpretations on this page fall outside the scope of the accreditation.',

    'no_results' => 'This sample has no validated tests yet.',

    'note_no_criteria' => ':count result(s) have no applicable acceptance criteria: they are shown without comparison against any limit. They must NOT be read as compliant.',
    // The same warning, counted over THE PAGE in hand.
    'note_no_criteria_page' => ':count result(s) on this page have no applicable acceptance criteria: they are printed without comparison against any limit and must NOT be read as compliant.',
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

    'and' => 'and',
    'analysis_title' => 'ANALYSIS OF RESULTS (opinions and interpretations)',
    'analysis_empty'  => 'No analysis recorded for this test family.',
    'analysis_edited' => 'Edited by the analyst',
    'family' => [
        'fisicoquimico' => 'PHYSICOCHEMICAL TESTS',
    ],
    'footer_legend' => 'Results apply only to the sample as received. The acceptance value is the criterion applicable to this equipment under the standard shown on each row.',
    'footer_accreditation' => '(A) accredited method · (NA) non-accredited method. The mark refers to the method actually used for this test.',
    'generated_by'  => 'Issued by: :name',
];
