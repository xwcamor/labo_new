<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El INFORME como registro, no como un PDF que se arma cada vez que alguien
 * aprieta el botón.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ EL INFORME TIENE QUE EXISTIR EN LA BASE                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Hasta ahora el informe se calculaba al vuelo desde la muestra y salía en
 * streaming. Eso alcanza para mirarlo, y no alcanza para nada más:
 *
 *   · El papel que se le entrega al cliente lleva un número —REP-LAB-2026-0800—
 *     y ese número tiene que ser el mismo la próxima vez que se imprima. Sin
 *     registro, cada impresión era un papel distinto sin nombre.
 *   · Una muestra puede tener más de un informe: el PRINCIPAL y los ADICIONALES
 *     que se emiten después (una prueba que llegó tarde, una corrección). El
 *     sistema anterior ya distinguía los dos casos (`type_report` 0 y 1).
 *   · El cliente pide "el informe 0800" por teléfono. Sin tabla no hay nada que
 *     buscar.
 *   · Qué ensayos salen impresos es una decisión del que emite, no una
 *     propiedad de la muestra: el mismo juego de resultados puede publicarse
 *     completo o recortado. Eso vive acá, en `sample_report_tests`.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LO QUE NO SE DUPLICA                                                     │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema anterior copiaba VEINTE columnas del transformador dentro de cada
 * reporte (serie, tensión, potencia, año, conmutador, aceite…). Acá no: esos
 * datos ya viven donde corresponde —el equipo es del equipo, la temperatura de
 * campo es de la muestra— y el formulario de alta del informe los EDITA en su
 * lugar en vez de sacarles una fotocopia.
 *
 * La fotocopia se saca UNA vez y por una razón concreta: al EMITIR. Ahí el
 * papel sale a la calle con un número, y a partir de ese momento tiene que
 * poder reimprimirse igual aunque el equipo cambie de TAG el año que viene. Eso
 * es `snapshot`, y solo se escribe en la emisión.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL CORRELATIVO                                                           │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Mismo mecanismo que el de muestras y por el mismo motivo: el del sistema
 * anterior buscaba el último con `where(deleted: 0)` y sin bloqueo, así que dos
 * emisiones simultáneas se llevaban el mismo número y dar de baja el último lo
 * devolvía a la fila. El repositorio viejo tiene un archivo dedicado a buscar
 * reportes duplicados; no es una hipótesis.
 */
return new class extends Migration {
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────────
        // report_counters — una fila por (workspace, año)
        // ─────────────────────────────────────────────────────────────────
        Schema::create('report_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()
                ->constrained('tenants')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'year'], 'report_counters_unico');
        });

        // ─────────────────────────────────────────────────────────────────
        // sample_reports — el informe
        // ─────────────────────────────────────────────────────────────────
        Schema::create('sample_reports', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            $table->foreignId('sample_id')->constrained('samples')->cascadeOnDelete();

            // REP-LAB-2026-0800. El año y el número van aparte del texto para
            // poder ordenar y filtrar sin partir la cadena, que es lo que hacía
            // el sistema anterior en tres lugares distintos.
            $table->string('code', 40);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('number');

            // PRINCIPAL o ADICIONAL. Lo decide el sistema —el primero de la
            // muestra es el principal—, no el usuario.
            $table->string('kind', 12)->default('primary')->index();

            // BORRADOR mientras se corrige; EMITIDO cuando salió a la calle.
            // Un informe emitido no se edita: se emite otro adicional.
            $table->string('status', 12)->default('draft')->index();

            // Lo propio del papel, no de la muestra.
            $table->date('issued_at')->nullable();      // fecha de emisión
            $table->date('delivered_at')->nullable();   // fecha de entrega
            $table->text('notes')->nullable();

            // Lo que se congela AL EMITIR: cabecera, filas y veredictos tal
            // como se imprimieron. Nulo mientras es borrador.
            $table->json('snapshot')->nullable();
            $table->string('verify_code', 64)->nullable()->index();

            $table->foreignId('tenant_id')->nullable()
                ->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // El correlativo no se repite ni se recicla. A diferencia del viejo,
            // el índice NO excluye los dados de baja: un número quemado queda
            // quemado, porque el cliente tiene un papel con ese número en la
            // mano y el portal de verificación tiene que seguir encontrándolo.
            $table->unique(['tenant_id', 'year', 'number'], 'sample_reports_correlativo');
            $table->index(['sample_id', 'status'], 'sample_reports_muestra_idx');
        });

        // ─────────────────────────────────────────────────────────────────
        // sample_report_tests — qué ensayos salen impresos
        // ─────────────────────────────────────────────────────────────────
        //
        // El sistema anterior resolvía esto con TREINTA columnas `*_display` en
        // la fila del detalle (`aci_display`, `cro_display`, `fur_display`…),
        // una por prueba del catálogo. Agregar una prueba nueva era agregar una
        // columna a la tabla y un `if` a la vista.
        //
        // Acá es una fila por prueba pedida, así que una prueba nueva no toca
        // el esquema.
        Schema::create('sample_report_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_report_id')
                ->constrained('sample_reports')->cascadeOnDelete();
            $table->foreignId('sample_test_id')
                ->constrained('sample_tests')->cascadeOnDelete();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['sample_report_id', 'sample_test_id'], 'sample_report_tests_unico');
        });

        // ─────────────────────────────────────────────────────────────────
        // La marca comercial del aceite
        // ─────────────────────────────────────────────────────────────────
        //
        // La cabecera acreditada la pide ("Marca de aceite: Ergon HyVolt II") y
        // era la única celda del informe que salía en raya fija porque el dato
        // no existía en ninguna tabla. Va como texto y no como catálogo a
        // propósito: es un nombre comercial que el cliente declara, no una
        // entidad del laboratorio. Si mañana se repite lo suficiente como para
        // querer normalizarlo, promoverlo a catálogo es una migración más.
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('oil_brand', 120)->nullable()->after('oil_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', fn (Blueprint $t) => $t->dropColumn('oil_brand'));
        Schema::dropIfExists('sample_report_tests');
        Schema::dropIfExists('sample_reports');
        Schema::dropIfExists('report_counters');
    }
};
