<?php

namespace App\Http\Requests\BusinessManagement\Instrument;

use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;

/**
 * OJO: a diferencia del resto de los catálogos, acá NO se deriva el `code` del
 * nombre. El código del instrumento es el de su hoja de calibración
 * (PP-LA-01C): inventarlo a partir del nombre produciría "bureta" para los tres
 * equipos que se llaman "Bureta" y rompería la unicidad justo donde importa.
 */
class StoreInstrumentRequest extends FormRequest
{
    use DerivesAttributesFromLang;
    use InstrumentRules;

    protected $attributeNamespace = 'instruments';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->instrumentRules();
    }
}
