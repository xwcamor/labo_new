<?php

namespace Tests\Feature\Lab;

use App\Models\Customer;
use App\Models\Reception;
use App\Models\Sample;
use App\Models\SampleTest;
use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\User;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
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
 * Cargar la bancada sin hacerlo de a una fila.
 *
 * Tres cosas que la pantalla hacía a mano y ahora hace el sistema:
 *
 *  1. El patrón y el duplicado que la prueba EXIGE quedan puestos al crear la
 *     hoja. El sistema ya los reclamaba antes de admitir la primera muestra, o
 *     sea que obligaba al analista a un trámite que él mismo imponía.
 *  2. Las muestras que la hoja espera se traen todas de una vez. La lista la
 *     resuelve el servidor: si viniera del navegador, un envío armado a mano
 *     podría meter en esta hoja pruebas de otra definición.
 *  3. "Guardar todo" manda las filas con cambios en UNA transacción. Si una
 *     falla no queda media hoja escrita.
 */
class WorksheetBatchTest extends TestCase
{
    use RefreshDatabase;

    private TestDefinition $definition;
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
        foreach (['worksheets.view', 'worksheets.create', 'worksheets.edit'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        // Una prueba que EXIGE patrón y duplicado, con una columna obligatoria.
        $this->definition = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'acid', 'name' => 'Número Ácido',
            'requires_control' => true, 'requires_duplicate' => true,
        ]);
        TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->definition->id,
            'code' => 'nro_muestra', 'label' => 'Nº de Muestra', 'type' => 'text',
            'role' => TestField::ROLE_SAMPLE_CODE, 'sort_order' => 1,
        ]);
        TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->definition->id,
            'code' => 'volumen', 'label' => 'Volumen', 'type' => 'number',
            'sort_order' => 2, 'is_required' => true,
        ]);

        $cliente = Customer::create(['slug' => Str::random(22), 'tenant_id' => 1, 'name' => 'Cliente']);
        $this->entrega = Reception::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'customer_id' => $cliente->id,
            'code' => '2026-0001', 'year' => 2026, 'number' => 1,
            'received_at' => now()->toDateString(), 'status' => Reception::STATUS_CONFIRMED,
        ]);
    }

    // ─── Las filas obligatorias ──────────────────────────────────────────

    public function test_crear_una_hoja_deja_puestos_el_patron_y_el_duplicado(): void
    {
        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.store'), [
                'test_definition_id' => $this->definition->id,
                'run_date'           => now()->toDateString(),
            ])
            ->assertRedirect();

        $hoja = Worksheet::first();

        $this->assertSame(
            [WorksheetRow::KIND_CONTROL, WorksheetRow::KIND_DUPLICATE],
            $hoja->rows()->orderBy('position')->pluck('kind')->all(),
        );
        // Vacías: los renglones los pone la corrida, los números el analista.
        $this->assertTrue($hoja->rows()->first()->values->every(fn ($v) => $v->isEmpty()));
    }

    public function test_una_prueba_que_solo_exige_patron_no_agrega_el_duplicado(): void
    {
        $this->definition->update(['requires_duplicate' => false]);

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.store'), [
                'test_definition_id' => $this->definition->id,
                'run_date'           => now()->toDateString(),
            ]);

        $this->assertSame(
            [WorksheetRow::KIND_CONTROL],
            Worksheet::first()->rows()->pluck('kind')->all(),
        );
    }

    public function test_una_prueba_sin_control_de_calidad_arranca_vacia(): void
    {
        $this->definition->update(['requires_control' => false, 'requires_duplicate' => false]);

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.store'), [
                'test_definition_id' => $this->definition->id,
                'run_date'           => now()->toDateString(),
            ]);

        $this->assertSame(0, Worksheet::first()->rows()->count());
    }

    // ─── Traer las muestras pendientes ───────────────────────────────────

    public function test_traer_pendientes_agrega_todas_las_muestras_que_la_hoja_espera(): void
    {
        $this->muestra('2026-0001');
        $this->muestra('2026-0002');
        $this->muestra('2026-0003');

        $hoja = $this->hoja();

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.rows.fill', $hoja->slug))
            ->assertRedirect();

        // Las tres muestras, más el patrón y el duplicado que la prueba exige.
        $this->assertSame(3, $hoja->rows()->where('kind', WorksheetRow::KIND_SAMPLE)->count());
        $this->assertSame(5, $hoja->rows()->count());

        // Atadas a su prueba pedida, no con el código tipeado: es el enlace del
        // que dependen el avance de la muestra y el informe.
        $this->assertSame(0, $hoja->rows()
            ->where('kind', WorksheetRow::KIND_SAMPLE)
            ->whereNull('sample_test_id')->count());
    }

    public function test_traer_pendientes_no_repite_las_que_ya_estan(): void
    {
        $this->muestra('2026-0001');
        $this->muestra('2026-0002');

        $hoja = $this->hoja();

        $this->actingAs($this->usuario())->post(route('lab_management.worksheets.rows.fill', $hoja->slug));
        $this->actingAs($this->usuario())->post(route('lab_management.worksheets.rows.fill', $hoja->slug));

        $this->assertSame(2, $hoja->rows()->where('kind', WorksheetRow::KIND_SAMPLE)->count());
    }

    public function test_traer_pendientes_no_trae_muestras_de_otra_prueba(): void
    {
        // La misma muestra, pero la prueba pedida es de OTRA definición.
        $otra = TestDefinition::create(['slug' => Str::random(22), 'code' => 'agua', 'name' => 'Agua']);
        $muestra = $this->muestra('2026-0001');
        SampleTest::where('sample_id', $muestra->id)->update(['test_definition_id' => $otra->id]);

        $hoja = $this->hoja();

        $this->actingAs($this->usuario())->post(route('lab_management.worksheets.rows.fill', $hoja->slug));

        $this->assertSame(0, $hoja->rows()->where('kind', WorksheetRow::KIND_SAMPLE)->count());
    }

    // ─── Guardar todo ────────────────────────────────────────────────────

    public function test_guardar_todo_escribe_las_filas_en_una_sola_llamada(): void
    {
        $this->muestra('2026-0001');
        $this->muestra('2026-0002');

        $hoja = $this->hoja();
        $this->actingAs($this->usuario())->post(route('lab_management.worksheets.rows.fill', $hoja->slug));

        $filas = $hoja->rows()->where('kind', WorksheetRow::KIND_SAMPLE)->get();

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.rows.bulk', $hoja->slug), [
                'rows' => $filas->map(fn (WorksheetRow $f) => [
                    'row_id'         => $f->id,
                    'kind'           => $f->kind,
                    'sample_test_id' => $f->sample_test_id,
                    'values'         => ['volumen' => [1 => '1.25']],
                ])->all(),
            ])
            ->assertSessionHasNoErrors();

        $volumen = TestField::where('code', 'volumen')->first();

        foreach ($filas as $fila) {
            $valor = $fila->fresh()->values()->where('test_field_id', $volumen->id)->first();
            $this->assertSame(1.25, (float) $valor->value_num);
        }
    }

    public function test_si_una_fila_del_lote_falla_no_se_guarda_ninguna(): void
    {
        // Dos filas para la MISMA muestra: la segunda choca con la regla de una
        // muestra por fila. Dejar la primera guardada le dejaría al analista una
        // hoja a medio escribir sin decirle dónde quedó.
        $muestra = $this->muestra('2026-0001');
        $prueba  = SampleTest::where('sample_id', $muestra->id)->first();

        $hoja = $this->hoja();

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.rows.bulk', $hoja->slug), [
                'rows' => [
                    ['kind' => WorksheetRow::KIND_SAMPLE, 'sample_test_id' => $prueba->id, 'values' => []],
                    ['kind' => WorksheetRow::KIND_SAMPLE, 'sample_test_id' => $prueba->id, 'values' => []],
                ],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, $hoja->rows()->where('kind', WorksheetRow::KIND_SAMPLE)->count());
    }

    // ─── Lo que falta para publicar ──────────────────────────────────────

    public function test_la_pantalla_dice_cuantas_celdas_obligatorias_faltan(): void
    {
        $this->muestra('2026-0001');

        $hoja = $this->hoja();
        $this->actingAs($this->usuario())->post(route('lab_management.worksheets.rows.fill', $hoja->slug));

        $props = $this->actingAs($this->usuario())
            ->get(route('lab_management.worksheets.show', $hoja->slug))
            ->viewData('page')['props'];

        // Tres filas —patrón, duplicado y la muestra— con la única columna
        // obligatoria de la plantilla (Volumen) vacía.
        $this->assertSame(3, $props['incomplete']['total']);
        $this->assertCount(3, $props['incomplete']['rows']);
    }

    public function test_completar_los_obligatorios_deja_la_hoja_sin_faltantes(): void
    {
        $muestra = $this->muestra('2026-0001');
        $prueba  = SampleTest::where('sample_id', $muestra->id)->first();

        $hoja = $this->hoja();
        $filas = $hoja->rows()->get();

        $lote = $filas->map(fn (WorksheetRow $f) => [
            'row_id' => $f->id, 'kind' => $f->kind, 'values' => ['volumen' => [1 => '1.00']],
        ])->all();

        $lote[] = [
            'kind' => WorksheetRow::KIND_SAMPLE, 'sample_test_id' => $prueba->id,
            'values' => ['volumen' => [1 => '1.00']],
        ];

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.rows.bulk', $hoja->slug), ['rows' => $lote])
            ->assertSessionHasNoErrors();

        $props = $this->actingAs($this->usuario())
            ->get(route('lab_management.worksheets.show', $hoja->slug))
            ->viewData('page')['props'];

        $this->assertSame(0, $props['incomplete']['total']);
        // Y sin faltantes, la hoja publica sola.
        $this->assertSame(Worksheet::STATUS_VALIDATED, $hoja->fresh()->status);
    }

    // ─── La vista previa en lote ─────────────────────────────────────────

    public function test_la_vista_previa_resuelve_varias_filas_en_una_peticion(): void
    {
        // Una columna calculada, para que haya algo que previsualizar.
        TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->definition->id,
            'code' => 'doble', 'label' => 'Doble', 'type' => 'computed',
            'sort_order' => 3, 'formula' => 'volumen * 2',
        ]);

        $hoja = $this->hoja();

        $respuesta = $this->actingAs($this->usuario())
            ->postJson(route('lab_management.worksheets.preview', $hoja->slug), [
                'rows' => [
                    '10'  => ['volumen' => [1 => '2']],
                    'new' => ['volumen' => [1 => '5']],
                ],
            ]);

        $respuesta->assertOk();
        $this->assertSame(4.0, (float) $respuesta->json('rows.10.values.doble.1'));
        $this->assertSame(10.0, (float) $respuesta->json('rows.new.values.doble.1'));
    }

    public function test_la_vista_previa_de_una_sola_fila_sigue_respondiendo_igual(): void
    {
        // La forma vieja no se rompe: la usan las pruebas ya escritas y
        // cualquier pestaña abierta con el paquete anterior.
        TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->definition->id,
            'code' => 'doble', 'label' => 'Doble', 'type' => 'computed',
            'sort_order' => 3, 'formula' => 'volumen * 2',
        ]);

        $respuesta = $this->actingAs($this->usuario())
            ->postJson(route('lab_management.worksheets.preview', $this->hoja()->slug), [
                'values' => ['volumen' => [1 => '3']],
            ]);

        $respuesta->assertOk();
        $this->assertSame(6.0, (float) $respuesta->json('values.doble.1'));
    }

    // ─── Fixtures ────────────────────────────────────────────────────────

    /** Una hoja con sus filas obligatorias ya puestas, como la crea el alta. */
    private function hoja(): Worksheet
    {
        $hoja = Worksheet::create([
            'slug' => Str::random(22), 'tenant_id' => 1,
            'test_definition_id' => $this->definition->id,
            'run_date' => now()->toDateString(),
        ]);

        app(\App\Services\Lab\WorksheetService::class)->seedRequiredRows($hoja->load('definition'));

        return $hoja->fresh();
    }

    private function muestra(string $codigo): Sample
    {
        $muestra = Sample::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'reception_id' => $this->entrega->id,
            'year' => 2026, 'number' => (int) substr($codigo, -4), 'code' => $codigo,
        ]);

        SampleTest::create([
            // `tenant_id` explícito: el modelo lo hereda del usuario en sesión
            // y estas se crean antes del `actingAs`. Sin él quedan en nulo y el
            // ámbito del workspace no las encuentra.
            'tenant_id' => 1,
            'sample_id' => $muestra->id,
            'test_definition_id' => $this->definition->id,
            'status' => SampleTest::STATUS_PENDING,
        ]);

        return $muestra;
    }

    private function usuario(): User
    {
        $rol = Role::firstOrCreate(['name' => 'perfil_bancada', 'guard_name' => 'web'], ['description' => 'Prueba']);
        $rol->syncPermissions(Permission::whereIn('name', [
            'worksheets.view', 'worksheets.create', 'worksheets.edit',
        ])->get());

        $usuario = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
