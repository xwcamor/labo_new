<?php

namespace App\Http\Requests\LabManagement\TestDefinition;

use Illuminate\Foundation\Http\FormRequest;

class DeleteTestDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se elimina hasta desbloquearlo.
        $testDefinition = $this->route('testDefinition');
        if (is_object($testDefinition) && $testDefinition->is_locked) {
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
