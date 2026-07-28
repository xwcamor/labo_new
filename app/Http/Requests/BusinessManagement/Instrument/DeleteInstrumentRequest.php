<?php

namespace App\Http\Requests\BusinessManagement\Instrument;

use Illuminate\Foundation\Http\FormRequest;

class DeleteInstrumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se elimina hasta desbloquearlo.
        $instrument = $this->route('instrument');
        if (is_object($instrument) && $instrument->is_locked) {
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
