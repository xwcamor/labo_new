<?php

namespace App\Http\Requests\BusinessManagement\Analyte;

use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
class UpdateAnalyteRequest extends FormRequest
{
    use DerivesAttributesFromLang;

    protected $attributeNamespace = 'analytes';

    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se edita hasta desbloquearlo.
        $analyte = $this->route('analyte');
        if (is_object($analyte) && $analyte->is_locked) {
            return false;
        }
        return true;
    }

    public function rules(): array
    {
        $analyte   = $this->route('analyte');
        $analyteId = is_object($analyte) ? $analyte->id : null;

        return [
            // Unicidad de name case + accent insensitive PER-TENANT, ignorando el
            // propio analyte y soft-deleted. Se filtra por tenant_id para alinear con
            // el indice unico parcial (tenant_id, name) de la tabla.
            'name'       => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($analyteId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('analytes')
                        ->whereNull('deleted_at')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->when($analyteId, fn ($qq) => $qq->where('id', '!=', $analyteId));
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('analytes.name_unique'));
                    }
                },
            ],
            'code'       => [
                'nullable', 'string', 'max:40',
                function ($attribute, $value, $fail) use ($analyteId) {
                    if ($value === null || $value === '') return;
                    $exists = DB::table('analytes')
                        ->whereNull('deleted_at')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->when($analyteId, fn ($qq) => $qq->where('id', '!=', $analyteId))
                        ->whereRaw('LOWER(code) = LOWER(?)', [trim((string) $value)])
                        ->exists();
                    if ($exists) {
                        $fail(__('analytes.code_unique'));
                    }
                },
            ],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
