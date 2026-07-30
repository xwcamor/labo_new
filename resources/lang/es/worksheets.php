<?php

return [

    'title'          => 'Hojas de trabajo',
    'singular'       => 'Hoja de trabajo',
    'create'         => 'Nueva hoja de trabajo',
    'edit'           => 'Editar hoja de trabajo',
    'show'           => 'Hoja de trabajo',
    'trash'          => 'Papelera de hojas de trabajo',

    // El nombre es deliberado: en el sistema viejo el menú decía "Muestras",
    // pero una hoja NO es una muestra: es una prueba corrida un día, y agrupa
    // todas las muestras que pasaron por la bancada esa jornada.
    'intro'          => 'Una hoja de trabajo es una prueba corrida un día por un analista. Agrupa las muestras del cliente, el patrón control y los duplicados de esa jornada.',

    // ── Campos ────────────────────────────────────────────────────────────
    'test_definition' => 'Prueba',
    // Encabezado del bloque de pruebas que todavía no tienen grupo asignado.
    // Va último en el desplegable: es la excepción, no por donde se empieza.
    'group_none'      => 'Sin grupo',
    'run_date'        => 'Fecha de ensayo',
    'analyst'         => 'Analista',
    'status'          => 'Estado',
    'validated_by'    => 'Validada por',
    'validated_at'    => 'Fecha de validación',
    'void_reason'     => 'Motivo de anulación',
    'ambient_temp_c'  => 'Temperatura ambiente',
    'ambient_humidity'=> 'Humedad ambiente',
    // Se imprime en el bloque de condiciones de ensayo del informe.
    'sample_temp_c'   => 'Temperatura de la muestra',
    'notes'           => 'Observaciones',
    'rows_count'      => 'Filas',
    'samples_count'   => 'Muestras',

    // ── Estados ───────────────────────────────────────────────────────────
    // Cuatro estados, no dos. En el sistema viejo "bloqueado" y "validado"
    // terminaron siendo el mismo campo, y el filtro de búsqueda quedó con las
    // etiquetas invertidas: filtrar por "Bloqueado" devolvía los desbloqueados.
    'state' => [
        'draft'     => 'En carga',
        'closed'    => 'Cerrada',   // heredado: ya no se produce
        'validated' => 'Completa',
        'voided'    => 'Dada de baja',
    ],
    'state_help' => [
        'draft'     => 'En carga. La hoja admite cambios hasta que el sistema la bloquee por antigüedad; desbloquearla después es una decisión explícita y queda auditada.',
        'closed'    => 'Estado heredado: el analista terminó y espera la revisión. Ya no se produce — hoy se valida en un solo paso.',
        'validated' => 'Completa: sus resultados ya están publicados y los patrones alimentan la carta de control. Sigue admitiendo correcciones hasta que el sistema la bloquee.',
        'voided'    => 'Dada de baja con su motivo. Sus valores crudos siguen ahí y se puede restaurar: el laboratorio responde por ella ante la auditoría.',
    ],

    // ── Tipos de fila ─────────────────────────────────────────────────────
    'kind' => [
        'sample'    => 'Muestra',
        'control'   => 'Patrón control',
        'duplicate' => 'Duplicado',
        'blank'     => 'Blanco de reactivos',
    ],
    'kind_help' => [
        'sample'    => 'Una muestra del cliente.',
        'control'   => 'Material de referencia de valor conocido. Es lo que se grafica en la carta de control.',
        'duplicate' => 'Segunda lectura de una muestra ya cargada. Mide la repetibilidad del método.',
        'blank'     => 'Solo los reactivos, sin muestra. Detecta contaminación del ensayo.',
    ],

    // ── Acciones ──────────────────────────────────────────────────────────
    'actions' => [
        'add_row'       => 'Agregar fila',
        'validate'      => 'Validar',
        'reopen'        => 'Reabrir',
        'import_file'   => 'Leer archivo del instrumento',
        'recalculate'   => 'Recalcular',
    ],
    'confirm' => [
        'validate' => 'Al validar, los resultados pasan al informe del cliente y los patrones alimentan la carta de control. Después la hoja deja de admitir cambios.',
        'void'     => 'Dar de baja requiere un motivo. La hoja queda registrada con sus valores y se puede restaurar.',
    ],

    // ── Errores ───────────────────────────────────────────────────────────
    // Todos se verifican del lado del servidor. En el sistema viejo estas
    // cuatro reglas vivían en el HTML y un envío directo las salteaba.
    'pick_sample' => 'Elegir la muestra…',
    'errors' => [
        'locked'                => 'La hoja está bloqueada por el supervisor. No admite cambios.',
        'not_draft'             => 'La hoja ya no está en carga. Solo se puede escribir mientras está en carga.',
        'not_open'              => 'Solo se puede validar una hoja en carga.',
        'missing_required'      => 'Faltan :count valores obligatorios. Complételos antes de validar la hoja.',
        'missing_prerequisites' => 'Esta prueba exige cargar primero: :kinds. No se admiten muestras hasta entonces.',
        'already_voided'        => 'La hoja ya está dada de baja. El motivo original no se reemplaza.',
        'void_reason_required'  => 'Indique el motivo de la baja.',
        'preview_too_large'     => 'Los datos enviados para el cálculo son demasiados. Guarde la fila y vuelva a intentarlo.',

        // Rango declarado de la columna. El primero es el caso del CERO: en
        // varias propiedades el 0 no es una medición sino el "no medido" del
        // sistema anterior, que obligaba a llenar la celda.
        'value_not_above'       => ':field: el valor tiene que ser mayor que :min. Si la propiedad no se midió, deje la celda vacía en vez de poner :min.',
        'value_below_min'       => ':field: el valor no puede ser menor que :min.',
        'value_above_max'       => ':field: el valor no puede ser mayor que :max.',
        'unknown_sample_test'   => 'La prueba pedida no existe. Vuelva a elegir la muestra.',
        'sample_test_other_definition' => 'Esa muestra tiene pedida otra prueba. Cárguela en la hoja que corresponde.',
    ],

    // ── Cálculo ───────────────────────────────────────────────────────────
    'computed'        => 'Calculado',
    'computed_help'   => 'Este valor lo calcula el servidor con la fórmula de la columna. No se escribe a mano.',
    'formula_cycle'   => 'Las fórmulas de esta prueba se referencian entre sí en círculo (:path). Corrija la plantilla.',
    'formula_error'   => 'La fórmula de :field no se pudo evaluar.',

    // Vista previa: el resultado que da la fórmula con lo que hay tipeado,
    // calculado por el servidor y todavía sin guardar.
    'preview_calculating' => 'Calculando…',
    'preview_hint'        => 'Vista previa calculada por el servidor con lo que hay cargado. Queda firme al guardar la fila.',
    'preview_failed'      => 'No se pudo consultar el cálculo al servidor. El valor se resuelve igual al guardar la fila.',

    // ── Valores censurados ────────────────────────────────────────────────
    'censored' => [
        'gt'    => 'Mayor que el valor indicado. El instrumento llegó a su tope.',
        'lt'    => 'Menor que el valor indicado. Por debajo del límite de detección.',
        'hint'  => 'Escriba > o < delante del número si la medición no es exacta (por ejemplo, >75).',
    ],

    // Mensajes de resultado de las acciones.
    'created'     => 'Hoja de trabajo creada.',
    'saved'       => 'Cabecera de la hoja guardada.',
    'row_saved'   => 'Fila guardada.',
    'row_deleted' => 'Fila eliminada.',
    'validated'   => 'Hoja validada. Los patrones ya alimentan la carta de control.',
    'deleted'     => 'Hoja dada de baja. Sus valores quedan guardados con el motivo.',

    // ── Equipo del que se tomó la muestra ─────────────────────────────────
    // El enlace es OPCIONAL, y lo confirmó el laboratorio: el número de muestra
    // se asocia al transformador después, y hay muestras que no vienen de ningún
    // equipo (un cilindro de aceite nuevo, un envase suelto). El resultado se
    // escribe y se informa igual. Lo que no se puede
    // equipo y no llega al informe del cliente. Se avisa, no se obliga: el
    // analista a veces carga la bancada antes de que el ingreso de la muestra
    // esté registrado.
    'equipment'                => 'Equipo',
    'equipment_hint'           => 'De dónde se tomó la muestra',
    'equipment_no_customer' => 'Sin cliente',
    'equipment_placeholder'    => 'Buscar por nombre, serie o etiqueta',
    'equipment_missing'        => 'Sin equipo',
    // El resultado SE INFORMA sin equipo: lo confirmo el laboratorio (2026-07-30).
    // El numero de muestra se asocia a un transformador despues, y a veces no se
    // asocia nunca — una muestra de un cilindro de aceite nuevo no viene de
    // ningun equipo. Lo que se pierde sin el enlace es la TENDENCIA del equipo y
    // los limites que dependen de su clase de tension, no el informe.
    'equipment_missing_help'   => 'Sin equipo el resultado se informa igual, pero no alimenta la tendencia de ningun equipo y no puede tomar los limites que dependen de su clase de tension. Si la muestra viene de un cilindro o de un envase suelto, es correcto dejarlo vacio.',
    'equipment_missing_count'  => '{0} Todas las muestras indican su equipo.|{1} 1 muestra sin equipo: su resultado se informa, pero sin tendencia por equipo ni limites por clase de tension.|[2,*] :count muestras sin equipo: sus resultados se informan, pero sin tendencia por equipo ni limites por clase de tension.',
    'equipment_na_short'       => 'No aplica',
    'equipment_not_applicable' => 'El patrón control, el duplicado y el blanco de reactivos son controles del método, no muestras de un cliente: no provienen de un equipo.',

    'kind_label'     => 'Tipo de fila',
    'instrument'     => 'Instrumento',
    'sample_code'    => 'Código de la muestra',
    'date_all'       => 'Sin filtro de fecha: se listan todas las hojas, incluidas las de años anteriores.',
    'no_edit_permission' => 'No tiene permiso para cargar valores en esta hoja.',

    'empty'          => 'Todavía no hay hojas de trabajo.',
    'empty_rows'     => 'La hoja no tiene filas. Agregue el patrón control para empezar.',

    // El informe de cromatografia la imprime: un volumen de gas depende de la
    // presion a la que se midio.
    'lab_pressure_hpa' => 'Presion atmosferica del laboratorio',
];
