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
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LOS TIPOS DE ACEITE SON CUATRO, Y ESTO SE SUPO TARDE                     │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Esta lista tenía NUEVE: los cuatro reales, un «éster natural (girasol)» y
 * cuatro «sin identificar». Los cinco de más estaban INFERIDOS, no copiados de
 * ninguna fuente, y el motivo es que la tabla de tipos de aceite NO ES DEL
 * LABORATORIO: en el sistema viejo el modelo es `class OilType < Primary2`, o
 * sea que vive en la base compartida con TRAPP. Por eso no aparece en el volcado
 * —cero apariciones en los dos `.sql` de `docs/migracion/esquema/`— y hubo que
 * deducirla de los `oil_type_id` que el código menciona.
 *
 * El 2026-07-31 el dueño mostró el desplegable del sistema en producción, que es
 * la fuente directa. Tiene CUATRO opciones más el centinela:
 *
 *     Mineral · Silicona · Éster Vegetal · Éster Sintético · -
 *
 * Coincide con las ramas del código viejo, que solo distingue 1 (mineral),
 * 4 (silicona), 5 (éster vegetal) y 7 (éster sintético).
 *
 * QUÉ PASÓ CON EL GIRASOL. El código viejo sí tiene una rama `oil_type_id == 6`
 * con los límites de cromatografía del girasol (`rem_report_detail.rb:637-651`),
 * distintos de los de la soya. Pero ese tipo ya no está en el desplegable, así
 * que ningún equipo puede tenerlo: la rama quedó INALCANZABLE. El cuadro no se
 * borra —sus números son reales y están respaldados por la hoja CR de
 * `VALORES_DE_ORIENTACION.xlsx`— pero deja de resolverse por tipo de aceite y
 * pasa a elegirse a mano, que es como el laboratorio resuelve lo que el
 * automático no cubre.
 *
 * Y el 5 es «Éster Vegetal» a secas: en cromatografía recibe los números que el
 * cuadro rotula SOYA - FR3, y en fisicoquímicos comparte cuadro con el éster
 * sintético (`5 or 7`, `rem_report_detail.rb:299`).
 *
 * Los cuatro «sin identificar» se van por lo mismo: eran huecos de ID que este
 * seeder rellenaba para que los números cuadraran. Un catálogo con cuatro
 * entradas que nadie puede explicar es peor que uno corto.
 */
class LabCatalogsSeeder extends Seeder
{
    public function run(): void
    {
        // ── Tipos de aceite ─────────────────────────────────────────────
        foreach ([
            // Los cuatro del desplegable del sistema en producción, en su orden.
            // El centinela «-» no se siembra: acá la ausencia de tipo es NULL,
            // que es lo que significa, y no una fila del catálogo.
            ['code' => 'mineral',          'name' => 'Mineral'],
            ['code' => 'silicona',         'name' => 'Silicona'],
            ['code' => 'ester_vegetal',    'name' => 'Éster Vegetal'],
            ['code' => 'ester_sintetico',  'name' => 'Éster Sintético'],
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
