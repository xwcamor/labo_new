<?php

namespace App\Http\Requests\BusinessManagement\EquipmentType;

use Illuminate\Foundation\Http\FormRequest;

class BulkSetActiveEquipmentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('equipment_types.edit') ?? false;
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
            'is_active.required' => __('equipment_types.is_active_required'),
        ];
    }
}
