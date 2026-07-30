<?php

namespace Tests\Feature\Lab;

use App\Models\DiagnosisTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * La redacción del informe se edita sin desplegar, y un laboratorio no le
 * reescribe el texto a otro.
 *
 * Lo que se fija acá es la COPIA AL ESCRIBIR: el super edita el estándar de
 * fábrica; el admin que edita una plantilla de fábrica obtiene su propia copia y
 * el estándar queda intacto. Sin esa regla, el primer laboratorio que ajusta una
 * coma se la cambia a todos los demás.
 */
class DiagnosisTemplateEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LaravelLocalizationRedirectFilter::class,
            LocaleSessionRedirect::class,
        ]);

        $this->seedParentRows();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_el_super_edita_el_estandar_de_fabrica(): void
    {
        $plantilla = $this->plantillaDeFabrica();

        $this->actingAs($this->usuario('super', null))
            ->put(route('lab_management.diagnosis_templates.update', $plantilla), [
                'body' => 'Redacción nueva del estándar.',
            ])
            ->assertSessionHasNoErrors();

        $plantilla->refresh();

        $this->assertSame('Redacción nueva del estándar.', $plantilla->body);
        // Y no se creó ninguna copia: el super escribe sobre la global.
        $this->assertSame(1, DiagnosisTemplate::withoutGlobalScopes()->count());
    }

    public function test_el_admin_obtiene_su_copia_y_no_toca_el_estandar(): void
    {
        $plantilla = $this->plantillaDeFabrica();
        $original  = $plantilla->body;

        $this->actingAs($this->usuario('admin', 1))
            ->put(route('lab_management.diagnosis_templates.update', $plantilla), [
                'body' => 'La redacción de este laboratorio.',
            ])
            ->assertSessionHasNoErrors();

        // El estándar quedó igual.
        $this->assertSame($original, $plantilla->fresh()->body);

        // Y apareció la copia del workspace, con el cambio.
        $copia = DiagnosisTemplate::withoutGlobalScopes()->where('tenant_id', 1)->sole();

        $this->assertSame('La redacción de este laboratorio.', $copia->body);
        $this->assertSame($plantilla->family, $copia->family);
        $this->assertSame($plantilla->case, $copia->case);
    }

    public function test_la_copia_del_workspace_gana_al_redactar(): void
    {
        // Es la razón de ser de todo esto: el resolvedor tiene que preferir la
        // del laboratorio sobre la de fábrica.
        $this->plantillaDeFabrica();

        DiagnosisTemplate::create([
            'slug' => Str::random(22), 'tenant_id' => 1,
            'family' => 'fisicoquimico', 'case' => 'none',
            'body' => 'Texto propio del laboratorio.',
        ]);

        $this->actingAs($this->usuario('admin', 1));

        $plantillas = (new \ReflectionClass(\App\Services\Lab\DiagnosisTextService::class));
        $metodo = $plantillas->getMethod('plantillas');
        $metodo->setAccessible(true);

        $resueltas = $metodo->invoke(new \App\Services\Lab\DiagnosisTextService());

        $mismaFamilia = collect($resueltas)->where('family', 'fisicoquimico')->where('case', 'none');

        // UNA sola plantilla para ese caso, y es la del laboratorio.
        $this->assertCount(1, $mismaFamilia);
        $this->assertSame('Texto propio del laboratorio.', $mismaFamilia->first()['body']);
    }

    public function test_restaurar_borra_la_copia_y_vuelve_el_estandar(): void
    {
        $this->plantillaDeFabrica();

        $copia = DiagnosisTemplate::create([
            'slug' => Str::random(22), 'tenant_id' => 1,
            'family' => 'fisicoquimico', 'case' => 'none',
            'body' => 'Texto propio.',
        ]);

        $this->actingAs($this->usuario('admin', 1))
            ->post(route('lab_management.diagnosis_templates.restore', $copia))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('diagnosis_templates', ['id' => $copia->id]);
        // La de fábrica sigue ahí, intacta.
        $this->assertDatabaseHas('diagnosis_templates', [
            'tenant_id' => null,
            'family'    => 'fisicoquimico',
            'deleted_at' => null,
        ]);
    }

    public function test_la_de_fabrica_no_se_restaura(): void
    {
        // Borrarla dejaría la familia sin ninguna redacción, y el informe
        // saldría sin ese párrafo sin que nada avise.
        $plantilla = $this->plantillaDeFabrica();

        $this->actingAs($this->usuario('super', null))
            ->post(route('lab_management.diagnosis_templates.restore', $plantilla))
            ->assertSessionHasErrors('body');

        $this->assertDatabaseHas('diagnosis_templates', [
            'id' => $plantilla->id, 'deleted_at' => null,
        ]);
    }

    public function test_una_plantilla_sin_texto_ni_tramos_se_rechaza(): void
    {
        $plantilla = $this->plantillaDeFabrica();

        $this->actingAs($this->usuario('super', null))
            ->put(route('lab_management.diagnosis_templates.update', $plantilla), [
                'body' => '', 'bands' => [],
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_el_analista_no_entra_al_editor(): void
    {
        // La redacción que firma el laboratorio no se delega a un perfil: las
        // rutas piden rol super o admin.
        $this->plantillaDeFabrica();

        // El middleware `role:` de este proyecto no devuelve 403: redirige con
        // el aviso. Lo que importa es que NO se sirva la pantalla.
        $this->actingAs($this->usuario('analista', 1))
            ->get(route('lab_management.diagnosis_templates.index'))
            ->assertRedirect();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function plantillaDeFabrica(): DiagnosisTemplate
    {
        return DiagnosisTemplate::create([
            'slug'      => Str::random(22),
            'tenant_id' => null,
            'family'    => 'fisicoquimico',
            'case'      => 'none',
            'body'      => 'Los resultados están dentro de los valores sugeridos por la Norma {norm}.',
        ]);
    }

    private function usuario(string $rol, ?int $tenantId): User
    {
        // `roles.description` es obligatoria en este esquema, así que
        // `findOrCreate` sin ella revienta contra la restricción de nulo.
        Role::firstOrCreate(
            ['name' => $rol, 'guard_name' => 'web'],
            ['description' => 'Rol de prueba.'],
        );

        $usuario = User::factory()->create([
            'country_id' => 1, 'locale_id' => 1, 'tenant_id' => $tenantId,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }

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
}
