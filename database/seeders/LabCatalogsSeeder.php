<?php

namespace Database\Seeders;

use App\Models\OilType;
use App\Models\EquipmentType;
use Illuminate\Database\Seeder;

/**
 * Catálogos reales del laboratorio, tomados del sistema Rails viejo.
 *
 * El CÓDIGO es la clave estable: las reglas, los cuadros de límites y la API
 * se atan al `code`, nunca al ID (mismo criterio que TrafoDex, que lo dice
 * explícito en su seeder: "los códigos no cambian nunca").
 *
 * Los IDs los asigna Postgres y no significan nada. En una versión anterior de
 * este seeder se clavaban a los del sistema Rails viejo, para poder verificar
 * los cuadros extraídos sin traducir. Se revirtió: acoplar las claves primarias
 * del sistema nuevo a las de un sistema muerto obliga a consultar un mapa de
 * 2019 para entender una fila de 2028 — que es justo el "mandrakeo" del que
 * esta migración se trata de salir. La correspondencia ID viejo → código vive
 * en `_meta` de `spec_limits_legacy.json`, como referencia del ETL de la
 * fase 12 y de ningún otro lado.
 *
 * OJO: los aceites 2, 3, 8 y 9 existen en el sistema viejo y reciben norma,
 * pero NINGUNA rama del código les asigna cuadro de límites. Se siembran con
 * el nombre desconocido a propósito, para que se vean: taparlos con un alias
 * inventado sería decidir por el laboratorio. Ver la anomalía
 * ACEITES-SIN-CUADRO en spec_limits_legacy.json.
 */
class LabCatalogsSeeder extends Seeder
{
    public function run(): void
    {
        // ── Tipos de aceite ─────────────────────────────────────────────
        foreach ([
            ['code' => 'mineral',          'name' => 'Mineral'],
            ['code' => 'aceite_2',         'name' => 'Aceite 2 (sin identificar)', 'is_active' => false],
            ['code' => 'aceite_3',         'name' => 'Aceite 3 (sin identificar)', 'is_active' => false],
            ['code' => 'silicona',         'name' => 'Silicona'],
            ['code' => 'vegetal_soya',     'name' => 'Éster natural (soya)'],
            ['code' => 'vegetal_girasol',  'name' => 'Éster natural (girasol)'],
            ['code' => 'ester_sintetico',  'name' => 'Éster sintético (Midel)'],
            ['code' => 'aceite_8',         'name' => 'Aceite 8 (sin identificar)', 'is_active' => false],
            ['code' => 'aceite_9',         'name' => 'Aceite 9 (sin identificar)', 'is_active' => false],
        ] as $i => $row) {
            OilType::withTrashed()->updateOrCreate(
                ['code' => $row['code']],
                $row + ['sort_order' => $i + 1, 'is_active' => $row['is_active'] ?? true]
            );
        }

        // ── Tipos de equipo ─────────────────────────────────────────────
        // La lista sale del comentario de referencia en
        // labo_old/app/views/im_management/rem_reports/partials/
        //   _form_add_details_physicals_default_values.html.erb
        // Son 20. TrafoDex conoce 3 (potencia, distribución, horno) y por eso
        // el envío actual manda todo lo demás como "potencia".
        foreach ([
            ['code' => 'potencia',          'name' => 'Potencia',           'shape' => 'tank'],
            ['code' => 'distribucion',      'name' => 'Distribución',       'shape' => 'pole'],
            ['code' => 'horno',             'name' => 'Horno',              'shape' => 'tank'],
            ['code' => 'corriente',         'name' => 'De corriente',       'shape' => 'dry'],
            ['code' => 'voltaje',           'name' => 'De voltaje',         'shape' => 'dry'],
            ['code' => 'instrumento',       'name' => 'Instrumento',        'shape' => 'dry'],
            ['code' => 'bushing',           'name' => 'Bushing',            'shape' => 'dry'],
            ['code' => 'cables',            'name' => 'Cables',             'shape' => 'dry'],
            ['code' => 'interruptor',       'name' => 'Interruptor',        'shape' => 'dry'],
            ['code' => 'conmutador',        'name' => 'Conmutador',         'shape' => 'dry'],
            ['code' => 'reactor',           'name' => 'Reactor',            'shape' => 'tank'],
            ['code' => 'termovacio',        'name' => 'Termovacío',         'shape' => 'dry'],
            ['code' => 'transformador',     'name' => 'Transformador',      'shape' => 'tank'],
            ['code' => 'rectificador',      'name' => 'Rectificador',       'shape' => 'tank'],
            ['code' => 'trafomix',          'name' => 'Trafomix',           'shape' => 'tank'],
            ['code' => 'nulo',              'name' => 'Sin tipo',           'shape' => 'dry', 'is_active' => false],
            ['code' => 'autotransformador', 'name' => 'Autotransformador',  'shape' => 'tank'],
            ['code' => 'electrobomba',      'name' => 'Electrobomba',       'shape' => 'dry'],
            ['code' => 'magneto',           'name' => 'Magneto',            'shape' => 'dry'],
            ['code' => 'intercambiador',    'name' => 'Intercambiador',     'shape' => 'dry'],
            // El 21º tipo, que faltaba. La lista se armó leyendo un COMENTARIO
            // de una vista del sistema anterior en vez del volcado de datos, y
            // ese comentario estaba viejo: el tipo se creó en agosto de 2024
            // (`docs/migracion/esquema/catalogos-definiciones.sql:1845`). Un
            // equipo cuyo tipo no está en el catálogo no se puede dar de alta, y
            // la API del laboratorio lo rechaza a propósito para no inventar un
            // tipo: la ausencia bloqueaba el ingreso de esas muestras.
            ['code' => 'regulador_voltaje', 'name' => 'Regulador de Voltaje', 'shape' => 'tank'],
        ] as $i => $row) {
            EquipmentType::withTrashed()->updateOrCreate(
                ['code' => $row['code']],
                $row + ['sort_order' => $i + 1, 'is_active' => $row['is_active'] ?? true]
            );
        }
    }
}
