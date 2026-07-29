<?php

namespace Tests\Feature\Lab;

use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\User;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
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
 * Las rutas de la bancada, y sobre todo el reparto de permisos.
 *
 * `worksheets.validate` es un permiso APARTE de `worksheets.edit`, y esa
 * separación corrige un agujero real del sistema Rails viejo: allá la pantalla
 * de validar escondía su enlace a los no supervisores, pero la ACCIÓN
 * verificaba el permiso de editar. El botón no estaba y la dirección sí: quien
 * pudiera cargar un ensayo podía firmarlo escribiendo la URL a mano.
 */
class WorksheetRoutesTest extends TestCase
{
    use RefreshDatabase;

    private TestDefinition $definition;

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
        foreach ([
            'worksheets.view', 'worksheets.create', 'worksheets.edit',
            'worksheets.delete', 'worksheets.validate',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->definition = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'acid', 'name' => 'Número Ácido',
        ]);
        TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->definition->id,
            'code' => 'nro_muestra', 'label' => 'Nº de Muestra', 'type' => 'text',
            'role' => TestField::ROLE_SAMPLE_CODE, 'sort_order' => 1,
        ]);
    }

    /** Un usuario con exactamente los permisos indicados. */
    private function userWith(array $permissions): User
    {
        $role = Role::create([
            'name'      => 'perfil_' . Str::random(6),
            'guard_name' => 'web',
            'description' => 'Prueba',
        ]);
        $role->syncPermissions(Permission::whereIn('name', $permissions)->get());

        $user = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $user->assignRole($role);

        return $user;
    }

    private function makeWorksheet(string $status = Worksheet::STATUS_DRAFT): Worksheet
    {
        return Worksheet::create([
            'slug'               => Str::random(22),
            'test_definition_id' => $this->definition->id,
            'run_date'           => '2026-07-28',
            'status'             => $status,
            'tenant_id'          => 1,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────

    public function test_el_analista_carga_pero_no_valida(): void
    {
        $analista = $this->userWith([
            'worksheets.view', 'worksheets.create', 'worksheets.edit',
        ]);
        $worksheet = $this->makeWorksheet(Worksheet::STATUS_CLOSED);

        $this->actingAs($analista)
            ->post(route('lab_management.worksheets.validate', $worksheet))
            // El proyecto convierte el 403 en una redirección al tablero.
            ->assertRedirect(route('dashboard_management.dashboards.index'));

        $this->assertSame(Worksheet::STATUS_CLOSED, $worksheet->fresh()->status);
        $this->assertNull($worksheet->fresh()->validated_by);
    }

    public function test_el_supervisor_valida(): void
    {
        $supervisor = $this->userWith([
            'worksheets.view', 'worksheets.edit', 'worksheets.validate',
        ]);
        $worksheet = $this->makeWorksheet(Worksheet::STATUS_CLOSED);

        $this->actingAs($supervisor)
            ->post(route('lab_management.worksheets.validate', $worksheet))
            ->assertSessionHasNoErrors();

        $worksheet->refresh();
        $this->assertSame(Worksheet::STATUS_VALIDATED, $worksheet->status);
        $this->assertSame($supervisor->id, $worksheet->validated_by);
    }

    public function test_sin_permiso_de_lectura_no_se_ve_el_listado(): void
    {
        $this->actingAs($this->userWith([]))
            ->get(route('lab_management.worksheets.index'))
            ->assertRedirect(route('dashboard_management.dashboards.index'));
    }

    public function test_el_listado_no_esconde_las_hojas_viejas(): void
    {
        // El sistema viejo forzaba en silencio un filtro de "últimos tres
        // meses" cuando no se mandaba fecha: los ensayos anteriores eran
        // invisibles y nada en la pantalla lo indicaba.
        $vieja = $this->makeWorksheet();
        $vieja->update(['run_date' => '2019-03-04']);
        $this->makeWorksheet();

        $this->actingAs($this->userWith(['worksheets.view']))
            ->get(route('lab_management.worksheets.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Worksheets/Index')
                ->has('worksheets.data', 2));
    }

    public function test_la_ficha_informa_que_falta_el_patron(): void
    {
        $this->definition->update(['requires_control' => true, 'requires_duplicate' => true]);
        $worksheet = $this->makeWorksheet();

        $this->actingAs($this->userWith(['worksheets.view', 'worksheets.edit']))
            ->get(route('lab_management.worksheets.show', $worksheet))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Worksheets/Show')
                ->where('missing', [WorksheetRow::KIND_CONTROL, WorksheetRow::KIND_DUPLICATE]));
    }

    public function test_dar_de_baja_exige_motivo(): void
    {
        // Era "anular" y ahora es borrar —que es lo que tenía el sistema
        // anterior—, pero el motivo sigue siendo obligatorio: una hoja que
        // desaparece sin decir por qué no sirve ante una auditoría.
        $worksheet = $this->makeWorksheet();

        $this->actingAs($this->userWith(['worksheets.view', 'worksheets.delete']))
            ->delete(route('lab_management.worksheets.destroy', $worksheet), [])
            ->assertSessionHasErrors('void_reason');

        $this->assertSame(Worksheet::STATUS_DRAFT, $worksheet->fresh()->status);
    }

    public function test_se_guarda_una_fila_por_la_ruta(): void
    {
        $worksheet = $this->makeWorksheet();

        $this->actingAs($this->userWith(['worksheets.view', 'worksheets.edit']))
            ->post(route('lab_management.worksheets.rows.save', $worksheet), [
                'kind'   => WorksheetRow::KIND_CONTROL,
                'values' => ['nro_muestra' => 'PATRON-1'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('worksheet_rows', [
            'worksheet_id' => $worksheet->id,
            'kind'         => WorksheetRow::KIND_CONTROL,
        ]);
    }
}
