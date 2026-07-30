<?php

return [

    'title' => 'Results analysis templates',
    'intro' => 'The paragraph the report prints for each test family. It is edited here and goes out on the customer paper.',
    'empty' => 'No templates seeded yet. Run the analysis templates seeder.',

    'scope_super' => 'You are editing the factory standard: the change applies to every laboratory without its own wording.',
    'scope_admin' => 'Saving a factory template creates a copy for this laboratory with your change. The standard is untouched, and "Restore" goes back to it.',
    'overridden_count' => 'This laboratory has :count template(s) with its own wording. Those no longer follow changes to the standard.',

    'factory'         => 'Factory',
    'overridden'      => 'Own wording',
    'overridden_hint' => 'This laboratory customized this template. It no longer follows changes to the standard.',
    'by_analyte'      => 'By the value of :analyte',

    'case' => [
        'none' => 'No results out of spec',
        'one'  => 'One result out of spec',
        'many' => 'Several results out of spec',
        'any'  => 'Any case',
    ],

    'family' => [
        'fisicoquimico' => 'Physical-chemical',
        'analisis_cromatografico' => 'Chromatographic analysis',
        'pcb' => 'PCB',
        'furanos' => 'Furans',
        'particulas' => 'Particles',
        'azufre_corrosivo' => 'Corrosive sulfur',
        'sedimentos' => 'Sediments',
        'metales_en_aceite' => 'Metals in oil',
        'viscocidad' => 'Viscosity',
        'dbds' => 'DBDS',
        'inflamacion' => 'Flash point',
        'fluidez' => 'Pour point',
        'inhibidor' => 'Inhibitor content',
        'grado_de_polimerizacion' => 'Degree of polymerization',
        'pasivador' => 'Passivator content',
    ],

    'markers_title' => 'Markers replaced when composing',
    'marker_ok'     => 'the tests within spec',
    'marker_failed' => 'the tests out of spec',
    'marker_norm'   => 'the criterion standard, taken from the result',
    'marker_value'  => 'the measured value',

    'body_placeholder' => 'Template text. You may use the markers above.',

    'bands_title' => 'Value bands',
    'bands_hint'  => 'Leave the minimum or maximum empty for an open band. A value must fall in exactly one band.',
    'band_min'    => 'From',
    'band_max'    => 'To',
    'band_body'   => 'Text for this band',
    'add_band'    => 'Add band',

    'origin' => 'Provenance',

    'restore'         => 'Restore',
    'restore_confirm' => 'This laboratory own wording is removed and the factory one comes back. Reports issued afterwards will use the standard text.',
    'restored'        => 'Template restored to the factory wording.',
    'restored_reason' => 'Restored to the factory wording from the editor.',
    'saved'           => 'Template saved.',

    'errors' => [
        'empty'           => 'The template needs a text or at least one band: without either, that family would print no paragraph in the report.',
        'no_tenant'       => 'Your user does not belong to a laboratory, so there is nowhere to store its own wording.',
        'factory_restore' => 'The factory template is not restored from here: to go back to the repository text, run the seeder.',
    ],

];
