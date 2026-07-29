<?php

namespace Tests\Feature\Lab;

use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\TestFieldOption;
use App\Models\User;
use App\Models\Worksheet;
use App\Models\WorksheetRow;
use App\Services\Lab\WorksheetService;
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
 * La vista previa del cálculo: lo que ve el analista mientras escribe.
 *
 * En el sistema Rails viejo eso lo hacía JavaScript guardado en una columna de
 * la base e inyectado en la página. Acá lo calcula el servidor con el MISMO
 * motor que el guardado, y estas pruebas sostienen las dos promesas de las que
 * depende que sirva para algo:
 *
 *   1. NO ESCRIBE NADA. Es una consulta, aunque se mande por POST.
 *   2. DA EL MISMO NÚMERO QUE EL GUARDADO sobre los mismos datos. Una vista
 *      previa que después no coincide con lo guardado es peor que no tenerla:
 *      el analista deja de creerle a la pantalla.
 */
class WorksheetPreviewTest extends TestCase
{
    use RefreshDatabase;

    private TestDefinition $definition;
    private User $analyst;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([LaravelLocalizationRedirectFilter::class, LocaleSessionRedirect::class]);

        $this->seedParentRows();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['worksheets.view', 'worksheets.edit'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->definition = $this->makeAcidNumberTest();
        $this->analyst = $this->userWith(['worksheets.view', 'worksheets.edit']);
    }

    /** Filas padre mínimas que exigen las claves foráneas de User. */
    private function seedParentRows(): void
    {
        DB::table('languages')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish', 'iso_code' => 'es',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('locales')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'code' => 'es_PE', 'name' => 'Español (PE)',
            'language_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('regions')->insertOrIgnore([[
            'id' => 999, 'slug' => Str::random(22), 'name' => '__bootstrap__', 'is_active' => false,
            'created_at' => now(), 'updated_at' => now(),
            'deleted_at' => now(), 'deleted_description' => 'Fixture de pruebas.',
        ]]);
        DB::table('countries')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Perú',
            'iso_code' => 'PE', 'currency' => 'PEN', 'timezone' => 'America/Lima',
            'default_locale_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]]);
        DB::table('tenants')->insertOrIgnore([[
            'id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
    }

    private function userWith(array $permissions): User
    {
        $role = Role::create([
            'name' => 'perfil_' . Str::random(6), 'guard_name' => 'web', 'description' => 'Prueba',
        ]);
        $role->syncPermissions(Permission::whereIn('name', $permissions)->get());

        $user = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * El Número Ácido con sus columnas y su fórmula reales:
     * (volumen gastado − volumen del blanco) × factor KOH / peso del aceite.
     *
     * Sin patrón ni duplicado obligatorios, porque lo que se prueba acá es el
     * cálculo y no la regla de control de calidad (esa tiene su propia prueba
     * en WorksheetServiceTest).
     */
    private function makeAcidNumberTest(array $overrides = []): TestDefinition
    {
        $definition = TestDefinition::create(array_merge([
            'slug' => Str::random(22), 'code' => 'acid', 'name' => 'Número Ácido',
        ], $overrides));

        $columns = [
            ['code' => 'nro_muestra',     'label' => 'Nº de Muestra', 'type' => 'text',   'role' => TestField::ROLE_SAMPLE_CODE, 'sort_order' => 1],
            ['code' => 'factor_koh',      'label' => 'Factor KOH',    'type' => 'number', 'sort_order' => 2],
            ['code' => 'volumen_blanco',  'label' => 'Vol. blanco',   'type' => 'number', 'sort_order' => 3],
            ['code' => 'peso_aceite',     'label' => 'Peso aceite',   'type' => 'number', 'sort_order' => 4],
            ['code' => 'volumen_gastado', 'label' => 'Vol. gastado',  'type' => 'number', 'sort_order' => 5],
            [
                'code' => 'resultado', 'label' => 'Resultado', 'type' => 'computed',
                'role' => TestField::ROLE_RESULT, 'sort_order' => 6, 'decimals' => 3,
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

    private function makeWorksheet(?TestDefinition $definition = null, string $status = Worksheet::STATUS_DRAFT): Worksheet
    {
        return Worksheet::create([
            'slug'               => Str::random(22),
            'test_definition_id' => ($definition ?? $this->definition)->id,
            'run_date'           => '2026-07-28',
            'status'             => $status,
            'tenant_id'          => 1,
        ]);
    }

    /** @param array<string,mixed> $values */
    private function preview(Worksheet $worksheet, array $values, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->analyst)->postJson(
            route('lab_management.worksheets.preview', $worksheet),
            ['values' => $values],
        );
    }

    // ─────────────────────────────────────────────────────────────────────

    public function test_calcula_sin_guardar_la_fila(): void
    {
        $worksheet = $this->makeWorksheet();

        $rowsAntes   = DB::table('worksheet_rows')->count();
        $valoresAntes = DB::table('worksheet_values')->count();

        $response = $this->preview($worksheet, [
            'factor_koh'      => ['1' => '0.5531'],
            'volumen_blanco'  => ['1' => '0.10'],
            'peso_aceite'     => ['1' => '20'],
            'volumen_gastado' => ['1' => '1.20'],
        ])->assertOk();

        // (1.20 − 0.10) × 0.5531 / 20 = 0.0304205 → 0.030 con 3 decimales.
        $this->assertEqualsWithDelta(0.030, $response->json('values.resultado.1'), 1e-9);

        // La promesa central: es cálculo, no un guardado encubierto.
        $this->assertSame($rowsAntes, DB::table('worksheet_rows')->count());
        $this->assertSame($valoresAntes, DB::table('worksheet_values')->count());
    }

    public function test_la_vista_previa_da_lo_mismo_que_el_guardado(): void
    {
        // Si estas dos salidas difieren, el analista ve un número mientras
        // escribe y otro cuando guarda, y deja de creerle a la pantalla. Los
        // datos van a propósito con las tres traducciones que hace el guardado:
        // coma decimal, signo de censura y ceros no significativos.
        $worksheet = $this->makeWorksheet();

        $values = [
            'nro_muestra'     => ['1' => '2026-0744'],
            'factor_koh'      => ['1' => '0,5531'],
            'volumen_blanco'  => ['1' => '0.10'],
            'peso_aceite'     => ['1' => '20.000'],
            'volumen_gastado' => ['1' => '>1.20'],
        ];

        $vistaPrevia = $this->preview($worksheet, $values)->assertOk()->json('values.resultado.1');

        $row = (new WorksheetService())->saveRow(
            $worksheet,
            ['kind' => WorksheetRow::KIND_SAMPLE],
            $values,
        );

        $guardado = $row->valueFor($this->definition->fields->firstWhere('code', 'resultado'));

        $this->assertNotNull($vistaPrevia);
        $this->assertEqualsWithDelta((float) $guardado->value_num, (float) $vistaPrevia, 1e-9);
    }

    public function test_la_vista_previa_coincide_con_el_guardado_replica_por_replica(): void
    {
        $definition = $this->makeAcidNumberTest(['code' => 'acid_rep']);
        $definition->fields()->whereIn('code', [
            'volumen_gastado', 'resultado',
        ])->update(['replicates' => 3]);

        $worksheet = $this->makeWorksheet($definition->fresh());

        // Cada réplica se resuelve con SU medición: mezclarlas daría el mismo
        // número tres veces.
        $values = [
            'factor_koh'      => ['1' => '0.5531'],
            'volumen_blanco'  => ['1' => '0.10'],
            'peso_aceite'     => ['1' => '20'],
            'volumen_gastado' => ['1' => '1.20', '2' => '1.35', '3' => '1.48'],
        ];

        $vistaPrevia = $this->preview($worksheet, $values)->assertOk()->json('values.resultado');

        $row = (new WorksheetService())->saveRow(
            $worksheet,
            ['kind' => WorksheetRow::KIND_SAMPLE],
            $values,
        );

        $field = $definition->fields()->where('code', 'resultado')->first();

        for ($replicate = 1; $replicate <= 3; $replicate++) {
            $guardado = $row->valueFor($field, $replicate);

            $this->assertNotNull($guardado, "Falta la réplica {$replicate} guardada.");
            $this->assertEqualsWithDelta(
                (float) $guardado->value_num,
                (float) $vistaPrevia[$replicate],
                1e-9,
                "La réplica {$replicate} no coincide con lo guardado.",
            );
        }

        // Y no son todas iguales: si lo fueran, la coincidencia de arriba no
        // probaría nada sobre la resolución por réplica.
        $this->assertNotEquals($vistaPrevia[1], $vistaPrevia[2]);
    }

    public function test_una_opcion_de_seleccion_se_lee_por_su_texto_y_no_por_su_id(): void
    {
        // El guardado guarda la opción por clave foránea y la lee por su TEXTO.
        // Si la vista previa usara el id, la fórmula operaría sobre un número
        // que no existe en la hoja.
        $definition = $this->makeAcidNumberTest(['code' => 'acid_sel']);
        $definition->fields()->where('code', 'factor_koh')->update(['type' => 'select']);

        $field = $definition->fields()->where('code', 'factor_koh')->first();
        $option = TestFieldOption::create([
            'test_field_id' => $field->id, 'value' => '0.5531', 'sort_order' => 1,
        ]);

        $worksheet = $this->makeWorksheet($definition->fresh());

        $values = [
            'factor_koh'      => ['1' => $option->id],
            'volumen_blanco'  => ['1' => '0.10'],
            'peso_aceite'     => ['1' => '20'],
            'volumen_gastado' => ['1' => '1.20'],
        ];

        $vistaPrevia = $this->preview($worksheet, $values)->assertOk()->json('values.resultado.1');

        $row = (new WorksheetService())->saveRow(
            $worksheet,
            ['kind' => WorksheetRow::KIND_SAMPLE],
            $values,
        );

        $guardado = $row->valueFor($definition->fields()->where('code', 'resultado')->first());

        $this->assertEqualsWithDelta((float) $guardado->value_num, (float) $vistaPrevia, 1e-9);
        $this->assertEqualsWithDelta(0.030, (float) $vistaPrevia, 1e-9);
    }

    public function test_un_dato_que_falta_deja_el_calculo_vacio_y_no_el_texto_nan(): void
    {
        // El sistema viejo dejaba guardado el texto "NaN" cuando la fórmula
        // operaba sobre un campo vacío, y después tenía un panel para salir a
        // cazar esas celdas.
        $worksheet = $this->makeWorksheet();

        $response = $this->preview($worksheet, [
            'factor_koh'  => ['1' => '0.5531'],
            'peso_aceite' => ['1' => '20'],
        ])->assertOk();

        $this->assertNull($response->json('values.resultado.1'));
        $this->assertSame([], $response->json('errors'));
    }

    public function test_una_hoja_dada_de_baja_no_calcula(): void
    {
        $worksheet = $this->makeWorksheet(null, Worksheet::STATUS_VOIDED);

        $this->preview($worksheet, ['peso_aceite' => ['1' => '20']])
            ->assertStatus(422)
            ->assertJsonPath('errors.worksheet.0', __('worksheets.errors.not_draft'));
    }

    public function test_una_hoja_bloqueada_por_el_supervisor_no_calcula(): void
    {
        $worksheet = $this->makeWorksheet();
        $worksheet->forceFill(['locked_at' => now()])->save();

        $this->preview($worksheet, ['peso_aceite' => ['1' => '20']])
            ->assertStatus(422)
            ->assertJsonPath('errors.worksheet.0', __('worksheets.errors.locked'));
    }

    public function test_un_ciclo_entre_formulas_se_informa_y_no_devuelve_numero(): void
    {
        $definition = $this->makeAcidNumberTest(['code' => 'acid_ciclo']);

        // El editor de plantillas impide crear esto, pero una plantilla migrada
        // del sistema viejo puede traerlo: la hoja tiene que decirlo, no quedar
        // muda.
        $definition->fields()->where('code', 'resultado')->update(['formula' => 'auxiliar + 1']);
        TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $definition->id,
            'code' => 'auxiliar', 'label' => 'Auxiliar', 'type' => 'computed',
            'sort_order' => 7, 'formula' => 'resultado * 2',
        ]);

        $worksheet = $this->makeWorksheet($definition->fresh());

        $response = $this->preview($worksheet, ['peso_aceite' => ['1' => '20']])->assertOk();

        $this->assertNotEmpty($response->json('cycles'));
        $this->assertNull($response->json('values.resultado.1'));
        $this->assertContains('resultado', $response->json('unresolved'));
    }

    public function test_sin_permiso_de_edicion_no_se_calcula(): void
    {
        // El menú no es una autorización: la vista previa lee la plantilla de la
        // prueba y solo la usa quien puede cargar valores.
        $worksheet = $this->makeWorksheet();

        $this->preview($worksheet, ['peso_aceite' => ['1' => '20']], $this->userWith(['worksheets.view']))
            ->assertStatus(403);
    }

    public function test_un_cuerpo_desmedido_se_rechaza(): void
    {
        $worksheet = $this->makeWorksheet();

        $this->preview($worksheet, ['peso_aceite' => ['1' => str_repeat('9', 70 * 1024)]])
            ->assertStatus(422)
            ->assertJsonPath('errors.values.0', __('worksheets.errors.preview_too_large'));
    }
}
