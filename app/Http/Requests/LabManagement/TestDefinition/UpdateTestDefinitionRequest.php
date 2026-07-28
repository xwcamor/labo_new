<?php

namespace App\Http\Requests\LabManagement\TestDefinition;

use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTestDefinitionRequest extends FormRequest
{
    use DerivesAttributesFromLang;
    use TestDefinitionRules;

    protected $attributeNamespace = 'test_definitions';

    /** El label del select de grupo vive bajo otra clave que el nombre del campo. */
    protected $attributeOverrides = [
        'test_group_id' => 'test_definitions.group',
    ];

    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se edita hasta desbloquearlo.
        $testDefinition = $this->route('testDefinition');
        if (is_object($testDefinition) && $testDefinition->is_locked) {
            return false;
        }
        return true;
    }

    public function rules(): array
    {
        $testDefinition = $this->route('testDefinition');

        return $this->testDefinitionRules(is_object($testDefinition) ? $testDefinition->id : null);
    }
}
