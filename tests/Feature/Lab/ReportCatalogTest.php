<?php

namespace Tests\Feature\Lab;

use App\Models\ReportCatalog;
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
 * Las cuatro listas del formulario del informe.
 *
 * Lo que se fija acá es lo que estas listas vinieron a resolver: que la misma
 * opción no se pueda cargar dos veces, que darla de baja no toque lo que ya se
 * imprimió, y que lo que consume el desplegable sea el TEXTO —no el id— para
 * que un informe emitido no cambie porque alguien ordenó el catálogo.
 */
class ReportCatalogTest extends TestCase
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

    public function test_el_desplegable_solo_ofrece_las_activas_y_en_su_orden(): void
    {
        $this->fila('Rutina', orden: 2);
        $this->fila('Evento', orden: 1);
        $this->fila('Descartada', orden: 3, activa: false);

        $opciones = ReportCatalog::options(ReportCatalog::KIND_REASON);

        $this->assertSame(['Evento', 'Rutina'], array_column($opciones, 'value'));
        // El valor ES el texto: lo que se guarda en la muestra y lo que imprime
        // el informe. Si fuera el id, renombrar la fila cambiaría un informe
        // ya emitido.
        $this->assertSame($opciones[0]['value'], $opciones[0]['label']);
    }

    public function test_la_misma_opcion_no_entra_dos_veces_en_la_misma_lista(): void
    {
        $this->fila('Rutina');

        $this->actingAs($this->usuario('admin'))
            ->post(route('lab_management.report_catalogs.store'), [
                'kind' => ReportCatalog::KIND_REASON,
                'name' => 'Rutina',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, ReportCatalog::where('name', 'Rutina')->count());
    }

    public function test_el_mismo_nombre_si_entra_en_OTRA_lista(): void
    {
        // «Cilindro» puede ser unidad de volumen y a la vez punto de muestreo:
        // la restricción es por lista, no global.
        $this->fila('Cilindro', kind: ReportCatalog::KIND_VOLUME_UNIT);

        $this->actingAs($this->usuario('admin'))
            ->post(route('lab_management.report_catalogs.store'), [
                'kind' => ReportCatalog::KIND_POINT,
                'name' => 'Cilindro',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ReportCatalog::where('name', 'Cilindro')->count());
    }

    public function test_la_fila_no_se_puede_mudar_de_lista(): void
    {
        // Mudarla la haría desaparecer de un desplegable y aparecer en otro, y
        // las muestras que la citan quedarían con una opción que ya no está en
        // su lista.
        $fila = $this->fila('Inferior', kind: ReportCatalog::KIND_POINT);

        $this->actingAs($this->usuario('admin'))
            ->put(route('lab_management.report_catalogs.update', $fila), [
                'kind' => ReportCatalog::KIND_VOLUME_UNIT,
                'name' => 'Inferior',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ReportCatalog::KIND_POINT, $fila->fresh()->kind);
    }

    public function test_dar_de_baja_la_saca_del_desplegable_y_no_toca_la_muestra(): void
    {
        $fila = $this->fila('Cambio de aceite');

        $this->actingAs($this->usuario('admin'))
            ->put(route('lab_management.report_catalogs.update', $fila), [
                'name'      => 'Cambio de aceite',
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([], ReportCatalog::options(ReportCatalog::KIND_REASON));
        // La fila sigue existiendo: los informes emitidos la citan por texto y
        // el catálogo tiene que poder explicar de dónde salió ese texto.
        $this->assertDatabaseHas('report_catalogs', [
            'id' => $fila->id, 'deleted_at' => null, 'is_active' => false,
        ]);
    }

    public function test_el_seeder_no_siembra_el_centinela_de_ninguno(): void
    {
        // El sistema anterior tenía una fila llamada literalmente «-» para decir
        // «ninguno» dentro de un desplegable obligatorio. Sembrarla dejaría una
        // opción elegible que se imprime como un guion, indistinguible de un
        // dato que nadie cargó.
        $this->seed(\Database\Seeders\ReportCatalogsSeeder::class);

        $this->assertSame(0, ReportCatalog::withoutGlobalScopes()->where('name', '-')->count());
        $this->assertNotSame(0, ReportCatalog::withoutGlobalScopes()->count());
    }

    public function test_sin_permiso_no_se_entra(): void
    {
        // El middleware de permisos de este proyecto no devuelve 403: redirige
        // con el aviso. Lo que importa es que NO se sirva la pantalla.
        $this->actingAs($this->usuario('analista'))
            ->get(route('lab_management.report_catalogs.index'))
            ->assertRedirect();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function fila(
        string $nombre,
        string $kind = ReportCatalog::KIND_REASON,
        int $orden = 0,
        bool $activa = true,
    ): ReportCatalog {
        return ReportCatalog::withoutGlobalScopes()->create([
            'slug'       => Str::random(22),
            'tenant_id'  => 1,
            'kind'       => $kind,
            'name'       => $nombre,
            'sort_order' => $orden,
            'is_active'  => $activa,
        ]);
    }

    private function usuario(string $rol): User
    {
        Role::firstOrCreate(
            ['name' => $rol, 'guard_name' => 'web'],
            ['description' => 'Rol de prueba.'],
        );

        if ($rol === 'admin') {
            foreach (['view', 'show', 'create', 'edit', 'delete'] as $accion) {
                Permission::firstOrCreate(
                    ['name' => "report_catalogs.{$accion}", 'guard_name' => 'web'],
                );
            }

            Role::where('name', 'admin')->first()->syncPermissions(
                Permission::where('name', 'like', 'report_catalogs.%')->get(),
            );
        }

        $usuario = User::factory()->create([
            'country_id' => 1, 'locale_id' => 1, 'tenant_id' => 1,
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
