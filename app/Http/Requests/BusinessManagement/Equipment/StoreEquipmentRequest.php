<?php

namespace App\Http\Requests\BusinessManagement\Equipment;

use App\Http\Requests\BusinessManagement\Equipment\Concerns\EquipmentFieldRules;
use App\Http\Requests\Concerns\DerivesAttributesFromLang;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de un equipo.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ UN EQUIPO SE IDENTIFICA POR SERIE + TAG, NO POR NOMBRE NI POR CÓDIGO     │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Este formulario venía del scaffold de catálogos (Marca, Tipo de aceite) y
 * arrastraba dos reglas que no son de este dominio:
 *
 *   · Un campo CÓDIGO derivado del nombre. La tabla `equipment` no tiene esa
 *     columna —nunca la tuvo— así que la regla consultaba `LOWER(code)` y
 *     rompía con "no existe la columna «code»" al guardar. Un transformador se
 *     identifica por su número de serie y su tag; un código inventado a partir
 *     del nombre no aporta nada y no se guardaba en ningún lado.
 *
 *   · NOMBRE único dentro del workspace. Eso impide que dos clientes distintos
 *     tengan cada uno su "Transformador Principal", que es lo normal: el
 *     laboratorio atiende a muchas empresas y los nombres se repiten. El
 *     sistema anterior tampoco lo exigía.
 *
 * La unicidad real es la que ya tiene el índice de la tabla —(workspace, serie,
 * tag), sin contar los borrados— y es la misma que validaba el sistema
 * anterior (`validates_uniqueness_of :num_tag, scope: [:num_serie]`). Acá se
 * valida en PHP para que el analista vea el mensaje en el campo en vez de un
 * error de base de datos.
 */
class StoreEquipmentRequest extends FormRequest
{
    use DerivesAttributesFromLang;
    use EquipmentFieldRules;

    protected $attributeNamespace = 'equipment';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->equipmentRules();
    }
}
