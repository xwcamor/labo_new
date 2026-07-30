<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "Mi workspace": el admin configura el membrete de informes de SU tenant
 * (dirección, disclaimer, aprobador) sin depender del super.
 */
class WorkspaceBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('languages')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish', 'iso_code' => 'es', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('locales')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'code' => 'es_AR', 'name' => 'Español', 'language_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('regions')->insertOrIgnore([['id' => 999, 'slug' => Str::random(22), 'name' => '__bs__', 'is_active' => false, 'deleted_at' => now(), 'deleted_description' => 'bs', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('countries')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Peru', 'iso_code' => 'PE', 'currency' => 'PEN', 'timezone' => 'UTC', 'default_locale_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('tenants')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Empresa 1', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['description' => 'admin']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web'], ['description' => 'user']);

        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
        ]);
    }

    private function makeUser(string $role): User
    {
        $u = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $u->assignRole($role);
        return $u;
    }

    public function test_admin_can_update_own_workspace_branding(): void
    {
        $this->actingAs($this->makeUser('admin'));

        $this->get(route('workspace.edit'))->assertOk();

        $this->put(route('workspace.update'), [
            'address'           => 'Av. Siempre Viva 742, Lima',
            'report_disclaimer' => 'Los resultados aplican solo a la muestra ensayada.',
        ])->assertRedirect();

        $t = Tenant::find(1);
        $this->assertSame('Av. Siempre Viva 742, Lima', $t->address);
        $this->assertNotNull($t->report_disclaimer);
    }

    /**
     * Los firmantes salen del MÓDULO FIRMAS, no de esta pantalla.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ QUÉ SE CONSOLIDÓ Y POR QUÉ                                           │
     * └──────────────────────────────────────────────────────────────────────┘
     * Había DOS lugares donde se configuraba quién firma un informe:
     *
     *   · `report_signers`, una tabla pelada con su editor dentro de esta
     *     pantalla. La usaba el flujo de aprobación y el gate del menú
     *     "Aprobaciones".
     *   · `signatures`, el módulo FIRMAS: catálogo completo, con pantalla propia,
     *     papelera, auditoría y candado. Es el que el informe IMPRIME.
     *
     * O sea que el papel se firmaba con una lista y el flujo de aprobación
     * gateaba con la otra: un laboratorio con sus firmas cargadas en el módulo
     * podía no ver nunca la bandeja de Aprobaciones. Se consolidó en el módulo.
     *
     * Este test reemplaza al que probaba el editor retirado. Lo que fija ahora es
     * que la pantalla LEA del catálogo y que NO lo escriba: la relación
     * `reportSigners()` apunta al catálogo, y el bloque que estaba acá hacía
     * `->delete()` sobre ella antes de recrear la lista — habría borrado las
     * firmas del workspace cada vez que alguien guardara la dirección.
     */
    public function test_los_firmantes_se_leen_del_modulo_firmas(): void
    {
        $interno = User::factory()->create([
            'tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1, 'name' => 'Ing. Ana Supervisora',
        ]);

        \App\Models\Signature::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'name' => 'Ing. Ana Supervisora',
            'title' => 'Supervisor', 'relation' => 'reviewed', 'user_id' => $interno->id,
            'sort_order' => 1, 'is_active' => true,
        ]);
        \App\Models\Signature::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'name' => 'C.P. Pedro Auditor',
            'title' => 'Auditor', 'relation' => 'approved', 'user_id' => null,
            'sort_order' => 2, 'is_active' => true,
        ]);

        $this->actingAs($this->makeUser('admin'));

        $props = $this->get(route('workspace.edit'))->viewData('page')['props'];

        $this->assertCount(2, $props['signers']);
        $this->assertSame(['Supervisor', 'Auditor'], collect($props['signers'])->pluck('title')->all());
        // El externo se marca como tal: en el papel es una línea para firmar a
        // mano, y el admin tiene que poder verlo antes de emitir.
        $this->assertSame('external', $props['signers'][1]['status']);
    }

    public function test_guardar_el_workspace_no_toca_las_firmas(): void
    {
        // El bloque retirado hacía `reportSigners()->delete()`. Con la relación
        // apuntando al catálogo, guardar la dirección habría dado de baja todas
        // las firmas del laboratorio, en silencio.
        \App\Models\Signature::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'name' => 'Ing. Ana Supervisora',
            'title' => 'Supervisor', 'relation' => 'reviewed', 'sort_order' => 1, 'is_active' => true,
        ]);

        $this->actingAs($this->makeUser('admin'));

        $this->put(route('workspace.update'), [
            'address' => 'Otra dirección 123',
        ])->assertRedirect();

        $this->assertSame(1, Tenant::find(1)->reportSigners()->count());
    }

    public function test_solo_los_firmantes_del_modulo_ven_la_bandeja_de_aprobaciones(): void
    {
        // El gate del menú mira el MISMO catálogo con el que se firma. Antes
        // miraba `report_signers`, o sea otra lista.
        $firmante = $this->makeUser('admin');
        $this->actingAs($firmante);

        $props = $this->get(route('workspace.edit'))->viewData('page')['props'];
        $this->assertFalse($props['approvals']['is_signer'] ?? false);

        \App\Models\Signature::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'name' => $firmante->name,
            'title' => 'Jefe de Laboratorio', 'relation' => 'approved',
            'user_id' => $firmante->id, 'sort_order' => 1, 'is_active' => true,
        ]);

        $props = $this->get(route('workspace.edit'))->viewData('page')['props'];
        $this->assertTrue($props['approvals']['is_signer']);
    }

    public function test_admin_updates_workspace_logo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->actingAs($this->makeUser('admin'));

        $this->post(route('workspace.logo.update'), [
            'logo' => \Illuminate\Http\UploadedFile::fake()->image('logo.png', 300, 100),
        ])->assertRedirect();

        $t = Tenant::find(1);
        $this->assertNotNull($t->logo);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($t->logo);

        // No-imagen → rechazada. Usuario común → bloqueado.
        $this->post(route('workspace.logo.update'), [
            'logo' => \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf'),
        ])->assertSessionHasErrors('logo');

        $this->actingAs($this->makeUser('user'));
        $this->post(route('workspace.logo.update'), [
            'logo' => \Illuminate\Http\UploadedFile::fake()->image('x.png'),
        ])->assertRedirect(); // 403 → redirect al dashboard (handler global)
        $this->assertSame($t->logo, Tenant::find(1)->logo); // sin cambios
    }

    public function test_regular_user_cannot_access_workspace_branding(): void
    {
        $this->actingAs($this->makeUser('user'));

        // El handler global convierte los 403 de usuarios autenticados en
        // redirect al dashboard — lo que importa es que NO toca el tenant.
        $this->get(route('workspace.edit'))->assertRedirect();
        $this->put(route('workspace.update'), ['address' => 'hackeado'])->assertRedirect();
        $this->assertNotSame('hackeado', Tenant::find(1)->address);
    }
}
