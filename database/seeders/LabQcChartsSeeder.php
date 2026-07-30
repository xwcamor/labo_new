<?php

namespace Database\Seeders;

use App\Models\Analyte;
use App\Models\QcChart;
use App\Models\TestDefinition;
use App\Models\TestField;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Las cartas de control que el sistema anterior llamaba "Tendencias".
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ SE REPRODUCE                                                         │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El único módulo con gráficos del sistema anterior era la carta de control del
 * PATRÓN interno, y la tenían ocho pruebas: acidez, factor de potencia a 25 y a
 * 100 °C, rigidez dieléctrica, tensión interfacial, contenido de agua, densidad
 * relativa y cromatografía —esta última con nueve gráficos, uno por gas—. Dieciséis
 * cartas en total. Ni una más: el enlace del menú se dibujaba con una condición
 * sobre el id de la prueba (`id < 7 || 8 < id < 11`), así que Color y Condición
 * Visual quedaban fuera por ser cualitativas, y todo lo de id mayor a 10 también.
 *
 * Agregarle pruebas por criterio propio sería inventar control de calidad que el
 * laboratorio no hace: una carta sin patrón que medir queda vacía, y una carta
 * vacía en pantalla se lee como un control que se dejó de correr.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LOS LÍMITES SE DERIVAN, NO SE INVENTAN                                   │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Los cinco límites (LAS, LAI, LC, LCS, LCI) vivían en la tabla
 * `patron_tendences` del sistema anterior, que NO está en su repositorio: sus
 * valores reales solo existen en la base de producción del laboratorio. Así que
 * las cartas nacen DERIVADAS —el centro y el desvío salen de los propios puntos
 * del patrón a medida que se cargan, que es el arranque correcto de una carta
 * nueva— y el laboratorio declara los suyos desde la pantalla cuando los tenga.
 *
 * Ahí está la mejora que importa: acá los límites declarados llevan fecha de
 * vigencia. En el sistema anterior se pisaban al cambiar de lote de patrón, y las
 * cartas históricas quedaban dibujadas contra el criterio de hoy — un punto que
 * en su momento estaba fuera de control aparecía adentro.
 *
 * Idempotente y respetuoso: una carta que ya existe no se toca. Si el
 * laboratorio le cargó sus límites, volver a sembrar no se los devuelve a
 * derivados.
 */
class LabQcChartsSeeder extends Seeder
{
    private const TENANT_ID = 1;

    public function run(): void
    {
        $ruta = database_path('seeders/data/qc_charts.json');

        if (! is_file($ruta)) {
            $this->command?->warn('No está qc_charts.json; se omiten las cartas de control.');

            return;
        }

        $datos = json_decode((string) file_get_contents($ruta), true) ?: [];

        $creadas = 0;
        $existentes = 0;
        $sinResolver = [];

        foreach ($datos['charts'] ?? [] as $fila) {
            $prueba = TestDefinition::where('code', $fila['test'])->first();

            if (! $prueba) {
                $sinResolver[] = $fila['test'];

                continue;
            }

            $columna = TestField::where('test_definition_id', $prueba->id)
                ->where('code', $fila['field'])
                ->first();

            if (! $columna) {
                $sinResolver[] = $fila['test'].'.'.$fila['field'];

                continue;
            }

            // Una carta POR COLUMNA de resultado: la cromatografía tiene nueve, y
            // la clave de unicidad no puede ser solo la prueba.
            $existente = QcChart::withoutGlobalScopes()
                ->where('tenant_id', self::TENANT_ID)
                ->where('test_field_id', $columna->id)
                ->whereNull('control_lot')
                ->first();

            if ($existente) {
                $existentes++;

                continue;
            }

            QcChart::withoutGlobalScopes()->create([
                'slug'               => Str::random(22),
                'tenant_id'          => self::TENANT_ID,
                'test_definition_id' => $prueba->id,
                'test_field_id'      => $columna->id,
                'analyte_id'         => Analyte::withoutGlobalScopes()
                    ->where('code', $fila['analyte'])->value('id'),
                // El rótulo sale del nombre de la prueba y de la columna: es el
                // que el laboratorio lee en la lista, y ponerlo a mano en el JSON
                // sería un texto más que se desincroniza al renombrar la prueba.
                'label'              => $prueba->name.' — '.$columna->label,
                // Sin lote todavía: el lote de patrón lo carga el laboratorio, y
                // es lo que define desde cuándo vale un juego de límites.
                'control_lot'        => null,
                'is_derived'         => true,
                'warn_sigma'         => 2,
                'action_sigma'       => 3,
                'effective_from'     => now()->startOfYear()->toDateString(),
                'is_active'          => true,
            ]);

            $creadas++;
        }

        $this->command?->info(sprintf(
            'Cartas de control: %d creadas, %d ya existían.',
            $creadas,
            $existentes,
        ));

        if ($sinResolver !== []) {
            $this->command?->warn(
                'No se pudieron resolver estas cartas (falta la prueba o la columna): '
                . implode(', ', $sinResolver)
            );
        }

        if ($creadas > 0) {
            $this->command?->warn(
                'Las cartas nacen DERIVADAS: sus límites se calculan de los puntos del patrón. '
                . 'Los valores declarados del sistema anterior (LAS/LAI/LC/LCS/LCI) vivían en su '
                . 'tabla `patron_tendences`, que no está en el repositorio — solo en su base de '
                . 'producción. El laboratorio los carga desde la pantalla de cada carta.'
            );
        }
    }
}
