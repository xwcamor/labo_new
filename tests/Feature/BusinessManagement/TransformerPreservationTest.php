<?php

namespace Tests\Feature\BusinessManagement;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\TransformerPreservation;
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
 * La pantalla de sistemas de preservación del aceite.
 *
 * La tabla, el modelo y el desplegable del formulario de equipos ya existían;
 * lo que faltaba era esto, así que la lista solo se podía alimentar por seeder
 * o por SQL a mano.
 *
 * Lo que se fija: que sea solo del super (es un catálogo global), que el nombre
 * no se repita ni cambiando la caja —el índice de la tabla es sobre
 * `lower(name)` y un `unique` textual dejaba pasar «Sellado» junto a «sellado»,
 * con el rechazo llegando como error de la base— y que no se dé de baja una
 * fila que algún equipo esté usando.
 */
class TransformerPreservationTest extends TestCase
{
    use RefreshDatabase;

    private TransformerPreservation $sistema;

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

        $this->sistema = TransformerPreservation::create([
            'name' => 'Sellado con nitrogeno', 'code' => 'N2', 'sort_order' => 1, 'is_active' => true,
        ]);

        app()->setLocale('es');
    }

    public function test_solo_el_super_administra_el_catalogo(): void
    {
        $comun = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);

        $this->actingAs($comun)
            ->get(route('business_management.transformer_preservations.index'))
            ->assertRedirect();
    }

    public function test_el_listado_dice_cuantos_equipos_usan_cada_fila(): void
    {
        // Es lo que hay que mirar antes de tocar una fila del catálogo.
        $this->equipoCon($this->sistema);

        $filas = $this->actingAs($this->super())
            ->get(route('business_management.transformer_preservations.index'))
            ->viewData('page')['props']['rows'];

        $this->assertCount(1, $filas);
        $this->assertSame(1, $filas[0]['equipment_count']);
    }

    public function test_se_crea_y_queda_disponible_para_los_equipos(): void
    {
        $this->actingAs($this->super())
            ->post(route('business_management.transformer_preservations.store'), [
                'name' => 'Respiracion libre', 'code' => 'RL', 'sort_order' => 2, 'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('transformer_preservations', ['name' => 'Respiracion libre']);
    }

    public function test_el_nombre_no_se_repite_ni_cambiando_la_caja(): void
    {
        $this->actingAs($this->super())
            ->post(route('business_management.transformer_preservations.store'), [
                'name' => 'SELLADO CON NITROGENO',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, TransformerPreservation::count());
    }

    public function test_editar_la_propia_fila_no_choca_consigo_misma(): void
    {
        $this->actingAs($this->super())
            ->put(route('business_management.transformer_preservations.update', $this->sistema->slug), [
                'name' => 'Sellado con nitrogeno', 'code' => 'N2-A', 'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('N2-A', $this->sistema->fresh()->code);
    }

    public function test_no_se_da_de_baja_una_fila_que_algun_equipo_usa(): void
    {
        // Borrarla dejaría al equipo con un sistema que ya no está en la lista,
        // y el informe imprimiría un hueco. Para eso está el interruptor.
        $this->equipoCon($this->sistema);

        $this->actingAs($this->super())
            ->delete(route('business_management.transformer_preservations.destroy', $this->sistema->slug))
            ->assertSessionHasErrors('delete');

        $this->assertNotSoftDeleted('transformer_preservations', ['id' => $this->sistema->id]);
    }

    public function test_una_fila_sin_uso_si_se_da_de_baja(): void
    {
        $this->actingAs($this->super())
            ->delete(route('business_management.transformer_preservations.destroy', $this->sistema->slug))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('transformer_preservations', ['id' => $this->sistema->id]);
    }

    // ─── Fixtures ────────────────────────────────────────────────────────

    private function equipoCon(TransformerPreservation $sistema): Equipment
    {
        $cliente = Customer::create(['slug' => Str::random(22), 'tenant_id' => 1, 'name' => 'Cliente']);

        return Equipment::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'customer_id' => $cliente->id,
            'name' => 'Transformador 01', 'serial' => 'TR-1',
            'transformer_preservation_id' => $sistema->id,
        ]);
    }

    private function super(): User
    {
        $rol = Role::firstOrCreate(['name' => 'super', 'guard_name' => 'web'], ['description' => 'Super']);

        $usuario = User::factory()->create(['tenant_id' => null, 'country_id' => 1, 'locale_id' => 1]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
