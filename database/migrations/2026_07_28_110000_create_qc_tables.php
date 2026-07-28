<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control de calidad analítica: cartas de control y repetibilidad.
 *
 * Es lo que sostiene la acreditación del laboratorio. En el sistema Rails
 * viejo son los submenús "Límite de Tendencias" y "Tendencias" de cada prueba.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA TERCERA TABLA ANCHA DEL SISTEMA VIEJO                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `patron_tendences` guarda los cinco límites de la carta de control por gas,
 * como columnas fijas:
 *
 *     lci, lai, lc, las, lcs            (hidrógeno, sin prefijo)
 *     oxi_lci, oxi_lai, oxi_lc, …       (oxígeno)
 *     nit_…, met_…, mon_…, dio_…, eti_…, eta_…, ace_…
 *
 * 5 límites × 9 gases = 45 columnas, todas `varchar(10)` —incluso los
 * números—, y solo para cromatografía. Agregar la carta de control de otro
 * parámetro exige cinco columnas nuevas y tocar el formulario.
 *
 * Es la misma enfermedad que `rem_report_details` (221 columnas) y que los
 * `if` de los límites normativos: lo que varía está en el esquema en vez de
 * estar en las filas.
 *
 * Acá una carta de control es UNA FILA por (prueba, parámetro), con sus
 * límites y su vigencia. Sumar un parámetro es insertar una fila.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ SIGNIFICAN LAS SIGLAS                                                │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Son una carta de Shewhart, la misma que usa Levey-Jennings en laboratorio
 * clínico:
 *
 *     LCS   límite de control superior   (media + 3 desvíos)
 *     LAS   límite de alerta superior    (media + 2 desvíos)
 *     LC    línea central                (la media del patrón)
 *     LAI   límite de alerta inferior    (media − 2 desvíos)
 *     LCI   límite de control inferior   (media − 3 desvíos)
 *
 * Se guardan los cinco EXPLÍCITOS y no solo media y desvío, porque el
 * laboratorio a veces los fija por criterio propio o por lo que exige la norma,
 * y no siempre salen del cálculo. Si se derivan, `center` y `sd` quedan
 * cargados y `is_derived` en verdadero, para poder recalcularlos.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LO QUE SE AGREGA                                                         │
 * └──────────────────────────────────────────────────────────────────────────┘
 * - VIGENCIA. Cambiar el lote del patrón cambia los límites. El sistema viejo
 *   los pisaba y las cartas históricas quedaban dibujadas contra límites que
 *   no eran los de su momento.
 * - REGLAS DE WESTGARD. El viejo dibujaba los límites pero no evaluaba nada:
 *   el analista tenía que mirar el gráfico y darse cuenta.
 * - REPETIBILIDAD desde los duplicados, que hoy se cargan y no se usan.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── La carta de control de un parámetro dentro de una prueba ─────
        Schema::create('qc_charts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();
            $table->foreignId('test_definition_id')
                ->constrained('test_definitions')->cascadeOnDelete();

            // Sobre qué se controla. Normalmente el campo de resultado; en
            // cromatografía hay una carta por gas, y cada gas es un campo.
            $table->foreignId('test_field_id')->nullable()
                ->constrained('test_fields')->nullOnDelete();
            $table->foreignId('analyte_id')->nullable()
                ->constrained('analytes')->nullOnDelete();

            $table->string('label', 150)->nullable();
            $table->string('control_lot', 100)->nullable();   // lote del patrón

            // Los cinco límites, tal como el laboratorio los declara.
            $table->decimal('lcl', 18, 6)->nullable();   // LCI
            $table->decimal('lwl', 18, 6)->nullable();   // LAI
            $table->decimal('center', 18, 6)->nullable();// LC
            $table->decimal('uwl', 18, 6)->nullable();   // LAS
            $table->decimal('ucl', 18, 6)->nullable();   // LCS

            // Si se derivan de media y desvío en vez de fijarse a mano.
            $table->decimal('sd', 18, 6)->nullable();
            $table->boolean('is_derived')->default(false);
            $table->decimal('warn_sigma', 4, 2)->default(2);
            $table->decimal('action_sigma', 4, 2)->default(3);

            // Criterio de repetibilidad para los duplicados: diferencia
            // máxima aceptable entre dos lecturas de la misma muestra.
            $table->decimal('repeatability_limit', 18, 6)->nullable();
            $table->string('repeatability_mode', 20)->default('absolute'); // absolute|relative

            // Vigencia: al cambiar el lote del patrón se cierra la carta
            // anterior y se abre una nueva. Las cartas históricas siguen
            // dibujándose contra los límites que regían ese día.
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->text('comment')->nullable();
            $table->boolean('is_active')->default(true);

            // A propósito NO es único, a diferencia del resto de los legacy_id
            // del esquema: en el sistema viejo UNA fila de `patron_tendences`
            // contenía los cinco límites de los NUEVE gases, en 45 columnas.
            // Al importarla se convierte en nueve cartas que comparten el mismo
            // id de origen. La unicidad real es (legacy_id, test_field_id).
            $table->unsignedInteger('legacy_id')->nullable();

            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deleted_description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['test_definition_id', 'test_field_id'], 'idx_qc_charts_test_field');
            $table->index(['effective_from', 'effective_to'], 'idx_qc_charts_vigencia');
        });

        // ── Cada punto medido del patrón ─────────────────────────────────
        // Se materializa desde las filas `kind = control` de las hojas de
        // trabajo. Se guarda aparte y no se calcula al vuelo porque la carta
        // se consulta seguido y el z tiene que quedar congelado contra los
        // límites vigentes EN SU MOMENTO, no contra los de hoy.
        Schema::create('qc_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_chart_id')
                ->constrained('qc_charts')->cascadeOnDelete();
            $table->foreignId('worksheet_row_id')->nullable()
                ->constrained('worksheet_rows')->nullOnDelete();
            $table->dateTime('measured_at')->index();
            $table->decimal('value', 24, 8);
            $table->decimal('z_score', 12, 6)->nullable();

            // ok | warn (pasó el límite de alerta) | out (pasó el de control)
            $table->string('flag', 10)->default('ok')->index();

            // Regla de Westgard que se violó, si alguna: 1_3s, 2_2s, R_4s,
            // 4_1s, 10x. El sistema viejo dibujaba los límites pero no
            // evaluaba: el analista tenía que verlo a ojo.
            $table->string('westgard_rule', 10)->nullable();

            $table->boolean('is_excluded')->default(false);  // descartado con motivo
            $table->text('exclusion_reason')->nullable();

            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();
            $table->timestamps();

            $table->index(['qc_chart_id', 'measured_at'], 'idx_qc_points_chart_date');
        });

        // ── Repetibilidad: el par de un duplicado ────────────────────────
        // El sistema viejo permite cargar duplicados (lab_detail_type_id = 3)
        // y no hace nada con ellos. Acá se comparan contra el criterio.
        Schema::create('qc_duplicates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_chart_id')->nullable()
                ->constrained('qc_charts')->nullOnDelete();
            $table->foreignId('original_row_id')
                ->constrained('worksheet_rows')->cascadeOnDelete();
            $table->foreignId('duplicate_row_id')
                ->constrained('worksheet_rows')->cascadeOnDelete();
            $table->dateTime('measured_at')->index();
            $table->decimal('value_a', 24, 8)->nullable();
            $table->decimal('value_b', 24, 8)->nullable();
            $table->decimal('difference', 24, 8)->nullable();
            $table->decimal('relative_difference', 12, 6)->nullable();
            $table->boolean('within_limit')->nullable();
            $table->foreignId('tenant_id')->nullable()->index()
                ->constrained('tenants')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_duplicates');
        Schema::dropIfExists('qc_points');
        Schema::dropIfExists('qc_charts');
    }
};
