<?php

namespace Database\Seeders;

use App\Models\TestField;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Enlaza cada columna de resultado con el parámetro que alimenta.
 *
 * Es el dato que el sistema Rails viejo no declaraba: allá el informe tomaba
 * "la última columna por posición" y asumía que era el resultado, y por eso su
 * pantalla de ayuda avisaba en mayúsculas que la columna de resultado tenía que
 * ser SIEMPRE la última. Insertar una columna en el medio rompía el informe sin
 * que nada avisara.
 *
 * Sin este enlace la cadena queda cortada: el analista carga la hoja, se guarda
 * en `worksheet_values`, y al validar no se materializa ningún `result` — con lo
 * cual el informe, la tendencia y el tablero no tienen de dónde leer. Por eso va
 * en el seed y no como un paso que hay que acordarse de correr.
 *
 * El mapa vive en `database/seeders/data/analyte_map.json` y es dato editable;
 * lo mismo se puede hacer columna por columna desde el editor de pruebas.
 *
 * Depende de LabAnalytesSeeder (los parámetros) y de LabTestTemplatesSeeder
 * (las pruebas): enlazar necesita que existan las dos puntas.
 */
class LabAnalyteMapSeeder extends Seeder
{
    public function run(): void
    {
        $codigo = Artisan::call('lab:map-analytes');

        if ($codigo !== 0) {
            $this->command?->error('El enlace columna → parámetro falló:');
            $this->command?->line(Artisan::output());

            return;
        }

        $enlazadas = TestField::whereNotNull('output_analyte_id')->count();
        $this->command?->info("Columnas enlazadas a un parámetro: {$enlazadas}.");
    }
}
