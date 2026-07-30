<?php

namespace Tests\Feature\Lab;

use App\Models\TestDefinition;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
use App\Services\LabManagement\TestDefinitionService;
use Database\Seeders\LabTestQcPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * El patrón y el duplicado que cada prueba exige por corrida.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL DEFECTO QUE ESTO CIERRA                                               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El mecanismo existía —`Worksheet::missingPrerequisites()` impide publicar una
 * hoja sin su patrón y su duplicado— pero las 29 pruebas tenían las dos banderas
 * en `false`, así que no se disparaba nunca: cualquier hoja se publicaba sin
 * haber corrido control de calidad.
 *
 * En el sistema anterior la regla era la misma (mínimo 1 patrón + 1 duplicado por
 * corrida), pero vivía dentro del HTML de tres formularios distintos, sin
 * validación en el modelo ni en el controlador, y terminó desactivada: el botón
 * que bloqueaba quedó envuelto en un `display:none` con el comentario "SE HA
 * COMENTADO PARA VALIDAR MUESTRAS".
 */
class TestQcPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenants')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio',
            'created_at' => now(), 'updated_at' => now(),
        ]]);
    }

    private function prueba(array $extra = []): TestDefinition
    {
        return TestDefinition::create(array_merge([
            'slug' => Str::random(22),
            'code' => 'p'.Str::random(6),
            'name' => 'Prueba',
        ], $extra));
    }

    // ─── El dato de fábrica ──────────────────────────────────────────────

    public function test_de_fabrica_toda_prueba_exige_patron_y_duplicado(): void
    {
        $prueba = $this->prueba();

        (new LabTestQcPolicySeeder())->run();

        $prueba->refresh();
        $this->assertTrue($prueba->requires_control);
        $this->assertTrue($prueba->requires_duplicate);
        // Y ninguna viene exenta: los valores reales de la exención en el sistema
        // anterior solo están en su base de producción.
        $this->assertFalse($prueba->is_grouped);
    }

    public function test_el_sembrador_no_pisa_lo_que_el_laboratorio_decidio(): void
    {
        // El supervisor marcó una prueba como exenta. Un `db:seed` posterior no
        // puede devolverle la exigencia: se enteraría cuando una hoja que venía
        // publicándose deje de publicarse, y no sabría por qué.
        $prueba = $this->prueba([
            'requires_control'   => false,
            'requires_duplicate' => false,
            'is_grouped'         => true,
            'qc_policy_set_at'   => now(),
        ]);

        (new LabTestQcPolicySeeder())->run();

        $prueba->refresh();
        $this->assertFalse($prueba->requires_control);
        $this->assertFalse($prueba->requires_duplicate);
        $this->assertTrue($prueba->is_grouped);
    }

    public function test_sembrar_dos_veces_no_cambia_nada(): void
    {
        $prueba = $this->prueba();

        (new LabTestQcPolicySeeder())->run();
        $primera = $prueba->fresh()->qc_policy_set_at;

        (new LabTestQcPolicySeeder())->run();

        $this->assertEquals($primera, $prueba->fresh()->qc_policy_set_at);
    }

    // ─── Tocar la ficha convierte la fila en decisión del laboratorio ────

    public function test_guardar_desde_la_ficha_marca_la_fila_como_calibrada(): void
    {
        $prueba = $this->prueba();
        $this->assertNull($prueba->qc_policy_set_at);

        app(TestDefinitionService::class)->update($prueba, [
            'name' => 'Prueba', 'requires_duplicate' => false,
        ]);

        $this->assertNotNull($prueba->fresh()->qc_policy_set_at);
    }

    public function test_guardar_sin_tocar_el_control_de_calidad_no_marca_nada(): void
    {
        // Corregir el nombre de una prueba no es decidir su control de calidad.
        // Si lo marcara, el sembrador dejaría de poder refrescar el valor de
        // fábrica de una prueba a la que solo se le arregló una tilde.
        $prueba = $this->prueba();

        app(TestDefinitionService::class)->update($prueba, ['name' => 'Número Ácido']);

        $this->assertNull($prueba->fresh()->qc_policy_set_at);
    }

    // ─── Y lo que la exigencia produce ───────────────────────────────────

    public function test_la_hoja_no_se_publica_sin_su_patron_ni_su_duplicado(): void
    {
        $prueba = $this->prueba(['requires_control' => true, 'requires_duplicate' => true]);

        $hoja = Worksheet::create([
            'slug' => Str::random(22),
            'test_definition_id' => $prueba->id,
            'run_date' => '2026-07-28',
            'status' => Worksheet::STATUS_DRAFT,
            'tenant_id' => 1,
        ]);

        $this->assertSame(
            [WorksheetRow::KIND_CONTROL, WorksheetRow::KIND_DUPLICATE],
            $hoja->missingPrerequisites(),
        );
    }

    public function test_con_el_patron_cargado_solo_falta_el_duplicado(): void
    {
        $prueba = $this->prueba(['requires_control' => true, 'requires_duplicate' => true]);

        $hoja = Worksheet::create([
            'slug' => Str::random(22),
            'test_definition_id' => $prueba->id,
            'run_date' => '2026-07-28',
            'status' => Worksheet::STATUS_DRAFT,
            'tenant_id' => 1,
        ]);

        WorksheetRow::create([
            'slug' => Str::random(22),
            'worksheet_id' => $hoja->id,
            'kind' => WorksheetRow::KIND_CONTROL,
            'position' => 1,
            'tenant_id' => 1,
        ]);

        $this->assertSame([WorksheetRow::KIND_DUPLICATE], $hoja->fresh()->missingPrerequisites());
    }

    public function test_una_prueba_exenta_publica_sin_control_de_calidad(): void
    {
        // Es el caso legítimo: hay ensayos —cualitativos, o corridos en un
        // instrumento con su propia verificación— que no llevan patrón por
        // corrida. El sistema anterior lo resolvía con la misma casilla.
        $prueba = $this->prueba([
            'requires_control' => false, 'requires_duplicate' => false, 'is_grouped' => true,
        ]);

        $hoja = Worksheet::create([
            'slug' => Str::random(22),
            'test_definition_id' => $prueba->id,
            'run_date' => '2026-07-28',
            'status' => Worksheet::STATUS_DRAFT,
            'tenant_id' => 1,
        ]);

        $this->assertSame([], $hoja->missingPrerequisites());
    }
}
