<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El correlativo de la ENTREGA, uno por workspace y por año.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ SE GENERA EN VEZ DE ESCRIBIRSE                                   │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El «Nº de recepción» era un campo de TEXTO LIBRE que el operador tenía que
 * inventar. El sistema anterior directamente no lo tiene: identifica la entrega
 * por cliente y fecha, y por eso encontrar una vieja significaba desplazarse por
 * el listado.
 *
 * Las dos salidas eran quitarlo o generarlo, y generarlo es la buena: el sistema
 * necesita nombrar la entrega —va en la URL, en el registro de auditoría, en la
 * caja física de frascos y en el asunto del correo al cliente— y el operador no
 * tiene por qué pensarlo. Deja de ser un campo que llenar.
 *
 * Contador propio y NO el de las muestras: son dos numeraciones distintas y
 * compartir el contador quemaría números de muestra al registrar una entrega.
 * Se reinicia cada año, como el de las muestras, y por eso la clave incluye el
 * año.
 *
 * Misma forma que `sample_counters` a propósito: `SampleNumberAllocator` resolvió
 * la carrera que el sistema anterior tenía (leía el último número con un SELECT
 * y lo escribía después, así que dos altas simultáneas emitían el mismo), y esta
 * tabla existe para poder aplicar exactamente esa solución sin tocarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reception_counters', function (Blueprint $table) {
            $table->id();
            // Nullable: el workspace global (super) también numera.
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            // La clave del bloqueo: sin esto, dos altas simultáneas del mismo
            // año crearían dos filas de contador y emitirían el mismo número.
            $table->unique(['tenant_id', 'year'], 'reception_counters_unicos');
        });

        Schema::table('receptions', function (Blueprint $table) {
            // El año y el número que componen el código, guardados aparte: sin
            // ellos, ordenar o filtrar por año exigiría partir la cadena.
            $table->unsignedSmallInteger('year')->nullable()->after('code');
            $table->unsignedInteger('number')->nullable()->after('year');

            $table->unique(['tenant_id', 'year', 'number'], 'receptions_correlativo_unico');
        });
    }

    public function down(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->dropUnique('receptions_correlativo_unico');
            $table->dropColumn(['year', 'number']);
        });

        Schema::dropIfExists('reception_counters');
    }
};
