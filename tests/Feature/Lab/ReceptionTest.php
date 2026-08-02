<?php

namespace Tests\Feature\Lab;

use App\Models\Reception;
use App\Models\Sample;
use App\Models\SampleTest;
use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\TestGroup;
use App\Models\User;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
use App\Services\Lab\ReceptionService;
use App\Services\Lab\SampleNumberAllocator;
use App\Services\Lab\SampleProgressService;
use App\Services\Lab\WorksheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use Tests\TestCase;

/**
 * La recepción de muestras, con las cuatro correcciones respecto del sistema
 * anterior: el correlativo se reserva y no se busca, la hoja se une a la muestra
 * por clave foránea, solo existen las pruebas que se piden, y el estado se
 * escribe cuando pasa y no cuando alguien mira la pantalla.
 */
class ReceptionTest extends TestCase
{
    use RefreshDatabase;

    private ReceptionService $service;
    private TestDefinition $cromas;
    private TestDefinition $acidez;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParentRows();
        $this->service = new ReceptionService();
        $this->cromas = $this->makeTest('cromas', 'Cromatografía');
        $this->acidez = $this->makeTest('acidez', 'Número Ácido');

        $this->actingAs(User::factory()->create([
            'country_id' => 1, 'locale_id' => 1, 'tenant_id' => 1,
        ]));
    }

    // ─── Correlativos ────────────────────────────────────────────────────

    public function test_confirmar_emite_correlativos_correlativos(): void
    {
        $reception = $this->makeReception();

        $this->service->confirm($reception, 3);

        $codigos = $reception->samples()->pluck('code')->all();

        $this->assertSame(['2026-0001', '2026-0002', '2026-0003'], $codigos);
        $this->assertSame(Reception::STATUS_CONFIRMED, $reception->fresh()->status);
    }

    public function test_una_segunda_recepcion_sigue_la_numeracion(): void
    {
        $this->service->confirm($this->makeReception(), 2);
        $segunda = $this->makeReception();

        $this->service->confirm($segunda, 2);

        $this->assertSame(['2026-0003', '2026-0004'], $segunda->samples()->pluck('code')->all());
    }

    public function test_un_correlativo_dado_de_baja_no_se_reutiliza(): void
    {
        // El sistema anterior buscaba el último número filtrando por
        // `deleted = 0`, así que al dar de baja la muestra más alta del año el
        // siguiente lote volvía a emitir ese número — ahora para otra muestra.
        // En un laboratorio eso es un resultado atribuido al equipo equivocado.
        $primera = $this->makeReception();
        $this->service->confirm($primera, 2);
        $primera->samples()->orderByDesc('number')->first()->delete();

        $segunda = $this->makeReception();
        $this->service->confirm($segunda, 1);

        $this->assertSame('2026-0003', $segunda->samples()->first()->code);
    }

    public function test_el_ano_sale_de_la_fecha_de_recepcion(): void
    {
        // Una entrega del 30 de diciembre que se confirma el 2 de enero
        // pertenece al ejercicio en que entró.
        $reception = $this->makeReception(['received_at' => '2025-12-30 16:00:00']);

        $this->service->confirm($reception, 1);

        $this->assertSame('2025-0001', $reception->samples()->first()->code);
    }

    public function test_dos_reservas_seguidas_no_se_pisan(): void
    {
        // Es la propiedad que al sistema anterior le faltaba: allá se leía el
        // último número y se sumaba uno, sin bloqueo, así que dos reservas
        // podían devolver el mismo. Acá cada reserva avanza el contador con el
        // propio UPDATE, sin ventana entre leer y escribir.
        $allocator = new SampleNumberAllocator();

        [$primera, $segunda] = DB::transaction(fn () => [
            $allocator->reserve(1, 2026, 3),
            $allocator->reserve(1, 2026, 2),
        ]);

        $this->assertSame([1, 2, 3], $primera);
        $this->assertSame([4, 5], $segunda);
        $this->assertSame([], array_intersect($primera, $segunda));
    }

    public function test_el_proximo_numero_se_puede_mirar_sin_quemarlo(): void
    {
        // La pantalla muestra "el próximo correlativo es 2026-0004" sin
        // emitirlo: entre que se muestra y se confirma pueden entrar otras
        // recepciones, y el número real es el que devuelve la reserva.
        $allocator = new SampleNumberAllocator();
        $this->service->confirm($this->makeReception(), 3);

        $this->assertSame(4, $allocator->peek(1, 2026));
        $this->assertSame(4, $allocator->peek(1, 2026));   // mirar no consume
    }

    public function test_no_se_confirma_dos_veces(): void
    {
        $reception = $this->makeReception();
        $this->service->confirm($reception, 2);

        $this->expectException(ValidationException::class);
        $this->service->confirm($reception->fresh(), 2);
    }

    public function test_el_correlativo_es_unico_en_la_base(): void
    {
        // La restricción existe en la base y no solo en el código: en el
        // sistema anterior la validación de unicidad estaba COMENTADA en el
        // modelo y no había ninguna restricción detrás.
        $reception = $this->makeReception();
        $this->service->confirm($reception, 1);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Sample::create([
            'slug' => Str::random(22), 'reception_id' => $reception->id,
            'tenant_id' => 1, 'year' => 2026, 'number' => 1, 'code' => '2026-0001',
        ]);
    }

    // ─── Pruebas pedidas ─────────────────────────────────────────────────

    public function test_solo_existen_las_pruebas_que_se_piden(): void
    {
        // El sistema anterior creaba una fila por CADA prueba del catálogo para
        // cada muestra y después marcaba a mano cuáles iban de verdad.
        $reception = $this->makeReception();
        $this->service->confirm($reception, 1);
        $muestra = $reception->samples()->first();

        $this->assertSame(0, $muestra->tests()->count());

        $this->service->requestTests($muestra, [$this->cromas->id]);

        $this->assertSame(1, $muestra->tests()->count());
        $this->assertSame($this->cromas->id, $muestra->tests()->first()->test_definition_id);
    }

    public function test_dejar_de_pedir_una_prueba_sin_ensayar_la_da_de_baja(): void
    {
        $muestra = $this->confirmedSample();
        $this->service->requestTests($muestra, [$this->cromas->id, $this->acidez->id]);

        $this->service->requestTests($muestra, [$this->cromas->id]);

        $this->assertSame(
            SampleTest::STATUS_CANCELLED,
            $muestra->tests()->where('test_definition_id', $this->acidez->id)->first()->status
        );
    }

    public function test_una_prueba_ya_validada_no_se_da_de_baja(): void
    {
        // Un ensayo corrido tiene que seguir constando aunque se deje de pedir:
        // el laboratorio responde por él.
        $muestra = $this->confirmedSample();
        $this->service->requestTests($muestra, [$this->cromas->id]);

        $prueba = $muestra->tests()->first();
        $prueba->update(['status' => SampleTest::STATUS_VALIDATED]);

        $this->service->requestTests($muestra, []);

        $this->assertSame(SampleTest::STATUS_VALIDATED, $prueba->fresh()->status);
    }

    public function test_una_prueba_no_se_pide_dos_veces(): void
    {
        $muestra = $this->confirmedSample();

        $this->service->requestTests($muestra, [$this->cromas->id, $this->cromas->id]);

        $this->assertSame(1, $muestra->tests()->count());
    }

    // ─── El equipo ───────────────────────────────────────────────────────

    public function test_no_se_asigna_un_equipo_de_otro_cliente(): void
    {
        // En el sistema anterior el desplegable filtraba por cliente pero
        // cargaba en paralelo los equipos de todos, y el guardado no lo
        // verificaba: alcanzaba un envío directo para colgarle la muestra de un
        // cliente al transformador de otro.
        $muestra = $this->confirmedSample();

        $otroCliente = \App\Models\Customer::create([
            'slug' => Str::random(22), 'name' => 'Otra Empresa', 'tenant_id' => 1,
        ]);
        $ajeno = $this->makeEquipment(customerId: $otroCliente->id);

        $this->expectException(ValidationException::class);
        $this->service->assignEquipment($muestra, $ajeno->id);
    }

    public function test_se_asigna_un_equipo_del_cliente(): void
    {
        $muestra = $this->confirmedSample();
        $propio = $this->makeEquipment(customerId: $muestra->reception->customer_id);

        $this->service->assignEquipment($muestra, $propio->id);

        $this->assertSame($propio->id, $muestra->fresh()->equipment_id);
    }

    // ─── La bancada hereda de la muestra ─────────────────────────────────

    public function test_la_fila_de_bancada_hereda_el_equipo_de_la_muestra(): void
    {
        // Éste es el arreglo de fondo: el analista no elige el transformador.
        $muestra = $this->confirmedSample();
        $equipo = $this->makeEquipment(customerId: $muestra->reception->customer_id);
        $this->service->assignEquipment($muestra, $equipo->id);
        $this->service->requestTests($muestra, [$this->cromas->id]);

        $prueba = $muestra->tests()->first();
        $hoja = $this->makeWorksheet($this->cromas);

        $fila = (new WorksheetService())->saveRow($hoja, [
            'kind'           => WorksheetRow::KIND_SAMPLE,
            'sample_test_id' => $prueba->id,
        ], ['h2' => '12.5']);

        $this->assertSame($equipo->id, $fila->equipment_id);
        $this->assertSame($muestra->id, $fila->sample_id);
        $this->assertSame($muestra->code, $fila->sample_code);
    }

    public function test_no_se_carga_una_muestra_en_la_hoja_de_otra_prueba(): void
    {
        // Sin esto, una cromatografía cargada en la hoja de número ácido saldría
        // informada bajo el parámetro equivocado.
        $muestra = $this->confirmedSample();
        $this->service->requestTests($muestra, [$this->cromas->id]);
        $prueba = $muestra->tests()->first();

        $hojaDeOtraPrueba = $this->makeWorksheet($this->acidez);

        $this->expectException(ValidationException::class);
        (new WorksheetService())->saveRow($hojaDeOtraPrueba, [
            'kind'           => WorksheetRow::KIND_SAMPLE,
            'sample_test_id' => $prueba->id,
        ], ['h2' => '12.5']);
    }

    // ─── Los estados ─────────────────────────────────────────────────────

    public function test_el_estado_avanza_al_cargar_y_al_completar(): void
    {
        [$muestra, $prueba, $hoja] = $this->readyToLoad();
        $servicio = new WorksheetService();

        // El estado se escribe cuando OCURRE, no cuando alguien abre la ficha
        // de la recepción. Esta prueba no declara obligatorios, así que la hoja
        // queda completa con la primera fila y publica ahí mismo: ya no hay un
        // botón intermedio que decida cuándo el resultado empieza a existir.
        $this->assertSame(SampleTest::STATUS_PENDING, $prueba->fresh()->status);

        $servicio->saveRow($hoja, [
            'kind' => WorksheetRow::KIND_SAMPLE, 'sample_test_id' => $prueba->id,
        ], ['h2' => '12.5']);

        $this->assertSame(SampleTest::STATUS_VALIDATED, $prueba->fresh()->status);
        $this->assertSame(Sample::STATUS_COMPLETED, $muestra->fresh()->status);
    }

    public function test_dar_de_baja_la_hoja_devuelve_la_prueba_a_la_cola(): void
    {
        [$muestra, $prueba, $hoja] = $this->readyToLoad();
        $servicio = new WorksheetService();

        $servicio->saveRow($hoja, [
            'kind' => WorksheetRow::KIND_SAMPLE, 'sample_test_id' => $prueba->id,
        ], ['h2' => '12.5']);
        $servicio->validate($hoja);

        $servicio->void($hoja->fresh(), 'Patrón vencido');

        $this->assertSame(SampleTest::STATUS_PENDING, $prueba->fresh()->status);
        $this->assertSame(Sample::STATUS_PENDING, $muestra->fresh()->status);
    }

    public function test_el_estado_no_retrocede_al_volver_a_guardar_la_fila(): void
    {
        [, $prueba, $hoja] = $this->readyToLoad();
        $servicio = new WorksheetService();

        $fila = $servicio->saveRow($hoja, [
            'kind' => WorksheetRow::KIND_SAMPLE, 'sample_test_id' => $prueba->id,
        ], ['h2' => '12.5']);
        $servicio->validate($hoja);

        // Se fuerza la hoja de vuelta a carga y se reguarda la fila: la prueba
        // ya validada NO puede bajar a "en proceso".
        $hoja->fresh()->forceFill(['status' => Worksheet::STATUS_DRAFT])->save();
        $servicio->saveRow($hoja->fresh(), [
            'kind' => WorksheetRow::KIND_SAMPLE, 'sample_test_id' => $prueba->id,
        ], ['h2' => '13.0'], $fila->fresh());

        $this->assertSame(SampleTest::STATUS_VALIDATED, $prueba->fresh()->status);
    }

    public function test_el_avance_de_la_recepcion_sale_en_una_sola_consulta(): void
    {
        // Es la consulta que reemplaza a las ~320 del sistema anterior, que
        // además ESCRIBÍA en cada apertura de la pantalla.
        $reception = $this->makeReception();
        $this->service->confirm($reception, 3);

        foreach ($reception->samples as $muestra) {
            $this->service->requestTests($muestra, [$this->cromas->id, $this->acidez->id]);
        }

        DB::enableQueryLog();
        $avance = (new SampleProgressService())->receptionBreakdown($reception->id);
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $consultas);
        $this->assertCount(3, $avance);
        $this->assertSame(2, (int) $avance->first()->pedidas);
        $this->assertSame(2, (int) $avance->first()->pendientes);
    }

    public function test_leer_la_recepcion_no_escribe_nada(): void
    {
        // El sistema anterior recalculaba el estado desde la VISTA, con
        // `Rem.update` y `update_all` dentro de un GET: abrir una remisión de 40
        // muestras eran unas 320 consultas y 40 escrituras.
        $reception = $this->makeReception();
        $this->service->confirm($reception, 2);

        foreach ($reception->samples as $muestra) {
            $this->service->requestTests($muestra, [$this->cromas->id]);
        }

        $antes = $reception->samples()->pluck('updated_at', 'id');

        DB::enableQueryLog();
        (new SampleProgressService())->receptionBreakdown($reception->id);
        $escrituras = collect(DB::getQueryLog())
            ->filter(fn ($q) => (bool) preg_match('/^\s*(update|insert|delete)/i', $q['query']))
            ->count();
        DB::disableQueryLog();

        $this->assertSame(0, $escrituras);
        $this->assertEquals($antes, $reception->samples()->pluck('updated_at', 'id'));
    }

    // ─────────────────────────────────────────────────────────────────────

    private function makeReception(array $overrides = []): Reception
    {
        return Reception::create(array_merge([
            'slug'        => Str::random(22),
            'tenant_id'   => 1,
            'customer_id' => $this->customerId(),
            'received_at' => '2026-03-10 09:00:00',
        ], $overrides));
    }

    private function confirmedSample(): Sample
    {
        $reception = $this->makeReception();
        $this->service->confirm($reception, 1);

        return $reception->samples()->first();
    }

    /** @return array{0:Sample,1:SampleTest,2:Worksheet} */
    private function readyToLoad(): array
    {
        $muestra = $this->confirmedSample();
        $equipo = $this->makeEquipment(customerId: $muestra->reception->customer_id);
        $this->service->assignEquipment($muestra, $equipo->id);
        $this->service->requestTests($muestra, [$this->cromas->id]);

        return [$muestra, $muestra->tests()->first(), $this->makeWorksheet($this->cromas)];
    }

    private function makeWorksheet(TestDefinition $definition): Worksheet
    {
        return Worksheet::create([
            'slug'               => Str::random(22),
            'test_definition_id' => $definition->id,
            'run_date'           => '2026-03-12',
            'tenant_id'          => 1,
        ]);
    }

    private function makeTest(string $code, string $name): TestDefinition
    {
        $group = TestGroup::firstOrCreate(
            ['code' => 'lab'],
            ['slug' => Str::random(22), 'name' => 'Laboratorio'],
        );

        $definition = TestDefinition::create([
            'slug' => Str::random(22), 'code' => $code, 'name' => $name,
            'test_group_id' => $group->id,
        ]);

        TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $definition->id,
            'code' => 'h2', 'label' => 'Hidrógeno', 'type' => 'number', 'sort_order' => 1,
        ]);

        return $definition->fresh();
    }

    private function makeEquipment(int $customerId)
    {
        return \App\Models\Equipment::create([
            'slug' => Str::random(22), 'name' => 'Transformador ' . Str::random(4),
            'customer_id' => $customerId, 'tenant_id' => 1,
        ]);
    }

    private function customerId(): int
    {
        // Sin caché estática: RefreshDatabase vacía la base entre pruebas y un
        // id memorizado apuntaría a un cliente que ya no existe.
        return \App\Models\Customer::firstOrCreate(
            ['name' => 'Minera Andina', 'tenant_id' => 1],
            ['slug' => Str::random(22)],
        )->id;
    }

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

    // ─── La baja de una entrega ──────────────────────────────────────────

    /**
     * Dar de baja una entrega ARRASTRA sus muestras y sus informes.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ EL DEFECTO QUE ESTO FIJA                                             │
     * └──────────────────────────────────────────────────────────────────────┘
     * Se daba de baja solo la fila de la entrega. Sus muestras quedaban vivas:
     * la bancada las seguía ofreciendo para cargar y el listado global seguía
     * mostrando sus informes. O sea que se podía trabajar y emitir el papel de
     * una entrega que ya no existe. El sistema anterior sí arrastraba
     * (`rem.rb:327-339`).
     */
    public function test_dar_de_baja_una_entrega_arrastra_sus_muestras(): void
    {
        $reception = $this->makeReception();
        $this->service->confirm($reception, 2);
        $ids = $reception->samples()->pluck('id');

        $this->actingAs($this->usuarioConPermiso())
            ->delete(route('lab_management.receptions.destroy', $reception), [
                'deleted_description' => 'La entrega se registró por duplicado.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            0,
            Sample::whereIn('id', $ids)->count(),
            'Las muestras sobrevivieron a su entrega: la bancada las sigue ofreciendo.',
        );
    }

    /**
     * Con un informe EMITIDO, la entrega no se borra.
     *
     * Es la misma regla que ya protegía a la muestra y que no se aplicaba un
     * nivel más arriba: el cliente tiene ese papel en la mano y el portal de
     * verificación tiene que seguir encontrándolo.
     */
    public function test_una_entrega_con_informe_emitido_no_se_borra(): void
    {
        $reception = $this->makeReception();
        $this->service->confirm($reception, 1);
        $muestra = $reception->samples()->first();

        \App\Models\SampleReport::create([
            'slug'      => Str::random(22),
            'sample_id' => $muestra->id,
            'tenant_id' => 1,
            'year'      => 2026,
            'number'    => 1,
            'code'      => \App\Models\SampleReport::formatCode(2026, 1),
            'kind'      => \App\Models\SampleReport::KIND_PRIMARY,
            'status'    => \App\Models\SampleReport::STATUS_ISSUED,
        ]);

        $this->actingAs($this->usuarioConPermiso())
            ->delete(route('lab_management.receptions.destroy', $reception), [
                'deleted_description' => 'Motivo cualquiera que no alcanza.',
            ])
            ->assertSessionHasErrors('deleted_description');

        $this->assertNotNull(Reception::find($reception->id));
    }

    private function usuarioConPermiso(): \App\Models\User
    {
        $rol = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['description' => 'Rol de prueba.'],
        );

        $rol->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'receptions.delete', 'guard_name' => 'web'],
        ));

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $usuario = \App\Models\User::factory()->create([
            'tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1,
        ]);
        $usuario->assignRole('admin');

        return $usuario;
    }
}
