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

    public function test_sin_permiso_de_edicion_no_se_escribe_la_hoja(): void
    {
        // Ya no hay acción de validar: la hoja publica sola en cuanto está
        // completa. Lo que sigue estando gobernado por permiso es ESCRIBIR, y
        // eso es lo que se verifica.
        $worksheet = $this->makeWorksheet();

        $this->actingAs($this->userWith(['worksheets.view']))
            ->post(route('lab_management.worksheets.rows.save', $worksheet), [
                'kind' => WorksheetRow::KIND_CONTROL,
                'values' => ['peso_aceite' => '20', 'volumen_gastado' => '1.20'],
            ])
            // El proyecto convierte el 403 en una redirección al tablero.
            ->assertRedirect(route('dashboard_management.dashboards.index'));

        $this->assertSame(0, $worksheet->rows()->count());
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

    // ─── El enlace con la muestra ─────────────────────────────────────────
    //
    // El sistema anterior ataba la fila a la muestra partiendo el correlativo
    // TIPEADO e interpolándolo en SQL. Si el formato no coincidía, el enlace no
    // se hacía y el resultado no llegaba al informe del cliente sin que nada lo
    // avisara. Acá la fila se ata por clave foránea, y de ese enlace dependen
    // el avance de la muestra, el equipo del resultado y el bloque de
    // condiciones de ensayo del informe.

    public function test_la_fila_se_ata_a_la_prueba_pedida_y_hereda_muestra_y_equipo(): void
    {
        $worksheet = $this->makeWorksheet();
        $prueba    = $this->makeSampleTest();

        $this->actingAs($this->userWith(['worksheets.view', 'worksheets.edit']))
            ->post(route('lab_management.worksheets.rows.save', $worksheet), [
                'kind'           => WorksheetRow::KIND_SAMPLE,
                'sample_test_id' => $prueba->id,
                'values'         => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('worksheet_rows', [
            'worksheet_id'   => $worksheet->id,
            'sample_test_id' => $prueba->id,
            'sample_id'      => $prueba->sample_id,
            'sample_code'    => $prueba->sample->code,
        ]);
    }

    public function test_la_ficha_ofrece_las_pruebas_que_esta_hoja_espera(): void
    {
        $worksheet = $this->makeWorksheet();
        $prueba    = $this->makeSampleTest();

        $this->actingAs($this->userWith(['worksheets.view']))
            ->get(route('lab_management.worksheets.show', $worksheet))
            ->assertInertia(fn ($page) => $page
                ->has('pendingTests', 1)
                ->where('pendingTests.0.id', $prueba->id)
                ->where('pendingTests.0.code', $prueba->sample->code));
    }

    /** Una muestra con esta prueba pedida y todavía sin resultado. */
    private function makeSampleTest(): \App\Models\SampleTest
    {
        $cliente = \App\Models\Customer::create([
            'slug' => Str::random(22), 'name' => 'Bancada ' . Str::random(5), 'tenant_id' => 1,
        ]);
        $recepcion = \App\Models\Reception::create([
            'slug' => Str::random(22), 'customer_id' => $cliente->id,
            'received_at' => now(), 'tenant_id' => 1,
            'status' => \App\Models\Reception::STATUS_CONFIRMED,
        ]);
        $muestra = \App\Models\Sample::create([
            'slug' => Str::random(22), 'reception_id' => $recepcion->id,
            'year' => 2026, 'number' => 700, 'code' => '2026-0700',
            'tenant_id' => 1, 'is_urgent' => false,
        ]);

        return \App\Models\SampleTest::create([
            'sample_id' => $muestra->id,
            'test_definition_id' => $this->definition->id,
            'status' => \App\Models\SampleTest::STATUS_PENDING,
            'tenant_id' => 1,
        ]);
    }
}
