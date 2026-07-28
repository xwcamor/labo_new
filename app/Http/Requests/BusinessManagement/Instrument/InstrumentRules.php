<?php

namespace App\Http\Requests\BusinessManagement\Instrument;

use Illuminate\Support\Facades\DB;

/**
 * Reglas compartidas por Store y Update para no dejar que las dos copias se
 * desincronicen (que es lo que pasó con el scaffold: validaba `name` único
 * porque clonaba un catálogo).
 *
 * DOS DECISIONES QUE NO SON DEL SCAFFOLD:
 *
 *   1. La clave natural es `code` (PP-LA-01C), no el nombre. Dos buretas se
 *      llaman las dos "Bureta" y son equipos distintos, así que `name` NO es
 *      único. El índice de la tabla es (tenant_id, LOWER(code)) parcial sobre
 *      los no borrados: la regla lo replica tal cual, incluido ignorar los
 *      soft-deleted y el propio registro al editar.
 *
 *   2. `calibration_due_at` no puede ser anterior a `calibrated_at`. Un
 *      certificado que vence antes de emitirse es un error de tipeo, y si
 *      entra, el semáforo de calibración muestra "vencida" para siempre sin
 *      que se entienda por qué.
 */
trait InstrumentRules
{
    /** @param int|null $ignoreId id del propio registro al editar */
    protected function instrumentRules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'code' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($ignoreId) {
                    $exists = DB::table('instruments')
                        ->whereNull('deleted_at')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->whereRaw('LOWER(code) = LOWER(?)', [trim((string) $value)])
                        ->exists();
                    if ($exists) {
                        $fail(__('instruments.code_unique'));
                    }
                },
            ],

            'brand'  => ['nullable', 'string', 'max:100'],
            'model'  => ['nullable', 'string', 'max:100'],
            'serial' => ['nullable', 'string', 'max:100'],

            'calibrated_at'      => ['nullable', 'date'],
            // La comparación solo se agrega si hay con qué comparar: con
            // `calibrated_at` vacío, `after_or_equal` intenta parsear el
            // nombre del campo como fecha y revienta.
            'calibration_due_at' => array_merge(
                ['nullable', 'date'],
                $this->filled('calibrated_at') ? ['after_or_equal:calibrated_at'] : [],
            ),

            'calibration_certificate' => ['nullable', 'string', 'max:150'],
            'location'                => ['nullable', 'string', 'max:150'],
            'notes'                   => ['nullable', 'string', 'max:2000'],

            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
