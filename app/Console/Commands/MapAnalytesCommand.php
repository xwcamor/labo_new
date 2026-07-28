<?php

namespace App\Console\Commands;

use App\Models\Analyte;
use App\Models\TestDefinition;
use App\Models\TestField;
use Illuminate\Console\Command;

/**
 * Declara qué COLUMNA de qué PRUEBA alimenta qué PARÁMETRO.
 *
 * Es el dato que el sistema Rails viejo no tenía. Allá el informe tomaba "la
 * última columna por posición" y asumía que era el resultado, y por eso su
 * README avisaba en mayúsculas que la columna de resultado tenía que ser
 * siempre la última: insertar una columna en el medio rompía el informe en
 * silencio.
 *
 * El mapa vive en `database/seeders/data/analyte_map.json` y es DATO editable.
 * Lo mismo se puede hacer desde el editor de columnas, una por una; este
 * comando existe para el arranque y para volver a aplicarlo después de una
 * reimportación.
 *
 * Lo que el mapa no declara queda SIN declarar a propósito: adivinar a qué
 * parámetro alimenta una columna manda el dato equivocado al informe. El
 * comando lista al final lo que quedó pendiente, con el motivo.
 */
class MapAnalytesCommand extends Command
{
    protected $signature = 'lab:map-analytes
        {--dry-run : Informa lo que haría, sin escribir}
        {--force : Sobrescribe los enlaces que ya estén declarados}';

    protected $description = 'Enlaza las columnas de resultado de cada prueba con su parámetro medible';

    public function handle(): int
    {
        $path = database_path('seeders/data/analyte_map.json');

        if (! is_file($path)) {
            $this->error('No se encontró analyte_map.json.');

            return self::FAILURE;
        }

        $json = json_decode((string) file_get_contents($path), true) ?: [];
        $mapa = $json['map'] ?? [];
        $dry = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $analitos = Analyte::withoutGlobalScopes()->pluck('id', 'code');
        $pruebas = TestDefinition::pluck('id', 'code');

        $enlazados = 0;
        $yaEstaban = 0;
        $problemas = [];

        foreach ($mapa as $clave => $codigoAnalito) {
            [$codigoPrueba, $codigoColumna] = array_pad(explode('.', (string) $clave, 2), 2, null);

            if (! isset($pruebas[$codigoPrueba])) {
                $problemas[] = "La prueba '{$codigoPrueba}' no existe.";
                continue;
            }

            if (! isset($analitos[$codigoAnalito])) {
                $problemas[] = "El parámetro '{$codigoAnalito}' no existe. ¿Corrió LabAnalytesSeeder?";
                continue;
            }

            $columna = TestField::where('test_definition_id', $pruebas[$codigoPrueba])
                ->where('code', $codigoColumna)->first();

            if (! $columna) {
                $problemas[] = "La columna '{$codigoColumna}' no existe en la prueba '{$codigoPrueba}'.";
                continue;
            }

            if ($columna->output_analyte_id !== null && ! $force) {
                $yaEstaban++;
                continue;
            }

            if (! $dry) {
                $columna->update([
                    'output_analyte_id' => $analitos[$codigoAnalito],
                    // Declarar el parámetro implica que la columna ES un
                    // resultado. Se marcan las dos cosas juntas para que no
                    // queden en desacuerdo.
                    'role'              => TestField::ROLE_RESULT,
                    'report_visible'    => true,
                ]);
            }

            $enlazados++;
        }

        $this->newLine();
        $this->info(($dry ? 'SIMULACIÓN — ' : '') . 'Enlaces columna → parámetro');
        $this->line("  enlazados:      {$enlazados}");
        $this->line("  ya declarados:  {$yaEstaban}" . ($yaEstaban && ! $force ? '  (use --force para rehacerlos)' : ''));

        if ($problemas !== []) {
            $this->newLine();
            $this->warn('Problemas:');
            foreach ($problemas as $p) {
                $this->line("  · {$p}");
            }
        }

        $this->reportarPendientes($json['pendientes'] ?? []);

        return self::SUCCESS;
    }

    /**
     * Lo que queda sin declarar, con el motivo, para que el laboratorio lo
     * resuelva. No es una falla del comando: es una decisión que no le
     * corresponde tomar al programa.
     *
     * @param array<string,mixed> $pendientes
     */
    private function reportarPendientes(array $pendientes): void
    {
        $pendientes = array_filter(
            $pendientes,
            fn ($k) => ! str_starts_with((string) $k, '_'),
            ARRAY_FILTER_USE_KEY
        );

        if ($pendientes === []) {
            return;
        }

        $this->newLine();
        $this->warn('Pruebas que NO informan resultado hasta que el laboratorio decida:');
        foreach ($pendientes as $prueba => $motivo) {
            $this->newLine();
            $this->line("  <fg=yellow>{$prueba}</>");
            $this->line('    ' . wordwrap((string) $motivo, 90, "\n    "));
        }

        $this->newLine();
        $this->line('  Se resuelven desde el editor de columnas de cada prueba, o');
        $this->line('  agregándolas al mapa en database/seeders/data/analyte_map.json.');
    }
}
