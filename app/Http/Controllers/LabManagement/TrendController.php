<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\Analyte;
use App\Models\Equipment;
use App\Models\Result;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La tendencia de un EQUIPO del cliente en el tiempo.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ ESTO NO ES LA CARTA DE CONTROL, Y LA DIFERENCIA IMPORTA                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 * La carta de control (`qc_charts`) mira hacia ADENTRO: el patrón y el
 * duplicado dicen si el método está midiendo bien hoy. Esta pantalla mira
 * hacia AFUERA: cómo evoluciona el aceite de un transformador del cliente a lo
 * largo de los años. Es el producto analítico que se le vende —ver venir la
 * falla incipiente antes de que el equipo salga de servicio—, y es la única de
 * las dos que le interesa al dueño del transformador.
 *
 * En el sistema anterior vivía en `Templates::TendencesController`, que armaba
 * la serie leyendo `lab_sub_details` y filtrando por el id de la columna de la
 * plantilla —61 a 69 para los nueve gases, escritos a mano en el controlador—.
 * Acá se lee de `results`, que ya está indexada por (equipo, fecha) y guarda el
 * límite de norma congelado con cada valor.
 *
 * Los límites NO se editan aparte (el viejo tenía un CRUD `patron_tendences`
 * solo para eso): cada resultado ya trae el suyo, el que regía el día que se
 * midió. Un límite editable a posteriori reescribiría la historia del gráfico.
 */
class TrendController extends Controller
{
    public function index(Request $request): Response
    {
        $equipo = $this->equipoElegido($request);

        return Inertia::render('Trends/Index', [
            // El selector no trae los 2000 equipos: solo los que TIENEN
            // resultados, que son los únicos que pueden dibujar una tendencia.
            'equipment' => $this->equiposConResultados($request),
            'selected'  => $equipo ? [
                'id'     => $equipo->id,
                'slug'   => $equipo->slug,
                'name'   => $equipo->name,
                'serial' => $equipo->serial,
                'tag'    => $equipo->tag,
            ] : null,
            'analytes' => $equipo ? $this->analitosDelEquipo($equipo) : [],
            'series'   => $equipo ? $this->series($equipo, $request) : [],
            'filters'  => [
                'equipment' => $equipo?->slug,
                'analytes'  => $this->analitosPedidos($request),
                'q'         => $request->input('q'),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function equipoElegido(Request $request): ?Equipment
    {
        if (! $request->filled('equipment')) {
            return null;
        }

        return Equipment::where('slug', $request->input('equipment'))->first();
    }

    /** @return array<int,array<string,mixed>> */
    private function equiposConResultados(Request $request): array
    {
        return Equipment::query()
            ->whereIn('id', Result::query()->whereNotNull('equipment_id')->distinct()->pluck('equipment_id'))
            ->when($request->input('q'), function ($q, $texto) {
                $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$texto}%")
                    ->orWhere('serial', 'like', "%{$texto}%")
                    ->orWhere('tag', 'like', "%{$texto}%"));
            })
            ->with('customer:id,name')
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'slug', 'name', 'serial', 'tag', 'customer_id'])
            ->map(fn (Equipment $e) => [
                'slug'     => $e->slug,
                'name'     => $e->name,
                'serial'   => $e->serial,
                'tag'      => $e->tag,
                'customer' => $e->customer?->name,
            ])
            ->all();
    }

    /**
     * Los parámetros que ESE equipo tiene medidos, con cuántas veces.
     *
     * Ofrecer el catálogo entero obligaría a probar analito por analito cuál
     * tiene datos: de 60 parámetros, un transformador con cromatografía tiene
     * nueve.
     */
    private function analitosDelEquipo(Equipment $equipo): array
    {
        $conteos = Result::query()
            ->where('equipment_id', $equipo->id)
            ->whereNotNull('value_num')
            ->selectRaw('analyte_id, count(*) as n')
            ->groupBy('analyte_id')
            ->pluck('n', 'analyte_id');

        return Analyte::whereIn('id', $conteos->keys())
            ->orderBy('group')->orderBy('id')
            ->get(['id', 'code', 'name', 'unit', 'group', 'decimals'])
            ->map(fn (Analyte $a) => [
                'id'     => $a->id,
                'code'   => $a->code,
                'name'   => $a->name,
                'unit'   => $a->unit,
                'group'  => $a->group,
                'points' => (int) $conteos[$a->id],
            ])
            ->all();
    }

    /** @return array<int,string> códigos de analito pedidos */
    private function analitosPedidos(Request $request): array
    {
        $pedidos = $request->input('analytes', []);

        return is_array($pedidos) ? array_values(array_filter($pedidos)) : [];
    }

    /**
     * La serie de cada parámetro elegido, en orden cronológico.
     *
     * El límite de norma va POR PUNTO y no como una constante del gráfico: si
     * la norma del cuadro cambió entre dos muestras, la línea tiene que
     * reflejarlo. Cada resultado guarda el suyo desde que se materializó.
     */
    private function series(Equipment $equipo, Request $request): array
    {
        $codigos = $this->analitosPedidos($request);

        if ($codigos === []) {
            return [];
        }

        $analitos = Analyte::whereIn('code', $codigos)->get(['id', 'code', 'name', 'unit', 'decimals']);

        return $analitos->map(function (Analyte $a) use ($equipo) {
            $puntos = Result::trend($equipo->id, $a->id)
                ->whereNotNull('value_num')
                ->with('sample:id,code')
                ->get(['id', 'sample_id', 'measured_at', 'value_num', 'qualifier', 'spec_min', 'spec_max', 'spec_status'])
                ->map(fn (Result $r) => [
                    'date'   => $r->measured_at?->toDateString(),
                    'value'  => (float) $r->value_num,
                    // Un ">75" no es 75: se marca para que el gráfico lo
                    // distinga del valor medido de verdad.
                    'censored' => $r->qualifier !== null,
                    'sample' => $r->sample?->code,
                    'min'    => $r->spec_min !== null ? (float) $r->spec_min : null,
                    'max'    => $r->spec_max !== null ? (float) $r->spec_max : null,
                    'status' => $r->spec_status,
                ])
                ->values()
                ->all();

            return [
                'code'     => $a->code,
                'name'     => $a->name,
                'unit'     => $a->unit,
                'decimals' => $a->decimals,
                'points'   => $puntos,
            ];
        })->all();
    }
}
