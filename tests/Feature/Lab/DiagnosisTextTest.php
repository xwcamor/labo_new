<?php

namespace Tests\Feature\Lab;

use App\Models\Analyte;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Reception;
use App\Models\Result;
use App\Models\Sample;
use App\Models\SampleDiagnosis;
use App\Models\SampleTest;
use App\Models\TestDefinition;
use App\Services\Lab\DiagnosisTextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * El párrafo de opinión del informe.
 *
 * Lo que se fija acá es lo que el sistema anterior resolvía con ~1134 líneas de
 * ERB: los cortes por valor de cada familia y el número medido dentro de la
 * frase. Allá mover el corte del pasivador de 70 a 80 ppm era editar una vista
 * y desplegar; acá es una línea de `diagnosis_templates.json`, y estas pruebas
 * son las que avisan si al moverla se rompió un escalón.
 */
class DiagnosisTextTest extends TestCase
{
    use RefreshDatabase;

    private DiagnosisTextService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Las plantillas del laboratorio están redactadas en español y la
        // conjunción de las listas sale del archivo de idioma: sin fijar el
        // idioma la frase saldría mitad y mitad.
        $this->app->setLocale('es');

        $this->seedParentRows();
        $this->service = new DiagnosisTextService();
    }

    // ─── Bandas: cada escalón dice algo distinto ─────────────────────────

    /**
     * Los cuatro cortes del grado de polimerización del sistema anterior
     * (`_form_add_details_polis_default_values.html.erb`): 1000, 650 y 350.
     * Antes de esto la familia tenía UNA frase para todo, así que un papel con
     * DP 1100 y otro con DP 200 salían diciendo lo mismo.
     */
    public function test_cada_banda_del_grado_de_polimerizacion_da_un_texto_distinto(): void
    {
        $textos = [];

        foreach ([1100.0, 800.0, 500.0, 200.0] as $dp) {
            $textos[] = $this->diagnosticar('grado_de_polimerizacion', [['dp', $dp]]);
        }

        $this->assertCount(4, array_unique($textos), 'Dos bandas devolvieron el mismo párrafo.');

        $this->assertStringContainsString('Nuevo', $textos[0]);
        $this->assertStringContainsString('Bueno', $textos[1]);
        $this->assertStringContainsString('Medianamente envejecido', $textos[2]);
        $this->assertStringContainsString('Envejecido', $textos[3]);
    }

    public function test_los_cortes_de_furanos_caen_donde_los_dejo_el_sistema_anterior(): void
    {
        // El valor que se lee no son los furanos sino el grado de
        // polimerización que se calcula del 2-FAL, igual que allá (@fur_value).
        // El corte pertenece a la banda de arriba: 700 es "buen estado", no
        // "medianamente envejecido".
        $this->assertStringContainsString('en buen estado', $this->diagnosticar('furanos', [['dp', 700.0]]));
        $this->assertStringContainsString('medianamente envejecido', $this->diagnosticar('furanos', [['dp', 699.9]]));
        $this->assertStringContainsString('medianamente envejecido', $this->diagnosticar('furanos', [['dp', 450.0]]));
        $this->assertStringContainsString('envejecido', $this->diagnosticar('furanos', [['dp', 449.9]]));
        $this->assertStringContainsString('severamente degradado', $this->diagnosticar('furanos', [['dp', 249.9]]));
    }

    public function test_el_escalon_del_pasivador_incluye_su_tope(): void
    {
        // El sistema anterior escribía `>= 50 && <= 70` para el escalón del
        // medio y `> 70` para el de arriba: los 70 ppm exactos son "justo", no
        // "bueno". Es el caso que se pierde solo si alguien escribe las bandas
        // como [min, max) sin pensar en el borde.
        $this->assertStringContainsString('es justo', $this->diagnosticar('pasivador', [['passivator', 70.0]]));
        $this->assertStringContainsString('es bueno', $this->diagnosticar('pasivador', [['passivator', 70.1]]));
        $this->assertStringContainsString('es deficiente', $this->diagnosticar('pasivador', [['passivator', 49.9]]));
        $this->assertStringContainsString('es justo', $this->diagnosticar('pasivador', [['passivator', 50.0]]));
    }

    public function test_una_banda_trae_su_recomendacion_de_accion(): void
    {
        // Las recomendaciones ("rellene con un pasivador nuevo, hasta al menos
        // 100 ppm") son parte del texto de la banda, no una frase aparte: en el
        // sistema anterior también viajaban pegadas al escalón.
        $texto = $this->diagnosticar('pasivador', [['passivator', 12.0]]);

        $this->assertStringContainsString('hasta al menos 100 ppm', $texto);
    }

    public function test_un_valor_fuera_de_todas_las_bandas_no_inventa_texto(): void
    {
        // El inhibidor por encima de 3 % no tenía frase en el sistema anterior
        // y sigue sin tenerla. Rellenar el hueco con la banda de al lado sería
        // firmar una interpretación que nadie escribió.
        $this->assertNull($this->diagnosticar('inhibidor', [['inhibitor', 4.0]]));
        $this->assertStringContainsString('Tipo I', $this->diagnosticar('inhibidor', [['inhibitor', 0.05]]));
        $this->assertStringContainsString('Tipo II', $this->diagnosticar('inhibidor', [['inhibitor', 0.08]]));
    }

    public function test_un_valor_censurado_solo_entra_donde_no_hay_duda(): void
    {
        // "<5 mg/kg" es todo lo que hay por debajo de 5: cae entero en la banda
        // baja del DBDS y se diagnostica. "<60 ppm" de pasivador toca dos
        // escalones a la vez, así que el ensayo no dijo en cuál está.
        $bajo = $this->diagnosticar('dbds', [['dbds', 5.0, ['qualifier' => Result::QUALIFIER_LT]]]);
        $this->assertStringContainsString('por debajo del valor de referencia', $bajo);

        $this->assertNull($this->diagnosticar('pasivador', [
            ['passivator', 60.0, ['qualifier' => Result::QUALIFIER_LT]],
        ]));
    }

    /**
     * El barrido: TODAS las familias con bandas del archivo real, una sonda por
     * banda, y ningún par de bandas diciendo lo mismo.
     *
     * Es la red que atrapa el error más probable al editar los cortes: dejar
     * dos bandas pisándose (y entonces el párrafo sale vacío, porque el motor
     * no elige por orden de aparición) o repetir un texto al copiar el escalón
     * de arriba.
     */
    public function test_todas_las_bandas_del_archivo_real_dan_un_texto_propio(): void
    {
        $conBandas = collect($this->plantillasReales())
            ->filter(fn ($p) => ! empty($p['bands']) && isset($p['analyte']));

        $this->assertGreaterThanOrEqual(6, $conBandas->count());

        foreach ($conBandas as $plantilla) {
            $codigo = $plantilla['analyte'];

            if (Analyte::withoutGlobalScopes()->where('code', $codigo)->doesntExist()) {
                $this->fail("La plantilla de '{$plantilla['family']}' cita el parámetro '{$codigo}', que no existe.");
            }

            $textos = [];

            foreach ($plantilla['bands'] as $i => $banda) {
                $sonda = $this->sonda($banda);
                $texto = $this->diagnosticar($plantilla['family'], [[$codigo, $sonda]]);

                $this->assertNotNull(
                    $texto,
                    "{$plantilla['family']}: la banda {$i} no devolvió texto con {$sonda}. "
                    . '¿Se pisa con otra?',
                );

                $textos[] = $texto;
            }

            $this->assertCount(
                count($textos),
                array_unique($textos),
                "{$plantilla['family']}: dos bandas devuelven el mismo párrafo.",
            );
        }
    }

    // ─── {value}: el número medido dentro de la frase ────────────────────

    public function test_el_marcador_value_imprime_el_numero_medido_con_su_unidad(): void
    {
        // "Se detectó 7.3 mg/kg de dibencil disulfuro". Sin esto el párrafo
        // decía que el DBDS excedía la referencia sin decir por cuánto, que es
        // justo el dato con el que el cliente decide si agrega pasivador.
        $texto = $this->diagnosticar('dbds', [['dbds', 7.3]]);

        $this->assertStringContainsString('Se detectó 7.30 mg/kg de dibencil disulfuro', $texto);
    }

    public function test_el_marcador_value_respeta_los_decimales_y_el_signo_del_parametro(): void
    {
        // El punto de inflamación se informa sin decimales, y un valor
        // censurado conserva su signo: ">300 °C" no es "300 °C".
        $this->assertStringContainsString(
            'punto de inflamación a 148 °C.',
            $this->diagnosticar('inflamacion', [['flash', 148.0]]),
        );

        $this->assertStringContainsString(
            'punto de fluidez a >-30 °C.',
            $this->diagnosticar('fluidez', [['pour', -30.0, ['qualifier' => Result::QUALIFIER_GT]]]),
        );
    }

    public function test_el_marcador_value_parte_un_codigo_compuesto(): void
    {
        // El ISO 4406 es "18/16/13" y el párrafo explica qué significa cada
        // tramo. La familia real —partículas— todavía no declara parámetro
        // medible, así que el corte se verifica sobre una plantilla propia.
        $this->plantillaDePrueba([
            'family' => 'particulas',
            'oil_types' => [], 'equipment_types' => [], 'case' => 'any',
            'body' => 'ISO {value_num}: {value_num[1]} a 4μm, {value_num[2]} a 6μm, {value_num[3]} a 14μm.',
        ]);

        $texto = $this->diagnosticar('particulas', [['iso_demo', null, ['value_text' => '18/16/13']]]);

        $this->assertSame('ISO 18/16/13: 18 a 4μm, 16 a 6μm, 13 a 14μm.', $texto);
    }

    public function test_el_marcador_value_pide_un_parametro_por_su_codigo(): void
    {
        // Con varios resultados en la familia, `{value}` a secas no adivina: la
        // plantilla nombra el parámetro que quiere citar.
        $this->plantillaDePrueba([
            'family' => 'fisicoquimico',
            'oil_types' => [], 'equipment_types' => [], 'case' => 'any',
            'body' => 'Agua {value:wat}, rigidez {value:rig} ({unit:rig}).',
        ]);

        $texto = $this->diagnosticar('fisicoquimico', [['wat', 12.0], ['rig', 47.0]]);

        $this->assertSame('Agua 12.0 ppm, rigidez 47.0 kV (kV).', $texto);
    }

    // ─── Umbral de presencia (los metales) ───────────────────────────────

    public function test_el_umbral_reemplaza_al_cuadro_de_limites_cuando_no_hay_cuadro(): void
    {
        // Los metales nunca tuvieron criterio de aceptación: el sistema
        // anterior los nombraba por presencia, con un `> 0.05` escrito en la
        // vista de carga. Acá el corte es un dato de la plantilla, y la lista
        // sale con su valor y sin la coma colgando que salía impresa allá.
        $this->plantillaDePrueba([
            'family' => 'metales_en_aceite',
            'oil_types' => [], 'equipment_types' => [], 'case' => 'many',
            'threshold' => 0.05,
            'body' => 'Se detectó {failed_values} ({count}).',
        ]);

        $texto = $this->diagnosticar('metales_en_aceite', [
            ['met_al', 7.3], ['met_cu', 2.1], ['met_fe', 0.05],
        ]);

        $this->assertSame('Se detectó 7.3 ppm de aluminio y 2.1 ppm de cobre (2).', $texto);
    }

    // ─── Lo que no se dice ───────────────────────────────────────────────

    public function test_una_familia_sin_resultados_no_recibe_parrafo(): void
    {
        // Es el caso de partículas y metales hoy: la prueba se validó pero
        // ninguna de sus columnas declara parámetro medible. Decir "no se
        // detectó presencia de metales" sobre cero mediciones es la misma
        // afirmación falsa que leer un cuadro de límites ausente como "cumple".
        $muestra = $this->muestraCon('metales_en_aceite', []);

        $this->service->generate($muestra);

        $this->assertSame(0, SampleDiagnosis::where('sample_id', $muestra->id)->count());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /**
     * Diagnostica una muestra con esos resultados y devuelve el párrafo.
     *
     * @param array<int,array{0:string,1:?float,2?:array<string,mixed>}> $resultados
     */
    private function diagnosticar(string $familia, array $resultados): ?string
    {
        $muestra = $this->muestraCon($familia, $resultados);

        return $this->service->generate($muestra)->firstWhere('family', $familia)?->body;
    }

    /**
     * @param array<int,array{0:string,1:?float,2?:array<string,mixed>}> $resultados
     */
    private function muestraCon(string $familia, array $resultados): Sample
    {
        $prueba = TestDefinition::firstOrCreate(
            ['code' => $familia],
            ['slug' => Str::random(22), 'name' => $familia, 'report_comment_group' => $familia],
        );

        $cliente = Customer::create([
            'slug' => Str::random(22), 'name' => 'Cliente ' . Str::random(6), 'tenant_id' => 1,
        ]);
        $recepcion = Reception::create([
            'slug' => Str::random(22), 'customer_id' => $cliente->id,
            'received_at' => now(), 'tenant_id' => 1, 'status' => Reception::STATUS_CONFIRMED,
        ]);
        $equipo = Equipment::create([
            'slug' => Str::random(22), 'name' => 'Equipo ' . Str::random(4),
            'tenant_id' => 1, 'oil_type_id' => 1, 'equipment_type_id' => 1,
        ]);

        $numero = Sample::where('tenant_id', 1)->count() + 1;
        $muestra = Sample::create([
            'slug' => Str::random(22), 'reception_id' => $recepcion->id,
            'equipment_id' => $equipo->id, 'oil_type_id' => 1,
            'year' => 2026, 'number' => $numero,
            'code' => Sample::formatCode(2026, $numero),
            'tenant_id' => 1, 'is_urgent' => false,
        ]);

        SampleTest::create([
            'sample_id' => $muestra->id, 'test_definition_id' => $prueba->id,
            'status' => SampleTest::STATUS_VALIDATED, 'tenant_id' => 1,
        ]);

        foreach ($resultados as $fila) {
            [$codigo, $valor] = $fila;
            $extra = $fila[2] ?? [];
            $analito = Analyte::withoutGlobalScopes()->where('code', $codigo)->firstOrFail();

            Result::create(array_merge([
                'tenant_id' => 1, 'equipment_id' => $equipo->id,
                'analyte_id' => $analito->id, 'measured_at' => now()->toDateString(),
                'test_definition_id' => $prueba->id, 'sample_id' => $muestra->id,
                'value_num' => $valor, 'unit' => $analito->unit,
            ], $extra));
        }

        return $muestra->fresh();
    }

    /**
     * Un valor que cae dentro de la banda, sin tocar sus bordes.
     *
     * @param array<string,mixed> $banda
     */
    private function sonda(array $banda): float
    {
        $min = isset($banda['min']) ? (float) $banda['min'] : null;
        $max = isset($banda['max']) ? (float) $banda['max'] : null;

        return match (true) {
            $min !== null && $max !== null => ($min + $max) / 2,
            $min !== null                  => $min + max(1.0, abs($min) * 0.1),
            default                        => $max - max(0.0001, abs($max) * 0.1),
        };
    }

    /** @return array<int,array<string,mixed>> */
    private function plantillasReales(): array
    {
        $ruta = database_path('seeders/data/diagnosis_templates.json');

        return json_decode((string) file_get_contents($ruta), true)['templates'] ?? [];
    }

    /**
     * Mete una plantilla en el archivo de datos, solo para esta prueba.
     *
     * El servicio lee `diagnosis_templates.json` del disco, así que las
     * plantillas de familias que todavía no declaran parámetro medible se
     * verifican con una propia en vez de tocar el archivo real.
     *
     * @param array<string,mixed> $plantilla
     */
    private function plantillaDePrueba(array $plantilla): void
    {
        $ruta = database_path('seeders/data/diagnosis_templates.json');
        $datos = json_decode((string) file_get_contents($ruta), true);

        // Va PRIMERA y con la misma especificidad, así que gana el orden: el
        // motor recorre de la más específica a la más general y se queda con la
        // primera que aplique.
        array_unshift($datos['templates'], $plantilla);

        $servicio = new DiagnosisTextService();
        $reflejo = new \ReflectionProperty($servicio, 'plantillas');
        $reflejo->setValue($servicio, $datos['templates']);

        $this->service = $servicio;
    }

    private function seedParentRows(): void
    {
        DB::table('languages')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish',
            'iso_code' => 'es', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('tenants')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio',
            'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('oil_types')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'code' => 'mineral', 'name' => 'Mineral',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('equipment_types')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'code' => 'potencia', 'name' => 'Potencia',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]]);

        // Los parámetros que las plantillas citan por su código, con los
        // decimales y la unidad con los que se imprimen en el informe.
        $analitos = [
            ['dp', 'Grado de polimerización', null, 0, 'papel'],
            ['passivator', 'Pasivador', 'ppm', 1, 'otros'],
            ['inhibitor', 'Inhibidor de oxidación', '%', 3, 'otros'],
            ['dbds', 'DBDS', 'mg/kg', 2, 'otros'],
            ['flash', 'Punto de inflamación', '°C', 0, 'fiqui'],
            ['pour', 'Punto de fluidez', '°C', 0, 'fiqui'],
            ['wat', 'Contenido de agua', 'ppm', 1, 'fiqui'],
            ['rig', 'Rigidez dieléctrica', 'kV', 1, 'fiqui'],
            ['sediments', 'Sedimentos', null, 3, 'otros'],
            ['visc', 'Viscosidad cinemática', 'mm²/s', 2, 'fiqui'],
            ['iso_demo', 'Código ISO 4406', null, 0, 'otros'],
            ['met_al', 'Aluminio', 'ppm', 1, 'otros'],
            ['met_cu', 'Cobre', 'ppm', 1, 'otros'],
            ['met_fe', 'Fierro', 'ppm', 1, 'otros'],
        ];

        foreach ($analitos as [$code, $name, $unit, $decimals, $group]) {
            DB::table('analytes')->insertOrIgnore([[
                'slug' => Str::random(22), 'code' => $code, 'name' => $name,
                'unit' => $unit, 'decimals' => $decimals, 'group' => $group,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]]);
        }
    }
}
