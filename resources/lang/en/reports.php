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

    'footer_legend' => 'Results apply only to the sample as received. The acceptance value is the criterion applicable to this equipment under the standard shown on each row.',
    'footer_accreditation' => '(A) accredited method · (NA) non-accredited method. The mark refers to the method actually used for this test.',
    'generated_by'  => 'Issued by: :name',
];
