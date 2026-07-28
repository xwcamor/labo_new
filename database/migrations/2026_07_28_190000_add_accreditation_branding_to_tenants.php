<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El sello de acreditación del informe, como dato del workspace.
 *
 * El informe acreditado lleva arriba a la derecha el sello del organismo que
 * acredita al laboratorio —en el sistema anterior, el de ANAB (ANSI National
 * Accreditation Board)— y al pie el párrafo bilingüe que cita el número de
 * certificado ("…Refer to certificate and scope of accreditation AT-2596").
 *
 * En el sistema anterior las dos cosas estaban clavadas: el sello como un
 * archivo con nombre fijo en los assets (`anab_logo.png`) y el número de
 * certificado escrito dentro del ERB de CADA sección del informe. Eso funciona
 * mientras haya un solo laboratorio y no le cambien la acreditación.
 *
 * No es el caso: el número de certificado vence y se renueva, el alcance
 * cambia, y este sistema es multi-empresa —otro laboratorio se acredita con
 * INACAL, no con ANAB—. Un dato que caduca no puede vivir en el código.
 *
 * `accreditation_logo` va junto a `logo` (mismo disco, misma pantalla de "Mi
 * workspace") y `accreditation_note` guarda el párrafo con su número. Si están
 * vacíos, el informe no dibuja el sello ni la nota: un laboratorio que todavía
 * no está acreditado NO debe emitir un papel que insinúe que sí.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('accreditation_logo')->nullable()->after('logo');
            $table->text('accreditation_note')->nullable()->after('accreditation_logo');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn([
            'accreditation_logo', 'accreditation_note',
        ]));
    }
};
