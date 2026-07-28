<?php

/*
|--------------------------------------------------------------------------
| Tipos de columna de una hoja de trabajo
|--------------------------------------------------------------------------
|
| Esto reemplaza la tabla `lab_category_sub_detail_types` del sistema Rails
| viejo, y el cambio de tabla a configuración es deliberado.
|
| Allá era un CRUD: cuatro filas (Texto=1, Número=2, Selección=3, Fecha=4) que
| el supervisor podía editar desde la pantalla de ajustes. Pero los ids 1/2/3/4
| estaban escritos a mano en unas diez vistas, siempre con el mismo comentario
| repetido: "HTML TAGS: INPUT =1,2 SELECT: 3 DATE: 4". Agregar un quinto tipo
| desde la interfaz guardaba la fila y no hacía absolutamente nada: ninguna
| vista sabía renderizarlo y la celda salía en blanco.
|
| O sea: parecía dato editable y era código disfrazado. Un tipo de columna no
| es un valor configurable, es un COMPORTAMIENTO — cómo se pinta el control,
| cómo se valida, cómo se guarda, si admite opciones, si admite fórmula. Nada
| de eso se puede declarar en una fila con un nombre.
|
| Lo que SÍ es dato editable por el laboratorio son las columnas de cada prueba
| (`test_fields`), sus opciones, sus fórmulas, sus unidades y sus límites. Eso
| vive en la base y se edita sin tocar código, que es como tiene que ser.
|
| La pantalla "Tipos de Columnas" pasa a ser una referencia de solo lectura que
| se arma leyendo este archivo. Agregar un tipo nuevo es una decisión de
| programación, y ahora se nota que lo es.
|
| Cada entrada declara:
|   storage      en qué columna de worksheet_values cae el dato
|   options      si el tipo admite opciones (test_field_options)
|   formula      si el tipo admite fórmula
|   numeric      si participa de cálculos, promedios y gráficos
|   legacy_id    el id que tenía en lab_category_sub_detail_types, para el ETL
|
*/

return [

    'text' => [
        'storage'   => 'value_text',
        'options'   => false,
        'formula'   => false,
        'numeric'   => false,
        'legacy_id' => 1,
    ],

    'number' => [
        'storage'   => 'value_num',
        'options'   => false,
        'formula'   => false,
        'numeric'   => true,
        'legacy_id' => 2,
    ],

    // El sistema viejo guardaba en la celda el ID de la opción, no su texto,
    // en la misma columna de texto que todo lo demás. Si alguien borraba una
    // opción, el histórico quedaba apuntando a un id inexistente. Acá el
    // vínculo es una clave foránea y las opciones se dan de baja, no se borran.
    'select' => [
        'storage'   => 'option_id',
        'options'   => true,
        'formula'   => false,
        'numeric'   => false,
        'legacy_id' => 3,
    ],

    'date' => [
        'storage'   => 'value_text',
        'options'   => false,
        'formula'   => false,
        'numeric'   => false,
        'legacy_id' => 4,
    ],

    // Campo calculado. En el sistema viejo no era un tipo: era la bandera
    // `is_blur` sobre una columna de número, más un bloque de JavaScript crudo
    // guardado en la base e inyectado con html_safe en la página. El servidor
    // no calculaba ni verificaba nada, así que un POST directo escribía
    // cualquier cosa en el resultado, y cuando la fórmula operaba sobre un
    // campo vacío quedaba el texto "NaN" guardado en la base — el sistema
    // tenía un panel entero dedicado a cazar esos registros.
    //
    // Acá la fórmula se evalúa en el servidor (App\Services\Lab) y el campo es
    // de solo lectura de verdad.
    'computed' => [
        'storage'   => 'value_num',
        'options'   => false,
        'formula'   => true,
        'numeric'   => true,
        'legacy_id' => null,
    ],

    // Referencia a un instrumento de bancada. Antes era un `select` cuyas
    // opciones eran textos como "Bureta PP-LA-01C", con el código de
    // calibración adentro del nombre.
    // Va por valor y no por fila porque una misma prueba usa varios: el
    // Número Ácido lleva bureta y balanza, y son dos columnas de la hoja.
    'instrument' => [
        'storage'   => 'instrument_id',
        'options'   => false,
        'formula'   => false,
        'numeric'   => false,
        'legacy_id' => null,
    ],

];
