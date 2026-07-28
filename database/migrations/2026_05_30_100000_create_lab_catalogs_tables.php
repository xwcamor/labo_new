<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos base del laboratorio.
 *
 * Reemplaza a `create_diagnostic_catalogs_tables` de TrafoDex, que creaba
 * cuatro tablas: `oil_types`, `transformer_types`, `standards` y `tests`.
 * Las dos primeras SÍ son del laboratorio y se conservan (la segunda ya
 * renombrada a `equipment_types`); `standards` y `tests` eran del motor de
 * diagnóstico y se rehacen en la fase 2 con otro esquema (`standards` gana
 * `kind`, `edition` y `superseded_by_id`).
 *
 * `equipment_types`, no `transformer_types`: el laboratorio recibe muestras de
 * 20 tipos de equipo (conmutadores, reactores, bushings, cables,
 * interruptores, electrobombas, intercambiadores…), no solo transformadores.
 * Llamarlo "transformador" es lo que llevó al `if tipo == 10` del sistema
 * viejo.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('oil_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->nullable()->unique();
            $table->string('code', 40)->nullable()->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('equipment_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->nullable()->unique();
            $table->string('code', 40)->nullable()->unique();
            $table->string('name', 100);
            $table->string('shape', 20)->default('tank'); // tank | pole | dry
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_types');
        Schema::dropIfExists('oil_types');
    }
};
