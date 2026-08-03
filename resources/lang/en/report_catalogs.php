<?php

return [
    'title' => 'Report lists',
    'intro' => 'The small lists of the system: the four that fill the report form, plus the stock unit. They used to be free text, which is how the same unit ended up written three different ways.',

    'frozen_note' => 'What the sample stores is the chosen TEXT, not the catalog row: editing or deactivating an option here does not change any report already issued.',

    'kind' => [
        'sampling_reason' => 'Analysis reason',
        'sampling_point'  => 'Sampling point',
        'oil_brand'       => 'Oil brand',
        'volume_unit'     => 'Volume unit',
        'stock_unit'      => 'Stock unit',
    ],

    'hint' => [
        'sampling_reason' => 'Why the analysis was requested: routine, event, treatment, oil change.',
        'sampling_point'  => 'Where the sample was drawn from: bottom, middle or top valve.',
        'oil_brand'       => 'The commercial brand of the oil (Nynas, Shell), distinct from the oil type.',
        'volume_unit'     => 'How the equipment oil volume is measured: L, Gl, Kg, Lb, drums.',
        'stock_unit'      => 'How a stock item is counted: unit, bottle, litre, kilo, box.',
    ],

    'name'        => 'Name',
    'order'       => 'Order',
    'active'      => 'Active',
    'active_help' => 'Deactivated, it stops being offered on new samples. Issued reports do not change.',
    'add'         => 'Add',
    'new_placeholder' => 'Name of the new option',
    'empty'       => 'This list has no options yet.',
    'delete_confirm' => 'Delete this option? If it was already used on a sample, deactivating it is safer than deleting it.',

    'created' => 'Option added.',
    'saved'   => 'Option updated.',
    'deleted' => 'Option deleted.',
];
