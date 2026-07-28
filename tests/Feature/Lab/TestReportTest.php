<?php

namespace Tests\Feature\Lab;

use App\Models\Analyte;
use App\Models\Customer;
use App\Models\Reception;
use App\Models\Result;
use App\Models\Sample;
use App\Models\SampleTest;
use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\User;
use App\Services\Lab\TestReportPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * El informe de ensayo.
 *
 * Lo que se verifica acá es lo que el informe anterior hacía mal y le costaba
 * dinero al laboratorio: volver a interpretar el límite al imprimir, mostrar
 * como conforme un valor que nadie comparó, y publicar ensayos que todavía no
 * estaban firmados.
 */
class TestReportTest extends TestCase
{
    use RefreshDatabase;

    private TestReportPayload $payload;
    private TestDefinition $prueba;
    private Analyte $analito;
    private TestField $columna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParentRows();
        $this->payload = new TestReportPayload();

        $this->actingAs(User::factory()->create([
            'country_id' => 1, 'locale_id' => 1, 'tenant_id' => 1,
        ]));

        $this->prueba = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'acidez', 'name' => 'Número Ácido',
        ]);
        $this->analito = Analyte::create([
            'slug' => Str::random(22), 'code' => 'acid', 'name' => 'Número ácido',
        ]);
        $this->columna = TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->prueba->id,
            'code' => 'valor', 'label' => 'Valor', 'type' => 'number',
            'role' => 'result', 'sort_order' => 1, 'decimals' => 2,
            // Qué columnas se publican es un dato de la plantilla y nace en
            // falso: el informe muestra el resultado, no las quince columnas
            // intermedias del cálculo.
            'report_visible' => true,
        ]);
    }

    // ─── Qué se publica ──────────────────────────────────────────────────

    public function test_solo_entran_las_pruebas_validadas(): void
    {
        // Un ensayo en proceso no tiene resultado firmado. Publicarlo como
        // sección vacía sugiere que se midió y dio cero.
        $muestra = $this->muestraCon(SampleTest::STATUS_IN_PROGRESS);
        $this->resultado($muestra, 0.10, min: null, max: 0.15, estado: 'in_spec');

        $this->assertSame([], $this->payload->forSample($muestra)['sections']);
    }

    public function test_la_prueba_validada_sale_con_sus_filas(): void
    {
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.10, min: null, max: 0.15, estado: 'in_spec');

        $secciones = $this->payload->forSample($muestra)['sections'];

        $this->assertCount(1, $secciones);
        $this->assertSame('Número Ácido', $secciones[0]['test']);
        $this->assertCount(1, $secciones[0]['rows']);
    }

    // ─── El límite ───────────────────────────────────────────────────────

    public function test_el_limite_se_arma_con_los_numeros_congelados(): void
    {
        // El informe anterior guardaba el límite como frase ("0.15 (máximo)") y
        // la volvía a convertir a número al imprimir con `delete!`, que devuelve
        // nil cuando la palabra no está: ahí el número impreso y el criterio
        // aplicado dejaban de coincidir. Acá los dos salen del mismo dato.
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.10, min: null, max: 0.15, estado: 'in_spec');

        $fila = $this->payload->forSample($muestra)['sections'][0]['rows'][0];

        $this->assertStringContainsString('0.15', $fila['limit']);
        $this->assertStringContainsString(__('reports.limit_max'), $fila['limit']);
    }

    public function test_un_limite_de_minimo_se_lee_como_minimo(): void
    {
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 63.6, min: 47, max: null, estado: 'in_spec');

        $fila = $this->payload->forSample($muestra)['sections'][0]['rows'][0];

        $this->assertStringContainsString('47', $fila['limit']);
        $this->assertStringContainsString(__('reports.limit_min'), $fila['limit']);
    }

    // ─── Sin criterio ────────────────────────────────────────────────────

    public function test_sin_criterio_no_es_conforme(): void
    {
        // Es el punto que más importa: un valor que nadie comparó contra nada no
        // puede salir impreso como si cumpliera.
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 10648.06, min: null, max: null, estado: null);

        $datos = $this->payload->forSample($muestra);
        $fila = $datos['sections'][0]['rows'][0];

        $this->assertNull($fila['status']);
        $this->assertSame('—', $fila['limit']);
        // Y el informe lo dice por escrito.
        $this->assertNotEmpty($datos['notes']);
    }

    // ─── El signo de censura ─────────────────────────────────────────────

    public function test_el_signo_viaja_con_el_numero(): void
    {
        // ">75 kV" no es 75: es que el equipo llegó a su tope sin que el aceite
        // rompiera. El sistema anterior lo perdía al convertir a número.
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 75, min: 47, max: null, estado: 'in_spec', qualifier: 'gt');

        $fila = $this->payload->forSample($muestra)['sections'][0]['rows'][0];

        $this->assertStringContainsString('>', $fila['value']);
        $this->assertStringContainsString('75', $fila['value']);
    }

    public function test_los_decimales_son_los_de_la_columna(): void
    {
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.309, min: null, max: 0.15, estado: 'out_of_spec');

        $fila = $this->payload->forSample($muestra)['sections'][0]['rows'][0];

        $this->assertSame('0.31', $fila['value']);
        $this->assertSame('out_of_spec', $fila['status']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function muestraCon(string $estado): Sample
    {
        $cliente = Customer::create([
            'slug' => Str::random(22), 'name' => 'Energía del Sur', 'tenant_id' => 1,
        ]);
        $recepcion = Reception::create([
            'slug' => Str::random(22), 'customer_id' => $cliente->id,
            'received_at' => now(), 'tenant_id' => 1, 'status' => Reception::STATUS_CONFIRMED,
        ]);
        $muestra = Sample::create([
            'slug' => Str::random(22), 'reception_id' => $recepcion->id,
            'year' => 2026, 'number' => 1, 'code' => '2026-0001',
            'tenant_id' => 1, 'is_urgent' => false,
        ]);
        // `sample_tests` no lleva slug: es una fila de trabajo interna, no un
        // registro que se enlace desde afuera.
        SampleTest::create([
            'sample_id' => $muestra->id,
            'test_definition_id' => $this->prueba->id, 'status' => $estado,
            'tenant_id' => 1,
        ]);

        return $muestra->fresh();
    }

    private function resultado(
        Sample $muestra,
        float $valor,
        ?float $min,
        ?float $max,
        ?string $estado,
        ?string $qualifier = null,
    ): void {
        Result::create([
            'sample_id' => $muestra->id,
            'test_definition_id' => $this->prueba->id,
            'test_field_id' => $this->columna->id,
            'analyte_id' => $this->analito->id,
            'value_num' => $valor,
            'qualifier' => $qualifier,
            'unit' => 'mg KOH/g',
            'replicate_no' => 1,
            'measured_at' => now(),
            'spec_status' => $estado,
            'spec_min' => $min,
            'spec_max' => $max,
            'spec_source' => $estado === null ? null : 'Mineral · 69-230 kV',
            'tenant_id' => 1,
        ]);
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
}
