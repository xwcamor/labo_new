<?php

namespace App\Http\Requests\BusinessManagement\Signature;

use Illuminate\Foundation\Http\FormRequest;

class DeleteSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se elimina hasta desbloquearlo.
        $signature = $this->route('signature');
        if (is_object($signature) && $signature->is_locked) {
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
