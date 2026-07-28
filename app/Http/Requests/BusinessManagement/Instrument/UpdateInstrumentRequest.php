<?php

namespace App\Http\Requests\BusinessManagement\Instrument;

use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInstrumentRequest extends FormRequest
{
    use DerivesAttributesFromLang;
    use InstrumentRules;

    protected $attributeNamespace = 'instruments';

    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se edita hasta desbloquearlo.
        $instrument = $this->route('instrument');
        if (is_object($instrument) && $instrument->is_locked) {
            return false;
        }
        return true;
    }

    public function rules(): array
    {
        $instrument = $this->route('instrument');

        return $this->instrumentRules(is_object($instrument) ? $instrument->id : null);
    }
}
