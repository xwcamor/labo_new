<?php

namespace App\Http\Requests\BusinessManagement\Signature;

use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Concerns\DerivesCodeFromName;
use Illuminate\Support\Facades\DB;
class StoreSignatureRequest extends FormRequest
{
    use DerivesAttributesFromLang;

    protected $attributeNamespace = 'signatures';

    use DerivesCodeFromName;

    protected function prepareForValidation(): void
    {
        $this->deriveCodeFromName();
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Unicidad case + accent insensitive. signatures es PER-TENANT: el nombre
            // es unico dentro del workspace del actor (no cross-tenant). Se filtra
            // por tenant_id para alinear con el indice unico parcial de la tabla.
            'name'       => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('signatures')
                        ->whereNull('deleted_at')
                        ->where('tenant_id', auth()->user()?->tenant_id);
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('signatures.name_unique'));
                    }
                },
            ],
            'code'       => [
                'nullable', 'string', 'max:40',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') return;
                    $exists = DB::table('signatures')
                        ->whereNull('deleted_at')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->whereRaw('LOWER(code) = LOWER(?)', [trim((string) $value)])
                        ->exists();
                    if ($exists) {
                        $fail(__('signatures.code_unique'));
                    }
                },
            ],
            // ── Lo propio de una firma ─────────────────────────────────
            'title'    => ['nullable', 'string', 'max:160'],
            // Lista cerrada: es lo que se imprime sobre la línea y tiene que
            // decir lo mismo en los dos idiomas. Texto libre daría "Aprobado
            // por", "aprobado" y "APROBÓ" como tres relaciones distintas.
            'relation' => ['nullable', \Illuminate\Validation\Rule::in(\App\Models\Signature::RELATIONS)],
            // El firmante con cuenta en el sistema firma con SU imagen, cargada
            // por él mismo desde su perfil. Acá solo se enlaza.
            'user_id'  => ['nullable', 'integer', 'exists:users,id'],
            // La imagen se acepta solo para el firmante SIN cuenta: el
            // laboratorio igual necesita su firma en el papel.
            'image'    => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
