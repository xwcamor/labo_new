<?php

namespace Tests\Feature\Lab;

use App\Models\Analyte;
use App\Models\Instrument;
use App\Models\TestDefinition;
use App\Models\TestField;
use App\Services\Lab\FormulaValidator;
use Database\Seeders\LabAnalyteMapSeeder;
use Database\Seeders\LabAnalytesSeeder;
use Database\Seeders\LabInstrumentsSeeder;
use Database\Seeders\LabTestFormulasSeeder;
use Database\Seeders\LabTestTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Que un solo comando deje el laboratorio cargado.
 *
 * Esta prueba existe por un motivo concreto: durante un tiempo las tablas del
 * laboratorio quedaban VACÍAS después de `setup:project`, porque cargarlas
 * dependía de tres comandos que había que acordarse de correr a mano. El
 * sistema se veía roto sin estarlo. Acá se verifica el resultado de sembrar, no
 * la existencia de los sembradores.
 *
 * De paso cubre la rotura silenciosa más probable de acá en adelante: renombrar
 * una columna de una prueba y dejar una fórmula apuntando al nombre viejo. Eso
 * no falla al sembrar —falla en la bancada, con la muestra ya cargada—, así que
 * se verifica que TODAS compilen contra las columnas reales.
 */
class LabSeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El workspace al que van los instrumentos. En el seed completo lo crea
        // TenantsSeeder; acá alcanza con la fila.
        DB::table('tenants')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]]);

        $this->seed([
            LabAnalytesSeeder::class,
            LabTestTemplatesSeeder::class,
            LabTestFormulasSeeder::class,
            LabInstrumentsSeeder::class,
            LabAnalyteMapSeeder::class,
        ]);
    }

    public function test_el_seed_deja_las_pruebas_reales_cargadas(): void
    {
        // Los números salen del volcado de definiciones del sistema anterior.
        // Si cambian, es porque cambió el volcado, y hay que mirarlo.
        $this->assertSame(29, TestDefinition::count());
        $this->assertSame(207, TestField::count());
        $this->assertGreaterThanOrEqual(36, Analyte::withoutGlobalScopes()->count());

        $this->assertNotNull(TestDefinition::where('code', 'analisis_cromatografico')->first());
        $this->assertNotNull(TestDefinition::where('code', 'numero_acido')->first());
    }

    public function test_todas_las_formulas_compilan_contra_sus_columnas(): void
    {
        $conFormula = TestField::whereNotNull('formula')->get();

        $this->assertGreaterThanOrEqual(9, $conFormula->count(), 'No se sembró ninguna fórmula.');

        $validador = new FormulaValidator();

        foreach ($conFormula as $columna) {
            $disponibles = TestField::where('test_definition_id', $columna->test_definition_id)
                ->where('id', '!=', $columna->id)
                ->pluck('code')
                ->all();

            $resultado = $validador->validate($columna->formula, $disponibles);

            $this->assertTrue(
                $resultado['ok'],
                "La fórmula de '{$columna->code}' no compila: " . implode(' ', $resultado['errors'])
            );
        }
    }

    public function test_una_columna_calculada_queda_de_solo_lectura(): void
    {
        // El sistema viejo dejaba el campo de resultado con `readonly`, que es
        // una sugerencia del navegador, y su propia ayuda pedía por escrito
        // "se recomienda bloquear la edición en las columnas que sean
        // resultados de cálculos". Acá lo hace el sembrador.
        $columna = $this->columna('numero_acido', 'resultado_mgkohg_aceite');

        $this->assertSame('computed', $columna->type);
        $this->assertTrue($columna->is_locked);
        $this->assertSame(3, (int) $columna->decimals);
    }

    public function test_ninguna_columna_tiene_un_codigo_que_una_formula_no_pueda_nombrar(): void
    {
        // Un código que empieza con un dígito no es un identificador válido:
        // "2_furfuraldehido" se lee como el número 2 seguido de otra cosa, y la
        // fórmula del grado de polimerización de Furanos no compilaba. El
        // importador ahora pasa el número al final ("furfuraldehido_2").
        $malos = TestField::pluck('code')
            ->filter(fn (string $code) => (bool) preg_match('/^\d/', $code))
            ->all();

        $this->assertSame([], array_values($malos));
    }

    public function test_los_instrumentos_dejan_de_ser_texto_suelto(): void
    {
        // En el sistema viejo el equipo era el TEXTO de una opción, con el
        // código de calibración adentro del nombre y sin fecha de vencimiento.
        $this->assertSame(25, Instrument::withoutGlobalScopes()->count());

        $bureta = $this->columna('numero_acido', 'bureta_pp_la_01c');
        $this->assertSame('instrument', $bureta->type);

        // Y el mismo equipo, usado por dos pruebas, es UNA fila: la balanza
        // PP-LA-01C-056 aparece en Número Ácido y en Contenido de Agua.
        $this->assertSame(
            1,
            Instrument::withoutGlobalScopes()->where('code', 'PP-LA-01C-056')->count()
        );

        // La errata del volcado ('PP-LA-01C-100.') queda unificada.
        $this->assertSame(
            0,
            Instrument::withoutGlobalScopes()->where('code', 'like', '%.')->count()
        );

        // La calibración se siembra vacía: inventarla sería inventar
        // trazabilidad.
        $this->assertNull(
            Instrument::withoutGlobalScopes()->where('code', 'PP-LA-01C-056')->value('calibrated_at')
        );
    }

    public function test_una_columna_con_opciones_que_no_son_equipos_no_se_convierte(): void
    {
        // "Tipo de Fluido" ofrece Mineral / Vegetal / Silicona: convertirla
        // habría perdido esas opciones.
        $fluido = $this->columna('rigidez_dielectrica', 'tipo_de_fluido');

        $this->assertSame('select', $fluido->type);
        $this->assertGreaterThan(0, $fluido->options()->count());
    }

    public function test_las_columnas_de_resultado_saben_a_que_parametro_alimentan(): void
    {
        // Sin este enlace, validar una hoja no materializa ningún resultado y
        // el informe se queda sin nada que leer.
        $this->assertGreaterThanOrEqual(30, TestField::whereNotNull('output_analyte_id')->count());

        $h2 = $this->columna('analisis_cromatografico', 'hidrogeno_h2_ppm');
        $this->assertNotNull($h2->output_analyte_id);
        $this->assertSame('h2', Analyte::withoutGlobalScopes()->find($h2->output_analyte_id)->code);
    }

    public function test_sembrar_dos_veces_no_duplica_nada(): void
    {
        $antes = [
            TestDefinition::count(),
            TestField::count(),
            Instrument::withoutGlobalScopes()->count(),
        ];

        $this->seed([
            LabTestTemplatesSeeder::class,
            LabTestFormulasSeeder::class,
            LabInstrumentsSeeder::class,
            LabAnalyteMapSeeder::class,
        ]);

        $this->assertSame($antes, [
            TestDefinition::count(),
            TestField::count(),
            Instrument::withoutGlobalScopes()->count(),
        ]);
    }

    private function columna(string $prueba, string $campo): TestField
    {
        $columna = TestField::where(
            'test_definition_id',
            TestDefinition::where('code', $prueba)->value('id')
        )->where('code', $campo)->first();

        $this->assertNotNull($columna, "No existe la columna '{$prueba}.{$campo}'.");

        return $columna;
    }
}
