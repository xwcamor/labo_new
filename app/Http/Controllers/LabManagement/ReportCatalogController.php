<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\ReportCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las cuatro listas chicas del formulario del informe, en una sola pantalla.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ UNA PANTALLA CON CUATRO SOLAPAS, NO CUATRO MÓDULOS                       │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Motivo del análisis, punto de muestreo, marca de aceite y unidad de volumen
 * tienen exactamente la misma forma —nombre, activo, orden— y se administran
 * juntas: quien entra a corregir «Valvula inferior» suele venir de corregir
 * «2500 galones». Cuatro módulos idénticos serían cuatro veces el mismo código
 * y cuatro entradas sueltas en el menú.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ NO SE USÓ EL SCAFFOLD                                            │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `make:module` genera papelera, exports en cuatro formatos, importación,
 * vistas guardadas y operaciones masivas. Sobre una lista de seis filas que se
 * toca dos veces por año, eso es cincuenta archivos que mantener a cambio de
 * nada. Acá alcanza con listar, agregar, corregir y desactivar.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ SE DESACTIVA, NO SE BORRA                                                │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Lo que se guarda en la muestra es el TEXTO, no el id: un informe emitido no
 * puede cambiar porque alguien tocó el catálogo. Por eso dar de baja una fila
 * la saca del desplegable y no toca ni un informe. El borrado real existe
 * igual, para la fila que se cargó mal el mismo día.
 */
class ReportCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $filas = ReportCatalog::query()
            ->orderBy('kind')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'kind', 'name', 'is_active', 'sort_order'])
            ->groupBy('kind');

        return Inertia::render('ReportCatalogs/Index', [
            'kinds' => ReportCatalog::KINDS,
            // Una clave por lista SIEMPRE, aunque venga vacía: si la lista sin
            // filas no viniera, la solapa se rompería en vez de mostrar su
            // estado vacío.
            'items' => collect(ReportCatalog::KINDS)
                ->mapWithKeys(fn (string $k) => [$k => $filas->get($k, collect())->values()])
                ->all(),
            'tab' => in_array($request->get('tab'), ReportCatalog::KINDS, true)
                ? $request->get('tab')
                : ReportCatalog::KIND_REASON,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validado($request, null);

        ReportCatalog::create($datos + [
            'slug'       => Str::random(22),
            'tenant_id'  => $request->user()?->tenant_id,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', __('report_catalogs.created'));
    }

    public function update(Request $request, ReportCatalog $report_catalog): RedirectResponse
    {
        // El TIPO no se edita: mover una fila de «punto de muestreo» a «unidad
        // de volumen» la haría desaparecer de un desplegable y aparecer en otro,
        // y las muestras que la usan quedarían citando una opción que ya no está
        // en su lista. Si se cargó en la solapa equivocada, se da de baja y se
        // carga bien.
        $datos = $this->validado($request, $report_catalog);
        unset($datos['kind']);

        $report_catalog->update($datos);

        return back()->with('success', __('report_catalogs.saved'));
    }

    public function destroy(Request $request, ReportCatalog $report_catalog): RedirectResponse
    {
        $report_catalog->update(['deleted_by' => $request->user()?->id]);
        $report_catalog->delete();

        return back()->with('success', __('report_catalogs.deleted'));
    }

    /**
     * @return array<string,mixed>
     */
    private function validado(Request $request, ?ReportCatalog $actual): array
    {
        $kind = $actual?->kind ?? $request->input('kind');

        $datos = $request->validate([
            'kind' => [
                Rule::requiredIf($actual === null),
                Rule::in(ReportCatalog::KINDS),
            ],
            'name' => [
                'required', 'string', 'max:120',
                // Dos filas con el mismo nombre en la misma lista son la misma
                // fila cargada dos veces, que es justo el desorden que este
                // catálogo vino a cortar. El índice de la tabla dice lo mismo;
                // esto lo dice ANTES, con un mensaje que se entiende.
                Rule::unique('report_catalogs', 'name')
                    ->where('kind', $kind)
                    ->where('tenant_id', $request->user()?->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($actual?->id),
            ],
            'is_active'  => ['boolean'],
            // El orden puede llegar vacío del formulario; la columna no es
            // nullable y su ausencia significa «al final por nombre», que es
            // 0 más el desempate alfabético del scope.
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $datos['sort_order'] = (int) ($datos['sort_order'] ?? 0);
        $datos['is_active'] = (bool) ($datos['is_active'] ?? true);

        return $datos;
    }
}
