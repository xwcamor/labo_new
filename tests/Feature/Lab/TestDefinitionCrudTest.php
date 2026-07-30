<?php

namespace Tests\Feature\Lab;

use App\Models\TestDefinition;
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
 * El alta de una prueba: lo que se tipea y lo que decide el sistema.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL ORDEN NO LO PONE EL USUARIO                                           │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Crear una prueba dejando "Orden" vacío reventaba con un error 500 en la cara:
 *
 *     SQLSTATE[23502]: Not null violation: el valor nulo en la columna
 *     «sort_order» de la relación «test_definitions» viola la restricción
 *     not-null
 *
 * La columna tiene `default(0)`, y ahí está la trampa: el default de una base
 * solo actúa cuando la columna NO viaja en el INSERT. El formulario mandaba
 * `sort_order = null` explícito, y un null explícito no dispara el default —
 * dispara la restricción. Es el mismo agujero que ya se había tapado en los
 * grupos de pruebas, y estaba abierto acá.
 */
class TestDefinitionCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private TestGroup $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LaravelLocalizationRedirectFilter::class,
            LocaleSessionRedirect::class,
        ]);

        $this->seedParentRows();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = ['test_definitions.view', 'test_definitions.create', 'test_definitions.edit'];
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $rol = Role::create(['name' => 'lab_' . Str::random(6), 'guard_name' => 'web', 'description' => 'Prueba']);
        $rol->syncPermissions(Permission::whereIn('name', $permisos)->get());

        $this->usuario = User::factory()->create(['country_id' => 1, 'locale_id' => 1, 'tenant_id' => 1]);
        $this->usuario->assignRole($rol);

        $this->grupo = TestGroup::create([
            'slug' => Str::random(22), 'name' => 'Fisicoquímicos',
            'code' => 'fisicoquimicos', 'sort_order' => 1, 'tenant_id' => 1,
        ]);
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

    /** El envío tal como lo manda el formulario cuando "Orden" queda vacío. */
    private function envio(array $extra = []): array
    {
        return array_merge([
            'name'                 => 'prueba bautista',
            'code'                 => 'bautista',
            'test_group_id'        => $this->grupo->id,
            'description'          => 'Una prueba nueva.',
            'container'            => 'PK',
            'chart_unit'           => 'ppm',
            'report_comment_group' => null,
            'has_control'          => true,
            'requires_control'     => true,
            'requires_duplicate'   => true,
            'is_grouped'           => false,
            'replicates'           => 1,
            'sort_order'           => null,
            'is_active'            => true,
        ], $extra);
    }

    public function test_crear_sin_orden_no_revienta(): void
    {
        $this->actingAs($this->usuario)
            ->post(route('lab_management.test_definitions.store'), $this->envio())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('test_definitions', ['code' => 'bautista']);
    }

    public function test_la_prueba_nueva_queda_al_final_de_su_grupo(): void
    {
        // Al final, no en 0: una prueba nueva que aparece PRIMERA en el
        // desplegable, delante de las que el laboratorio ya venía usando, es un
        // reordenamiento que nadie pidió.
        TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'ya_estaba', 'name' => 'Ya estaba',
            'test_group_id' => $this->grupo->id, 'sort_order' => 12, 'tenant_id' => 1,
        ]);

        $this->actingAs($this->usuario)
            ->post(route('lab_management.test_definitions.store'), $this->envio())
            ->assertSessionHasNoErrors();

        $this->assertSame(13, TestDefinition::where('code', 'bautista')->value('sort_order'));
    }

    public function test_el_orden_que_se_tipea_se_respeta(): void
    {
        $this->actingAs($this->usuario)
            ->post(route('lab_management.test_definitions.store'), $this->envio(['sort_order' => 3]))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, TestDefinition::where('code', 'bautista')->value('sort_order'));
    }

    public function test_vaciar_el_orden_al_editar_no_mueve_la_prueba(): void
    {
        // Vaciar el campo en el formulario de edición no es pedir que la prueba
        // se vaya al final de la lista.
        $prueba = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'existente', 'name' => 'Existente',
            'test_group_id' => $this->grupo->id, 'sort_order' => 4, 'tenant_id' => 1,
        ]);

        $this->actingAs($this->usuario)
            ->put(route('lab_management.test_definitions.update', $prueba), $this->envio([
                'name' => 'Existente renombrada',
                'code' => 'existente',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $prueba->fresh()->sort_order);
    }
}
