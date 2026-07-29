<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La placa de un transformador de tres devanados dice "500 / 220 / 33 kV", y la
 * de uno con refrigeración forzada dice "120 / 160 / 200 MVA". El sistema viejo
 * guardaba esas dos placas como TEXTO libre (`num_ten`, `num_pot`) y cada vez
 * que necesitaba un número hacía `split('/').map(&:to_f).max` — en cinco
 * lugares distintos, uno de ellos el propio envío a TrafoDex.
 *
 * Acá las placas son columnas numéricas, y hasta ahora había dos de tensión y
 * una de potencia. Sobre los 100 equipos del volcado real: 33 tienen TRES
 * tensiones y 16 tienen TRES potencias, y NINGUNO tiene cuatro. O sea que la
 * ficha perdía el terciario y el informe imprimía una placa incompleta.
 *
 * Tres y no una lista abierta: el dato real nunca pasa de tres, y una tabla
 * hija de devanados para un máximo de tres valores es complejidad sin dueño.
 *
 * Estas columnas son SOLO del LaboRep (fidelidad del informe y migración del
 * histórico). TrafoDex tiene una tensión y una potencia por equipo, y así
 * seguirá: la sincronización le manda el MÁXIMO, que es exactamente lo que el
 * sistema viejo ya hacía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->decimal('voltage_kv_tv', 10, 2)->nullable()->after('voltage_kv_lv'); // terciario
            $table->decimal('power_mva_2', 10, 2)->nullable()->after('power_mva');
            $table->decimal('power_mva_3', 10, 2)->nullable()->after('power_mva_2');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn(['voltage_kv_tv', 'power_mva_2', 'power_mva_3']);
        });
    }
};
