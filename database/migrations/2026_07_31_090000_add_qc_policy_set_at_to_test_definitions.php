<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de que el laboratorio ya decidió el control de calidad de una prueba.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ HACE FALTA UNA COLUMNA Y NO ALCANZA CON LOS BOOLEANOS            │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `requires_control = false` significa dos cosas incompatibles: "nadie lo
 * configuró todavía" y "el supervisor decidió que esta prueba no lleva patrón".
 * Sin distinguirlas, el sembrador de fábrica no puede refrescar los valores sin
 * pisar decisiones: cada `db:seed` le devolvería a una prueba exenta la
 * exigencia que alguien quitó a propósito, y el laboratorio lo descubriría
 * cuando una hoja se niegue a publicarse.
 *
 * Con esta marca, el sembrador escribe una sola vez y a partir de ahí la fila es
 * del laboratorio. Es el mismo criterio con el que se separa la IDENTIDAD de un
 * parámetro (que el seeder refresca) de su CALIBRACIÓN (que no toca).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_definitions', function (Blueprint $table) {
            $table->timestamp('qc_policy_set_at')->nullable()->after('is_grouped');
        });
    }

    public function down(): void
    {
        Schema::table('test_definitions', function (Blueprint $table) {
            $table->dropColumn('qc_policy_set_at');
        });
    }
};
