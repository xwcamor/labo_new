<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SearchController — buscador global del topbar. Devuelve coincidencias de
 * transformadores (por serie/TAG/marca/subestación o por ESTADO de salud) y
 * clientes (por nombre) del workspace actual.
 *
 * Seguridad: los modelos ya auto-filtran por tenant (BelongsToTenant) y por
 * clientes asignados; además se respeta el permiso de vista de cada módulo.
 */
class SearchController extends Controller
{
    /** health_rating (0-4) → token de color y clave de condición del semáforo. */
    private const RATING = [
        4 => ['green', 'muy_bueno'], 3 => ['lime', 'bueno'], 2 => ['yellow', 'medio'],
        1 => ['orange', 'malo'], 0 => ['red', 'muy_malo'],
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['transformers' => [], 'customers' => []]);
        }

        $user = $request->user();
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $term = '%' . $q . '%';

        // Fase 1: el buscador global pasa a indexar `equipment` y `samples`.
        $transformers = [];

        $customers = [];
        if ($user->can('customers.view')) {
            $customers = Customer::query()
                ->where('name', $like, $term)
                ->orderBy('name')
                ->limit(6)
                ->get(['id', 'slug', 'name'])
                ->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->name])
                ->all();
        }

        return response()->json(compact('transformers', 'customers'));
    }

}
