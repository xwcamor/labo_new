<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las plantillas del análisis de resultados, como DATOS editables.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ SALEN DEL ARCHIVO Y ENTRAN A LA BASE                             │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El párrafo que el informe imprime por cada familia de ensayo ("los resultados
 * están dentro de los valores sugeridos por la Norma…") es exactamente lo que el
 * laboratorio quiere ajustar sin programador: es SU redacción, la que viene
 * firmando desde 2019, y va impresa en el papel que recibe el cliente.
 *
 * Hasta ahora vivía en `database/seeders/data/diagnosis_templates.json`. Eso ya
 * era mejor que el sistema anterior —donde cada frase era un `if` en una vista
 * ERB, con cuatro variantes por familia repetidas en tres archivos— pero seguía
 * exigiendo editar un archivo del repositorio y volver a sembrar. O sea: un
 * despliegue para cambiar una coma.
 *
 * Con esta tabla el JSON pasa a ser el VALOR DE FÁBRICA y la base manda.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ tenant_id NULL = PLANTILLA DE FÁBRICA                                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Mismo criterio que el resto de los catálogos compartidos del sistema: una fila
 * con `tenant_id` nulo es la plantilla estándar que publica el super, y una fila
 * con `tenant_id` es la personalización de ESE laboratorio. El resolvedor
 * prefiere la del workspace y cae a la global, así que un laboratorio puede
 * reescribir su redacción sin tocar el estándar de los demás, y "restaurar" es
 * simplemente borrar su fila.
 *
 * Las BANDAS (`bands`) van en JSON y no en una tabla hija a propósito: son de 3
 * a 5 tramos por plantilla, siempre se leen y se guardan juntos, y nunca se
 * consultan por separado. Una tabla hija agregaría dos JOIN y un editor de
 * filas anidadas sin comprar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosis_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 22)->unique();

            // Nulo = plantilla de fábrica (global). Con valor = la del workspace.
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();

            // La familia de ensayo a la que aplica (fisicoquimico, furanos,
            // analisis_cromatografico…). No es una clave foránea: la familia es
            // una clave de configuración (`config('lab.report_families')`), no
            // una fila de catálogo.
            $table->string('family', 60)->index();

            // Qué caso cubre esta plantilla: sin resultados fuera de norma
            // (`none`), uno (`one`), varios (`many`), o cualquiera (`any`).
            // Es lo que en el sistema anterior eran los cuatro `if` seguidos.
            $table->string('case', 20)->default('any');

            // Cuándo aplica: solo para ciertos aceites o tipos de equipo. Vacío
            // = para todos. El resolvedor prefiere la plantilla MÁS específica.
            $table->json('oil_types')->nullable();
            $table->json('equipment_types')->nullable();

            // Para las plantillas graduadas: el analito cuyo valor decide la
            // banda (por ejemplo el grado de polimerización) y los tramos con
            // su texto. `null` en bands = plantilla de un solo texto.
            $table->string('analyte', 40)->nullable();
            $table->decimal('threshold', 18, 6)->nullable();
            $table->json('bands')->nullable();

            // El texto. Admite los marcadores que resuelve DiagnosisTextService
            // ({ok}, {failed}, {norm}, {value}…). Puede quedar vacío en las
            // plantillas que solo tienen bandas.
            $table->text('body')->nullable();

            // De dónde salió la redacción original (la vista del sistema
            // anterior) y la nota del analista. Es procedencia: sirve para
            // discutir un cambio sabiendo qué decía el papel de antes.
            $table->string('origin')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deleted_description')->nullable();

            // Por familia y caso se busca siempre en conjunto.
            $table->index(['tenant_id', 'family', 'case']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosis_templates');
    }
};
