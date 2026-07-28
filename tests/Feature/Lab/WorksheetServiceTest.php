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

    public function test_una_hoja_cerrada_ya_no_admite_cambios(): void
    {
        // El bloqueo del sistema viejo solo escondía botones: los controladores
        // nunca miraban el estado.
        $worksheet = $this->makeWorksheet();
        $this->addControlAndDuplicate($worksheet);
        $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_SAMPLE], [
            'nro_muestra' => '2026-0744', 'peso_aceite' => '20',
        ]);

        $this->service->close($worksheet);

        $this->expectException(ValidationException::class);
        $this->service->saveRow($worksheet->fresh(), ['kind' => WorksheetRow::KIND_SAMPLE], [
            'nro_muestra' => '2026-0745', 'peso_aceite' => '20',
        ]);
    }

    public function test_no_se_cierra_una_hoja_con_obligatorios_vacios(): void
    {
        $worksheet = $this->makeWorksheet();
        $this->service->saveRow($worksheet, ['kind' => WorksheetRow::KIND_CONTROL], [
            'volumen_gastado' => '1.20',   // falta peso_aceite, que es obligatorio
        ]);

        $this->expectException(ValidationException::class);
        $this->service->close($worksheet);
    }

    public function test_solo_se_valida_una_hoja_cerrada(): void
    {
        $worksheet = $this->makeWorksheet();

        $this->expectException(ValidationException::class);
        $this->service->validate($worksheet);
    }

    public function test_validar_deja_constancia_de_quien_y_cuando(): void
    {
        // En el sistema viejo `validate_user_id` se sobrescribía en cada cambio
        // de candado: no había forma de saber quién había validado.
        $worksheet = $this->makeWorksheet();
        $this->addControlAndDuplicate($worksheet);
        $this->service->close($worksheet);

        $this->service->validate($worksheet->fresh());

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
        $this->service->close($worksheet);
        $this->service->validate($worksheet->fresh());

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
        $this->service->close($worksheet);
        $this->service->validate($worksheet->fresh());

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

    public function test_anular_no_borra_y_saca_los_puntos_de_la_carta(): void
    {
        $chart = QcChart::create([
            'slug'               => Str::random(22),
            'test_definition_id' => $this->definition->id,
            'test_field_id'      => $this->definition->fields->firstWhere('code', 'resultado')->id,
            'center'             => 0.030, 'sd' => 0.002, 'is_derived' => true,
        ]);

        $worksheet = $this->makeWorksheet();
        $this->addControlAndDuplicate($worksheet);
        $this->service->close($worksheet);
        $this->service->validate($worksheet->fresh());

        $this->service->void($worksheet->fresh(), 'Patrón vencido');

        $worksheet->refresh();
        $this->assertSame(Worksheet::STATUS_VOIDED, $worksheet->status);
        $this->assertSame('Patrón vencido', $worksheet->void_reason);

        $point = QcPoint::where('qc_chart_id', $chart->id)->first();
        $this->assertTrue($point->is_excluded);
        $this->assertSame('Patrón vencido', $point->exclusion_reason);
    }
}
