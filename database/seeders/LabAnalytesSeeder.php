<?php

namespace Database\Seeders;

use App\Models\Analyte;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Los parámetros que mide el laboratorio.
 *
 * La lista vive en `database/seeders/data/analytes.json` y sale de las columnas
 * de resultado de las 29 pruebas reales importadas del sistema anterior. No se
 * inventó ninguna.
 *
 * Es idempotente y NO pisa lo que el laboratorio haya editado: refresca la
 * identidad del parámetro (nombre, unidad, decimales, dirección) y respeta el
 * resto. Mismo criterio que el resto de los sembradores de catálogo.
 */
class LabAnalytesSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/analytes.json');

        if (! is_file($path)) {
            $this->command?->warn('No se encontró analytes.json. Nada que sembrar.');

            return;
        }

        $data = json_decode((string) file_get_contents($path), true) ?: [];

        $orden = 0;
        $creados = 0;
        $actualizados = 0;

        foreach ($data as $grupo => $items) {
            // Las claves que empiezan con guion bajo son documentación.
            if (str_starts_with((string) $grupo, '_') || ! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $existente = Analyte::withoutGlobalScopes()
                    ->where('code', $item['code'])->first();

                $atributos = [
                    'name'       => $item['name'],
                    'unit'       => $item['unit'] ?: null,
                    'decimals'   => $item['decimals'] ?? 2,
                    'group'      => $grupo,
                    'direction'  => $item['direction'] ?? 'lower_better',
                    'sort_order' => ++$orden,
                ];

                if ($existente) {
                    $existente->fill($atributos)->save();
                    $actualizados++;
                    continue;
                }

                Analyte::create($atributos + [
                    'slug'      => Str::random(22),
                    'code'      => $item['code'],
                    'is_active' => true,
                    // Global: el catálogo de parámetros es el mismo para todos
                    // los workspaces. Un workspace puede agregar los suyos.
                    'tenant_id' => config('lab.seed_tenant_id'),
                ]);
                $creados++;
            }
        }

        $this->command?->info("Parámetros: {$creados} creados, {$actualizados} actualizados.");
    }
}
