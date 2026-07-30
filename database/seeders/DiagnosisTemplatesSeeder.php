<?php

namespace Database\Seeders;

use App\Models\DiagnosisTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Las plantillas de fábrica del análisis de resultados.
 *
 * Siembra `diagnosis_templates.json` como filas GLOBALES (`tenant_id` nulo), que
 * es la redacción estándar que publica el super.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ REFRESCA LA REDACCIÓN DE FÁBRICA; NO PISA LO QUE EL LABORATORIO EDITÓ    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Cada corrida vuelve a escribir las filas GLOBALES desde el archivo —así una
 * corrección de la redacción estándar llega con el despliegue— y **no toca
 * ninguna fila con `tenant_id`**: esas son la personalización del laboratorio, y
 * un seeder que las sobreescribiera le borraría su trabajo cada vez que alguien
 * despliega. Es el mismo criterio con el que el resto del proyecto separa
 * identidad de calibración.
 */
class DiagnosisTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = database_path('seeders/data/diagnosis_templates.json');

        if (! is_file($ruta)) {
            $this->command?->warn('No está diagnosis_templates.json: no hay plantillas de fábrica que sembrar.');

            return;
        }

        $datos = json_decode((string) file_get_contents($ruta), true) ?: [];
        $plantillas = $datos['templates'] ?? [];

        $orden = 0;

        foreach ($plantillas as $plantilla) {
            $familia = $plantilla['family'] ?? null;

            if ($familia === null) {
                continue;
            }

            $caso    = $plantilla['case'] ?? DiagnosisTemplate::CASE_ANY;
            $analito = $plantilla['analyte'] ?? null;

            // La identidad de una plantilla de fábrica es (familia + caso +
            // analito): es lo que el resolvedor usa para elegirla, así que es lo
            // que tiene que ser único para poder refrescarla sin duplicar.
            $fila = DiagnosisTemplate::withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->where('family', $familia)
                ->where('case', $caso)
                ->where(fn ($q) => $analito === null
                    ? $q->whereNull('analyte')
                    : $q->where('analyte', $analito))
                ->first();

            $valores = [
                'family'          => $familia,
                'case'            => $caso,
                'oil_types'       => $plantilla['oil_types'] ?? [],
                'equipment_types' => $plantilla['equipment_types'] ?? [],
                'analyte'         => $analito,
                'threshold'       => $plantilla['threshold'] ?? null,
                'bands'           => $plantilla['bands'] ?? null,
                'body'            => $plantilla['body'] ?? null,
                // La procedencia: de qué vista del sistema anterior salió la
                // redacción, y la nota del analista. Sirve para discutir un
                // cambio sabiendo qué decía el papel de antes.
                'origin'          => $plantilla['_origen'] ?? null,
                'notes'           => $plantilla['_nota'] ?? null,
                'sort_order'      => $orden += 10,
                'is_active'       => true,
            ];

            if ($fila) {
                $fila->update($valores);

                continue;
            }

            DiagnosisTemplate::create($valores + [
                'slug'      => Str::random(22),
                'tenant_id' => null,
            ]);
        }

        $this->command?->info('Plantillas de análisis de fábrica: ' . count($plantillas) . '.');
    }
}
