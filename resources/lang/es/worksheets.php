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
    'run_date'        => 'Fecha de ensayo',
    'analyst'         => 'Analista',
    'status'          => 'Estado',
    'validated_by'    => 'Validada por',
    'validated_at'    => 'Fecha de validación',
    'void_reason'     => 'Motivo de anulación',
    'ambient_temp_c'  => 'Temperatura ambiente',
    'ambient_humidity'=> 'Humedad ambiente',
    'notes'           => 'Observaciones',
    'rows_count'      => 'Filas',
    'samples_count'   => 'Muestras',

    // ── Estados ───────────────────────────────────────────────────────────
    // Cuatro estados, no dos. En el sistema viejo "bloqueado" y "validado"
    // terminaron siendo el mismo campo, y el filtro de búsqueda quedó con las
    // etiquetas invertidas: filtrar por "Bloqueado" devolvía los desbloqueados.
    'state' => [
        'draft'     => 'En carga',
        'closed'    => 'Cerrada',
        'validated' => 'Validada',
        'voided'    => 'Anulada',
    ],
    'state_help' => [
        'draft'     => 'El analista todavía está cargando. Es el único estado en el que se puede escribir.',
        'closed'    => 'El analista terminó. Espera la revisión del supervisor.',
        'validated' => 'El supervisor la revisó y la firmó. Los patrones ya alimentan la carta de control.',
        'voided'    => 'Anulada con motivo. No se borra: el laboratorio responde por ella ante la auditoría.',
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
        'close'         => 'Cerrar hoja',
        'validate'      => 'Validar',
        'void'          => 'Anular',
        'reopen'        => 'Reabrir',
        'import_file'   => 'Leer archivo del instrumento',
        'recalculate'   => 'Recalcular',
    ],
    'confirm' => [
        'close'    => '¿Cerrar la hoja? Después de cerrarla no se pueden cargar más valores.',
        'validate' => 'Al validar, los patrones de esta hoja pasan a alimentar la carta de control.',
        'void'     => 'Anular requiere un motivo. La hoja queda registrada, no se borra.',
    ],

    // ── Errores ───────────────────────────────────────────────────────────
    // Todos se verifican del lado del servidor. En el sistema viejo estas
    // cuatro reglas vivían en el HTML y un envío directo las salteaba.
    'errors' => [
        'locked'                => 'La hoja está bloqueada por el supervisor. No admite cambios.',
        'not_draft'             => 'La hoja ya no está en carga. Solo se puede escribir mientras está en carga.',
        'not_closed'            => 'Solo se puede validar una hoja cerrada.',
        'missing_required'      => 'Faltan :count valores obligatorios. Complételos antes de cerrar la hoja.',
        'missing_prerequisites' => 'Esta prueba exige cargar primero: :kinds. No se admiten muestras hasta entonces.',
        'already_voided'        => 'La hoja ya está anulada. El motivo original no se reemplaza.',
        'void_reason_required'  => 'Indique el motivo de la anulación.',
    ],

    // ── Cálculo ───────────────────────────────────────────────────────────
    'computed'        => 'Calculado',
    'computed_help'   => 'Este valor lo calcula el servidor con la fórmula de la columna. No se escribe a mano.',
    'formula_cycle'   => 'Las fórmulas de esta prueba se referencian entre sí en círculo (:path). Corrija la plantilla.',
    'formula_error'   => 'La fórmula de :field no se pudo evaluar.',

    // ── Valores censurados ────────────────────────────────────────────────
    'censored' => [
        'gt'    => 'Mayor que el valor indicado. El instrumento llegó a su tope.',
        'lt'    => 'Menor que el valor indicado. Por debajo del límite de detección.',
        'hint'  => 'Escriba > o < delante del número si la medición no es exacta (por ejemplo, >75).',
    ],

    // Mensajes de resultado de las acciones.
    'created'     => 'Hoja de trabajo creada.',
    'row_saved'   => 'Fila guardada.',
    'row_deleted' => 'Fila eliminada.',
    'closed'      => 'Hoja cerrada. Queda a la espera de la validación del supervisor.',
    'validated'   => 'Hoja validada. Los patrones ya alimentan la carta de control.',
    'voided'      => 'Hoja anulada.',

    'kind_label'     => 'Tipo de fila',
    'instrument'     => 'Instrumento',
    'sample_code'    => 'Código de la muestra',
    'date_all'       => 'Sin filtro de fecha: se listan todas las hojas, incluidas las de años anteriores.',
    'no_edit_permission' => 'No tiene permiso para cargar valores en esta hoja.',

    'empty'          => 'Todavía no hay hojas de trabajo.',
    'empty_rows'     => 'La hoja no tiene filas. Agregue el patrón control para empezar.',

];
