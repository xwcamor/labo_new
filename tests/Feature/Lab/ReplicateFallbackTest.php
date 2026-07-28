<?php

namespace Tests\Feature\Lab;

use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\User;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
use App\Services\Lab\WorksheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Una columna de valor único vale para TODAS las réplicas.
 *
 * El caso real es la rigidez dieléctrica: se mide cinco o seis veces sobre la
 * misma muestra, pero el peso y el factor de la solución se cargan una sola vez
 * y se aplican a las cinco. Sin este respaldo la primera medición calculaba y
 * las demás salían vacías, que es exactamente el tipo de falla silenciosa que
 * este sistema existe para no repetir.
 */
class ReplicateFallbackTest extends TestCase
{
    use RefreshDatabase;

    private WorksheetService $service;
    private TestDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedParentRows();
        $this->service = new WorksheetService();

        $this->actingAs(User::factory()->create([
            'tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1,
        ]));

        $this->definition = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'rig', 'name' => 'Rigidez Dieléctrica',
            'replicates' => 3,
        ]);

        // El factor se carga UNA vez para toda la muestra.
        TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->definition->id,
            'code' => 'factor', 'label' => 'Factor de corrección', 'type' => 'number',
            'sort_order' => 1, 'replicates' => 1,
        ]);

        // La medición se repite tres veces.
        TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->definition->id,
            'code' => 'medicion', 'label' => 'Medición', 'type' => 'number',
            'sort_order' => 2, 'replicates' => 3,
        ]);

        // Y el resultado se calcula por cada medición, usando el factor único.
        TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->definition->id,
            'code' => 'corregida', 'label' => 'Medición corregida', 'type' => 'computed',
            'sort_order' => 3, 'replicates' => 3, 'decimals' => 2,
            'formula' => 'medicion * factor',
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

    private function cargar(): WorksheetRow
    {
        $w = Worksheet::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->definition->id,
            'run_date' => '2026-07-28', 'tenant_id' => 1,
        ]);

        return $this->service->saveRow($w, ['kind' => WorksheetRow::KIND_SAMPLE], [
            'factor'   => ['1' => '1.1'],
            'medicion' => ['1' => '40', '2' => '50', '3' => '60'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────

    public function test_las_tres_replicas_calculan_con_el_factor_unico(): void
    {
        $row = $this->cargar();
        $campo = $this->definition->fields->firstWhere('code', 'corregida');

        foreach ([1 => 44.0, 2 => 55.0, 3 => 66.0] as $replica => $esperado) {
            $valor = $row->valueFor($campo, $replica);

            $this->assertNotNull($valor, "la réplica {$replica} no calculó");
            $this->assertEqualsWithDelta($esperado, (float) $valor->value_num, 1e-9);
        }
    }

    public function test_el_mapa_de_la_replica_dos_ve_el_factor(): void
    {
        $row = $this->cargar();

        $mapa = $row->valuesByFieldCode(2);

        $this->assertEqualsWithDelta(1.1, (float) $mapa['factor'], 1e-9);
        $this->assertEqualsWithDelta(50.0, (float) $mapa['medicion'], 1e-9);
    }

    public function test_una_columna_con_su_propia_replica_no_usa_el_respaldo(): void
    {
        // Si la columna SÍ tiene su medición 3, se usa esa y no la primera.
        $row = $this->cargar();

        $this->assertEqualsWithDelta(60.0, (float) $row->valuesByFieldCode(3)['medicion'], 1e-9);
    }
}
