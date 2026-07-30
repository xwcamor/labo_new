<?php

return [
    'title'    => 'Informe de ensayo',
    'subtitle' => 'Resultados de ensayo de aceite dieléctrico',
    'emitted'  => 'Emitido',

    // ── La banda y las dos tablas de cabecera ───────────────────────────
    // Se repiten en TODAS las páginas: una hoja suelta tiene que poder
    // identificarse sola. Los rótulos son los del informe acreditado.
    'header_title'   => 'INFORME DE RESULTADOS',
    'customer_info'  => 'INFORMACIÓN DEL CLIENTE',
    'equipment_info' => 'INFORMACIÓN DEL EQUIPO (DATOS PROPORCIONADOS POR EL CLIENTE)',

    'customer'      => 'Cliente y muestra',
    'customer_name' => 'Cliente',
    'address'       => 'Dirección',
    'contact'       => 'Contacto',
    'end_user'      => 'Usuario final',
    'service_order' => 'Nº orden de servicio',
    'sampled_at'    => 'Fecha de muestreo',
    'received_at'   => 'Fecha de recepción (dd-mm-aa)',
    'issued_at'     => 'Fecha de emisión (dd-mm-aa)',
    'sampler'       => 'Muestra extraída por',
    'sample_description' => 'Descripción muestra',

    'equipment'      => 'Equipo',
    'equipment_name' => 'Equipo',
    'serial'         => 'Serie',
    'tag'            => 'Código de cliente / TAG',
    'equipment_type' => 'Tipo de equipo',
    'oil_type'       => 'Tipo de aceite',
    'voltage'        => 'Tensión (kV)',
    'power'          => 'Potencia (MVA)',
    'location'       => 'Locación',
    'sampling_point' => 'Punto de muestreo',
    'preservation'   => 'Sistema de preservación',
    'sampling_reason' => 'Razón de muestreo',
    'brand'          => 'Fabricante',
    'oil_brand'      => 'Marca de aceite',
    'manufacture_year' => 'Año de fabricación',
    'oil_qty'        => 'Cant. de aceite',
    'tap_changer'    => 'Conmutador',
    'in_service'     => 'En operación',
    // Las cuatro condiciones de CAMPO, al tomar la muestra.
    'oil_temp'       => 'Temp. aceite transform. (°C)',
    'equipment_temp' => 'Temp. aceite campo (°C)',
    'ambient_temp'   => 'Temp. amb. campo (°C)',
    'humidity'       => 'Hum. relat. campo (%HR)',

    // ── La tabla de resultados ──────────────────────────────────────────
    'results_title' => 'RESULTADOS DE ENSAYOS',
    'col_item'     => 'Ítem',
    'col_standard' => 'Norma',
    'col_test'     => 'Ensayo',
    'col_unit'     => 'Unidad',
    'col_limit'    => 'Valor de orientación (*)',
    'col_result'   => 'Resultado',

    'limit_max'   => '(máximo)',
    'limit_min'   => '(mínimo)',
    'out_of_spec' => 'fuera de norma',
    // El valor que nadie comparó contra nada lleva la palabra, no solo el
    // gris: el color no sobrevive a una fotocopia en blanco y negro.
    'no_criterion' => 'sin criterio',

    // ── Las notas al pie de cada página de ensayo ───────────────────────
    // La leyenda de las marcas de acreditación del método. Explica el
    // superíndice de la columna NORMA, así que solo se imprime cuando hay uno.
    'foot_accredited'     => '(A) Acreditado',
    'foot_not_accredited' => '(NA) No acreditado',
    // El párrafo de la acreditación (organismo, certificado y alcance) NO vive
    // acá: es un dato del laboratorio, `tenants.accreditation_note`. El número
    // de certificado vence y otro laboratorio se acredita con otro organismo.

    /*
     * Notas al pie PROPIAS DE UNA PRUEBA, por código de prueba.
     *
     * En el sistema anterior la del tipo de celda estaba escrita dentro del
     * parcial de fisicoquímicos, así que cambiar la celda del espinterómetro
     * —una decisión del laboratorio, no del programa— exigía tocar HTML y
     * volver a desplegar. Acá se agrega una línea acá y listo: sin migración y
     * sin sembrador. La prueba que no figura no imprime ninguna línea.
     */
    'test_footnote' => [
        'rigidez_dielectrica' => '(1) Tipo de celda: MC2A, tensión (RMS): 2000 VCA / 500 VDC',
    ],

    // ── Las condiciones de ensayo (una tabla por página, de su bancada) ──
    'cond_standard'      => '(*) Norma de referencia',
    'cond_run_date'      => 'Fecha de análisis',
    'cond_sample_temp'   => 'Temp. de muestra en laboratorio',
    'cond_lab_temp'      => 'Temperatura lab',
    'cond_lab_humidity'  => 'Humedad relativa lab',

    'page_of' => 'Página :num de :total',
    'reported_by' => 'Reportado por:',

    // ── La última página ────────────────────────────────────────────────
    // El título y el "sin análisis" viven más abajo, con el resto de las claves
    // del motor de diagnóstico: son las mismas para el informe y la pantalla.
    // Por qué esta página no lleva el sello de acreditación: una opinión no es
    // un resultado de ensayo y queda fuera del alcance acreditado. El informe
    // anterior ya lo hacía así (usaba el logo "parcial", sin el del organismo).
    'analysis_scope' => 'Las opiniones e interpretaciones de esta página quedan fuera del alcance de la acreditación.',

    'no_results' => 'Esta muestra todavía no tiene ensayos validados.',

    // Advertencias impresas. Un informe que calla lo que le falta se lee como
    // completo, y ahí es donde un valor sin criterio pasa por conforme.
    'note_no_criteria' => 'Hay :count resultado(s) sin criterio de aceptación aplicable: se muestran sin comparar contra ningún límite. NO deben leerse como conformes.',
    // La misma advertencia, contada sobre LA PÁGINA que se tiene en la mano.
    // El total del informe va en la última hoja, pero una página fotocopiada
    // suelta tiene que llevar la suya.
    'note_no_criteria_page' => ':count resultado(s) de esta página sin criterio de aceptación aplicable: se imprimen sin comparar contra ningún límite y NO deben leerse como conformes.',
    'note_pending'     => 'Quedan :count ensayo(s) pedidos y todavía no validados: este informe es parcial.',
    'note_no_equipment' => 'La muestra no tiene equipo asignado.',

    'verify_code' => 'Código',
    'verify_hint' => 'Verifique este informe escaneando el código',
    'no_signers'  => 'Firma',
    'relation'    => [
        'prepared'  => 'Realizado por',
        'reviewed'  => 'Revisado por',
        'approved'  => 'Aprobado por',
        'authorized'=> 'Autorizado por',
        'verified'  => 'Verificado por',
        'endorsed'  => 'Avalado por',
    ],
    'verify_sample'    => 'Muestra',
    'verify_equipment' => 'Equipo',
    'verify_sections'  => 'Ensayos informados',
    // ── Portal público de verificación (el destino del QR) ──────────────
    'verify_title'      => 'Verificación de informe',
    'verify_ok'         => 'Informe verificado',
    'verify_ok_sub'     => 'Este código corresponde a un informe emitido por el sistema.',
    'verify_fail'       => 'Código no encontrado',
    'verify_fail_sub'   => 'El código :code no corresponde a ningún informe emitido. Revise que esté completo.',
    'verify_form_hint'  => 'Ingrese el código impreso en el informe para comprobar que es auténtico.',
    'verify_form_btn'   => 'Verificar',
    'verify_foot'       => 'Esta página solo confirma que el informe salió del sistema. No publica los resultados del ensayo.',
    'verify_issued_at'  => 'Fecha de emisión',
    'verify_issued_by'  => 'Emitido por',
    'verify_signers'    => 'Firmantes',
    'verify_match_hint' => 'Si algún dato no coincide con el papel que tiene en la mano, el documento fue alterado.',
    'verify_serial'     => 'Serie',
    'code'              => 'Informe',
    'health_index'      => 'Índice de salud',

    'and' => 'y',
    // Une el valor medido con el parámetro dentro de una enumeración del
    // análisis: "7.3 ppm DE aluminio y 2.1 ppm DE cobre".
    'of'  => 'de',
    'analysis_title' => 'ANÁLISIS DE RESULTADOS (opiniones e interpretaciones)',
    'analysis_empty'  => 'Sin análisis cargado para esta familia de ensayos.',
    'analysis_edited' => 'Editado por el analista',
    // El título de la PÁGINA cuando varias pruebas la comparten. Sale de
    // `test_definitions.report_comment_group`; si una familia no tiene texto
    // acá, se imprime su clave —feo, pero visible— en vez de una página sin
    // título.
    'family' => [
        'azufre_corrosivo' => 'AZUFRE CORROSIVO',
        'fisicoquimico' => 'ENSAYOS FISICO-QUIMICOS',
    ],
    'footer_legend' => 'Los resultados corresponden únicamente a la muestra recibida. El valor de orientación es el criterio de aceptación aplicable a este equipo según la norma indicada en cada fila.',
    'footer_accreditation' => '(A) método acreditado · (NA) método no acreditado. La marca corresponde al método con el que se corrió este ensayo.',
    'generated_by'  => 'Emitido por: :name',
    // Las fichas de resumen de la primera pagina del informe moderno: lo
    // primero que se pregunta quien lo abre. Se cuentan sobre el estado
    // congelado de cada resultado, el mismo que imprimen las tablas.
    'sample'         => 'Muestra',
    'sum_tests'      => 'Parametros informados',
    'sum_tests_note' => '{0} Sin pruebas|{1} en :count prueba|[2,*] en :count pruebas',
    'sum_out'        => 'Fuera de norma',
    'sum_out_note'   => 'Revisar el analisis de resultados.',
    'sum_ok_note'    => 'Todo dentro de norma.',
    'sum_no_spec'    => 'Sin criterio',
    'sum_no_spec_note' => 'Sin limite aplicable declarado.',

    'cond_lab_pressure' => 'Presion atmosferica lab',
];
