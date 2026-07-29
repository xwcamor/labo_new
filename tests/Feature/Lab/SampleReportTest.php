<?php

namespace Tests\Feature\Lab;

use App\Models\Customer;
use App\Models\Reception;
use App\Models\Sample;
use App\Models\SampleReport;
use App\Models\SampleTest;
use App\Models\TestDefinition;
use App\Models\User;
use App\Services\Lab\SampleReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * El informe como registro.
 *
 * Lo que se fija acá es lo que el sistema anterior hacía mal y le costaba
 * dinero al laboratorio: emitir dos veces el mismo correlativo, devolver a la
 * fila el número de un informe dado de baja, y dejar editable un papel que ya
 * estaba en manos del cliente.
 */
class SampleReportTest extends TestCase
{
    use RefreshDatabase;

    private SampleReportService $service;
    private TestDefinition $prueba;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParentRows();
        $this->service = new SampleReportService();

        $this->prueba = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'acidez', 'name' => 'Número Ácido',
        ]);
    }

    // ─── El correlativo ──────────────────────────────────────────────────

    public function test_el_primer_informe_de_la_muestra_es_el_principal(): void
    {
        $muestra = $this->muestra();

        $primero = $this->service->create($muestra, [], null);
        $segundo = $this->service->create($muestra, [], null);

        $this->assertSame(SampleReport::KIND_PRIMARY, $primero->kind);
        $this->assertSame(SampleReport::KIND_ADDITIONAL, $segundo->kind);
    }

    public function test_el_correlativo_no_se_repite(): void
    {
        $a = $this->service->create($this->muestra(), [], null);
        $b = $this->service->create($this->muestra(), [], null);

        $this->assertSame('REP-LAB-' . now()->year . '-0001', $a->code);
        $this->assertSame('REP-LAB-' . now()->year . '-0002', $b->code);
    }

    public function test_dar_de_baja_un_informe_no_devuelve_su_numero(): void
    {
        // El del sistema anterior sí: buscaba el último con `where(deleted: 0)`,
        // así que borrar el más alto hacía que el siguiente lo reemitiera. Dos
        // clientes con papeles del mismo código y un portal de verificación que
        // no puede decir cuál es cuál.
        $primero = $this->service->create($this->muestra(), [], null);
        $primero->delete();

        $segundo = $this->service->create($this->muestra(), [], null);

        $this->assertNotSame($primero->code, $segundo->code);
        $this->assertSame(2, $segundo->number);
    }

    // ─── Lo que se guarda dónde ──────────────────────────────────────────

    public function test_la_cabecera_se_guarda_donde_vive_cada_dato(): void
    {
        // El sistema anterior copiaba los cuarenta campos dentro de la fila del
        // reporte, así que corregir el contacto del cliente había que
        // corregirlo informe por informe.
        $muestra = $this->muestra();

        $this->service->create($muestra, [
            'service_order'  => '700089655',
            'contact_info'   => 'jadriazo@fmi.com',
            'sampling_point' => 'Superior',
            'oil_temp_c'     => 23.8,
        ], null);

        $muestra->refresh();

        $this->assertSame('700089655', $muestra->reception->service_order);
        $this->assertSame('jadriazo@fmi.com', $muestra->reception->contact_info);
        $this->assertSame('Superior', $muestra->sampling_point);
        $this->assertEquals(23.8, $muestra->oil_temp_c);
    }

    public function test_sin_lista_explicita_se_publican_todas_las_pruebas(): void
    {
        $muestra = $this->muestra();

        $informe = $this->service->create($muestra, [], null);

        $this->assertCount(1, $informe->visibilities()->where('is_visible', true)->get());
    }

    public function test_una_prueba_desmarcada_no_se_publica(): void
    {
        $muestra = $this->muestra();

        $informe = $this->service->create($muestra, ['tests' => []], null);

        $this->assertSame([], $informe->fresh()->visibleTestIds());
    }

    // ─── Lo emitido no se toca ───────────────────────────────────────────

    public function test_un_informe_emitido_no_se_edita(): void
    {
        $muestra = $this->muestra();
        $informe = $this->service->create($muestra, ['notes' => 'borrador'], null);
        $informe->update(['status' => SampleReport::STATUS_ISSUED]);

        $this->service->update($informe, ['notes' => 'cambiado']);

        $this->assertSame('borrador', $informe->fresh()->notes);
    }

    // ─── Lo emitido se reimprime igual ───────────────────────────────────

    public function test_el_borrador_no_tiene_carga_congelada(): void
    {
        $informe = $this->service->create($this->muestra(), [], null);

        // En borrador se calcula en vivo a propósito: todavía se corrige.
        $this->assertNull($informe->frozenPayload());
    }

    public function test_el_emitido_imprime_su_snapshot_aunque_los_datos_cambien(): void
    {
        // El papel que el cliente tiene en la mano y la reimpresión tienen que
        // decir lo mismo. Reimprimir "con lo último" un documento emitido no es
        // una mejora: es un segundo documento circulando con el mismo número.
        $muestra = $this->muestra();
        $informe = $this->service->create($muestra, [], null);

        $congelado = ['sample' => ['code' => $muestra->code], 'sections' => [['test' => 'Número Ácido']]];
        $informe->update(['status' => SampleReport::STATUS_ISSUED, 'snapshot' => $congelado]);

        // La muestra cambia DESPUÉS de emitir…
        $muestra->update(['description' => 'corregida después de emitir']);

        // …y el informe sigue imprimiendo lo que se firmó.
        $this->assertSame($congelado, $informe->fresh()->frozenPayload());
    }

    // ─── La cadena de estados al emitir ──────────────────────────────────

    public function test_emitir_informa_las_pruebas_y_cierra_la_recepcion(): void
    {
        // El eslabón que faltaba: sin esto ninguna prueba llegaba a
        // "informado", la muestra nunca alcanzaba su estado final y la
        // recepción quedaba abierta para siempre — en el sistema anterior el
        // jefe la "bloqueaba" a mano cuando la daba por terminada.
        $muestra = $this->muestra();
        $informe = $this->service->create($muestra, [], null);
        $informe->update(['status' => SampleReport::STATUS_ISSUED]);

        app(\App\Services\Lab\SampleProgressService::class)->markReported($informe);

        $muestra->refresh();

        $this->assertSame(
            SampleTest::STATUS_REPORTED,
            $muestra->tests()->first()->status,
        );
        $this->assertSame(Sample::STATUS_REPORTED, $muestra->status);
        // La única muestra de la entrega quedó informada: la recepción se
        // cierra sola, sin botón.
        $this->assertSame(Reception::STATUS_CLOSED, $muestra->reception->fresh()->status);
    }

    public function test_una_prueba_no_publicada_no_se_marca_informada(): void
    {
        // El informe salió SIN esa prueba: decir que se informó mentiría en el
        // avance, y la recepción no puede cerrarse.
        $muestra = $this->muestra();
        $informe = $this->service->create($muestra, ['tests' => []], null);
        $informe->update(['status' => SampleReport::STATUS_ISSUED]);

        app(\App\Services\Lab\SampleProgressService::class)->markReported($informe);

        $this->assertSame(
            SampleTest::STATUS_VALIDATED,
            $muestra->tests()->first()->fresh()->status,
        );
        $this->assertSame(Reception::STATUS_CONFIRMED, $muestra->reception->fresh()->status);
    }

    public function test_la_recepcion_reabre_si_una_prueba_vuelve_a_la_cola(): void
    {
        // Cerrada no es lápida: si un ensayo vuelve a pendiente (se anuló la
        // hoja, se pidió uno nuevo), la recepción deja de figurar terminada
        // sin que nadie tenga que acordarse de reabrirla.
        $muestra = $this->muestra();
        $informe = $this->service->create($muestra, [], null);
        $informe->update(['status' => SampleReport::STATUS_ISSUED]);

        $progreso = app(\App\Services\Lab\SampleProgressService::class);
        $progreso->markReported($informe);
        $this->assertSame(Reception::STATUS_CLOSED, $muestra->reception->fresh()->status);

        $muestra->tests()->first()->update(['status' => SampleTest::STATUS_PENDING]);
        $progreso->refreshSample($muestra->fresh());

        $this->assertSame(Reception::STATUS_CONFIRMED, $muestra->reception->fresh()->status);
    }

    // ─── El borrado de la muestra ────────────────────────────────────────

    public function test_una_muestra_con_informe_emitido_no_se_borra(): void
    {
        // El cliente tiene un papel que cita ese número y el portal público de
        // verificación responde contra el registro: borrarla convertiría ese
        // papel en un documento que el propio sistema desmiente.
        $muestra = $this->muestra();
        $informe = $this->service->create($muestra, [], null);
        $informe->update(['status' => SampleReport::STATUS_ISSUED]);

        $this->assertSame('issued_report', $muestra->fresh()->deletionBlockedBy());
    }

    public function test_un_borrador_no_traba_el_borrado_de_la_muestra(): void
    {
        $muestra = $this->muestra();
        $this->service->create($muestra, [], null);

        $this->assertNull($muestra->fresh()->deletionBlockedBy());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function muestra(): Sample
    {
        // El nombre del cliente es único por workspace, así que cada muestra de
        // la prueba trae el suyo.
        $cliente = Customer::create([
            'slug' => Str::random(22), 'name' => 'Cliente ' . Str::random(6), 'tenant_id' => 1,
        ]);
        $recepcion = Reception::create([
            'slug' => Str::random(22), 'customer_id' => $cliente->id,
            'received_at' => now(), 'tenant_id' => 1, 'status' => Reception::STATUS_CONFIRMED,
        ]);
        $numero = Sample::where('tenant_id', 1)->count() + 1;
        $muestra = Sample::create([
            'slug' => Str::random(22), 'reception_id' => $recepcion->id,
            'year' => 2026, 'number' => $numero,
            'code' => Sample::formatCode(2026, $numero),
            'tenant_id' => 1, 'is_urgent' => false,
        ]);
        SampleTest::create([
            'sample_id' => $muestra->id,
            'test_definition_id' => $this->prueba->id,
            'status' => SampleTest::STATUS_VALIDATED,
            'tenant_id' => 1,
        ]);

        return $muestra->fresh();
    }

    private function seedParentRows(): void
    {
        DB::table('languages')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish',
            'iso_code' => 'es', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('tenants')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio',
            'created_at' => now(), 'updated_at' => now(),
        ]]);
    }
}
