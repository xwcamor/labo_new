<?php

namespace App\Http\Requests\LabManagement\TestDefinition;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas por Store y Update.
 *
 * LO QUE CAMBIA RESPECTO DEL SCAFFOLD:
 *
 *   - `code` obligatorio y único GLOBAL (la columna es NOT NULL + unique común,
 *     no un índice parcial por workspace): la comprobación no se filtra por
 *     tenant y SÍ mira los soft-deleted, porque un unique común los cuenta.
 *   - `name` deja de ser único: la tabla no lo exige.
 *   - `legacy_id` NO se valida acá a propósito: es el id en el sistema Rails
 *     viejo, lo escribe el importador y no se edita desde el formulario. Si
 *     apareciera en el request, se descarta al no estar en las reglas.
 */
trait TestDefinitionRules
{
    /** @param int|null $ignoreId id del propio registro al editar */
    protected function testDefinitionRules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],

            'code' => [
                'required', 'string', 'max:60',
                function ($attribute, $value, $fail) use ($ignoreId) {
                    $exists = DB::table('test_definitions')
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->whereRaw('LOWER(code) = LOWER(?)', [trim((string) $value)])
                        ->exists();
                    if ($exists) {
                        $fail(__('test_definitions.code_unique'));
                    }
                },
            ],

            // El grupo puede quedar vacío: la migración lo permite y hay
            // pruebas del volcado viejo que llegan sin clasificar.
            'test_group_id' => ['nullable', 'integer', Rule::exists('test_groups', 'id')->whereNull('deleted_at')],

            'description' => ['nullable', 'string', 'max:2000'],
            'container'   => ['nullable', 'string', 'max:100'],
            'chart_unit'  => ['nullable', 'string', 'max:40'],

            // Con qué otras pruebas comparte tabla en el informe. Texto libre
            // acotado y no una FK: la familia AGRUPA pruebas, no hay una fila
            // que la represente. Vacío = página propia.
            'report_comment_group' => ['nullable', 'string', 'max:60'],

            'has_control'        => ['sometimes', 'boolean'],
            'requires_control'   => ['sometimes', 'boolean'],
            'requires_duplicate' => ['sometimes', 'boolean'],
            'is_grouped'         => ['sometimes', 'boolean'],

            // La columna es unsignedTinyInteger: pasarse de 255 revienta el
            // insert. El tope real de una medición repetida es mucho menor
            // (rigidez dieléctrica: 5 o 6), pero no hay norma que lo fije.
            'replicates' => ['nullable', 'integer', 'min:1', 'max:255'],

            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
