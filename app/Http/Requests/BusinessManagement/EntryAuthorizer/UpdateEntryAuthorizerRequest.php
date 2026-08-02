<?php

namespace App\Http\Requests\BusinessManagement\EntryAuthorizer;

use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
class UpdateEntryAuthorizerRequest extends FormRequest
{
    use DerivesAttributesFromLang;

    protected $attributeNamespace = 'entry_authorizers';

    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se edita hasta desbloquearlo.
        $entryAuthorizer = $this->route('entryAuthorizer');
        if (is_object($entryAuthorizer) && $entryAuthorizer->is_locked) {
            return false;
        }
        return true;
    }

    public function rules(): array
    {
        $entryAuthorizer   = $this->route('entryAuthorizer');
        $entryAuthorizerId = is_object($entryAuthorizer) ? $entryAuthorizer->id : null;

        return [
            // Unicidad de name case + accent insensitive PER-TENANT, ignorando el
            // propio entryAuthorizer y soft-deleted. Se filtra por tenant_id para alinear con
            // el indice unico parcial (tenant_id, name) de la tabla.
            'name'       => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($entryAuthorizerId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('entry_authorizers')
                        ->whereNull('deleted_at')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->when($entryAuthorizerId, fn ($qq) => $qq->where('id', '!=', $entryAuthorizerId));
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('entry_authorizers.name_unique'));
                    }
                },
            ],
            'code'       => [
                'nullable', 'string', 'max:40',
                function ($attribute, $value, $fail) use ($entryAuthorizerId) {
                    if ($value === null || $value === '') return;
                    $exists = DB::table('entry_authorizers')
                        ->whereNull('deleted_at')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->when($entryAuthorizerId, fn ($qq) => $qq->where('id', '!=', $entryAuthorizerId))
                        ->whereRaw('LOWER(code) = LOWER(?)', [trim((string) $value)])
                        ->exists();
                    if ($exists) {
                        $fail(__('entry_authorizers.code_unique'));
                    }
                },
            ],
            // La firma escaneada. Opcional: sin imagen el acta deja la línea
            // para firmar a mano.
            'image'      => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
