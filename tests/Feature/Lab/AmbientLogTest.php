<?php

namespace Tests\Feature\Lab;

use App\Models\AmbientLog;
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
 * La bitácora de condiciones ambientales de las salas.
 *
 * Es el «Control de Temperaturas» del sistema anterior, que allá eran dos
 * módulos gemelos con las mismas cuatro columnas. Lo que se fija acá es la
 * regla que sostiene el registro: UNA lectura por sala y por día — dos lecturas
 * del mismo día no dicen cuál valía cuando se corrió el ensayo.
 */
class AmbientLogTest extends TestCase
{
    use RefreshDatabase;

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
        foreach (['ambient_logs.view', 'ambient_logs.create', 'ambient_logs.edit', 'ambient_logs.delete'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
    }

    public function test_se_registra_la_lectura_del_dia(): void
    {
        $this->actingAs($this->usuario())
            ->post(route('lab_management.ambient_logs.store'), [
                'room'          => AmbientLog::ROOM_CHROMATOGRAPHY,
                'logged_on'     => now()->toDateString(),
                'temperature_c' => 22.5,
                'humidity_pct'  => 58,
                'pressure_hpa'  => 1013.2,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ambient_logs', [
            'room'      => AmbientLog::ROOM_CHROMATOGRAPHY,
            'tenant_id' => 1,
        ]);
    }

    public function test_una_sola_lectura_por_sala_y_dia(): void
    {
        $usuario = $this->usuario();
        $datos = [
            'room'          => AmbientLog::ROOM_CHROMATOGRAPHY,
            'logged_on'     => now()->toDateString(),
            'temperature_c' => 22.5,
        ];

        $this->actingAs($usuario)->post(route('lab_management.ambient_logs.store'), $datos);

        $this->actingAs($usuario)
            ->post(route('lab_management.ambient_logs.store'), $datos)
            ->assertSessionHasErrors('logged_on');

        $this->assertSame(1, AmbientLog::withoutGlobalScopes()->count());
    }

    public function test_la_otra_sala_si_puede_cargar_el_mismo_dia(): void
    {
        $usuario = $this->usuario();
        $dia = now()->toDateString();

        foreach (AmbientLog::ROOMS as $sala) {
            $this->actingAs($usuario)->post(route('lab_management.ambient_logs.store'), [
                'room' => $sala, 'logged_on' => $dia, 'temperature_c' => 21,
            ]);
        }

        $this->assertSame(2, AmbientLog::withoutGlobalScopes()->count());
    }

    public function test_no_se_registra_una_lectura_del_futuro(): void
    {
        // Una condición ambiental de mañana no se midió: se inventó.
        $this->actingAs($this->usuario())
            ->post(route('lab_management.ambient_logs.store'), [
                'room'      => AmbientLog::ROOM_PHYSICOCHEMICAL,
                'logged_on' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors('logged_on');
    }

    public function test_los_valores_fuera_de_rango_fisico_se_rechazan(): void
    {
        // 150 % de humedad es un error de tipeo, no una medición.
        $this->actingAs($this->usuario())
            ->post(route('lab_management.ambient_logs.store'), [
                'room'         => AmbientLog::ROOM_PHYSICOCHEMICAL,
                'logged_on'    => now()->toDateString(),
                'humidity_pct' => 150,
                'pressure_hpa' => 3,
            ])
            ->assertSessionHasErrors(['humidity_pct', 'pressure_hpa']);
    }

    public function test_la_pantalla_dice_que_sala_no_cargo_hoy(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario)->post(route('lab_management.ambient_logs.store'), [
            'room' => AmbientLog::ROOM_CHROMATOGRAPHY,
            'logged_on' => now()->toDateString(), 'temperature_c' => 22,
        ]);

        $props = $this->actingAs($usuario)
            ->get(route('lab_management.ambient_logs.index'))
            ->viewData('page')['props'];

        $this->assertSame([AmbientLog::ROOM_CHROMATOGRAPHY], $props['today']);
    }

    public function test_sin_permiso_no_se_entra(): void
    {
        $intruso = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);

        $this->actingAs($intruso)
            ->get(route('lab_management.ambient_logs.index'))
            ->assertRedirect();
    }

    private function usuario(): User
    {
        $rol = Role::firstOrCreate(['name' => 'perfil_ambiente', 'guard_name' => 'web'], ['description' => 'Prueba']);
        $rol->syncPermissions(Permission::where('name', 'like', 'ambient_logs.%')->get());

        $usuario = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
