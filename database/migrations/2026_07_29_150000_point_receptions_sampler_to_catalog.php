<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién tomó la muestra es un CATÁLOGO, no un usuario del sistema.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ESTABA MAL                                                       │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `receptions.sampler_id` apuntaba a `users`, con un `sampler_name` de texto
 * libre al lado como escape para "los que no son usuarios del sistema".
 *
 * Pero es que casi ninguno lo es. El catálogo real del laboratorio tiene doce
 * entradas y ninguna es una persona: LABORATORIO, SERVICE CAMPO, REPARACIONES,
 * CLIENTE INTERNO, CLIENTE, PPMV, PPHV, PA, PS, DM, ABB, SUBCONTRATISTA. Son
 * áreas propias, el cliente, y terceros. El informe acreditado imprime
 * justamente eso: "Muestra extraída por: Cliente".
 *
 * Con la clave apuntando a `users`, el 90% de las entregas caía al texto libre.
 * Y texto libre significa que "Cliente", "cliente" y "CLIENTE " son tres
 * muestreadores distintos: no se puede filtrar por muestreador, no se puede
 * contar cuántas muestras tomó cada tercero, y el informe imprime lo que alguien
 * tipeó ese día. Es el mismo error que el sistema anterior cometía al unir la
 * muestra con la hoja por texto.
 *
 * `sampler_name` se conserva —nullable y sin uso nuevo— hasta que los datos
 * cargados se pasen al catálogo. No se borra en la misma migración que cambia la
 * clave: eso tiraría el dato antes de poder traspasarlo.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->dropForeign(['sampler_id']);
            $table->foreign('sampler_id')->references('id')->on('samplers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->dropForeign(['sampler_id']);
            $table->foreign('sampler_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
