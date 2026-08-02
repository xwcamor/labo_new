<?php

namespace App\Http\Requests\BusinessManagement\Signature;

use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
class UpdateSignatureRequest extends FormRequest
{
    use DerivesAttributesFromLang;

    protected $attributeNamespace = 'signatures';

    public function authorize(): bool
    {
        // Registro BLOQUEADO (Lockable): no se edita hasta desbloquearlo.
        $signature = $this->route('signature');
        if (is_object($signature) && $signature->is_locked) {
            return false;
        }
        return true;
    }

    public function rules(): array
    {
        $signature   = $this->route('signature');
        $signatureId = is_object($signature) ? $signature->id : null;

        return [
            // Unicidad de name case + accent insensitive PER-TENANT, ignorando el
            // propio signature y soft-deleted. Se filtra por tenant_id para alinear con
            // el indice unico parcial (tenant_id, name) de la tabla.
            'name'       => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($signatureId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('signatures')
                        ->whereNull('deleted_at')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->when($signatureId, fn ($qq) => $qq->where('id', '!=', $signatureId));
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
                function ($attribute, $value, $fail) use ($signatureId) {
                    if ($value === null || $value === '') return;
                    $exists = DB::table('signatures')
                        ->whereNull('deleted_at')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->when($signatureId, fn ($qq) => $qq->where('id', '!=', $signatureId))
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
