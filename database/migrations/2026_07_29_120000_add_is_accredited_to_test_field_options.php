<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separar el HECHO de la acreditación de su RÓTULO.
 *
 * `accreditation_flag` llegó del sistema anterior con dos valores, "A" y "NA",
 * y es a la vez el dato y el texto que se imprime como superíndice al lado de
 * la norma: ASTM D974 (A), ASTM D3612 (NA).
 *
 * Mezclar las dos cosas en una columna de texto ya había producido un error
 * real: `isAccredited()` respondía "sí" ante cualquier cadena no vacía, o sea
 * también ante "NA". Un método FUERA del alcance acreditado quedaba contado
 * como acreditado, y con eso el informe estampaba el sello del organismo y el
 * párrafo del certificado en una página que no le corresponde. Eso no es un
 * detalle de presentación: es afirmar una acreditación que el laboratorio no
 * tiene para ese ensayo.
 *
 * Con la columna booleana el hecho es un booleano y el rótulo sigue siendo
 * texto libre —cada organismo marca su alcance con el código que quiere—, y
 * cambiar cómo se imprime la marca no puede volver a cambiar quién está
 * acreditado.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('test_field_options', function (Blueprint $table) {
            $table->boolean('is_accredited')->default(false)->after('accreditation_flag');
        });

        // Traspaso del dato que ya estaba cargado: en el sistema anterior el
        // alcance acreditado se marcaba "A" y el resto "NA" o vacío.
        DB::table('test_field_options')
            ->whereRaw("UPPER(TRIM(COALESCE(accreditation_flag, ''))) = 'A'")
            ->update(['is_accredited' => true]);
    }

    public function down(): void
    {
        Schema::table('test_field_options', fn (Blueprint $t) => $t->dropColumn('is_accredited'));
    }
};
