<?php

namespace App\Http\Requests\BusinessManagement\EntryAuthorizer;

use Illuminate\Foundation\Http\FormRequest;

class DeleteEntryAuthorizerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se elimina hasta desbloquearlo.
        $entryAuthorizer = $this->route('entryAuthorizer');
        if (is_object($entryAuthorizer) && $entryAuthorizer->is_locked) {
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
