<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de ensayo — las 29 pruebas del laboratorio y sus hojas de trabajo.
 *
 * Esta es la parte del sistema Rails viejo que estaba BIEN modelada y se
 * conserva: `lab_category_detail_types` → `lab_category_details` →
 * `lab_category_sub_details` → `lab_category_sub_detail_options`. El laboratorio
 * agrega una prueba o una columna sin tocar código, y eso hay que mantenerlo.
 *
 * Lo que se le agrega, y es lo que faltaba para que el informe pudiera leer de
 * acá en vez de la tabla de 221 columnas:
 *
 *   output_analyte_id  QUÉ campo es un RESULTADO, y a qué parámetro alimenta.
 *                      El sistema viejo no lo declaraba: el informe tomaba
 *                      "la última columna por posición" y asumía que era el
 *                      resultado. Reordenar una columna lo rompía en silencio.
 *
 *   formula            La fórmula, como expresión evaluable EN EL SERVIDOR.
 *                      El viejo guardaba JavaScript con document.getElementById
 *                      en la columna `blur_calculation`: el servidor no podía
 *                      recalcular ni validar, y mover una columna rompía el
 *                      cálculo porque dependía de los id del DOM.
 *
 *   unit, decimals, min_value, max_value, is_computed
 *                      Tipado real. El viejo solo distinguía texto/número/
 *                      opciones.
 *
 * OJO: una prueba puede tener VARIOS resultados. Cromatografía tiene 9 gases,
 * Partículas 10 rangos de tamaño y Metales en Aceite 10 elementos — todos son
 * resultados. Por eso `output_analyte_id` va en el CAMPO y no en la prueba.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── Grupos: Físico Químico · Cromatografía · Otros ───────────────
        Schema::create('test_groups', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Parámetros medibles (acidez, rigidez, H2, CH4…) ──────────────
        // Es la pieza que el sistema viejo NO tenía. Sin ella, "Hidrógeno H2
        // ppm" en la hoja de cromas y el H2 del informe son dos textos sin
        // relación, y hace falta una columna fija para unirlos.
        Schema::create('analytes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('code', 60)->unique();          // acid, rig, h2, ch4…
            $table->string('name', 150);
            $table->string('unit', 30)->nullable();
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->string('group', 40)->nullable();       // fiqui | dga | furanos | …
            // Hacia dónde es "peor": el valor sube (acidez) o baja (rigidez).
            $table->string('direction', 20)->default('lower_better');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Las pruebas ──────────────────────────────────────────────────
        Schema::create('test_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('code', 60)->unique();
            $table->string('name', 150);
            $table->foreignId('test_group_id')->nullable()
                ->constrained('test_groups')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('container', 100)->nullable();   // envase requerido
            $table->string('chart_unit', 40)->nullable();   // rótulo del eje en tendencias
            $table->boolean('has_control')->default(false); // corre con patrón
            $table->boolean('is_grouped')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // Trazabilidad de la importación: id en el sistema Rails viejo.
            // Sirve para re-correr el importador sin duplicar y para el ETL de
            // los datos históricos. NO se usa para ninguna lógica.
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Las columnas de cada hoja de trabajo ─────────────────────────
        Schema::create('test_fields', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->foreignId('test_definition_id')
                ->constrained('test_definitions')->cascadeOnDelete();
            $table->string('code', 60);                    // volumen_gastado, peso_aceite…
            $table->string('label', 200);
            $table->integer('sort_order')->default(0);

            // text | number | select | date | computed | standard | instrument
            $table->string('type', 20)->default('text');

            $table->string('unit', 30)->nullable();
            $table->unsignedTinyInteger('decimals')->nullable();
            $table->decimal('min_value', 18, 6)->nullable();
            $table->decimal('max_value', 18, 6)->nullable();

            $table->boolean('is_required')->default(false);
            $table->boolean('is_locked')->default(false);   // solo lectura (calculado)
            $table->boolean('is_reusable')->default(false); // arrastra el valor anterior
            $table->string('default_value', 255)->nullable();
            $table->boolean('report_visible')->default(false);

            // Expresión sobre códigos de campo de ESTA prueba. Ejemplo real,
            // el de Número Ácido:
            //   (volumen_gastado - volumen_blanco) * factor_koh / peso_aceite
            $table->text('formula')->nullable();

            // Si este campo ES un resultado, a qué parámetro alimenta.
            $table->foreignId('output_analyte_id')->nullable()
                ->constrained('analytes')->nullOnDelete();

            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['test_definition_id', 'code'], 'test_fields_def_code_unique');
            $table->index(['test_definition_id', 'sort_order'], 'test_fields_def_order_idx');
        });

        // ── Opciones de los campos de tipo select ────────────────────────
        Schema::create('test_field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_field_id')
                ->constrained('test_fields')->cascadeOnDelete();
            $table->string('value', 255);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_hidden')->default(false);
            // Bandera de acreditación del sistema viejo (`applicability_flag`):
            // marca si el método está dentro del alcance acreditado.
            $table->string('accreditation_flag', 60)->nullable();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['test_field_id', 'sort_order'], 'test_field_options_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_field_options');
        Schema::dropIfExists('test_fields');
        Schema::dropIfExists('test_definitions');
        Schema::dropIfExists('analytes');
        Schema::dropIfExists('test_groups');
    }
};
