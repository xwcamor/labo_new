<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * equipment — el equipo del que se toma la muestra.
 *
 * NO se llama `transformers`. El laboratorio recibe muestras de 20 tipos de
 * equipo (conmutadores, reactores, bushings, cables, interruptores,
 * electrobombas, intercambiadores…). Llamarlo "transformador" es lo que llevó
 * al `if tipo == 10` del sistema viejo.
 *
 * Diferencias con la tabla que genera `make:module` (que clona el catálogo
 * Brand):
 *   - NO lleva el índice único de `name` por tenant: dos clientes distintos
 *     pueden tener equipos con el mismo nombre, y hasta el mismo cliente puede
 *     repetirlo entre subestaciones.
 *   - Se le sacan `code` y `sort_order`, que son de catálogo.
 *   - Se le agregan las FKs del dominio (el scaffold no las genera).
 *
 * NO lleva campos de diagnóstico (`health_index`, `fault_type`,
 * `gassing_rate`, `paper_dp`, `ieee_condition`). Eso es de TrafoDex: el
 * laboratorio emite un informe de ensayo contra un criterio de aceptación, no
 * diagnostica el estado del equipo.
 *
 * La TENSIÓN es numérica y va separada en alta y baja. En el sistema viejo era
 * un `string` `"220/60/10"` que el código parseaba con
 * `split('/').map(&:to_f).max` cada vez que necesitaba la banda de tensión, en
 * cinco lugares distintos.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            // ── Identificación ──────────────────────────────────────────
            $table->string('name')->index();                // descripción del equipo
            $table->string('serial')->nullable()->index();  // num_serie
            $table->string('tag')->nullable()->index();     // num_tag / TR-01

            // ── Dónde está: cliente y su jerarquía ──────────────────────
            $table->foreignId('customer_id')->nullable()
                ->constrained('customers')->nullOnDelete();
            $table->foreignId('customer_location_id')->nullable()
                ->constrained('customer_locations')->nullOnDelete();
            $table->foreignId('customer_area_id')->nullable()
                ->constrained('customer_areas')->nullOnDelete();
            $table->foreignId('customer_substation_id')->nullable()
                ->constrained('customer_substations')->nullOnDelete();

            // ── Qué es: los ejes que resuelven el cuadro de límites ─────
            // equipment_type + oil_type + banda de tensión son exactamente las
            // tres dimensiones con las que SpecSetResolver elegirá el cuadro
            // (ver docs/migracion/03-NORMAS-Y-LIMITES.md).
            $table->foreignId('equipment_type_id')->nullable()
                ->constrained('equipment_types')->nullOnDelete();
            $table->foreignId('oil_type_id')->nullable()
                ->constrained('oil_types')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()
                ->constrained('brands')->nullOnDelete();
            $table->foreignId('tap_changer_type_id')->nullable()
                ->constrained('tap_changer_types')->nullOnDelete();
            $table->foreignId('transformer_preservation_id')->nullable()
                ->constrained('transformer_preservations')->nullOnDelete();

            // ── Datos físicos ───────────────────────────────────────────
            $table->decimal('voltage_kv_hv', 10, 2)->nullable(); // alta
            $table->decimal('voltage_kv_lv', 10, 2)->nullable(); // baja
            $table->decimal('power_mva', 10, 2)->nullable();
            $table->integer('phases')->nullable();
            $table->integer('manufacture_year')->nullable();
            $table->decimal('oil_volume', 12, 2)->nullable();
            $table->string('oil_volume_unit', 20)->nullable();  // L | gal
            $table->string('service_state', 20)->nullable();     // new | in_service

            // ── Puente con TrafoDex ─────────────────────────────────────
            // slug del transformer equivalente. Nullable: hay equipos del
            // laboratorio que allá no existen (bushings, cables, electrobombas).
            // Sin esto, el envío de resultados tendría que emparejar por número
            // de serie en texto — que es como el sistema viejo terminaba
            // cargando muestras en el transformador equivocado.
            $table->string('external_ref')->nullable()->index();

            $table->boolean('is_active')->default(true);

            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamp('locked_at')->nullable()->index();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->string('lock_scope', 10)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'tag'], 'idx_equipment_customer_tag');
            $table->index(['is_active', 'created_at'], 'idx_equipment_active_created');
            $table->index('created_at', 'idx_equipment_created_at');
            $table->index('updated_at', 'idx_equipment_updated_at');
            $table->index('deleted_at', 'idx_equipment_deleted_at');
            $table->index('created_by', 'idx_equipment_created_by');
        });

        // Unicidad real del dominio: el par (serie, tag) no se repite dentro de
        // un mismo workspace. Es la regla que el sistema viejo ya tenía
        // (`validates_uniqueness_of :num_tag, scope: [:num_serie]`), ahora
        // acotada al tenant y sin contar los borrados.
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX equipment_serial_tag_unique_active ' .
                'ON equipment (tenant_id, LOWER(serial), LOWER(tag)) ' .
                'WHERE deleted_at IS NULL AND serial IS NOT NULL AND tag IS NOT NULL'
            );
            DB::statement('CREATE INDEX idx_equipment_name_pattern ON equipment (name varchar_pattern_ops)');
        } elseif ($driver === 'sqlite') {
            DB::statement(
                'CREATE UNIQUE INDEX equipment_serial_tag_unique_active ' .
                'ON equipment (tenant_id, LOWER(serial), LOWER(tag)) ' .
                'WHERE deleted_at IS NULL AND serial IS NOT NULL AND tag IS NOT NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
