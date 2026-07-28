<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // Durante la fase 1 las migraciones se editan EN SU LUGAR, porque
        // todavía no hay despliegue. `analytes` se creaba en su propia
        // migración (generada por make:module) y pasó a crearse acá, con otra
        // forma. Quien ya había migrado antes de ese cambio se queda con la
        // tabla vieja y con el registro de una migración que ya no existe, y
        // sin este aviso lo único que ve es "Duplicate table", que no dice qué
        // hacer.
        if (Schema::hasTable('analytes')) {
            throw new RuntimeException(
                "La tabla 'analytes' ya existe, creada por una migración que se fusionó con esta.\n"
                . "Su base quedó a mitad de camino. Corra:\n\n"
                . "    php artisan lab:doctor          (informa qué encontró)\n"
                . "    php artisan lab:doctor --fix    (lo repara conservando el resto de los datos)\n"
                . "    php artisan migrate\n\n"
                . "O, si no le importa perder la base local:\n\n"
                . "    php artisan migrate:fresh --seed\n"
            );
        }

        // ── Grupos: Físico Químico · Cromatografía · Otros ───────────────
        Schema::create('test_groups', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('code', 40);
            $table->string('name', 100);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();
            // Lockable: bloqueo de registros (lock()/unlock()).
            $table->timestamp('locked_at')->nullable()->index();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->string('lock_scope', 10)->nullable();

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
            $table->string('code', 60);                    // acid, rig, h2, ch4…
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
            // Lockable: bloqueo de registros (lock()/unlock()).
            $table->timestamp('locked_at')->nullable()->index();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->string('lock_scope', 10)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // ── Las pruebas ──────────────────────────────────────────────────
        Schema::create('test_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('code', 60);
            $table->string('name', 150);
            $table->foreignId('test_group_id')->nullable()
                ->constrained('test_groups')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('container', 100)->nullable();   // envase requerido
            $table->string('chart_unit', 40)->nullable();   // rótulo del eje en tendencias

            // ── Qué exige la hoja antes de aceptar muestras ──────────────
            // El sistema viejo tenía esta regla METIDA EN LA VISTA: mientras
            // no hubiera al menos un Patrón y un Duplicado cargados, el select
            // de tipo de fila solo ofrecía esas dos opciones. Como vivía en el
            // HTML, un POST directo la salteaba. Acá son dos banderas y las
            // valida el servidor.
            $table->boolean('has_control')->default(false); // corre con patrón
            $table->boolean('requires_control')->default(false);
            $table->boolean('requires_duplicate')->default(false);

            // En el viejo `is_grouped` significaba "no usa patrón ni duplicado"
            // y así estaba rotulado en el editor. Se conserva por trazabilidad
            // de la importación, pero quien manda son las dos banderas de arriba.
            $table->boolean('is_grouped')->default(false);

            // Cuántas veces se repite la medición sobre la MISMA muestra.
            // La rigidez dieléctrica se mide 5 o 6 veces y se promedia. El
            // sistema viejo lo resolvía con una columna por medición
            // ("Medición 1".."Medición 6") o, peor, concatenando los valores
            // con "/" dentro de un solo campo de texto — así funciona hoy el
            // Grado de Polimerización, y por eso existe `str_two_values`, que
            // hace `gsub('/', '<br><br>')` para volver a separarlos al mostrar.
            // Acá la repetición es un número y cada réplica es su propia fila
            // de valor (ver worksheet_values.replicate_no).
            $table->unsignedTinyInteger('replicates')->default(1);
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
            // Lockable: bloqueo de registros (lock()/unlock()).
            $table->timestamp('locked_at')->nullable()->index();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->string('lock_scope', 10)->nullable();

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

            // text | number | select | date | computed | instrument
            $table->string('type', 20)->default('text');

            // ┌────────────────────────────────────────────────────────────┐
            // │ EL ROL DE LA COLUMNA — lo que el viejo deducía por POSICIÓN │
            // └────────────────────────────────────────────────────────────┘
            // El sistema Rails tenía tres supuestos posicionales, los tres sin
            // declarar en ningún lado y los tres frágiles:
            //
            //   num_pos == 1        es el "Nº de Muestra"
            //                       (JS copiaba #col1 a lab_details.num_test)
            //   num_pos == 2        es la "Norma"
            //                       (LabDetail#norma_y_flag lo asume)
            //   la ÚLTIMA columna   es el "Resultado"
            //                       (tendences_controller: .order(num_pos).last)
            //
            // De ahí el aviso en mayúsculas del README del sistema viejo: "LA
            // COLUMNA RESULTADO SIEMPRE ES LA ÚLTIMA". Insertar una columna en
            // el medio o reordenar el cuadro rompía el enlace con la muestra,
            // la norma del informe y el gráfico de tendencias, en silencio.
            //
            // Acá el rol se DECLARA:
            //   none         columna común de la bancada
            //   sample_code  el código de la muestra (enlaza la fila con ella)
            //   standard     la norma con la que se ensayó
            //   result       un resultado (además lleva output_analyte_id)
            //   temperature  temperatura del ensayo (condición, no resultado)
            //   observation  observación del analista
            //
            // Ordenar las columnas pasa a ser cosmético, que es lo que debería
            // haber sido siempre.
            $table->string('role', 20)->default('none')->index();

            // Cuántas réplicas admite ESTA columna. Normalmente hereda el
            // `replicates` de la prueba; se deja por campo porque en una misma
            // hoja conviven la medición repetida y el promedio calculado.
            $table->unsignedTinyInteger('replicates')->default(1);

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

            // Sin columnas de candado: el candado se pone sobre la PRUEBA, no
            // sobre cada una de sus columnas. Bloquear una columna suelta
            // dejaría la plantilla a medio congelar, que es peor que no
            // bloquearla. (El scaffold las inyecta por costumbre; acá sobran.)

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

        // ── El código es único POR WORKSPACE, no en todo el sistema ──────
        // Con un único global, el primer laboratorio que se dé de alta se
        // queda con los códigos obvios: nadie más podría tener un grupo
        // `fisico_quimico` ni una prueba `acid`. Y como estas tablas admiten
        // filas globales de fábrica (tenant_id nulo), el índice tiene que
        // tratar el nulo como un valor más, cosa que un UNIQUE común de SQL no
        // hace: dos filas con NULL no se consideran iguales.
        //
        // Por eso el índice va sobre COALESCE(tenant_id, 0) y se escribe a
        // mano; y se limita a las filas vivas, para que un borrado lógico no
        // bloquee el alta de un código que se dio de baja.
        $driver = DB::getDriverName();
        foreach ([
            ['test_groups', 'test_groups_code_unique_active'],
            ['analytes', 'analytes_code_unique_active'],
            ['test_definitions', 'test_definitions_code_unique_active'],
        ] as [$table, $indexName]) {
            if (in_array($driver, ['pgsql', 'sqlite'], true)) {
                DB::statement(
                    "CREATE UNIQUE INDEX {$indexName} ON {$table} " .
                    "(COALESCE(tenant_id, 0), LOWER(code)) WHERE deleted_at IS NULL"
                );
            }
        }
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
