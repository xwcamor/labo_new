<?php

namespace App\Services\Lab;

use App\Models\Sample;
use App\Models\SampleReport;
use App\Models\SampleReportTest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alta y edición del informe de una muestra.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL FORMULARIO EDITA DONDE VIVE EL DATO                                   │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El formulario de "Nuevo informe" del sistema anterior pedía cuarenta campos y
 * los guardaba TODOS en la fila del reporte: la serie del transformador, su
 * tensión, su año de fabricación, el contacto del cliente. Cada informe era una
 * fotocopia del equipo y del cliente, y corregir un dato mal cargado obligaba a
 * corregirlo informe por informe.
 *
 * Acá el mismo formulario escribe en tres lugares distintos, cada dato en el
 * suyo: lo del cliente y la entrega van a la RECEPCIÓN, lo de la muestra —punto
 * de muestreo, temperaturas de campo, descripción— va a la MUESTRA, lo del
 * equipo va al EQUIPO, y en el informe queda solo lo que es del papel: su
 * correlativo, sus fechas y qué ensayos publica.
 *
 * El efecto práctico es que corregir el TAG del transformador una vez lo corrige
 * en todos los informes que todavía no salieron, en lugar de dejar cuarenta
 * copias divergentes.
 *
 * La fotocopia se saca UNA vez, al emitir (`issue()`), y ahí sí es lo correcto:
 * a partir de ese momento el papel está en la calle con un número.
 */
class SampleReportService
{
    public function __construct(
        private readonly ReportNumberAllocator $allocator = new ReportNumberAllocator(),
    ) {
    }

    /**
     * Crea el informe en borrador y devuelve el registro.
     *
     * @param array<string,mixed> $datos
     */
    public function create(Sample $sample, array $datos, ?int $userId): SampleReport
    {
        return DB::transaction(function () use ($sample, $datos, $userId) {
            $anio   = (int) (($datos['issued_at'] ?? null) ? date('Y', strtotime($datos['issued_at'])) : now()->year);
            $numero = $this->allocator->reserve($sample->tenant_id, $anio);

            // El PRIMERO de la muestra es el principal; el resto, adicionales.
            // Lo decide el sistema porque es un hecho, no una preferencia: hay
            // un solo informe principal y es el que se emitió primero.
            $yaTiene = SampleReport::where('sample_id', $sample->id)->exists();

            $informe = SampleReport::create([
                'slug'       => Str::random(22),
                'sample_id'  => $sample->id,
                'code'       => SampleReport::formatCode($anio, $numero),
                'year'       => $anio,
                'number'     => $numero,
                'kind'       => $yaTiene ? SampleReport::KIND_ADDITIONAL : SampleReport::KIND_PRIMARY,
                'status'     => SampleReport::STATUS_DRAFT,
                'issued_at'  => $datos['issued_at'] ?? now()->toDateString(),
                'delivered_at' => $datos['delivered_at'] ?? null,
                'notes'      => $datos['notes'] ?? null,
                'tenant_id'  => $sample->tenant_id,
                'created_by' => $userId,
            ]);

            $this->aplicarCabecera($sample, $datos);
            $this->sincronizarPruebas($informe, $sample, $datos['tests'] ?? null);

            return $informe;
        });
    }

    /**
     * Guarda los cambios de un informe EN BORRADOR.
     *
     * @param array<string,mixed> $datos
     */
    public function update(SampleReport $informe, array $datos): SampleReport
    {
        return DB::transaction(function () use ($informe, $datos) {
            // Un informe emitido no se edita. No es una restricción de permisos:
            // el papel ya salió con ese contenido y ese número, y cambiarlo
            // dejaría al cliente con una copia que el sistema desmiente. Lo que
            // corresponde es emitir un adicional.
            if ($informe->isIssued()) {
                return $informe;
            }

            $informe->update([
                'issued_at'    => $datos['issued_at'] ?? $informe->issued_at,
                'delivered_at' => $datos['delivered_at'] ?? null,
                'notes'        => $datos['notes'] ?? null,
            ]);

            $muestra = $informe->sample()->firstOrFail();

            $this->aplicarCabecera($muestra, $datos);
            $this->sincronizarPruebas($informe, $muestra, $datos['tests'] ?? null);

            return $informe->fresh();
        });
    }

    /**
     * Emitir: el papel sale a la calle.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ POR QUÉ ESTO NO VIVE EN EL CONTROLADOR                               │
     * └──────────────────────────────────────────────────────────────────────┘
     * Emitir son tres cosas que tienen que pasar juntas o ninguna: congelar el
     * contenido en `snapshot`, marcar informados los ensayos publicados —que es
     * lo que cierra la muestra y la entrega— y dejar constancia en el registro.
     * Mientras estuvo escrito dentro del controlador, cualquier otra vía de
     * emisión (el sembrador de demostración, un comando, mañana la API) tenía
     * que copiar las tres y podía olvidarse de una. Un informe emitido cuyos
     * ensayos siguen en "validado" deja la entrega abierta para siempre, que es
     * exactamente el defecto del sistema anterior.
     *
     * Devuelve `false` si ya estaba emitido: el correlativo sale una sola vez.
     *
     * @param  array<string,mixed>  $rastro  Datos de la petición para el registro (URL, IP, agente).
     */
    public function issue(SampleReport $informe, ?int $userId, array $rastro = []): bool
    {
        if ($informe->isIssued()) {
            return false;
        }

        DB::transaction(function () use ($informe, $userId, $rastro) {
            $datos = app(TestReportPayload::class)->forSample($informe->sample, $informe);

            $informe->update([
                'status'    => SampleReport::STATUS_ISSUED,
                'issued_at' => $informe->issued_at ?: now()->toDateString(),
                'issued_by' => $userId,
                'snapshot'  => $datos,
            ]);

            // El estado se escribe cuando ocurre: los ensayos publicados pasan a
            // "informado", la muestra recalcula el suyo y la recepción se cierra
            // sola si era la última. En el sistema anterior nada de esto pasaba
            // al emitir — el jefe "bloqueaba" la remisión a mano.
            app(SampleProgressService::class)->markReported($informe);

            \App\Models\AuditLog::create(array_merge([
                'user_id'        => $userId,
                'auditable_type' => SampleReport::class,
                'auditable_id'   => $informe->id,
                'event'          => 'report_issued',
                'new_values'     => [
                    'code'     => $informe->code,
                    'sample'   => $informe->sample->code,
                    'sections' => count($datos['sections']),
                ],
                'module'         => 'samples',
            ], $rastro));
        });

        return true;
    }

    /**
     * Reparte la cabecera del formulario a donde vive cada dato.
     *
     * @param array<string,mixed> $datos
     */
    private function aplicarCabecera(Sample $sample, array $datos): void
    {
        // ── De la entrega: quién la pidió y para quién es ────────────────
        $recepcion = array_filter([
            'service_order' => $datos['service_order'] ?? null,
            'contact_info'  => $datos['contact_info'] ?? null,
            'end_user'      => $datos['end_user'] ?? null,
        ], fn ($v) => $v !== null);

        if ($recepcion !== [] && $sample->reception) {
            $sample->reception->update($recepcion);
        }

        // ── De la muestra: cómo y de dónde se tomó ───────────────────────
        $deMuestra = array_filter([
            'description'       => $datos['description'] ?? null,
            'sampling_reason'   => $datos['sampling_reason'] ?? null,
            'sampling_point'    => $datos['sampling_point'] ?? null,
            'sampled_at'        => $datos['sampled_at'] ?? null,
            'oil_temp_c'        => $datos['oil_temp_c'] ?? null,
            'equipment_temp_c'  => $datos['equipment_temp_c'] ?? null,
            'ambient_temp_c'    => $datos['ambient_temp_c'] ?? null,
            'relative_humidity' => $datos['relative_humidity'] ?? null,
            'report_number'     => $datos['report_number'] ?? null,
        ], fn ($v) => $v !== null);

        if ($deMuestra !== []) {
            $sample->update($deMuestra);
        }

        // ── Del equipo: lo que el cliente declara del transformador ──────
        //
        // Se escribe en el EQUIPO y no en el informe porque es una propiedad del
        // equipo: la próxima muestra del mismo transformador tiene que llegar
        // con el dato ya cargado, no obligar a tipearlo de nuevo. Es
        // exactamente lo que el sistema anterior no hacía.
        $deEquipo = array_filter([
            'oil_brand'        => $datos['oil_brand'] ?? null,
            'manufacture_year' => $datos['manufacture_year'] ?? null,
            'oil_volume'       => $datos['oil_volume'] ?? null,
        ], fn ($v) => $v !== null);

        if ($deEquipo !== [] && $sample->equipment) {
            $sample->equipment->update($deEquipo);
        }
    }

    /**
     * Qué ensayos publica el informe.
     *
     * Sin lista explícita entran todos los pedidos, marcados visibles: es lo que
     * espera quien emite un informe completo, y evita que un ensayo válido se
     * quede afuera por omisión.
     *
     * @param array<int,int>|null $visibles ids de `sample_tests`
     */
    private function sincronizarPruebas(SampleReport $informe, Sample $sample, ?array $visibles): void
    {
        $pedidas = $sample->tests()->pluck('id')->all();

        foreach ($pedidas as $id) {
            SampleReportTest::updateOrCreate(
                ['sample_report_id' => $informe->id, 'sample_test_id' => $id],
                ['is_visible' => $visibles === null || in_array($id, $visibles, false)],
            );
        }

        // Una prueba que se dio de baja de la muestra deja de figurar.
        SampleReportTest::where('sample_report_id', $informe->id)
            ->whereNotIn('sample_test_id', $pedidas ?: [0])
            ->delete();
    }
}
