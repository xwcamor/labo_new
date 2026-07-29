<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\Analyte;
use App\Models\QcChart;
use App\Models\QcPoint;
use App\Models\TestDefinition;
use App\Services\Lab\WestgardEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Cartas de control: los dos submenús "Límite de Tendencias" y "Tendencias"
 * del sistema viejo, unificados.
 *
 * Eran dos pantallas separadas que hablaban de lo mismo: una editaba los cinco
 * límites y la otra los dibujaba. Peor: no había ninguna clave foránea entre
 * ellas. La pantalla de tendencias hacía `PatronTendence.find(id_de_la_prueba)`
 * y confiaba en que el id de la tabla de límites coincidiera con el id de la
 * prueba. Cualquier desalineación cruzaba los límites de una prueba con los
 * datos de otra, en silencio y sin que nada lo detectara.
 *
 * Acá la carta es un registro con su clave foránea, y el gráfico es su ficha.
 */
class QcChartController extends Controller
{
    use \App\Traits\BuildsRecordAudit;

    public function index(Request $request)
    {
        $query = QcChart::query()
            ->with(['definition:id,slug,code,name', 'field:id,code,label,unit', 'analyte:id,code,name,unit'])
            ->withCount('points');

        $query->when($request->filled('test_definition'), fn ($q) => $q->whereHas(
            'definition',
            fn ($d) => $d->where('slug', $request->test_definition)
        ));

        $query->when($request->filled('is_active'), fn ($q) => $q->where(
            'is_active',
            filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
        ));

        return Inertia::render('QcCharts/Index', [
            'charts'  => $query->orderBy('test_definition_id')->orderBy('id')
                ->paginate(25)->withQueryString(),
            'tests'   => TestDefinition::where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'slug', 'code', 'name']),
            'filters' => $request->only(['test_definition', 'is_active']),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('QcCharts/Form', [
            'chart'    => null,
            'tests'    => $this->testsWithFields(),
            'analytes' => Analyte::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'unit']),
            'modes'    => QcChart::REPEATABILITY_MODES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $chart = QcChart::create($this->validated($request) + [
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('lab_management.qc_charts.show', $chart)
            ->with('success', __('qc_charts.created'));
    }

    /**
     * La ficha ES el gráfico: la serie de puntos con sus cinco líneas y el
     * veredicto de cada uno.
     */
    public function show(Request $request, QcChart $qc_chart)
    {
        $qc_chart->load([
            'definition:id,slug,code,name,chart_unit',
            'field:id,code,label,unit',
            'analyte:id,code,name,unit',
        ]);

        $points = $qc_chart->points()
            ->with('row:id,worksheet_id,kind')
            ->orderBy('measured_at')->orderBy('id')
            ->get();

        return Inertia::render('QcCharts/Show', [
            'chart'  => $qc_chart,
            // Cambiar los límites de una carta reescribe el veredicto de todo
            // lo que venga después: quién los movió y cuándo tiene que quedar
            // a la vista, no solo en la tabla de auditoría.
            'recordAudit' => $this->recordAuditMeta($qc_chart),
            'activity'    => $this->recordActivity($qc_chart, $request),
            'limits' => $qc_chart->limits(),
            'points' => $points,
            // Las reglas se mandan al front para poder explicarlas en el
            // gráfico. El veredicto NO se recalcula acá: viene guardado en cada
            // punto, congelado contra los límites que regían ese día.
            'rules'  => WestgardEvaluator::RULES,
            'duplicates' => $qc_chart->duplicates()
                ->orderByDesc('measured_at')->limit(50)->get(),
        ]);
    }

    public function edit(QcChart $qc_chart)
    {
        return Inertia::render('QcCharts/Form', [
            'chart'    => $qc_chart->load(['definition:id,slug,name', 'field:id,code,label']),
            'tests'    => $this->testsWithFields(),
            'analytes' => Analyte::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'unit']),
            'modes'    => QcChart::REPEATABILITY_MODES,
        ]);
    }

    public function update(Request $request, QcChart $qc_chart): RedirectResponse
    {
        $qc_chart->update($this->validated($request, $qc_chart));

        return redirect()
            ->route('lab_management.qc_charts.show', $qc_chart)
            ->with('success', __('qc_charts.updated'));
    }

    /**
     * Excluir un punto del cálculo, con motivo.
     *
     * No se borra. Un patrón que se salió de control es información: el
     * laboratorio tiene que poder mostrar que lo detectó, qué hizo y por qué lo
     * descartó. Borrarlo dejaría una carta impecable que no prueba nada.
     */
    public function excludePoint(Request $request, QcChart $qc_chart, QcPoint $point): RedirectResponse
    {
        abort_unless($point->qc_chart_id === $qc_chart->id, 404);

        $data = $request->validate([
            'is_excluded'      => ['required', 'boolean'],
            'exclusion_reason' => ['required_if:is_excluded,true', 'nullable', 'string', 'min:3', 'max:500'],
        ]);

        $point->update($data);

        return back()->with('success', __('qc_charts.point_updated'));
    }

    public function destroy(QcChart $qc_chart): RedirectResponse
    {
        $qc_chart->update(['deleted_by' => auth()->id()]);
        $qc_chart->delete();

        return redirect()
            ->route('lab_management.qc_charts.index')
            ->with('success', __('qc_charts.deleted'));
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request, ?QcChart $chart = null): array
    {
        return $request->validate([
            'test_definition_id'   => ['required', 'integer', Rule::exists('test_definitions', 'id')],
            'test_field_id'        => ['nullable', 'integer', Rule::exists('test_fields', 'id')],
            'analyte_id'           => ['nullable', 'integer', Rule::exists('analytes', 'id')],
            'label'                => ['nullable', 'string', 'max:150'],
            'control_lot'          => ['nullable', 'string', 'max:100'],

            'lcl'                  => ['nullable', 'numeric'],
            'lwl'                  => ['nullable', 'numeric'],
            'center'               => ['nullable', 'numeric'],
            'uwl'                  => ['nullable', 'numeric'],
            'ucl'                  => ['nullable', 'numeric'],

            'sd'                   => ['nullable', 'numeric', 'min:0'],
            'is_derived'           => ['boolean'],
            // Se exige que la alerta esté por dentro del control. Al revés la
            // carta queda al revés y no avisa nunca.
            'warn_sigma'           => ['nullable', 'numeric', 'min:0.5', 'max:10', 'lt:action_sigma'],
            'action_sigma'         => ['nullable', 'numeric', 'min:0.5', 'max:10'],

            'repeatability_limit'  => ['nullable', 'numeric', 'min:0'],
            'repeatability_mode'   => ['nullable', Rule::in(QcChart::REPEATABILITY_MODES)],

            'effective_from'       => ['nullable', 'date'],
            'effective_to'         => ['nullable', 'date', 'after_or_equal:effective_from'],

            'comment'              => ['nullable', 'string', 'max:2000'],
            'is_active'            => ['boolean'],
        ]);
    }

    /** Las pruebas con sus columnas, para poder elegir sobre qué se controla. */
    private function testsWithFields()
    {
        return TestDefinition::where('is_active', true)
            ->with(['fields:id,test_definition_id,code,label,unit,type,role'])
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'code', 'name']);
    }
}
