<?php

namespace Tests\Feature\Lab;

use App\Exports\LabManagement\LabReports\OtdReportExport;
use App\Exports\LabManagement\LabReports\ReceptionRegisterExport;
use App\Exports\LabManagement\LabReports\ReportsListExport;
use App\Exports\LabManagement\LabReports\SamplesFlatExport;
use App\Models\Customer;
use App\Models\Reception;
use App\Models\Sample;
use App\Models\SampleReport;
use App\Models\SampleTest;
use App\Models\TestDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Los 7 Excel del menú "Reportes de Lab." (portados del sistema antiguo).
 *
 * Lo que se fija: el gate por permiso propio, que cada descarga responda un
 * XLSX, y las REGLAS de negocio que el viejo tenía rotas o ambiguas y acá se
 * fijaron: el cálculo OTD unificado (entrega−recepción ≤ 5, emisión ≤ 2,
 * entrega tras emisión ≤ 3), el listado que solo trae informes principales,
 * y el desglose por prueba que cuenta MUESTRAS (el dato real), no envases.
 */
class LabReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([LaravelLocalizationRedirectFilter::class, LocaleSessionRedirect::class]);

        DB::table('languages')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish', 'iso_code' => 'es', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('locales')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'code' => 'es_PE', 'name' => 'Espanol', 'language_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('regions')->insertOrIgnore([['id' => 999, 'slug' => Str::random(22), 'name' => '__bs__', 'is_active' => false, 'deleted_at' => now(), 'deleted_description' => 'bs', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('countries')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Peru', 'iso_code' => 'PE', 'currency' => 'PEN', 'timezone' => 'America/Lima', 'default_locale_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('tenants')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'lab_reports.view', 'guard_name' => 'web']);

        // Las aserciones de texto ("Sí"/"Entregado") son sobre el español.
        app()->setLocale('es');
    }

    // ─── Acceso ──────────────────────────────────────────────────────────

    public function test_sin_el_permiso_no_se_entra_ni_se_descarga(): void
    {
        $intruso = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);

        $this->actingAs($intruso)
            ->get(route('lab_management.lab_reports.index'))
            ->assertRedirect();

        $this->actingAs($intruso)
            ->get(route('lab_management.lab_reports.download', ['report' => 'otd', 'from' => '2026-01-01']))
            ->assertRedirect();
    }

    public function test_cada_reporte_descarga_un_xlsx(): void
    {
        $this->recepcion();

        foreach (['otd', 'rlabs', 'rems', 'fims', 'jobs', 'ents', 'listado'] as $reporte) {
            $respuesta = $this->actingAs($this->usuario())
                ->get(route('lab_management.lab_reports.download', ['report' => $reporte, 'from' => '2026-01-01']));

            $respuesta->assertOk();
            $this->assertStringContainsString(
                'attachment',
                $respuesta->headers->get('content-disposition', ''),
                "El reporte {$reporte} no respondió una descarga.",
            );
        }
    }

    public function test_un_reporte_inexistente_no_descarga_nada(): void
    {
        // El shell convierte el 404 autenticado en redirección al dashboard
        // (bootstrap/app.php); lo que se fija es que NO salga un archivo.
        $respuesta = $this->actingAs($this->usuario())
            ->get(route('lab_management.lab_reports.download', ['report' => 'nope', 'from' => '2026-01-01']));

        $respuesta->assertRedirect();
        $this->assertStringNotContainsString('attachment', $respuesta->headers->get('content-disposition', ''));
    }

    // ─── El cálculo OTD (la regla que el viejo tenía cuadruplicada) ──────

    public function test_otd_calcula_los_tres_plazos_contra_sus_umbrales(): void
    {
        // Recibida el 1, emitida el 2 (1 día ≤ 2: bien), entregada el 8
        // (7 días desde recepción > 5: mal; 6 días tras emisión > 3: mal).
        $recepcion = $this->recepcion(['received_at' => '2026-03-01 09:00:00']);
        $muestra = $this->muestra($recepcion);
        $this->informe($muestra, ['status' => SampleReport::STATUS_ISSUED, 'issued_at' => '2026-03-02', 'delivered_at' => '2026-03-08']);

        $this->actingAs($this->usuario());
        $filas = (new OtdReportExport('2026-01-01', null))->array();

        $this->assertCount(2, $filas);
        [, $fila] = $filas;

        $this->assertSame(7, $fila[3], 'OTD = entrega - recepción');
        $this->assertSame('No', $fila[4], '7 días supera el máximo de 5');
        $this->assertSame(1, $fila[5], 'emisión - recepción');
        $this->assertSame('Sí', $fila[6], '1 día cumple el máximo de 2');
        $this->assertSame(6, $fila[7], 'entrega - emisión');
        $this->assertSame('No', $fila[8], '6 días supera el máximo de 3');
        $this->assertSame($muestra->code, $fila[11]);
    }

    public function test_otd_sin_fechas_no_revienta_ni_inventa(): void
    {
        // El estilo del XLSX viejo hacía `date_emi > date_ent` sobre nil y el
        // export entero moría con 500. Acá el borrador sin fechas sale con "-".
        $recepcion = $this->recepcion();
        $this->informe($this->muestra($recepcion), ['status' => SampleReport::STATUS_DRAFT]);

        $this->actingAs($this->usuario());
        [, $fila] = (new OtdReportExport('2026-01-01', null))->array();

        $this->assertSame('-', $fila[1]);
        $this->assertSame('-', $fila[3]);
        $this->assertSame('-', $fila[4]);
    }

    // ─── Listado de Reportes: solo principales, estado real ──────────────

    public function test_el_listado_solo_trae_informes_principales(): void
    {
        $recepcion = $this->recepcion();
        $muestra = $this->muestra($recepcion);
        $this->informe($muestra, ['kind' => SampleReport::KIND_PRIMARY, 'status' => SampleReport::STATUS_ISSUED, 'issued_at' => '2026-03-02', 'delivered_at' => '2026-03-03']);
        $this->informe($muestra, ['kind' => SampleReport::KIND_ADDITIONAL, 'number' => 91]);

        $this->actingAs($this->usuario());
        $filas = (new ReportsListExport('2026-01-01', null))->array();

        $this->assertCount(2, $filas, 'cabecera + solo el principal');
        $this->assertSame('Entregado', $filas[1][7]);
    }

    // ─── El desglose por prueba cuenta muestras reales ───────────────────

    public function test_el_formato_de_ingreso_cuenta_muestras_por_familia_de_prueba(): void
    {
        $recepcion = $this->recepcion(['packages' => 4]);
        $fiqui = $this->prueba('numero_acido', 'fisicoquimico');
        $croma = $this->prueba('analisis_cromatografico', 'analisis_cromatografico');

        $m1 = $this->muestra($recepcion);
        $m2 = $this->muestra($recepcion);

        foreach ([[$m1, $fiqui], [$m2, $fiqui], [$m2, $croma]] as [$m, $p]) {
            SampleTest::create(['sample_id' => $m->id, 'test_definition_id' => $p->id, 'status' => SampleTest::STATUS_PENDING, 'tenant_id' => 1]);
        }

        $this->actingAs($this->usuario());
        $filas = (new ReceptionRegisterExport('2026-01-01', null))->array();

        // Fila 3 = la recepción (2 de cabecera). Col 7 = fisicoquímico, col 8 = cromatografía.
        $this->assertSame(2, $filas[2][6], 'dos muestras piden fisicoquímico');
        $this->assertSame(1, $filas[2][7], 'una muestra pide cromatografía');
        $this->assertSame(4, $filas[2][21], 'el total de envases es el capturado');
    }

    public function test_el_registro_plano_dice_el_avance_en_si_no(): void
    {
        $recepcion = $this->recepcion();
        $muestra = $this->muestra($recepcion);
        $prueba = $this->prueba('numero_acido', 'fisicoquimico');
        SampleTest::create(['sample_id' => $muestra->id, 'test_definition_id' => $prueba->id, 'status' => SampleTest::STATUS_VALIDATED, 'tenant_id' => 1]);

        $this->actingAs($this->usuario());
        [, $fila] = (new SamplesFlatExport('2026-01-01', null))->array();

        $this->assertSame('No', $fila[8], 'sin equipo asignado');
        $this->assertSame('Sí', $fila[9], 'con pruebas pedidas');
        $this->assertSame('Sí', $fila[10], 'con valores (validada)');
        $this->assertSame('No', $fila[11], 'sin informe');
    }

    // ─── Fixtures ────────────────────────────────────────────────────────

    private function usuario(): User
    {
        $rol = Role::firstOrCreate(['name' => 'perfil_reportes', 'guard_name' => 'web'], ['description' => 'Prueba']);
        $rol->syncPermissions(Permission::where('name', 'lab_reports.view')->get());

        $usuario = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $usuario->assignRole($rol);

        return $usuario;
    }

    private function recepcion(array $extra = []): Reception
    {
        $cliente = Customer::create([
            'slug' => Str::random(22), 'tenant_id' => 1,
            'name' => 'Cliente '.Str::random(5),
        ]);

        return Reception::create(array_merge([
            'slug' => Str::random(22), 'tenant_id' => 1,
            'customer_id' => $cliente->id,
            'received_at' => '2026-03-01 09:00:00',
            'status' => Reception::STATUS_CONFIRMED,
            'service_order' => '700012345',
        ], $extra));
    }

    private function muestra(Reception $recepcion): Sample
    {
        $numero = Sample::withoutGlobalScopes()->count() + 1;

        return Sample::create([
            'slug' => Str::random(22), 'tenant_id' => 1,
            'reception_id' => $recepcion->id,
            'year' => 2026, 'number' => $numero,
            'code' => Sample::formatCode(2026, $numero),
        ]);
    }

    private function informe(Sample $muestra, array $extra = []): SampleReport
    {
        $numero = $extra['number'] ?? (SampleReport::withoutGlobalScopes()->count() + 1);

        return SampleReport::create(array_merge([
            'slug' => Str::random(22), 'tenant_id' => 1,
            'sample_id' => $muestra->id,
            'year' => 2026, 'number' => $numero,
            'code' => sprintf('REP-LAB-2026-%04d', $numero),
            'kind' => SampleReport::KIND_PRIMARY,
            'status' => SampleReport::STATUS_DRAFT,
        ], $extra));
    }

    private function prueba(string $code, string $grupo): TestDefinition
    {
        return TestDefinition::firstOrCreate(
            ['code' => $code],
            ['slug' => Str::random(22), 'name' => ucfirst($code), 'report_comment_group' => $grupo],
        );
    }
}
