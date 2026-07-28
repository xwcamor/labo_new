<?php

namespace App\Http\Requests\BusinessManagement\Equipment;

use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
class UpdateEquipmentRequest extends FormRequest
{
    use DerivesAttributesFromLang;

    protected $attributeNamespace = 'equipment';

    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se edita hasta desbloquearlo.
        $equipment = $this->route('equipment');
        if (is_object($equipment) && $equipment->is_locked) {
            return false;
        }
        return true;
    }

    public function rules(): array
    {
        $equipment   = $this->route('equipment');
        $equipmentId = is_object($equipment) ? $equipment->id : null;

        return [
            // Unicidad de name case + accent insensitive PER-TENANT, ignorando el
            // propio equipment y soft-deleted. Se filtra por tenant_id para alinear con
            // el indice unico parcial (tenant_id, name) de la tabla.
            'name'       => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($equipmentId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('equipment')
                        ->whereNull('deleted_at')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->when($equipmentId, fn ($qq) => $qq->where('id', '!=', $equipmentId));
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('equipment.name_unique'));
                    }
                },
            ],
            'code'       => [
                'nullable', 'string', 'max:40',
                function ($attribute, $value, $fail) use ($equipmentId) {
                    if ($value === null || $value === '') return;
                    $exists = DB::table('equipment')
                        ->whereNull('deleted_at')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->when($equipmentId, fn ($qq) => $qq->where('id', '!=', $equipmentId))
                        ->whereRaw('LOWER(code) = LOWER(?)', [trim((string) $value)])
                        ->exists();
                    if ($exists) {
                        $fail(__('equipment.code_unique'));
                    }
                },
            ],
            'is_active'  => ['sometimes', 'boolean'],
            'serial' => 'nullable|string|max:255',
            'tag' => 'nullable|string|max:255',
            'voltage_kv_hv' => 'nullable|numeric',
            'voltage_kv_lv' => 'nullable|numeric',
            'power_mva' => 'nullable|numeric',
            'phases' => 'nullable|integer',
            'manufacture_year' => 'nullable|integer',
            'oil_volume' => 'nullable|numeric',
            'external_ref' => 'nullable|string|max:255',
        ];
    }
}
