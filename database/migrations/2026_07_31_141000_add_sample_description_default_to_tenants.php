<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El texto con el que arranca la descripción de la muestra.
 *
 * El sistema anterior lo traía escrito dentro del formulario
 * (`_form_new_data_customer.html.erb:48`):
 *
 *     "Se recibió muestra según procedimiento P-PG-TR-LA-18-20."
 *
 * Copiarlo al código nuevo sería repetir el error que esta migración vino a
 * corregir, y por un motivo concreto, no de prolijidad: **la frase cita un
 * procedimiento con código de versión**. Los procedimientos se revisan. El día
 * que el laboratorio pase a la `-21`, un texto clavado hace que cada informe
 * siga afirmando la versión vieja, y corregir un dato del laboratorio exigiría
 * un deploy. Es el mismo caso que el número de certificado `AT-2596`, que ya se
 * sacó del HTML del informe por esto.
 *
 * Va al workspace, junto a sus dos hermanos que ya viven ahí y se editan en la
 * misma pantalla: `report_disclaimer` (el descargo del pie) y
 * `accreditation_note` (el párrafo del certificado). Los tres son texto del
 * informe que el laboratorio escribe.
 *
 * Nace VACÍO. Un valor de fábrica con el código de procedimiento de OTRO
 * laboratorio sería peor que ninguno: la descripción arrancaría afirmando un
 * procedimiento que quien la lee no tiene.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('sample_description_default')->nullable()->after('report_disclaimer');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('sample_description_default');
        });
    }
};
