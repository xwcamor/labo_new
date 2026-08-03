<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\StockItem;
use App\Models\StockLoan;
use App\Models\StockLoanLine;
use App\Models\StockReturn;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Préstamos del almacén: quién se llevó qué, y qué falta volver.
 *
 * El listado y el alta viven en la misma pantalla que el sistema anterior
 * ("Seguimiento de Equipos"); la ficha del préstamo es donde se registran las
 * devoluciones, que pueden ser parciales y varias por línea.
 *
 * Las tres reglas que el sistema anterior NO tenía y acá se imponen:
 * prestatario obligatorio, no se puede prestar más de lo disponible, y no se
 * puede devolver más de lo que falta. El porqué de cada una está en la
 * migración `create_stock_tables`.
 */
class StockLoanController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('StockLoans/Index', [
            'rows' => StockLoan::query()
                ->filter($request)
                ->with(['borrower:id,name', 'lines.item:id,slug,name,code,unit', 'lines.returns:id,stock_loan_line_id,qty'])
                ->orderByDesc('loaned_on')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (StockLoan $prestamo) => $this->parted($prestamo)),
            'items'    => $this->articulosDisponibles(),
            // Los del workspace, para elegir al prestatario sin escribir su
            // nombre. El de afuera se escribe; el de adentro se elige, y así el
            // préstamo queda atado a una persona real del sistema.
            'users'    => User::query()
                ->when($request->user()?->tenant_id, fn ($q, $t) => $q->where('tenant_id', $t))
                ->orderBy('name')
                ->get(['id', 'name']),
            'statuses' => StockLoan::STATUSES,
            'filters'  => [
                'status' => $request->input('status'),
                'q'      => $request->input('q'),
                'item'   => $request->input('item'),
                'from'   => $request->input('from'),
                'to'     => $request->input('to'),
            ],
        ]);
    }

    public function show(StockLoan $stock_loan): Response
    {
        $stock_loan->load([
            'borrower:id,name',
            'creator:id,name',
            'lines.item:id,slug,name,code,unit',
            'lines.returns' => fn ($q) => $q->with('creator:id,name')->orderBy('returned_on'),
        ]);

        return Inertia::render('StockLoans/Show', [
            'loan' => $this->parted($stock_loan, conDevoluciones: true),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validadoCabecera($request) + $this->validadoLineas($request);

        DB::transaction(function () use ($datos, $request) {
            $prestamo = StockLoan::create([
                'loaned_on'        => $datos['loaned_on'],
                'borrower_user_id' => $datos['borrower_user_id'] ?? null,
                'borrower_name'    => $datos['borrower_name'] ?? null,
                'purpose'          => $datos['purpose'] ?? null,
                'status'           => StockLoan::STATUS_OPEN,
                'tenant_id'        => $request->user()?->tenant_id,
                'created_by'       => $request->user()?->id,
            ]);

            foreach ($datos['lines'] as $linea) {
                $prestamo->lines()->create([
                    'stock_item_id' => $linea['stock_item_id'],
                    'qty'           => $linea['qty'],
                    'notes'         => $linea['notes'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('lab_management.stock_loans.index')
            ->with('success', __('stock_loans.created'));
    }

    public function update(Request $request, StockLoan $stock_loan): RedirectResponse
    {
        // Solo la cabecera. Corregir las LÍNEAS de un préstamo con devoluciones
        // ya cargadas dejaría devoluciones colgando de cantidades que cambiaron;
        // si el préstamo se cargó mal, se da de baja y se vuelve a cargar.
        $stock_loan->update($this->validadoCabecera($request));

        return back()->with('success', __('stock_loans.saved'));
    }

    public function destroy(Request $request, StockLoan $stock_loan): RedirectResponse
    {
        $stock_loan->update(['deleted_by' => $request->user()?->id]);
        $stock_loan->delete();

        return redirect()
            ->route('lab_management.stock_loans.index')
            ->with('success', __('stock_loans.deleted'));
    }

    /** Registrar una devolución sobre una línea del préstamo. */
    public function storeReturn(Request $request, StockLoan $stock_loan): RedirectResponse
    {
        $datos = $request->validate([
            'stock_loan_line_id' => [
                'required',
                Rule::exists('stock_loan_lines', 'id')
                    ->where('stock_loan_id', $stock_loan->id)
                    ->whereNull('deleted_at'),
            ],
            'returned_on' => ['required', 'date', 'before_or_equal:today'],
            'qty'         => ['required', 'integer', 'min:1'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ], [
            'returned_on.before_or_equal' => __('stock_loans.errors.future_return'),
        ]);

        $linea = StockLoanLine::with('returns')->findOrFail($datos['stock_loan_line_id']);

        // Lo que el sistema anterior dejaba pasar: devolver más de lo prestado
        // ponía el pendiente en negativo y ahí se quedaba.
        if ($datos['qty'] > $linea->pending()) {
            return back()->withErrors([
                'qty' => __('stock_loans.errors.over_return', ['n' => $linea->pending()]),
            ]);
        }

        // La devolución no puede ser anterior al préstamo.
        if ($datos['returned_on'] < $stock_loan->loaned_on->toDateString()) {
            return back()->withErrors([
                'returned_on' => __('stock_loans.errors.return_before_loan'),
            ]);
        }

        DB::transaction(function () use ($datos, $linea, $stock_loan, $request) {
            StockReturn::create([
                'stock_loan_line_id' => $linea->id,
                'returned_on'        => $datos['returned_on'],
                'qty'                => $datos['qty'],
                'notes'              => $datos['notes'] ?? null,
                'created_by'         => $request->user()?->id,
            ]);

            $stock_loan->refreshStatus();
        });

        return back()->with('success', __('stock_loans.return_saved'));
    }

    /** Dar de baja una devolución mal cargada. Vuelve a abrir el préstamo. */
    public function destroyReturn(StockLoan $stock_loan, StockReturn $stock_return): RedirectResponse
    {
        abort_unless($stock_return->line?->stock_loan_id === $stock_loan->id, 404);

        DB::transaction(function () use ($stock_return, $stock_loan) {
            $stock_return->delete();
            $stock_loan->refreshStatus();
        });

        return back()->with('success', __('stock_loans.return_deleted'));
    }

    // ─── Interno ─────────────────────────────────────────────────────────

    /**
     * El préstamo con lo que falta por línea ya resuelto.
     *
     * @return array<string,mixed>
     */
    private function parted(StockLoan $prestamo, bool $conDevoluciones = false): array
    {
        $lineas = $prestamo->lines->map(fn (StockLoanLine $linea) => [
            'id'        => $linea->id,
            'item'      => $linea->item?->only(['slug', 'name', 'code', 'unit']),
            'qty'       => $linea->qty,
            'returned'  => $linea->returned(),
            'pending'   => $linea->pending(),
            'notes'     => $linea->notes,
            'returns'   => $conDevoluciones
                ? $linea->returns->map(fn (StockReturn $d) => [
                    'id'          => $d->id,
                    'returned_on' => $d->returned_on?->toDateString(),
                    'qty'         => $d->qty,
                    'notes'       => $d->notes,
                    'by'          => $d->creator?->name,
                ])->values()
                : [],
        ])->values();

        return [
            'id'        => $prestamo->id,
            'slug'      => $prestamo->slug,
            'loaned_on' => $prestamo->loaned_on?->toDateString(),
            'borrower'  => $prestamo->borrowerLabel(),
            'borrower_user_id' => $prestamo->borrower_user_id,
            'borrower_name'    => $prestamo->borrower_name,
            'purpose'   => $prestamo->purpose,
            'status'    => $prestamo->status,
            'returned_at' => $prestamo->returned_at?->toDateTimeString(),
            'pending'   => (int) $lineas->sum('pending'),
            'created_by' => $prestamo->creator?->name,
            'lines'     => $lineas,
        ];
    }

    /** Los artículos que se pueden prestar, con lo que queda disponible. */
    private function articulosDisponibles(): array
    {
        return StockItem::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'slug', 'code', 'name', 'unit', 'on_hand'])
            ->map(fn (StockItem $articulo) => [
                'id'        => $articulo->id,
                'slug'      => $articulo->slug,
                'code'      => $articulo->code,
                'name'      => $articulo->name,
                'unit'      => $articulo->unit,
                'available' => $articulo->available(),
            ])
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    private function validadoCabecera(Request $request): array
    {
        $datos = $request->validate([
            'loaned_on'        => ['required', 'date', 'before_or_equal:today'],
            'borrower_user_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'borrower_name'    => ['nullable', 'string', 'max:120'],
            'purpose'          => ['nullable', 'string', 'max:500'],
        ], [
            'loaned_on.before_or_equal' => __('stock_loans.errors.future_loan'),
        ]);

        // El prestatario es OBLIGATORIO, y es la diferencia de fondo con el
        // sistema anterior: allá el préstamo tenía una descripción de texto
        // libre y nada más, así que el registro no servía para dar con el
        // material. Vale un usuario del sistema o un nombre escrito.
        if (empty($datos['borrower_user_id']) && blank($datos['borrower_name'] ?? null)) {
            throw ValidationException::withMessages([
                'borrower_user_id' => __('stock_loans.errors.borrower_required'),
            ]);
        }

        return $datos;
    }

    /**
     * @return array<string,mixed>
     */
    private function validadoLineas(Request $request): array
    {
        $datos = $request->validate([
            'lines'                 => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => ['required', Rule::exists('stock_items', 'id')->whereNull('deleted_at')],
            'lines.*.qty'           => ['required', 'integer', 'min:1'],
            'lines.*.notes'         => ['nullable', 'string', 'max:255'],
        ]);

        // No se presta más de lo que queda en el estante. Se suma POR ARTÍCULO
        // antes de comparar: dos líneas de tres unidades del mismo frasco son
        // seis, y mirarlas de a una las dejaba pasar las dos.
        $pedido = [];
        foreach ($datos['lines'] as $linea) {
            $pedido[$linea['stock_item_id']] = ($pedido[$linea['stock_item_id']] ?? 0) + $linea['qty'];
        }

        foreach ($pedido as $articuloId => $cantidad) {
            $articulo = StockItem::find($articuloId);
            if ($articulo && $cantidad > $articulo->available()) {
                throw ValidationException::withMessages([
                    'lines' => __('stock_loans.errors.over_available', [
                        'item' => $articulo->name,
                        'n'    => $articulo->available(),
                    ]),
                ]);
            }
        }

        return $datos;
    }
}
