<?php

namespace App\Http\Requests\LabManagement\TestDefinition;

use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;

class StoreTestDefinitionRequest extends FormRequest
{
    use DerivesAttributesFromLang;
    use DerivesCodeFromName;
    use TestDefinitionRules;

    protected $attributeNamespace = 'test_definitions';

    /** El label del select de grupo vive bajo otra clave que el nombre del campo. */
    protected $attributeOverrides = [
        'test_group_id' => 'test_definitions.group',
    ];

    protected function prepareForValidation(): void
    {
        $this->mergeCodeFromName();
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
