<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El almacén del laboratorio: qué hay, quién se lo llevó y qué falta volver.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ ERA EN EL SISTEMA ANTERIOR                                           │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Cinco tablas (`stocks`, `stock_units`, `stock_details`, `stock_detail_moves`,
 * `stock_detail_returns`) bajo el menú "Seguimiento de Equipos". La forma era
 * correcta —artículo · préstamo · líneas del préstamo · devoluciones
 * parciales— y se conserva. Lo que NO se conserva son sus cuatro agujeros:
 *
 *  1. NO SE SABÍA QUIÉN SE LLEVÓ NADA. El préstamo tenía una `description` de
 *     texto libre y punto: ni usuario, ni nombre, ni firma. Un registro de
 *     préstamos que no dice a quién no sirve para lo único que se pide en una
 *     auditoría 17025, que es dar con el material. Acá el prestatario es
 *     obligatorio: un usuario del sistema o un nombre externo escrito.
 *  2. SE PODÍA DEVOLVER MÁS DE LO PRESTADO. La cantidad devuelta solo validaba
 *     `min=1`, así que el "pendiente de devolución" que la pantalla calculaba
 *     restando podía quedar en negativo y quedaba así. Acá la devolución no
 *     puede superar lo que falta en esa línea.
 *  3. EL ESTADO ERA MENTIRA. `stock_details.is_loan` se escribía en `true` al
 *     crear y no se tocaba nunca más; el método `str_state` que lo leía
 *     ("En Préstamo"/"Devuelto") no se llamaba desde ninguna vista. El estado
 *     real se recalculaba a mano en cada pantalla sumando y restando. Acá el
 *     estado se ESCRIBE cuando ocurre, como el resto del sistema.
 *  4. LAS BAJAS LÓGICAS NO SE RESPETABAN AL SUMAR. La tabla listaba las líneas
 *     con `deleted=0` pero el semáforo de la misma fila sumaba TODAS, borradas
 *     incluidas: dar de baja una línea cambiaba el listado y no el estado.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA EXISTENCIA ES UN DATO DECLARADO, NO UN SALDO CONTABLE                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `stock_items.on_hand` es lo que el laboratorio dice tener, y se corrige a
 * mano. NO se descuenta al prestar ni se suma al devolver, porque para eso
 * haría falta registrar también compras, consumos y mermas — un módulo de
 * movimientos que nadie pidió y que, a medio hacer, deja un número que se aleja
 * de la realidad sin que nadie lo note. Lo que sí se calcula es lo PRESTADO
 * (suma de lo pendiente en los préstamos abiertos), y de ahí lo disponible.
 * Así el número que puede estar mal es uno solo y se ve de dónde sale.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ NO HAY TABLA DE UNIDADES                                         │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `stock_units` tenía UNA columna útil (`name`) y su propio CRUD. Es la misma
 * forma que las otras listas chicas del sistema, así que va como una lista más
 * de `report_catalogs` (`kind = stock_unit`) y se administra en la misma
 * pantalla. En el artículo se guarda el TEXTO de la unidad, igual que en la
 * muestra: renombrar la fila del catálogo no reescribe lo ya cargado.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── El artículo del almacén ──────────────────────────────────────
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            $table->string('code', 40);
            $table->string('name', 160);

            // El texto de la unidad, elegido del catálogo. Ver la cabecera.
            $table->string('unit', 40)->nullable();

            // Lo que el laboratorio declara tener. Ver la cabecera: no es un
            // saldo que el sistema mueva solo.
            $table->integer('on_hand')->default(0);

            // Punto de reposición. Con `on_hand` por debajo, el listado lo
            // marca. El sistema anterior no tenía nada parecido: había que
            // mirar la columna número por número.
            $table->integer('min_qty')->nullable();

            $table->string('location', 80)->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->foreignId('tenant_id')->nullable()->index()->constrained('tenants')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at', 'idx_stock_items_created_at');
            $table->index('deleted_at', 'idx_stock_items_deleted_at');
        });

        // El código no se repite dentro del laboratorio. Parcial, para que dar
        // de baja un artículo libere su código.
        $this->unicoVivo('stock_items', ['tenant_id', 'code'], 'stock_items_code_unique');

        // ── El préstamo ──────────────────────────────────────────────────
        Schema::create('stock_loans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            $table->date('loaned_on')->index();

            // Quién se lo llevó. Uno de los dos, obligatorio (lo impone el
            // controlador): el usuario cuando es alguien del sistema, el nombre
            // cuando es un tercero. Esto es lo que el sistema anterior NO tenía.
            $table->foreignId('borrower_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('borrower_name', 120)->nullable();

            // Para qué. Era la `description` del sistema anterior.
            $table->text('purpose')->nullable();

            // ESCRITO cuando ocurre, no deducido al leer: 'open' mientras algo
            // falte volver, 'returned' cuando no falta nada.
            $table->string('status', 12)->default('open')->index();
            $table->timestamp('returned_at')->nullable();

            $table->foreignId('tenant_id')->nullable()->index()->constrained('tenants')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'loaned_on'], 'idx_stock_loans_estado_fecha');
            $table->index('created_at', 'idx_stock_loans_created_at');
            $table->index('deleted_at', 'idx_stock_loans_deleted_at');
        });

        // ── Las líneas del préstamo: qué artículo y cuánto ───────────────
        Schema::create('stock_loan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_loan_id')->constrained('stock_loans')->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained('stock_items')->restrictOnDelete();

            $table->integer('qty');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['stock_item_id', 'deleted_at'], 'idx_stock_lines_articulo');
        });

        // ── Las devoluciones, que pueden ser parciales y varias ──────────
        Schema::create('stock_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_loan_line_id')->constrained('stock_loan_lines')->cascadeOnDelete();

            $table->date('returned_on');
            $table->integer('qty');
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['stock_loan_line_id', 'deleted_at'], 'idx_stock_returns_linea');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_returns');
        Schema::dropIfExists('stock_loan_lines');
        Schema::dropIfExists('stock_loans');
        Schema::dropIfExists('stock_items');
    }

    /**
     * Índice único que solo mira las filas VIVAS.
     *
     * Postgres lo hace con un índice parcial; el resto (SQLite en las pruebas)
     * mete `deleted_at` en la clave, que consigue lo mismo porque NULL no
     * colisiona con NULL.
     */
    private function unicoVivo(string $tabla, array $columnas, string $nombre): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(sprintf(
                'CREATE UNIQUE INDEX %s ON %s (%s) WHERE deleted_at IS NULL',
                $nombre, $tabla, implode(', ', $columnas)
            ));

            return;
        }

        Schema::table($tabla, function (Blueprint $table) use ($columnas, $nombre) {
            $table->unique([...$columnas, 'deleted_at'], $nombre);
        });
    }
};
