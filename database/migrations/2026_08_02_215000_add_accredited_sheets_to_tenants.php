<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué hojas del informe clásico llevan el sello de acreditación — por workspace.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ES UNA COLUMNA Y NO EL CONFIG                                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Las hojas con sello ANAB estaban clavadas en `config/legacy_report.php`
 * (fisicoquímico, cromatografía y azufre — las tres del sistema anterior). El
 * laboratorio lo señaló: el ALCANCE de la acreditación es un dato suyo, no del
 * programa. Un certificado se amplía o se recorta, y ese día el sello tiene
 * que cambiar de hojas sin esperar a un programador — el mismo criterio por el
 * que el sello y el párrafo del certificado ya se editan en «Mi workspace».
 *
 * `null` = las hojas de fábrica del config (lo que traía el papel viejo). Una
 * lista guardada —aunque esté vacía— manda: `[]` significa «ninguna hoja lleva
 * el sello», que es distinto de «nunca lo configuré».
 *
 * OJO: esto decide el SELLO POR HOJA. La marca (A)/(NA) POR FILA es otra cosa
 * y ya era dato: viaja con la opción de norma que eligió el analista
 * (`test_field_options.is_accredited`, editable en las columnas de la prueba).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('accredited_sheets')->nullable()->after('accreditation_note');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('accredited_sheets');
        });
    }
};
