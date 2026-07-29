<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las pruebas fisicoquímicas comparten UNA tabla en el informe.
 *
 * El informe acreditado dedica una página a "ENSAYOS FISICO-QUIMICOS" con las
 * trece pruebas en una sola tabla —cada fila con su norma: D974 el número
 * ácido, D1816 la rigidez— y una página a cada una de las demás
 * (cromatografía, PCB, furanos…). El motor ya sabía hacerlo: agrupa por
 * `test_definitions.report_comment_group`. Lo que faltaba era el dato.
 *
 * La migración que creó la columna dice en su comentario que las trece
 * comparten párrafo, pero su UPDATE le puso a CADA prueba su propio código; y
 * además corre antes de que el importador siembre las pruebas, así que las 29
 * quedaron con la columna nula. Resultado: trece páginas de una fila cada una,
 * todas repitiendo la cabecera entera.
 *
 * Acá se rellena lo que está vacío, tomando la familia del GRUPO de la prueba
 * (`config('lab.report_families')`) y no de una lista de códigos: si mañana el
 * laboratorio agrega una prueba fisicoquímica, entra sola.
 *
 * Solo toca las filas SIN familia. Si el laboratorio ya reagrupó algo desde el
 * editor de la prueba, esa decisión es suya y no se pisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        $familias = config('lab.report_families', []);

        foreach ($familias as $codigoGrupo => $familia) {
            DB::table('test_definitions')
                ->whereNull('report_comment_group')
                ->whereIn('test_group_id', DB::table('test_groups')->where('code', $codigoGrupo)->pluck('id'))
                ->update(['report_comment_group' => $familia]);
        }

        // Las que no pertenecen a ninguna familia declarada son su propia
        // página. Se escribe el código en vez de dejarlo nulo para que el
        // editor de la prueba muestre con qué está agrupada, en lugar de un
        // campo vacío que no dice nada.
        DB::statement("
            UPDATE test_definitions SET report_comment_group = code
            WHERE report_comment_group IS NULL
        ");
    }

    /**
     * No se revierte: volver a poner la columna en nulo borraría también las
     * familias que el laboratorio haya editado a mano, y el motor trata el nulo
     * exactamente igual que el código propio (una página por prueba). Deshacer
     * destruiría datos sin cambiar comportamiento.
     */
    public function down(): void
    {
    }
};
