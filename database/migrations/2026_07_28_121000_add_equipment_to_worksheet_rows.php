<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A qué EQUIPO pertenece la muestra de esta fila.
 *
 * Sin esto, `results` no puede responder la única pregunta para la que existe:
 * "la acidez de este transformador en cinco años". El parámetro y la fecha ya
 * los tiene; le faltaba el equipo.
 *
 * En el destino final el equipo viene de la MUESTRA (`samples`, fase 3), que es
 * donde conceptualmente vive: una muestra se extrae de un equipo en una fecha y
 * después se le corren varias pruebas. Mientras esa tabla no exista, la fila de
 * bancada apunta al equipo directamente.
 *
 * Los dos caminos conviven a propósito y el orden de precedencia está escrito
 * en ResultMaterializer: si hay muestra, manda la muestra; si no, este campo.
 * Cuando llegue la fase 3 no hay que migrar nada: las filas viejas conservan su
 * equipo y las nuevas lo toman de la muestra.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('worksheet_rows', function (Blueprint $table) {
            $table->foreignId('equipment_id')->nullable()->after('sample_test_id')
                ->constrained('equipment')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('worksheet_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_id');
        });
    }
};
