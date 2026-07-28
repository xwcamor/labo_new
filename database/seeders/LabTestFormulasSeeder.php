<?php

namespace Database\Seeders;

use App\Models\TestDefinition;
use App\Models\TestField;
use App\Services\Lab\FormulaValidator;
use Illuminate\Database\Seeder;

/**
 * Las fórmulas de las pruebas, traídas del sistema viejo como DATO.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ SE ESTÁ REEMPLAZANDO                                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Cada prueba del sistema Rails viejo guardaba en `blur_calculation` un bloque
 * de JavaScript que se inyectaba en la página y direccionaba las celdas por
 * POSICIÓN (`document.getElementById('col9')`). El propio sistema avisaba en su
 * pantalla de ayuda que reordenar una columna obligaba a reescribir la fórmula.
 *
 * Acá la fórmula es una expresión que nombra las columnas por su código, se
 * analiza con un parser propio (no hay `eval` en ninguna parte) y la evalúa el
 * servidor. Reordenar el cuadro no cambia nada, y el número que queda guardado
 * es el que calculó el servidor, no el que mandó el navegador.
 *
 * La traducción de cada fórmula, con el JavaScript original al lado para poder
 * cotejarla, vive en `database/seeders/data/test_formulas.json`.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ VALIDA ANTES DE GUARDAR                                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Una fórmula que nombra una columna que no existe no falla al sembrarse: falla
 * en la bancada, el día que el analista carga la muestra, y ahí ya es tarde. El
 * sembrador la analiza contra las columnas reales de esa prueba y, si no cierra,
 * NO la guarda y lo dice. Es el mismo criterio del editor de columnas.
 *
 * Idempotente y respetuoso: si el laboratorio editó una fórmula desde la
 * pantalla, no se la pisa. Para forzar la de fábrica hay que borrar la suya.
 */
class LabTestFormulasSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = database_path('seeders/data/test_formulas.json');

        if (! is_file($ruta)) {
            $this->command?->warn('No se encontró test_formulas.json. Nada que sembrar.');

            return;
        }

        $json = json_decode((string) file_get_contents($ruta), true) ?: [];

        // El orden importa: si una columna que la fórmula usa está declarada
        // como texto, el motor no la puede leer como número. Se corrigen los
        // tipos ANTES de validar las expresiones.
        $this->corregirEntradas($json['entradas_numericas'] ?? []);
        $this->sembrarFormulas($json['formulas'] ?? []);
        $this->avisarPendientes($json['pendientes'] ?? []);
    }

    /**
     * Columnas que el viejo declaraba de texto y que una fórmula usa como
     * número. Allá daba igual —todo caía en la misma columna varchar—; acá el
     * tipo decide en qué columna se guarda el dato.
     *
     * @param array<string,mixed> $entradas
     */
    private function corregirEntradas(array $entradas): void
    {
        $corregidas = 0;

        foreach ($entradas as $clave => $tipo) {
            if (str_starts_with((string) $clave, '_') || ! is_string($tipo)) {
                continue;
            }

            $columna = $this->columna((string) $clave);

            if (! $columna || $columna->type === $tipo) {
                continue;
            }

            $columna->update(['type' => $tipo]);
            $corregidas++;
        }

        if ($corregidas > 0) {
            $this->command?->line("  {$corregidas} columnas de texto pasadas a número (son entradas de un cálculo).");
        }
    }

    /**
     * @param array<string,mixed> $formulas
     */
    private function sembrarFormulas(array $formulas): void
    {
        $sembradas = 0;
        $respetadas = 0;
        $problemas = [];

        foreach ($formulas as $clave => $spec) {
            if (str_starts_with((string) $clave, '_') || ! is_array($spec)) {
                continue;
            }

            $columna = $this->columna((string) $clave);

            if (! $columna) {
                $problemas[] = "No existe la columna '{$clave}'.";
                continue;
            }

            // Lo que el laboratorio escribió gana. Un sembrador que pisa la
            // calibración del usuario es un sembrador que no se puede correr
            // dos veces.
            if (filled($columna->formula) && $columna->formula !== $spec['formula']) {
                $respetadas++;
                continue;
            }

            $error = $this->porQueNoCompila($columna, (string) $spec['formula']);

            if ($error !== null) {
                $problemas[] = "'{$clave}': {$error}";
                continue;
            }

            $columna->update([
                'formula'  => $spec['formula'],
                'decimals' => $spec['decimales'] ?? $columna->decimals,
                // Una columna con fórmula es calculada, y una columna calculada
                // no se escribe a mano: el tipo la vuelve de solo lectura en la
                // grilla. En el viejo esto era una bandera aparte que había que
                // acordarse de marcar, y su propia ayuda lo pedía por escrito
                // ("se recomienda bloquear la edición en las columnas que sean
                // resultados de cálculos").
                'type'      => 'computed',
                'is_locked' => true,
            ]);

            $sembradas++;
        }

        $this->command?->info("Fórmulas: {$sembradas} sembradas."
            . ($respetadas ? "  {$respetadas} ya editadas por el laboratorio, sin tocar." : ''));

        foreach ($problemas as $p) {
            $this->command?->warn("  · {$p}");
        }
    }

    /**
     * Devuelve el motivo por el que la fórmula no sirve, o null si compila.
     *
     * Se valida contra las columnas REALES de esa prueba, que es lo único que
     * detecta el error que de verdad ocurre: nombrar una columna que no existe
     * o que se llama distinto.
     */
    private function porQueNoCompila(TestField $columna, string $formula): ?string
    {
        $disponibles = TestField::where('test_definition_id', $columna->test_definition_id)
            ->where('id', '!=', $columna->id)
            ->pluck('code')
            ->all();

        $resultado = (new FormulaValidator())->validate($formula, $disponibles);

        return $resultado['ok'] ? null : implode(' ', $resultado['errors']);
    }

    /**
     * `codigo_de_prueba.codigo_de_columna` → la columna, o null.
     */
    private function columna(string $clave): ?TestField
    {
        [$prueba, $campo] = array_pad(explode('.', $clave, 2), 2, null);

        $pruebaId = TestDefinition::where('code', $prueba)->value('id');

        if (! $pruebaId || ! $campo) {
            return null;
        }

        return TestField::where('test_definition_id', $pruebaId)
            ->where('code', $campo)
            ->first();
    }

    /**
     * @param array<string,mixed> $pendientes
     */
    private function avisarPendientes(array $pendientes): void
    {
        $lista = array_diff_key($pendientes, ['_doc' => null]);

        if ($lista === []) {
            return;
        }

        $this->command?->line('  Sin portar (ver test_formulas.json): ' . implode(', ', array_keys($lista)));
    }
}
