<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las listas chicas que llenan el formulario del informe.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ UNA TABLA Y NO CUATRO                                            │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema anterior eran cuatro tablas —`rem_report_reasons`,
 * `transformer_points`, `transformer_oil_units`, `transformer_oil_marks`— que
 * alimentaban cuatro desplegables del mismo formulario. Ninguna tenía módulo
 * propio: se cargaban por base y ya.
 *
 * Acá habían quedado como TEXTO LIBRE, y eso es lo que llena una columna de
 * «2500 gal», «2500 galones» y «2500Gal» para la misma cosa: después no se
 * puede filtrar, ni agrupar, ni sumar.
 *
 * Las cuatro tienen exactamente la misma forma —nombre, activo, orden— y se
 * administran juntas, así que son una tabla con una columna que dice de cuál
 * lista es. Cuatro módulos idénticos serían cuatro veces el mismo código y
 * cuatro entradas sueltas en el menú; una sola con sus solapas se lee como lo
 * que es: las listas del informe.
 *
 * El `code` es opcional a propósito. Estas listas las escribe el laboratorio y
 * lo que se guarda en la muestra es el TEXTO —igual que en el sistema anterior,
 * donde el informe imprimía `rem_report_reason.name`—; el código existe solo
 * para las pocas filas que el sistema necesita reconocer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            // De qué lista es esta fila. Ver `ReportCatalog::KINDS`.
            $table->string('kind', 40)->index();

            $table->string('name', 120);
            // Opcional: solo lo llevan las filas que el sistema tiene que
            // reconocer por sí mismas, no las que el laboratorio agrega.
            $table->string('code', 60)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            // El id que tenía en el sistema anterior, para el ETL de los
            // informes históricos: ahí la muestra guarda el id, no el texto.
            $table->unsignedInteger('legacy_id')->nullable();

            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deleted_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Dos filas con el mismo nombre en la misma lista son la misma
            // fila escrita dos veces, que es justo lo que el texto libre
            // producía. El parcial deja fuera las borradas.
            $table->unique(['tenant_id', 'kind', 'name'], 'report_catalogs_unicas');
            $table->index(['kind', 'is_active', 'sort_order'], 'report_catalogs_listado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_catalogs');
    }
};
