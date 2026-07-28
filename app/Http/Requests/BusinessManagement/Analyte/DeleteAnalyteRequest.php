<?php

namespace App\Http\Requests\BusinessManagement\Analyte;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAnalyteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se elimina hasta desbloquearlo.
        $analyte = $this->route('analyte');
        if (is_object($analyte) && $analyte->is_locked) {
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
