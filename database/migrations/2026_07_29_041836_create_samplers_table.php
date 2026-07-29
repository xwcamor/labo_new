<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * samplers — tabla base generada por make:module.
 * Agregar columnas custom del dominio aquí.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('samplers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('name')->index();
            $table->string('code')->nullable();
            $table->integer('sort_order')->nullable();

            // El país del muestreador: el catálogo del sistema anterior lo
            // llevaba como primera columna y no es decorativo — un laboratorio
            // que recibe muestras de dos países tiene su propia lista de
            // terceros en cada uno.
            $table->unsignedBigInteger('country_id')->nullable()->index();
            $table->boolean('is_active')->default(true);

            // Catálogo per-tenant: cada workspace tiene su propio catálogo de marcas.
            $table->foreignId('tenant_id')->nullable()->index()->constrained('tenants')->nullOnDelete();

            // Audit + soft-delete (patrón master template).
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            // Lockable: bloqueo de registros (lock()/unlock()).
            $table->timestamp('locked_at')->nullable()->index();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->string('lock_scope', 10)->nullable();

            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->softDeletes();
            // Performance indexes — listado + trash + filtros (patron Regions).
            $table->index(['is_active', 'created_at'], 'idx_samplers_tenant_active_created');
            $table->index('created_at', 'idx_samplers_created_at');
            $table->index('updated_at', 'idx_samplers_updated_at');
            $table->index('deleted_at', 'idx_samplers_deleted_at');
            $table->index('created_by', 'idx_samplers_created_by');
            $table->index('is_active',  'idx_samplers_is_active');
        });

        // Catálogo per-tenant: nombre único por tenant, case/accent-insensitive.
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX samplers_name_unique_active " .
                "ON samplers (tenant_id, unaccent_immutable(LOWER(name))) " .
                "WHERE deleted_at IS NULL"
            );
            // varchar_pattern_ops para `WHERE name LIKE 'X%'` eficiente.
            DB::statement('CREATE INDEX idx_samplers_name_pattern ON samplers (name varchar_pattern_ops)');
        } elseif ($driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX samplers_name_unique_active " .
                "ON samplers (tenant_id, LOWER(name)) " .
                "WHERE deleted_at IS NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('samplers');
    }
};
