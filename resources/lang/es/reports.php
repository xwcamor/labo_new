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
    // La norma del METODO con que se corrio el ensayo. Es COLUMNA y no una linea
    // chica debajo del nombre: quien revisa el informe compara los metodos de
    // todos los parametros entre si, y para eso tienen que caer alineados.
    'col_method'   => 'Norma',
    'col_unit'     => 'Unidad',
    'col_limit'    => 'Valor de orientación (*)',
    'col_result'   => 'Resultado',
    // El veredicto tiene su propia columna en la maqueta moderna. En el informe
    // clásico no existía: había que mirar el color del número, y el color no
    // sobrevive a una fotocopia en blanco y negro.
    'col_status'   => 'Condición',

    'limit_max'   => '(máximo)',
    'limit_min'   => '(mínimo)',
    'out_of_spec' => 'fuera de norma',
    // El valor que nadie comparó contra nada NO lleva palabra: va un guion, y la
    // columna del límite también. Escribir "sin criterio" en letras decía dos
    // veces lo mismo y llenaba de texto gris una tabla que se lee de un golpe.
    // La garantía —que el papel no afirme conformidad de lo que no comparó— la
    // da el guion igual.
    'no_criterion' => '—',
    // "Conforme", no "aprobado": el laboratorio informa que el valor cumple el
    // criterio de aceptación, no que el equipo esté bien.
    'in_spec'      => 'conforme',

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
    // 'reported_by' se quitó: era el rótulo "Reportado por:" que iba a la
    // IZQUIERDA del bloque de firmas del informe moderno. Sobraba porque cada
    // firma ya dice su relación debajo de la línea, y con dos firmantes de
    // relaciones distintas el rótulo único directamente mentía. Era el vestigio
    // del papel viejo, que tenía UNA sola firma y podía rotularla de una vez.

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
    // La misma advertencia, contada sobre LA PÁGINA que se tiene en la mano.
    // El total del informe va en la última hoja, pero una página fotocopiada
    // suelta tiene que llevar la suya.
    'note_pending'     => 'Quedan :count ensayo(s) pedidos y todavía no validados: este informe es parcial.',
    'note_no_equipment' => 'La muestra no tiene equipo asignado.',

    'verify_code' => 'Código',
    'verify_hint' => 'Verifique este informe escaneando el código',
    'no_signers'  => 'Firma',
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
    // RESPALDO del título de página cuando varias pruebas la comparten y NO
    // tienen grupo asignado en el catálogo. El título normal es el nombre del
    // grupo (Grupos de pruebas), que el laboratorio edita; esta lista solo
    // cubre a las pruebas huérfanas de grupo. Si tampoco hay texto acá, se
    // imprime la clave de la familia —feo, pero visible— en vez de una página
    // sin título.
    // ── Los títulos de las hojas del informe CLÁSICO ─────────────────────
    // Uno por familia de prueba, tal como los imprimía el sistema anterior
    // (erratas incluidas: "VISCOCIDAD" es como sale en el papel que el cliente
    // conoce). Son TEXTO: viven acá y no en `config/legacy_report.php`, que
    // guarda solo la estructura de cada hoja.
    'family' => [
        'fisicoquimico'            => 'ENSAYOS FISICO-QUIMICOS',
        'analisis_cromatografico'  => 'CROMATOGRÁFICO',
        'pcb'                      => 'ENSAYO DE PCB',
        'furanos'                  => 'ENSAYO DE FURANOS',
        'particulas'               => 'ENSAYO DE PARTÍCULAS',
        'azufre_corrosivo'         => 'AZUFRE CORROSIVO',
        'sedimentos'               => 'ENSAYO DE SEDIMENTOS',
        'metales_en_aceite'                  => 'ENSAYO DE METALES',
        'viscocidad'               => 'VISCOCIDAD',
        'dbds'                     => 'ENSAYO DE DBDS',
        'inflamacion'              => 'ENSAYO DE PUNTO DE INFLAMACIÓN',
        'fluidez'                  => 'PUNTO DE FLUIDEZ',
        'inhibidor'                => 'CONTENIDO DE INHIBIDOR',
        'grado_de_polimerizacion'  => 'GRADO DE POLIMERIZACIÓN',
        'pasivador'                => 'CONTENIDO DE PASIVADOR',
    ],

    // El rótulo de la columna del parámetro, que cambia por familia. Sin entrada
    // se usa "ENSAYO".
    'legacy_col3' => [
        'analisis_cromatografico' => 'GAS',
        'furanos'                 => 'COMPUESTO',
        'particulas'              => 'TAMAÑO DE PARTICULA',
        'sedimentos'              => 'COMPUESTO',
        'metales_en_aceite'                 => 'COMPUESTO',
        'dbds'                    => 'COMPUESTO',
        'pasivador'               => 'COMPUESTO',
    ],

    // La TÉCNICA instrumental de la columna MÉTODO, distinta de la norma. En el
    // sistema anterior estaba escrita dentro del partial de cada prueba.
    'legacy_technique' => [
        'pcb'         => 'Cromatografía de gases',
        'furanos'     => 'HPLC',
        'particulas'  => 'Conteo óptico',
        'sedimentos'  => 'Gravimétrico',
        'metales_en_aceite'     => 'ICP-AES',
        'dbds'        => 'GC-MS',
        'inflamacion' => 'Copa abierta',
        'pasivador'   => 'HPLC',
    ],

    // La nota al pie de la hoja de furanos.
    'legacy_note_chendong' => '(*) Para el cálculo del grado de polimerización se utiliza la correlación de Chendong sobre el 2-furfuraldehído.',
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

    'cond_lab_pressure' => 'Presión atmosférica lab',
];
