<?php

namespace App\Http\Requests\LabManagement\TestGroup;

use Illuminate\Foundation\Http\FormRequest;

class DeleteTestGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se elimina hasta desbloquearlo.
        $testGroup = $this->route('testGroup');
        if (is_object($testGroup) && $testGroup->is_locked) {
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
