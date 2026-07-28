<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El "ANÁLISIS DE RESULTADOS (opiniones e interpretaciones)" del informe.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ TEXTO GENERADO, EDITABLE, Y REGENERABLE — LAS TRES COSAS                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema anterior lo resolvía así y el flujo era correcto: al entrar a
 * cargar el informe, un botón "autodiagnóstico" pre-cargaba un texto en un
 * `textarea`, el analista lo corregía si hacía falta, y se guardaba en la fila
 * del informe (`fiq_comment`, `cro_comment`, `pcb_comment`… una columna por
 * familia). Volver a pulsar el botón lo regeneraba.
 *
 * Lo que estaba mal era DÓNDE vivía el texto: en 1134 líneas de ERB con
 * condicionales anidados por tipo de aceite × tipo de equipo × cuántos
 * parámetros fallaron. Cambiar una frase —o agregar un tipo de equipo— exigía
 * un programador, y el texto quedaba fuera de control del laboratorio, que es
 * justamente quien responde por lo que dice el informe.
 *
 * Acá el texto se compone desde PLANTILLAS EN DATOS y lo generado se guarda en
 * esta tabla. Se guarda —en vez de recalcularse al imprimir— por dos razones:
 *
 *   1. El analista lo edita. Si se recalculara, su corrección se perdería en la
 *      siguiente impresión.
 *   2. Es una OPINIÓN firmada. Tiene que quedar registrado qué opinión se emitió
 *      y si la escribió el motor o una persona (`is_edited`), porque de eso
 *      responde el laboratorio ante una auditoría.
 *
 * El alcance es la FAMILIA, no la prueba: en el informe, las trece pruebas
 * fisicoquímicas comparten un solo párrafo, mientras que cromatografía, PCB o
 * furanos tienen el suyo. Esa agrupación es un dato de la prueba
 * (`test_definitions.report_comment_group`), no una lista escrita en el código.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sample_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained('samples')->cascadeOnDelete();

            // La familia del informe ("fisicoquimico", "cromatografia", "pcb"…).
            // Es texto y no una FK porque la familia agrupa pruebas: no hay una
            // fila de `test_definitions` que la represente.
            $table->string('family', 60);

            $table->text('body')->nullable();

            // ¿La escribió una persona? Un texto editado NO se pisa al volver a
            // diagnosticar sin que alguien lo pida explícitamente.
            $table->boolean('is_edited')->default(false);

            $table->timestamp('generated_at')->nullable();
            $table->unsignedBigInteger('edited_by')->nullable();

            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();

            $table->timestamps();

            $table->unique(['sample_id', 'family'], 'sample_diagnoses_unico');
        });

        Schema::table('test_definitions', function (Blueprint $table) {
            // Qué párrafo del análisis le toca a esta prueba. Las que comparten
            // valor comparten párrafo. Nulo = la prueba no lleva análisis
            // (no todas lo llevan: sedimentos es un dato, no una opinión).
            $table->string('report_comment_group', 60)->nullable();
        });

        // Las trece pruebas fisicoquímicas comparten párrafo, como en el
        // informe acreditado. El resto arranca con el suyo propio; el
        // laboratorio lo reagrupa desde el editor de la prueba si quiere.
        DB::statement("
            UPDATE test_definitions SET report_comment_group = code
            WHERE report_comment_group IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_diagnoses');
        Schema::table('test_definitions', fn (Blueprint $t) => $t->dropColumn('report_comment_group'));
    }
};
