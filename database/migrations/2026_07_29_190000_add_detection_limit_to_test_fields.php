<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El límite de detección del método: por debajo de él no se informa un número.
 *
 * El informe acreditado del sistema viejo NO imprimía el valor medido cuando
 * caía por debajo del límite de detección: imprimía el límite con un "menor
 * que". Un hidrógeno de 0.4 ppm salía como "< 1", y así es como corresponde —
 * el método no distingue 0.4 de 0.7, y publicar el número sugiere una precisión
 * que el ensayo no tiene.
 *
 * Esos cortes estaban CLAVADOS en el HTML del informe viejo, repetidos hasta
 * tres veces por gas (una vez por rama del `if` que decidía el color):
 *
 *     <% if rem_report_detail.cro2_val.to_f < 105.4 %>
 *       <b>< 105.4 </b>
 *     <% else %> … <% end %>
 *
 * Acá es una columna. Cambiar el límite de detección de un método —cosa que
 * pasa cuando se cambia el equipo o se revalida— es editar un número en la
 * ficha de la prueba, no buscar 27 apariciones en una plantilla.
 *
 * OJO: esto es SOLO PRESENTACIÓN. El veredicto (`results.spec_status`) se
 * decidió al validar la hoja, con el valor medido, y no se toca. Un valor por
 * debajo del límite de detección se sigue comparando contra la norma con el
 * número real; lo único que cambia es cómo se imprime. Mezclar las dos cosas
 * haría que el papel y el criterio discrepen, que es exactamente el error del
 * sistema viejo que este proyecto vino a corregir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_fields', function (Blueprint $table) {
            $table->decimal('detection_limit', 18, 6)->nullable()->after('min_exclusive');
        });
    }

    public function down(): void
    {
        Schema::table('test_fields', fn (Blueprint $t) => $t->dropColumn('detection_limit'));
    }
};
