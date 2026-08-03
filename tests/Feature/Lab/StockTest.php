<?php

namespace Tests\Feature\Lab;

use App\Models\StockItem;
use App\Models\StockLoan;
use App\Models\StockLoanLine;
use App\Models\StockReturn;
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
 * El almacén: artículos, préstamos y devoluciones.
 *
 * Lo que se fija son las cuatro reglas que el sistema anterior NO tenía y que
 * son la razón de portar el módulo en vez de copiarlo:
 *
 *  1. no se presta sin decir quién se lo lleva;
 *  2. no se presta más de lo disponible (sumando por artículo, no por línea);
 *  3. no se devuelve más de lo que falta;
 *  4. el estado se ESCRIBE al ocurrir y es reversible.
 */
class StockTest extends TestCase
{
    use RefreshDatabase;

    private StockItem $articulo;

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
        foreach ([
            'stock_items.view', 'stock_items.create', 'stock_items.edit', 'stock_items.delete',
            'stock_loans.view', 'stock_loans.create', 'stock_loans.edit', 'stock_loans.delete',
        ] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $this->articulo = StockItem::create([
            'slug' => Str::random(22), 'tenant_id' => 1,
            'code' => 'MAT-01', 'name' => 'Matraz aforado 250 mL', 'unit' => 'Unidad',
            'on_hand' => 10, 'min_qty' => 2, 'is_active' => true,
        ]);

        app()->setLocale('es');
    }

    // ─── Acceso ──────────────────────────────────────────────────────────

    public function test_sin_permiso_no_se_ve_el_almacen(): void
    {
        $intruso = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);

        $this->actingAs($intruso)->get(route('lab_management.stock_items.index'))->assertRedirect();
        $this->actingAs($intruso)->get(route('lab_management.stock_loans.index'))->assertRedirect();
    }

    // ─── Artículos ───────────────────────────────────────────────────────

    public function test_el_listado_dice_cuanto_hay_cuanto_esta_afuera_y_cuanto_queda(): void
    {
        // Es LA diferencia con el listado del sistema anterior, que mostraba
        // solo el número tipeado a mano.
        $this->prestar(4);

        $fila = $this->actingAs($this->usuario())
            ->get(route('lab_management.stock_items.index'))
            ->viewData('page')['props']['rows']['data'][0];

        $this->assertSame(10, $fila['on_hand']);
        $this->assertSame(4, $fila['on_loan']);
        $this->assertSame(6, $fila['available']);
    }

    public function test_el_articulo_bajo_minimo_queda_marcado(): void
    {
        // Disponible 1 con un mínimo de 2. Lo que se mira es lo DISPONIBLE, no
        // la existencia declarada: los otros nueve están repartidos afuera.
        $this->prestar(9);

        $fila = $this->actingAs($this->usuario())
            ->get(route('lab_management.stock_items.index'))
            ->viewData('page')['props']['rows']['data'][0];

        $this->assertSame(1, $fila['available']);
        $this->assertTrue($fila['is_low']);
    }

    public function test_un_articulo_con_material_afuera_no_se_da_de_baja(): void
    {
        $this->prestar(2);

        $this->actingAs($this->usuario())
            ->delete(route('lab_management.stock_items.destroy', $this->articulo->slug))
            ->assertSessionHasErrors('delete');

        $this->assertNotSoftDeleted('stock_items', ['id' => $this->articulo->id]);
    }

    public function test_el_codigo_no_se_repite(): void
    {
        $this->actingAs($this->usuario())
            ->post(route('lab_management.stock_items.store'), [
                'code' => 'MAT-01', 'name' => 'Otro', 'on_hand' => 1,
            ])
            ->assertSessionHasErrors('code');
    }

    // ─── Préstamos ───────────────────────────────────────────────────────

    public function test_no_se_presta_sin_decir_quien_se_lo_lleva(): void
    {
        // El agujero de fondo del sistema anterior: el préstamo tenía una
        // descripción de texto libre y nada más.
        $this->actingAs($this->usuario())
            ->post(route('lab_management.stock_loans.store'), [
                'loaned_on' => now()->toDateString(),
                'purpose'   => 'Ensayo de rigidez',
                'lines'     => [['stock_item_id' => $this->articulo->id, 'qty' => 2]],
            ])
            ->assertSessionHasErrors('borrower_user_id');

        $this->assertDatabaseCount('stock_loans', 0);
    }

    public function test_no_se_presta_mas_de_lo_disponible(): void
    {
        $this->actingAs($this->usuario())
            ->post(route('lab_management.stock_loans.store'), [
                'loaned_on'     => now()->toDateString(),
                'borrower_name' => 'Ana',
                'lines'         => [['stock_item_id' => $this->articulo->id, 'qty' => 11]],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertDatabaseCount('stock_loans', 0);
    }

    public function test_dos_lineas_del_mismo_articulo_se_suman_antes_de_comparar(): void
    {
        // Seis y seis son doce, y hay diez. Mirándolas de a una pasaban las dos.
        $this->actingAs($this->usuario())
            ->post(route('lab_management.stock_loans.store'), [
                'loaned_on'     => now()->toDateString(),
                'borrower_name' => 'Ana',
                'lines'         => [
                    ['stock_item_id' => $this->articulo->id, 'qty' => 6],
                    ['stock_item_id' => $this->articulo->id, 'qty' => 6],
                ],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertDatabaseCount('stock_loans', 0);
    }

    public function test_el_prestamo_nace_abierto_con_sus_lineas(): void
    {
        $this->actingAs($this->usuario())
            ->post(route('lab_management.stock_loans.store'), [
                'loaned_on'     => now()->toDateString(),
                'borrower_name' => 'Ana Quispe',
                'purpose'       => 'Ensayo de rigidez',
                'lines'         => [['stock_item_id' => $this->articulo->id, 'qty' => 3, 'notes' => 'Caja azul']],
            ])
            ->assertRedirect(route('lab_management.stock_loans.index'));

        $prestamo = StockLoan::first();

        $this->assertSame(StockLoan::STATUS_OPEN, $prestamo->status);
        $this->assertSame('Ana Quispe', $prestamo->borrowerLabel());
        $this->assertNull($prestamo->returned_at);
        $this->assertSame(3, $prestamo->lines()->first()->qty);
    }

    // ─── Devoluciones ────────────────────────────────────────────────────

    public function test_no_se_devuelve_mas_de_lo_que_falta(): void
    {
        $prestamo = $this->prestar(3);
        $linea = $prestamo->lines()->first();

        $this->actingAs($this->usuario())
            ->post(route('lab_management.stock_loans.returns.store', $prestamo->slug), [
                'stock_loan_line_id' => $linea->id,
                'returned_on'        => now()->toDateString(),
                'qty'                => 4,
            ])
            ->assertSessionHasErrors('qty');

        $this->assertDatabaseCount('stock_returns', 0);
    }

    public function test_la_devolucion_no_puede_ser_anterior_al_prestamo(): void
    {
        $prestamo = $this->prestar(3, now()->subDays(2)->toDateString());
        $linea = $prestamo->lines()->first();

        $this->actingAs($this->usuario())
            ->post(route('lab_management.stock_loans.returns.store', $prestamo->slug), [
                'stock_loan_line_id' => $linea->id,
                'returned_on'        => now()->subDays(5)->toDateString(),
                'qty'                => 1,
            ])
            ->assertSessionHasErrors('returned_on');
    }

    public function test_la_devolucion_parcial_deja_el_prestamo_abierto_y_la_total_lo_cierra(): void
    {
        $prestamo = $this->prestar(10);
        $linea = $prestamo->lines()->first();

        $this->devolver($prestamo, $linea, 6);

        $prestamo->refresh();
        $this->assertSame(StockLoan::STATUS_OPEN, $prestamo->status);
        $this->assertNull($prestamo->returned_at);
        $this->assertSame(4, $linea->fresh()->load('returns')->pending());

        $this->devolver($prestamo, $linea, 4);

        $prestamo->refresh();
        $this->assertSame(StockLoan::STATUS_RETURNED, $prestamo->status);
        $this->assertNotNull($prestamo->returned_at);
    }

    public function test_dar_de_baja_una_devolucion_vuelve_a_abrir_el_prestamo(): void
    {
        // El estado no solo avanza: una devolución mal cargada se corrige sin
        // tocar la base a mano.
        $prestamo = $this->prestar(2);
        $linea = $prestamo->lines()->first();

        $this->devolver($prestamo, $linea, 2);
        $this->assertSame(StockLoan::STATUS_RETURNED, $prestamo->fresh()->status);

        $devolucion = StockReturn::first();

        $this->actingAs($this->usuario())
            ->delete(route('lab_management.stock_loans.returns.destroy', [$prestamo->slug, $devolucion->id]))
            ->assertRedirect();

        $prestamo->refresh();
        $this->assertSame(StockLoan::STATUS_OPEN, $prestamo->status);
        $this->assertNull($prestamo->returned_at);
    }

    public function test_una_devolucion_de_otro_prestamo_no_se_da_de_baja_desde_este(): void
    {
        $uno = $this->prestar(2);
        $otro = $this->prestar(2);

        $this->devolver($otro, $otro->lines()->first(), 1);
        $ajena = StockReturn::first();

        $this->actingAs($this->usuario())
            ->delete(route('lab_management.stock_loans.returns.destroy', [$uno->slug, $ajena->id]))
            ->assertRedirect();

        $this->assertDatabaseCount('stock_returns', 1);
    }

    public function test_lo_devuelto_vuelve_a_estar_disponible(): void
    {
        $prestamo = $this->prestar(4);
        $this->assertSame(6, $this->articulo->fresh()->available());

        $this->devolver($prestamo, $prestamo->lines()->first(), 4);

        $this->assertSame(10, $this->articulo->fresh()->available());
        // Y la existencia declarada NO se tocó en ningún momento: no es un
        // saldo contable, es lo que el laboratorio dice tener.
        $this->assertSame(10, $this->articulo->fresh()->on_hand);
    }

    public function test_las_lineas_dadas_de_baja_no_cuentan_como_prestadas(): void
    {
        // El sistema anterior listaba las líneas vivas pero sumaba TODAS, así
        // que dar de baja una cambiaba la pantalla y no el estado.
        $prestamo = $this->prestar(4);
        $prestamo->lines()->first()->delete();

        $this->assertSame(0, $this->articulo->fresh()->onLoan());
    }

    // ─── Fixtures ────────────────────────────────────────────────────────

    private function prestar(int $cantidad, ?string $fecha = null): StockLoan
    {
        $prestamo = StockLoan::create([
            'slug' => Str::random(22), 'tenant_id' => 1,
            'loaned_on' => $fecha ?? now()->toDateString(),
            'borrower_name' => 'Ana Quispe',
            'status' => StockLoan::STATUS_OPEN,
        ]);

        StockLoanLine::create([
            'stock_loan_id' => $prestamo->id,
            'stock_item_id' => $this->articulo->id,
            'qty'           => $cantidad,
        ]);

        return $prestamo->fresh('lines');
    }

    private function devolver(StockLoan $prestamo, StockLoanLine $linea, int $cantidad): void
    {
        $this->actingAs($this->usuario())
            ->post(route('lab_management.stock_loans.returns.store', $prestamo->slug), [
                'stock_loan_line_id' => $linea->id,
                'returned_on'        => now()->toDateString(),
                'qty'                => $cantidad,
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * El usuario del almacén, con los ocho permisos.
     *
     * Se memoiza POR PRUEBA (`$this->cache`) y no en un `static`: con un static
     * el objeto sobrevive a `RefreshDatabase` y las pruebas siguientes actúan
     * como un usuario cuya fila ya no existe — todo pasa a responder redirección
     * y el fallo no se parece en nada a la causa.
     */
    private ?User $cache = null;

    private function usuario(): User
    {
        if ($this->cache) {
            return $this->cache;
        }

        $rol = Role::firstOrCreate(['name' => 'perfil_almacen', 'guard_name' => 'web'], ['description' => 'Prueba']);
        $rol->syncPermissions(Permission::whereIn('name', [
            'stock_items.view', 'stock_items.create', 'stock_items.edit', 'stock_items.delete',
            'stock_loans.view', 'stock_loans.create', 'stock_loans.edit', 'stock_loans.delete',
        ])->get());

        $this->cache = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $this->cache->assignRole($rol);

        return $this->cache;
    }
}
