<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Reception;
use App\Models\Sample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('languages')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'ES', 'iso_code' => 'es', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('locales')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'code' => 'es_AR', 'name' => 'Español', 'language_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('regions')->insertOrIgnore([['id' => 999, 'slug' => Str::random(22), 'name' => '__bs__', 'is_active' => false, 'deleted_at' => now(), 'deleted_description' => 'bs', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('countries')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Peru', 'iso_code' => 'PE', 'currency' => 'PEN', 'timezone' => 'UTC', 'default_locale_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('tenants')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'T1', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('tenants')->insertOrIgnore([['id' => 2, 'slug' => Str::random(22), 'name' => 'T2', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['equipment.view', 'receptions.view', 'customers.view'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['description' => 'a']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web'], ['description' => 'u']);

        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
        ]);
    }

    private function actor(string $role, array $perms = [], int $tenant = 1): User
    {
        $u = User::factory()->create(['tenant_id' => $tenant, 'country_id' => 1, 'locale_id' => 1]);
        $u->assignRole($role);
        $u->givePermissionTo($perms);
        return $u;
    }

    public function test_searches_equipment_and_customers_of_own_tenant(): void
    {
        $cust = Customer::create(['slug' => 'c1', 'name' => 'Minera Andina', 'tenant_id' => 1, 'created_by' => 1]);
        Equipment::create(['slug' => 'eq1', 'name' => 'Trafo principal', 'serial' => 'ABC-123', 'tag' => 'T1', 'customer_id' => $cust->id, 'tenant_id' => 1, 'created_by' => 1]);
        // Otro workspace: no debe aparecer.
        Equipment::create(['slug' => 'eq2', 'name' => 'Ajeno', 'serial' => 'ABC-999', 'tag' => 'T9', 'tenant_id' => 2, 'created_by' => 1]);

        $this->actingAs($this->actor('admin', ['equipment.view', 'customers.view']));

        $res = $this->getJson(route('search', ['q' => 'ABC']))->assertOk();
        $res->assertJsonCount(1, 'equipment');
        $res->assertJsonPath('equipment.0.serial', 'ABC-123');

        $this->getJson(route('search', ['q' => 'Andina']))->assertOk()->assertJsonPath('customers.0.name', 'Minera Andina');
    }

    public function test_finds_the_sample_by_its_code(): void
    {
        // El correlativo es lo que el cliente cita por teléfono, y el resultado
        // lleva a la ficha de su ENTREGA, que es donde la muestra se trabaja.
        $cust = Customer::create(['slug' => 'c1', 'name' => 'Minera Andina', 'tenant_id' => 1, 'created_by' => 1]);
        $rec = Reception::create(['slug' => 'rec-1', 'customer_id' => $cust->id, 'received_at' => now(), 'tenant_id' => 1, 'status' => 'confirmed']);
        Sample::create(['slug' => Str::random(22), 'reception_id' => $rec->id, 'year' => 2026, 'number' => 695, 'code' => '2026-0695', 'tenant_id' => 1, 'is_urgent' => false]);

        $this->actingAs($this->actor('admin', ['receptions.view']));

        $res = $this->getJson(route('search', ['q' => '0695']))->assertOk();
        $res->assertJsonPath('samples.0.code', '2026-0695');
        $res->assertJsonPath('samples.0.reception', 'rec-1');
        $res->assertJsonPath('samples.0.customer', 'Minera Andina');
    }

    public function test_respects_permissions_and_min_length(): void
    {
        Equipment::create(['slug' => 'eq1', 'name' => 'X', 'serial' => 'XYZ-1', 'tag' => 'T', 'tenant_id' => 1, 'created_by' => 1]);

        // Sin permiso de equipos, el buscador no los devuelve: esconder el
        // modulo del menu no serviria de nada si la busqueda lo salteara.
        $this->actingAs($this->actor('user', []));
        $this->getJson(route('search', ['q' => 'XYZ']))->assertOk()->assertJsonCount(0, 'equipment');

        // Consulta muy corta: vacio, sin barrer la tabla.
        $this->actingAs($this->actor('admin', ['equipment.view']));
        $this->getJson(route('search', ['q' => 'X']))->assertOk()->assertJsonCount(0, 'equipment');
    }
}
