<?php

namespace App\Http\Requests\BusinessManagement\EquipmentType;

use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
class UpdateEquipmentTypeRequest extends FormRequest
{
    use DerivesAttributesFromLang;

    protected $attributeNamespace = 'equipment_types';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $equipmentType   = $this->route('equipmentType');
        $equipmentTypeId = is_object($equipmentType) ? $equipmentType->id : null;

        return [
            // Unicidad de name case + accent insensitive (catalogo GLOBAL, sin
            // tenant), ignorando el propio equipmentType y soft-deleted. NO se filtra
            // por tenant_id: la tabla no tiene esa columna (filtrarla revienta
            // en Postgres con un 500 al crear/editar).
            'name'       => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($equipmentTypeId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('equipment_types')
                        ->whereNull('deleted_at')
                        ->when($equipmentTypeId, fn ($qq) => $qq->where('id', '!=', $equipmentTypeId));
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('equipment_types.name_unique'));
                    }
                },
            ],
            'code'       => [
                'nullable', 'string', 'max:40',
                function ($attribute, $value, $fail) use ($equipmentTypeId) {
                    if ($value === null || $value === '') return;
                    $exists = DB::table('equipment_types')
                        ->whereNull('deleted_at')
                        ->when($equipmentTypeId, fn ($qq) => $qq->where('id', '!=', $equipmentTypeId))
                        ->whereRaw('LOWER(code) = LOWER(?)', [trim((string) $value)])
                        ->exists();
                    if ($exists) {
                        $fail(__('equipment_types.code_unique'));
                    }
                },
            ],
            'shape'      => ['sometimes', 'nullable', 'in:tank,pole,dry'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
