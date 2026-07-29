<?php

namespace Tests\Feature\Lab;

use App\Models\InstrumentFormat;
use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\User;
use App\Models\Worksheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "Lectura de Archivo TXT" de punta a punta.
 *
 * El contenido de los archivos reproduce la FORMA de los protocolos reales del
 * laboratorio (ensayador disruptivo DPA 75C, cromatógrafo), no los archivos en
 * sí: el repositorio es público y los originales llevan números de muestra de
 * clientes.
 */
class InstrumentFileUploadTest extends TestCase
{
    use RefreshDatabase;

    private Worksheet $worksheet;
    private InstrumentFormat $format;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([LaravelLocalizationRedirectFilter::class, LocaleSessionRedirect::class]);
        Storage::fake('local');

        DB::table('languages')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish', 'iso_code' => 'es', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('locales')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'code' => 'es_PE', 'name' => 'Español', 'language_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('regions')->insertOrIgnore([['id' => 999, 'slug' => Str::random(22), 'name' => '__bs__', 'is_active' => false, 'deleted_at' => now(), 'deleted_description' => 'bs', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('countries')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Peru', 'iso_code' => 'PE', 'currency' => 'PEN', 'timezone' => 'America/Lima', 'default_locale_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('tenants')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['worksheets.view', 'worksheets.edit'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'Analista de laboratorio', 'guard_name' => 'web'], ['description' => 'Prueba']);
        $role->syncPermissions(Permission::all());

        $user = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $user->assignRole($role);
        $this->actingAs($user);

        $definition = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'rig', 'name' => 'Rigidez Dieléctrica',
        ]);
        foreach (['rig_1', 'rig_5', 'temp'] as $i => $code) {
            TestField::create([
                'slug' => Str::random(22), 'test_definition_id' => $definition->id,
                'code' => $code, 'label' => $code, 'type' => 'number', 'sort_order' => $i + 1,
            ]);
        }

        $this->worksheet = Worksheet::create([
            'slug' => Str::random(22), 'test_definition_id' => $definition->id,
            'run_date' => '2026-07-28', 'tenant_id' => 1,
        ]);

        $this->format = InstrumentFormat::create([
            'slug' => Str::random(22), 'code' => 'dpa75c', 'name' => 'Hitachi DPA 75C',
            'test_definition_id' => $definition->id, 'kind' => 'label', 'encoding' => 'UTF-8',
            'column_map' => ['fields' => [
                ['code' => 'rig_1', 'mode' => 'label', 'match' => 'Medición 1:'],
                ['code' => 'rig_5', 'mode' => 'label', 'match' => 'Medición 5:'],
                ['code' => 'temp',  'mode' => 'label', 'match' => 'Temperatura:'],
            ]],
            'is_active' => true, 'tenant_id' => 1,
        ]);
    }

    private function protocolo(): string
    {
        return implode("\n", [
            'Protocolo de medición',
            'Valores de medición',
            '',
            'Temperatura: '."\t\t".'20 °C',
            'Medición 1:'."\t\t\t".'39.1  kV',
            'Medición 5:'."\t\t\t".'>75.0  kV',
        ]);
    }

    private function upload(string $contents, string $name = 'protocolo.txt')
    {
        return $this->postJson(
            route('lab_management.worksheets.instrument_file', $this->worksheet),
            [
                'file' => UploadedFile::fake()->createWithContent($name, $contents),
                'instrument_format_id' => $this->format->id,
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────

    public function test_el_archivo_se_interpreta_y_devuelve_los_valores(): void
    {
        $response = $this->upload($this->protocolo())->assertOk();

        // El paso por JSON devuelve 20 y no 20.0: se compara el valor, no el tipo.
        $this->assertEqualsWithDelta(39.1, $response->json('values.rig_1.0.number'), 1e-9);
        $this->assertEqualsWithDelta(20.0, $response->json('values.temp.0.number'), 1e-9);
        $this->assertSame('parsed', $response->json('file.status'));
    }

    public function test_el_tope_del_instrumento_se_conserva_como_tal(): void
    {
        // ">75.0 kV" es "al menos 75", no 75: el aceite no rompió.
        $response = $this->upload($this->protocolo())->assertOk();

        $this->assertEqualsWithDelta(75.0, $response->json('values.rig_5.0.number'), 1e-9);
        $this->assertSame('gt', $response->json('values.rig_5.0.qualifier'));
    }

    public function test_el_archivo_queda_guardado_con_su_huella(): void
    {
        // El sistema viejo leía el archivo entero y lo volcaba dentro de una
        // columna de texto de la base; el archivo como tal no se conservaba.
        $this->upload($this->protocolo())->assertOk();

        $this->assertDatabaseCount('instrument_files', 1);
        $record = \App\Models\InstrumentFile::first();

        $this->assertSame(64, strlen((string) $record->sha256));
        $this->assertSame('protocolo.txt', $record->original_name);
        Storage::assertExists($record->path);
    }

    public function test_lo_que_no_aparece_se_informa_en_vez_de_fallar(): void
    {
        $response = $this->upload("Temperatura:\t\t20 °C")->assertOk();

        $this->assertEqualsWithDelta(20.0, $response->json('values.temp.0.number'), 1e-9);
        $this->assertSame(['rig_1', 'rig_5'], $response->json('unmatched'));
    }

    public function test_un_archivo_sin_ninguna_coincidencia_queda_marcado_como_fallido(): void
    {
        $response = $this->upload("Nada de esto coincide\ncon el formato elegido")->assertOk();

        $this->assertSame('failed', $response->json('file.status'));
        $this->assertNotNull(\App\Models\InstrumentFile::first()->parse_error);
    }

    public function test_no_se_carga_un_archivo_en_una_hoja_bloqueada(): void
    {
        // Lo único que cierra la hoja es el candado, que pone el sistema a los
        // N meses. No hay estado intermedio que la congele.
        $this->worksheet->forceFill(['locked_at' => now()])->save();

        $this->upload($this->protocolo())->assertStatus(422);

        $this->assertDatabaseCount('instrument_files', 0);
    }

    public function test_el_formato_es_obligatorio(): void
    {
        $this->postJson(
            route('lab_management.worksheets.instrument_file', $this->worksheet),
            ['file' => UploadedFile::fake()->createWithContent('x.txt', 'algo')]
        )->assertStatus(422)->assertJsonValidationErrors('instrument_format_id');
    }
}
