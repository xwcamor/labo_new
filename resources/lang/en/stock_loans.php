<?php

return [
    'title'    => 'Stock loans',
    'subtitle' => 'Who took what, and what is still out',
    'singular' => 'Loan',

    'new'    => 'New loan',
    'edit'   => 'Edit loan',
    'search' => 'Search by borrower or purpose',

    'loaned_on'    => 'Loan date',
    'borrower'     => 'Borrower',
    'borrower_help' => 'Who takes the material: a system user, or a written name for someone external. Required — without it the record cannot be used to track the material down.',
    'borrower_user' => 'User',
    'borrower_name' => 'External',
    'borrower_name_placeholder' => 'Name of the person taking it',
    'purpose'      => 'Purpose',
    'purpose_placeholder' => 'What the material is for',
    'created_by'   => 'Recorded by',

    'lines'      => 'Items on loan',
    'item'       => 'Item',
    'qty'        => 'Quantity',
    'notes'      => 'Note',
    'add_line'   => 'Add item',
    'remove_line' => 'Remove',
    'available_n' => 'available: :n',

    'status'     => 'Status',
    'status_open'     => 'Still out',
    'status_returned' => 'Returned',
    'pending'    => 'Pending',
    'returned'   => 'Returned',
    'returned_at' => 'Closed on',

    'returns'        => 'Returns',
    'new_return'     => 'Register a return',
    'returned_on'    => 'Return date',
    'return_qty'     => 'Quantity returned',
    'no_returns'     => 'No returns yet.',
    'return_delete_confirm' => 'Delete this return? The loan reopens for that quantity.',

    'empty'          => 'No loans yet.',
    'delete_confirm' => 'Delete this loan? Its lines and returns go with it.',

    'created'        => 'Loan recorded.',
    'saved'          => 'Loan updated.',
    'deleted'        => 'Loan deleted.',
    'return_saved'   => 'Return recorded.',
    'return_deleted' => 'Return deleted.',

    'header_note' => 'Only the header fields are editable. If the lines are wrong, delete the loan and record it again: changing a quantity that already has returns would leave those returns hanging off a number that no longer exists.',

    'errors' => [
        'borrower_required'  => 'Say who is taking the material: a user or a name.',
        'over_available'     => 'Not that many units of ":item": :n available.',
        'over_return'        => 'Cannot return more than what is still out: :n units left.',
        'return_before_loan' => 'The return cannot predate the loan.',
        'future_loan'        => 'The loan date cannot be in the future.',
        'future_return'      => 'The return date cannot be in the future.',
    ],
];
