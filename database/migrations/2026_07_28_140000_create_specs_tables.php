<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los límites de norma, como datos.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ SE ESTÁ REEMPLAZANDO                                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema Rails anterior el límite que se compara contra el resultado era
 * un TEXTO guardado en la tabla ancha del informe:
 *
 *     rem_report_details.aci_ori = "0.20 - máximo"
 *     rem_report_details.rig_ori = "40.0 - mínimo"
 *     rem_report_details.con_ori = "Brillante y Claro"
 *
 * Con eso, para pintar un semáforo había que PARSEAR la cadena y quedarse con
 * el número; y el criterio que decidía qué texto poner era un árbol de
 * `if/elsif` sobre el tipo de aceite, el tipo de equipo y la tensión, escrito a
 * mano. Ese árbol está DUPLICADO en dos archivos —`rem_report.rb` y
 * `rem_report_detail.rb`— y ya divergió: uno trata como vegetal los aceites
 * 5, 6 y 9, el otro solo el 5. Un informe de girasol nace sin norma asignada y
 * solo la recibe si alguien lo edita después.
 *
 * Acá el límite es un NÚMERO en una fila, con su operador, y el criterio que lo
 * elige es una consulta sobre esas filas. Agregar la edición 2019 de una norma
 * es cargar filas; en el sistema anterior era editar dos archivos y desplegar.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ TRES NORMAS DISTINTAS QUE EL SISTEMA ANTERIOR MEZCLABA                   │
 * └──────────────────────────────────────────────────────────────────────────┘
 *   método      con qué se midió           ASTM D1816, 2.0 mm
 *   aceptación  contra qué se compara      IEEE C57.106
 *   diagnóstico cómo se interpreta el gas  IEC 60599  (eso es de TrafoDex)
 *
 * Confundir las dos primeras es lo que producía informes incoherentes: el PDF
 * imprimía "ASTM D877" como método y al lado un límite sacado de la tabla de
 * D1816 — son separaciones de electrodos distintas y los kV no son comparables.
 * Van en la misma tabla con un campo `kind`, y NUNCA se sustituyen entre sí.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA VIGENCIA ES LO QUE PROTEGE LOS INFORMES YA EMITIDOS                   │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Cada cuadro tiene `effective_from`/`effective_to`. Cuando el laboratorio
 * adopta una edición nueva se carga el cuadro nuevo y se cierra el anterior; no
 * se toca nada de lo emitido. Y el resultado ya guarda su veredicto congelado
 * (`results.spec_status`, `spec_min`, `spec_max`, `spec_source`), así que un
 * ensayo de 2019 sigue diciendo lo que decía en 2019.
 */
return new class extends Migration {
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────────
        // standards — las normas
        // ─────────────────────────────────────────────────────────────────
        Schema::create('standards', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            $table->string('code', 60);                      // ASTM D1816
            $table->string('name')->nullable();              // su título
            $table->string('edition', 20)->nullable();        // 2019
            $table->string('issuer', 40)->nullable();         // ASTM, IEC, IEEE

            // method     con qué se midió
            // acceptance contra qué se compara el resultado
            // diagnosis  cómo se interpreta (es de TrafoDex, no del laboratorio)
            $table->string('kind', 12)->default('method')->index();

            // Qué norma la reemplazó. Permite avisar en pantalla: "la edición
            // 2015 fue reemplazada por la 2019; hay 43 muestras pendientes que
            // todavía usan la anterior".
            $table->foreignId('superseded_by_id')->nullable()
                ->constrained('standards')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->nullable();

            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // Una norma con su edición no se repite dentro del workspace. Va con
        // COALESCE porque el catálogo es global (tenant nulo) y cada workspace
        // puede agregar las suyas.
        DB::statement(
            "CREATE UNIQUE INDEX standards_codigo_unico ON standards " .
            "(COALESCE(tenant_id, 0), LOWER(code), COALESCE(edition, '')) " .
            "WHERE deleted_at IS NULL"
        );

        // ─────────────────────────────────────────────────────────────────
        // test_methods — el parámetro, separado de cómo se mide
        // ─────────────────────────────────────────────────────────────────
        //
        // Error del sistema anterior: `rig` y `rigep` eran dos columnas, y
        // `f25`, `f90` y `f100` tres. En realidad hay DOS parámetros medidos de
        // varias maneras: la rigidez (por D877 con electrodos planos, o por
        // D1816 a 1 o 2 mm) y el factor de potencia (a 25, 90 o 100 °C).
        //
        // Con esta tabla el informe muestra UNA fila "Rigidez dieléctrica" con
        // el método al lado, en vez de dos filas que compiten; y el límite se
        // busca por (parámetro, método), así que la separación de electrodos
        // deja de ser un supuesto.
        Schema::create('test_methods', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            $table->foreignId('analyte_id')
                ->constrained('analytes')->cascadeOnDelete();
            $table->foreignId('standard_id')->nullable()
                ->constrained('standards')->nullOnDelete();

            $table->string('code', 60);                      // rig_d1816_2mm
            $table->string('label', 120);                    // "ASTM D1816 · 2.0 mm"

            // Las condiciones del ensayo que CAMBIAN el valor esperado: la
            // separación de electrodos, la temperatura. En el sistema anterior
            // no se registraban, y por eso hoy no se sabe con qué gap se
            // midieron los históricos de rigidez.
            $table->json('conditions')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->nullable();

            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['analyte_id', 'is_active'], 'idx_test_methods_parametro');
        });

        DB::statement(
            'CREATE UNIQUE INDEX test_methods_codigo_unico ON test_methods ' .
            '(COALESCE(tenant_id, 0), LOWER(code)) WHERE deleted_at IS NULL'
        );

        // ─────────────────────────────────────────────────────────────────
        // spec_sets — el cuadro de valores de orientación
        // ─────────────────────────────────────────────────────────────────
        //
        // Un cuadro es el conjunto de límites que aplica a una combinación:
        // "Fisicoquímico · Mineral · hasta 69 kV". Es la traducción a datos de
        // cada rama del árbol de `if/elsif` del sistema anterior.
        Schema::create('spec_sets', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            $table->string('code', 80);
            $table->string('label');
            // fiqui | dga | papel | otros — a qué familia de ensayos aplica.
            $table->string('group', 20)->index();

            $table->foreignId('standard_id')->nullable()
                ->constrained('standards')->nullOnDelete();

            // ── Los criterios. Nulo = "cualquiera". ──────────────────────
            $table->foreignId('oil_type_id')->nullable()
                ->constrained('oil_types')->nullOnDelete();
            $table->foreignId('equipment_type_id')->nullable()
                ->constrained('equipment_types')->nullOnDelete();
            $table->string('service_state', 20)->nullable();  // new | in_service
            $table->decimal('voltage_from', 10, 2)->nullable();
            $table->decimal('voltage_to', 10, 2)->nullable();
            $table->decimal('power_from', 10, 2)->nullable();
            $table->decimal('power_to', 10, 2)->nullable();

            // ── Vigencia ─────────────────────────────────────────────────
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('source_note')->nullable();          // de dónde salió
            $table->integer('sort_order')->nullable();

            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // El índice de la resolución: se busca por familia y por aceite.
            $table->index(['group', 'oil_type_id', 'is_active'], 'idx_spec_sets_resolucion');
        });

        DB::statement(
            'CREATE UNIQUE INDEX spec_sets_codigo_unico ON spec_sets ' .
            '(COALESCE(tenant_id, 0), LOWER(code)) WHERE deleted_at IS NULL'
        );

        // ─────────────────────────────────────────────────────────────────
        // spec_limits — el límite, como número
        // ─────────────────────────────────────────────────────────────────
        Schema::create('spec_limits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('spec_set_id')
                ->constrained('spec_sets')->cascadeOnDelete();
            $table->foreignId('analyte_id')
                ->constrained('analytes')->cascadeOnDelete();
            // Cuando el límite depende de CÓMO se midió (la rigidez por D877 no
            // se compara contra la de D1816), el cuadro lo declara.
            $table->foreignId('test_method_id')->nullable()
                ->constrained('test_methods')->nullOnDelete();

            // <= | >= | between | text
            $table->string('operator', 10)->default('<=');
            $table->decimal('min_value', 24, 8)->nullable();
            $table->decimal('max_value', 24, 8)->nullable();
            // Para los cualitativos: "Brillante y Claro", "No Corrosivo".
            $table->string('text_value', 120)->nullable();

            // Banda de aviso: el valor todavía cumple, pero está pegado al
            // límite. El sistema anterior no la tenía —un aceite pasaba de
            // "cumple" a "no cumple" sin escalón— y es justo lo que un
            // laboratorio quiere ver antes de que el equipo salga de norma.
            $table->decimal('warn_min', 24, 8)->nullable();
            $table->decimal('warn_max', 24, 8)->nullable();

            // El texto LITERAL del sistema anterior ("0.20 - máximo"), para
            // poder cotejar cuadro por cuadro contra los informes viejos.
            $table->string('display', 60)->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->nullable();

            $table->timestamps();

            // Un parámetro no puede tener dos límites en el mismo cuadro para
            // el mismo método.
            $table->unique(
                ['spec_set_id', 'analyte_id', 'test_method_id'],
                'spec_limits_unico'
            );
            $table->index('analyte_id', 'idx_spec_limits_parametro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spec_limits');
        Schema::dropIfExists('spec_sets');
        Schema::dropIfExists('test_methods');
        Schema::dropIfExists('standards');
    }
};
