<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Chips rápidos del índice de clientes (customer_group: activos/inactivos/con
 * equipos/sin equipos). Adaptado de TrafoDex: se quitó
 * test_health_band_filters_transformers — el semáforo de salud (health_rating)
 * es del motor de diagnóstico de TrafoDex y no existe en LaboRep; los grupos
 * with_tx/without_tx pasaron a with_eq/without_eq sobre `equipment`.
 */
class PresetFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('languages')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish', 'iso_code' => 'es', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('locales')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'code' => 'es_AR', 'name' => 'Español', 'language_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('regions')->insertOrIgnore([['id' => 999, 'slug' => Str::random(22), 'name' => '__bs__', 'is_active' => false, 'deleted_at' => now(), 'deleted_description' => 'bs', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('countries')->insertOrIgnore([
            ['id' => 1, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Peru', 'iso_code' => 'PE', 'currency' => 'PEN', 'timezone' => 'UTC', 'default_locale_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Chile', 'iso_code' => 'CL', 'currency' => 'CLP', 'timezone' => 'UTC', 'default_locale_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('tenants')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Empresa 1', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);

        $this->admin = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $this->actingAs($this->admin);
    }

    private function eq(): Equipment
    {
        $t = (new Equipment())->forceFill([
            'slug' => 'eq-' . uniqid(), 'name' => 'Equipo ' . uniqid(),
            'serial' => Str::random(6), 'tag' => 'T',
            'tenant_id' => 1, 'created_by' => $this->admin->id,
        ]);
        $t->save();
        return $t;
    }

    private function customer(bool $active, bool $withEq = false): Customer
    {
        $c = (new Customer())->forceFill([
            'slug' => 'c-' . uniqid(), 'name' => 'C' . uniqid(), 'cod' => Str::random(8),
            'country_id' => 1, 'is_active' => $active, 'tenant_id' => 1, 'created_by' => $this->admin->id,
        ]);
        $c->save();
        if ($withEq) {
            $t = $this->eq();
            DB::table('equipment')->where('id', $t->id)->update(['customer_id' => $c->id]);
        }
        return $c;
    }

    public function test_customer_group_filters(): void
    {
        $this->customer(true, withEq: true);   // activo + con equipo
        $this->customer(true);                 // activo, sin equipo
        $this->customer(false);                // inactivo, sin equipo

        $g = fn (string $group) => Customer::filter(new Request(['customer_group' => $group]))->count();

        $this->assertSame(2, $g('active'));
        $this->assertSame(1, $g('inactive'));
        $this->assertSame(1, $g('with_eq'));
        $this->assertSame(2, $g('without_eq'));
        $this->assertSame(3, Customer::count());
    }
}
