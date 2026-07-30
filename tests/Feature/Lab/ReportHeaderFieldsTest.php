<?php

namespace Tests\Feature\Lab;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Reception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Los campos de la cabecera del informe se pueden GUARDAR.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ESTE TEST EXISTE                                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * La marca comercial del aceite no estaba en `Equipment::$fillable`, y el
 * formulario del informe la escribe con `update()`. La asignación masiva de
 * Eloquent descarta lo que no está declarado **sin lanzar ningún error**: quien
 * tipeaba la marca veía el informe guardado, el dato no llegaba nunca al equipo,
 * y la próxima muestra del mismo transformador volvía a pedirla. El informe la
 * imprimía en raya.
 *
 * Es la clase de defecto que no se ve: no hay excepción, no hay mensaje, y la
 * pantalla dice que guardó. Solo se nota comparando lo que se escribió contra lo
 * que quedó, que es exactamente lo que hace este test.
 */
class ReportHeaderFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParentRows();
    }

    public function test_la_marca_del_aceite_se_guarda_en_el_equipo(): void
    {
        $equipo = $this->equipo();

        $equipo->update(['oil_brand' => 'Nynas Nytro Libra']);

        // Se relee de la base: comparar contra el objeto en memoria no detecta
        // el descarte, porque el atributo sí queda seteado en la instancia.
        $this->assertSame('Nynas Nytro Libra', $equipo->fresh()->oil_brand);
    }

    public function test_el_resto_de_la_placa_tambien_se_guarda(): void
    {
        // Los mismos campos que el informe imprime y que el formulario del
        // informe escribe en el equipo. Si alguno se cae de `$fillable`, el
        // papel vuelve a mostrar una raya donde hay un dato cargado.
        $equipo = $this->equipo();

        $equipo->update([
            'oil_volume'       => 12500,
            'oil_volume_unit'  => 'L',
            'manufacture_year' => 2005,
            'service_state'    => 'in_service',
        ]);

        $fresco = $equipo->fresh();

        $this->assertEquals(12500, $fresco->oil_volume);
        $this->assertSame('L', $fresco->oil_volume_unit);
        $this->assertSame(2005, $fresco->manufacture_year);
        $this->assertSame('in_service', $fresco->service_state);
    }

    public function test_el_contacto_y_el_usuario_final_se_guardan_en_la_recepcion(): void
    {
        // Viven en la recepción y el informe los imprime en su cabecera. Se
        // cargan al RECIBIR la muestra, no al emitir: quien recibe tiene el
        // correo del cliente delante.
        $recepcion = $this->recepcion();

        $recepcion->update([
            'contact_info' => 'contacto@ejemplo.com',
            'end_user'     => 'Gerencia de Mantenimiento',
        ]);

        $fresca = $recepcion->fresh();

        $this->assertSame('contacto@ejemplo.com', $fresca->contact_info);
        $this->assertSame('Gerencia de Mantenimiento', $fresca->end_user);
    }

    public function test_la_presion_del_laboratorio_se_guarda_en_la_hoja(): void
    {
        // El informe de cromatografía la imprime: no existía columna y el papel
        // mostraba una raya clavada.
        $prueba = \App\Models\TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'cromas', 'name' => 'Cromatografía',
        ]);

        $hoja = \App\Models\Worksheet::create([
            'slug' => Str::random(22), 'test_definition_id' => $prueba->id,
            'run_date' => now()->toDateString(), 'tenant_id' => 1,
            'lab_pressure_hpa' => 1013.4,
        ]);

        $this->assertEquals(1013.4, $hoja->fresh()->lab_pressure_hpa);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function equipo(): Equipment
    {
        $cliente = Customer::create([
            'slug' => Str::random(22), 'name' => 'Cliente ' . Str::random(6), 'tenant_id' => 1,
        ]);

        return Equipment::create([
            'slug' => Str::random(22), 'name' => 'Transformador de prueba',
            'customer_id' => $cliente->id, 'tenant_id' => 1, 'is_active' => true,
        ]);
    }

    private function recepcion(): Reception
    {
        $cliente = Customer::create([
            'slug' => Str::random(22), 'name' => 'Cliente ' . Str::random(6), 'tenant_id' => 1,
        ]);

        return Reception::create([
            'slug' => Str::random(22), 'customer_id' => $cliente->id,
            'received_at' => now(), 'tenant_id' => 1,
        ]);
    }

    private function seedParentRows(): void
    {
        DB::table('languages')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish',
            'iso_code' => 'es', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('tenants')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio',
            'created_at' => now(), 'updated_at' => now(),
        ]]);
    }
}
