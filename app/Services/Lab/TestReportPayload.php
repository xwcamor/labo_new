<?php

namespace App\Services\Lab;

use App\Models\Result;
use App\Models\Sample;
use App\Models\SampleTest;
use Illuminate\Support\Collection;

/**
 * Lo que dice el informe de ensayo de UNA muestra.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL LÍMITE NO SE VUELVE A CALCULAR AL IMPRIMIR                            │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Es la diferencia que más importa respecto del informe anterior. Allá el
 * límite era un TEXTO guardado en la fila del informe ("16 (máximo)") y la
 * vista lo convertía a número en el momento de imprimir:
 *
 *     @orientation = (rem_report_detail.aci_ori.strip.delete! "(máximo)").to_f
 *     if rem_report_detail.aci_val.to_f <= @orientation   # verde
 *
 * `String#delete!` devuelve **nil** cuando no encontró nada que borrar. En el
 * cuadro "De voltaje · Mineral" el límite del acetileno está cargado como "16",
 * sin la palabra "(máximo)": ahí `delete!` devuelve nil, `nil.to_f` es 0.0, y el
 * informe imprime 16 como valor de orientación mientras colorea el resultado
 * contra 0 ppm. O sea: el número que el cliente lee y el criterio con el que se
 * lo juzga son distintos, y nada avisa.
 *
 * Acá el veredicto ya viene decidido y CONGELADO en el resultado
 * (`spec_status`, `spec_min`, `spec_max`, `spec_source`), escrito cuando se
 * validó la hoja. El informe no compara nada: lee. El texto del límite se ARMA
 * a partir de los mismos números con los que se decidió, así que no pueden
 * discrepar.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ SIN CRITERIO NO ES "CUMPLE"                                              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Un `spec_status` nulo significa que para ese parámetro no había cuadro de
 * límites aplicable. Se imprime como raya, nunca como conforme, y el pie del
 * informe lo dice. Un informe que muestra en negro un valor que nadie comparó
 * contra nada es peor que uno incompleto.
 *
 * Solo entran resultados de pruebas VALIDADAS: el informe es lo que el
 * laboratorio firma.
 */
class TestReportPayload
{
    /**
     * @return array<string,mixed>
     */
    public function forSample(Sample $sample): array
    {
        $sample->loadMissing([
            'reception.customer:id,name',
            'reception.sampler:id,name',
            'equipment.customer:id,name',
            'equipment.equipmentType:id,name',
            'equipment.oilType:id,name',
            'equipment.location:id,name',
            'equipment.substation:id,name',
            'tests.definition.group:id,name,sort_order',
        ]);

        return [
            'sample'    => $this->cabeceraMuestra($sample),
            'customer'  => $this->cabeceraCliente($sample),
            'equipment' => $this->cabeceraEquipo($sample),
            'sections'  => $this->secciones($sample),
            'notes'     => $this->notas($sample),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function cabeceraMuestra(Sample $sample): array
    {
        return [
            'code'         => $sample->code,
            'year'         => $sample->year,
            'number'       => $sample->number,
            'sampled_at'   => $sample->sampled_at,
            'received_at'  => $sample->reception?->received_at,
            'service_order' => $sample->reception?->service_order,
            'reception'    => $sample->reception?->code,
            'sampler'      => $sample->reception?->sampler?->name ?? $sample->reception?->sampler_name,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function cabeceraCliente(Sample $sample): array
    {
        // El cliente del informe es el de la RECEPCIÓN, no el del equipo: es
        // quien encargó el ensayo y a quien se le factura. Coinciden salvo que
        // alguien haya movido el equipo de empresa después de la entrega, y en
        // ese caso el informe tiene que seguir diciendo quién lo pidió.
        $customer = $sample->reception?->customer ?? $sample->equipment?->customer;

        return [
            'name' => $customer?->name,
            'id'   => $customer?->id,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function cabeceraEquipo(Sample $sample): array
    {
        $e = $sample->equipment;

        if (! $e) {
            return ['missing' => true];
        }

        return [
            'missing'    => false,
            'name'       => $e->name,
            'serial'     => $e->serial,
            'tag'        => $e->tag,
            'type'       => $e->equipmentType?->name,
            'oil_type'   => $e->oilType?->name,
            'location'   => $e->location?->name,
            'substation' => $e->substation?->name,
            'voltage_hv' => $e->voltage_kv_hv,
            'voltage_lv' => $e->voltage_kv_lv,
            'power_mva'  => $e->power_mva,
            'year'       => $e->manufacture_year,
        ];
    }

    /**
     * Una sección por prueba, con sus filas de resultado.
     *
     * @return array<int,array<string,mixed>>
     */
    private function secciones(Sample $sample): array
    {
        // Solo las pruebas que se validaron o ya se informaron. Una prueba
        // pendiente o en proceso no tiene resultado firmado, y publicarla como
        // sección vacía sugiere que se midió y dio cero.
        $pruebas = $sample->tests
            ->whereIn('status', [SampleTest::STATUS_VALIDATED, SampleTest::STATUS_REPORTED])
            ->sortBy(fn ($t) => [$t->definition?->group?->sort_order ?? 999, $t->definition?->sort_order ?? 999]);

        if ($pruebas->isEmpty()) {
            return [];
        }

        $resultados = Result::query()
            ->where('sample_id', $sample->id)
            ->whereIn('test_definition_id', $pruebas->pluck('test_definition_id'))
            ->with(['field:id,label,unit,decimals,sort_order,report_visible', 'analyte:id,name'])
            ->orderBy('test_definition_id')
            ->get()
            ->groupBy('test_definition_id');

        $secciones = [];
        $item = 0;

        foreach ($pruebas as $prueba) {
            $filas = $this->filas($resultados->get($prueba->test_definition_id, collect()), $item);

            if ($filas === []) {
                continue;
            }

            $norma = $this->normaDelEnsayo($sample, $prueba->test_definition_id);

            $secciones[] = [
                'test'   => $prueba->definition?->name,
                'code'   => $prueba->definition?->code,
                'group'  => $prueba->definition?->group?->name,
                'method' => $norma['label'],
                'accreditation' => $norma['flag'],
                'rows'   => $filas,
            ];
        }

        return $secciones;
    }

    /**
     * Con qué NORMA se corrió el ensayo, y si ese método está acreditado.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ LA NORMA DEL MÉTODO NO ES LA NORMA DEL CRITERIO                      │
     * └──────────────────────────────────────────────────────────────────────┘
     * Son dos cosas distintas y el informe tiene que decir la primera:
     *
     *   · ASTM D974 es CÓMO se mide el número ácido.
     *   · IEEE C57.106 es CONTRA QUÉ se juzga el valor obtenido.
     *
     * La columna "Norma" del informe del laboratorio siempre fue la del método,
     * y sale de la columna "Norma" de la hoja de trabajo: la eligió el analista
     * al correr el ensayo, así que es la que de verdad se usó y no la que
     * debería haberse usado. El criterio va en el pie, una vez.
     *
     * La MARCA DE ACREDITACIÓN viaja con la opción elegida
     * (`test_field_options.accreditation_flag`): dentro de una misma prueba hay
     * métodos acreditados y no acreditados, y el cliente tiene derecho a saber
     * cuál le corrieron. Es un requisito de la ISO 17025, no un adorno.
     *
     * @return array{label: ?string, flag: ?string}
     */
    private function normaDelEnsayo(Sample $sample, ?int $testDefinitionId): array
    {
        if (! $testDefinitionId) {
            return ['label' => null, 'flag' => null];
        }

        $fila = \App\Models\WorksheetValue::query()
            ->join('worksheet_rows', 'worksheet_rows.id', '=', 'worksheet_values.worksheet_row_id')
            ->join('test_fields', 'test_fields.id', '=', 'worksheet_values.test_field_id')
            ->leftJoin('test_field_options', 'test_field_options.id', '=', 'worksheet_values.option_id')
            ->where('worksheet_rows.sample_id', $sample->id)
            ->where('test_fields.test_definition_id', $testDefinitionId)
            ->where('test_fields.role', 'standard')
            ->whereNull('worksheet_rows.deleted_at')
            ->orderByDesc('worksheet_values.id')
            ->first([
                'test_field_options.value as opcion',
                'test_field_options.accreditation_flag as flag',
                'worksheet_values.value_text as texto',
            ]);

        return [
            'label' => $fila?->opcion ?? $fila?->texto,
            'flag'  => $fila?->flag,
        ];
    }

    /**
     * @param  Collection<int,Result> $resultados
     * @return array<int,array<string,mixed>>
     */
    private function filas(Collection $resultados, int &$item): array
    {
        $filas = [];

        $ordenados = $resultados
            // La columna decide si se publica. Es un dato de la plantilla
            // (`report_visible`), no una lista de banderas por parámetro como
            // las 13 columnas `*_display` de la tabla del informe anterior.
            ->filter(fn (Result $r) => $r->field?->report_visible ?? true)
            ->sortBy(fn (Result $r) => $r->field?->sort_order ?? 999);

        foreach ($ordenados as $resultado) {
            $filas[] = [
                'item'     => ++$item,
                'analyte'  => $resultado->analyte?->name ?? $resultado->field?->label,
                'unit'     => $resultado->unit ?? $resultado->field?->unit,
                // De dónde salió el LÍMITE (el cuadro de aceptación aplicado).
                // No es la norma del método: ver `normaDelEnsayo`.
                'criterion' => $resultado->spec_source,
                'value'    => $this->valor($resultado),
                'limit'    => $this->limite($resultado),
                'status'   => $resultado->spec_status,
            ];
        }

        return $filas;
    }

    /**
     * El resultado, con su signo de censura.
     *
     * ">75 kV" no es 75: es "el equipo llegó a su tope sin que el aceite
     * rompiera". El sistema anterior guardaba el signo dentro del texto del
     * valor y lo perdía al convertir a número, así que el informe decía 75.
     */
    private function valor(Result $resultado): string
    {
        if ($resultado->value_num === null) {
            return (string) ($resultado->value_text ?? '—');
        }

        $decimales = $resultado->field?->decimals;
        $numero = $decimales !== null
            ? number_format((float) $resultado->value_num, (int) $decimales, '.', '')
            : rtrim(rtrim(number_format((float) $resultado->value_num, 6, '.', ''), '0'), '.');

        return match ($resultado->qualifier) {
            'gt' => '> ' . $numero,
            'lt' => '< ' . $numero,
            default => $numero,
        };
    }

    /**
     * El "valor de orientación" que lee el cliente.
     *
     * Se ARMA con los mismos números contra los que se decidió el veredicto
     * (`spec_min` / `spec_max`, congelados en el resultado), en vez de guardarse
     * como una frase suelta que después hay que volver a interpretar. Es lo que
     * hace imposible que el número impreso y el criterio aplicado difieran.
     */
    private function limite(Result $resultado): string
    {
        $min = $resultado->spec_min;
        $max = $resultado->spec_max;

        if ($min === null && $max === null) {
            return '—';
        }

        $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 6, '.', ''), '0'), '.');

        if ($min !== null && $max !== null) {
            return $n($min) . ' – ' . $n($max);
        }

        return $max !== null
            ? $n($max) . ' ' . __('reports.limit_max')
            : $n($min) . ' ' . __('reports.limit_min');
    }

    /**
     * Las advertencias que el informe tiene que llevar impresas.
     *
     * @return array<int,string>
     */
    private function notas(Sample $sample): array
    {
        $notas = [];

        $sinCriterio = Result::where('sample_id', $sample->id)->whereNull('spec_status')->count();

        if ($sinCriterio > 0) {
            $notas[] = __('reports.note_no_criteria', ['count' => $sinCriterio]);
        }

        $pendientes = $sample->tests
            ->whereIn('status', [SampleTest::STATUS_PENDING, SampleTest::STATUS_IN_PROGRESS])
            ->count();

        if ($pendientes > 0) {
            // Que falten ensayos no impide emitir —el cliente suele pedir el
            // parcial—, pero tiene que estar dicho en el papel.
            $notas[] = __('reports.note_pending', ['count' => $pendientes]);
        }

        if (! $sample->equipment_id) {
            $notas[] = __('reports.note_no_equipment');
        }

        return $notas;
    }
}
