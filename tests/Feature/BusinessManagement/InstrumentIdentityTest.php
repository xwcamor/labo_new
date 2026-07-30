<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\Instrument;
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
 * La identidad del instrumento: el NOMBRE es su código de calibración.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ SE FIJA ACÁ                                                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el laboratorio el equipo se llama "PP-LA-01C-100": es lo que el analista
 * dice, lo que va en la hoja de bancada y lo que hace trazable el resultado.
 * "Bureta" es su DESCRIPCIÓN, y tres equipos distintos la comparten.
 *
 * Hasta 2026-07-30 la tabla lo tenía al revés (`name` = "Bureta", `code` =
 * PP-LA-01C-100) y cada capa —el modelo, las reglas, el importador, el listado—
 * tenía que aclarar en un comentario que la clave natural NO era el nombre.
 * Estos casos fijan la regla en el único lugar donde no se puede desmentir:
 *
 *   1. El nombre es único por workspace; la descripción se repite.
 *   2. La descripción es OPCIONAL — obligarla es lo que llevó a que doce filas
 *      dijeran "Bureta" y esa fuera la columna principal del listado.
 *   3. El buscador encuentra por las dos cosas: quien busca escribe tanto
 *      "PP-LA-01C" como "bureta".
 */
class InstrumentIdentityTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LaravelLocalizationRedirectFilter::class,
            LocaleSessionRedirect::class,
        ]);

        $this->seedParentRows();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = ['instruments.view', 'instruments.create', 'instruments.edit'];
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $rol = Role::create(['name' => 'lab_' . Str::random(6), 'guard_name' => 'web', 'description' => 'Prueba']);
        $rol->syncPermissions(Permission::whereIn('name', $permisos)->get());

        $this->usuario = User::factory()->create(['country_id' => 1, 'locale_id' => 1, 'tenant_id' => 1]);
        $this->usuario->assignRole($rol);
        $this->actingAs($this->usuario);
    }

    /** Las filas padre que la fábrica de usuarios referencia por id fijo. */
    private function seedParentRows(): void
    {
        DB::table('languages')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish',
            'iso_code' => 'es', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('locales')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'code' => 'es_PE',
            'name' => 'Español (PE)', 'language_id' => 1, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('regions')->insertOrIgnore([[
            'id' => 999, 'slug' => Str::random(22), 'name' => '__bootstrap__',
            'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
            'deleted_at' => now(), 'deleted_description' => 'Fixture de pruebas.',
        ]]);
        DB::table('countries')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Perú',
            'iso_code' => 'PE', 'currency' => 'PEN', 'timezone' => 'America/Lima',
            'default_locale_id' => 1, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('tenants')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]]);
    }

    public function test_el_nombre_es_el_codigo_de_calibracion_y_es_unico(): void
    {
        Instrument::factory()->named('PP-LA-01C-100')->create(['tenant_id' => 1]);

        $this->post(route('business_management.instruments.store'), [
            'name'        => 'pp-la-01c-100',   // mismo nombre, otra caja
            'description' => 'Otra bureta',
        ])->assertSessionHasErrors('name');
    }

    public function test_tres_equipos_comparten_la_descripcion(): void
    {
        // Es el caso real del laboratorio: tres buretas. La descripción no puede
        // ser la identidad, y por eso no se valida como única.
        foreach (['PP-LA-01C-023', 'PP-LA-01C-065', 'PP-LA-01C-100'] as $nombre) {
            $this->post(route('business_management.instruments.store'), [
                'name'        => $nombre,
                'description' => 'Bureta',
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(3, Instrument::where('description', 'Bureta')->count());
    }

    public function test_la_descripcion_es_opcional(): void
    {
        $this->post(route('business_management.instruments.store'), [
            'name' => 'PP-LA-01C-777',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('instruments', [
            'name'        => 'PP-LA-01C-777',
            'description' => null,
        ]);
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        $this->post(route('business_management.instruments.store'), [
            'description' => 'Bureta sin identificar',
        ])->assertSessionHasErrors('name');
    }

    public function test_el_buscador_encuentra_por_nombre_y_por_descripcion(): void
    {
        Instrument::factory()->named('PP-LA-01C-100')->described('Bureta')->create(['tenant_id' => 1]);
        Instrument::factory()->named('PP-LA-02C-500')->described('Balanza analítica')->create(['tenant_id' => 1]);

        $request = new \Illuminate\Http\Request(['name' => 'PP-LA-01C']);
        $this->assertSame(1, Instrument::query()->filter($request)->count());

        // Y por la descripción: quien busca escribe "bureta", no el código.
        $request = new \Illuminate\Http\Request(['name' => 'bureta']);
        $this->assertSame(1, Instrument::query()->filter($request)->count());
    }

    public function test_el_duplicado_conserva_la_descripcion_y_sufija_el_nombre(): void
    {
        // Duplicar sirve para dar de alta el segundo equipo igual al primero:
        // la descripción SÍ se copia (dos buretas son las dos "Bureta"), el
        // nombre no puede repetirse y la calibración no se arrastra —diría que
        // un equipo está calibrado cuando todavía no se calibró—.
        $original = Instrument::factory()
            ->named('PP-LA-01C-100')
            ->described('Bureta')
            ->create(['tenant_id' => 1, 'serial' => 'SN-1']);

        $copia = app(\App\Services\BusinessManagement\InstrumentService::class)->duplicate($original);

        $this->assertNotNull($copia);
        $this->assertSame('Bureta', $copia->description);
        $this->assertNotSame($original->name, $copia->name);
        $this->assertStringStartsWith('PP-LA-01C-100', $copia->name);
        $this->assertNull($copia->calibration_due_at);
        $this->assertNull($copia->serial);
    }
}
