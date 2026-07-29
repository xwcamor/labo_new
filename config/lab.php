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

];
