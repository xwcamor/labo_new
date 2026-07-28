<?php

namespace App\Http\Requests\LabManagement\TestDefinition;

use Illuminate\Foundation\Http\FormRequest;

class BulkSetActiveTestDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('test_definitions.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'       => 'required|array|min:1|max:500',
            'ids.*'     => 'integer',
            'is_active' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required'       => __('global.bulk_no_selection'),
            'is_active.required' => __('test_definitions.is_active_required'),
        ];
    }
}
