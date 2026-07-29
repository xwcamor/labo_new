<?php

namespace Database\Seeders;

use App\Models\Analyte;
use App\Models\Standard;
use App\Models\TestMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Las normas del laboratorio y los métodos con los que mide.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ SON TRES CLASES Y NO UNA                                         │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema anterior guardaba la norma del MÉTODO como una opción de texto de
 * la plantilla, y la norma de ACEPTACIÓN como un id clavado en el código del
 * informe. Al no distinguirlas, el PDF imprimía "ASTM D877" como método y al
 * lado un límite sacado de la tabla de D1816 — separaciones de electrodos
 * distintas, kV no comparables, y un informe que se contradice a sí mismo.
 *
 * Las de método salen de las opciones reales de las 29 pruebas del laboratorio.
 * Las de aceptación son las que citan los cuadros de límites.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LAS ERRATAS NO SE CORRIGEN EN LOS ENSAYOS                                │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Tres opciones del sistema anterior están mal escritas ("ASTM 3612" por ASTM
 * D3612, "ASTM 1275" por D1275, "IEC60247" sin espacio). El catálogo usa el
 * código correcto y guarda el texto literal para poder enlazar lo que el
 * analista ya eligió, pero NO reescribe las opciones: eso cambiaría lo que
 * dicen los ensayos ya cargados y es una decisión del laboratorio.
 *
 * Idempotente y respetuoso: refresca la identidad de la norma (nombre, emisor)
 * y no pisa lo que el laboratorio haya editado.
 */
class LabStandardsSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = database_path('seeders/data/standards.json');

        if (! is_file($ruta)) {
            $this->command?->warn('No se encontró standards.json. Nada que sembrar.');

            return;
        }

        $json = json_decode((string) file_get_contents($ruta), true) ?: [];

        $normas = $this->sembrarNormas($json['normas'] ?? []);
        $metodos = $this->sembrarMetodos($json['metodos']['lista'] ?? []);

        $this->command?->info("Normas: {$normas}. Métodos de ensayo: {$metodos}.");
    }

    /**
     * @param  array<int,array<string,mixed>> $normas
     */
    private function sembrarNormas(array $normas): int
    {
        $hechas = 0;

        foreach ($normas as $n) {
            if (! isset($n['code'])) {
                continue;
            }

            $existente = Standard::withoutGlobalScopes()
                ->where('code', $n['code'])
                ->whereNull('edition')
                ->first();

            $atributos = [
                'name'   => $n['name'] ?? null,
                'issuer' => $n['issuer'] ?? null,
                'kind'   => $n['kind'] ?? Standard::KIND_METHOD,
                // Los textos con los que la norma aparece en las opciones de la
                // plantilla. Es lo que permite enlazar lo que el analista
                // eligió con esta fila, erratas incluidas.
                'notes'  => isset($n['literal'])
                    ? 'Aparece en las plantillas como: ' . implode(' · ', (array) $n['literal'])
                    : null,
            ];

            if ($existente) {
                $existente->fill($atributos)->save();
                continue;
            }

            Standard::create($atributos + [
                'slug'      => Str::random(22),
                'code'      => $n['code'],
                'is_active' => true,
                // Global: el catálogo de normas es el mismo para todos los
                // workspaces. Uno puede agregar las suyas.
                'tenant_id' => config('lab.seed_tenant_id'),
            ]);

            $hechas++;
        }

        return $hechas;
    }

    /**
     * @param  array<int,array<string,mixed>> $metodos
     */
    private function sembrarMetodos(array $metodos): int
    {
        $analitos = Analyte::withoutGlobalScopes()->pluck('id', 'code');
        $normas = Standard::withoutGlobalScopes()->pluck('id', 'code');

        $hechos = 0;
        $problemas = [];

        foreach ($metodos as $m) {
            if (! isset($m['code'], $m['analyte'])) {
                continue;
            }

            if (! isset($analitos[$m['analyte']])) {
                $problemas[] = "El parámetro '{$m['analyte']}' no existe (método {$m['code']}).";
                continue;
            }

            $normaId = isset($m['standard']) ? ($normas[$m['standard']] ?? null) : null;

            if (isset($m['standard']) && $normaId === null) {
                $problemas[] = "La norma '{$m['standard']}' no existe (método {$m['code']}).";
                continue;
            }

            $existente = TestMethod::withoutGlobalScopes()->where('code', $m['code'])->first();

            $atributos = [
                'analyte_id'  => $analitos[$m['analyte']],
                'standard_id' => $normaId,
                'label'       => $m['label'] ?? $m['code'],
                'conditions'  => $m['conditions'] ?? null,
            ];

            if ($existente) {
                $existente->fill($atributos)->save();
                continue;
            }

            TestMethod::create($atributos + [
                'slug'      => Str::random(22),
                'code'      => $m['code'],
                'is_active' => true,
                'tenant_id' => config('lab.seed_tenant_id'),
            ]);

            $hechos++;
        }

        foreach ($problemas as $p) {
            $this->command?->warn("  · {$p}");
        }

        return $hechos;
    }
}
