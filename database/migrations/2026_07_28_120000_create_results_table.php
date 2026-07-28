<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * results — el resultado medido, tipado y consultable.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ ESTA ES LA TABLA QUE FALTABA, Y LA DISCUSIÓN QUE LA ORIGINA              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema Rails viejo guardaba cada celda medida en `lab_sub_details`: una
 * fila por (fila de la hoja, columna). Esa tabla creció y el sistema se volvió
 * lento, y de ahí sale la objeción —legítima— de que "todo era vertical y no
 * horizontal", y la propuesta de hacer una tabla ancha por prueba.
 *
 * Conviene ser preciso sobre QUÉ lo hizo lento, porque de eso depende el
 * remedio. `lab_sub_details` tenía cuatro problemas y solo uno era la forma:
 *
 *   1. El valor era TEXTO. `name varchar` guardaba números, fechas y hasta el
 *      id de la opción elegida en un select. Sobre una columna así ningún
 *      índice sirve para comparar, ordenar ni promediar: cada consulta tiene
 *      que convertir, y convertir en el WHERE anula el índice.
 *   2. Para saber a QUÉ equipo y a qué FECHA pertenecía una celda había que
 *      subir tres saltos: valor → fila → hoja → prueba, y de la fila al
 *      informe por un texto interpolado en SQL. Ninguna consulta útil se
 *      resolvía sin recorrer media base.
 *   3. Las vistas tenían N+1 masivo: una consulta POR FILA de la tabla. El
 *      propio sistema paginaba de cinco en cinco para disimularlo.
 *   4. Y recién en cuarto lugar, sí: era vertical.
 *
 * Esta tabla arregla los tres primeros y conserva el cuarto, con una diferencia
 * que lo cambia todo: acá `equipment_id`, `analyte_id` y `measured_at` viven EN
 * LA FILA. Son las tres claves por las que el laboratorio pregunta siempre, y
 * están juntas en un índice. La consulta "la acidez de este equipo en cinco
 * años" es un recorrido de índice, no un rastreo con conversiones.
 *
 * La medición que compara esta forma contra una tabla ancha por prueba, con
 * volumen real y planes de ejecución, está en
 * `docs/migracion/08-BENCHMARK-VERTICAL-VS-ANCHO.md`. Si esos números dicen
 * otra cosa, mandan los números.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ NO ES EL BOLSÓN DE `worksheet_values`                            │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Son dos capas y hacen cosas distintas:
 *
 *   worksheet_values   lo que ESCRIBIÓ el analista en la bancada, guiado por la
 *                      plantilla. Es la constancia del trabajo y lo que audita
 *                      el laboratorio. Se guarda por columna de la plantilla,
 *                      así que soporta cualquier prueba nueva sin tocar el
 *                      esquema. No se consulta para informar.
 *
 *   results            lo que se MIDIÓ, por parámetro. Tipado, indexado y con
 *                      el equipo y la fecha adentro. Es lo que consultan el
 *                      informe, las tendencias, el tablero y la API hacia
 *                      TrafoDex. Se materializa al validar la hoja.
 *
 * Lo crudo nunca se pisa; esto se puede reconstruir entero desde ahí. Si mañana
 * se corrige una fórmula, se recalcula esta tabla sin tocar lo que cargó el
 * analista (`php artisan lab:rebuild-results`).
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ EL VEREDICTO CONTRA LA NORMA SE GUARDA Y NO SE CALCULA AL LEER   │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Las normas cambian y los límites del laboratorio también. Un ensayo de 2019
 * tiene que seguir diciendo lo que decía en 2019: si el veredicto se recalcula
 * al abrir el informe, un cambio de límite reescribe la historia en silencio y
 * un certificado ya emitido deja de coincidir con lo que muestra la pantalla.
 * Por eso van guardados el estado, los dos límites que se aplicaron y de qué
 * norma salieron. Es el mismo criterio que ya usan los puntos de las cartas de
 * control.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();

            // ── Las tres claves de toda consulta del laboratorio ──────────
            // Van desnormalizadas A PROPÓSITO. Es la diferencia con la tabla
            // del sistema viejo, donde para saber de qué equipo y de qué fecha
            // era una celda había que subir tres saltos.
            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()
                ->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('analyte_id')
                ->constrained('analytes')->cascadeOnDelete();
            // NOT NULL a propósito: además de ser obligatorio en el dominio,
            // deja abierta la puerta a particionar por año si el volumen algún
            // día lo justifica (la clave de partición no admite nulos).
            $table->date('measured_at');

            // ── De dónde salió: trazabilidad completa hacia la bancada ────
            $table->foreignId('worksheet_row_id')->nullable()
                ->constrained('worksheet_rows')->cascadeOnDelete();
            $table->foreignId('test_definition_id')->nullable()
                ->constrained('test_definitions')->nullOnDelete();
            $table->foreignId('test_field_id')->nullable()
                ->constrained('test_fields')->nullOnDelete();
            // Fase 3: se agrega la restricción contra `samples`.
            $table->unsignedBigInteger('sample_id')->nullable()->index();

            // ── El valor ─────────────────────────────────────────────────
            // Numérico de verdad. En el sistema viejo esto era `varchar` y ahí
            // convivían "1.5E-03", "<0.5", cadenas vacías y el texto "NaN".
            $table->decimal('value_num', 24, 8)->nullable();
            // Solo para los ensayos cualitativos (aspecto, olor, color).
            $table->text('value_text')->nullable();
            // Valor censurado: "gt" = el instrumento llegó a su tope y el
            // aceite no rompió; "lt" = por debajo del límite de detección.
            $table->string('qualifier', 4)->nullable();
            $table->string('unit', 30)->nullable();
            $table->unsignedTinyInteger('replicate_no')->default(1);

            // ── El veredicto contra la norma, congelado ──────────────────
            // in_spec | near_limit | out_of_spec
            $table->string('spec_status', 12)->nullable();
            $table->decimal('spec_min', 24, 8)->nullable();
            $table->decimal('spec_max', 24, 8)->nullable();
            // De qué norma salieron esos dos límites ("ASTM D974", "IEC 60422").
            $table->string('spec_source', 60)->nullable();

            $table->timestamps();

            // ── Índices ──────────────────────────────────────────────────
            // Uno por cada consulta real del sistema. El orden de las columnas
            // no es decorativo: la igualdad va primero y el rango al final, que
            // es lo único que permite resolver el filtro y el orden en un solo
            // recorrido.

            // ESTOS ÍNDICES NO SON UNA CORAZONADA: salen medidos de
            // docs/migracion/08-BENCHMARK-VERTICAL-VS-ANCHO.md, sobre 84
            // millones de filas. Con ellos ninguna consulta del laboratorio
            // pasa de 2 ms; con el juego equivocado, la del tablero llegó a
            // 1.895 ms con los MISMOS datos. El límite no lo pone el volumen,
            // lo pone el índice. Antes de cambiar cualquiera de los tres,
            // volver a correr el banco de pruebas.

            // 1. La tendencia de un parámetro de un equipo.
            $table->index(
                ['equipment_id', 'analyte_id', 'measured_at'],
                'idx_results_equipo_parametro_fecha'
            );

            // 2. El informe de un equipo y el consolidado: TODOS sus
            //    parámetros, sin filtrar por analito. Sin este índice esas dos
            //    consultas se resuelven por el anterior descartando filas.
            $table->index(['equipment_id', 'measured_at'], 'idx_results_equipo_fecha');

            // 4. Trazabilidad inversa: qué resultados produjo esta fila de
            //    bancada. Postgres NO crea índice solo por declarar la clave
            //    foránea, y sin esto el borrado en cascada recorre la tabla.
            $table->index('worksheet_row_id', 'idx_results_fila');

            // Re-materializar tiene que ser idempotente: volver a validar una
            // hoja actualiza sus resultados, no los duplica.
            $table->unique(
                ['worksheet_row_id', 'analyte_id', 'replicate_no'],
                'results_fila_parametro_unico'
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            // 3. El tablero de flota: un parámetro sobre todos los equipos del
            //    workspace. Es el índice que decide todo el asunto.
            //
            //    El `tenant_id` va PRIMERO porque el tablero siempre mira un
            //    workspace, y el `INCLUDE (value_num)` hace que la consulta se
            //    resuelva recorriendo solo el índice, sin bajar a la tabla.
            //    Medido: con este índice, 1,72 ms sobre 84 millones de filas;
            //    con `(analyte_id, measured_at)`, que es la elección natural,
            //    1.895 ms. Mil veces peor por el orden de dos columnas.
            DB::statement(
                'CREATE INDEX idx_results_flota ON results ' .
                '(tenant_id, analyte_id, equipment_id, measured_at DESC) INCLUDE (value_num)'
            );

            // 4. "Todo lo que está fuera de norma", para el tablero de alertas.
            //    Parcial porque las filas fuera de norma son una fracción chica
            //    del total: el índice queda diminuto comparado con uno sobre la
            //    columna entera.
            DB::statement(
                "CREATE INDEX idx_results_fuera_de_norma ON results (tenant_id, measured_at DESC) " .
                "WHERE spec_status = 'out_of_spec'"
            );
        } else {
            // SQLite (las pruebas) no admite INCLUDE. Se crea la parte que sí
            // entiende: lo que se verifica en las pruebas es el comportamiento,
            // no el plan de ejecución.
            Schema::table('results', function (Blueprint $table) {
                $table->index(
                    ['tenant_id', 'analyte_id', 'equipment_id', 'measured_at'],
                    'idx_results_flota'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
