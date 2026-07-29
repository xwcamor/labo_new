<?php

return [

    /*
    |--------------------------------------------------------------------------
    | De quién son los datos que siembra el sistema
    |--------------------------------------------------------------------------
    |
    | Las pruebas, sus grupos, los parámetros medibles, las normas y los cuadros
    | de límites se sembraban SIN empresa (`tenant_id` nulo), que en este
    | sistema significa "catálogo compartido por todos los workspaces". La idea
    | era que fueran el estándar de fábrica y que cada laboratorio lo
    | personalizara encima.
    |
    | No es lo que corresponde acá: estos datos son del laboratorio: sus 29
    | pruebas, sus columnas, sus instrumentos y sus criterios, sacados de SU
    | sistema anterior. Sembrarlos como compartidos los pondría en manos de
    | cualquier workspace que se cree después, y dejaría al laboratorio sin
    | poder editarlos sin ser super.
    |
    | Qué se pierde: si mañana entra un segundo laboratorio, arranca vacío y hay
    | que sembrarle lo suyo. Es lo correcto —sus pruebas no son las de este— y
    | el día que se quiera un catálogo de fábrica compartido, se pone `null`
    | acá y vuelve el comportamiento anterior sin tocar código.
    |
    */

    'seed_tenant_id' => env('LAB_SEED_TENANT_ID', 1),

    /*
    |--------------------------------------------------------------------------
    | Qué pruebas comparten tabla en el informe
    |--------------------------------------------------------------------------
    |
    | El informe acreditado dedica UNA página a "ENSAYOS FISICO-QUIMICOS" con
    | las trece pruebas en una sola tabla —cada fila con su propia norma: D974
    | el número ácido, D1816 la rigidez— y una página a cada una de las demás
    | (cromatografía, PCB, furanos…). Trece páginas de una fila cada una, todas
    | repitiendo la cabecera entera, no es el formato acreditado.
    |
    | Acá se declara por GRUPO de la prueba, no con la lista de los trece
    | códigos: si el laboratorio agrega mañana una prueba fisicoquímica, entra
    | sola en la tabla. El grupo que no figure acá deja a cada prueba con su
    | propia página, que es el default.
    |
    | Esto fija el valor INICIAL de `test_definitions.report_comment_group`
    | cuando está vacío. A partir de ahí manda la base: el laboratorio reagrupa
    | desde el editor de la prueba, y ni el importador ni las migraciones le
    | pisan esa decisión.
    |
    */

    'report_families' => [
        // código del grupo => familia del informe
        'fisico_quimico' => 'fisicoquimico',
    ],

    /*
    | Excepciones POR PRUEBA, para las familias que no coinciden con un grupo.
    |
    | Los tres ensayos de azufre viven en el grupo "Otros" —junto a PCB, furanos
    | y todo lo demás— pero el informe acreditado los imprime en UNA sola hoja,
    | con sus tres tablas. Declararlo por grupo mandaría a los quince ensayos de
    | "Otros" a la misma página, que no es lo que hace el papel.
    */

    'report_families_by_test' => [
        'azufre_1275b'          => 'azufre_corrosivo',
        'azufre_62535_48_horas' => 'azufre_corrosivo',
        'azufre_62535_72_horas' => 'azufre_corrosivo',
    ],

];
