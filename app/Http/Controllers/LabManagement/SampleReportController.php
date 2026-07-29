<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Sample;
use App\Models\SampleReport;
use App\Services\Lab\SampleReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edición y emisión del informe de una muestra.
 *
 * El PDF sigue viviendo en `TestReportController`: acá se administra el
 * REGISTRO —su correlativo, sus fechas, qué ensayos publica— y allá se dibuja
 * el papel.
 */
class SampleReportController extends Controller
{
    public function __construct(private readonly SampleReportService $service)
    {
    }

    /**
     * Los datos con los que se abre el formulario de alta.
     *
     * Llega por AJAX desde la ficha de la entrega: el formulario es un modal a
     * pantalla completa y pedir cuarenta campos de cabecera en el payload de
     * TODAS las muestras de la recepción sería cargar cuarenta veces algo que
     * se usa una.
     */
    public function create(Sample $sample): JsonResponse
    {
        $sample->loadMissing([
            'reception.customer:id,name,address',
            'reception.sampler:id,name',
            'equipment.equipmentType:id,name',
            'equipment.oilType:id,name',
            'equipment.brand:id,name',
            'equipment.tapChangerType:id,name',
            'equipment.preservation:id,name',
            'equipment.location:id,name',
            'tests.definition:id,code,name',
        ]);

        return response()->json($this->formulario($sample, null));
    }

    /** Los mismos datos, pero de un informe existente. */
    public function edit(SampleReport $report): JsonResponse
    {
        $report->loadMissing(['visibilities', 'sample']);
        $sample = $report->sample;

        $sample->loadMissing([
            'reception.customer:id,name,address',
            'reception.sampler:id,name',
            'equipment.equipmentType:id,name',
            'equipment.oilType:id,name',
            'equipment.brand:id,name',
            'equipment.tapChangerType:id,name',
            'equipment.preservation:id,name',
            'equipment.location:id,name',
            'tests.definition:id,code,name',
        ]);

        return response()->json($this->formulario($sample, $report));
    }

    public function store(Request $request, Sample $sample): RedirectResponse
    {
        $datos = $this->validated($request);

        $informe = $this->service->create($sample, $datos, $request->user()?->id);

        return back()->with('success', __('sample_reports.created', ['code' => $informe->code]));
    }

    public function update(Request $request, SampleReport $report): RedirectResponse
    {
        // Un informe emitido no se edita: el papel ya salió con ese contenido y
        // ese número. Lo que corresponde es emitir un adicional.
        if ($report->isIssued()) {
            return back()->withErrors(['status' => __('sample_reports.issued_is_final')]);
        }

        $this->service->update($report, $this->validated($request));

        return back()->with('success', __('sample_reports.saved'));
    }

    /**
     * Emitir: el papel sale a la calle.
     *
     * A partir de acá el informe deja de ser editable y su contenido queda
     * congelado en `snapshot`, para que reimprimirlo dentro de dos años dé el
     * mismo papel aunque el equipo haya cambiado de TAG.
     */
    public function issue(Request $request, SampleReport $report): RedirectResponse
    {
        if ($report->isIssued()) {
            return back()->withErrors(['status' => __('sample_reports.already_issued')]);
        }

        DB::transaction(function () use ($request, $report) {
            $datos = app(\App\Services\Lab\TestReportPayload::class)
                ->forSample($report->sample, $report);

            $report->update([
                'status'    => SampleReport::STATUS_ISSUED,
                'issued_at' => $report->issued_at ?: now()->toDateString(),
                'issued_by' => $request->user()?->id,
                'snapshot'  => $datos,
            ]);

            AuditLog::create([
                'user_id'        => $request->user()?->id,
                'auditable_type' => SampleReport::class,
                'auditable_id'   => $report->id,
                'event'          => 'report_issued',
                'new_values'     => [
                    'code'     => $report->code,
                    'sample'   => $report->sample->code,
                    'sections' => count($datos['sections']),
                ],
                'url'        => $request->fullUrl(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'module'     => 'samples',
            ]);
        });

        return back()->with('success', __('sample_reports.issued', ['code' => $report->code]));
    }

    /**
     * Dar de baja un informe.
     *
     * Un informe EMITIDO no se borra ni siquiera acá: el cliente tiene un papel
     * con ese código y el portal de verificación tiene que seguir
     * encontrándolo. Un borrador sí, con su motivo.
     */
    public function destroy(Request $request, SampleReport $report): RedirectResponse
    {
        if ($report->isIssued()) {
            return back()->withErrors(['status' => __('sample_reports.issued_cannot_delete')]);
        }

        $request->validate([
            'deleted_description' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $report->update([
            'deleted_by'          => $request->user()?->id,
            'deleted_description' => $request->input('deleted_description'),
        ]);
        $report->delete();

        return back()->with('success', __('sample_reports.deleted'));
    }

    /**
     * La cabecera con la que se abre el formulario.
     *
     * Sale de donde vive cada dato —recepción, muestra, equipo— y no de una
     * copia guardada en el informe: así el formulario muestra lo último que se
     * sabe y no una fotocopia de cuando se creó el borrador.
     *
     * @return array<string,mixed>
     */
    private function formulario(Sample $sample, ?SampleReport $report): array
    {
        $eq = $sample->equipment;
        $re = $sample->reception;

        $visibles = $report
            ? $report->visibilities->pluck('is_visible', 'sample_test_id')->all()
            : [];

        return [
            'report' => $report ? [
                'slug'         => $report->slug,
                'code'         => $report->code,
                'kind'         => $report->kind,
                'status'       => $report->status,
                'issued_at'    => $report->issued_at?->toDateString(),
                'delivered_at' => $report->delivered_at?->toDateString(),
                'notes'        => $report->notes,
            ] : null,

            // Lo que NO se edita: identifica al registro y cambiarlo rompería la
            // trazabilidad. Se muestra para que el operador confirme que está en
            // la muestra correcta.
            'readonly' => [
                'sample_code'   => $sample->code,
                'customer_name' => ($re?->customer?->name) ?? $eq?->customer?->name,
                'serial'        => $eq?->serial,
                'reception'     => $re?->code,
            ],

            'header' => [
                // De la entrega
                'service_order' => $re?->service_order,
                'contact_info'  => $re?->contact_info,
                'end_user'      => $re?->end_user,
                'received_at'   => $re?->received_at?->toDateString(),
                'sampler'       => $re?->sampler?->name ?? $re?->sampler_name,

                // De la muestra
                'report_number'     => $sample->report_number,
                'description'       => $sample->description,
                'sampling_reason'   => $sample->sampling_reason,
                'sampling_point'    => $sample->sampling_point,
                'sampled_at'        => $sample->sampled_at?->toDateString(),
                'oil_temp_c'        => $sample->oil_temp_c,
                'equipment_temp_c'  => $sample->equipment_temp_c,
                'ambient_temp_c'    => $sample->ambient_temp_c,
                'relative_humidity' => $sample->relative_humidity,

                // Del equipo
                'location'         => $eq?->location?->name,
                'tag'              => $eq?->tag,
                'equipment_type'   => $eq?->equipmentType?->name,
                'brand'            => $eq?->brand?->name,
                'oil_type'         => $eq?->oilType?->name,
                'oil_brand'        => $eq?->oil_brand,
                'voltage_kv_hv'    => $eq?->voltage_kv_hv,
                'voltage_kv_lv'    => $eq?->voltage_kv_lv,
                'power_mva'        => $eq?->power_mva,
                'manufacture_year' => $eq?->manufacture_year,
                'tap_changer'      => $eq?->tapChangerType?->name,
                'preservation'     => $eq?->preservation?->name,
                'oil_volume'       => $eq?->oil_volume,
                'oil_volume_unit'  => $eq?->oil_volume_unit,
                'has_equipment'    => $eq !== null,
            ],

            // Las pruebas pedidas, con su estado: el operador ve cuáles todavía
            // no están validadas y por qué no van a salir impresas aunque las
            // deje marcadas.
            'tests' => $sample->tests->map(fn ($t) => [
                'id'         => $t->id,
                'name'       => $t->definition?->name,
                'code'       => $t->definition?->code,
                'status'     => $t->status,
                'is_visible' => $visibles[$t->id] ?? true,
            ])->values(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'issued_at'    => ['nullable', 'date'],
            'delivered_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'notes'        => ['nullable', 'string', 'max:2000'],

            'service_order' => ['nullable', 'string', 'max:60'],
            'contact_info'  => ['nullable', 'string', 'max:1000'],
            'end_user'      => ['nullable', 'string', 'max:255'],

            'report_number'   => ['nullable', 'string', 'max:40'],
            'description'     => ['nullable', 'string', 'max:1000'],
            'sampling_reason' => ['nullable', 'string', 'max:80'],
            'sampling_point'  => ['nullable', 'string', 'max:80'],
            'sampled_at'      => ['nullable', 'date'],

            // Las condiciones de campo son medidas, no casillas: el rango evita
            // el 30000 de un dedo pesado, y el nulo se distingue del cero. En el
            // sistema anterior se guardaban como texto y se imprimían con
            // `to_f`, así que un campo vacío salía "0.00".
            'oil_temp_c'        => ['nullable', 'numeric', 'between:-50,250'],
            'equipment_temp_c'  => ['nullable', 'numeric', 'between:-50,250'],
            'ambient_temp_c'    => ['nullable', 'numeric', 'between:-50,80'],
            'relative_humidity' => ['nullable', 'numeric', 'between:0,100'],

            'oil_brand'        => ['nullable', 'string', 'max:120'],
            'manufacture_year' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'oil_volume'       => ['nullable', 'numeric', 'min:0', 'max:999999'],

            'tests'   => ['nullable', 'array'],
            'tests.*' => ['integer'],
        ]);
    }
}
