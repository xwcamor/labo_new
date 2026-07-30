<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La presión atmosférica del laboratorio al correr el ensayo.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ES UN DATO Y NO UN ADORNO                                        │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El informe de cromatografía la IMPRIME en su bloque de condiciones de ensayo, y
 * tiene por qué: la cromatografía de gases mide volúmenes, y un volumen depende
 * de la presión a la que se midió. El laboratorio está en Lima, casi a nivel del
 * mar, pero el mismo método corrido en un laboratorio de altura da otro número
 * con la misma muestra. Es parte de la trazabilidad del ensayo que pide la
 * ISO/IEC 17025: hay que poder decir en qué condiciones se midió.
 *
 * En el sistema anterior vivía en la bitácora diaria del laboratorio
 * (`cro_temperatures.cro_lab_pre` y `fiq_temperatures.fiq_lab_pre`, con 100 filas
 * reales cada una) y quedaba congelada en cada informe emitido.
 *
 * Al migrar se portaron la temperatura ambiente y la humedad, y ESTA se perdió:
 * no existía columna en ninguna tabla del sistema nuevo, así que el informe la
 * imprimía como raya fija. Un campo que el papel muestra y el sistema no puede
 * guardar es peor que no mostrarlo: parece un dato que alguien olvidó cargar.
 *
 * Va en la HOJA y no en la muestra, junto a las otras dos condiciones del
 * ensayo: la presión es del momento y del lugar en que se corrió la tanda, no de
 * la muestra. La bitácora diaria —una fila por fecha, que precargaba estos tres
 * valores para todas las hojas del día— sigue pendiente y está anotada en el
 * checklist como C1; esta columna es lo que permite que el dato exista mientras
 * tanto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worksheets', function (Blueprint $table) {
            // hPa. Rango real de la escala: del orden de 1013 al nivel del mar y
            // unos 700 en la sierra, así que cuatro enteros y un decimal sobran.
            $table->decimal('lab_pressure_hpa', 6, 1)->nullable()->after('ambient_humidity');
        });
    }

    public function down(): void
    {
        Schema::table('worksheets', fn (Blueprint $t) => $t->dropColumn('lab_pressure_hpa'));
    }
};
