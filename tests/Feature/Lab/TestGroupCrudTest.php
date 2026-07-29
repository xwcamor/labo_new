<?php

namespace Tests\Feature\Lab;

use App\Models\TestGroup;
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
 * El grupo de pruebas: qué se tipea y qué decide el sistema.
 *
 * El alta reventaba con una violación de nulo porque `sort_order` es obligatorio
 * en la base y el formulario podía no traerlo. Y el código se tipeaba a mano,
 * con lo que un grupo terminó llamándose "67" — cuando el código es el
 * identificador con el que las pruebas, el informe y los archivos de idioma
 * referencian al grupo.
 */
class TestGroupCrudTest extends TestCase
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

        $permisos = ['test_groups.view', 'test_groups.create', 'test_groups.edit'];
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $rol = Role::create(['name' => 'lab_' . Str::random(6), 'guard_name' => 'web', 'description' => 'Prueba']);
        $rol->syncPermissions(Permission::whereIn('name', $permisos)->get());

        $this->usuario = User::factory()->create(['country_id' => 1, 'locale_id' => 1, 'tenant_id' => 1]);
        $this->usuario->assignRole($rol);
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

    public function test_el_orden_se_asigna_solo_cuando_el_formulario_no_lo_trae(): void
    {
        // Es lo que reventaba: `sort_order` es NOT NULL y el formulario lo deja
        // vacío. Pedirle al usuario un número que el sistema sabe calcular es
        // lo que hacía que el alta fallara con una violación de nulo en la cara.
        TestGroup::create([
            'slug' => Str::random(22), 'name' => 'Primero', 'code' => 'primero',
            'sort_order' => 7, 'tenant_id' => 1,
        ]);

        $this->actingAs($this->usuario)
            ->post(route('lab_management.test_groups.store'), ['name' => 'Segundo Grupo'])
            ->assertSessionHasNoErrors();

        $this->assertSame(8, TestGroup::where('name', 'Segundo Grupo')->value('sort_order'));
    }

    public function test_el_codigo_se_deriva_del_nombre(): void
    {
        $this->actingAs($this->usuario)
            ->post(route('lab_management.test_groups.store'), ['name' => 'Ensayos Físico Químicos'])
            ->assertSessionHasNoErrors();

        // Sin tildes, en minúsculas, y los espacios como guion bajo.
        $this->assertDatabaseHas('test_groups', [
            'name' => 'Ensayos Físico Químicos',
            'code' => 'ensayos_fisico_quimicos',
        ]);
    }

    public function test_el_codigo_que_manden_no_se_usa(): void
    {
        // Aunque alguien arme la petición a mano: el código sale del nombre.
        // Así fue como un grupo terminó llamándose "67".
        $this->actingAs($this->usuario)
            ->post(route('lab_management.test_groups.store'), [
                'name' => 'Otros Ensayos',
                'code' => '67',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('otros_ensayos', TestGroup::where('name', 'Otros Ensayos')->value('code'));
    }

    public function test_dos_nombres_que_dan_el_mismo_codigo_no_pasan(): void
    {
        // "Físico Químico" y "Fisico Quimico" producen el MISMO código. La
        // validación tiene que mirar el derivado, no lo que venga en el campo,
        // o el choque aparece recién contra el índice de la base.
        TestGroup::create([
            'slug' => Str::random(22), 'name' => 'Fisico Quimico', 'code' => 'fisico_quimico',
            'sort_order' => 1, 'tenant_id' => 1,
        ]);

        $this->actingAs($this->usuario)
            ->post(route('lab_management.test_groups.store'), ['name' => 'Físico Químico'])
            ->assertSessionHasErrors('code');
    }

    public function test_renombrar_el_grupo_no_le_cambia_el_codigo(): void
    {
        // Si algo ya referencia ese código —una prueba, una traducción, un
        // cuadro de límites—, reescribirlo por debajo rompe el enlace en
        // silencio.
        $grupo = TestGroup::create([
            'slug' => Str::random(22), 'name' => 'Otros', 'code' => 'otros',
            'sort_order' => 3, 'tenant_id' => 1,
        ]);

        $this->actingAs($this->usuario)
            ->put(route('lab_management.test_groups.update', $grupo), [
                'name' => 'Otros Ensayos Especiales',
                'code' => 'otro_codigo_cualquiera',
            ])
            ->assertSessionHasNoErrors();

        $grupo->refresh();

        $this->assertSame('Otros Ensayos Especiales', $grupo->name);
        $this->assertSame('otros', $grupo->code);
    }

    public function test_crear_y_editar_llevan_a_la_ficha(): void
    {
        $this->actingAs($this->usuario)
            ->post(route('lab_management.test_groups.store'), ['name' => 'Grupo Nuevo'])
            ->assertRedirect(route(
                'lab_management.test_groups.show',
                TestGroup::where('name', 'Grupo Nuevo')->value('slug'),
            ));

        $grupo = TestGroup::where('name', 'Grupo Nuevo')->first();

        $this->actingAs($this->usuario)
            ->put(route('lab_management.test_groups.update', $grupo), ['name' => 'Grupo Renombrado'])
            ->assertRedirect(route('lab_management.test_groups.show', $grupo->slug));
    }
}
