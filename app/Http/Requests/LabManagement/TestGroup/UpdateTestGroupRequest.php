<?php

namespace App\Http\Requests\LabManagement\TestGroup;

use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTestGroupRequest extends FormRequest
{
    use DerivesAttributesFromLang;
    use TestGroupRules;

    protected $attributeNamespace = 'test_groups';

    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se edita hasta desbloquearlo.
        $testGroup = $this->route('testGroup');
        if (is_object($testGroup) && $testGroup->is_locked) {
            return false;
        }
        return true;
    }

    public function rules(): array
    {
        $testGroup = $this->route('testGroup');

        return $this->testGroupRules(is_object($testGroup) ? $testGroup->id : null);
    }
}
