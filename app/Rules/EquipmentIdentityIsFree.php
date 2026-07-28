<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * La identidad de un equipo: (serie, tag) no se repite dentro del workspace.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ NO ES EL NOMBRE                                                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El formulario venía del scaffold de catálogos y exigía NOMBRE único dentro
 * del workspace. Un laboratorio atiende a muchas empresas y todas tienen su
 * "Transformador Principal": esa regla le impedía a la segunda cargarlo. Lo que
 * de verdad no se puede repetir es el par serie + tag, que es la chapa del
 * equipo. Es la misma regla que ya validaba el sistema anterior
 * (`validates_uniqueness_of :num_tag, scope: [:num_serie]`), ahora acotada al
 * workspace y sin contar los borrados.
 *
 * La regla existe en la base como índice único parcial
 * (`equipment_serial_tag_unique_active`). Se valida acá ADEMÁS para que el
 * analista vea el mensaje en el campo; el índice es el que garantiza que dos
 * altas simultáneas no lo esquiven.
 *
 * Se aplica a los DOS campos porque el duplicado es del par: marcar solo uno
 * dejaría el otro sin señalar y el error se leería como si el problema fuera
 * únicamente ese.
 */
class EquipmentIdentityIsFree implements ValidationRule
{
    public function __construct(
        private readonly Request $request,
        private readonly ?int $ignoreId = null,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $serial = trim((string) $this->request->input('serial', ''));
        $tag    = trim((string) $this->request->input('tag', ''));

        // El índice único es parcial: solo cuenta cuando los dos están. Un
        // equipo sin chapa legible se carga igual —pasa— y queda identificado
        // por su cliente y su nombre.
        if ($serial === '' || $tag === '') {
            return;
        }

        $existe = DB::table('equipment')
            ->whereNull('deleted_at')
            ->where('tenant_id', auth()->user()?->tenant_id)
            ->when($this->ignoreId, fn ($q) => $q->where('id', '!=', $this->ignoreId))
            ->whereRaw('LOWER(serial) = LOWER(?)', [$serial])
            ->whereRaw('LOWER(tag) = LOWER(?)', [$tag])
            ->exists();

        if ($existe) {
            $fail(__('equipment.identity_unique'));
        }
    }
}
