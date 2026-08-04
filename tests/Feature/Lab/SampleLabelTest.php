<?php

namespace Tests\Feature\Lab;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Reception;
use App\Models\Sample;
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
 * Las etiquetas de los envases.
 *
 * En el sistema anterior esto era un CRUD ("Control de Stickers") donde se daba
 * de alta una etiqueta tipeando el número de muestra como texto libre, sin
 * relación con la muestra y sin registro de quién imprimía. Acá es una acción
 * sobre muestras que ya existen, en su propio menú.
 *
 * Lo que se fija: que la hoja imprimible sea HTML y no un PDF —las medidas de
 * la impresora del laboratorio están calibradas contra esa maqueta—, que el
 * comentario de la tanda salga en la etiqueta, que un código de otro workspace
 * no imprima nada, y que cada impresión deje constancia en el registro de
 * auditoría, que es exactamente lo que la tabla `stickers` no guardaba.
 */
class SampleLabelTest extends TestCase
{
    use RefreshDatabase;

    private Reception $entrega;

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
        Permission::firstOrCreate(['name' => 'receptions.view', 'guard_name' => 'web']);

        $cliente = Customer::create(['slug' => Str::random(22), 'tenant_id' => 1, 'name' => 'Cliente']);

        $this->entrega = Reception::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'customer_id' => $cliente->id,
            'code' => '2026-0007', 'year' => 2026, 'number' => 7,
            'received_at' => '2026-03-04', 'status' => Reception::STATUS_CONFIRMED,
        ]);

        $this->muestra(1, '2026-0001');
        $this->muestra(2, '2026-0002');
    }

    public function test_sin_el_permiso_de_ver_no_se_llega_al_listado(): void
    {
        $intruso = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);

        $this->actingAs($intruso)
            ->get(route('lab_management.sample_labels.index'))
            ->assertRedirect();
    }

    public function test_el_listado_trae_las_muestras_para_etiquetar(): void
    {
        $props = $this->actingAs($this->usuario())
            ->get(route('lab_management.sample_labels.index'))
            ->viewData('page')['props'];

        $this->assertCount(2, $props['samples']['data']);
        // De la más nueva hacia atrás: es lo que se está rotulando ahora.
        $this->assertSame('2026-0002', $props['samples']['data'][0]['code']);
    }

    public function test_el_listado_busca_por_numero_de_muestra(): void
    {
        $props = $this->actingAs($this->usuario())
            ->get(route('lab_management.sample_labels.index', ['search' => '0001']))
            ->viewData('page')['props'];

        $this->assertCount(1, $props['samples']['data']);
        $this->assertSame('2026-0001', $props['samples']['data'][0]['code']);
    }

    /**
     * La hoja es HTML, no un PDF: la impresora de etiquetas del laboratorio
     * está calibrada contra esa maqueta (el recuadro, el logo de 80×50 y el
     * desplazamiento de 1 mm al imprimir). Un PDF con su propia caja cambiaría
     * dónde cae la etiqueta en el rollo.
     */
    public function test_la_hoja_imprimible_es_html_con_la_maqueta_del_sistema_anterior(): void
    {
        $respuesta = $this->actingAs($this->usuario())
            ->post(route('lab_management.sample_labels.print'), ['codes' => ['2026-0001']]);

        $respuesta->assertOk();
        $this->assertStringContainsString('text/html', $respuesta->headers->get('content-type'));

        $html = $respuesta->getContent();

        $this->assertStringContainsString('2026-0001', $html);
        $this->assertStringContainsString(__('labels.sample_no'), $html);
        // La fecha de la entrega, en el formato del anterior.
        $this->assertStringContainsString('04-03-2026', $html);
        // Las medidas que no se tocan.
        $this->assertStringContainsString('border-radius: 1em', $html);
        $this->assertStringContainsString('margin-left: 1mm', $html);
        $this->assertStringContainsString('width="80" height="50"', $html);
        // Y el QR de esa muestra.
        $this->assertStringContainsString('data:image/png;base64', $html);
    }

    public function test_se_imprimen_varias_de_una_vez_en_orden(): void
    {
        $respuesta = $this->actingAs($this->usuario())
            ->post(route('lab_management.sample_labels.print'), [
                'codes' => ['2026-0002', '2026-0001'],
            ]);

        $respuesta->assertOk();
        $html = $respuesta->getContent();

        // Una etiqueta por muestra, y por número de muestra ascendente sin
        // importar en qué orden se eligieron.
        $this->assertSame(2, substr_count($html, 'class="sticker"'));
        $this->assertLessThan(strpos($html, '2026-0002'), strpos($html, '2026-0001'));
    }

    /**
     * El comentario del sistema anterior: una línea que sale en TODAS las
     * etiquetas de la tanda. Sin comentario, la fila NO se dibuja — una fila
     * vacía cambiaría el alto y sacaría la etiqueta de registro.
     */
    public function test_el_comentario_sale_en_la_etiqueta_y_sin_el_no_hay_fila(): void
    {
        $con = $this->actingAs($this->usuario())
            ->post(route('lab_management.sample_labels.print'), [
                'codes'   => ['2026-0001'],
                'comment' => 'Recontramuestra',
            ])->getContent();

        $this->assertStringContainsString('Recontramuestra', $con);
        $this->assertStringContainsString(__('labels.comment'), $con);

        $sin = $this->actingAs($this->usuario())
            ->post(route('lab_management.sample_labels.print'), ['codes' => ['2026-0001']])
            ->getContent();

        $this->assertStringNotContainsString(__('labels.comment'), $sin);
    }

    public function test_un_codigo_que_no_existe_no_imprime_nada(): void
    {
        $this->actingAs($this->usuario())
            ->post(route('lab_management.sample_labels.print'), ['codes' => ['2026-9999']])
            // El manejador global convierte el 404 en una redirección con
            // aviso, en vez de dejar la pantalla en blanco.
            ->assertRedirect();
    }

    public function test_cada_impresion_deja_constancia_de_quien_y_cuantas(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario)
            ->post(route('lab_management.sample_labels.print'), [
                'codes' => ['2026-0001', '2026-0002'],
            ])
            ->assertOk();

        $registro = AuditLog::where('event', 'labels_printed')->latest('id')->first();

        $this->assertNotNull($registro);
        $this->assertSame($usuario->id, $registro->user_id);
        $this->assertSame(Sample::class, $registro->auditable_type);
        $this->assertSame(2, $registro->new_values['count']);
        $this->assertSame(['2026-0001', '2026-0002'], $registro->new_values['samples']);
    }

    // ─── Fixtures ────────────────────────────────────────────────────────

    private function muestra(int $numero, string $codigo): Sample
    {
        return Sample::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'reception_id' => $this->entrega->id,
            'year' => 2026, 'number' => $numero, 'code' => $codigo,
        ]);
    }

    private function usuario(): User
    {
        $rol = Role::firstOrCreate(['name' => 'perfil_etiquetas', 'guard_name' => 'web'], ['description' => 'Prueba']);
        $rol->syncPermissions(Permission::where('name', 'receptions.view')->get());

        $usuario = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
