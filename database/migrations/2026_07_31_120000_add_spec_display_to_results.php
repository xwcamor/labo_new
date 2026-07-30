<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El TEXTO del valor de orientación, congelado en el resultado.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ NO ALCANZA CON spec_min Y spec_max                               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El informe imprime el valor de orientación tal como el laboratorio lo
 * escribió: `0.50 - máximo`, `40.0 - mínimo`, `Brillante y Claro`. Ese texto ES
 * el dato —está en `spec_limits.display`, poblado en los 157 límites— y el
 * cuadro de límites del sistema anterior lo guardaba igual, como texto.
 *
 * El informe no lo leía: lo REARMABA desde los números
 * (`LegacyReportRenderer::orientacionVieja`), con tres pérdidas:
 *
 *   1. `0.50 - máximo` salía `0.5 - máximo` — el formateador recorta el cero, y
 *      el laboratorio escribió los dos decimales a propósito.
 *   2. Un límite CUALITATIVO no tiene mínimo ni máximo, así que la condición
 *      visual —cuyo criterio es la frase `Brillante y Claro`— imprimía una
 *      raya. El criterio existía en la base y el papel decía que no había.
 *   3. El acetileno de un cuadro traía el límite escrito `16` sin la palabra
 *      "máximo"; rearmarlo desde el número nunca puede reproducir eso.
 *
 * Se congela por la misma razón que `spec_status`, `spec_min` y `spec_max`: el
 * papel tiene que decir contra qué se juzgó ESA muestra, no contra lo que el
 * cuadro diga hoy. Editar un límite no puede reescribir un informe ya emitido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            // Texto y no numérico: el criterio de la condición visual es una
            // frase. 120 caracteres entran los tres textos más largos del
            // cuadro heredado con margen.
            $table->string('spec_display', 120)->nullable()->after('spec_max');
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn('spec_display');
        });
    }
};
