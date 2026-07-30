<?php

namespace Tests\Feature\Lab;

use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\TestGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La tabla ancha por prueba, generada desde su propia definición.
 *
 * El laboratorio pide —con razón— que cada prueba tenga sus columnas, porque su
 * exportación a Excel es exactamente eso. Este comando la produce como VISTA:
 * se guarda por columna tipada y se lee ancho.
 *
 * Lo que se verifica acá es la generación del SQL, que se puede comprobar en
 * cualquier motor. La ejecución solo ocurre en Postgres.
 */
class TestViewsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTest(): TestDefinition
    {
        $group = TestGroup::firstOrCreate(
            ['code' => 'cromas'],
            ['slug' => Str::random(22), 'name' => 'Cromatografía'],
        );

        $definition = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'analisis_cromatografico',
            'name' => 'Análisis Cromatográfico', 'test_group_id' => $group->id,
        ]);

        foreach ([
            ['no_de_muestra', 'text',     1],
            ['norma',         'select',   2],
            ['equipo',        'instrument', 3],
            ['hidrogeno_h2_ppm', 'number', 4],
            ['total',         'computed', 5],
        ] as [$code, $type, $orden]) {
            TestField::create([
                'slug' => Str::random(22), 'test_definition_id' => $definition->id,
                'code' => $code, 'label' => $code, 'type' => $type, 'sort_order' => $orden,
            ]);
        }

        return $definition->fresh();
    }

    private function sql(): string
    {
        Artisan::call('lab:build-views', ['--test' => 'analisis_cromatografico', '--mostrar' => true]);

        return Artisan::output();
    }

    public function test_la_vista_lleva_el_nombre_de_la_prueba(): void
    {
        $this->makeTest();

        $this->assertStringContainsString('VIEW v_analisis_cromatografico', $this->sql());
    }

    public function test_cada_columna_de_la_prueba_es_una_columna_de_la_vista(): void
    {
        // Con el nombre que el laboratorio le puso, no un `col12` como en el
        // sistema anterior.
        $this->makeTest();
        $sql = $this->sql();

        foreach (['no_de_muestra', 'norma', 'equipo', 'hidrogeno_h2_ppm', 'total'] as $code) {
            $this->assertStringContainsString('AS "' . $code . '"', $sql);
        }
    }

    public function test_la_vista_trae_el_contexto_que_el_excel_del_laboratorio_espera(): void
    {
        // Su exportación arranca con Fecha · Tipo · Nº de Muestra y termina con
        // quién la cargó. Sin eso la tabla ancha no reemplaza a su Excel.
        $this->makeTest();
        $sql = $this->sql();

        foreach (['AS fecha', 'AS tipo', 'AS nro_muestra', 'AS equipo', 'AS cliente', 'AS analista'] as $columna) {
            $this->assertStringContainsString($columna, $sql);
        }
    }

    public function test_cada_tipo_de_columna_se_lee_de_donde_se_guardo(): void
    {
        // Es la contracara de ValueCoercer: allá se decide en qué columna cae el
        // valor, acá de cuál se lee. Si las dos no coinciden la vista sale vacía
        // sin que nada falle.
        $this->makeTest();
        $sql = $this->sql();

        $this->assertStringContainsString('o.value END) AS "norma"', $sql);
        // `i.name`: el nombre del instrumento ES su código de calibración
        // (PP-LA-01C-100), que es lo que hace trazable el resultado.
        $this->assertStringContainsString('i.name END) AS "equipo"', $sql);
        $this->assertStringContainsString('v.value_text END) AS "no_de_muestra"', $sql);
        $this->assertStringContainsString('v.value_num', $sql);
    }

    public function test_el_signo_de_censura_viaja_con_el_numero(): void
    {
        // ">75" no es 75. El sistema anterior limpiaba el signo antes de
        // convertir, así que su exportación decía 75 y perdía la advertencia.
        $this->makeTest();

        $this->assertStringContainsString("v.qualifier = 'gt' THEN '>'", $this->sql());
    }

    public function test_el_correlativo_de_la_muestra_gana_sobre_el_texto_de_la_fila(): void
    {
        // La fila puede traer un código tipeado de antes de que existiera la
        // recepción; si hay muestra, manda su correlativo.
        $this->makeTest();

        $this->assertStringContainsString('COALESCE(s.code, r.sample_code)', $this->sql());
    }

    public function test_una_prueba_sin_columnas_no_genera_vista(): void
    {
        TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'analisis_cromatografico',
            'name' => 'Vacía',
        ]);

        $this->assertStringNotContainsString('CREATE OR REPLACE VIEW', $this->sql());
    }
}
