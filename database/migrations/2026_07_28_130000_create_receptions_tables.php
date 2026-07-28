<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La recepción de muestras: lo que entra al laboratorio.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ES LA PIEZA QUE FALTABA                                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Hasta ahora la hoja de trabajo elegía el EQUIPO por fila: el analista, con la
 * muestra ya en la mano, seleccionaba a qué transformador pertenecía. Eso es una
 * oportunidad de pegarle el ensayo al trafo equivocado, y además el analista no
 * es quien tiene ese dato — lo tiene quien recibió el envase.
 *
 * El sistema Rails anterior ya modelaba esto bien y conviene decirlo:
 *
 *     rems  →  rem_correlatives (la muestra, con transformer_id)
 *                  └─ rem_jobs (qué prueba le toca)  →  lab_details (la hoja)
 *
 * Lo que falló allá fue la implementación, no la idea. Acá se conserva la idea y
 * se corrigen las cuatro cosas que la rompían.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LAS CUATRO CORRECCIONES                                                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * 1. EL CORRELATIVO NO SE BUSCA, SE RESERVA.
 *    Allá se generaba leyendo el último y sumando uno, DENTRO de un bucle, sin
 *    bloqueo, con la validación de unicidad comentada y filtrando por
 *    `deleted = 0` — o sea que reemitía el número de una muestra dada de baja,
 *    ahora para otra muestra. Que ocurrió en producción está documentado por el
 *    propio equipo en su archivo para cazar números duplicados.
 *    Acá hay `sample_counters` (§ más abajo) y un índice ÚNICO de verdad.
 *
 * 2. LA MUESTRA SE UNE A LA HOJA POR CLAVE FORÁNEA, NO POR TEXTO.
 *    Allá `lab_details.num_test` era un varchar copiado por jQuery desde la
 *    primera celda de la fila, y para encontrar la muestra se partía la cadena
 *    ("2026-0695" → año 2026, número 695) y se interpolaba cruda en SQL. Sin
 *    clave foránea, sin índice, y sin garantía de que la muestra existiera. El
 *    propio autor lo anotó en el modelo: "No funciona si el usuario crea antes
 *    de que registre el ingreso de la muestra".
 *    Acá el número de muestra es una ETIQUETA que se muestra; la relación es
 *    `worksheet_rows.sample_test_id`.
 *
 * 3. SOLO SE CREAN LAS PRUEBAS QUE SE PIDEN.
 *    Allá, al crear una muestra se insertaba una fila por CADA prueba del
 *    catálogo y después se marcaba a mano cuáles iban de verdad: una remisión de
 *    40 muestras insertaba más de mil filas que no significaban nada.
 *
 * 4. EL ESTADO SE ESCRIBE CUANDO PASA LO QUE LO CAMBIA.
 *    Allá se recalculaba AL LEER, desde la vista, con `Rem.update` y
 *    `update_all` dentro de un GET. Abrir una remisión de 40 muestras eran unas
 *    320 consultas y 40 escrituras. Y el estado dependía de que alguien abriera
 *    la pantalla: si nadie la abría, quedaba viejo y los filtros mentían.
 *    Acá el estado de cada prueba pedida es una columna que cambia por evento, y
 *    abrir la recepción es una consulta con GROUP BY que no escribe nada.
 */
return new class extends Migration {
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────────
        // sample_counters — el correlativo, reservado y no buscado
        // ─────────────────────────────────────────────────────────────────
        //
        // Una fila por (workspace, año). Reservar N números es UN update con la
        // fila bloqueada:
        //
        //     UPDATE sample_counters SET last_number = last_number + :n
        //      WHERE tenant_id = :t AND year = :y
        //  RETURNING last_number;
        //
        // Sin recorrer nada y sin condición de carrera. Es exactamente lo que el
        // laboratorio pide —"registro lo que entra y digo cuántos correlativos
        // quiero"— y lo que el sistema anterior no podía dar.
        Schema::create('sample_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()
                ->constrained('tenants')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'year'], 'sample_counters_unico');
        });

        // ─────────────────────────────────────────────────────────────────
        // receptions — la remisión: una entrega física del cliente
        // ─────────────────────────────────────────────────────────────────
        Schema::create('receptions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            // Su propio número, independiente del de las muestras.
            $table->string('code', 30)->nullable();
            $table->string('service_order', 60)->nullable();     // orden de servicio

            $table->foreignId('customer_id')->nullable()
                ->constrained('customers')->nullOnDelete();
            $table->foreignId('sampler_id')->nullable()
                ->constrained('users')->nullOnDelete();
            // Quien tomó la muestra puede no ser un usuario del sistema (personal
            // del cliente, un tercero). Se guarda el nombre para ese caso.
            $table->string('sampler_name', 120)->nullable();

            $table->dateTime('received_at');
            $table->date('due_at')->nullable();                  // fecha comprometida

            // ── Verificación física del envase, al recibirlo ──────────────
            // El sistema anterior las tenía como tres enteros sueltos. Son las
            // tres cosas que se miran ANTES de aceptar la muestra, y si alguna
            // falla el ensayo puede ser inválido: conviene que consten.
            $table->boolean('container_ok')->nullable();         // envase adecuado
            $table->boolean('volume_ok')->nullable();            // volumen suficiente
            $table->boolean('label_ok')->nullable();             // datos completos
            $table->unsignedSmallInteger('packages')->nullable();// cuántos envases

            $table->boolean('is_urgent')->default(false);
            $table->text('notes')->nullable();

            // draft → confirmed → closed | cancelled
            // El correlativo se emite al CONFIRMAR, no al crear: mientras la
            // recepción está en borrador todavía se corrige sin quemar números.
            $table->string('status', 12)->default('draft')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()
                ->constrained('users')->nullOnDelete();

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

            $table->index(['tenant_id', 'received_at'], 'idx_receptions_workspace_fecha');
            $table->index(['customer_id', 'received_at'], 'idx_receptions_cliente_fecha');
            $table->index('created_at', 'idx_receptions_created_at');
            $table->index('deleted_at', 'idx_receptions_deleted_at');
        });

        // ─────────────────────────────────────────────────────────────────
        // samples — LA MUESTRA. Acá vive el equipo.
        // ─────────────────────────────────────────────────────────────────
        Schema::create('samples', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            // El correlativo, partido en sus dos piezas Y armado.
            // Se guardan las tres cosas a propósito: `year` y `number` son lo
            // que ordena y lo que hace único; `code` es lo que se imprime en la
            // etiqueta y se busca. En el sistema anterior existían las dos
            // primeras y la tercera se armaba al vuelo, así que buscar por el
            // número que el cliente cita obligaba a partir la cadena.
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('number');
            $table->string('code', 20);

            $table->foreignId('reception_id')
                ->constrained('receptions')->cascadeOnDelete();
            // De qué equipo se tomó. Puede llegar en nulo —el cliente a veces
            // manda el envase sin identificar el trafo— y eso es justamente el
            // pendiente que la pantalla de recepción muestra en rojo.
            $table->foreignId('equipment_id')->nullable()
                ->constrained('equipment')->nullOnDelete();
            $table->foreignId('oil_type_id')->nullable()
                ->constrained('oil_types')->nullOnDelete();

            $table->date('sampled_at')->nullable();              // fecha de toma
            $table->string('sampling_point', 80)->nullable();    // grifo, tapa, conmutador
            $table->string('container', 60)->nullable();         // jeringa, botella…
            $table->boolean('is_urgent')->default(false);
            $table->text('notes')->nullable();

            // pending → in_progress → completed | reported
            // Se DERIVA de sus pruebas pedidas, pero se GUARDA: es lo que
            // permite listar y filtrar sin recorrer las pruebas de cada muestra.
            $table->string('status', 12)->default('pending')->index();

            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('reception_id', 'idx_samples_recepcion');
            $table->index(['equipment_id', 'sampled_at'], 'idx_samples_equipo_fecha');
            $table->index(['tenant_id', 'status'], 'idx_samples_workspace_estado');
        });

        // El correlativo es único DE VERDAD, y esta es la diferencia que
        // importa: el sistema anterior tenía la validación comentada en el
        // modelo y ninguna restricción en la base.
        //
        // Incluye las dadas de baja a propósito (no es un índice parcial): un
        // número emitido queda quemado para siempre. En un laboratorio, un
        // correlativo reutilizado es un resultado atribuido a otra muestra.
        DB::statement(
            'CREATE UNIQUE INDEX samples_correlativo_unico ON samples (tenant_id, year, number)'
        );
        DB::statement(
            'CREATE UNIQUE INDEX samples_codigo_unico ON samples (tenant_id, code)'
        );

        // ─────────────────────────────────────────────────────────────────
        // sample_tests — qué prueba le toca a cada muestra
        // ─────────────────────────────────────────────────────────────────
        Schema::create('sample_tests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sample_id')
                ->constrained('samples')->cascadeOnDelete();
            $table->foreignId('test_definition_id')
                ->constrained('test_definitions')->cascadeOnDelete();

            // pending: pedida, sin ensayar
            // in_progress: hay fila de bancada cargada, la hoja no se validó
            // validated: el supervisor firmó la hoja
            // reported: salió en un informe al cliente
            // cancelled: se dio de baja el pedido
            $table->string('status', 12)->default('pending')->index();

            // Dónde se ensayó. Se llena solo, al cargar la fila.
            $table->unsignedBigInteger('worksheet_row_id')->nullable()->index();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();

            $table->timestamps();

            // Una prueba no se pide dos veces para la misma muestra.
            $table->unique(['sample_id', 'test_definition_id'], 'sample_tests_unico');
            // "Qué falta por ensayar", que es la consulta de la bandeja.
            $table->index(['test_definition_id', 'status'], 'idx_sample_tests_prueba_estado');
            $table->index(['tenant_id', 'status'], 'idx_sample_tests_workspace_estado');
        });

        // ─────────────────────────────────────────────────────────────────
        // Y ahora sí: las claves foráneas de la bancada
        // ─────────────────────────────────────────────────────────────────
        // `worksheet_rows.sample_id` y `sample_test_id` existían desde el
        // principio como enteros sueltos, esperando estas tablas. Recién ahora
        // pueden ser claves foráneas de verdad.
        Schema::table('worksheet_rows', function (Blueprint $table) {
            $table->foreign('sample_id')->references('id')->on('samples')->nullOnDelete();
            $table->foreign('sample_test_id')->references('id')->on('sample_tests')->nullOnDelete();
        });

        Schema::table('results', function (Blueprint $table) {
            $table->foreign('sample_id')->references('id')->on('samples')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropForeign(['sample_id']);
        });

        Schema::table('worksheet_rows', function (Blueprint $table) {
            $table->dropForeign(['sample_id']);
            $table->dropForeign(['sample_test_id']);
        });

        Schema::dropIfExists('sample_tests');
        Schema::dropIfExists('samples');
        Schema::dropIfExists('receptions');
        Schema::dropIfExists('sample_counters');
    }
};
