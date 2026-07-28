<?php

namespace App\Http\Requests\BusinessManagement\Equipment;

use Illuminate\Foundation\Http\FormRequest;

class DeleteEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se elimina hasta desbloquearlo.
        $equipment = $this->route('equipment');
        if (is_object($equipment) && $equipment->is_locked) {
            return false;
        }
        return true;
    }

    public function rules(): array
    {
        return [
            'deleted_description' => 'required|string|min:3|max:1000',
        ];
    }
}
