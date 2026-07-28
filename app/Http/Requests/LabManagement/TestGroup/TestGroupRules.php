<?php

namespace App\Http\Requests\LabManagement\TestGroup;

use Illuminate\Support\Facades\DB;

/**
 * Reglas compartidas por Store y Update.
 *
 * DOS CORRECCIONES SOBRE LO QUE GENERÓ EL SCAFFOLD:
 *
 *   1. El `code` es OBLIGATORIO (la columna es NOT NULL) y su índice único es
 *      GLOBAL, no por workspace: la comprobación NO se filtra por tenant_id.
 *      Y como el índice es un unique común y no uno parcial, alcanza con que
 *      exista una fila borrada con ese código para que el insert reviente:
 *      por eso la búsqueda incluye los soft-deleted.
 *
 *   2. El `name` NO es único. La tabla no lo exige y no hay razón de negocio
 *      para exigirlo; el scaffold lo validaba porque clonaba un catálogo.
 */
trait TestGroupRules
{
    /** @param int|null $ignoreId id del propio registro al editar */
    protected function testGroupRules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],

            'code' => [
                'required', 'string', 'max:40',
                function ($attribute, $value, $fail) use ($ignoreId) {
                    $exists = DB::table('test_groups')
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->whereRaw('LOWER(code) = LOWER(?)', [trim((string) $value)])
                        ->exists();
                    if ($exists) {
                        $fail(__('test_groups.code_unique'));
                    }
                },
            ],

            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
