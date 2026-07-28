<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los datos que la cabecera del informe exige y no estaban.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ LA CABECERA SE REPITE EN CADA PÁGINA                             │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El informe del laboratorio lleva UNA PÁGINA POR ENSAYO, y cada página repite
 * el logo, el número de informe, los datos del cliente y los del equipo. No es
 * un descuido del sistema anterior: es el formato acreditado. Una hoja suelta
 * —fotocopiada, escaneada, adjuntada a un correo— tiene que poder identificarse
 * sola, porque en la práctica los informes se desarman.
 *
 * Reproducir ese formato exige datos que el esquema no tenía, porque los
 * inventé pensando en una sola página:
 *
 *   · `receptions.contact_info` / `end_user` — el contacto de la empresa y el
 *     usuario final. El usuario final NO siempre es el cliente: una
 *     contratista manda muestras del transformador de la minera.
 *   · `samples.report_number` — el correlativo del INFORME, distinto del
 *     correlativo de la muestra. Un informe puede reemitirse.
 *   · `samples.description` — cómo describe el cliente lo que mandó.
 *   · `samples.sampling_reason` — por qué se tomó (rutina, puesta en servicio,
 *     posfalla). Cambia cómo se lee el resultado.
 *   · Las cuatro TEMPERATURAS del muestreo. En el sistema anterior se guardaban
 *     como texto y se imprimían con `to_f.round(2)`, así que un campo vacío
 *     salía "0.00" y quedaba indistinguible de una medición real de cero.
 *     Acá son decimales anulables: vacío es vacío.
 *
 * `worksheets` ya trae la temperatura y la humedad del laboratorio
 * (`ambient_temp_c`, `ambient_humidity`); falta la de la muestra al ensayarla,
 * que es otra cosa: el aceite entra frío del transporte y el ensayo espera a
 * que se estabilice.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->string('contact_info')->nullable()->after('sampler_name');
            $table->string('end_user')->nullable()->after('contact_info');
        });

        Schema::table('samples', function (Blueprint $table) {
            $table->string('report_number', 40)->nullable()->after('code');
            $table->string('description')->nullable()->after('report_number');
            $table->string('sampling_reason', 80)->nullable()->after('description');

            // Condiciones de campo al tomar la muestra.
            $table->decimal('oil_temp_c', 6, 2)->nullable();
            $table->decimal('equipment_temp_c', 6, 2)->nullable();
            $table->decimal('ambient_temp_c', 6, 2)->nullable();
            $table->decimal('relative_humidity', 5, 2)->nullable();
        });

        Schema::table('worksheets', function (Blueprint $table) {
            $table->decimal('sample_temp_c', 6, 2)->nullable()->after('ambient_humidity');
        });
    }

    public function down(): void
    {
        Schema::table('receptions', fn (Blueprint $t) => $t->dropColumn(['contact_info', 'end_user']));
        Schema::table('samples', fn (Blueprint $t) => $t->dropColumn([
            'report_number', 'description', 'sampling_reason',
            'oil_temp_c', 'equipment_temp_c', 'ambient_temp_c', 'relative_humidity',
        ]));
        Schema::table('worksheets', fn (Blueprint $t) => $t->dropColumn('sample_temp_c'));
    }
};
