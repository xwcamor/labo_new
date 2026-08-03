<?php

namespace Database\Seeders;

use App\Models\ReportCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Las cuatro listas del formulario del informe, tal como las tenía el
 * laboratorio.
 *
 * Salen del volcado de su base (`docs/migracion/esquema/catalogos-definiciones.sql`):
 * `rem_report_reasons`, `transformer_points`, `transformer_oil_units` y
 * `transformer_oil_marks`. En el sistema anterior eran cuatro tablas sin
 * pantalla; acá se cargan una vez y el laboratorio las administra.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL CENTINELA «-» NO SE SIEMBRA                                           │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Tres de las cuatro listas traen una fila cuyo nombre es literalmente `-`. Era
 * la forma que tenía el sistema anterior de decir «ninguno» dentro de un
 * desplegable obligatorio. Acá la ausencia de valor es un campo vacío, que es lo
 * que significa; sembrarla dejaría una opción que el usuario puede elegir y que
 * después se imprime como un guion en el informe, indistinguible de un dato que
 * nadie cargó.
 *
 * Idempotente: se emparejan por `legacy_id` dentro de su lista, así que volver a
 * correrlo no duplica ni pisa lo que el laboratorio haya renombrado.
 */
class ReportCatalogsSeeder extends Seeder
{
    private const TENANT_ID = 1;

    public function run(): void
    {
        $ruta = database_path('seeders/data/report_catalogs.json');

        if (! is_file($ruta)) {
            $this->command?->warn('No está report_catalogs.json; se omiten los catálogos del informe.');

            return;
        }

        $datos = json_decode((string) file_get_contents($ruta), true) ?: [];

        $creadas = 0;
        $omitidas = 0;

        foreach (ReportCatalog::KINDS as $kind) {
            $orden = 0;

            foreach ($datos[$kind] ?? [] as $fila) {
                $nombre = trim((string) ($fila['name'] ?? ''));

                // El centinela de «ninguno» del sistema anterior. Ver arriba.
                if ($nombre === '' || $nombre === '-') {
                    $omitidas++;

                    continue;
                }

                $existente = ReportCatalog::withoutGlobalScopes()
                    ->where('tenant_id', self::TENANT_ID)
                    ->where('kind', $kind)
                    ->where('legacy_id', $fila['legacy_id'] ?? null)
                    ->first();

                if ($existente) {
                    continue;
                }

                ReportCatalog::withoutGlobalScopes()->create([
                    'slug'       => Str::random(22),
                    'tenant_id'  => self::TENANT_ID,
                    'kind'       => $kind,
                    'name'       => $nombre,
                    'legacy_id'  => $fila['legacy_id'] ?? null,
                    'sort_order' => ++$orden,
                    'is_active'  => true,
                ]);

                $creadas++;
            }
        }

        $creadas += $this->unidadesDeAlmacen();

        $this->command?->info(sprintf(
            'Catálogos del informe: %d filas creadas (%d centinelas «-» omitidos).',
            $creadas,
            $omitidas,
        ));
    }

    /**
     * La quinta lista: en qué se cuenta un artículo del almacén.
     *
     * NO sale del volcado. El sistema anterior la tenía en `stock_units`, y de
     * esa tabla solo se consiguió la estructura —el volcado es `--no-data`—,
     * así que sembrar «lo que tenía el laboratorio» sería inventarlo. Estas son
     * unidades de arranque para que la pantalla no abra vacía; el laboratorio
     * las corrige desde el editor de listas.
     *
     * Se emparejan por NOMBRE y no por `legacy_id`, que acá no existe: con
     * `legacy_id` nulo la comparación `= NULL` no encuentra nada en SQL y cada
     * corrida del seed volvería a crearlas.
     */
    private function unidadesDeAlmacen(): int
    {
        $unidades = ['Unidad', 'Frasco', 'Caja', 'L', 'mL', 'kg', 'g'];
        $creadas = 0;
        $orden = 0;

        foreach ($unidades as $nombre) {
            $orden++;

            $existe = ReportCatalog::withoutGlobalScopes()
                ->where('tenant_id', self::TENANT_ID)
                ->where('kind', ReportCatalog::KIND_STOCK_UNIT)
                ->where('name', $nombre)
                ->exists();

            if ($existe) {
                continue;
            }

            ReportCatalog::withoutGlobalScopes()->create([
                'slug'       => Str::random(22),
                'tenant_id'  => self::TENANT_ID,
                'kind'       => ReportCatalog::KIND_STOCK_UNIT,
                'name'       => $nombre,
                'sort_order' => $orden,
                'is_active'  => true,
            ]);

            $creadas++;
        }

        return $creadas;
    }
}
