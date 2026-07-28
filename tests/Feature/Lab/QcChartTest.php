<?php

namespace Tests\Feature\Lab;

use App\Models\QcChart;
use App\Models\QcPoint;
use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Las cartas de control.
 *
 * Lo que se cuida acá es lo que el sistema Rails viejo no cuidaba: que los
 * límites pertenezcan a la carta (allá la relación entre la tabla de límites y
 * la prueba era una coincidencia de ids, sin clave foránea), que la alerta esté
 * por dentro del control, que la vigencia se respete, y que un punto excluido
 * quede registrado con su motivo en vez de desaparecer.
 */
class QcChartTest extends TestCase
{
    use RefreshDatabase;

    private TestDefinition $definition;
    private TestField $field;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([LaravelLocalizationRedirectFilter::class, LocaleSessionRedirect::class]);

        DB::table('languages')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish', 'iso_code' => 'es', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('locales')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'code' => 'es_PE', 'name' => 'Español', 'language_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('regions')->insertOrIgnore([['id' => 999, 'slug' => Str::random(22), 'name' => '__bs__', 'is_active' => false, 'deleted_at' => now(), 'deleted_description' => 'bs', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('countries')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Peru', 'iso_code' => 'PE', 'currency' => 'PEN', 'timezone' => 'America/Lima', 'default_locale_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('tenants')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['qc_charts.view', 'qc_charts.create', 'qc_charts.edit', 'qc_charts.delete'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'Supervisor de laboratorio', 'guard_name' => 'web'], ['description' => 'Prueba']);
        $role->syncPermissions(Permission::all());

        $user = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $user->assignRole($role);
        $this->actingAs($user);

        $this->definition = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'acid', 'name' => 'Número Ácido',
        ]);
        $this->field = TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->definition->id,
            'code' => 'resultado', 'label' => 'Resultado', 'type' => 'computed',
            'role' => TestField::ROLE_RESULT, 'sort_order' => 1,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'test_definition_id' => $this->definition->id,
            'test_field_id'      => $this->field->id,
            'control_lot'        => 'LOTE-2026-A',
            'center'             => 0.030,
            'sd'                 => 0.002,
            'is_derived'         => true,
            'warn_sigma'         => 2,
            'action_sigma'       => 3,
            'is_active'          => true,
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────────────

    public function test_se_crea_una_carta_con_su_prueba_y_su_columna(): void
    {
        // La clave foránea es el punto: en el sistema viejo la pantalla de
        // tendencias buscaba los límites con find(id_de_la_prueba), confiando
        // en que los ids de dos tablas sin relación coincidieran.
        $this->post(route('lab_management.qc_charts.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('qc_charts', [
            'test_definition_id' => $this->definition->id,
            'test_field_id'      => $this->field->id,
            'control_lot'        => 'LOTE-2026-A',
        ]);
    }

    public function test_la_alerta_tiene_que_estar_por_dentro_del_control(): void
    {
        // Al revés la carta queda invertida y no avisa nunca.
        $this->post(route('lab_management.qc_charts.store'), $this->payload([
            'warn_sigma' => 3, 'action_sigma' => 2,
        ]))->assertSessionHasErrors('warn_sigma');
    }

    public function test_la_vigencia_no_puede_terminar_antes_de_empezar(): void
    {
        $this->post(route('lab_management.qc_charts.store'), $this->payload([
            'effective_from' => '2026-07-01', 'effective_to' => '2026-06-01',
        ]))->assertSessionHasErrors('effective_to');
    }

    public function test_los_limites_derivados_salen_de_la_media_y_el_desvio(): void
    {
        $chart = QcChart::create($this->payload() + ['slug' => Str::random(22)]);

        $limits = $chart->limits();

        $this->assertEqualsWithDelta(0.030, $limits['lc'], 1e-9);
        $this->assertEqualsWithDelta(0.034, $limits['las'], 1e-9);   // +2 desvíos
        $this->assertEqualsWithDelta(0.036, $limits['lcs'], 1e-9);   // +3 desvíos
        $this->assertEqualsWithDelta(0.026, $limits['lai'], 1e-9);
        $this->assertEqualsWithDelta(0.024, $limits['lci'], 1e-9);
    }

    public function test_sin_desvio_no_se_inventan_limites(): void
    {
        // Es preferible una carta sin límites, que se ve, a una carta con
        // límites inventados sobre un desvío ausente, que no se ve.
        $chart = QcChart::create($this->payload(['sd' => null]) + ['slug' => Str::random(22)]);

        $limits = $chart->limits();

        $this->assertNull($limits['lcs']);
        $this->assertNull($limits['lci']);
        $this->assertEqualsWithDelta(0.030, $limits['lc'], 1e-9);
    }

    public function test_la_vigencia_decide_que_carta_aplica_a_cada_fecha(): void
    {
        // Al cambiar el lote del patrón se cierra la carta y se abre otra. El
        // sistema viejo pisaba los límites y las cartas históricas quedaban
        // dibujadas contra un criterio que no era el de su día.
        $vieja = QcChart::create($this->payload([
            'control_lot' => 'LOTE-A',
            'effective_from' => '2026-01-01', 'effective_to' => '2026-06-30',
        ]) + ['slug' => Str::random(22)]);

        $nueva = QcChart::create($this->payload([
            'control_lot' => 'LOTE-B', 'center' => 0.032,
            'effective_from' => '2026-07-01', 'effective_to' => null,
        ]) + ['slug' => Str::random(22)]);

        $this->assertTrue($vieja->estabaVigenteAl('2026-03-15'));
        $this->assertFalse($vieja->estabaVigenteAl('2026-07-15'));

        $this->assertFalse($nueva->estabaVigenteAl('2026-03-15'));
        $this->assertTrue($nueva->estabaVigenteAl('2026-07-15'));
    }

    public function test_excluir_un_punto_exige_motivo(): void
    {
        $chart = QcChart::create($this->payload() + ['slug' => Str::random(22)]);
        $point = QcPoint::create([
            'qc_chart_id' => $chart->id, 'measured_at' => '2026-07-01 10:00:00',
            'value' => 0.031, 'flag' => QcPoint::FLAG_OK,
        ]);

        $this->patch(
            route('lab_management.qc_charts.points.update', [$chart, $point]),
            ['is_excluded' => true]
        )->assertSessionHasErrors('exclusion_reason');

        $this->assertFalse($point->fresh()->is_excluded);
    }

    public function test_un_punto_excluido_queda_registrado_con_su_motivo(): void
    {
        // No se borra: el laboratorio tiene que poder mostrar que detectó el
        // patrón fuera de control y por qué lo descartó. Una carta impecable
        // porque se borraron los puntos malos no prueba nada.
        $chart = QcChart::create($this->payload() + ['slug' => Str::random(22)]);
        $point = QcPoint::create([
            'qc_chart_id' => $chart->id, 'measured_at' => '2026-07-01 10:00:00',
            'value' => 0.055, 'flag' => QcPoint::FLAG_OUT,
        ]);

        $this->patch(
            route('lab_management.qc_charts.points.update', [$chart, $point]),
            ['is_excluded' => true, 'exclusion_reason' => 'Patrón mal preparado']
        )->assertSessionHasNoErrors();

        $point->refresh();
        $this->assertTrue($point->is_excluded);
        $this->assertSame('Patrón mal preparado', $point->exclusion_reason);
        $this->assertDatabaseHas('qc_points', ['id' => $point->id]);
    }

    public function test_no_se_toca_un_punto_de_otra_carta(): void
    {
        $chart = QcChart::create($this->payload() + ['slug' => Str::random(22)]);
        $otra  = QcChart::create($this->payload(['control_lot' => 'OTRO']) + ['slug' => Str::random(22)]);

        $point = QcPoint::create([
            'qc_chart_id' => $otra->id, 'measured_at' => '2026-07-01 10:00:00',
            'value' => 0.031, 'flag' => QcPoint::FLAG_OK,
        ]);

        // El proyecto convierte el 404 en una redirección al tablero para el
        // usuario autenticado (ver bootstrap/app.php), así que lo que se
        // comprueba es que la dirección no lleve a ningún lado Y que el punto
        // ajeno quede intacto.
        $this->patch(
            route('lab_management.qc_charts.points.update', [$chart, $point]),
            ['is_excluded' => true, 'exclusion_reason' => 'no corresponde']
        )->assertRedirect(route('dashboard_management.dashboards.index'));

        $this->assertFalse($point->fresh()->is_excluded);
        $this->assertNull($point->fresh()->exclusion_reason);
    }

    public function test_la_ficha_muestra_la_carta_con_sus_limites_y_sus_puntos(): void
    {
        $chart = QcChart::create($this->payload() + ['slug' => Str::random(22)]);
        QcPoint::create([
            'qc_chart_id' => $chart->id, 'measured_at' => '2026-07-01 10:00:00',
            'value' => 0.031, 'flag' => QcPoint::FLAG_OK,
        ]);

        $this->get(route('lab_management.qc_charts.show', $chart))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('QcCharts/Show')
                ->has('points', 1)
                ->has('limits')
                ->has('rules'));
    }
}
