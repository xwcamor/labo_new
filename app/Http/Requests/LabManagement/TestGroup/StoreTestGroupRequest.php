<?php

namespace App\Http\Requests\LabManagement\TestGroup;

use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreTestGroupRequest extends FormRequest
{
    use DerivesAttributesFromLang;
    use TestGroupRules;

    protected $attributeNamespace = 'test_groups';

    protected function prepareForValidation(): void
    {
        // El código es obligatorio, pero se deriva del nombre si se deja vacío.
        // Se usa Str::slug, no el trait DerivesCodeFromName del scaffold: aquel
        // solo baja a minúsculas y cambia espacios por guion bajo, así que
        // "Físico Químico" quedaría como "físico_químico" —con tildes— mientras
        // que el importador de las pruebas del sistema viejo genera
        // "fisico_quimico". Dos códigos distintos para el mismo grupo, y el
        // importador crearía un duplicado.
        if (blank($this->input('code')) && filled($this->input('name'))) {
            $this->merge(['code' => Str::slug((string) $this->input('name'), '_')]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->testGroupRules();
    }
}
