<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién dio por bueno el análisis de resultados, y cuándo.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL DEFECTO QUE ESTO CIERRA                                               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El informe se podía EMITIR sin que nadie hubiera abierto el análisis de
 * resultados. El motor compone los párrafos cuando esa pantalla se abre; si no
 * se abre, no hay párrafos, y el informe salía con los títulos de familia
 * —FISICOQUIMICO, CROMATOGRAFICO, AZUFRE CORROSIVO…— y ni una línea debajo de
 * ninguno. Un papel firmado, con número de verificación, que no dice nada en la
 * única sección que es opinión del laboratorio.
 *
 * Pasó de verdad: en la muestra de demostración, con las 29 pruebas cargadas y
 * validadas, `sample_diagnoses` quedó en CERO filas y el informe se emitió igual.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ UNA CONFIRMACIÓN EXPLÍCITA Y NO «QUE HAYA TEXTO»                │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Se podría exigir simplemente que las familias tengan párrafo. No alcanza: el
 * motor los compone solo, así que esa condición se cumpliría sin que nadie los
 * hubiera leído, y el análisis es justo la parte del informe donde el
 * laboratorio OPINA. Lo que tiene que constar es que una persona lo dio por
 * bueno, con su nombre y su hora — que es además lo que una auditoría pregunta.
 *
 * La confirmación se CAE sola si después alguien edita un párrafo: lo que se
 * confirmó fue un texto, no un trámite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_reports', function (Blueprint $table) {
            $table->timestamp('analysis_confirmed_at')->nullable()->after('issued_at');
            $table->foreignId('analysis_confirmed_by')->nullable()->after('analysis_confirmed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sample_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('analysis_confirmed_by');
            $table->dropColumn('analysis_confirmed_at');
        });
    }
};
