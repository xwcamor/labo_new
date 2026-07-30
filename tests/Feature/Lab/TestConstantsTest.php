<?php

namespace Tests\Feature\Lab;

use App\Models\TestDefinition;
use App\Models\TestField;
use Database\Seeders\LabTestFieldTypesSeeder;
use Database\Seeders\LabTestFormulasSeeder;
use Database\Seeders\LabTestTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Las CONSTANTES de las pruebas: los números que el analista no mide pero que
 * entran en el cálculo del resultado.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ESTO MERECE UN TEST PROPIO                                       │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El Factor KOH de la acidez es 0.514. Cambiarlo por 0.5531 no rompe nada, no
 * lanza ningún error y no aparece en ninguna pantalla: simplemente todos los
 * resultados de Número Ácido salen un 8 % más altos, indefinidamente, y el
 * laboratorio lo descubriría cuando un cliente reclame. Es la clase de dato que
 * se corrompe en silencio, y por eso queda clavado en un test.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ DE DÓNDE SALEN ESTOS NÚMEROS                                             │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Del volcado de la base de PRODUCCIÓN del sistema anterior
 * (`docs/migracion/esquema/catalogos-definiciones.sql`, tabla
 * `lab_category_sub_details`, columnas `is_reuse` + `reuse_value`), que es donde
 * el sistema anterior los guardaba: no estaban en el código ni en un archivo de
 * configuración, eran el valor con el que se precargaba cada campo del
 * formulario de carga.
 *
 * OJO — `db/seeds.rb` del repositorio Ruby trae OTROS valores para los mismos
 * campos (0.5531 y 0.512 para la acidez, 21.6 y 0.997 para la tensión
 * interfacial). Es una instantánea vieja: los dos seeds versionados de ese
 * repositorio discrepan entre sí, y ninguno es la base que el laboratorio venía
 * usando. Si alguien "corrige" estos números contra `seeds.rb`, este test se cae
 * — y hace bien.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LO QUE SÍ CAMBIÓ RESPECTO DEL VIEJO                                      │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Allá el valor se copiaba como TEXTO a la fila del ensayo al guardar, así que
 * cambiar la constante no recalculaba el histórico —lo cual es correcto— pero
 * tampoco quedaba registro de cuándo cambió ni de cuál se usó. Acá la constante
 * es una columna tipada de la prueba, editable desde el editor de columnas, y el
 * valor usado queda en la fila de la hoja como número.
 */
class TestConstantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenants')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]]);

        // Se siembra el catálogo real: lo que se verifica son sus VALORES, así
        // que fabricar las pruebas a mano no probaría nada.
        $this->seed([
            LabTestTemplatesSeeder::class,
            LabTestFieldTypesSeeder::class,
            LabTestFormulasSeeder::class,
        ]);
    }

    /**
     * Los dieciséis valores precargados del volcado de producción.
     *
     * Formato: código de prueba → código de columna → valor.
     */
    private const CONSTANTES = [
        // El titulante y el blanco de la acidez. Son las DOS que de verdad
        // entran en la fórmula del resultado:
        //     (volumen gastado − blanco) × factor / peso de aceite
        'numero_acido' => [
            'factor_koh' => '0.514',
            'vol_blanco' => '0.181',
        ],

        // Condiciones del laboratorio precargadas. No entran en ningún cálculo:
        // son el valor "cómodo" que el analista arrastra de la última corrida y
        // puede sobrescribir. Se conservan porque el informe las imprime.
        'factor_de_potencia_25o' => [
            'temperatura_ambiente_oc' => '20.2',
            'humedad_ambiente'        => '60',
        ],
        'factor_de_potencia_100o' => [
            'temperatura_ambiente_oc' => '20.2',
            'humedad_ambiente'        => '66',
        ],
        'factor_de_potencia_90o' => [
            'temperatura_ambiente_oc' => '21.5',
            'humedad_ambiente'        => '62',
        ],
        'rigidez_dielectrica' => [
            'temperatura_ambiente_oc' => '20.2',
            'humedad_ambiente'        => '65',
        ],
        'rigidez_dielectrica_electrodos_planos' => [
            'temperatura_ambiente_oc' => '21.3',
            'humedad_ambiente'        => '41',
        ],

        // La referencia del agua del tensiómetro. El rango de aceptación
        // (70-74 mN/m) vive en el NOMBRE de otra columna, no como validación:
        // eso sigue igual que en el viejo y está anotado como pendiente.
        'tension_interfacial' => [
            'temp_agua'     => '20.1',
            'densidad_agua' => '0.998',
        ],

        // La temperatura de ensayo de cada resistividad. Es la que le da nombre
        // a la prueba, y por eso viene fija.
        'resistividad_volumetrica_25o'  => ['temperatura_oc' => '25'],
        'resistividad_volumetrica_100o' => ['temperatura_oc' => '100'],
    ];

    public function test_las_constantes_conservan_el_valor_de_produccion(): void
    {
        foreach (self::CONSTANTES as $prueba => $columnas) {
            $definicion = TestDefinition::where('code', $prueba)->first();
            $this->assertNotNull($definicion, "No existe la prueba {$prueba}.");

            foreach ($columnas as $columna => $esperado) {
                $campo = TestField::where('test_definition_id', $definicion->id)
                    ->where('code', $columna)
                    ->first();

                $this->assertNotNull($campo, "No existe la columna {$prueba}.{$columna}.");
                $this->assertSame(
                    $esperado,
                    (string) $campo->default_value,
                    "La constante {$prueba}.{$columna} cambió de valor.",
                );
            }
        }
    }

    public function test_toda_constante_llega_precargada_al_formulario(): void
    {
        // Un valor por omisión que no se marca reutilizable no se precarga: el
        // analista tendría que teclear 0.514 en cada corrida, y ahí es donde se
        // tipea 0.541.
        foreach (self::CONSTANTES as $prueba => $columnas) {
            $definicion = TestDefinition::where('code', $prueba)->first();

            foreach (array_keys($columnas) as $columna) {
                $campo = TestField::where('test_definition_id', $definicion->id)
                    ->where('code', $columna)
                    ->first();

                $this->assertTrue(
                    (bool) $campo->is_reusable,
                    "La constante {$prueba}.{$columna} no se precarga en el formulario.",
                );
            }
        }
    }

    public function test_las_constantes_son_editables_y_no_estan_clavadas(): void
    {
        // La prueba de que son DATO: se cambia el valor de la columna y se lee
        // cambiado, sin tocar código. En el sistema anterior también eran dato
        // —y editables por cualquiera con acceso a esa pantalla, sin lista
        // blanca de parámetros (`permit!`)—, así que esto no es una mejora sino
        // una condición que no se puede perder.
        $definicion = TestDefinition::where('code', 'numero_acido')->firstOrFail();
        $campo = TestField::where('test_definition_id', $definicion->id)
            ->where('code', 'factor_koh')
            ->firstOrFail();

        $campo->update(['default_value' => '0.5531']);

        $this->assertSame('0.5531', (string) $campo->fresh()->default_value);
    }

    /**
     * Las constantes que viven DENTRO de una fórmula también son dato.
     *
     * La correlación de Chendong para el grado de polimerización lleva tres
     * números —1.51, 0.0035 y el factor 1000 de ppb a ppm— y en el sistema
     * anterior estaban dentro de un texto de JavaScript guardado en la base
     * (`lab_category_details.blur_calculation`), que la vista inyectaba en la
     * página con `html_safe`. Acá viven en `test_fields.formula`, que el editor
     * de columnas edita y el servidor evalúa: sigue siendo dato, sin ejecutar
     * JavaScript del laboratorio en el navegador de nadie.
     */
    public function test_las_constantes_de_la_formula_del_papel_estan_en_la_formula(): void
    {
        $furanos = TestDefinition::where('code', 'furanos')->firstOrFail();
        $campo = TestField::where('test_definition_id', $furanos->id)
            ->where('code', 'grado_de_polimerizacion')
            ->firstOrFail();

        $this->assertStringContainsString('1.51', $campo->formula);
        $this->assertStringContainsString('0.0035', $campo->formula);
        $this->assertStringContainsString('1000', $campo->formula);
    }

    public function test_la_formula_de_la_acidez_usa_sus_dos_constantes(): void
    {
        $acidez = TestDefinition::where('code', 'numero_acido')->firstOrFail();
        $campo = TestField::where('test_definition_id', $acidez->id)
            ->whereNotNull('formula')
            ->firstOrFail();

        // Por NOMBRE de columna, no por el número: así cambiar la constante desde
        // el editor cambia el cálculo, que es el punto de que sea dato.
        $this->assertStringContainsString('factor_koh', $campo->formula);
        $this->assertStringContainsString('vol_blanco', $campo->formula);
        $this->assertStringNotContainsString('0.514', $campo->formula);
    }
}
