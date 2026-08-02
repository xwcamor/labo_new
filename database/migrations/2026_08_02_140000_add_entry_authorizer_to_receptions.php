<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién autoriza el ingreso de la muestra.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL CAMPO QUE NUNCA SE MIGRÓ, Y LA AUDITORÍA DIO POR BUENO                │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema anterior lo pide OBLIGATORIO al registrar la entrega
 * (`_form_new.html.erb:69`, con `data-parsley-required`), lo muestra como
 * columna del listado (`_table.html.erb:14`) y estampa su FIRMA en el acta de
 * recepción (`_xls_partial_report.erb:88-99`). Sale de `rems.rem_user_signature_id`.
 *
 * Acá no existía. Y `docs/migracion/auditoria/E-cobertura-tablas.md:152` daba
 * la tabla `rem_user_signatures` por PORTADA, con la nota «las dos tablas del
 * viejo se unificaron en una» — pero `rem_signatures` es QUIÉN FIRMA EL INFORME
 * y `rem_user_signatures` es QUIÉN AUTORIZA LA ENTRADA DE LA MUESTRA. Dos
 * momentos y dos responsabilidades distintas. Al unificarlas se perdió el
 * autorizador y la auditoría de tablas no lo vio, porque comparaba tablas y no
 * lo que el usuario puede hacer.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ APUNTA A `signatures` Y NO A UNA TABLA NUEVA                     │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El autorizador es, igual que el firmante del informe, una PERSONA DEL
 * LABORATORIO CON FIRMA: en el viejo su firma se estampa en el acta. La tabla
 * `signatures` ya guarda exactamente eso —nombre, cargo, imagen de firma,
 * usuario del sistema, activo—, así que una cuarta tabla de personas (después
 * de `users`, `samplers` y `signatures`) sería la misma lista cargada de nuevo,
 * con las mismas firmas escaneadas dos veces y desincronizándose.
 *
 * Lo que distingue al autorizador es un PAPEL, no una tabla, y por eso va como
 * bandera: `signatures.authorizes_entry`. Una persona puede firmar informes,
 * autorizar ingresos, o las dos cosas — que es como funciona un laboratorio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signatures', function (Blueprint $table) {
            $table->boolean('authorizes_entry')->default(false)->after('relation');
        });

        Schema::table('receptions', function (Blueprint $table) {
            // `nullOnDelete` y no `cascade`: si la persona se da de baja del
            // catálogo, la entrega no desaparece — quedó registrada y su acta
            // ya salió. Se pierde el nombre, no el registro.
            $table->foreignId('authorized_by_id')->nullable()->after('sampler_name')
                ->constrained('signatures')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('authorized_by_id');
        });

        Schema::table('signatures', function (Blueprint $table) {
            $table->dropColumn('authorizes_entry');
        });
    }
};
