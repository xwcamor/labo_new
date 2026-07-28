<?php

namespace App\Services\Lab;

use App\Models\Equipment;
use App\Models\Sample;
use App\Models\SpecSet;
use Illuminate\Support\Carbon;

/**
 * Qué cuadro de límites le corresponde a una muestra.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LO QUE ESTO REEMPLAZA                                                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema Rails anterior esta decisión era un árbol de `if/elsif` escrito
 * a mano sobre `transformer_oil_type_id` y la tensión, DUPLICADO en dos archivos
 * (`rem_report.rb` y `rem_report_detail.rb`) y ya divergido entre sí: uno trata
 * como vegetal los aceites 5, 6 y 9; el otro solo el 5. Un informe de girasol
 * nace sin norma asignada y solo la recibe si alguien lo edita después.
 *
 * Acá es una consulta sobre filas. Agregar un cuadro es cargar datos.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LAS TRES REGLAS DE LA RESOLUCIÓN                                         │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * 1. LA FECHA ES LA DE LA MUESTRA, NO HOY. Un ensayo de 2019 se evalúa con la
 *    norma que regía en 2019. Es lo que permite adoptar una edición nueva sin
 *    reescribir la historia.
 *
 * 2. GANA EL MÁS ESPECÍFICO. Entre un cuadro de "mineral" y uno de "mineral en
 *    conmutador", para un conmutador gana el segundo. Sin esa regla el ganador
 *    dependería del orden en que la base devuelva las filas, que es la clase de
 *    resultado impredecible que un informe no puede tener.
 *
 * 3. EL DEL WORKSPACE LE GANA AL GLOBAL. El catálogo de fábrica es la base; un
 *    laboratorio puede tener su propio criterio para un caso.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ NULO NO ES "TODO BIEN"                                                   │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Si no hay cuadro, devuelve null, y quien lo consuma tiene que decir
 * explícitamente "no se tiene referencia de valores típicos para este fluido y
 * este tipo de equipo". Rellenar con un guion en silencio —que es lo que hacía
 * el sistema anterior— convierte una falta de criterio en una aprobación.
 *
 * Es la misma lección que TrafoDex ya aprendió por las malas: un aceite sin
 * reglas devolvía "100 Excelente" y ocultaba una muestra que la norma marcaba
 * peligrosa.
 */
class SpecSetResolver
{
    /**
     * El cuadro que aplica a una muestra para una familia de ensayos.
     */
    public function forSample(Sample $sample, string $group, ?Carbon $at = null): ?SpecSet
    {
        $equipo = $sample->relationLoaded('equipment')
            ? $sample->getRelation('equipment')
            : $sample->equipment()->first();

        return $this->resolve(
            group: $group,
            oilTypeId: $sample->oil_type_id ?? $equipo?->oil_type_id,
            equipment: $equipo,
            tenantId: $sample->tenant_id,
            at: $at ?? ($sample->sampled_at ? Carbon::parse($sample->sampled_at) : null),
        );
    }

    /**
     * La resolución cruda, para cuando no hay muestra (vistas previas, editor).
     */
    public function resolve(
        string $group,
        ?int $oilTypeId = null,
        ?Equipment $equipment = null,
        ?int $tenantId = null,
        ?Carbon $at = null,
    ): ?SpecSet {
        $fecha = ($at ?? Carbon::now())->toDateString();

        $candidatos = SpecSet::withoutGlobalScopes()
            ->with('limits.analyte:id,code,unit,decimals', 'standard:id,code,edition')
            ->where('group', $group)
            ->where('is_active', true)
            ->effectiveAt($fecha)
            // El del workspace o el global; nunca el de otro workspace.
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            // Un criterio nulo significa "cualquiera", así que un cuadro
            // genérico entra igual y después pierde por especificidad.
            ->where(fn ($q) => $q->whereNull('oil_type_id')->orWhere('oil_type_id', $oilTypeId))
            ->get();

        $aplicables = $candidatos->filter(fn (SpecSet $s) => $this->applies($s, $oilTypeId, $equipment));

        if ($aplicables->isEmpty()) {
            return null;
        }

        // Primero el del workspace, después el más específico, y como último
        // desempate el orden que declaró el laboratorio. Los tres criterios
        // juntos hacen que la resolución sea determinista.
        return $aplicables
            ->sortByDesc(fn (SpecSet $s) => [
                $s->tenant_id !== null ? 1 : 0,
                $s->specificity(),
                -1 * (int) ($s->sort_order ?? 0),
            ])
            ->first();
    }

    /**
     * ¿Este cuadro aplica a este equipo?
     *
     * Un criterio nulo no filtra. Un criterio con valor exige coincidencia; y si
     * el equipo no declara el dato que el cuadro exige, el cuadro NO aplica —
     * un transformador sin tensión registrada no puede caer en el cuadro de
     * "hasta 69 kV" por descarte.
     */
    private function applies(SpecSet $set, ?int $oilTypeId, ?Equipment $equipment): bool
    {
        if ($set->oil_type_id !== null && $set->oil_type_id !== $oilTypeId) {
            return false;
        }

        if ($set->equipment_type_id !== null
            && $set->equipment_type_id !== $equipment?->equipment_type_id) {
            return false;
        }

        if ($set->service_state !== null && $set->service_state !== $equipment?->service_state) {
            return false;
        }

        if (! $this->withinRange($set->voltage_from, $set->voltage_to, $equipment?->voltage_kv_hv)) {
            return false;
        }

        if (! $this->withinRange($set->power_from, $set->power_to, $equipment?->power_mva)) {
            return false;
        }

        return true;
    }

    private function withinRange($from, $to, $value): bool
    {
        if ($from === null && $to === null) {
            return true;    // el cuadro no exige nada por este criterio
        }

        if ($value === null) {
            return false;   // el cuadro exige un dato que el equipo no tiene
        }

        $value = (float) $value;

        if ($from !== null && $value < (float) $from) {
            return false;
        }

        // El tope es INCLUSIVO: "hasta 69 kV" incluye los 69 kV. Es como está
        // escrito en el sistema anterior (`@num_ten <= 69`), y cambiarlo movería
        // de cuadro a todos los equipos que están justo en el corte.
        if ($to !== null && $value > (float) $to) {
            return false;
        }

        return true;
    }
}
