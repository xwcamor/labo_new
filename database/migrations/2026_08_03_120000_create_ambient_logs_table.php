<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ambient_logs — la bitácora diaria de condiciones ambientales de las salas.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ UNA SOLA TABLA Y NO DOS                                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema anterior tenía DOS tablas idénticas —`cro_temperatures` y
 * `fiq_temperatures`— con las mismas cuatro columnas, dos modelos, dos
 * controladores de 139 líneas cada uno y dos juegos de vistas, para registrar
 * lo mismo en dos salas distintas. Acá la sala es un DATO (`room`): agregar la
 * sala de furanos mañana es una opción más, no otra tabla con su CRUD.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ SE REGISTRA Y POR QUÉ NO ALCANZA CON LA HOJA DE TRABAJO              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * La hoja de trabajo ya guarda temperatura y humedad, pero son las de ESA
 * corrida: existen solo si ese día se corrió un ensayo. La bitácora se lleva
 * TODOS los días hábiles, haya o no trabajo, porque lo que acredita no es la
 * condición de un ensayo sino que la sala estuvo bajo control. Es el registro
 * que un auditor pide primero, y la humedad de la sala condiciona la validez
 * de la rigidez dieléctrica y del contenido de agua.
 *
 * Una lectura por sala y por día: el índice único lo impone (el viejo lo hacía
 * con una validación de Rails que un `update_attribute` salteaba).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ambient_logs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            // La sala. Lista cerrada en el modelo, no catálogo: son las áreas
            // físicas del laboratorio, no un dato que el usuario administre.
            $table->string('room', 30)->index();
            $table->date('logged_on')->index();

            // Las tres magnitudes del sistema anterior, con su unidad en el
            // nombre para que nadie las cargue en la equivocada.
            $table->decimal('temperature_c', 6, 2)->nullable();
            $table->decimal('humidity_pct', 6, 2)->nullable();
            $table->decimal('pressure_hpa', 8, 2)->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('tenant_id')->nullable()->index()->constrained('tenants')->nullOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'room', 'logged_on'], 'idx_ambient_logs_room_day');
            $table->index('created_at', 'idx_ambient_logs_created_at');
            $table->index('deleted_at', 'idx_ambient_logs_deleted_at');
        });

        // Una lectura por sala y por día. Parcial para que una baja lógica
        // libere el día: si se cargó mal y se borra, se puede volver a cargar.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'CREATE UNIQUE INDEX ambient_logs_day_unique
                 ON ambient_logs (tenant_id, room, logged_on)
                 WHERE deleted_at IS NULL'
            );
        } else {
            Schema::table('ambient_logs', function (Blueprint $table) {
                $table->unique(['tenant_id', 'room', 'logged_on', 'deleted_at'], 'ambient_logs_day_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ambient_logs');
    }
};
