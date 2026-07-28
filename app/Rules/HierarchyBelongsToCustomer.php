<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * La ubicación es de ese cliente, el área es de esa ubicación y la subestación
 * es de esa área.
 *
 * El formulario encadena los desplegables, así que en el uso normal esto no
 * puede fallar. Se valida igual porque el encadenado vive en el navegador: un
 * envío armado a mano, o el mismo formulario después de cambiar el cliente sin
 * limpiar lo elegido, colgaría el equipo de la subestación de otra empresa. En
 * el sistema anterior ese era el camino por el que una muestra terminaba en el
 * transformador equivocado.
 */
class HierarchyBelongsToCustomer implements ValidationRule
{
    /** @param 'location'|'area'|'substation' $nivel */
    public function __construct(
        private readonly Request $request,
        private readonly string $nivel,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        [$tabla, $columnaPadre, $campoPadre] = match ($this->nivel) {
            'location'   => ['customer_locations',   'customer_id',          'customer_id'],
            'area'       => ['customer_areas',       'customer_location_id', 'customer_location_id'],
            'substation' => ['customer_substations', 'customer_area_id',     'customer_area_id'],
        };

        $padre = $this->request->input($campoPadre);

        // Sin el nivel de arriba no hay contra qué comparar. El propio
        // formulario no deja llegar acá (el desplegable está deshabilitado
        // hasta elegir el padre); esto es el respaldo.
        if ($padre === null || $padre === '') {
            $fail(__('equipment.hierarchy_needs_parent'));

            return;
        }

        $ok = DB::table($tabla)
            ->whereNull('deleted_at')
            ->where('id', $value)
            ->where($columnaPadre, $padre)
            ->exists();

        if (! $ok) {
            $fail(__('equipment.hierarchy_mismatch'));
        }
    }
}
