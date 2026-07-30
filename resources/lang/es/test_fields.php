<?php

return [

    'title'     => 'Columnas de la prueba',
    'singular'  => 'Columna',
    'create'    => 'Agregar columna',
    'edit'      => 'Editar columna',

    'intro'     => 'Las columnas son la hoja de trabajo de la prueba: lo que el analista ve y llena en la bancada. Agregar, quitar o reordenar columnas no necesita programación.',

    // ── Campos ────────────────────────────────────────────────────────────
    'code'   => 'Código',
    'code_help' => 'El nombre con el que las fórmulas se refieren a esta columna. Solo minúsculas, números y guion bajo. No lo ve el analista.',
    'label'  => 'Rótulo',
    'label_help' => 'El texto que se muestra en la cabecera de la hoja.',
    'type'   => 'Tipo',
    'role'   => 'Rol',
    'unit'   => 'Unidad',
    'decimals' => 'Decimales',
    'min_value' => 'Mínimo aceptable',
    'max_value' => 'Máximo aceptable',
    'range_help' => 'Rango de lo físicamente posible, para atajar un error de tipeo. No es el límite de la norma: eso se define aparte.',
    'is_required'    => 'Obligatoria',
    'is_locked'      => 'Solo lectura',
    'is_reusable'    => 'Constante',
    'is_reusable_help' => 'El valor se arrastra a la muestra siguiente. Se usa para el factor de la solución titulante o la temperatura ambiente.',
    'default_value'  => 'Valor constante',
    'report_visible' => 'Mostrar en el informe',
    'report_visible_help' => 'Decide si esta columna sale impresa en el informe del cliente. Las columnas intermedias del cálculo normalmente no se publican.',
    'detection_limit' => 'Límite de detección',
    'detection_limit_help' => 'Por debajo de este número el informe imprime "< límite" en vez del valor medido: el método no distingue 0.4 de 0.7, y publicar el número sugiere una precisión que el ensayo no tiene. NO participa del veredicto, que se decide con el valor real.',
    'replicates'     => 'Mediciones por muestra',
    'replicates_help' => 'Cuántas veces se repite esta medición sobre la misma muestra. La rigidez dieléctrica se mide cinco o seis veces y se promedia.',
    'sort_order'     => 'Orden',
    'options'        => 'Opciones',
    'is_accredited'  => 'Acreditado',
    'is_accredited_help' => 'El método está dentro del alcance acreditado del laboratorio. Solo las páginas del informe que llevan un método acreditado muestran el sello del organismo y el párrafo del certificado.',
    'accreditation_flag' => 'Marca',
    'accreditation_flag_help' => 'El superíndice que se imprime al lado de la norma: (A), (NA).',
    'is_hidden'      => 'Oculta',

    // ── Tipos de columna ──────────────────────────────────────────────────
    'types' => [
        'text'       => 'Texto',
        'number'     => 'Número',
        'select'     => 'Selección',
        'date'       => 'Fecha',
        'computed'   => 'Calculada',
        'instrument' => 'Instrumento',
    ],
    'types_help' => [
        'text'       => 'Texto libre. No participa de los cálculos.',
        'number'     => 'Un valor medido. Admite > y < para las mediciones no exactas.',
        'select'     => 'Una lista cerrada de opciones. La opción elegida se guarda por referencia, así que renombrar la lista no cambia lo que dice un ensayo ya cerrado.',
        'date'       => 'Una fecha.',
        'computed'   => 'La calcula el servidor con su fórmula. El analista no la escribe.',
        'instrument' => 'Con qué equipo de bancada se tomó el valor. Trae su estado de calibración.',
    ],

    // ── Roles ─────────────────────────────────────────────────────────────
    // Esto es lo que el sistema anterior deducía por posición: la columna 1 era
    // el número de muestra, la 2 la norma y la última el resultado. Reordenar
    // el cuadro rompía el enlace con la muestra, la norma del informe y el
    // gráfico de tendencias, sin ningún aviso.
    'roles' => [
        'none'        => 'Columna común',
        'sample_code' => 'Código de la muestra',
        'standard'    => 'Norma del ensayo',
        'result'      => 'Resultado',
        'temperature' => 'Temperatura del ensayo',
        'observation' => 'Observación del analista',
    ],
    'roles_help' => [
        'none'        => 'Un dato de la bancada, sin significado especial.',
        'sample_code' => 'Enlaza la fila con la muestra del cliente. Debe haber una sola.',
        'standard'    => 'Con qué norma se ensayó. Va al informe.',
        'result'      => 'Un resultado de la prueba. Alimenta el parámetro que se indique. Una prueba puede tener varios: cromatografía tiene nueve.',
        'temperature' => 'Condición del ensayo, no resultado.',
        'observation' => 'Nota del analista sobre esa fila.',
    ],
    'role_help_intro' => 'Declarar el rol es lo que permite reordenar las columnas sin romper nada.',

    'output_analyte'      => 'Parámetro que alimenta',
    'output_analyte_help' => 'A qué parámetro medible corresponde este resultado. Es lo que permite al informe y a las tendencias buscar por parámetro en vez de por posición de columna.',

    // ── Fórmula ───────────────────────────────────────────────────────────
    'formula'      => 'Fórmula',
    'formula_help' => 'Una expresión sobre los códigos de las otras columnas de esta prueba. Por ejemplo: (volumen_gastado - volumen_blanco) * factor_koh / peso_aceite',
    'formula_functions' => 'Funciones disponibles: abs, sqrt, log10, ln, exp, pow, round, min, max, sum, avg.',
    'formula_check'  => 'Comprobar',
    'formula_valid'  => 'La fórmula es válida.',
    'formula_uses'   => 'Usa: :fields',
    'formula_server' => 'La fórmula se evalúa en el servidor. El valor que queda guardado es el que calcula el servidor, no el que muestra el navegador.',

    // ── Errores ───────────────────────────────────────────────────────────
    'errors' => [
        'used_by_formula' => 'No se puede eliminar: la fórmula de :fields usa esta columna. Corrija esas fórmulas primero.',
        'formula_cycle'   => 'Las fórmulas se referencian en círculo (:path). Ninguna se podría calcular.',
        'unknown_field'   => 'La fórmula usa :field, que no es una columna de esta prueba.',
        'code_taken'      => 'Ya existe una columna con ese código en esta prueba.',
    ],

    // ── Mensajes ──────────────────────────────────────────────────────────
    'created'   => 'Columna agregada.',
    'updated'   => 'Columna actualizada.',
    'deleted'   => 'Columna eliminada.',
    'reordered' => 'Orden actualizado.',
    'reorder_safe' => 'El orden es solo presentación: las fórmulas usan códigos y los roles están declarados, así que reordenar no cambia ningún cálculo.',
    // Lo que dice la franja del pie cuando hay un reordenamiento sin guardar.
    // No repite el texto de arriba: ahí se explica que reordenar es seguro,
    // acá se avisa que el cambio todavía NO está en la plantilla.
    'reorder_pending' => 'El nuevo orden todavía no está guardado.',

    // ── Valores constantes ────────────────────────────────────────────────
    'constants'       => 'Valores constantes',
    'constants_intro' => 'Los valores que se arrastran de una muestra a la siguiente. Cámbielos cuando titule una solución nueva o cambie la condición de la sala.',
    'constants_updated' => 'Valores constantes actualizados.',
    // Lo que dice la franja del pie cuando hay cambios sin guardar.
    'constants_pending' => 'Hay valores cambiados y todavía sin guardar.',
    'constants_empty' => 'Esta prueba no tiene columnas marcadas como constantes.',

    'empty' => 'La prueba todavía no tiene columnas.',

];
