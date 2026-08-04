<?php

namespace Tests\Feature\Lab;

use App\Models\Customer;
use App\Models\Reception;
use App\Models\Sample;
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
 * El listado de hojas de trabajo, al estándar de los índices del sistema.
 *
 * Era el único listado sin acciones de fila, sin favoritos, sin filtros
 * avanzados y sin selección múltiple. Lo que se prueba acá es lo que cambió:
 *
 *  1. Los filtros —prueba, estado, analista, número de muestra, rango de
 *     fechas, solo favoritas— acotan de verdad, y el rango vacío NO recorta
 *     (el sistema viejo aplicaba en silencio un "últimos tres meses").
 *  2. El filtro avanzado entra por `FilterApplier` contra `filterSchema()`, y
 *     el orden pasa por lista blanca: lo que llega de la URL no entra al SQL.
 *  3. La baja masiva pasa por la MISMA puerta que la individual
 *     (`WorksheetService::void`), salta las bloqueadas por candado y exige
 *     motivo.
 */
class WorksheetIndexTest extends TestCase
{
    use RefreshDatabase;

    private TestDefinition $acidez;
    private TestDefinition $rigidez;
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
        foreach (['worksheets.view', 'worksheets.edit', 'worksheets.delete'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $this->acidez  = $this->prueba('acid', 'Número Ácido');
        $this->rigidez = $this->prueba('rig', 'Rigidez Dieléctrica');

        $cliente = Customer::create(['slug' => Str::random(22), 'tenant_id' => 1, 'name' => 'Cliente']);
        $this->entrega = Reception::create([
            'slug' => Str::random(22), 'tenant_id' => 1, 'customer_id' => $cliente->id,
            'code' => '2026-0001', 'year' => 2026, 'number' => 1,
            'received_at' => now()->toDateString(), 'status' => Reception::STATUS_CONFIRMED,
        ]);
    }

    // ─── Los filtros rápidos ─────────────────────────────────────────────

    public function test_el_listado_muestra_las_hojas_del_workspace(): void
    {
        $this->hoja($this->acidez);
        $this->hoja($this->rigidez);

        $props = $this->propsDelIndice();

        $this->assertCount(2, $props['worksheets']['data']);
        // El esqueleto del estándar: el esquema del filtro avanzado y los topes
        // de exportación viajan con la página, no se piden aparte.
        $this->assertNotEmpty($props['filterSchema']);
        $this->assertArrayHasKey('exportLimits', $props);
        $this->assertArrayHasKey('can', $props);
        // El contador de la franja necesita el total SIN filtrar.
        $this->assertArrayHasKey('total_unfiltered', $props['worksheets']);

        // El ANALISTA salió del filtro rápido pero no se perdió: vive en el
        // esquema del filtro avanzado.
        $claves = array_column($props['filterSchema'], 'key');
        $this->assertContains('analyst_id', $claves);
    }

    public function test_filtra_por_prueba(): void
    {
        $this->hoja($this->acidez);
        $this->hoja($this->rigidez);

        $props = $this->propsDelIndice(['test_definition' => $this->rigidez->slug]);

        $this->assertCount(1, $props['worksheets']['data']);
        $this->assertSame($this->rigidez->id, $props['worksheets']['data'][0]['test_definition_id']);
    }

    public function test_filtra_por_estado(): void
    {
        $this->hoja($this->acidez);
        $validada = $this->hoja($this->rigidez);
        $validada->forceFill(['status' => Worksheet::STATUS_VALIDATED])->save();

        $props = $this->propsDelIndice(['status' => Worksheet::STATUS_VALIDATED]);

        $this->assertCount(1, $props['worksheets']['data']);
        $this->assertSame($validada->id, $props['worksheets']['data'][0]['id']);
    }

    public function test_filtra_por_analista(): void
    {
        $analista = $this->usuario();
        $mia = $this->hoja($this->acidez);
        $mia->forceFill(['analyst_id' => $analista->id])->save();
        $this->hoja($this->rigidez);

        $props = $this->propsDelIndice(['analyst' => $analista->id]);

        $this->assertCount(1, $props['worksheets']['data']);
        $this->assertSame($mia->id, $props['worksheets']['data'][0]['id']);
    }

    public function test_filtra_por_numero_de_muestra(): void
    {
        $conMuestra = $this->hoja($this->acidez);
        WorksheetRow::create([
            'slug' => Str::random(22), 'worksheet_id' => $conMuestra->id,
            'kind' => WorksheetRow::KIND_SAMPLE, 'sample_code' => '2026-0042', 'position' => 1,
        ]);
        $this->hoja($this->rigidez);

        $props = $this->propsDelIndice(['sample' => '0042']);

        $this->assertCount(1, $props['worksheets']['data']);
        $this->assertSame($conMuestra->id, $props['worksheets']['data'][0]['id']);
    }

    /**
     * El sistema viejo forzaba en silencio un "últimos tres meses": las hojas
     * más viejas no salían y nada en la pantalla lo decía. Sin rango, TODO.
     */
    public function test_sin_rango_de_fechas_se_listan_tambien_las_hojas_viejas(): void
    {
        $vieja = $this->hoja($this->acidez);
        $vieja->forceFill(['run_date' => now()->subYears(2)->toDateString()])->save();
        $this->hoja($this->rigidez);

        $this->assertCount(2, $this->propsDelIndice()['worksheets']['data']);

        // Y con rango, acota.
        $props = $this->propsDelIndice([
            'from' => now()->subMonth()->toDateString(),
            'to'   => now()->toDateString(),
        ]);
        $this->assertCount(1, $props['worksheets']['data']);
    }

    public function test_solo_favoritas_deja_las_marcadas(): void
    {
        $usuario = $this->usuario();
        $favorita = $this->hoja($this->acidez);
        $this->hoja($this->rigidez);

        DB::table('user_favorites')->insert([
            'user_id' => $usuario->id,
            'favoritable_type' => Worksheet::class,
            'favoritable_id'   => $favorita->id,
            'created_at' => now(),
        ]);

        $props = $this->actingAs($usuario)
            ->get(route('lab_management.worksheets.index', ['only_favorites' => 1]))
            ->viewData('page')['props'];

        $this->assertCount(1, $props['worksheets']['data']);
        $this->assertSame($favorita->id, $props['worksheets']['data'][0]['id']);
        // Y la columna calculada viaja: la estrella no cuesta una consulta por fila.
        $this->assertTrue((bool) $props['worksheets']['data'][0]['is_favorite']);
    }

    // ─── El filtro avanzado y el orden ───────────────────────────────────

    public function test_el_filtro_avanzado_acota_por_condiciones_ambientales(): void
    {
        $calurosa = $this->hoja($this->acidez);
        $calurosa->forceFill(['ambient_temp_c' => 28.5])->save();
        $fresca = $this->hoja($this->rigidez);
        $fresca->forceFill(['ambient_temp_c' => 21.0])->save();

        $props = $this->propsDelIndice([
            'advanced_where' => json_encode([
                ['field' => 'ambient_temp_c', 'op' => '>', 'value' => 25],
            ]),
        ]);

        $this->assertCount(1, $props['worksheets']['data']);
        $this->assertSame($calurosa->id, $props['worksheets']['data'][0]['id']);
    }

    /**
     * Lo que llega de la URL no entra al SQL: una columna inventada cae al
     * orden por omisión, y la pantalla recibe DE VUELTA el orden resuelto —
     * no el que pidió. Si le devolviera el suyo, cada navegación siguiente
     * volvería a mandar la columna inventada.
     */
    public function test_un_orden_inventado_cae_al_orden_por_omision(): void
    {
        $this->hoja($this->acidez);

        // Ni siquiera con una inyección adentro: no llega al SQL.
        $this->propsDelIndice(['sort' => 'run_date); drop table worksheets;--']);
        $this->assertSame(1, Worksheet::count());

        $props = $this->propsDelIndice(['sort' => 'columna_que_no_existe', 'direction' => 'raro']);

        $this->assertSame('run_date', $props['filters']['sort']);
        $this->assertSame('desc', $props['filters']['direction']);
        $this->assertCount(1, $props['worksheets']['data']);
    }

    /**
     * Se ordena por TODAS las columnas del listado, no solo por las que son
     * columna propia de la hoja. Prueba, analista y validador viven en otra
     * tabla y entran como subconsulta; los dos recuentos, por el alias que
     * `withCount` dejó en el SELECT.
     */
    public function test_ordena_por_las_columnas_que_viven_en_otra_tabla(): void
    {
        // "Número Ácido" antes que "Rigidez Dieléctrica" por nombre de prueba.
        $conAcidez  = $this->hoja($this->acidez);
        $conRigidez = $this->hoja($this->rigidez);

        $porPrueba = $this->propsDelIndice(['sort' => 'definition', 'direction' => 'asc']);
        $this->assertSame(
            [$conAcidez->id, $conRigidez->id],
            array_column($porPrueba['worksheets']['data'], 'id'),
        );

        // Y por analista, alfabético.
        $ana  = User::factory()->create(['name' => 'Ana Quispe',  'tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $zoe  = User::factory()->create(['name' => 'Zoe Ramirez', 'tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $conAcidez->forceFill(['analyst_id' => $zoe->id])->save();
        $conRigidez->forceFill(['analyst_id' => $ana->id])->save();

        $porAnalista = $this->propsDelIndice(['sort' => 'analyst', 'direction' => 'asc']);
        $this->assertSame(
            [$conRigidez->id, $conAcidez->id],
            array_column($porAnalista['worksheets']['data'], 'id'),
        );
    }

    public function test_ordena_por_la_cantidad_de_filas(): void
    {
        $vacia = $this->hoja($this->acidez);
        $cargada = $this->hoja($this->rigidez);

        foreach ([1, 2, 3] as $posicion) {
            WorksheetRow::create([
                'slug' => Str::random(22), 'worksheet_id' => $cargada->id,
                'kind' => WorksheetRow::KIND_SAMPLE, 'position' => $posicion,
            ]);
        }

        $props = $this->propsDelIndice(['sort' => 'rows_count', 'direction' => 'desc']);

        $this->assertSame(
            [$cargada->id, $vacia->id],
            array_column($props['worksheets']['data'], 'id'),
        );
        $this->assertSame(3, $props['worksheets']['data'][0]['rows_count']);
    }

    // ─── La baja masiva ──────────────────────────────────────────────────

    public function test_la_baja_masiva_da_de_baja_las_hojas_con_su_motivo(): void
    {
        $this->conPlanQueDesbloqueaBulk();
        $una = $this->hoja($this->acidez);
        $otra = $this->hoja($this->rigidez);

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.bulk_delete'), [
                'ids' => [$una->id, $otra->id],
                'deleted_description' => 'Corrida repetida por contaminación del reactivo',
            ])
            ->assertSessionHasNoErrors();

        foreach ([$una, $otra] as $hoja) {
            $fresca = Worksheet::withTrashed()->find($hoja->id);
            $this->assertNotNull($fresca->deleted_at);
            $this->assertSame(Worksheet::STATUS_VOIDED, $fresca->status);
            $this->assertSame('Corrida repetida por contaminación del reactivo', $fresca->void_reason);
        }
    }

    public function test_la_baja_masiva_salta_las_bloqueadas_por_candado(): void
    {
        $this->conPlanQueDesbloqueaBulk();
        $libre = $this->hoja($this->acidez);
        $trabada = $this->hoja($this->rigidez);
        $trabada->forceFill(['locked_at' => now(), 'lock_scope' => 'tenant'])->save();

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.bulk_delete'), [
                'ids' => [$libre->id, $trabada->id],
                'deleted_description' => 'Motivo suficiente',
            ]);

        $this->assertNotNull(Worksheet::withTrashed()->find($libre->id)->deleted_at);
        $this->assertNull(Worksheet::withTrashed()->find($trabada->id)->deleted_at);
    }

    public function test_la_baja_masiva_exige_motivo(): void
    {
        $this->conPlanQueDesbloqueaBulk();
        $hoja = $this->hoja($this->acidez);

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.bulk_delete'), [
                'ids' => [$hoja->id],
                'deleted_description' => 'ab',
            ])
            ->assertSessionHasErrors('deleted_description');

        $this->assertNull(Worksheet::withTrashed()->find($hoja->id)->deleted_at);
    }

    /** Sin ninguna que se pueda borrar, el usuario recibe el porqué, no un éxito falso. */
    public function test_si_no_se_borra_ninguna_lo_dice(): void
    {
        $this->conPlanQueDesbloqueaBulk();
        $trabada = $this->hoja($this->acidez);
        $trabada->forceFill(['locked_at' => now(), 'lock_scope' => 'tenant'])->save();

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.bulk_delete'), [
                'ids' => [$trabada->id],
                'deleted_description' => 'Motivo suficiente',
            ])
            ->assertSessionHasErrors('ids');
    }

    public function test_sin_permiso_de_borrar_la_baja_masiva_no_pasa(): void
    {
        $this->conPlanQueDesbloqueaBulk();
        $hoja = $this->hoja($this->acidez);

        $this->actingAs($this->usuario(['worksheets.view']))
            ->post(route('lab_management.worksheets.bulk_delete'), [
                'ids' => [$hoja->id],
                'deleted_description' => 'Motivo suficiente',
            ])
            ->assertRedirect();

        $this->assertNull(Worksheet::withTrashed()->find($hoja->id)->deleted_at);
    }

    /** La hoja bloqueada tampoco se borra de a una por la ruta DELETE. */
    public function test_una_hoja_bloqueada_no_se_borra_por_la_ruta_individual(): void
    {
        $trabada = $this->hoja($this->acidez);
        $trabada->forceFill(['locked_at' => now(), 'lock_scope' => 'tenant'])->save();

        $this->actingAs($this->usuario())
            ->delete(route('lab_management.worksheets.destroy', $trabada->slug), [
                'void_reason' => 'Motivo suficiente',
            ])
            ->assertRedirect();

        $this->assertNull(Worksheet::withTrashed()->find($trabada->id)->deleted_at);
    }

    // ─── Deshacer la última baja ─────────────────────────────────────────

    /**
     * Deshacer no es un `restore()`: la baja retiró los resultados de la capa
     * consultable, marcó los puntos de control de calidad y devolvió los
     * ensayos a la cola. Volver a poner la hoja en el listado sin revertir eso
     * dejaría una hoja que se ve viva y no lo está.
     */
    public function test_deshacer_devuelve_la_hoja_y_lo_que_la_baja_se_llevo(): void
    {
        $hoja = $this->hoja($this->acidez);
        $hoja->forceFill(['status' => Worksheet::STATUS_VALIDATED])->save();

        $this->actingAs($this->usuario())
            ->delete(route('lab_management.worksheets.destroy', $hoja->slug), [
                'void_reason' => 'Se cargó en la hoja equivocada',
            ])
            ->assertRedirect();

        $this->assertNotNull(Worksheet::withTrashed()->find($hoja->id)->deleted_at);

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.undo_last_delete'))
            ->assertSessionHas('success');

        $devuelta = Worksheet::withTrashed()->find($hoja->id);
        $this->assertNull($devuelta->deleted_at);
        // El motivo se va con la baja: la hoja volvió, no quedó "de baja viva".
        $this->assertNull($devuelta->void_reason);
        $this->assertNotSame(Worksheet::STATUS_VOIDED, $devuelta->status);
    }

    public function test_deshacer_alcanza_a_todas_las_de_una_baja_masiva(): void
    {
        $this->conPlanQueDesbloqueaBulk();
        $una = $this->hoja($this->acidez);
        $otra = $this->hoja($this->rigidez);

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.bulk_delete'), [
                'ids' => [$una->id, $otra->id],
                'deleted_description' => 'Corrida repetida',
            ]);

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.undo_last_delete'))
            ->assertSessionHas('success');

        foreach ([$una, $otra] as $hoja) {
            $this->assertNull(Worksheet::withTrashed()->find($hoja->id)->deleted_at);
        }
    }

    /** Sin nada que deshacer, lo dice — no finge un éxito. */
    public function test_deshacer_sin_baja_reciente_avisa(): void
    {
        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.undo_last_delete'))
            ->assertSessionHas('error');
    }

    /** Pasada la ventana de 60 segundos, la baja queda firme. */
    public function test_deshacer_fuera_de_la_ventana_no_revive_nada(): void
    {
        $hoja = $this->hoja($this->acidez);

        $this->actingAs($this->usuario())
            ->delete(route('lab_management.worksheets.destroy', $hoja->slug), [
                'void_reason' => 'Motivo suficiente',
            ]);

        $this->travel(2)->minutes();

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.undo_last_delete'))
            ->assertSessionHas('error');

        $this->assertNotNull(Worksheet::withTrashed()->find($hoja->id)->deleted_at);
    }

    // ─── La papelera ─────────────────────────────────────────────────────

    public function test_la_papelera_lista_las_hojas_dadas_de_baja_con_su_motivo(): void
    {
        $hoja = $this->hoja($this->acidez);

        $this->actingAs($this->usuario())
            ->delete(route('lab_management.worksheets.destroy', $hoja->slug), [
                'void_reason' => 'Reactivo vencido',
            ]);

        $props = $this->actingAs($this->superUsuario())
            ->get(route('lab_management.worksheets.trash'))
            ->viewData('page')['props'];

        $this->assertCount(1, $props['worksheets']['data']);
        $this->assertSame('Reactivo vencido', $props['worksheets']['data'][0]['void_reason']);
        // Y QUIÉN la dio de baja: la papelera decía por qué desapareció una
        // hoja pero no de quién fue la decisión.
        $this->assertNotNull($props['worksheets']['data'][0]['deleter']);
    }

    public function test_la_papelera_es_solo_del_super(): void
    {
        $this->actingAs($this->usuario())
            ->get(route('lab_management.worksheets.trash'))
            ->assertRedirect();
    }

    /**
     * Restaurar desde la papelera pasa por la MISMA puerta que "deshacer": no
     * es un `restore()` a secas.
     */
    public function test_restaurar_desde_la_papelera_devuelve_la_hoja_entera(): void
    {
        $hoja = $this->hoja($this->acidez);
        $hoja->forceFill(['status' => Worksheet::STATUS_VALIDATED])->save();

        $this->actingAs($this->usuario())
            ->delete(route('lab_management.worksheets.destroy', $hoja->slug), [
                'void_reason' => 'Reactivo vencido',
            ]);

        $this->actingAs($this->superUsuario())
            ->post(route('lab_management.worksheets.restore', $hoja->slug))
            ->assertSessionHasNoErrors();

        $devuelta = Worksheet::withTrashed()->find($hoja->id);
        $this->assertNull($devuelta->deleted_at);
        $this->assertNull($devuelta->void_reason);
    }

    /**
     * NO hay borrado definitivo, y es a propósito: una hoja es la constancia
     * de un ensayo que respalda informes ya firmados.
     */
    public function test_no_existe_borrado_definitivo_de_una_hoja(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('lab_management.worksheets.force_delete'),
        );
    }

    // ─── La exportación del listado ──────────────────────────────────────

    public function test_exportar_encola_el_trabajo_y_lo_deja_auditado(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->hoja($this->acidez);

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.export_csv'), [
                'scope'   => 'filtered',
                'columns' => ['run_date', 'definition', 'status'],
            ])
            ->assertSessionHas('success');

        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Jobs\LabManagement\Worksheets\GenerateWorksheetsCsvJob::class,
        );

        $this->assertDatabaseHas('audit_logs', [
            'event'          => 'export_queued',
            'auditable_type' => Worksheet::class,
            'module'         => 'worksheets',
        ]);
    }

    /**
     * Las columnas van por lista blanca. Sin eso, un envío armado a mano podría
     * pedir `columns[]=void_reason` y llevarse en una planilla el motivo de
     * cada baja.
     */
    public function test_exportar_rechaza_una_columna_que_no_esta_permitida(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.export_csv'), [
                'columns' => ['void_reason'],
            ])
            ->assertSessionHasErrors('columns.0');

        \Illuminate\Support\Facades\Queue::assertNothingPushed();
    }

    /**
     * `id` y `slug` son identificadores internos: el diálogo se los ofrece
     * solo al super, y el servidor es el que decide — esconder una casilla no
     * es una autorización.
     */
    public function test_los_identificadores_internos_solo_los_exporta_el_super(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $this->actingAs($this->usuario())
            ->post(route('lab_management.worksheets.export_csv'), ['columns' => ['id', 'run_date']])
            ->assertSessionHasErrors('columns.0');

        $this->actingAs($this->superUsuario())
            ->post(route('lab_management.worksheets.export_csv'), ['columns' => ['id', 'slug', 'run_date']])
            ->assertSessionHasNoErrors();
    }

    /** El CSV escribe lo mismo que la pantalla muestra. */
    public function test_el_csv_escribe_la_tabla_del_listado(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $hoja = $this->hoja($this->acidez);
        $hoja->forceFill(['status' => Worksheet::STATUS_VALIDATED])->save();

        $usuario = $this->usuario();

        (new \App\Jobs\LabManagement\Worksheets\GenerateWorksheetsCsvJob($usuario->id, [
            'scope'   => 'all',
            'columns' => ['run_date', 'definition', 'status'],
            'title'   => 'Hojas',
        ]))->handle();

        $descarga = \App\Models\Download::where('user_id', $usuario->id)->latest('id')->first();

        $this->assertSame('ready', $descarga->status);

        $contenido = \Illuminate\Support\Facades\Storage::disk('local')->get($descarga->path);

        $this->assertStringContainsString('Número Ácido', $contenido);
        // El estado sale TRADUCIDO, no como la clave interna: es lo que se lee
        // en la pantalla.
        $this->assertStringContainsString(__('worksheets.state.validated'), $contenido);
        $this->assertStringNotContainsString('validated', $contenido);
    }

    // ─── Fixtures ────────────────────────────────────────────────────────

    /**
     * El middleware `plan_feature:bulk_operations` resuelve el plan por la
     * suscripción vigente del workspace — sin esto la ruta contesta que la
     * función está bloqueada y el test no prueba nada.
     */
    private function conPlanQueDesbloqueaBulk(): void
    {
        DB::table('plans')->insertOrIgnore([[
            'id' => 1, 'slug' => 'enterprise', 'name' => 'Enterprise',
            'sort_order' => 1, 'max_users' => -1, 'max_records_per_module' => -1,
            'export_rate_limit' => 50, 'support_level' => 'priority',
            'features' => json_encode(['bulk_operations' => true, 'saved_views' => true]),
            'price_monthly' => 0, 'price_yearly' => 0, 'currency' => 'USD',
            'is_active' => true, 'is_public' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('subscriptions')->insertOrIgnore([[
            'id' => 1, 'tenant_id' => 1, 'plan' => 'enterprise', 'status' => 'active',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
            'currency' => 'USD', 'payment_method' => 'manual',
            'created_at' => now(), 'updated_at' => now(),
        ]]);
    }

    private function propsDelIndice(array $query = []): array
    {
        return $this->actingAs($this->usuario())
            ->get(route('lab_management.worksheets.index', $query))
            ->viewData('page')['props'];
    }

    private function prueba(string $code, string $name): TestDefinition
    {
        $definicion = TestDefinition::create([
            'slug' => Str::random(22), 'code' => $code, 'name' => $name, 'is_active' => true,
        ]);

        TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $definicion->id,
            'code' => 'nro_muestra', 'label' => 'Nº de Muestra', 'type' => 'text',
            'role' => TestField::ROLE_SAMPLE_CODE, 'sort_order' => 1,
        ]);

        return $definicion;
    }

    private function hoja(TestDefinition $definicion): Worksheet
    {
        return Worksheet::create([
            'slug' => Str::random(22), 'tenant_id' => 1,
            'test_definition_id' => $definicion->id,
            'run_date' => now()->toDateString(),
        ]);
    }

    /**
     * Un usuario con permisos, memoizado por juego de permisos.
     *
     * NUNCA en `static`: el estado estático sobrevive a RefreshDatabase y el
     * usuario de una prueba aparecería —ya borrado— en la siguiente.
     */
    private array $cache = [];

    private function usuario(array $permisos = ['worksheets.view', 'worksheets.edit', 'worksheets.delete']): User
    {
        $clave = implode('|', $permisos);

        if (isset($this->cache[$clave])) {
            return $this->cache[$clave];
        }

        $rol = Role::firstOrCreate(
            ['name' => 'perfil_' . md5($clave), 'guard_name' => 'web'],
            ['description' => 'Prueba'],
        );
        $rol->syncPermissions(Permission::whereIn('name', $permisos)->get());

        $usuario = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $usuario->assignRole($rol);

        return $this->cache[$clave] = $usuario;
    }

    /** El super: la papelera y la restauración son suyas. */
    private function superUsuario(): User
    {
        if (isset($this->cache['super'])) {
            return $this->cache['super'];
        }

        $rol = Role::firstOrCreate(['name' => 'super', 'guard_name' => 'web'], ['description' => 'Super']);

        $usuario = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $usuario->assignRole($rol);

        return $this->cache['super'] = $usuario;
    }
}
