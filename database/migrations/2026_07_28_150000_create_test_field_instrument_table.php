<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué instrumentos ofrece CADA columna.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL DEFECTO QUE ESTO ARREGLA                                              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * La grilla de bancada ofrecía TODOS los instrumentos del laboratorio en TODAS
 * las columnas de equipo. En la columna "Bureta PP-LA-01C" del Número Ácido
 * aparecía el Colorímetro, que es el equipo del ensayo de Color. Un ensayo
 * queda firmado con el equipo equivocado y la trazabilidad ISO 17025 —que es la
 * única razón por la que existe el catálogo de instrumentos— deja de valer.
 *
 * El dato para no equivocarse YA EXISTÍA y se estaba tirando: el sistema
 * anterior declaraba, por columna, exactamente qué códigos ofrecía —la bureta
 * ofrecía las tres buretas, el tensiómetro los tres tensiómetros—, y el
 * sembrador que dio de alta los instrumentos leía esas opciones, creaba los
 * equipos y después descartaba a qué columna pertenecía cada uno.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ UNA TABLA DE UNIÓN Y NO UNA COLUMNA                              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Es de muchos a muchos en las dos direcciones, y las dos ocurren de verdad:
 * la columna "Bureta" ofrece tres buretas, y la balanza PP-LA-01C-056 la usan
 * el Número Ácido y el Contenido de Agua.
 *
 * Una columna SIN filas acá ofrece TODOS los instrumentos del workspace. Es lo
 * correcto para las columnas de equipo que el sistema anterior dejó como texto
 * libre y no declaran nada: es mejor ofrecer de más que no ofrecer nada.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('test_field_instrument', function (Blueprint $table) {
            $table->id();

            $table->foreignId('test_field_id')
                ->constrained('test_fields')->cascadeOnDelete();
            $table->foreignId('instrument_id')
                ->constrained('instruments')->cascadeOnDelete();

            // El que se propone por defecto en una columna que ofrece varios.
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->nullable();

            $table->timestamps();

            $table->unique(['test_field_id', 'instrument_id'], 'test_field_instrument_unico');
            $table->index('instrument_id', 'idx_tfi_instrumento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_field_instrument');
    }
};
