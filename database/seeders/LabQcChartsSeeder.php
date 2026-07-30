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
 * │ LOS LÍMITES SON LOS DEL LABORATORIO, DECLARADOS                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Acá decía que los cinco límites (LAS, LAI, LC, LCS, LCI) «no están en el
 * repositorio» y por eso las cartas nacían DERIVADAS, con el centro y el desvío
 * calculados de los propios puntos. Era falso: están en
 * `docs/migracion/esquema/catalogos-definiciones.sql`, tabla `patron_tendences`,
 * 27 filas, de las cuales 16 traen números — justo las 16 cartas de acá.
 *
 * La diferencia no es cosmética. Una carta derivada mueve su propio centro con
 * cada punto que se carga, así que una corrida que históricamente estaba fuera
 * de control puede terminar cayendo dentro. El laboratorio calibró esos
 * límites; el sistema no los vuelve a inventar. Así funcionaba el anterior: los
 * cinco números se tecleaban y el gráfico solo los dibujaba.
 *
 * DOS TRAMPAS AL SEMBRARLOS, las dos resueltas en `qc_charts.json`:
 *
 *   · Las siglas NO son fiables. En quince filas el de CONTROL es el de afuera
 *     (±3σ) y el de ADVERTENCIA el de adentro (±2σ), pero en Densidad Relativa
 *     vienen al revés. Por eso el mapeo es POR DISTANCIA A LA LÍNEA CENTRAL y no
 *     por el nombre de la columna: mapear por nombre dejaría esa carta con los
 *     límites invertidos, dando por buena una corrida fuera de control.
 *   · Condición Visual trae el texto `PASA` en los cinco campos: es una carta
 *     cualitativa y no tiene límites numéricos. Queda sin sembrar.
 *
 * Lo que sí es mejora sobre el anterior: los límites llevan fecha de vigencia.
 * Allá se pisaban al cambiar de lote de patrón y las cartas históricas quedaban
 * dibujadas contra el criterio de hoy.
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
                // ┌──────────────────────────────────────────────────────────┐
                // │ LÍMITES DECLARADOS, COMO EN EL SISTEMA ANTERIOR          │
                // └──────────────────────────────────────────────────────────┘
                // Allá los cinco números los TECLEABA el laboratorio y el
                // gráfico solo los dibujaba: no calculaba nada. Acá se
                // sembraban DERIVADOS —el sistema los deducía de los puntos que
                // se fueran cargando— porque se creyó que esos valores no
                // estaban en ninguna parte. Sí están: son las 27 filas de
                // `patron_tendences` del volcado, y 16 traen números.
                //
                // La diferencia no es cosmética: una carta derivada mueve su
                // propio centro con cada punto nuevo, así que una corrida que
                // históricamente estaba fuera de control puede caer dentro.
                // El laboratorio calibró esos límites; el sistema no los
                // vuelve a inventar.
                'is_derived'         => ($fila['center'] ?? null) === null,
                'lcl'                => $fila['lcl'],
                'lwl'                => $fila['lwl'],
                'center'             => $fila['center'],
                'uwl'                => $fila['uwl'],
                'ucl'                => $fila['ucl'],
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
            $declaradas = QcChart::withoutGlobalScopes()
                ->where('tenant_id', self::TENANT_ID)
                ->whereNotNull('center')
                ->count();

            $this->command?->info(sprintf(
                '%d con los límites declarados del laboratorio (patron_tendences del volcado); '
                . 'el resto quedan derivadas hasta que se carguen los suyos.',
                $declaradas,
            ));
        }
    }
}
