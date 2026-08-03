<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\AmbientLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La bitácora de condiciones ambientales de las salas.
 *
 * Una lectura por sala y por día. El sistema anterior tenía dos módulos
 * gemelos para esto (cromatografía y fisicoquímico); acá la sala es un filtro.
 *
 * Pantalla única con modal, como el resto de los registros de pocos campos:
 * cargar la lectura del día no merece navegar a otra página.
 */
class AmbientLogController extends Controller
{
    /** Cuántos días atrás mira el listado si nadie eligió un rango. */
    private const DIAS_POR_OMISION = 30;

    public function index(Request $request): Response
    {
        $desde = $request->input('from') ?: now()->subDays(self::DIAS_POR_OMISION)->toDateString();

        return Inertia::render('AmbientLogs/Index', [
            'rows' => AmbientLog::query()
                ->filter($request->merge(['from' => $desde]))
                ->orderByDesc('logged_on')
                ->orderBy('room')
                ->paginate(30)
                ->withQueryString(),
            'rooms'   => AmbientLog::ROOMS,
            'filters' => [
                'room' => $request->input('room'),
                'from' => $desde,
                'to'   => $request->input('to'),
            ],
            // Qué salas ya tienen la lectura de HOY. Es la pregunta que se hace
            // quien entra a la pantalla, y contestarla con la tabla obliga a
            // buscar la fila entre las de todo el mes.
            'today'   => AmbientLog::query()
                ->whereDate('logged_on', now()->toDateString())
                ->pluck('room')
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AmbientLog::create($this->validado($request, null) + [
            'tenant_id'  => $request->user()?->tenant_id,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', __('ambient_logs.created'));
    }

    public function update(Request $request, AmbientLog $ambient_log): RedirectResponse
    {
        $ambient_log->update($this->validado($request, $ambient_log));

        return back()->with('success', __('ambient_logs.saved'));
    }

    public function destroy(Request $request, AmbientLog $ambient_log): RedirectResponse
    {
        $ambient_log->update(['deleted_by' => $request->user()?->id]);
        $ambient_log->delete();

        return back()->with('success', __('ambient_logs.deleted'));
    }

    /**
     * @return array<string,mixed>
     */
    private function validado(Request $request, ?AmbientLog $actual): array
    {
        return $request->validate([
            'room'      => ['required', Rule::in(AmbientLog::ROOMS)],
            // Una lectura por sala y por día. La regla mira las VIVAS: si la del
            // día se dio de baja por mal cargada, el día vuelve a estar libre.
            //
            // Se compara con `whereDate` y no con `Rule::unique`: la columna es
            // de fecha, pero el cast de Eloquent la escribe con hora en SQLite,
            // así que la comparación textual del `unique` no encontraba nada y
            // dejaba entrar la segunda lectura del mismo día.
            'logged_on' => [
                'required', 'date', 'before_or_equal:today',
                function (string $atributo, $valor, $fail) use ($request, $actual) {
                    $existe = AmbientLog::query()
                        ->where('room', $request->input('room'))
                        ->whereDate('logged_on', $valor)
                        ->when($actual, fn ($q) => $q->whereKeyNot($actual->id))
                        ->exists();

                    if ($existe) {
                        $fail(__('ambient_logs.errors.duplicate_day'));
                    }
                },
            ],
            // Rangos con sentido físico: una humedad de 150 % o una presión de
            // 3 hPa son un error de tipeo, no una medición.
            'temperature_c' => ['nullable', 'numeric', 'between:-20,60'],
            'humidity_pct'  => ['nullable', 'numeric', 'between:0,100'],
            'pressure_hpa'  => ['nullable', 'numeric', 'between:800,1100'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ], [
            'logged_on.before_or_equal' => __('ambient_logs.errors.future'),
        ]);
    }
}
