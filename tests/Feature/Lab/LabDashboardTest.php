<?php

namespace Tests\Feature\Lab;

use App\Models\Customer;
use App\Models\Reception;
use App\Models\Sample;
use App\Models\SampleReport;
use App\Models\SampleTest;
use App\Models\TestDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * La «Alerta de Pendientes» del laboratorio.
 *
 * Es la pantalla de inicio del sistema anterior, que resolvía sus contadores
 * con SQL crudo sobre TODA la base: cualquiera que entrara veía las recepciones
 * de todos los clientes. Acá se cuenta con Eloquent normal, así que el scope de
 * tenant se aplica solo — y eso es lo primero que fija este test.
 */
class LabDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([LaravelLocalizationRedirectFilter::class, LocaleSessionRedirect::class]);

        DB::table('languages')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish', 'iso_code' => 'es', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('locales')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'code' => 'es_PE', 'name' => 'Espanol', 'language_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('regions')->insertOrIgnore([['id' => 999, 'slug' => Str::random(22), 'name' => '__bs__', 'is_active' => false, 'deleted_at' => now(), 'deleted_description' => 'bs', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('countries')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Peru', 'iso_code' => 'PE', 'currency' => 'PEN', 'timezone' => 'America/Lima', 'default_locale_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('tenants')->insertOrIgnore([
            ['id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'slug' => Str::random(22), 'name' => 'Otro', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['receptions.view', 'worksheets.view'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
    }

    public function test_cuenta_lo_que_falta_por_etapa(): void
    {
        $prueba = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'acid', 'name' => 'Número Ácido',
        ]);

        // Una entrega vencida y sin orden de servicio.
        $vencida = $this->recepcion(['due_at' => now()->subDays(3), 'service_order' => null]);

        // Muestra sin equipo y sin pruebas pedidas.
        $this->muestra($vencida);

        // Muestra con prueba pendiente (falta cargar valores).
        $enProceso = $this->muestra($vencida);
        SampleTest::create(['sample_id' => $enProceso->id, 'test_definition_id' => $prueba->id, 'status' => SampleTest::STATUS_PENDING, 'tenant_id' => 1]);

        // Muestra terminada y sin informe: lista para informar.
        $lista = $this->muestra($vencida);
        SampleTest::create(['sample_id' => $lista->id, 'test_definition_id' => $prueba->id, 'status' => SampleTest::STATUS_VALIDATED, 'tenant_id' => 1]);

        // Informe emitido sin fecha de entrega.
        $informada = $this->muestra($vencida);
        SampleTest::create(['sample_id' => $informada->id, 'test_definition_id' => $prueba->id, 'status' => SampleTest::STATUS_REPORTED, 'tenant_id' => 1]);
        SampleReport::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'sample_id' => $informada->id,
            'year' => 2026, 'number' => 1, 'code' => 'REP-LAB-2026-0001',
            'kind' => SampleReport::KIND_PRIMARY, 'status' => SampleReport::STATUS_ISSUED,
            'issued_at' => now(),
        ]);

        $alertas = collect($this->actingAs($this->usuario())
            ->get(route('dashboard_management.dashboards.index'))
            ->viewData('page')['props']['labAlerts'])
            ->keyBy('key');

        $this->assertSame(1, $alertas['lab_overdue']['value']);
        $this->assertSame(1, $alertas['lab_no_order']['value']);
        $this->assertSame(4, $alertas['lab_no_equipment']['value'], 'las cuatro muestras están sin equipo');
        $this->assertSame(1, $alertas['lab_no_tests']['value']);
        $this->assertSame(1, $alertas['lab_no_values']['value']);
        $this->assertSame(1, $alertas['lab_no_report']['value'], 'la validada sin informe');
        $this->assertSame(1, $alertas['lab_undelivered']['value']);
    }

    public function test_no_cuenta_lo_de_otro_workspace(): void
    {
        // El sistema anterior contaba TODO para cualquiera. Acá el scope manda.
        $ajena = $this->recepcion(['tenant' => 2, 'due_at' => now()->subDays(5)]);
        $this->muestra($ajena, 2);

        $alertas = collect($this->actingAs($this->usuario())
            ->get(route('dashboard_management.dashboards.index'))
            ->viewData('page')['props']['labAlerts'])
            ->keyBy('key');

        $this->assertSame(0, $alertas['lab_overdue']['value']);
        $this->assertSame(0, $alertas['lab_no_equipment']['value']);
    }

    public function test_una_tarjeta_en_cero_no_lleva_enlace(): void
    {
        // Sin nada trabado, la tarjeta no invita a ir a buscar nada.
        $alertas = collect($this->actingAs($this->usuario())
            ->get(route('dashboard_management.dashboards.index'))
            ->viewData('page')['props']['labAlerts']);

        $this->assertTrue($alertas->every(fn ($a) => $a['value'] === 0 && $a['href'] === null));
    }

    // ─── Fixtures ────────────────────────────────────────────────────────

    private function usuario(int $tenant = 1): User
    {
        return User::factory()->create(['tenant_id' => $tenant, 'country_id' => 1, 'locale_id' => 1]);
    }

    private function recepcion(array $extra = []): Reception
    {
        $tenant = $extra['tenant'] ?? 1;
        unset($extra['tenant']);

        $cliente = Customer::create([
            'slug' => Str::random(22), 'tenant_id' => $tenant, 'name' => 'Cliente '.Str::random(5),
        ]);

        return Reception::create(array_merge([
            'slug' => Str::random(22), 'tenant_id' => $tenant,
            'customer_id' => $cliente->id, 'received_at' => now()->subDays(10),
            'status' => Reception::STATUS_CONFIRMED,
            'service_order' => '700012345',
        ], $extra));
    }

    private function muestra(Reception $recepcion, int $tenant = 1): Sample
    {
        $numero = Sample::withoutGlobalScopes()->count() + 1;

        return Sample::create([
            'slug' => Str::random(22), 'tenant_id' => $tenant,
            'reception_id' => $recepcion->id,
            'year' => 2026, 'number' => $numero,
            'code' => Sample::formatCode(2026, $numero),
        ]);
    }
}
