<?php

namespace Database\Seeders;

use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\TestFieldOption;
use Illuminate\Database\Seeder;

/**
 * Qué mide cada columna: tipo, unidad, decimales y rango.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ SE ESTÁ CORRIGIENDO                                                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema Rails anterior guardaba todos los valores en una sola columna de
 * texto (`lab_sub_details.name varchar(255)`): números, fechas y hasta el id de
 * la opción elegida en un desplegable. Con eso, declarar una columna como
 * "texto" no costaba nada, y así quedaron declaradas casi todas.
 *
 * El importador copió ese criterio tal cual, y el resultado fue que el número
 * ácido, las dos lecturas de Karl Fischer, los PCB, los metales, las partículas
 * y la viscosidad llegaron como TEXTO siendo números. Sobre una columna de texto
 * no se puede comparar contra un límite, ni promediar, ni graficar una
 * tendencia, ni impedir que alguien escriba "aprox 45".
 *
 * Este sembrador decide por lo que la columna MIDE, no por lo que el sistema
 * anterior declaraba. Cada corrección lleva su evidencia en
 * `database/seeders/data/test_field_types.json`.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ TRES COSAS QUE HACE, Y POR QUÉ CADA UNA                                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 *   1. TIPO, unidad y decimales — para que el valor caiga en su columna tipada
 *      y el informe sepa cómo mostrarlo.
 *   2. RANGO, incluido el caso del cero — hay propiedades donde el 0 no es una
 *      medición sino el "no medido" del sistema anterior, que obligaba a llenar
 *      la celda. Una rigidez de 0 kV no existe.
 *   3. LISTAS CERRADAS — el resultado de los tres ensayos de Azufre y la
 *      clasificación de la lámina de cobre son clasificaciones, no texto libre.
 *      Con texto libre conviven "Corrosivo", "corrosivo" y "CORROSIVO" en la
 *      misma columna y ningún filtro las agrupa.
 *
 * Es idempotente y NO pisa lo que el laboratorio haya cambiado desde la
 * pantalla: si el tipo ya es el correcto, no lo toca; si le pusieron otro a
 * propósito, tampoco. Lo que corrige es el arrastre del sistema anterior.
 */
class LabTestFieldTypesSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = database_path('seeders/data/test_field_types.json');

        if (! is_file($ruta)) {
            $this->command?->warn('No se encontró test_field_types.json. Nada que corregir.');

            return;
        }

        $json = json_decode((string) file_get_contents($ruta), true) ?: [];

        $corregidas = $this->corregirTipos($json['correcciones'] ?? []);
        $ceros = $this->rechazarElCero($json['el_cero_no_es_una_medicion']['columnas'] ?? []);
        $listas = $this->cerrarVocabularios($json['opciones'] ?? []);

        $this->command?->info(
            "Columnas: {$corregidas} retipadas, {$ceros} que ya no admiten cero, {$listas} pasadas a lista cerrada."
        );

        $this->avisarPendientes($json['pendientes'] ?? []);
    }

    /**
     * @param  array<string,mixed> $correcciones
     */
    private function corregirTipos(array $correcciones): int
    {
        $hechas = 0;

        foreach ($correcciones as $clave => $spec) {
            if (str_starts_with((string) $clave, '_') || ! is_array($spec)) {
                continue;
            }

            $columna = $this->columna((string) $clave);

            if (! $columna) {
                $this->command?->warn("  · No existe la columna '{$clave}'.");
                continue;
            }

            $atributos = [];

            // El tipo solo se corrige si sigue siendo el que arrastró el
            // importador. Si el laboratorio ya lo cambió, manda el laboratorio.
            if (isset($spec['type']) && $columna->type !== $spec['type']) {
                $atributos['type'] = $spec['type'];
            }

            foreach (['unit' => 'unit', 'decimals' => 'decimals', 'label' => 'label'] as $enJson => $enTabla) {
                if (isset($spec[$enJson]) && blank($columna->{$enTabla})) {
                    $atributos[$enTabla] = $spec[$enJson];
                }
            }

            // La etiqueta sí se pisa cuando el JSON la corrige: son erratas del
            // sistema anterior ("Znic" por Zinc, "Silicio (Sn)" por Silicio (Si),
            // que además es el símbolo del estaño de la fila de arriba).
            if (isset($spec['label'])) {
                $atributos['label'] = $spec['label'];
            }

            if (array_key_exists('min', $spec)) {
                $atributos['min_value'] = $spec['min'];
                $atributos['min_exclusive'] = (bool) ($spec['min_exclusive'] ?? false);
            }

            if (array_key_exists('max', $spec)) {
                $atributos['max_value'] = $spec['max'];
            }

            // OBLIGATORIA o no. El sistema anterior no tenía la noción —todas
            // sus columnas se podían dejar vacías, y de ahí salieron los ceros
            // y los huecos del histórico—, así que el importador las trajo
            // todas opcionales. Lo que decide cuáles son obligatorias es el
            // laboratorio, y se anota acá.
            //
            // Marcar una columna obligatoria NO impide guardar la fila: la hoja
            // se guarda igual y simplemente no PUBLICA hasta que esté completa
            // (ver `WorksheetService::publishIfComplete`). El analista mide la
            // rigidez a la mañana y termina a la tarde.
            if (array_key_exists('required', $spec)) {
                $atributos['is_required'] = (bool) $spec['required'];
            }

            if ($atributos === []) {
                continue;
            }

            $columna->update($atributos);
            $hechas++;
        }

        return $hechas;
    }

    /**
     * Las columnas donde el cero es el "no medido" del sistema anterior.
     *
     * @param  array<int,string> $columnas
     */
    private function rechazarElCero(array $columnas): int
    {
        $hechas = 0;

        foreach ($columnas as $clave) {
            $columna = $this->columna((string) $clave);

            if (! $columna) {
                $this->command?->warn("  · No existe la columna '{$clave}'.");
                continue;
            }

            if ($columna->min_value !== null && (bool) $columna->min_exclusive) {
                continue;   // ya está
            }

            $columna->update(['min_value' => 0, 'min_exclusive' => true]);
            $hechas++;
        }

        return $hechas;
    }

    /**
     * Pasa una columna de texto libre a lista cerrada, con su vocabulario.
     *
     * @param  array<string,mixed> $listas
     */
    private function cerrarVocabularios(array $listas): int
    {
        $hechas = 0;

        foreach ($listas as $clave => $spec) {
            if (str_starts_with((string) $clave, '_') || ! is_array($spec)) {
                continue;
            }

            $columna = $this->columna((string) $clave);

            if (! $columna) {
                $this->command?->warn("  · No existe la columna '{$clave}'.");
                continue;
            }

            $opciones = $spec['opciones'] ?? [];

            if ($opciones === []) {
                continue;
            }

            $columna->update(['type' => 'select']);

            foreach (array_values($opciones) as $i => $valor) {
                // Sin `legacy_id`: estas opciones no vienen del volcado, se
                // declaran acá. Se anclan al par (columna, valor) para que
                // volver a sembrar no las duplique.
                TestFieldOption::updateOrCreate(
                    ['test_field_id' => $columna->id, 'value' => $valor],
                    ['sort_order' => $i + 1, 'is_hidden' => false],
                );
            }

            $hechas++;
        }

        return $hechas;
    }

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

        $this->command?->line(
            '  Esperan decisión del laboratorio (ver test_field_types.json): '
            . implode(', ', array_keys($lista))
        );
    }
}
