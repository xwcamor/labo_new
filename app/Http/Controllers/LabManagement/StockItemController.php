<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\ReportCatalog;
use App\Models\StockItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El catálogo de artículos del almacén.
 *
 * Siete campos: pantalla única con modal, como el resto de los registros
 * chicos. Lo que agrega sobre el listado del sistema anterior es la cuenta de
 * lo PRESTADO y lo DISPONIBLE por artículo — allá la columna "Stock" era el
 * número tipeado a mano y nada decía cuánto de eso estaba afuera.
 */
class StockItemController extends Controller
{
    public function index(Request $request): Response
    {
        $articulos = StockItem::query()
            ->filter($request)
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        // Lo prestado se resuelve para la PÁGINA que se muestra, no artículo por
        // artículo dentro del bucle: con 200 artículos, preguntar uno por uno son
        // 200 consultas para pintar una columna.
        $prestado = $this->prestadoPorArticulo($articulos->pluck('id')->all());

        $articulos->getCollection()->transform(function (StockItem $articulo) use ($prestado) {
            $fuera = $prestado[$articulo->id] ?? 0;

            return array_merge($articulo->toArray(), [
                'on_loan'   => $fuera,
                'available' => $articulo->on_hand - $fuera,
                'is_low'    => $articulo->min_qty !== null && ($articulo->on_hand - $fuera) <= $articulo->min_qty,
            ]);
        });

        return Inertia::render('StockItems/Index', [
            'rows'    => $articulos,
            'units'   => ReportCatalog::query()
                ->where('kind', ReportCatalog::KIND_STOCK_UNIT)
                ->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')
                ->pluck('name'),
            'filters' => [
                'q'      => $request->input('q'),
                'unit'   => $request->input('unit'),
                'active' => $request->input('active'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        StockItem::create($this->validado($request, null) + [
            'tenant_id'  => $request->user()?->tenant_id,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', __('stock_items.created'));
    }

    public function update(Request $request, StockItem $stock_item): RedirectResponse
    {
        $stock_item->update($this->validado($request, $stock_item));

        return back()->with('success', __('stock_items.saved'));
    }

    public function destroy(Request $request, StockItem $stock_item): RedirectResponse
    {
        // Un artículo con material afuera no se da de baja: el préstamo abierto
        // quedaría apuntando a un artículo que ya no está en el catálogo y nadie
        // volvería a preguntarse por él. Primero vuelve, después se archiva.
        if ($stock_item->onLoan() > 0) {
            return back()->withErrors([
                'delete' => __('stock_items.errors.on_loan', ['n' => $stock_item->onLoan()]),
            ]);
        }

        $stock_item->update(['deleted_by' => $request->user()?->id]);
        $stock_item->delete();

        return back()->with('success', __('stock_items.deleted'));
    }

    /**
     * Cuántas unidades de cada artículo están afuera, en UNA consulta.
     *
     * @param  array<int>  $ids
     * @return array<int,int>
     */
    private function prestadoPorArticulo(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return \App\Models\StockLoanLine::query()
            ->whereIn('stock_loan_lines.stock_item_id', $ids)
            ->whereNull('stock_loan_lines.deleted_at')
            ->whereHas('loan', fn ($q) => $q->where('status', \App\Models\StockLoan::STATUS_OPEN))
            ->with('returns:id,stock_loan_line_id,qty')
            ->get(['id', 'stock_item_id', 'qty'])
            ->groupBy('stock_item_id')
            ->map(fn ($lineas) => (int) $lineas->sum(fn ($l) => max(0, $l->qty - $l->returns->sum('qty'))))
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    private function validado(Request $request, ?StockItem $actual): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('stock_items', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $request->user()?->tenant_id)->whereNull('deleted_at'))
                    ->ignore($actual?->id),
            ],
            'name'     => ['required', 'string', 'max:160'],
            'unit'     => ['nullable', 'string', 'max:40'],
            // La existencia no puede ser negativa: un almacén con -3 frascos no
            // es un dato, es un error de tipeo.
            'on_hand'  => ['required', 'integer', 'min:0'],
            'min_qty'  => ['nullable', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:80'],
            'is_active' => ['boolean'],
        ]);
    }
}
