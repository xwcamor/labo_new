<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Models\TransformerPreservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Sistemas de preservación del aceite del transformador.
 *
 * Conservador con membrana, tanque sellado con nitrógeno, respiración libre.
 * Es un metadato descriptivo del equipo (NO un eje del diagnóstico) y sale
 * impreso en la ficha del informe.
 *
 * La tabla, el modelo y el desplegable del formulario de equipos ya existían;
 * lo único que faltaba era la pantalla, así que hasta hoy la lista solo se
 * podía alimentar por seeder o por SQL a mano. Cuatro campos: modal, como el
 * resto de los catálogos chicos.
 *
 * Catálogo GLOBAL (sin `tenant_id`), y por eso lo administra el super: los
 * sistemas de preservación son una clasificación técnica del equipo, no una
 * preferencia de cada laboratorio. Ese gate vive en las rutas.
 */
class TransformerPreservationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('TransformerPreservations/Index', [
            'rows' => TransformerPreservation::query()
                ->when($request->input('q'), function ($q, $texto) {
                    $aguja = \App\Support\LikeQuery::contains((string) $texto);
                    $q->where(fn ($w) => $w->where('name', 'like', $aguja)->orWhere('code', 'like', $aguja));
                })
                ->withCount('equipment')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'filters' => ['q' => $request->input('q')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        TransformerPreservation::create($this->validado($request, null) + [
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', __('transformer_preservations.created'));
    }

    public function update(Request $request, TransformerPreservation $transformer_preservation): RedirectResponse
    {
        $transformer_preservation->update($this->validado($request, $transformer_preservation));

        return back()->with('success', __('transformer_preservations.saved'));
    }

    public function destroy(Request $request, TransformerPreservation $transformer_preservation): RedirectResponse
    {
        // Con equipos apuntando a esta fila, la baja los dejaría con un sistema
        // de preservación que ya no está en la lista y el informe imprimiría un
        // hueco. Para sacarla de circulación está el interruptor de activa, que
        // deja de ofrecerla en los equipos nuevos sin tocar los que ya la usan.
        $usados = $transformer_preservation->equipment()->count();

        if ($usados > 0) {
            return back()->withErrors([
                'delete' => __('transformer_preservations.errors.in_use', ['n' => $usados]),
            ]);
        }

        $transformer_preservation->update(['deleted_by' => $request->user()?->id]);
        $transformer_preservation->delete();

        return back()->with('success', __('transformer_preservations.deleted'));
    }

    /**
     * @return array<string,mixed>
     */
    private function validado(Request $request, ?TransformerPreservation $actual): array
    {
        return $request->validate([
            // El nombre no se repite. La comparación va en minúsculas porque el
            // índice único de la tabla es sobre `unaccent(lower(name))`: con un
            // `unique` textual, «Sellado» y «sellado» pasaban la validación y
            // el rechazo llegaba como error 500 de la base.
            'name' => [
                'required', 'string', 'max:120',
                function (string $atributo, $valor, $fail) use ($actual) {
                    $existe = TransformerPreservation::query()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $valor))])
                        ->when($actual, fn ($q) => $q->whereKeyNot($actual->id))
                        ->exists();

                    if ($existe) {
                        $fail(__('transformer_preservations.errors.duplicate'));
                    }
                },
            ],
            'code'       => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['boolean'],
        ]);
    }
}
