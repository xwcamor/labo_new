<?php

namespace App\Http\Requests\BusinessManagement\Sampler;

use Illuminate\Foundation\Http\FormRequest;

class DeleteSamplerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se elimina hasta desbloquearlo.
        $sampler = $this->route('sampler');
        if (is_object($sampler) && $sampler->is_locked) {
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
