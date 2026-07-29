<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * signatures — tabla base generada por make:module.
 * Agregar columnas custom del dominio aquí.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('name')->index();
            $table->string('code')->nullable();
            $table->integer('sort_order')->nullable();

            // ── Lo propio de una firma ──────────────────────────────────
            //
            // `name` es el nombre completo tal como se imprime bajo la línea.
            // `title` es el cargo ("Testing & Oil Laboratory Specialist").
            $table->string('title', 160)->nullable();

            // La imagen de la firma. Vive acá y no en el usuario porque el
            // laboratorio firma con gente que no siempre tiene cuenta en el
            // sistema, y el informe acreditado igual la lleva.
            $table->string('image')->nullable();

            // Si la firma corresponde a un usuario del sistema, se enlaza. Con
            // enlace, la imagen puede salir de su perfil —que es la única que
            // esa persona cargó con su consentimiento— en vez de la subida
            // desde acá. Sin enlace, es un firmante externo.
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Con qué relación firma: preparado / revisado / aprobado…
            // Lista cerrada y TRADUCIBLE, no texto libre: es lo que se imprime
            // sobre la línea y tiene que decir lo mismo en los dos idiomas.
            $table->string('relation', 20)->default('approved');
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

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->softDeletes();
            // Performance indexes — listado + trash + filtros (patron Regions).
            $table->index(['is_active', 'created_at'], 'idx_signatures_tenant_active_created');
            $table->index('created_at', 'idx_signatures_created_at');
            $table->index('updated_at', 'idx_signatures_updated_at');
            $table->index('deleted_at', 'idx_signatures_deleted_at');
            $table->index('created_by', 'idx_signatures_created_by');
            $table->index('is_active',  'idx_signatures_is_active');
        });

        // Catálogo per-tenant: nombre único por tenant, case/accent-insensitive.
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX signatures_name_unique_active " .
                "ON signatures (tenant_id, unaccent_immutable(LOWER(name))) " .
                "WHERE deleted_at IS NULL"
            );
            // varchar_pattern_ops para `WHERE name LIKE 'X%'` eficiente.
            DB::statement('CREATE INDEX idx_signatures_name_pattern ON signatures (name varchar_pattern_ops)');
        } elseif ($driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX signatures_name_unique_active " .
                "ON signatures (tenant_id, LOWER(name)) " .
                "WHERE deleted_at IS NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('signatures');
    }
};
