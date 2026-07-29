<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los tres ensayos de azufre comparten una sola hoja del informe.
 *
 * El papel acreditado imprime "AZUFRE CORROSIVO" con las tres tablas —ASTM
 * 1275B, IEC 62535 a 48 horas y a 72 horas— en la misma página. El sistema
 * nuevo les daba una hoja a cada uno: tres hojas de una fila, cada una
 * repitiendo la cabecera entera con los datos del cliente y del equipo.
 *
 * No se puede declarar por GRUPO como se hizo con los fisicoquímicos: los tres
 * azufres viven en "Otros", junto a PCB, furanos, metales y todo lo demás, y
 * mandar los quince a la misma página no es lo que hace el papel. Por eso
 * `config('lab.report_families_by_test')` declara la excepción por prueba.
 *
 * Como el resto de las familias: se escribe SOLO donde el laboratorio no puso
 * la suya. Si alguien ya reagrupó los azufres a mano desde la ficha de la
 * prueba, esa decisión no se pisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('lab.report_families_by_test', []) as $codigoPrueba => $familia) {
            DB::table('test_definitions')
                ->where('code', $codigoPrueba)
                // El valor inicial que puso la migración anterior es el propio
                // código de la prueba: eso NO es una decisión del laboratorio,
                // es el default de "cada prueba su hoja", y sí se reemplaza.
                ->where(function ($q) use ($codigoPrueba) {
                    $q->whereNull('report_comment_group')
                        ->orWhere('report_comment_group', $codigoPrueba);
                })
                ->update(['report_comment_group' => $familia]);
        }
    }

    /**
     * No se revierte: devolver cada azufre a su propia hoja significaría pisar
     * lo que el laboratorio haya decidido después, y el motor trata las dos
     * situaciones sin romperse. Deshacer destruiría datos sin cambiar nada.
     */
    public function down(): void
    {
    }
};
