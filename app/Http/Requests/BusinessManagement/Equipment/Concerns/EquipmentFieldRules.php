<?php

namespace App\Http\Requests\BusinessManagement\Equipment\Concerns;

use App\Models\Equipment;
use App\Rules\EquipmentIdentityIsFree;
use App\Rules\HierarchyBelongsToCustomer;
use Illuminate\Validation\Rule;

/**
 * Los campos de un equipo, una sola vez para el alta y la edición.
 *
 * Estaban duplicados entre los dos FormRequests, y ahí es donde se desalinean:
 * el alta pedía una cosa y la edición otra. Lo que cambia entre los dos es solo
 * el registro que se ignora al buscar duplicados.
 */
trait EquipmentFieldRules
{
    /**
     * @return array<string,mixed>
     */
    protected function equipmentRules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            // ── De quién es y dónde está ────────────────────────────────
            // El CLIENTE es obligatorio. Sin él, el equipo no aparece en
            // ninguna recepción: la recepción solo ofrece los equipos del
            // cliente de la entrega, justamente para no colgarle la muestra de
            // una empresa al transformador de otra.
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'customer_location_id' => [
                'nullable', 'integer', 'exists:customer_locations,id',
                new HierarchyBelongsToCustomer($this, 'location'),
            ],
            'customer_area_id' => [
                'nullable', 'integer', 'exists:customer_areas,id',
                new HierarchyBelongsToCustomer($this, 'area'),
            ],
            'customer_substation_id' => [
                'nullable', 'integer', 'exists:customer_substations,id',
                new HierarchyBelongsToCustomer($this, 'substation'),
            ],

            // ── Qué es ──────────────────────────────────────────────────
            // Tipo de equipo y tipo de aceite no son decoración: junto con la
            // banda de tensión son las tres dimensiones con las que
            // SpecSetResolver elige el cuadro de límites. Sin ellos el
            // resultado no se puede comparar contra ninguna norma y sale "sin
            // criterio" — que es peor que un dato faltante, porque parece que
            // cumple.
            'equipment_type_id' => ['nullable', 'integer', 'exists:equipment_types,id'],
            'oil_type_id' => ['nullable', 'integer', 'exists:oil_types,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'tap_changer_type_id' => ['nullable', 'integer', 'exists:tap_changer_types,id'],
            'transformer_preservation_id' => ['nullable', 'integer', 'exists:transformer_preservations,id'],

            // ── La chapa ────────────────────────────────────────────────
            'serial' => ['nullable', 'string', 'max:255', new EquipmentIdentityIsFree($this, $ignoreId)],
            'tag' => ['nullable', 'string', 'max:255', new EquipmentIdentityIsFree($this, $ignoreId)],

            // ── Datos físicos ───────────────────────────────────────────
            // Con cotas: una tensión negativa o un año de fabricación de tres
            // cifras no son un dato raro, son un error de tipeo, y llegan hasta
            // la banda de tensión que elige el cuadro de límites.
            'voltage_kv_hv' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'voltage_kv_lv' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'power_mva' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'phases' => ['nullable', 'integer', 'min:1', 'max:3'],
            'manufacture_year' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'oil_volume' => ['nullable', 'numeric', 'min:0'],
            'oil_volume_unit' => ['nullable', Rule::in(Equipment::OIL_VOLUME_UNITS)],
            'service_state' => ['nullable', Rule::in(Equipment::SERVICE_STATES)],

            'external_ref' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
