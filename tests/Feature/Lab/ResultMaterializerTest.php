<?php

namespace Tests\Feature\Lab;

use App\Models\Analyte;
use App\Models\Equipment;
use App\Models\Result;
use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\User;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
use App\Services\Lab\ResultMaterializer;
use App\Services\Lab\WorksheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La capa `results`: lo que se midió, por parámetro, consultable por equipo.
 *
 * Lo que se cuida acá es la separación entre las dos capas. `worksheet_values`
 * es la constancia de lo que hizo el analista; `results` es su lectura tipada y
 * se tiene que poder reconstruir entera desde allá. Si alguna vez `results`
 * guarda algo que no está en la capa cruda, deja de ser derivada.
 */
class ResultMaterializerTest extends TestCase
{
    use RefreshDatabase;

    private WorksheetService $service;
    private ResultMaterializer $materializer;
    private TestDefinition $definition;
    private Analyte $acidez;
    private Equipment $equipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedParentRows();

        $this->service = new WorksheetService();
        $this->materializer = new ResultMaterializer();

        $this->actingAs(User::factory()->create([
            'tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1,
        ]));

        $this->acidez = Analyte::create([
            'slug' => Str::random(22), 'code' => 'acid', 'name' => 'Número Ácido',
            'unit' => 'mg KOH/g', 'decimals' => 3, 'direction' => 'lower_better',
        ]);

        $this->equipo = Equipment::create([
            'slug' => Str::random(22), 'name' => 'Transformador 01',
            'serial' => 'SN-001', 'tag' => 'TR-01',
            'tenant_id' => 1, 'created_by' => 1,
        ]);

        $this->definition = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'acid', 'name' => 'Número Ácido',
        ]);

        foreach ([
            ['code' => 'nro_muestra',     'label' => 'Nº de Muestra', 'type' => 'text',   'role' => TestField::ROLE_SAMPLE_CODE, 'sort_order' => 1],
            ['code' => 'peso_aceite',     'label' => 'Peso aceite',   'type' => 'number', 'sort_order' => 2],
            ['code' => 'volumen_gastado', 'label' => 'Vol. gastado',  'type' => 'number', 'sort_order' => 3],
        ] as $c) {
            TestField::create(array_merge(
                ['slug' => Str::random(22), 'test_definition_id' => $this->definition->id],
                $c
            ));
        }

        // La columna de resultado, declarando a qué parámetro alimenta. Es el
        // dato que el sistema viejo no tenía.
        TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->definition->id,
            'code' => 'resultado', 'label' => 'Resultado', 'type' => 'computed',
            'role' => TestField::ROLE_RESULT, 'sort_order' => 4, 'decimals' => 3,
            'formula' => 'volumen_gastado * 0.5531 / peso_aceite',
            'output_analyte_id' => $this->acidez->id,
        ]);

        $this->definition->refresh();
    }

    private function seedParentRows(): void
    {
        DB::table('languages')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish', 'iso_code' => 'es', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('locales')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'code' => 'es_PE', 'name' => 'Español', 'language_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('regions')->insertOrIgnore([['id' => 999, 'slug' => Str::random(22), 'name' => '__bs__', 'is_active' => false, 'deleted_at' => now(), 'deleted_description' => 'bs', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('countries')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Peru', 'iso_code' => 'PE', 'currency' => 'PEN', 'timezone' => 'America/Lima', 'default_locale_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('tenants')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
    }

    private function hoja(string $fecha = '2026-07-28'): Worksheet
    {
        return Worksheet::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->definition->id,
            'run_date' => $fecha, 'tenant_id' => 1,
        ]);
    }

    private function cargarMuestra(Worksheet $w, ?Equipment $equipo = null, float $volumen = 1.20): WorksheetRow
    {
        return $this->service->saveRow($w, [
            'kind'         => WorksheetRow::KIND_SAMPLE,
            'equipment_id' => ($equipo ?? $this->equipo)->id,
        ], [
            'nro_muestra' => '2026-0744', 'peso_aceite' => '20',
            'volumen_gastado' => (string) $volumen,
        ]);
    }

    private function validar(Worksheet $w): void
    {
        $this->service->validate($w);
    }

    // ─────────────────────────────────────────────────────────────────────

    public function test_validar_la_hoja_produce_el_resultado_por_parametro(): void
    {
        $w = $this->hoja();
        $this->cargarMuestra($w);
        $this->validar($w);

        $r = Result::where('analyte_id', $this->acidez->id)->first();

        $this->assertNotNull($r);
        $this->assertSame($this->equipo->id, $r->equipment_id);
        $this->assertEqualsWithDelta(0.033, (float) $r->value_num, 1e-9);
        $this->assertSame('mg KOH/g', $r->unit);
        $this->assertSame('2026-07-28', $r->measured_at->toDateString());
    }

    public function test_una_hoja_incompleta_todavia_no_informa_nada(): void
    {
        // Un obligatorio vacío significa que la medición no terminó. Publicarla
        // a medias la haría aparecer en el informe de un cliente con un hueco
        // que nadie decidió dejar.
        $w = $this->hoja();
        $this->service->saveRow($w, ['kind' => WorksheetRow::KIND_SAMPLE], [
            'nro_muestra' => '2026-0744',   // sin peso_aceite, que es obligatorio
        ]);

        $this->assertSame(0, Result::count());
    }

    public function test_el_patron_y_el_duplicado_no_son_resultados_del_cliente(): void
    {
        // Miden si el método está midiendo bien, no el aceite del cliente.
        // Mezclarlos contaminaría la tendencia del equipo.
        $w = $this->hoja();
        $this->service->saveRow($w, ['kind' => WorksheetRow::KIND_CONTROL], ['peso_aceite' => '20', 'volumen_gastado' => '1.20']);
        $this->service->saveRow($w, ['kind' => WorksheetRow::KIND_DUPLICATE], ['peso_aceite' => '20', 'volumen_gastado' => '1.20']);
        $this->service->saveRow($w, ['kind' => WorksheetRow::KIND_BLANK], ['peso_aceite' => '20', 'volumen_gastado' => '0.02']);
        $this->cargarMuestra($w);
        $this->validar($w);

        $this->assertSame(1, Result::count(), 'solo la muestra produce resultado');
    }

    public function test_una_columna_que_no_declara_su_parametro_no_produce_nada(): void
    {
        // Es deliberado: adivinar a qué parámetro alimenta una columna manda el
        // dato equivocado al informe. Es el caso real de los nueve gases de
        // cromatografía, que quedan sin declarar hasta que el laboratorio los
        // confirme.
        TestField::where('code', 'resultado')->update(['output_analyte_id' => null]);

        $w = $this->hoja();
        $this->cargarMuestra($w);
        $this->validar($w);

        $this->assertSame(0, Result::count());
    }

    public function test_una_fila_sin_equipo_escribe_su_resultado_igual(): void
    {
        // ┌──────────────────────────────────────────────────────────────────┐
        // │ ESTE TEST AFIRMABA LO CONTRARIO, Y ESTABA MAL                    │
        // └──────────────────────────────────────────────────────────────────┘
        // Antes decía que una fila sin equipo NO producía resultado, y el
        // materializador la salteaba. Lo corrigió el propio laboratorio
        // (2026-07-30):
        //
        //   "cuando se ingresan los datos de croma es con el número que le
        //    ponemos y luego ese número lo asociamos a un transformador o a lo
        //    que sea. En el reporte no necesariamente; a veces solamente es el
        //    dato de la muestra nada más, o datos de un cilindro."
        //
        // El equipo se asocia DESPUÉS, y a veces nunca: una muestra de un
        // cilindro de aceite nuevo no viene de ningún equipo y se informa igual.
        // Con la compuerta puesta, ese ensayo se corría, se cargaba, y el
        // resultado se descartaba en silencio.
        //
        // El informe busca por MUESTRA, no por equipo, así que el resultado
        // llega al papel. Lo único que no puede hacer es alimentar la tendencia
        // de un equipo que no existe.
        $w = $this->hoja();
        $this->service->saveRow($w, ['kind' => WorksheetRow::KIND_SAMPLE], [
            'nro_muestra' => '2026-0744', 'peso_aceite' => '20', 'volumen_gastado' => '1.20',
        ]);
        $this->service->validate($w);

        $this->assertGreaterThan(0, Result::count());

        // Y se escribe SIN equipo, no con un equipo inventado.
        $this->assertNotNull(Result::first());
        $this->assertNull(Result::first()->equipment_id);

        // Nada quedó salteado: no hay trabajo perdido que reportar.
        $informe = $this->materializer->forWorksheet($w->fresh());
        $this->assertSame([], $informe['skipped']);
    }

    public function test_borrar_una_fila_retira_su_resultado(): void
    {
        // El borrado era `$row->delete()` a secas: la fila desaparecía de la
        // grilla pero su resultado quedaba vivo y el informe seguía imprimiendo
        // un valor sin ninguna fila detrás — el fantasma que el analista veía
        // después de rehacer una medición.
        $w = $this->hoja();
        $fila = $this->cargarMuestra($w);
        $this->assertSame(1, Result::count());

        $this->service->deleteRow($w, $fila);

        $this->assertSame(0, Result::count());
    }

    public function test_rematerializar_sanea_los_fantasmas_de_filas_borradas(): void
    {
        // El huérfano dejado ANTES de la corrección (baja sin retiro): al
        // republicarse la hoja, se sanea solo.
        $w = $this->hoja();
        $fila = $this->cargarMuestra($w);
        $fila->delete();   // el camino viejo, sin retirar el resultado
        $this->assertSame(1, Result::count());

        $this->materializer->forWorksheet($w->refresh());

        $this->assertSame(0, Result::count());
    }

    public function test_la_misma_muestra_no_entra_dos_veces_como_fila_de_muestra(): void
    {
        // Dos filas de la misma muestra son dos resultados oficiales para la
        // misma medición. La segunda medición es el Duplicado (control) o una
        // corrección editando la fila que ya está.
        $w = $this->hoja();
        $this->cargarMuestra($w);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->cargarMuestra($w);   // mismo código de muestra, misma hoja
    }

    public function test_rematerializar_actualiza_y_no_duplica(): void
    {
        $w = $this->hoja();
        $this->cargarMuestra($w);
        $this->validar($w);

        $this->materializer->forWorksheet($w->fresh());
        $this->materializer->forWorksheet($w->fresh());

        $this->assertSame(1, Result::count());
    }

    public function test_la_capa_se_reconstruye_entera_desde_la_bancada(): void
    {
        // Es la prueba de que `results` es derivada: si se borra, se regenera
        // desde lo que cargó el analista, sin pérdida.
        $w = $this->hoja();
        $this->cargarMuestra($w);
        $this->validar($w);

        $antes = Result::first()->only(['equipment_id', 'analyte_id', 'value_num', 'unit']);

        Result::query()->delete();
        $this->assertSame(0, Result::count());

        $this->materializer->forWorksheet($w->fresh());

        $despues = Result::first()->only(['equipment_id', 'analyte_id', 'value_num', 'unit']);
        $this->assertSame($antes, $despues);
    }

    public function test_corregir_la_formula_y_reconstruir_cambia_el_resultado_sin_tocar_lo_cargado(): void
    {
        $w = $this->hoja();
        $row = $this->cargarMuestra($w);
        $this->validar($w);

        $crudoAntes = DB::table('worksheet_values')
            ->where('worksheet_row_id', $row->id)->orderBy('id')->pluck('value_num')->all();

        // El laboratorio corrige el factor de la solución titulante.
        TestField::where('code', 'resultado')
            ->update(['formula' => 'volumen_gastado * 0.4987 / peso_aceite']);

        $this->service->recalculate($row->fresh());
        $this->materializer->forWorksheet($w->fresh());

        $this->assertEqualsWithDelta(0.030, (float) Result::first()->value_num, 1e-9);

        // Y lo que cargó el analista sigue exactamente igual.
        $crudoDespues = DB::table('worksheet_values')
            ->where('worksheet_row_id', $row->id)
            ->whereIn('test_field_id', TestField::where('code', '!=', 'resultado')->pluck('id'))
            ->orderBy('id')->pluck('value_num')->all();

        $this->assertSame(
            array_slice($crudoAntes, 0, count($crudoDespues)),
            $crudoDespues
        );
    }

    public function test_anular_retira_el_resultado_de_la_capa_consultable(): void
    {
        // Un ensayo anulado no puede seguir apareciendo en el informe de un
        // cliente. La hoja y sus valores crudos quedan, con su motivo.
        $w = $this->hoja();
        $row = $this->cargarMuestra($w);
        $this->validar($w);
        $this->assertSame(1, Result::count());

        $this->service->void($w->fresh(), 'Muestra mal identificada');

        $this->assertSame(0, Result::count());
        $this->assertDatabaseHas('worksheets', ['id' => $w->id, 'void_reason' => 'Muestra mal identificada']);
        $this->assertTrue(DB::table('worksheet_values')->where('worksheet_row_id', $row->id)->exists());
    }

    public function test_la_tendencia_de_un_equipo_sale_ordenada_por_fecha(): void
    {
        foreach ([['2024-03-10', 1.10], ['2025-06-02', 1.30], ['2026-07-28', 1.20]] as [$fecha, $vol]) {
            $w = $this->hoja($fecha);
            $this->cargarMuestra($w, null, $vol);
            $this->validar($w);
        }

        $serie = Result::trend($this->equipo->id, $this->acidez->id)->get();

        $this->assertCount(3, $serie);
        $this->assertSame(
            ['2024-03-10', '2025-06-02', '2026-07-28'],
            $serie->map(fn ($r) => $r->measured_at->toDateString())->all()
        );
    }

    public function test_un_valor_censurado_conserva_su_signo_y_no_entra_en_estadistica(): void
    {
        // ">75" tratado como 75 baja el promedio de una serie de aceites sanos.
        $w = $this->hoja();
        $this->service->saveRow($w, [
            'kind' => WorksheetRow::KIND_SAMPLE, 'equipment_id' => $this->equipo->id,
        ], [
            'nro_muestra' => '2026-0744', 'peso_aceite' => '>75', 'volumen_gastado' => '1.20',
        ]);

        TestField::where('code', 'peso_aceite')->update([
            'role' => TestField::ROLE_RESULT, 'output_analyte_id' => $this->acidez->id,
        ]);
        TestField::where('code', 'resultado')->update(['output_analyte_id' => null]);

        $this->validar($w);

        $r = Result::first();
        $this->assertSame('gt', $r->qualifier);
        $this->assertSame('>75.000', $r->display);
        $this->assertFalse($r->isUsableInStatistics());
    }

    /**
     * Una columna de resultado que es LISTA se materializa con su texto.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ EL DEFECTO QUE ESTO FIJA                                             │
     * └──────────────────────────────────────────────────────────────────────┘
     * La celda de una columna de lista guarda su respuesta en `option_id`, no
     * en `value_text`. El materializador copiaba `value_num` y `value_text` y
     * nada más, así que el resultado quedaba con los dos en nulo: existía la
     * fila y no tenía valor.
     *
     * Son tres columnas en todo el catálogo —el resultado de los tres azufres
     * corrosivos— y el efecto era doble: la hoja de azufre salía impresa con
     * sus tres filas en blanco, y el motor del análisis, que juzga sobre el
     * resultado, no tenía qué juzgar y dejaba esa familia sin párrafo.
     */
    public function test_una_columna_de_lista_materializa_el_texto_de_su_opcion(): void
    {
        $columna = TestField::where('code', 'resultado')->first();
        $columna->update(['type' => 'select', 'formula' => null]);

        $opcion = \App\Models\TestFieldOption::create([
            'test_field_id' => $columna->id,
            'value' => 'No Corrosivo',
            'sort_order' => 1,
        ]);

        $w = $this->hoja();
        $this->service->saveRow($w, [
            'kind' => WorksheetRow::KIND_SAMPLE, 'equipment_id' => $this->equipo->id,
        ], [
            'nro_muestra' => '2026-0744', 'peso_aceite' => '20',
            'volumen_gastado' => '1.20', 'resultado' => (string) $opcion->id,
        ]);

        $this->validar($w);

        $r = Result::first();
        $this->assertNotNull($r, 'La columna de lista no materializó ningún resultado.');
        $this->assertSame('No Corrosivo', $r->value_text);
        $this->assertNull($r->value_num);
    }

    /**
     * Sacarle el parámetro a una columna BORRA su resultado.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ EL DEFECTO QUE ESTO FIJA                                             │
     * └──────────────────────────────────────────────────────────────────────┘
     * `updateOrCreate` actualiza lo que sigue existiendo y crea lo nuevo, pero
     * no se lleva lo que dejó de corresponder. Si a una columna se le quitaba
     * el parámetro que alimentaba, su resultado viejo quedaba en la tabla para
     * siempre y el informe lo seguía imprimiendo —con su valor y su veredicto—
     * sin ninguna columna de bancada detrás.
     *
     * Eso rompe la regla que sostiene esta capa y que fija la prueba «la capa se
     * reconstruye entera desde la bancada»: un resultado que no se puede
     * reconstruir es un dato inventado en un papel firmado.
     */
    public function test_quitarle_el_parametro_a_una_columna_borra_su_resultado(): void
    {
        $w = $this->hoja();
        $this->cargarMuestra($w);
        $this->validar($w);

        $this->assertSame(1, Result::count());

        TestField::where('code', 'resultado')->update(['output_analyte_id' => null]);
        $this->materializer->forWorksheet($w->fresh());

        $this->assertSame(
            0,
            Result::count(),
            'El resultado sobrevivió a la columna que lo alimentaba: la capa dejó de ser derivada.',
        );
    }
}
