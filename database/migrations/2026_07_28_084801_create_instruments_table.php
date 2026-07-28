<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * instruments — el equipamiento de bancada con el que se corre cada ensayo.
 *
 * Lo exige ISO 17025: un resultado solo es trazable si consta con QUÉ se midió
 * y si ese equipo estaba calibrado ESE día.
 *
 * En el sistema Rails viejo los instrumentos eran OPCIONES SUELTAS de un campo
 * select de la plantilla: "Bureta PP-LA-01C", "Balanza PP-LA-01C". El código de
 * calibración iba metido adentro del nombre, como texto, sin fecha de
 * vencimiento y sin forma de saber si el equipo seguía vigente. Cambiar el
 * código obligaba a editar la opción en cada prueba que lo usara, y los ensayos
 * ya cargados quedaban apuntando al texto nuevo como si se hubieran corrido con
 * él.
 *
 * Acá el instrumento es una entidad con su calibración, y la fila de la hoja de
 * trabajo lo referencia por FK.
 *
 * OJO al índice único: la clave natural es el CÓDIGO (PP-LA-01C), no el nombre.
 * Dos buretas se llaman las dos "Bureta" y son equipos distintos. El scaffold
 * genera el único sobre `name` porque clona un catálogo; acá está corregido.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('name')->index();                // Bureta, Balanza analítica
            $table->string('code')->nullable();             // PP-LA-01C
            $table->integer('sort_order')->nullable();
            $table->boolean('is_active')->default(true);

            // ── Trazabilidad metrológica (ISO 17025) ─────────────────────
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial', 100)->nullable();
            $table->date('calibrated_at')->nullable();
            $table->date('calibration_due_at')->nullable();
            $table->string('calibration_certificate', 150)->nullable();
            $table->string('location', 150)->nullable();
            $table->text('notes')->nullable();

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
            $table->index(['is_active', 'created_at'], 'idx_instruments_tenant_active_created');
            $table->index('created_at', 'idx_instruments_created_at');
            $table->index('updated_at', 'idx_instruments_updated_at');
            $table->index('deleted_at', 'idx_instruments_deleted_at');
            $table->index('created_by', 'idx_instruments_created_by');
            $table->index('is_active',  'idx_instruments_is_active');
            // Para el aviso de "calibración por vencer" del tablero.
            $table->index('calibration_due_at', 'idx_instruments_due');
        });

        // Único por CÓDIGO dentro del workspace (ver nota de arriba).
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX instruments_code_unique_active " .
                "ON instruments (tenant_id, LOWER(code)) " .
                "WHERE deleted_at IS NULL AND code IS NOT NULL"
            );
            DB::statement('CREATE INDEX idx_instruments_name_pattern ON instruments (name varchar_pattern_ops)');
        } elseif ($driver === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX instruments_code_unique_active " .
                "ON instruments (tenant_id, LOWER(code)) " .
                "WHERE deleted_at IS NULL AND code IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};
