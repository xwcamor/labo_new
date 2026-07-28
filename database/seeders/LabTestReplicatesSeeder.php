<?php

namespace Database\Seeders;

use App\Models\TestDefinition;
use App\Models\TestField;
use Illuminate\Database\Seeder;

/**
 * Cuántas lecturas admite cada columna.
 *
 * Hay pruebas donde una columna no lleva un número sino varios: en Grado de
 * Polimerización el analista mide la masa dos veces y los tiempos de flujo del
 * viscosímetro cuatro, y el resultado sale del promedio. El formulario del
 * sistema anterior dibujaba esas 2 o 4 casillas según un rango de índices
 * escrito a mano en el HTML, y guardaba las lecturas concatenadas en un solo
 * texto separadas por una barra ("12.5/12.7").
 *
 * Acá cada lectura es su propia fila tipada (`worksheet_values.replicate_no` +
 * `value_num`) y lo único que hace falta es decir cuántas admite la columna
 * (`test_fields.replicates`). Este seeder pone los valores de fábrica; el
 * supervisor los cambia desde el editor de columnas.
 *
 * NO pisa lo que el laboratorio haya ajustado: solo escribe cuando la columna
 * sigue en 1, que es el valor con el que nació la importación (el sistema
 * anterior no tenía este dato en la base, así que no había nada que importar).
 */
class LabTestReplicatesSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = database_path('seeders/data/test_replicates.json');

        if (! is_file($ruta)) {
            return;
        }

        $datos = json_decode((string) file_get_contents($ruta), true) ?: [];
        $tocadas = 0;

        foreach ($datos as $codigoPrueba => $columnas) {
            if ($codigoPrueba === '_doc' || ! is_array($columnas)) {
                continue;
            }

            $prueba = TestDefinition::where('code', $codigoPrueba)->first();

            if (! $prueba) {
                $this->command?->warn("  · Prueba desconocida: {$codigoPrueba}");
                continue;
            }

            foreach ($columnas as $codigoColumna => $lecturas) {
                $tocadas += TestField::where('test_definition_id', $prueba->id)
                    ->where('code', $codigoColumna)
                    ->where('replicates', 1)
                    ->update(['replicates' => (int) $lecturas]);
            }
        }

        $this->command?->info("  Lecturas por columna: {$tocadas} columnas configuradas.");
    }
}
