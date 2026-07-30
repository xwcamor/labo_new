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
use Database\Seeders\LabTestFieldTypesSeeder;
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
            LabTestFieldTypesSeeder::class,
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
        // Hoy ese código ES el nombre del instrumento, y el tipo de equipo
        // ("Balanza analítica") vive en su descripción.
        // Son 24 y no 25 porque el importador ya no revive las opciones que el
        // laboratorio había dado de baja.
        $this->assertSame(24, Instrument::withoutGlobalScopes()->count());

        $bureta = $this->columna('numero_acido', 'bureta_pp_la_01c');
        $this->assertSame('instrument', $bureta->type);

        // Y el mismo equipo, usado por dos pruebas, es UNA fila: la balanza
        // PP-LA-01C-056 aparece en Número Ácido y en Contenido de Agua.
        $this->assertSame(
            1,
            Instrument::withoutGlobalScopes()->where('name', 'PP-LA-01C-056')->count()
        );

        // La errata del volcado ('PP-LA-01C-100.') queda unificada.
        $this->assertSame(
            0,
            Instrument::withoutGlobalScopes()->where('name', 'like', '%.')->count()
        );

        // La calibración se siembra vacía: inventarla sería inventar
        // trazabilidad.
        $this->assertNull(
            Instrument::withoutGlobalScopes()->where('name', 'PP-LA-01C-056')->value('calibrated_at')
        );
    }

    public function test_ninguna_columna_numerica_quedo_declarada_como_texto(): void
    {
        // El sistema viejo guardaba TODO en una sola columna varchar, así que
        // declaraba "texto" hasta para los números. El importador copió ese
        // criterio y quedaron como texto el resultado del Número Ácido, los
        // PCB, los metales, las partículas y la viscosidad.
        $numericas = [
            'pcb.contenido_total_de_pcbs',
            'metales_en_aceite.cobre_cu',
            'particulas.um_4',
            'viscocidad.resultado_mm2s',
            'inhibidor.resultado',
            'dbds.resultado',
            'fluidez.resultado',
            'inflamacion.resultado',
            'pasivador.resultado',
            'color.resultado',
        ];

        foreach ($numericas as $clave) {
            [$prueba, $campo] = explode('.', $clave);
            $this->assertSame(
                'number',
                $this->columna($prueba, $campo)->type,
                "La columna '{$clave}' mide un número y quedó declarada como texto."
            );
        }
    }

    public function test_las_fechas_del_ensayo_de_azufre_son_fechas(): void
    {
        // Éstas el sistema viejo SÍ las declaraba fecha (tipo 4); las degradó
        // el importador, que no mapeaba ese tipo y caía a texto en silencio.
        // Importa: el ensayo IEC 62535 es a 48 y a 72 horas, y sin fechas
        // comparables no se puede demostrar que la exposición duró lo debido.
        foreach (['azufre_1275b', 'azufre_62535_48_horas', 'azufre_62535_72_horas'] as $prueba) {
            $this->assertSame('date', $this->columna($prueba, 'fecha_inicial')->type);
            $this->assertSame('date', $this->columna($prueba, 'fecha_final')->type);
        }
    }

    public function test_las_clasificaciones_dejan_de_ser_texto_libre(): void
    {
        // Con texto libre conviven "Corrosivo", "corrosivo" y "CORROSIVO" en la
        // misma columna y ningún filtro las agrupa.
        $azufre = $this->columna('azufre_1275b', 'resultado');
        $this->assertSame('select', $azufre->type);
        $this->assertEqualsCanonicalizing(
            ['No Corrosivo', 'Corrosivo'],
            $azufre->options->pluck('value')->all()
        );

        // A 72 horas el vocabulario es DISTINTO: se evalúa además el depósito
        // de sulfuro de cobre en el papel.
        $this->assertEqualsCanonicalizing(
            ['Negativo sin depósitos', 'Positivo sin depósitos', 'Positivo con depósitos'],
            $this->columna('azufre_62535_72_horas', 'resultado')->options->pluck('value')->all()
        );

        $this->assertCount(12, $this->columna('azufre_1275b', 'astm_d130')->options);
    }

    public function test_el_cero_no_se_admite_donde_no_es_una_medicion(): void
    {
        // Una rigidez de 0 kV no existe, y un factor de potencia de exactamente
        // 0.000 % no es medible: es el "no medido" del sistema anterior, que
        // obligaba a llenar la celda.
        foreach ([
            ['rigidez_dielectrica', 'resultado_kv'],
            ['factor_de_potencia_100o', 'resultado'],
            ['numero_acido', 'peso_aceite_g'],
            ['furanos', 'furfuraldehido_2'],
        ] as [$prueba, $campo]) {
            $columna = $this->columna($prueba, $campo);
            $this->assertNotNull($columna->min_value, "{$prueba}.{$campo} sin cota inferior.");
            $this->assertTrue((bool) $columna->min_exclusive, "{$prueba}.{$campo} admite el cero.");
        }

        // Y donde el cero SÍ es real, no se toca: un gas no detectado es 0 ppm.
        $h2 = $this->columna('analisis_cromatografico', 'hidrogeno_h2_ppm');
        $this->assertFalse((bool) $h2->min_exclusive);
    }

    public function test_no_se_reviven_las_opciones_dadas_de_baja(): void
    {
        // El volcado trae opciones con `deleted = 1` que el bucle no filtraba.
        // Entre ellas, la errata 'PP-LA-01C-100.' con el punto al final.
        $this->assertSame(
            0,
            \App\Models\TestFieldOption::where('value', 'like', '%100.')->count()
        );
    }

    public function test_la_acreditacion_de_cada_opcion_no_se_pierde(): void
    {
        // El indicador "A" es el que imprime el "(A) Acreditado" y la nota de
        // la acreditación ISO/IEC 17025 en el informe. El importador copiaba
        // solo el texto de la opción y lo dejaba caer: es pérdida de dato con
        // consecuencia legal.
        $this->assertGreaterThan(
            0,
            \App\Models\TestFieldOption::where('accreditation_flag', 'A')->count(),
            'No quedó ninguna opción marcada como ensayo acreditado.'
        );

        // Y las que el laboratorio había retirado de la lista siguen retiradas.
        $this->assertGreaterThan(
            0,
            \App\Models\TestFieldOption::where('is_hidden', true)->count()
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
            LabTestFieldTypesSeeder::class,
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
