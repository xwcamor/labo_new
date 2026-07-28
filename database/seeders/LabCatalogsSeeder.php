<?php

namespace Database\Seeders;

use App\Models\OilType;
use App\Models\TransformerType;
use Illuminate\Database\Seeder;

/**
 * Catálogos reales del laboratorio, tomados del sistema Rails viejo.
 *
 * Los IDs se fijan a propósito: los cuadros de límites extraídos del Ruby
 * (`database/seeders/data/spec_limits_legacy.json`) condicionan por ID
 * numérico de aceite y de tipo de equipo. Mantenerlos permite sembrar y
 * verificar los cuadros sin traducir nada.
 *
 * Los CÓDIGOS son la referencia estable de aquí en adelante — las reglas y la
 * API se atan al code, nunca al ID (mismo criterio que TrafoDex). Los IDs son
 * solo el puente con lo histórico.
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
        // ── Tipos de aceite (ID = el del sistema viejo) ──────────────────
        foreach ([
            ['id' => 1, 'code' => 'mineral',          'name' => 'Mineral'],
            ['id' => 2, 'code' => 'aceite_2',         'name' => 'Aceite 2 (sin identificar)', 'is_active' => false],
            ['id' => 3, 'code' => 'aceite_3',         'name' => 'Aceite 3 (sin identificar)', 'is_active' => false],
            ['id' => 4, 'code' => 'silicona',         'name' => 'Silicona'],
            ['id' => 5, 'code' => 'vegetal_soya',     'name' => 'Éster natural (soya)'],
            ['id' => 6, 'code' => 'vegetal_girasol',  'name' => 'Éster natural (girasol)'],
            ['id' => 7, 'code' => 'ester_sintetico',  'name' => 'Éster sintético (Midel)'],
            ['id' => 8, 'code' => 'aceite_8',         'name' => 'Aceite 8 (sin identificar)', 'is_active' => false],
            ['id' => 9, 'code' => 'aceite_9',         'name' => 'Aceite 9 (sin identificar)', 'is_active' => false],
        ] as $i => $row) {
            OilType::withTrashed()->updateOrCreate(
                ['id' => $row['id']],
                $row + ['sort_order' => $i + 1, 'is_active' => $row['is_active'] ?? true]
            );
        }

        // ── Tipos de equipo (ID = el del sistema viejo) ──────────────────
        // La lista sale del comentario de referencia en
        // labo_old/app/views/im_management/rem_reports/partials/
        //   _form_add_details_physicals_default_values.html.erb
        // Son 20. TrafoDex conoce 3 (potencia, distribución, horno) y por eso
        // el envío actual manda todo lo demás como "potencia".
        foreach ([
            ['id' => 1,  'code' => 'potencia',          'name' => 'Potencia',           'shape' => 'tank'],
            ['id' => 2,  'code' => 'distribucion',      'name' => 'Distribución',       'shape' => 'pole'],
            ['id' => 3,  'code' => 'horno',             'name' => 'Horno',              'shape' => 'tank'],
            ['id' => 4,  'code' => 'corriente',         'name' => 'De corriente',       'shape' => 'dry'],
            ['id' => 5,  'code' => 'voltaje',           'name' => 'De voltaje',         'shape' => 'dry'],
            ['id' => 6,  'code' => 'instrumento',       'name' => 'Instrumento',        'shape' => 'dry'],
            ['id' => 7,  'code' => 'bushing',           'name' => 'Bushing',            'shape' => 'dry'],
            ['id' => 8,  'code' => 'cables',            'name' => 'Cables',             'shape' => 'dry'],
            ['id' => 9,  'code' => 'interruptor',       'name' => 'Interruptor',        'shape' => 'dry'],
            ['id' => 10, 'code' => 'conmutador',        'name' => 'Conmutador',         'shape' => 'dry'],
            ['id' => 11, 'code' => 'reactor',           'name' => 'Reactor',            'shape' => 'tank'],
            ['id' => 12, 'code' => 'termovacio',        'name' => 'Termovacío',         'shape' => 'dry'],
            ['id' => 13, 'code' => 'transformador',     'name' => 'Transformador',      'shape' => 'tank'],
            ['id' => 14, 'code' => 'rectificador',      'name' => 'Rectificador',       'shape' => 'tank'],
            ['id' => 15, 'code' => 'trafomix',          'name' => 'Trafomix',           'shape' => 'tank'],
            ['id' => 16, 'code' => 'nulo',              'name' => 'Sin tipo',           'shape' => 'dry', 'is_active' => false],
            ['id' => 17, 'code' => 'autotransformador', 'name' => 'Autotransformador',  'shape' => 'tank'],
            ['id' => 18, 'code' => 'electrobomba',      'name' => 'Electrobomba',       'shape' => 'dry'],
            ['id' => 19, 'code' => 'magneto',           'name' => 'Magneto',            'shape' => 'dry'],
            ['id' => 20, 'code' => 'intercambiador',    'name' => 'Intercambiador',     'shape' => 'dry'],
        ] as $i => $row) {
            TransformerType::withTrashed()->updateOrCreate(
                ['id' => $row['id']],
                $row + ['sort_order' => $i + 1, 'is_active' => $row['is_active'] ?? true]
            );
        }
    }
}
