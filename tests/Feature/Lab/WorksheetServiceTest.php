<?php

namespace Tests\Feature\Lab;

use App\Models\QcChart;
use App\Models\QcPoint;
use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\TestGroup;
use App\Models\User;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
use App\Services\Lab\WorksheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Cada prueba de acá corresponde a una regla que en el sistema Rails viejo
 * vivía en el HTML y que un envío directo salteaba.
 *
 * La prueba de ejemplo es el Número Ácido, con sus columnas y su fórmula
 * reales: (volumen gastado - volumen del blanco) * factor KOH / peso del aceite.
 */
class WorksheetServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorksheetService $service;
    private TestDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParentRows();

        $this->service = new WorksheetService();
        $this->definition = $this->makeAcidNumberTest();

        $this->actingAs(User::factory()->create([
            'country_id' => 1, 'locale_id' => 1,
        ]));
    }

    /** Filas padre mínimas que exigen las claves foráneas de User. */
    private function seedParentRows(): void
    {
        DB::table('languages')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22),
            'name' => 'Spanish', 'iso_code' => 'es', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('locales')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22),
            'code' => 'es_PE', 'name' => 'Español (PE)', 'language_id' => 1,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('regions')->insertOrIgnore([[
            'id' => 999, 'slug' => Str::random(22),
            'name' => '__bootstrap__', 'is_active' => false,
            'created_at' => now(), 'updated_at' => now(),
            'deleted_at' => now(), 'deleted_description' => 'Fixture de pruebas.',
        ]]);
        DB::table('countries')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22),
            'region_id' => 999, 'name' => 'Perú',
            'iso_code' => 'PE', 'currency' => 'PEN', 'timezone' => 'America/Lima',
            'default_locale_id' => 1, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
    }

    private function makeAcidNumberTest(array $overrides = []): TestDefinition
    {
        $group = TestGroup::firstOrCreate(
            ['code' => 'fiqui'],
            ['slug' => Str::random(22), 'name' => 'Físico Químico'],
        );

        $definition = TestDefinition::create(array_merge([
            'slug'               => Str::random(22),
            'code'               => 'acid',
            'name'               => 'Número Ácido',
            'test_group_id'      => $group->id,
            'requires_control'   => true,
            'requires_duplicate' => true,
        ], $overrides));

        $columns = [
            ['code' => 'nro_muestra',     'label' => 'Nº de Muestra', 'type' => 'text',   'role' => TestField::ROLE_SAMPLE_CODE, 'sort_order' => 1],
            ['code' => 'norma',           'label' => 'Norma',         'type' => 'text',   'role' => TestField::ROLE_STANDARD,    'sort_order' => 2],
            ['code' => 'factor_koh',      'label' => 'Factor KOH',    'type' => 'number', 'sort_order' => 3, 'is_reusable' => true, 'default_value' => '0.5531'],
            ['code' => 'volumen_blanco',  'label' => 'Vol. blanco',   'type' => 'number', 'sort_order' => 4],
            ['code' => 'peso_aceite',     'label' => 'Peso aceite',   'type' => 'number', 'sort_order' => 5, 'is_required' => true],
            ['code' => 'volumen_gastado', 'label' => 'Vol. gastado',  'type' => 'number', 'sort_order' => 6],
            [
                'code' => 'resultado', 'label' => 'Resultado', 'type' => 'computed',
                'role' => TestField::ROLE_RESULT, 'sort_order' => 7, 'decimals' => 3,
                'formula' => '(volumen_gastado - volumen_blanco) * factor_koh / peso_aceite',
            ],
        ];

        foreach ($columns as $column) {
            TestField::create(array_merge(
                ['slug' => Str::random(22), 'test_definition_id' => $definition->id],
                $column
            ));
        }

        return $definition->fresh();
    }

    private function makeWorksheet(?TestDefinition $definition = null): Worksheet
    {
        return Worksheet::create([
            'slug'               => Str::random(22),
            'test_definition_id' => ($definition ?? $this->definition)->id,
            'run_date'           => '2026-07-28',
        ]);
    }

    private function addControlAndDuplicate(Worksheet $worksheet): void
    {
        $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_CONTROL], [
            'factor_koh' => '0.5531', 'volumen_blanco' => '0.10',
            'peso_aceite' => '20', 'volumen_gastado' => '1.20',
        ]);

        $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_DUPLICATE], [
            'factor_koh' => '0.5531', 'volumen_blanco' => '0.10',
            'peso_aceite' => '20', 'volumen_gastado' => '1.20',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────

    public function test_el_servidor_calcula_el_resultado(): void
    {
        // (1.20 − 0.10) × 0.5531 / 20 = 0.0304205 → 0.030 con 3 decimales.
        $worksheet = $this->makeWorksheet();
        $this->addControlAndDuplicate($worksheet);

        $row = $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_SAMPLE], [
            'nro_muestra' => '2026-0744', 'norma' => 'ASTM D974',
            'factor_koh' => '0.5531', 'volumen_blanco' => '0.10',
            'peso_aceite' => '20', 'volumen_gastado' => '1.20',
        ]);

        $resultado = $row->valueFor($this->definition->fields->firstWhere('code', 'resultado'));

        $this->assertNotNull($resultado);
        $this->assertEqualsWithDelta(0.030, (float) $resultado->value_num, 1e-9);
        $this->assertTrue($resultado->is_computed);
    }

    public function test_el_valor_del_formulario_no_puede_pisar_un_campo_calculado(): void
    {
        // En el sistema viejo el campo resultado tenía `readonly`, que es una
        // sugerencia del navegador: un envío directo escribía cualquier número.
        $worksheet = $this->makeWorksheet();
        $this->addControlAndDuplicate($worksheet);

        $row = $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_SAMPLE], [
            'nro_muestra' => '2026-0744',
            'factor_koh' => '0.5531', 'volumen_blanco' => '0.10',
            'peso_aceite' => '20', 'volumen_gastado' => '1.20',
            'resultado' => '999',           // intento de escritura directa
        ]);

        $resultado = $row->valueFor($this->definition->fields->firstWhere('code', 'resultado'));

        $this->assertEqualsWithDelta(0.030, (float) $resultado->value_num, 1e-9);
    }

    public function test_no_se_admiten_muestras_sin_patron_ni_duplicado(): void
    {
        $worksheet = $this->makeWorksheet();

        $this->expectException(ValidationException::class);

        $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_SAMPLE], [
            'nro_muestra' => '2026-0744', 'peso_aceite' => '20',
        ]);
    }

    public function test_una_prueba_que_no_exige_patron_admite_muestras_de_entrada(): void
    {
        $definition = $this->makeAcidNumberTest([
            'code' => 'acid_libre', 'requires_control' => false, 'requires_duplicate' => false,
        ]);
        $worksheet = $this->makeWorksheet($definition);

        $row = $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_SAMPLE], [
            'nro_muestra' => '2026-0745', 'peso_aceite' => '20',
        ]);

        $this->assertSame(WorksheetRow::KIND_SAMPLE, $row->kind);
    }

    public function test_el_codigo_de_muestra_sale_del_campo_que_declara_serlo(): void
    {
        // No de la primera columna. En el sistema viejo lo copiaba JavaScript
        // desde el input #col1, y si el analista pegaba el código sin disparar
        // el evento del teclado el enlace con el informe se perdía en silencio.
        $worksheet = $this->makeWorksheet();
        $this->addControlAndDuplicate($worksheet);

        $row = $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_SAMPLE], [
            'nro_muestra' => '  2026-0744  ', 'peso_aceite' => '20',
        ]);

        $this->assertSame('2026-0744', $row->sample_code);
    }

    public function test_el_patron_no_lleva_codigo_de_muestra(): void
    {
        $worksheet = $this->makeWorksheet();

        $row = $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_CONTROL], [
            'nro_muestra' => 'PATRON-1', 'peso_aceite' => '20',
        ]);

        $this->assertNull($row->sample_code);
    }

    public function test_reordenar_las_columnas_no_cambia_el_resultado(): void
    {
        // El sistema viejo referenciaba las columnas por posición (#col8, #col6)
        // y su README avisaba en mayúsculas que la columna resultado tenía que
        // ser siempre la última. Acá la fórmula usa códigos.
        $worksheet = $this->makeWorksheet();
        $this->addControlAndDuplicate($worksheet);

        $row = $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_SAMPLE], [
            'nro_muestra' => '2026-0744',
            'factor_koh' => '0.5531', 'volumen_blanco' => '0.10',
            'peso_aceite' => '20', 'volumen_gastado' => '1.20',
        ]);
        $antes = (float) $row->valueFor($this->definition->fields->firstWhere('code', 'resultado'))->value_num;

        // Se invierte el orden de TODAS las columnas.
        foreach ($this->definition->fields as $field) {
            $field->update(['sort_order' => 100 - $field->sort_order]);
        }

        $this->service->recalculate($row->fresh());
        $despues = (float) $row->fresh()->valueFor($this->definition->fresh()->fields->firstWhere('code', 'resultado'))->value_num;

        $this->assertSame($antes, $despues);
    }

    public function test_una_hoja_bloqueada_ya_no_admite_cambios(): void
    {
        // Lo único que cierra la hoja es el candado. El bloqueo del sistema
        // viejo solo escondía botones: los controladores nunca miraban el
        // estado, así que un envío directo escribía igual.
        $worksheet = $this->makeWorksheet();
        $this->addControlAndDuplicate($worksheet);
        $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_SAMPLE], [
            'nro_muestra' => '2026-0744', 'peso_aceite' => '20',
        ]);

        $worksheet->forceFill(['locked_at' => now()])->save();

        $this->expectException(ValidationException::class);
        $this->service->saveRow($worksheet->fresh(), ['kind' => WorksheetRow::KIND_SAMPLE], [
            'nro_muestra' => '2026-0745', 'peso_aceite' => '20',
        ]);
    }

    public function test_la_hoja_completa_publica_sola(): void
    {
        // Ya no hay botón que la firme: al guardar la última fila que faltaba,
        // los resultados quedan consultables. Antes vivían en un limbo hasta
        // que alguien se acordara de apretar "Validar".
        $worksheet = $this->makeWorksheet();
        $this->addControlAndDuplicate($worksheet);

        $this->assertSame(Worksheet::STATUS_VALIDATED, $worksheet->fresh()->status);
    }

    public function test_una_hoja_con_obligatorios_vacios_no_publica(): void
    {
        $worksheet = $this->makeWorksheet();
        $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_CONTROL], [
            'volumen_gastado' => '1.20',   // falta peso_aceite, que es obligatorio
        ]);

        $this->expectException(ValidationException::class);
        $this->service->validate($worksheet);
    }

    public function test_una_hoja_bloqueada_no_se_valida(): void
    {
        // El candado lo pone el sistema a los N meses. Validar una hoja
        // bloqueada sería entrar por la ventana a lo que el candado cierra.
        $worksheet = $this->makeWorksheet();
        $this->addControlAndDuplicate($worksheet);
        $worksheet->forceFill(['locked_at' => now()])->save();

        $this->expectException(ValidationException::class);
        $this->service->validate($worksheet->fresh());
    }

    public function test_el_bloqueo_automatico_alcanza_a_las_hojas_viejas(): void
    {
        $vieja = $this->makeWorksheet();
        $vieja->forceFill(['run_date' => now()->subMonths(6)->toDateString()])->save();
        $nueva = $this->makeWorksheet();

        $this->assertSame(1, $this->service->autoLockAged(4));
        $this->assertNotNull($vieja->fresh()->locked_at);
        $this->assertNull($nueva->fresh()->locked_at);
    }

    public function test_el_bloqueo_automatico_se_puede_apagar(): void
    {
        $vieja = $this->makeWorksheet();
        $vieja->forceFill(['run_date' => now()->subYears(3)->toDateString()])->save();

        $this->assertSame(0, $this->service->autoLockAged(0));
        $this->assertNull($vieja->fresh()->locked_at);
    }

    public function test_validar_deja_constancia_de_quien_y_cuando(): void
    {
        // En el sistema viejo `validate_user_id` se sobrescribía en cada cambio
        // de candado: no había forma de saber quién había validado.
        $worksheet = $this->makeWorksheet();
        $this->addControlAndDuplicate($worksheet);
        $this->service->validate($worksheet);

        $worksheet->refresh();
        $this->assertSame(Worksheet::STATUS_VALIDATED, $worksheet->status);
        $this->assertSame(auth()->id(), $worksheet->validated_by);
        $this->assertNotNull($worksheet->validated_at);
    }

    public function test_los_patrones_alimentan_la_carta_de_control_al_validar(): void
    {
        $chart = QcChart::create([
            'slug'               => Str::random(22),
            'test_definition_id' => $this->definition->id,
            'test_field_id'      => $this->definition->fields->firstWhere('code', 'resultado')->id,
            'center'             => 0.030,
            'sd'                 => 0.002,
            'is_derived'         => true,
        ]);

        $worksheet = $this->makeWorksheet();
        $this->addControlAndDuplicate($worksheet);
        $this->service->validate($worksheet);

        $this->assertSame(1, QcPoint::where('qc_chart_id', $chart->id)->count());

        $point = QcPoint::where('qc_chart_id', $chart->id)->first();
        $this->assertEqualsWithDelta(0.030, (float) $point->value, 1e-9);
        $this->assertSame(QcPoint::FLAG_OK, $point->flag);
    }

    public function test_un_patron_fuera_de_control_queda_marcado(): void
    {
        $chart = QcChart::create([
            'slug'               => Str::random(22),
            'test_definition_id' => $this->definition->id,
            'test_field_id'      => $this->definition->fields->firstWhere('code', 'resultado')->id,
            'center'             => 0.010,
            'sd'                 => 0.001,
            'is_derived'         => true,
        ]);

        // El patrón da 0.030 con una media de 0.010 y desvío 0.001: veinte
        // desvíos de distancia.
        $worksheet = $this->makeWorksheet();
        $this->addControlAndDuplicate($worksheet);
        $this->service->validate($worksheet);

        $point = QcPoint::where('qc_chart_id', $chart->id)->first();

        $this->assertSame(QcPoint::FLAG_OUT, $point->flag);
        $this->assertSame('1_3s', $point->westgard_rule);
    }

    public function test_un_valor_censurado_guarda_el_umbral_y_su_signo(): void
    {
        // ">75" es "al menos 75", no 75. En el sistema viejo esto terminaba
        // como texto en la misma columna que los números y rompía los cálculos.
        $worksheet = $this->makeWorksheet();

        $row = $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_CONTROL], [
            'peso_aceite' => '>75',
        ]);

        $value = $row->valueFor($this->definition->fields->firstWhere('code', 'peso_aceite'));

        $this->assertEqualsWithDelta(75.0, (float) $value->value_num, 1e-9);
        $this->assertSame('gt', $value->qualifier);
        $this->assertSame('>75', $value->display);
    }

    public function test_un_atributo_opcional_se_puede_vaciar(): void
    {
        // Con el operador `??`, mandar el campo en nulo dejaba el valor
        // anterior: la pantalla podía ofrecer un botón de limpiar que no
        // limpiaba nada. "No vino la clave" y "vino en nulo" son cosas
        // distintas.
        $worksheet = $this->makeWorksheet();
        $row = $this->service->saveRow($worksheet, [
            'kind'  => WorksheetRow::KIND_CONTROL,
            'notes' => 'Patrón del lote A',
        ], ['peso_aceite' => '20']);

        $this->assertSame('Patrón del lote A', $row->notes);

        // Sin la clave: se conserva.
        $row = $this->service->saveRow($worksheet, [
            'kind' => WorksheetRow::KIND_CONTROL,
        ], ['peso_aceite' => '20'], $row);
        $this->assertSame('Patrón del lote A', $row->notes);

        // Con la clave en nulo: se borra.
        $row = $this->service->saveRow($worksheet, [
            'kind'  => WorksheetRow::KIND_CONTROL,
            'notes' => null,
        ], ['peso_aceite' => '20'], $row);
        $this->assertNull($row->notes);
    }

    public function test_dar_de_baja_no_borra_y_saca_los_puntos_de_la_carta(): void
    {
        $chart = QcChart::create([
            'slug'               => Str::random(22),
            'test_definition_id' => $this->definition->id,
            'test_field_id'      => $this->definition->fields->firstWhere('code', 'resultado')->id,
            'center'             => 0.030, 'sd' => 0.002, 'is_derived' => true,
        ]);

        $worksheet = $this->makeWorksheet();
        $this->addControlAndDuplicate($worksheet);
        $this->service->validate($worksheet);

        $this->service->void($worksheet->fresh(), 'Patrón vencido');

        $worksheet->refresh();
        $this->assertSame(Worksheet::STATUS_VOIDED, $worksheet->status);
        $this->assertSame('Patrón vencido', $worksheet->void_reason);

        $point = QcPoint::where('qc_chart_id', $chart->id)->first();
        $this->assertTrue($point->is_excluded);
        $this->assertSame('Patrón vencido', $point->exclusion_reason);
    }

    public function test_el_rango_declarado_de_la_columna_se_aplica(): void
    {
        // El rango vivía en la definición de la columna y NO lo leía nadie: se
        // podía declarar que el peso va de 0 a 100 g y guardar 900.
        $peso = $this->definition->fields->firstWhere('code', 'peso_aceite');
        $peso->update(['min_value' => 1, 'max_value' => 100]);

        $worksheet = $this->makeWorksheet();

        $this->expectException(ValidationException::class);
        $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_CONTROL], [
            'peso_aceite' => '900',
        ]);
    }

    public function test_el_cero_se_rechaza_donde_no_es_una_medicion(): void
    {
        // Una rigidez de 0 kV no existe y un factor de potencia de exactamente
        // 0.000 % no es medible: es el "no medido" del sistema anterior, que
        // obligaba a llenar la celda. Con la cota abierta, 0 se rechaza y
        // cualquier valor real por chico que sea entra.
        $peso = $this->definition->fields->firstWhere('code', 'peso_aceite');
        $peso->update(['min_value' => 0, 'min_exclusive' => true]);

        $worksheet = $this->makeWorksheet();

        // 0.001 entra.
        $row = $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_CONTROL], [
            'peso_aceite' => '0.001',
        ]);
        $this->assertEqualsWithDelta(
            0.001,
            (float) $row->valueFor($peso)->value_num,
            1e-9
        );

        // 0 no.
        $this->expectException(ValidationException::class);
        $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_CONTROL], [
            'peso_aceite' => '0',
        ]);
    }

    public function test_una_columna_sin_rango_declarado_admite_cualquier_numero(): void
    {
        // Las 207 columnas vienen sin rango: declararlo es una decisión del
        // laboratorio, columna por columna. Mientras no lo declare, no se
        // inventa un límite.
        $worksheet = $this->makeWorksheet();

        $row = $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_CONTROL], [
            'peso_aceite' => '0', 'volumen_gastado' => '99999',
        ]);

        $this->assertEqualsWithDelta(
            0.0,
            (float) $row->valueFor($this->definition->fields->firstWhere('code', 'peso_aceite'))->value_num,
            1e-9
        );
    }

    public function test_no_se_le_exige_codigo_de_muestra_a_un_patron(): void
    {
        // Un patrón, un duplicado o un blanco no son la muestra de un cliente y
        // no llevan código: saveRow() lo da por sentado y guarda nulo. Cuando el
        // laboratorio marca esa columna como obligatoria —y en sus 29 pruebas
        // reales lo está en todas—, la hoja no se podía cerrar nunca: reclamaba
        // una celda que ella misma no dejaba llenar.
        $definition = $this->makeAcidNumberTest([
            'code' => 'acid_codigo_obligatorio',
            'requires_duplicate' => false,
        ]);
        TestField::where('test_definition_id', $definition->id)
            ->where('code', 'nro_muestra')
            ->update(['is_required' => true]);

        $worksheet = $this->makeWorksheet($definition);

        $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_CONTROL], [
            'peso_aceite' => '20', 'volumen_gastado' => '1.20',
        ]);
        $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_SAMPLE], [
            'nro_muestra' => '2026-0744', 'peso_aceite' => '20', 'volumen_gastado' => '1.20',
        ]);

        $this->service->validate($worksheet);

        $this->assertSame(Worksheet::STATUS_VALIDATED, $worksheet->fresh()->status);
    }

    public function test_a_la_muestra_si_se_le_exige_el_codigo(): void
    {
        // La contracara de la anterior: la excepción es para el patrón, no una
        // puerta para que una muestra entre sin identificar.
        $definition = $this->makeAcidNumberTest([
            'code' => 'acid_codigo_obligatorio_2',
            'requires_control' => false, 'requires_duplicate' => false,
        ]);
        TestField::where('test_definition_id', $definition->id)
            ->where('code', 'nro_muestra')
            ->update(['is_required' => true]);

        $worksheet = $this->makeWorksheet($definition);
        $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_SAMPLE], [
            'peso_aceite' => '20', 'volumen_gastado' => '1.20',
        ]);

        $this->expectException(ValidationException::class);
        $this->service->validate($worksheet);
    }

    public function test_westgard_no_baja_la_alarma_de_un_punto_fuera_del_limite_de_alerta(): void
    {
        // Son dos criterios distintos y ninguno reemplaza al otro: los límites
        // de la carta responden por el punto suelto y las reglas de Westgard
        // responden por la serie. Antes la evaluación de la serie se escribía
        // encima de la del punto, así que un valor por fuera de la línea de
        // alerta del laboratorio —que no rompe ninguna regla de la serie por sí
        // solo— quedaba guardado como "ok" y se dibujaba en verde.
        $chart = QcChart::create([
            'slug'               => Str::random(22),
            'test_definition_id' => $this->definition->id,
            'test_field_id'      => $this->definition->fields->firstWhere('code', 'resultado')->id,
            'center'             => 0.030,
            'sd'                 => 0.002,
            'is_derived'         => true,      // límites de 2σ y 3σ derivados
        ]);

        // (1.36 − 0.10) × 0.5531 / 20 = 0.034845 → 0.035 con 3 decimales.
        // Contra centro 0.030 y desvío 0.002 eso es z = +2.5: pasada la línea
        // de alerta (2σ) y adentro de la de control (3σ).
        $worksheet = $this->makeWorksheet();
        $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_CONTROL], [
            'factor_koh' => '0.5531', 'volumen_blanco' => '0.10',
            'peso_aceite' => '20', 'volumen_gastado' => '1.36',
        ]);
        $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_DUPLICATE], [
            'factor_koh' => '0.5531', 'volumen_blanco' => '0.10',
            'peso_aceite' => '20', 'volumen_gastado' => '1.20',
        ]);

        $this->service->validate($worksheet);

        $point = QcPoint::where('qc_chart_id', $chart->id)->first();

        $this->assertNotNull($point);
        $this->assertEqualsWithDelta(2.5, (float) $point->z_score, 0.05);
        $this->assertSame(QcPoint::FLAG_WARN, $point->flag);
        // Un punto solo no rompe ninguna regla de serie: la alarma viene de los
        // límites de la carta, y por eso no queda ninguna regla anotada.
        $this->assertNull($point->westgard_rule);
    }
}
