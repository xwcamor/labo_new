<?php

namespace Tests\Feature\Lab;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Reception;
use App\Models\Sample;
use App\Models\Setting;
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
 * El pliego de etiquetas de los envases.
 *
 * En el sistema anterior esto era un CRUD ("Control de Stickers") donde se
 * daba de alta una etiqueta tipeando el número de muestra como texto libre, sin
 * relación con la muestra y sin registro de quién imprimía. Acá es una acción
 * sobre muestras que ya existen.
 *
 * Lo que se fija: que solo imprima muestras DE ESA entrega, que una entrega en
 * borrador no imprima nada (todavía no hay números que pegar), y que cada
 * impresión deje constancia en el registro de auditoría — que es exactamente
 * lo que la tabla `stickers` no guardaba.
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

    public function test_sin_el_permiso_de_ver_la_entrega_no_hay_etiquetas(): void
    {
        $intruso = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);

        $this->actingAs($intruso)
            ->get(route('lab_management.receptions.labels', $this->entrega->slug))
            ->assertRedirect();
    }

    public function test_el_pliego_sale_en_pdf_con_todas_las_muestras_de_la_entrega(): void
    {
        $respuesta = $this->actingAs($this->usuario())
            ->get(route('lab_management.receptions.labels', $this->entrega->slug));

        $respuesta->assertOk();
        $respuesta->assertHeader('content-type', 'application/pdf');

        $pdf = $respuesta->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_se_puede_imprimir_una_sola_muestra(): void
    {
        $una = $this->entrega->samples()->where('code', '2026-0002')->first();

        $this->actingAs($this->usuario())
            ->get(route('lab_management.receptions.labels', [$this->entrega->slug, 'samples' => [$una->id]]))
            ->assertOk();

        $registro = AuditLog::where('event', 'labels_printed')->latest('id')->first();

        $this->assertSame(1, $registro->new_values['count']);
        $this->assertSame(['2026-0002'], $registro->new_values['samples']);
    }

    public function test_un_id_de_otra_entrega_no_imprime_su_etiqueta(): void
    {
        // El filtro se aplica sobre las muestras de ESTA entrega. Sin eso,
        // pasar el id de una muestra ajena imprimía la etiqueta de otro cliente
        // desde una entrega que el usuario sí puede ver.
        $otra = Reception::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'customer_id' => $this->entrega->customer_id,
            'code' => '2026-0008', 'year' => 2026, 'number' => 8,
            'received_at' => '2026-03-05', 'status' => 'confirmed',
        ]);
        $ajena = Sample::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'reception_id' => $otra->id,
            'year' => 2026, 'number' => 99, 'code' => '2026-0099',
        ]);

        $this->actingAs($this->usuario())
            ->get(route('lab_management.receptions.labels', [$this->entrega->slug, 'samples' => [$ajena->id]]))
            // El manejador global convierte el 404 en una redirección con
            // aviso, en vez de dejar la pantalla en blanco.
            ->assertRedirect();
    }

    public function test_una_entrega_en_borrador_no_tiene_nada_que_etiquetar(): void
    {
        $borrador = Reception::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'customer_id' => $this->entrega->customer_id,
            'code' => null, 'received_at' => '2026-03-06', 'status' => 'draft',
        ]);

        $this->actingAs($this->usuario())
            ->get(route('lab_management.receptions.labels', $borrador->slug))
            ->assertRedirect();
    }

    public function test_cada_impresion_deja_constancia_de_quien_y_cuantas(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario)
            ->get(route('lab_management.receptions.labels', $this->entrega->slug))
            ->assertOk();

        $registro = AuditLog::where('event', 'labels_printed')->latest('id')->first();

        $this->assertNotNull($registro);
        $this->assertSame($usuario->id, $registro->user_id);
        $this->assertSame(Reception::class, $registro->auditable_type);
        $this->assertSame($this->entrega->id, $registro->auditable_id);
        $this->assertSame(2, $registro->new_values['count']);
        $this->assertSame(['2026-0001', '2026-0002'], $registro->new_values['samples']);
    }

    public function test_la_grilla_del_pliego_sale_de_los_ajustes(): void
    {
        // El tamaño de la etiqueta NO se configura: se deriva de la grilla para
        // que el pliego entre siempre en la hoja A4. Lo que se guarda es la
        // grilla usada, para poder explicar un pliego mal cortado.
        Setting::create(['key' => 'labels.columns', 'name' => 'Columnas', 'type' => 'int', 'value' => '2', 'group' => 'lab']);
        Setting::create(['key' => 'labels.rows', 'name' => 'Filas', 'type' => 'int', 'value' => '5', 'group' => 'lab']);

        $this->actingAs($this->usuario())
            ->get(route('lab_management.receptions.labels', $this->entrega->slug))
            ->assertOk();

        $registro = AuditLog::where('event', 'labels_printed')->latest('id')->first();

        $this->assertSame('2x5', $registro->new_values['grid']);
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
