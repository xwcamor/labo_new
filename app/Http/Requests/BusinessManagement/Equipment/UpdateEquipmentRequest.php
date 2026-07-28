<?php

namespace App\Http\Requests\BusinessManagement\Equipment;

use App\Http\Requests\BusinessManagement\Equipment\Concerns\EquipmentFieldRules;
use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edición de un equipo. Las reglas son las mismas del alta —viven en
 * `EquipmentFieldRules`— ignorando el propio registro al buscar duplicados.
 * Por qué la identidad es (serie, tag) y no el nombre ni un código: ver
 * `StoreEquipmentRequest`.
 */
class UpdateEquipmentRequest extends FormRequest
{
    use DerivesAttributesFromLang;
    use EquipmentFieldRules;

    protected $attributeNamespace = 'equipment';

    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se edita hasta desbloquearlo.
        $equipment = $this->route('equipment');
        if (is_object($equipment) && $equipment->is_locked) {
            return false;
        }
        return true;
    }

    public function rules(): array
    {
        $equipment   = $this->route('equipment');
        $equipmentId = is_object($equipment) ? $equipment->id : null;

        return $this->equipmentRules($equipmentId);
    }

}
