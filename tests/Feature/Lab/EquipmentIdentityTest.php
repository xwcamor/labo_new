<?php

namespace Tests\Feature\Lab;

use App\Http\Requests\BusinessManagement\Equipment\StoreEquipmentRequest;
use App\Http\Requests\BusinessManagement\Equipment\UpdateEquipmentRequest;
use App\Models\Customer;
use App\Models\CustomerArea;
use App\Models\CustomerLocation;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Qué identifica a un equipo y de quién es.
 *
 * El formulario venía del scaffold de catálogos y traía tres cosas que este
 * dominio no admite:
 *
 *   1. Un campo CÓDIGO. La tabla `equipment` no tiene esa columna —la migración
 *      la excluye a propósito—, así que guardar reventaba con
 *      «no existe la columna "code"». Eso es lo que reportó el laboratorio.
 *   2. NOMBRE único por workspace, que impedía que dos clientes tuvieran cada
 *      uno su "Transformador Principal".
 *   3. Ningún campo de CLIENTE: todo lo cargado desde la pantalla quedaba sin
 *      dueño, y un equipo sin dueño no aparece en ninguna recepción.
 *
 * Lo que sí identifica al equipo es la chapa (serie + tag), igual que en el
 * sistema anterior (`validates_uniqueness_of :num_tag, scope: [:num_serie]`).
 */
class EquipmentIdentityTest extends TestCase
{
    use RefreshDatabase;

    private Customer $cliente;
    private Customer $otroCliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParentRows();

        // La unicidad es POR WORKSPACE, así que la regla mira el tenant del
        // usuario: sin sesión no encontraría nada y todo pasaría.
        $this->actingAs(User::factory()->create([
            'country_id' => 1, 'locale_id' => 1, 'tenant_id' => 1,
        ]));

        $this->cliente     = $this->makeCustomer('Energía del Sur');
        $this->otroCliente = $this->makeCustomer('Minera del Norte');
    }

    // ─── La identidad ────────────────────────────────────────────────────

    public function test_dos_clientes_pueden_tener_un_equipo_con_el_mismo_nombre(): void
    {
        // Es el caso normal: el laboratorio atiende a muchas empresas y todas
        // llaman "Transformador Principal" al suyo.
        Equipment::create([
            'slug' => Str::random(22), 'name' => 'Transformador Principal',
            'customer_id' => $this->cliente->id, 'tenant_id' => 1,
        ]);

        $errores = $this->validarAlta([
            'name' => 'Transformador Principal',
            'customer_id' => $this->otroCliente->id,
        ]);

        $this->assertSame([], $errores);
    }

    public function test_la_misma_serie_y_tag_no_se_repiten(): void
    {
        Equipment::create([
            'slug' => Str::random(22), 'name' => 'TR-1',
            'customer_id' => $this->cliente->id, 'tenant_id' => 1,
            'serial' => 'A-1000', 'tag' => 'TR-01',
        ]);

        $errores = $this->validarAlta([
            'name' => 'Otro nombre distinto',
            'customer_id' => $this->otroCliente->id,
            'serial' => 'a-1000',   // la comparación ignora mayúsculas
            'tag' => 'tr-01',
        ]);

        // Los DOS campos quedan marcados: el duplicado es del par, y señalar
        // uno solo se leería como si el problema fuera únicamente ese.
        $this->assertArrayHasKey('serial', $errores);
        $this->assertArrayHasKey('tag', $errores);
    }

    public function test_media_chapa_no_alcanza_para_ser_un_duplicado(): void
    {
        // El índice único de la tabla es parcial: solo cuenta cuando están los
        // dos. Un equipo con serie y sin tag se carga igual.
        Equipment::create([
            'slug' => Str::random(22), 'name' => 'TR-1',
            'customer_id' => $this->cliente->id, 'tenant_id' => 1,
            'serial' => 'A-1000', 'tag' => 'TR-01',
        ]);

        $errores = $this->validarAlta([
            'name' => 'TR-2',
            'customer_id' => $this->cliente->id,
            'serial' => 'A-1000',
        ]);

        $this->assertSame([], $errores);
    }

    public function test_al_editar_el_equipo_no_choca_consigo_mismo(): void
    {
        $equipo = Equipment::create([
            'slug' => Str::random(22), 'name' => 'TR-1',
            'customer_id' => $this->cliente->id, 'tenant_id' => 1,
            'serial' => 'A-1000', 'tag' => 'TR-01',
        ]);

        $datos = [
            'name' => 'TR-1 renombrado',
            'customer_id' => $this->cliente->id,
            'serial' => 'A-1000',
            'tag' => 'TR-01',
        ];

        $request = UpdateEquipmentRequest::create('/', 'PUT', $datos);
        $request->setRouteResolver(fn () => new class($equipo) {
            public function __construct(private $equipo)
            {
            }

            public function parameter($nombre)
            {
                return $this->equipo;
            }
        });

        $this->assertSame([], $this->correr($request, $datos));
    }

    // ─── El dueño ────────────────────────────────────────────────────────

    public function test_el_cliente_es_obligatorio(): void
    {
        // Sin cliente el equipo no aparece en ninguna recepción, así que
        // guardarlo es cargar un registro invisible.
        $errores = $this->validarAlta(['name' => 'Transformador huérfano']);

        $this->assertArrayHasKey('customer_id', $errores);
    }

    public function test_la_ubicacion_tiene_que_ser_del_cliente(): void
    {
        $ajena = CustomerLocation::create([
            'slug' => Str::random(22), 'customer_id' => $this->otroCliente->id,
            'name' => 'Planta ajena', 'tenant_id' => 1,
        ]);

        $errores = $this->validarAlta([
            'name' => 'TR-9',
            'customer_id' => $this->cliente->id,
            'customer_location_id' => $ajena->id,
        ]);

        $this->assertArrayHasKey('customer_location_id', $errores);
    }

    public function test_el_area_tiene_que_ser_de_la_ubicacion(): void
    {
        $propia = CustomerLocation::create([
            'slug' => Str::random(22), 'customer_id' => $this->cliente->id,
            'name' => 'Planta propia', 'tenant_id' => 1,
        ]);
        $otraUbicacion = CustomerLocation::create([
            'slug' => Str::random(22), 'customer_id' => $this->cliente->id,
            'name' => 'Otra planta', 'tenant_id' => 1,
        ]);
        $areaDeLaOtra = CustomerArea::create([
            'slug' => Str::random(22), 'customer_location_id' => $otraUbicacion->id,
            'name' => 'Patio 1', 'tenant_id' => 1,
        ]);

        $errores = $this->validarAlta([
            'name' => 'TR-9',
            'customer_id' => $this->cliente->id,
            'customer_location_id' => $propia->id,
            'customer_area_id' => $areaDeLaOtra->id,
        ]);

        $this->assertArrayHasKey('customer_area_id', $errores);
    }

    // ─── El código que no existe ─────────────────────────────────────────

    public function test_no_queda_ninguna_regla_sobre_una_columna_inexistente(): void
    {
        // La regla del scaffold consultaba `LOWER(code)`. Guardar cualquier
        // equipo lanzaba un error de SQL, no un error de validación: por eso el
        // laboratorio veía «no existe la columna "code"» en la pantalla.
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('equipment', 'code'),
            'La tabla equipment no lleva `code`: un equipo se identifica por serie y tag.',
        );

        $reglas = (new StoreEquipmentRequest())->rules();

        $this->assertArrayNotHasKey('code', $reglas);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

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

    private function makeCustomer(string $nombre): Customer
    {
        return Customer::create([
            'slug' => Str::random(22), 'name' => $nombre, 'tenant_id' => 1,
        ]);
    }

    /**
     * @param array<string,mixed> $datos
     *
     * @return array<string,mixed> errores por campo
     */
    private function validarAlta(array $datos): array
    {
        return $this->correr(StoreEquipmentRequest::create('/', 'POST', $datos), $datos);
    }

    /**
     * @param array<string,mixed> $datos
     *
     * @return array<string,mixed>
     */
    private function correr($request, array $datos): array
    {
        $validador = Validator::make($datos, $request->rules());

        return $validador->fails() ? $validador->errors()->toArray() : [];
    }
}
