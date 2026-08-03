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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\PermissionRegistrar;
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

        // Sin el redirector de idioma: `route()` genera la URL sin el prefijo
        // /es y el middleware la manda a /en antes de llegar al controlador.
        // Es la misma exclusión que usan las pruebas de Clientes.
        $this->withoutMiddleware([
            LaravelLocalizationRedirectFilter::class,
            LocaleSessionRedirect::class,
        ]);

        $this->seedParentRows();
        $this->payload = new TestReportPayload();

        // La ruta del informe está gateada por `receptions.view`: quien puede
        // ver la entrega puede imprimir su informe.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'receptions.view', 'guard_name' => 'web']);
        $rol = Role::create(['name' => 'lab_' . Str::random(6), 'guard_name' => 'web', 'description' => 'Prueba']);
        $rol->syncPermissions(Permission::where('name', 'receptions.view')->get());

        $usuario = User::factory()->create([
            'country_id' => 1, 'locale_id' => 1, 'tenant_id' => 1,
        ]);
        $usuario->assignRole($rol);

        $this->actingAs($usuario);

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


    // ─── La acreditación ─────────────────────────────────────────────────
    //
    // El sello del organismo acreditador es una afirmación con consecuencia
    // legal: dice que ESE ensayo está dentro del alcance del certificado.
    // Mientras el hecho y el rótulo vivieron en la misma columna de texto,
    // cualquier cadena no vacía contaba como acreditada —"NA" incluido— y el
    // informe estampaba el sello en páginas que no lo tenían.

    public function test_un_metodo_no_acreditado_no_acredita_la_pagina(): void
    {
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.10, min: null, max: 0.15, estado: 'in_spec');
        $this->normaCorrida($muestra, 'ASTM 3612 - Método C', flag: 'NA', acreditado: false);

        $seccion = $this->payload->forSample($muestra)['sections'][0];

        $this->assertSame('NA', $seccion['rows'][0]['accreditation']);
        $this->assertFalse($seccion['rows'][0]['accredited']);
        $this->assertFalse($seccion['accredited'], 'La página no debe llevar el sello.');
        $this->assertTrue($seccion['not_accredited']);
    }

    public function test_un_metodo_acreditado_acredita_la_pagina(): void
    {
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.10, min: null, max: 0.15, estado: 'in_spec');
        $this->normaCorrida($muestra, 'ASTM D974', flag: 'A', acreditado: true);

        $seccion = $this->payload->forSample($muestra)['sections'][0];

        $this->assertTrue($seccion['accredited']);
        $this->assertFalse($seccion['not_accredited']);
    }

    // ─── El límite de detección ──────────────────────────────────────────
    //
    // El informe acreditado no publica el número medido cuando cae por debajo
    // del límite de detección: el método no distingue 0.4 ppm de 0.7 ppm, y
    // publicar el número sugiere una precisión que el ensayo no tiene.

    public function test_por_debajo_del_limite_de_deteccion_se_imprime_el_limite(): void
    {
        $this->columna->forceFill(['detection_limit' => 1])->save();

        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.40, min: null, max: 150, estado: 'in_spec');

        $fila = $this->payload->forSample($muestra)['sections'][0]['rows'][0];

        $this->assertSame('< 1', $fila['value']);
    }

    public function test_en_el_limite_exacto_ya_se_informa_el_numero(): void
    {
        // El corte es estricto: el límite ES informable. Con "menor o igual",
        // una medición que da justo el límite se publicaría como si no se
        // hubiera podido medir.
        $this->columna->forceFill(['detection_limit' => 1])->save();

        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 1.00, min: null, max: 150, estado: 'in_spec');

        $this->assertSame('1.00', $this->payload->forSample($muestra)['sections'][0]['rows'][0]['value']);
    }

    public function test_el_limite_de_deteccion_no_cambia_el_veredicto(): void
    {
        // Es lo que separa este cambio del error del sistema viejo: el papel y
        // el criterio no pueden discrepar. El veredicto se congeló al validar
        // con el valor medido y el límite de detección no lo toca.
        $this->columna->forceFill(['detection_limit' => 1])->save();

        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.40, min: 47, max: null, estado: 'out_of_spec');

        $fila = $this->payload->forSample($muestra)['sections'][0]['rows'][0];

        $this->assertSame('< 1', $fila['value']);
        $this->assertSame('out_of_spec', $fila['status'], 'El veredicto sale del resultado congelado, no del texto impreso.');
    }

    public function test_el_censurado_que_tipeo_el_analista_gana(): void
    {
        // Si el analista declaró "> 75" es su lectura, no la del catálogo.
        $this->columna->forceFill(['detection_limit' => 100])->save();

        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 75, min: null, max: null, estado: null, qualifier: 'gt');

        $this->assertSame('> 75.00', $this->payload->forSample($muestra)['sections'][0]['rows'][0]['value']);
    }

    public function test_sin_limite_de_deteccion_se_imprime_lo_medido(): void
    {
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.40, min: null, max: 150, estado: 'in_spec');

        $this->assertSame('0.40', $this->payload->forSample($muestra)['sections'][0]['rows'][0]['value']);
    }

    // ─── Qué comparte tabla ──────────────────────────────────────────────
    //
    // El informe acreditado dedica UNA página a "ENSAYOS FISICO-QUIMICOS" con
    // las trece pruebas en una sola tabla, y una a cada una de las demás. Trece
    // páginas de una fila, todas repitiendo la cabecera entera, no es el
    // formato acreditado — y es lo que salía mientras la columna que decide la
    // agrupación estuvo vacía en las 29 pruebas.

    public function test_las_pruebas_de_la_misma_familia_comparten_una_sola_tabla(): void
    {
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.10, min: null, max: 0.15, estado: 'in_spec');

        // La primera prueba es fisicoquímica; la segunda también.
        $this->prueba->forceFill(['report_comment_group' => 'fisicoquimico'])->save();
        $rigidez = $this->pruebaHermana('rigidez', 'Rigidez Dieléctrica', 'fisicoquimico', $muestra, 64.9);

        // La cromatografía NO: es su propia página.
        $this->pruebaHermana('cromas', 'Análisis Cromatográfico', 'cromas', $muestra, 25.52);

        $secciones = $this->payload->forSample($muestra)['sections'];

        $this->assertCount(2, $secciones, 'Las dos fisicoquímicas van juntas; la cromatografía aparte.');

        $fisico = collect($secciones)->firstWhere('family', 'fisicoquimico');
        $this->assertCount(2, $fisico['rows']);
        // Estas pruebas no tienen grupo en el catálogo, así que el título cae
        // al respaldo del archivo de idioma. El caso normal —con grupo— se fija
        // en el test siguiente.
        $this->assertSame(__('reports.family.fisicoquimico'), $fisico['test']);

        // El ítem se numera DENTRO de la página: la tabla se lee sola.
        $this->assertSame([1, 2], array_column($fisico['rows'], 'item'));

        $cromas = collect($secciones)->firstWhere('family', 'cromas');
        $this->assertCount(1, $cromas['rows']);
        $this->assertSame('Análisis Cromatográfico', $cromas['test']);

        unset($rigidez);
    }

    /**
     * El título de una página con varias pruebas es el de su FAMILIA.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ POR QUÉ NO ES EL GRUPO DEL CATÁLOGO                                  │
     * └──────────────────────────────────────────────────────────────────────┘
     * Esta prueba exigía el nombre del GRUPO ("Fisico Quimico"), con el
     * argumento de que el laboratorio lo edita desde Grupos de pruebas y el
     * rótulo del archivo de idioma no. El argumento era bueno y la premisa
     * falsa: grupo y familia son dos ejes distintos. El GRUPO ordena el
     * catálogo; la FAMILIA decide qué pruebas comparten HOJA en el informe.
     * Coinciden en fisicoquímico y no coinciden en el resto — las quince
     * pruebas que no son fiqui ni cromas viven en el grupo cajón de sastre
     * "Otros", y ahí adentro hay once familias.
     *
     * El resultado era que la hoja de los tres azufres se titulaba "OTROS" en
     * el informe moderno mientras el clásico, que sí usa la familia, titulaba
     * "AZUFRE CORROSIVO". El mismo papel con dos nombres.
     *
     * El grupo queda de respaldo para una familia sin rótulo declarado, que es
     * lo que fija la segunda mitad de esta prueba.
     */
    public function test_el_titulo_de_la_pagina_compartida_es_el_de_su_familia(): void
    {
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.10, min: null, max: 0.15, estado: 'in_spec');

        $grupo = \App\Models\TestGroup::create([
            'slug' => Str::random(22), 'name' => 'Fisico Quimico',
            'code' => 'fisico_quimico', 'sort_order' => 1, 'tenant_id' => 1,
        ]);

        $this->prueba->forceFill([
            'report_comment_group' => 'fisicoquimico',
            'test_group_id'        => $grupo->id,
        ])->save();
        $this->pruebaHermana('rigidez', 'Rigidez Dieléctrica', 'fisicoquimico', $muestra, 64.9);

        $fisico = collect($this->payload->forSample($muestra)['sections'])
            ->firstWhere('family', 'fisicoquimico');

        $this->assertSame(__('reports.family.fisicoquimico'), $fisico['test']);
    }

    public function test_una_familia_sin_rotulo_declarado_cae_al_grupo_del_catalogo(): void
    {
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.10, min: null, max: 0.15, estado: 'in_spec');

        $grupo = \App\Models\TestGroup::create([
            'slug' => Str::random(22), 'name' => 'Ensayos propios',
            'code' => 'propios', 'sort_order' => 1, 'tenant_id' => 1,
        ]);

        // Una familia que el laboratorio agregó y que no está en el archivo de
        // idioma: el papel no puede imprimir la clave con puntos.
        $this->prueba->forceFill([
            'report_comment_group' => 'familia_del_laboratorio',
            'test_group_id'        => $grupo->id,
        ])->save();
        $this->pruebaHermana('rigidez', 'Rigidez Dieléctrica', 'familia_del_laboratorio', $muestra, 64.9);

        $seccion = collect($this->payload->forSample($muestra)['sections'])
            ->firstWhere('family', 'familia_del_laboratorio');

        $this->assertSame('Ensayos propios', $seccion['test']);
    }

    public function test_dentro_de_la_tabla_cada_fila_lleva_su_propia_norma(): void
    {
        // Es la razón por la que la norma es por FILA y no por página: en la
        // hoja fisicoquímica el número ácido se corre con D974 y la rigidez con
        // D1816. Una norma por página obligaría a partirlas.
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.10, min: null, max: 0.15, estado: 'in_spec');
        $this->normaCorrida($muestra, 'ASTM D974', flag: 'A', acreditado: true);

        $this->prueba->forceFill(['report_comment_group' => 'fisicoquimico'])->save();
        $this->pruebaHermana('rigidez', 'Rigidez Dieléctrica', 'fisicoquimico', $muestra, 64.9);

        $seccion = collect($this->payload->forSample($muestra)['sections'])
            ->firstWhere('family', 'fisicoquimico');

        $normas = array_column($seccion['rows'], 'method');
        $this->assertContains('ASTM D974', $normas);
        $this->assertContains(null, $normas, 'La segunda prueba no declaró norma: sale en raya, no hereda la de la otra.');
    }

    // ─── Lo emitido se reimprime igual ───────────────────────────────────

    public function test_el_pdf_de_un_informe_emitido_sale_del_snapshot(): void
    {
        // Se emite con UNA sección. Después llega otra prueba validada: la
        // vista previa en vivo ya ve dos, pero el informe emitido tiene que
        // seguir imprimiendo la única que se firmó. Reimprimir "con lo último"
        // sería un segundo documento circulando con el mismo número.
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.10, min: null, max: 0.15, estado: 'in_spec');

        $informe = (new \App\Services\Lab\SampleReportService())->create($muestra, [], null);
        $informe->update([
            'status'   => \App\Models\SampleReport::STATUS_ISSUED,
            'snapshot' => $this->payload->forSample($muestra->fresh(), $informe),
        ]);

        // La segunda prueba llega DESPUÉS de emitir.
        $otra = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'agua', 'name' => 'Contenido de Agua',
        ]);
        $columna = TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $otra->id,
            'code' => 'valor', 'label' => 'Valor', 'type' => 'number',
            'role' => 'result', 'sort_order' => 1, 'decimals' => 0,
            'report_visible' => true,
        ]);
        SampleTest::create([
            'sample_id' => $muestra->id, 'test_definition_id' => $otra->id,
            'status' => SampleTest::STATUS_VALIDATED, 'tenant_id' => 1,
        ]);
        Result::create([
            'sample_id' => $muestra->id, 'test_definition_id' => $otra->id,
            'test_field_id' => $columna->id, 'analyte_id' => $this->analito->id,
            'value_num' => 18, 'unit' => 'ppm', 'replicate_no' => 1,
            'measured_at' => now(), 'spec_status' => 'in_spec',
            'spec_min' => null, 'spec_max' => 35, 'tenant_id' => 1,
        ]);

        // La vista previa en vivo ve las dos secciones…
        $this->assertCount(2, $this->payload->forSample($muestra->fresh())['sections']);

        // …y el PDF del informe emitido imprime la sección congelada.
        $this->get(route('lab_management.sample_reports.pdf', $informe))->assertOk();

        $log = \App\Models\AuditLog::where('event', 'report_generated')
            ->where('auditable_id', $muestra->id)
            ->latest('id')->first();

        $this->assertSame(1, $log->new_values['sections']);
        $this->assertSame($informe->code, $log->new_values['report']);
    }

    /**
     * El informe CLÁSICO solo lee los resultados que el payload publica.
     *
     * Las hojas de fisicoquímico y cromatografía del clásico se arman de los
     * resultados crudos (su maqueta es la del papel viejo), y leían TODOS los
     * de la muestra: una prueba que se dejó de pedir imprimía igual su fila,
     * con la celda de NORMA vacía —el payload sí la excluía— y corriendo la
     * numeración de los ítems. El filtro tiene que ser el MISMO del payload:
     * prueba validada/informada, selección del emisor, snapshot congelado.
     */
    public function test_el_clasico_solo_lee_los_resultados_que_el_informe_publica(): void
    {
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.28, min: null, max: 0.15, estado: 'out_of_spec');

        // Una segunda prueba con su resultado CARGADO… pero dada de baja (se
        // dejó de pedir). Su fila queda en `results` y no debe imprimirse.
        $fp25 = Analyte::create([
            'slug' => Str::random(22), 'code' => 'fp25', 'name' => 'Factor de potencia a 25 °C',
        ]);
        $otra = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'factor_de_potencia_25o', 'name' => 'Factor De Potencia 25º',
        ]);
        $columna = TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $otra->id,
            'code' => 'valor', 'label' => 'Valor', 'type' => 'number',
            'role' => 'result', 'sort_order' => 1, 'decimals' => 3, 'report_visible' => true,
        ]);
        SampleTest::create([
            'sample_id' => $muestra->id, 'test_definition_id' => $otra->id,
            'status' => SampleTest::STATUS_CANCELLED, 'tenant_id' => 1,
        ]);
        Result::create([
            'sample_id' => $muestra->id, 'test_definition_id' => $otra->id,
            'test_field_id' => $columna->id, 'analyte_id' => $fp25->id,
            'value_num' => 0.3, 'unit' => '%', 'replicate_no' => 1,
            'measured_at' => now(), 'spec_status' => null, 'tenant_id' => 1,
        ]);

        $datos = $this->payload->forSample($muestra->fresh());

        $renderer = new \App\Services\Lab\LegacyReportRenderer();
        $metodo = new \ReflectionMethod($renderer, 'resultadosPublicados');
        $metodo->setAccessible(true);
        $publicados = $metodo->invoke($renderer, $muestra->fresh(), $datos);

        $this->assertTrue($publicados->has('acid'));
        $this->assertFalse(
            $publicados->has('fp25'),
            'El resultado de una prueba dada de baja se sigue imprimiendo en el clásico.',
        );
    }

    /**
     * El formulario del informe no ofrece pruebas DADAS DE BAJA.
     *
     * Se listaban todas las de la muestra, así que el formulario mostraba
     * "Rigidez (sin validar)" sobre una prueba que se dejó de pedir y que la
     * recepción ya ni muestra: dos pantallas contando historias distintas.
     */
    public function test_el_formulario_del_informe_no_ofrece_pruebas_dadas_de_baja(): void
    {
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.10, min: null, max: 0.15, estado: 'in_spec');

        $baja = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'rigidez', 'name' => 'Rigidez Dieléctrica',
        ]);
        SampleTest::create([
            'sample_id' => $muestra->id, 'test_definition_id' => $baja->id,
            'status' => SampleTest::STATUS_CANCELLED, 'tenant_id' => 1,
        ]);

        $json = $this->getJson(route('lab_management.sample_reports.create', $muestra))->json();

        $this->assertCount(1, $json['tests']);
        $this->assertSame('Número Ácido', $json['tests'][0]['name']);
    }

    // ─── La emisión ──────────────────────────────────────────────────────

    public function test_emitir_deja_constancia_con_su_codigo_de_verificacion(): void
    {
        // El código impreso solo prueba algo si existe del lado del sistema.
        // Acá se comprueba las dos mitades: que se emite y que el portal
        // público lo encuentra.
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.10, min: null, max: 0.15, estado: 'in_spec');

        $this->get(route('lab_management.samples.report', $muestra))->assertOk();

        $log = \App\Models\AuditLog::where('event', 'report_generated')
            ->where('auditable_id', $muestra->id)
            ->latest('id')->first();

        $this->assertNotNull($log);
        $codigo = $log->new_values['verify_code'] ?? null;
        $this->assertMatchesRegularExpression('/^[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/', (string) $codigo);

        $this->get(route('report.verify', $codigo))
            ->assertOk()
            ->assertSee($muestra->code);
    }

    public function test_un_codigo_inventado_no_verifica(): void
    {
        $this->get(route('report.verify', 'AAAA-BBBB-CCCC'))
            ->assertOk()
            ->assertSee(__('reports.verify_fail'));
    }

    public function test_dos_emisiones_dan_codigos_distintos(): void
    {
        // Cada papel que sale es rastreable por separado: si el cliente reclama
        // sobre "el informe que me mandaron", el código dice cuál de todos es.
        $muestra = $this->muestraCon(SampleTest::STATUS_VALIDATED);
        $this->resultado($muestra, 0.10, min: null, max: 0.15, estado: 'in_spec');

        $this->get(route('lab_management.samples.report', $muestra))->assertOk();
        $this->travel(1)->seconds();
        $this->get(route('lab_management.samples.report', $muestra))->assertOk();

        $codigos = \App\Models\AuditLog::where('event', 'report_generated')
            ->where('auditable_id', $muestra->id)
            ->get()->pluck('new_values.verify_code');

        $this->assertCount(2, $codigos);
        $this->assertCount(2, $codigos->unique());
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

    /**
     * Otra prueba validada de la MISMA muestra, con su columna y su resultado.
     * `$familia` es lo que decide si comparte tabla con las demás.
     */
    private function pruebaHermana(
        string $codigo,
        string $nombre,
        string $familia,
        Sample $muestra,
        float $valor,
    ): TestDefinition {
        $prueba = TestDefinition::create([
            'slug' => Str::random(22), 'code' => $codigo, 'name' => $nombre,
            'report_comment_group' => $familia,
        ]);
        $columna = TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $prueba->id,
            'code' => 'valor', 'label' => 'Valor', 'type' => 'number',
            'role' => 'result', 'sort_order' => 1, 'decimals' => 2, 'report_visible' => true,
        ]);
        SampleTest::create([
            'sample_id' => $muestra->id, 'test_definition_id' => $prueba->id,
            'status' => SampleTest::STATUS_VALIDATED, 'tenant_id' => 1,
        ]);
        Result::create([
            'sample_id' => $muestra->id,
            'test_definition_id' => $prueba->id,
            'test_field_id' => $columna->id,
            'analyte_id' => $this->analito->id,
            'value_num' => $valor,
            'unit' => 'kV',
            'replicate_no' => 1,
            'measured_at' => now(),
            'spec_status' => 'in_spec',
            'spec_min' => 47,
            'spec_max' => null,
            'spec_source' => 'Mineral · 69-230 kV',
            'tenant_id' => 1,
        ]);

        return $prueba;
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

    /**
     * Deja escrito con qué norma se corrió el ensayo, como lo hace la bancada:
     * una columna de rol `standard`, su opción elegida y la fila de la hoja que
     * la apunta. El informe lee la norma DE AHÍ y no de la plantilla, porque es
     * la que de verdad se usó.
     */
    private function normaCorrida(Sample $muestra, string $norma, string $flag, bool $acreditado): void
    {
        $columna = TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->prueba->id,
            'code' => 'norma', 'label' => 'Norma', 'type' => 'select',
            'role' => 'standard', 'sort_order' => 2,
        ]);

        $opcion = \App\Models\TestFieldOption::create([
            'test_field_id' => $columna->id, 'value' => $norma, 'sort_order' => 1,
            'accreditation_flag' => $flag, 'is_accredited' => $acreditado,
        ]);

        $hoja = \App\Models\Worksheet::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->prueba->id,
            'run_date' => now()->toDateString(), 'status' => 'validated', 'tenant_id' => 1,
        ]);
        $fila = \App\Models\WorksheetRow::create([
            'worksheet_id' => $hoja->id, 'kind' => 'sample',
            'sample_id' => $muestra->id, 'sample_code' => $muestra->code, 'position' => 1,
        ]);
        \App\Models\WorksheetValue::create([
            'worksheet_row_id' => $fila->id, 'test_field_id' => $columna->id,
            'option_id' => $opcion->id, 'replicate_no' => 1, 'value_text' => $norma,
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
