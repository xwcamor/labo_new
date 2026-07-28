<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos base del laboratorio.
 *
 * Reemplaza a `create_diagnostic_catalogs_tables` de TrafoDex, que creaba
 * cuatro tablas: `oil_types`, `transformer_types`, `standards` y `tests`.
 * Las dos primeras SÍ son del laboratorio y se conservan; `standards` y
 * `tests` eran del motor de diagnóstico y se rehacen en la fase 2 con otro
 * esquema (`standards` gana `kind`, `edition` y `superseded_by_id`).
 *
 * PENDIENTE DE LA FASE 1: `transformer_types` pasa a llamarse
 * `equipment_types`. El laboratorio recibe muestras de 20 tipos de equipo, no
 * solo transformadores; el nombre actual se mantiene para no romper el módulo
 * heredado (controlador, servicio, jobs, exports y páginas Vue) antes de
 * tiempo. El rename se hace completo, con su migración de datos.
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

        Schema::create('transformer_types', function (Blueprint $table) {
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
        Schema::dropIfExists('transformer_types');
        Schema::dropIfExists('oil_types');
    }
};
