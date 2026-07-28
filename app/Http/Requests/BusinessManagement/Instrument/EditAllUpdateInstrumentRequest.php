<?php

namespace App\Http\Requests\BusinessManagement\Instrument;

use Illuminate\Foundation\Http\FormRequest;

class EditAllUpdateInstrumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('instruments.edit') ?? false;
    }

    public function rules(): array
    {
        // edit_all_max define cuantas filas se pueden tocar en un solo batch.
        $max = (int) config('instruments.edit_all_max', 200);

        return [
            'changes'             => "required|array|min:1|max:{$max}",
            'changes.*.id'        => 'required|integer',
            // name aceptado como sometimes (cliente puede mandar solo is_active),
            // pero si viene, NO puede ser empty string ni null. Sin min:1 antes
            // un cliente podía mandar name:"" y el instrument quedaba sin nombre
            // (rompía unicidad y búsqueda).
            'changes.*.name'      => 'sometimes|required|string|min:1|max:255',
            'changes.*.is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
