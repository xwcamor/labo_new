<?php

namespace App\Http\Requests\LabManagement\TestDefinition;

use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreTestDefinitionRequest extends FormRequest
{
    use DerivesAttributesFromLang;
    use TestDefinitionRules;

    protected $attributeNamespace = 'test_definitions';

    /** El label del select de grupo vive bajo otra clave que el nombre del campo. */
    protected $attributeOverrides = [
        'test_group_id' => 'test_definitions.group',
    ];

    protected function prepareForValidation(): void
    {
        // Código derivado del nombre con Str::slug —el mismo que usa el
        // importador de las 29 pruebas del sistema viejo—, para que dar de
        // alta "Número Ácido" a mano y volver a correr el importador no
        // terminen en dos filas: "numero_acido" en los dos casos.
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
        return $this->testDefinitionRules();
    }
}
