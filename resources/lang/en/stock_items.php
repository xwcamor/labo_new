<?php

return [
    'title'    => 'Stock items',
    'subtitle' => 'What is in stock, what is out on loan and what is left on the shelf',
    'singular' => 'Item',

    'new'    => 'New item',
    'edit'   => 'Edit item',
    'search' => 'Search by code or name',

    'code'      => 'Code',
    'name'      => 'Name',
    'unit'      => 'Unit',
    'on_hand'   => 'On hand',
    'on_hand_help' => 'What the lab states it has. It is not decremented on loan: correct it here after a purchase, a consumption or a stock count.',
    'min_qty'   => 'Minimum',
    'min_qty_help' => 'Reorder point. When available reaches it or drops below, the item is flagged in the list.',
    'location'  => 'Location',
    'active'    => 'Active',
    'active_help' => 'Inactive stops being offered when building a loan. What is already out still counts.',
    'on_loan'   => 'On loan',
    'available' => 'Available',

    'low'      => 'Below minimum',
    'low_hint' => 'Available has reached the reorder point.',

    'empty'          => 'No items yet.',
    'delete_confirm' => 'Archive this item? It stops being offered on new loans.',

    'created' => 'Item created.',
    'saved'   => 'Item updated.',
    'deleted' => 'Item archived.',

    'errors' => [
        'on_loan' => 'Cannot archive: :n units are out on loan. Register the return first.',
    ],
];
