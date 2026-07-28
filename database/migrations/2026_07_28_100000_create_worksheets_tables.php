<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bancada: las hojas de trabajo donde el analista carga los ensayos.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ NO UNA TABLA POR PRUEBA                                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * La pregunta natural es "¿cada prueba no debería tener su tabla?". La
 * respuesta es NO, y conviene dejarla escrita porque va a volver a aparecer.
 *
 * Con 29 tablas —una por prueba— agregar la prueba 30 exige una migración, un
 * modelo, un controlador y un despliegue. El laboratorio deja de poder agregar
 * un ensayo solo y vuelve a depender del programador. Es el mismo problema que
 * la tabla de 221 columnas del sistema viejo, apenas rotado: en vez de una
 * tabla ancha, 29 tablas angostas que igual hay que crear a mano.
 *
 * El sistema Rails viejo ya había resuelto esto BIEN con plantillas
 * (lab_category_details → lab_category_sub_details → lab_sub_details) y esa
 * decisión se conserva.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ PERO TAMPOCO SOLO EAV: SON DOS CAPAS                                     │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Un EAV puro (todo en `worksheet_values`) es flexible pero pésimo para
 * consultar: sacar "la acidez de este equipo en los últimos 5 años" obliga a
 * unir por texto y filtrar por campo, y las tendencias se arrastran.
 *
 * Por eso hay DOS capas y cada una hace lo suyo:
 *
 *   worksheet_values   El dato CRUDO tal como se cargó en la bancada, guiado
 *                      por la plantilla. Flexible: soporta cualquier prueba
 *                      nueva sin tocar el esquema. Es el registro de lo que
 *                      hizo el analista, y es lo que audita el laboratorio.
 *
 *   results            (fase 5) Una fila por PARÁMETRO MEDIDO, tipada e
 *                      indexada, materializada al validar la hoja desde los
 *                      campos que declaran `output_analyte_id`. Es lo que
 *                      consultan el informe, las tendencias y el histórico.
 *
 * Lo crudo nunca se pisa; lo materializado se puede reconstruir. Si mañana
 * cambia una fórmula, se recalcula `results` sin tocar lo que cargó el
 * analista.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LO QUE SE ARREGLA DEL SISTEMA VIEJO                                      │
 * └──────────────────────────────────────────────────────────────────────────┘
 * 1. El vínculo hoja↔muestra era por TEXTO. `LabDetail#update_rem_jobs` hacía
 *    `num_test.split('-')` e interpolaba el resultado en SQL crudo. Si el
 *    analista escribía "2026 - 744" en vez de "2026-0744", el resultado no se
 *    enlazaba al informe y la celda salía vacía sin aviso. Acá es una FK.
 *
 * 2. Los valores se guardaban TODOS como texto en `lab_sub_details.name`,
 *    incluidos los números. Ordenar o promediar exigía convertir en cada
 *    consulta. Acá el valor numérico va en su columna tipada.
 *
 * 3. No había registro de QUIÉN cargó cada valor ni CUÁNDO. Solo el dato.
 */
return new class extends Migration {
    public function up(): void
    {
        // Los instrumentos (ISO 17025) viven en su propia migración,
        // `create_instruments_table`, porque son un módulo con pantalla propia.

        // ── La hoja de trabajo: una prueba, un día, un analista ───────────
        Schema::create('worksheets', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->foreignId('test_definition_id')
                ->constrained('test_definitions')->cascadeOnDelete();
            $table->date('run_date');
            $table->foreignId('analyst_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // draft = se está cargando · closed = el analista terminó
            // validated = el supervisor la revisó y firmó · voided = anulada
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('validated_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('void_reason')->nullable();

            // Condiciones ambientales del ensayo. En el sistema viejo eran
            // columnas de la plantilla y se recargaban a mano en cada fila.
            $table->decimal('ambient_temp_c', 6, 2)->nullable();
            $table->decimal('ambient_humidity', 6, 2)->nullable();

            $table->text('notes')->nullable();
            $table->unsignedInteger('legacy_id')->nullable()->unique();

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

            // Sin índice único sobre (prueba, fecha): el sistema viejo SÍ lo
            // tenía y era una limitación, no una regla del laboratorio. Correr
            // el mismo ensayo dos veces en un día es normal (una tanda por la
            // mañana y otra por la tarde, o una repetición tras un patrón fuera
            // de control), y allá la segunda corrida daba "Registro duplicado".
            $table->index(['test_definition_id', 'run_date'], 'idx_worksheets_test_date');
        });

        // ── Cada fila de la hoja ─────────────────────────────────────────
        Schema::create('worksheet_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksheet_id')
                ->constrained('worksheets')->cascadeOnDelete();

            // sample = una muestra del cliente · control = patrón de referencia
            // duplicate = repetición de la misma muestra (repetibilidad)
            // blank = blanco de reactivos
            //
            // El sistema viejo tenía esto en lab_detail_type_id (1 patrón,
            // 2 muestra, 3 duplicado) y es lo que sostiene el control de
            // calidad analítica. Se conserva y se le agrega el blanco.
            $table->string('kind', 20)->default('sample')->index();

            // El código que escribe el analista ("2026-0744"). Se conserva
            // TEXTUAL además de la FK, porque es lo que él tipeó y es lo que
            // hay que poder auditar si el enlace sale mal.
            //
            // En el sistema viejo esto era todo lo que había: `num_test` se
            // llenaba por JavaScript copiando el valor de `#col1` y quitando
            // espacios, con el input en readonly. Si el analista pegaba el
            // código sin disparar el `keyup`, `num_test` quedaba VACÍO y el
            // resultado nunca llegaba al informe — sin ningún aviso.
            $table->string('sample_code', 60)->nullable()->index();

            // FK real a la muestra. El viejo la resolvía haciendo
            // `num_test.split('-')` e interpolando el resultado en SQL crudo.
            // Fase 3: se agrega la constraint contra `samples`.
            $table->unsignedBigInteger('sample_id')->nullable()->index();
            $table->unsignedBigInteger('sample_test_id')->nullable()->index();

            $table->integer('position')->default(0);
            $table->foreignId('instrument_id')->nullable()
                ->constrained('instruments')->nullOnDelete();

            // De qué archivo de instrumento salió esta fila, si vino importada.
            // La restricción se agrega al final de la migración porque
            // `instrument_files` se crea más abajo.
            $table->unsignedBigInteger('instrument_file_id')->nullable()->index();

            $table->text('notes')->nullable();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['worksheet_id', 'position'], 'idx_worksheet_rows_order');
        });

        // ── El valor de cada columna en cada fila ─────────────────────────
        Schema::create('worksheet_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksheet_row_id')
                ->constrained('worksheet_rows')->cascadeOnDelete();
            $table->foreignId('test_field_id')
                ->constrained('test_fields')->cascadeOnDelete();

            // Qué réplica de la medición es. Casi siempre 1. La rigidez
            // dieléctrica se mide 5 o 6 veces sobre la misma muestra y se
            // promedia: en el sistema viejo eso eran seis COLUMNAS distintas
            // ("Medición 1".."Medición 6"), y en el Grado de Polimerización
            // eran varios valores concatenados con "/" dentro de un campo de
            // texto, que la vista volvía a separar con un gsub. Acá es una fila
            // por réplica y el promedio es una fórmula.
            $table->unsignedTinyInteger('replicate_no')->default(1);

            // Tipado de verdad: el sistema viejo guardaba TODO como texto en
            // `lab_sub_details.name`, incluidos los números, así que ordenar o
            // promediar exigía convertir en cada consulta.
            $table->decimal('value_num', 24, 8)->nullable();
            $table->text('value_text')->nullable();

            // ── Valores censurados ───────────────────────────────────────
            // Hay mediciones que el instrumento no puede dar como número:
            //
            //   ">75.0  kV"   el ensayador disruptivo llegó a su tope y el
            //                 aceite no rompió. La rigidez NO es 75: es al
            //                 menos 75. Aparece tal cual en los archivos del
            //                 DPA 75C.
            //   "0.000 BDL"   below detection limit, el furano no se detectó.
            //                 NO es cero: es menos que el límite de detección
            //                 del método. Aparece tal cual en los archivos del
            //                 HPLC de furanos.
            //
            // El sistema viejo guardaba esas cadenas en la misma columna de
            // texto que todo lo demás, así que cualquier cálculo sobre ellas
            // daba NaN, y su parser de archivos borraba las unidades carácter
            // por carácter, con lo que ">75.0 kV" podía terminar en ">75.0"
            // o en cualquier cosa según qué letras tuviera la unidad.
            //
            // Acá el número y la censura van separados: `value_num` guarda el
            // umbral (75, o el límite de detección) y `qualifier` dice cómo
            // leerlo. Así el promedio puede decidir qué hacer con ellos en vez
            // de tropezarse.
            //   null  el valor es el que dice
            //   gt    mayor que value_num
            //   lt    menor que value_num (incluye el "no detectado")
            $table->string('qualifier', 4)->nullable();
            $table->foreignId('option_id')->nullable()
                ->constrained('test_field_options')->nullOnDelete();

            // Con qué instrumento se tomó ESTE valor. Una misma prueba usa
            // varios: el Número Ácido lleva una bureta y una balanza, y son
            // columnas distintas de la hoja. En el sistema viejo cada una era
            // un select cuyas opciones eran textos sueltos ("Bureta PP-LA-01C"),
            // con el código de calibración adentro del nombre, así que no había
            // manera de cruzar el ensayo con el estado de calibración del
            // equipo. Por eso el instrumento va por VALOR y no solo por fila.
            $table->foreignId('instrument_id')->nullable()
                ->constrained('instruments')->nullOnDelete();

            // Si el valor lo produjo una fórmula y no la mano del analista.
            $table->boolean('is_computed')->default(false);

            // Quién y cuándo. El viejo no lo guardaba.
            $table->unsignedBigInteger('entered_by')->nullable();
            $table->timestamp('entered_at')->nullable();

            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->unique(['worksheet_row_id', 'test_field_id', 'replicate_no'], 'worksheet_values_row_field_unique');
            $table->index('test_field_id', 'idx_worksheet_values_field');
        });

        // ── Archivos crudos de los equipos de medición ───────────────────
        Schema::create('instrument_files', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->foreignId('worksheet_id')->nullable()
                ->constrained('worksheets')->nullOnDelete();
            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('sha256', 64)->nullable()->index();
            // Formato con el que se interpretó (ver instrument_formats).
            $table->unsignedBigInteger('instrument_format_id')->nullable()->index();
            $table->string('status', 20)->default('uploaded');  // uploaded|parsed|failed
            $table->text('parse_error')->nullable();
            $table->unsignedInteger('rows_parsed')->nullable();
            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Cómo se lee cada formato de archivo, COMO DATOS ──────────────
        // El sistema viejo tenía el mapeo clavado en SQL, con los ids de
        // columna escritos a mano y un comentario que decía
        // "DONT MOVE FURANOS COLUMN ORDER":
        //   update lab_file_details SET name = substring_index(name,' ',1)
        //   WHERE lab_category_sub_detail_id IN (80,81,82,83,84)
        // Acá el formato es una fila editable, no una consulta en el código.
        Schema::create('instrument_formats', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->string('code', 60)->unique();
            $table->string('name', 150);          // "Hitachi DPA 75C · Cromas"
            $table->foreignId('test_definition_id')->nullable()
                ->constrained('test_definitions')->nullOnDelete();
            // Cómo está armado el archivo:
            //   label    protocolo "Etiqueta: valor unidad" (DPA 75C, DTL C)
            //   lookup   tabla "nombre  valor" (cromatografía, furanos)
            $table->string('kind', 20)->default('label');
            $table->string('delimiter', 10)->nullable();
            $table->unsignedSmallInteger('header_row')->nullable();
            $table->unsignedSmallInteger('first_data_row')->default(1);
            $table->string('encoding', 30)->default('UTF-8');
            // Mapa etiqueta-del-archivo -> code del test_field. Lo consume
            // App\Services\Lab\InstrumentFileParser, que documenta su forma.
            $table->json('column_map')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // La fila apunta al archivo del que salió. Va acá y no arriba porque
        // `instrument_files` se crea después que `worksheet_rows`.
        Schema::table('worksheet_rows', function (Blueprint $table) {
            $table->foreign('instrument_file_id')
                ->references('id')->on('instrument_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_formats');
        Schema::dropIfExists('instrument_files');
        Schema::dropIfExists('worksheet_values');
        Schema::dropIfExists('worksheet_rows');
        Schema::dropIfExists('worksheets');
    }
};
