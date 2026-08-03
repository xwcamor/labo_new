<?php

namespace Tests\Feature\Lab;

use App\Models\Analyte;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Result;
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
 * La tendencia del equipo del cliente.
 *
 * Es lo que el sistema anterior resolvía en `Templates::TendencesController`
 * leyendo `lab_sub_details` y filtrando por el id de columna de la plantilla
 * (61 a 69 para los nueve gases, escritos a mano). Acá sale de `results`.
 *
 * Lo que se fija: que la serie salga en orden cronológico, que el límite viaje
 * POR PUNTO (el que regía cuando se midió, no el de hoy), y que el selector de
 * parámetros ofrezca solo los que ese equipo tiene medidos.
 */
class TrendTest extends TestCase
{
    use RefreshDatabase;

    private Analyte $acidez;
    private Analyte $agua;
    private Equipment $equipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([LaravelLocalizationRedirectFilter::class, LocaleSessionRedirect::class]);

        DB::table('languages')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish', 'iso_code' => 'es', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('locales')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'code' => 'es_PE', 'name' => 'Espanol', 'language_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('regions')->insertOrIgnore([['id' => 999, 'slug' => Str::random(22), 'name' => '__bs__', 'is_active' => false, 'deleted_at' => now(), 'deleted_description' => 'bs', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('countries')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Peru', 'iso_code' => 'PE', 'currency' => 'PEN', 'timezone' => 'America/Lima', 'default_locale_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('tenants')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'equipment.view', 'guard_name' => 'web']);

        $this->acidez = Analyte::create([
            'slug' => Str::random(22), 'code' => 'acid', 'name' => 'Número Ácido',
            'unit' => 'mg KOH/g', 'decimals' => 3, 'direction' => 'lower_better', 'group' => 'fiqui',
        ]);
        $this->agua = Analyte::create([
            'slug' => Str::random(22), 'code' => 'wat', 'name' => 'Contenido de agua',
            'unit' => 'ppm', 'decimals' => 1, 'direction' => 'lower_better', 'group' => 'fiqui',
        ]);

        $cliente = Customer::create(['slug' => Str::random(22), 'tenant_id' => 1, 'name' => 'Cliente']);
        $this->equipo = Equipment::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'customer_id' => $cliente->id,
            'name' => 'Transformador 01', 'serial' => 'TR-9001', 'tag' => 'T-01',
        ]);
    }

    public function test_la_serie_sale_en_orden_cronologico_con_su_limite_por_punto(): void
    {
        // Se cargan desordenadas a propósito: el orden lo tiene que poner la
        // consulta, no el orden de inserción.
        $this->resultado('2026-06-01', 0.09, 0.20);
        $this->resultado('2024-01-15', 0.03, 0.10);   // con el límite viejo
        $this->resultado('2025-03-10', 0.05, 0.20);

        $serie = $this->serieDe(['acid']);

        $this->assertSame(['2024-01-15', '2025-03-10', '2026-06-01'], array_column($serie['points'], 'date'));
        // El límite es el que regía CUANDO se midió: el primero conserva 0.10
        // aunque el cuadro haya pasado después a 0.20.
        $this->assertSame([0.10, 0.20, 0.20], array_column($serie['points'], 'max'));
    }

    public function test_el_valor_fuera_de_norma_viaja_marcado(): void
    {
        $this->resultado('2026-06-01', 0.35, 0.20, Result::SPEC_OUT);

        $serie = $this->serieDe(['acid']);

        $this->assertSame(Result::SPEC_OUT, $serie['points'][0]['status']);
    }

    public function test_solo_se_ofrecen_los_parametros_que_ese_equipo_tiene_medidos(): void
    {
        // De todo el catálogo, este equipo solo tiene acidez. Ofrecer los 60
        // obligaría a probar uno por uno cuál tiene datos.
        $this->resultado('2026-06-01', 0.09, 0.20);

        $props = $this->actingAs($this->usuario())
            ->get(route('lab_management.trends.index', ['equipment' => $this->equipo->slug]))
            ->viewData('page')['props'];

        $this->assertCount(1, $props['analytes']);
        $this->assertSame('acid', $props['analytes'][0]['code']);
        $this->assertSame(1, $props['analytes'][0]['points']);
    }

    public function test_sin_equipo_elegido_no_se_dibuja_nada(): void
    {
        $props = $this->actingAs($this->usuario())
            ->get(route('lab_management.trends.index'))
            ->viewData('page')['props'];

        $this->assertNull($props['selected']);
        $this->assertSame([], $props['series']);
    }

    public function test_el_selector_solo_trae_equipos_con_resultados(): void
    {
        // Un equipo sin una sola medición no puede dibujar una tendencia; en
        // la lista solo estorba.
        Equipment::create([
            'slug' => Str::random(22), 'tenant_id' => 1,
            'customer_id' => $this->equipo->customer_id, 'name' => 'Sin datos', 'serial' => 'TR-0000',
        ]);
        $this->resultado('2026-06-01', 0.09, 0.20);

        $props = $this->actingAs($this->usuario())
            ->get(route('lab_management.trends.index'))
            ->viewData('page')['props'];

        $this->assertCount(1, $props['equipment']);
        $this->assertSame('TR-9001', $props['equipment'][0]['serial']);
    }

    // ─── Fixtures ────────────────────────────────────────────────────────

    private function serieDe(array $codigos): array
    {
        $props = $this->actingAs($this->usuario())
            ->get(route('lab_management.trends.index', [
                'equipment' => $this->equipo->slug,
                'analytes'  => $codigos,
            ]))
            ->viewData('page')['props'];

        return $props['series'][0];
    }

    private function resultado(string $fecha, float $valor, ?float $max, ?string $estado = null): Result
    {
        return Result::create([
            'tenant_id'    => 1,
            'equipment_id' => $this->equipo->id,
            'analyte_id'   => $this->acidez->id,
            'measured_at'  => $fecha,
            'value_num'    => $valor,
            'spec_max'     => $max,
            'spec_status'  => $estado,
            'replicate_no' => 1,
            'unit'         => 'mg KOH/g',
        ]);
    }

    private function usuario(): User
    {
        $rol = Role::firstOrCreate(['name' => 'perfil_tendencias', 'guard_name' => 'web'], ['description' => 'Prueba']);
        $rol->syncPermissions(Permission::where('name', 'equipment.view')->get());

        $usuario = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
