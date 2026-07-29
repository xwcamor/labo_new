<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Sample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * El buscador global del topbar.
 *
 * Busca lo que el laboratorio de verdad cita por teléfono:
 *
 *   · MUESTRAS por su correlativo ("2026-0695") — es el identificador que el
 *     cliente lee del papel. El resultado lleva a la ficha de su ENTREGA, que
 *     es donde la muestra se trabaja.
 *   · EQUIPOS por serie, TAG o nombre.
 *   · CLIENTES por nombre.
 *
 * Quedó un tiempo devolviendo `transformers` vacío —el stub de la migración
 * desde TrafoDex— y el buscador solo encontraba clientes: la barra prometía
 * "buscar un trafo" y no buscaba ninguno.
 *
 * Seguridad: los modelos auto-filtran por workspace (BelongsToTenant*), y cada
 * bloque respeta el permiso de vista de su módulo — quien no puede abrir
 * recepciones tampoco ve muestras en el buscador.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['equipment' => [], 'samples' => [], 'customers' => []]);
        }

        $user = $request->user();
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $term = '%' . $q . '%';

        $equipment = [];
        if ($user->can('equipment.view')) {
            $equipment = Equipment::query()
                ->with('customer:id,name')
                ->where(fn ($w) => $w
                    ->where('serial', $like, $term)
                    ->orWhere('tag', $like, $term)
                    ->orWhere('name', $like, $term))
                ->orderBy('name')
                ->limit(6)
                ->get(['id', 'slug', 'name', 'serial', 'tag', 'customer_id'])
                ->map(fn ($e) => [
                    'slug'     => $e->slug,
                    'name'     => $e->name,
                    'serial'   => $e->serial,
                    'tag'      => $e->tag,
                    'customer' => $e->customer?->name,
                ])
                ->all();
        }

        $samples = [];
        if ($user->can('receptions.view')) {
            $samples = Sample::query()
                ->with(['reception:id,slug,customer_id', 'reception.customer:id,name'])
                ->where('code', $like, $term)
                ->orderByDesc('id')
                ->limit(6)
                ->get(['id', 'code', 'reception_id'])
                ->map(fn ($s) => [
                    'code'      => $s->code,
                    'reception' => $s->reception?->slug,
                    'customer'  => $s->reception?->customer?->name,
                ])
                // Una muestra cuya entrega se dio de baja no tiene adónde
                // llevar: se omite en vez de fabricar un enlace roto.
                ->filter(fn ($s) => $s['reception'] !== null)
                ->values()
                ->all();
        }

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

        return response()->json(compact('equipment', 'samples', 'customers'));
    }
}
