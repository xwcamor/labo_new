<?php

namespace Database\Seeders;

use App\Models\TestDefinition;
use Illuminate\Database\Seeder;

/**
 * Los límites de detección de los métodos, sacados del HTML del informe viejo.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ DE DÓNDE SALEN                                                           │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El informe acreditado del sistema Ruby no imprimía el valor medido cuando
 * caía por debajo del límite de detección: imprimía el límite con un "menor
 * que". Un hidrógeno de 0.4 ppm salía "< 1".
 *
 * Esos cortes no estaban en ninguna tabla: estaban escritos en la plantilla,
 * repetidos hasta tres veces por gas —una vez por cada rama del `if` que
 * decidía el color de la celda—, en tres archivos distintos. Cambiar el límite
 * de detección de un método significaba buscar 27 apariciones.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ES UN SEMBRADOR Y NO UNA MIGRACIÓN                               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El límite de detección es del MÉTODO y cambia cuando se cambia el equipo o se
 * revalida. Es un dato del laboratorio, editable desde la ficha de la columna;
 * esto es solo el valor de fábrica.
 *
 * Por eso escribe SOLO donde está vacío: si el laboratorio ya cargó el suyo,
 * volver a sembrar no se lo pisa. Mismo criterio que las familias del informe y
 * que la calibración de los parámetros.
 */
class LabDetectionLimitsSeeder extends Seeder
{
    public function run(): void
    {
        $archivo = database_path('seeders/data/detection_limits.json');

        if (! is_file($archivo)) {
            $this->command?->warn('detection_limits.json no está: no se sembró ningún límite de detección.');
            return;
        }

        $datos = json_decode((string) file_get_contents($archivo), true) ?: [];

        $escritos = 0;
        $sinPrueba = [];
        $sinColumna = [];

        foreach ($datos as $codigoPrueba => $columnas) {
            // Las claves que empiezan con guion bajo son documentación del
            // propio archivo, no datos.
            if (str_starts_with((string) $codigoPrueba, '_') || ! is_array($columnas)) {
                continue;
            }

            $prueba = TestDefinition::where('code', $codigoPrueba)->first();

            if ($prueba === null) {
                $sinPrueba[] = $codigoPrueba;
                continue;
            }

            foreach ($columnas as $codigoColumna => $limite) {
                $columna = $prueba->fields()->where('code', $codigoColumna)->first();

                if ($columna === null) {
                    $sinColumna[] = "{$codigoPrueba}.{$codigoColumna}";
                    continue;
                }

                if ($columna->detection_limit !== null) {
                    continue;   // ya tiene el suyo: es del laboratorio, no se pisa
                }

                $columna->forceFill(['detection_limit' => $limite])->save();
                $escritos++;
            }
        }

        // Se avisa lo que NO se pudo sembrar: un límite de detección que no se
        // escribió significa que el informe va a publicar un número donde el
        // papel acreditado decía "< algo", y eso no puede pasar en silencio.
        foreach ($sinPrueba as $codigo) {
            $this->command?->warn("Límite de detección: no existe la prueba '{$codigo}'.");
        }

        foreach ($sinColumna as $ref) {
            $this->command?->warn("Límite de detección: no existe la columna '{$ref}'.");
        }

        $this->command?->info("Límites de detección sembrados: {$escritos}.");
    }
}
