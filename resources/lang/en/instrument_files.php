<?php

return [

    'title'    => 'Instrument files',
    'singular' => 'Instrument file',

    'intro'    => 'The raw file the measuring instrument produces. It is stored exactly as uploaded and read with the matching format; the values found are pre-filled into the worksheet for the analyst to confirm.',

    'format'        => 'Format',
    'original_name' => 'File',
    'size'          => 'Size',
    'rows_parsed'   => 'Values found',
    'status'        => 'Status',

    'state' => [
        'uploaded' => 'Uploaded',
        'parsed'   => 'Parsed',
        'failed'   => 'No matches',
    ],

    'upload'   => 'Upload instrument file',
    'unmatched' => 'Not found in the file: :fields',
    'confirm'  => 'Review the values before saving. The file only pre-fills the form.',

    'errors' => [
        'nothing_matched' => 'None of the configured values was found. Check that the chosen format matches this instrument.',
        'not_editable'    => 'The worksheet does not accept changes.',
    ],

];
