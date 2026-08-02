<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * entry_authorizers — el personal del laboratorio que AUTORIZA el ingreso de
 * muestras.
 *
 * Es el «Personal de Laboratorio» del sistema anterior (`rem_user_signatures`):
 * un catálogo PROPIO con nombre completo y firma escaneada, cuyo elegido es
 * obligatorio al registrar una recepción y cuya firma se imprimía en el acta.
 * NO es el catálogo de firmantes de informes (`signatures` acá,
 * `rem_signatures` allá): son dos listas distintas en el sistema anterior y se
 * mantienen distintas — unificarlas fue el error que perdió este dato en la
 * primera migración.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('entry_authorizers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('name')->index();
            $table->string('code')->nullable();
            // La firma escaneada (el viejo la exigía; acá es opcional: sin
            // imagen, el acta deja la línea para firmar a mano — el mismo
            // criterio que los firmantes de informes).
            $table->string('image')->nullable();
            $table->integer('sort_order')->nullable();
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
            $table->softDeletes();
            // Performance indexes — listado + trash + filtros (patron Regions).
            $table->index(['is_active', 'created_at'], 'idx_entry_authorizers_tenant_active_created');
            $table->index('created_at', 'idx_entry_authorizers_created_at');
            $table->index('updated_at', 'idx_entry_authorizers_updated_at');
            $table->index('deleted_at', 'idx_entry_authorizers_deleted_at');
            $table->index('created_by', 'idx_entry_authorizers_created_by');
            $table->index('is_active',  'idx_entry_authorizers_is_active');
        });

        // Catálogo per-tenant: nombre único por tenant, case/accent-insensitive.
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX entry_authorizers_name_unique_active " .
                "ON entry_authorizers (tenant_id, unaccent_immutable(LOWER(name))) " .
                "WHERE deleted_at IS NULL"
            );
            // varchar_pattern_ops para `WHERE name LIKE 'X%'` eficiente.
            DB::statement('CREATE INDEX idx_entry_authorizers_name_pattern ON entry_authorizers (name varchar_pattern_ops)');
        } elseif ($driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX entry_authorizers_name_unique_active " .
                "ON entry_authorizers (tenant_id, LOWER(name)) " .
                "WHERE deleted_at IS NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_authorizers');
    }
};
