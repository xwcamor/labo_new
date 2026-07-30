<?php

namespace Tests\Feature\Lab;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\OilType;
use App\Models\Reception;
use App\Models\Sample;
use App\Models\SampleReport;
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
 * El listado global de informes — el "Listado de Nº de Reportes".
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ SE FIJA ACÁ                                                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Que el ORDEN y la BÚSQUEDA los resuelva la base y no el navegador. No es un
 * gusto de arquitectura: el sistema anterior hacía esta pantalla con DataTables
 * —todas las filas al cliente— y recalculaba el estado de cada muestra AL LEER,
 * así que un cliente con 130 pruebas pedidas convertía cada visita al listado en
 * un recorrido con escrituras. Si mañana alguien "simplifica" el controlador
 * mandando la colección entera, estos tests se caen.
 *
 * Y que el aislamiento por workspace exista: las muestras entran por JOIN, de
 * modo que el scope global de `Sample` NO se aplica. Sin el filtro explícito el
 * listado sería cross-tenant, que es la peor falla posible en un multi-tenant.
 */
class SampleReportIndexTest extends TestCase
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
            ['id' => 2, 'slug' => Str::random(22), 'name' => 'Otro laboratorio', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'receptions.view', 'guard_name' => 'web']);
    }

    // ─── Lo que se ve ────────────────────────────────────────────────────

    public function test_el_listado_trae_las_columnas_del_sistema_anterior(): void
    {
        $this->informe([
            'cliente'  => 'RED DE ENERGIA DEL PERU',
            'serie'    => 'TR-99887',
            'tension'  => [220, 60, 10],
            'potencia' => [50, 62.5, null],
        ]);

        $respuesta = $this->actingAs($this->usuario())
            ->get(route('lab_management.sample_reports.index'));

        $respuesta->assertOk();
        $fila = $respuesta->viewData('page')['props']['reports']['data'][0];

        $this->assertSame('RED DE ENERGIA DEL PERU', $fila['customer_name']);
        $this->assertSame('TR-99887', $fila['equipment_serial']);
        $this->assertSame('Transformador de potencia', $fila['equipment_type']);
        $this->assertSame('Mineral', $fila['oil_type']);
        // La placa: la MAYOR de las que declara, como el
        // `num_ten.split('/').max` del sistema anterior.
        $this->assertEquals(220, $fila['voltage_kv']);
        $this->assertEquals(62.5, $fila['power_mva']);
    }

    public function test_el_equipo_sin_placa_no_inventa_un_cero(): void
    {
        // Un 0 en la columna se lee como "cero kV". Lo que corresponde es decir
        // que no está declarado, y eso lo tiene que decidir la consulta: la
        // pantalla no puede distinguir un 0 real de un 0 de relleno.
        $this->informe(['tension' => [null, null, null], 'potencia' => [null, null, null]]);

        $fila = $this->actingAs($this->usuario())
            ->get(route('lab_management.sample_reports.index'))
            ->viewData('page')['props']['reports']['data'][0];

        $this->assertNull($fila['voltage_kv']);
        $this->assertNull($fila['power_mva']);
    }

    // ─── La búsqueda la resuelve la base ─────────────────────────────────

    public function test_la_busqueda_por_columna_filtra_en_la_consulta(): void
    {
        $this->informe(['cliente' => 'RED DE ENERGIA DEL PERU']);
        $this->informe(['cliente' => 'SOUTHERN PERU COPPER']);

        $props = $this->actingAs($this->usuario())
            ->get(route('lab_management.sample_reports.index', ['customer_name' => 'southern']))
            ->assertOk()
            ->viewData('page')['props'];

        // UNA fila, no dos filtradas después: el total paginado también baja.
        $this->assertSame(1, $props['reports']['total']);
        $this->assertSame('SOUTHERN PERU COPPER', $props['reports']['data'][0]['customer_name']);
    }

    public function test_el_buscador_global_mira_todas_las_columnas_de_texto(): void
    {
        $this->informe(['cliente' => 'RED DE ENERGIA DEL PERU', 'serie' => 'TR-11111']);
        $this->informe(['cliente' => 'SOUTHERN PERU COPPER', 'serie' => 'TR-22222']);

        // El número de serie no está en ninguna casilla de columna: se escribe
        // en el buscador de arriba, que es lo que se hace cuando se lo tiene en
        // un correo y no se sabe de qué columna es.
        $props = $this->actingAs($this->usuario())
            ->get(route('lab_management.sample_reports.index', ['q' => 'TR-22222']))
            ->viewData('page')['props'];

        $this->assertSame(1, $props['reports']['total']);
    }

    public function test_el_orden_lo_resuelve_el_motor(): void
    {
        $bajo = $this->informe(['tension' => [60, 10, null]]);
        $alto = $this->informe(['tension' => [500, 220, 33]]);

        $ids = fn (string $direccion) => collect(
            $this->actingAs($this->usuario())
                ->get(route('lab_management.sample_reports.index', [
                    'sort' => 'voltage_kv', 'direction' => $direccion,
                ]))
                ->viewData('page')['props']['reports']['data'],
        )->pluck('id')->all();

        // Ordenar por la MAYOR de las tensiones es una expresión SQL, no una
        // columna: si el orden se hiciera en la pantalla, ordenaría solo la
        // página que tiene delante.
        $this->assertSame([$alto->id, $bajo->id], $ids('desc'));
        $this->assertSame([$bajo->id, $alto->id], $ids('asc'));
    }

    public function test_una_columna_que_no_esta_en_la_lista_blanca_no_ordena(): void
    {
        // El nombre de la columna llega por la URL. Sin lista blanca sería una
        // inyección en el ORDER BY.
        $this->informe([]);

        $this->actingAs($this->usuario())
            ->get(route('lab_management.sample_reports.index', ['sort' => 'notes) --']))
            ->assertOk();
    }

    public function test_la_pantalla_recibe_una_pagina_no_el_listado_entero(): void
    {
        // Lo que reventó en el sistema anterior: mandarle todo al navegador.
        foreach (range(1, 12) as $i) {
            $this->informe([]);
        }

        $props = $this->actingAs($this->usuario())
            ->get(route('lab_management.sample_reports.index', ['per_page' => 10]))
            ->viewData('page')['props'];

        $this->assertCount(10, $props['reports']['data']);
        $this->assertSame(12, $props['reports']['total']);
    }

    // ─── Aislamiento ─────────────────────────────────────────────────────

    public function test_no_se_ven_los_informes_de_otro_workspace(): void
    {
        $mio  = $this->informe(['tenant' => 1]);
        $ajeno = $this->informe(['tenant' => 2]);

        $datos = $this->actingAs($this->usuario(1))
            ->get(route('lab_management.sample_reports.index'))
            ->viewData('page')['props']['reports']['data'];

        $ids = collect($datos)->pluck('id')->all();
        $this->assertContains($mio->id, $ids);
        $this->assertNotContains($ajeno->id, $ids);
    }

    public function test_sin_permiso_no_se_entra(): void
    {
        $sinPermiso = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $sinPermiso->assignRole(Role::create(['name' => 'mirar_'.Str::random(6), 'guard_name' => 'web', 'description' => 'Prueba']));

        $this->actingAs($sinPermiso)
            ->get(route('lab_management.sample_reports.index'))
            ->assertRedirect(route('dashboard_management.dashboards.index'));
    }

    public function test_una_muestra_dada_de_baja_saca_su_informe_del_listado(): void
    {
        $informe = $this->informe([]);
        $informe->sample->delete();

        $props = $this->actingAs($this->usuario())
            ->get(route('lab_management.sample_reports.index'))
            ->viewData('page')['props'];

        $this->assertSame(0, $props['reports']['total']);
    }


    // ─── El desbloqueo, por la ruta ──────────────────────────────────────

    /**
     * Desbloquear un informe emitido pide admin o super, no solo poder editar.
     *
     * En el sistema anterior las dos acciones —emitir y desbloquear— estaban bajo
     * el MISMO permiso (el 42), así que cualquiera que pudiera cargar un informe
     * podía desbloquear uno ya entregado al cliente.
     */
    public function test_sin_ser_admin_no_se_desbloquea_un_informe(): void
    {
        Permission::firstOrCreate(['name' => 'receptions.edit', 'guard_name' => 'web']);

        $informe = $this->informe([]);

        $rol = Role::create(['name' => 'analista_'.Str::random(6), 'guard_name' => 'web', 'description' => 'Prueba']);
        $rol->syncPermissions(Permission::whereIn('name', ['receptions.view', 'receptions.edit'])->get());

        $analista = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $analista->assignRole($rol);

        $this->actingAs($analista)
            ->post(route('lab_management.sample_reports.unissue', $informe->slug), [
                'reason' => 'Quiero corregir un valor',
            ])
            // El proyecto convierte el 403 en una redirección al tablero.
            ->assertRedirect(route('dashboard_management.dashboards.index'));

        $this->assertSame(SampleReport::STATUS_ISSUED, $informe->fresh()->status);
    }

    public function test_el_desbloqueo_exige_motivo(): void
    {
        Permission::firstOrCreate(['name' => 'receptions.edit', 'guard_name' => 'web']);

        $informe = $this->informe([]);

        $rol = Role::create(['name' => 'admin', 'guard_name' => 'web', 'description' => 'Prueba']);
        $rol->syncPermissions(Permission::whereIn('name', ['receptions.view', 'receptions.edit'])->get());

        $admin = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $admin->assignRole($rol);

        // Sin motivo: rebota y el informe sigue emitido.
        $this->actingAs($admin)
            ->post(route('lab_management.sample_reports.unissue', $informe->slug), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(SampleReport::STATUS_ISSUED, $informe->fresh()->status);

        // Con motivo: vuelve a borrador.
        $this->actingAs($admin)
            ->post(route('lab_management.sample_reports.unissue', $informe->slug), [
                'reason' => 'La rigidez estaba mal tipeada',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(SampleReport::STATUS_DRAFT, $informe->fresh()->status);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function usuario(int $tenant = 1): User
    {
        $rol = Role::create(['name' => 'perfil_'.Str::random(6), 'guard_name' => 'web', 'description' => 'Prueba']);
        $rol->syncPermissions(Permission::where('name', 'receptions.view')->get());

        $usuario = User::factory()->create(['tenant_id' => $tenant, 'country_id' => 1, 'locale_id' => 1]);
        $usuario->assignRole($rol);

        return $usuario;
    }

    /**
     * Un informe con su cadena entera: cliente → entrega → muestra → equipo.
     *
     * @param  array<string,mixed>  $datos
     */
    private function informe(array $datos): SampleReport
    {
        $tenant = $datos['tenant'] ?? 1;

        $tipo = EquipmentType::firstOrCreate(
            ['name' => 'Transformador de potencia'],
            ['slug' => Str::random(22), 'tenant_id' => null],
        );
        $aceite = OilType::firstOrCreate(
            ['name' => 'Mineral'],
            ['slug' => Str::random(22), 'tenant_id' => null],
        );

        $cliente = Customer::create([
            'slug' => Str::random(22), 'tenant_id' => $tenant,
            'name' => $datos['cliente'] ?? 'Cliente '.Str::random(6),
        ]);

        $tension  = $datos['tension'] ?? [220, 60, null];
        $potencia = $datos['potencia'] ?? [50, null, null];

        $equipo = Equipment::create([
            'slug' => Str::random(22), 'tenant_id' => $tenant,
            'customer_id' => $cliente->id,
            'equipment_type_id' => $tipo->id,
            'oil_type_id' => $aceite->id,
            'name' => 'Equipo '.Str::random(4),
            'serial' => $datos['serie'] ?? 'TR-'.Str::random(5),
            'voltage_kv_hv' => $tension[0], 'voltage_kv_lv' => $tension[1], 'voltage_kv_tv' => $tension[2] ?? null,
            'power_mva' => $potencia[0], 'power_mva_2' => $potencia[1], 'power_mva_3' => $potencia[2] ?? null,
        ]);

        $entrega = Reception::create([
            'slug' => Str::random(22), 'tenant_id' => $tenant,
            'customer_id' => $cliente->id, 'received_at' => now(),
            'status' => Reception::STATUS_CONFIRMED,
            'service_order' => $datos['os'] ?? '7000'.random_int(10000, 99999),
        ]);

        $numero = Sample::withoutGlobalScopes()->count() + 1;
        $muestra = Sample::create([
            'slug' => Str::random(22), 'tenant_id' => $tenant,
            'reception_id' => $entrega->id, 'equipment_id' => $equipo->id,
            'year' => 2026, 'number' => $numero, 'code' => Sample::formatCode(2026, $numero),
            'sampling_reason' => $datos['razon'] ?? 'Mantenimiento programado',
            'is_urgent' => false,
        ]);

        return SampleReport::create([
            'slug' => Str::random(22), 'tenant_id' => $tenant,
            'sample_id' => $muestra->id,
            'year' => 2026, 'number' => $numero,
            'code' => 'REP-LAB-2026-'.str_pad((string) $numero, 4, '0', STR_PAD_LEFT),
            'kind' => SampleReport::KIND_PRIMARY,
            'status' => $datos['estado'] ?? SampleReport::STATUS_ISSUED,
            'issued_at' => now(),
        ]);
    }
}
