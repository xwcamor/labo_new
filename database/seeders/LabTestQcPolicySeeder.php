<?php

namespace Database\Seeders;

use App\Models\TestDefinition;
use Illuminate\Database\Seeder;

/**
 * Qué control de calidad exige cada prueba: patrón y duplicado.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL DEFECTO QUE ESTO CIERRA                                               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema nuevo ya tenía las columnas (`requires_control`,
 * `requires_duplicate`) y el mecanismo que las hace valer
 * (`Worksheet::missingPrerequisites()`, que impide publicar la hoja sin ellas).
 * Lo que no tenía era el DATO: las 29 pruebas estaban en `false`, así que el
 * mecanismo no se disparaba nunca y cualquier hoja se publicaba sin haber
 * corrido un patrón ni un duplicado.
 *
 * En el sistema anterior la regla existía —mínimo 1 patrón y 1 duplicado por
 * corrida— pero vivía dentro del HTML de tres formularios distintos, sin
 * validación en el modelo ni en el controlador, y terminó DESACTIVADA: el botón
 * que bloqueaba quedó envuelto en un `display:none` con el comentario "SE HA
 * COMENTADO PARA VALIDAR MUESTRAS". De la regla solo sobrevivían las alertas.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ CALIBRACIÓN CONTRA IDENTIDAD                                             │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Mismo criterio que `FiquiRulesSeeder` en el proyecto hermano: este seeder
 * refresca el valor de FÁBRICA y NUNCA pisa la decisión del laboratorio. Una
 * prueba que el supervisor marcó exenta se queda exenta aunque se vuelva a
 * sembrar — si no, cada `db:seed` le devolvería una exigencia que él quitó a
 * propósito, y lo descubriría cuando una hoja se niegue a publicarse.
 *
 * Para distinguir "nunca se tocó" de "el supervisor lo puso en false" se usa
 * `qc_policy_set_at`: se escribe la primera vez y a partir de ahí la fila es
 * suya.
 */
class LabTestQcPolicySeeder extends Seeder
{
    public function run(): void
    {
        $ruta = database_path('seeders/data/test_qc_policy.json');

        if (! is_file($ruta)) {
            $this->command?->warn('No está test_qc_policy.json; se omite la política de control de calidad.');

            return;
        }

        $datos = json_decode((string) file_get_contents($ruta), true) ?: [];
        $porOmision = $datos['defaults'] ?? [];
        $porPrueba  = $datos['tests'] ?? [];

        $tocadas = 0;
        $respetadas = 0;

        foreach (TestDefinition::withTrashed()->get() as $prueba) {
            // Ya la calibró el laboratorio: no se toca.
            if ($prueba->qc_policy_set_at !== null) {
                $respetadas++;

                continue;
            }

            $politica = array_merge($porOmision, $porPrueba[$prueba->code] ?? []);

            $prueba->forceFill([
                'requires_control'   => (bool) ($politica['control'] ?? false),
                'requires_duplicate' => (bool) ($politica['duplicate'] ?? false),
                // `is_grouped` es la EXENCIÓN, el nombre que traía del sistema
                // anterior. Se conserva porque es la columna que ya existe y la
                // que su formulario rotulaba "No usa Duplicados / No usa Patrón
                // Control".
                'is_grouped'         => (bool) ($politica['exempt'] ?? false),
                'qc_policy_set_at'   => now(),
            ])->saveQuietly();

            $tocadas++;
        }

        $this->command?->info(sprintf(
            'Control de calidad por prueba: %d con patrón y duplicado exigidos, %d ya calibradas por el laboratorio.',
            $tocadas,
            $respetadas,
        ));

        if ($porPrueba === []) {
            $this->command?->warn(
                'Ninguna prueba viene EXENTA de patrón y duplicado. En el sistema anterior la '
                . 'exención existía (columna `is_grouped`, "No usa Duplicados / No usa Patrón '
                . 'Control") pero sus valores reales solo están en su base de producción: no hay '
                . 'volcado en el repositorio. El laboratorio las marca desde la ficha de cada prueba.'
            );
        }
    }
}
