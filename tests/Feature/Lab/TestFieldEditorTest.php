<?php

namespace Tests\Feature\Lab;

use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\TestFieldOption;
use App\Models\TestGroup;
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
 * El editor de columnas de una prueba.
 *
 * Lo que se cuida acá es que la plantilla no pueda quedar en un estado que
 * rompa los ensayos: fórmulas que referencian columnas inexistentes, ciclos
 * entre campos calculados, y columnas borradas que alguna fórmula todavía usa.
 *
 * En el sistema Rails viejo nada de esto se verificaba. La fórmula era un bloque
 * de JavaScript escrito en un campo de texto de la configuración; el
 * procedimiento documentado para agregar una columna consistía en pegar código
 * temporal en una vista, desplegar, y después comentarlo y volver a desplegar.
 */
class TestFieldEditorTest extends TestCase
{
    use RefreshDatabase;

    private TestDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([LaravelLocalizationRedirectFilter::class, LocaleSessionRedirect::class]);

        DB::table('languages')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Spanish', 'iso_code' => 'es', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('locales')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'code' => 'es_PE', 'name' => 'Español', 'language_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('regions')->insertOrIgnore([['id' => 999, 'slug' => Str::random(22), 'name' => '__bs__', 'is_active' => false, 'deleted_at' => now(), 'deleted_description' => 'bs', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('countries')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'region_id' => 999, 'name' => 'Peru', 'iso_code' => 'PE', 'currency' => 'PEN', 'timezone' => 'America/Lima', 'default_locale_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('tenants')->insertOrIgnore([['id' => 1, 'slug' => Str::random(22), 'name' => 'Laboratorio', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['test_definitions.view', 'test_definitions.edit'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(
            ['name' => 'Supervisor de laboratorio', 'guard_name' => 'web'],
            ['description' => 'Prueba']
        );
        $role->syncPermissions(Permission::all());

        $user = User::factory()->create(['tenant_id' => 1, 'country_id' => 1, 'locale_id' => 1]);
        $user->assignRole($role);
        $this->actingAs($user);

        $group = TestGroup::create(['slug' => Str::random(22), 'code' => 'fiqui', 'name' => 'Físico Químico']);
        $this->definition = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'acid', 'name' => 'Número Ácido',
            'test_group_id' => $group->id,
        ]);

        foreach ([
            ['code' => 'volumen_gastado', 'label' => 'Vol. gastado', 'sort_order' => 1],
            ['code' => 'peso_aceite',     'label' => 'Peso aceite',  'sort_order' => 2],
        ] as $column) {
            TestField::create(array_merge([
                'slug' => Str::random(22),
                'test_definition_id' => $this->definition->id,
                'type' => 'number',
                'role' => TestField::ROLE_NONE,
            ], $column));
        }
    }

    private function storeField(array $overrides = [])
    {
        return $this->post(
            route('lab_management.test_definitions.fields.store', $this->definition),
            array_merge([
                'code'  => 'resultado',
                'label' => 'Resultado',
                'type'  => 'computed',
                'role'  => TestField::ROLE_RESULT,
            ], $overrides)
        );
    }

    // ─────────────────────────────────────────────────────────────────────

    public function test_se_acepta_una_formula_valida(): void
    {
        $this->storeField(['formula' => 'volumen_gastado / peso_aceite'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('test_fields', [
            'code'    => 'resultado',
            'formula' => 'volumen_gastado / peso_aceite',
        ]);
    }

    public function test_se_rechaza_una_formula_que_usa_una_columna_inexistente(): void
    {
        $this->storeField(['formula' => 'volumen_gastado / densidad'])
            ->assertSessionHasErrors('formula');

        $this->assertDatabaseMissing('test_fields', ['code' => 'resultado']);
    }

    public function test_se_rechaza_una_formula_mal_escrita(): void
    {
        $this->storeField(['formula' => 'volumen_gastado / '])
            ->assertSessionHasErrors('formula');
    }

    public function test_se_rechaza_una_formula_que_se_referencia_a_si_misma(): void
    {
        $this->storeField(['formula' => 'resultado + 1'])
            ->assertSessionHasErrors('formula');
    }

    public function test_se_rechaza_un_ciclo_entre_dos_campos_calculados(): void
    {
        // Primero un campo `a` que depende de uno que todavía no existe se
        // rechaza, así que se crean en orden y el ciclo se cierra al segundo.
        $this->storeField(['code' => 'a', 'label' => 'A', 'formula' => 'peso_aceite * 2'])
            ->assertSessionHasNoErrors();

        $this->storeField(['code' => 'b', 'label' => 'B', 'formula' => 'a + 1'])
            ->assertSessionHasNoErrors();

        // Ahora se intenta que `a` dependa de `b`: a → b → a.
        $a = TestField::where('code', 'a')->firstOrFail();

        $this->put(
            route('lab_management.test_definitions.fields.update', [$this->definition, $a]),
            ['code' => 'a', 'label' => 'A', 'type' => 'computed', 'role' => TestField::ROLE_NONE, 'formula' => 'b + 1']
        )->assertSessionHasErrors('formula');
    }

    public function test_el_codigo_tiene_que_ser_un_nombre_usable_por_una_formula(): void
    {
        $this->storeField(['code' => 'Resultado Final (%)'])
            ->assertSessionHasErrors('code');
    }

    public function test_el_codigo_no_se_repite_dentro_de_la_misma_prueba(): void
    {
        $this->storeField(['code' => 'peso_aceite', 'type' => 'number'])
            ->assertSessionHasErrors('code');
    }

    public function test_no_se_borra_una_columna_que_alguna_formula_usa(): void
    {
        $this->storeField(['formula' => 'volumen_gastado / peso_aceite']);

        $peso = TestField::where('code', 'peso_aceite')->firstOrFail();

        $this->delete(route('lab_management.test_definitions.fields.destroy', [$this->definition, $peso]))
            ->assertSessionHasErrors('field');

        $this->assertDatabaseHas('test_fields', ['id' => $peso->id, 'deleted_at' => null]);
    }

    public function test_se_borra_una_columna_que_nadie_usa(): void
    {
        $field = TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->definition->id,
            'code' => 'observacion', 'label' => 'Observación', 'type' => 'text',
            'role' => TestField::ROLE_OBSERVATION, 'sort_order' => 9,
        ]);

        $this->delete(route('lab_management.test_definitions.fields.destroy', [$this->definition, $field]))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('test_fields', ['id' => $field->id]);
    }

    public function test_reordenar_no_cambia_ninguna_formula(): void
    {
        // Es el punto de todo el rediseño: en el sistema viejo las fórmulas
        // referenciaban las columnas por posición y reordenar las rompía.
        $this->storeField(['formula' => 'volumen_gastado / peso_aceite']);

        $ids = $this->definition->fields()->orderBy('sort_order')->pluck('id')->all();

        $this->post(
            route('lab_management.test_definitions.fields.reorder', $this->definition),
            ['order' => array_reverse($ids)]
        )->assertSessionHasNoErrors();

        $this->assertDatabaseHas('test_fields', [
            'code'    => 'resultado',
            'formula' => 'volumen_gastado / peso_aceite',
        ]);

        $this->assertSame(
            array_reverse($ids),
            $this->definition->fields()->orderBy('sort_order')->pluck('id')->all()
        );
    }

    public function test_reordenar_no_toca_columnas_de_otra_prueba(): void
    {
        $otra = TestDefinition::create([
            'slug' => Str::random(22), 'code' => 'otra', 'name' => 'Otra prueba',
        ]);
        $ajena = TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $otra->id,
            'code' => 'x', 'label' => 'X', 'type' => 'number', 'sort_order' => 7,
        ]);

        $this->post(
            route('lab_management.test_definitions.fields.reorder', $this->definition),
            ['order' => [$ajena->id]]
        )->assertSessionHasNoErrors();

        $this->assertSame(7, $ajena->fresh()->sort_order);
    }

    public function test_las_opciones_se_ocultan_en_vez_de_borrarse(): void
    {
        // Un ensayo cerrado apunta a la opción que se eligió ese día. Borrarla
        // dejaría el registro histórico apuntando a la nada; el sistema viejo lo
        // permitía y por eso tenía un parche que reinyectaba la opción.
        $this->storeField([
            'code' => 'norma', 'label' => 'Norma', 'type' => 'select',
            'role' => TestField::ROLE_STANDARD,
            'options' => [
                ['value' => 'ASTM D974'],
                ['value' => 'ASTM D1534'],
            ],
        ])->assertSessionHasNoErrors();

        $field = TestField::where('code', 'norma')->firstOrFail();
        $this->assertSame(2, $field->options()->count());

        // Se guarda de nuevo con una sola opción.
        $this->put(
            route('lab_management.test_definitions.fields.update', [$this->definition, $field]),
            [
                'code' => 'norma', 'label' => 'Norma', 'type' => 'select',
                'role' => TestField::ROLE_STANDARD,
                'options' => [['value' => 'ASTM D974']],
            ]
        )->assertSessionHasNoErrors();

        $this->assertSame(2, $field->options()->count(), 'la opción no se borra');
        $this->assertTrue(
            TestFieldOption::where('test_field_id', $field->id)
                ->where('value', 'ASTM D1534')->first()->is_hidden
        );
    }

    public function test_los_valores_constantes_solo_aceptan_columnas_declaradas_constantes(): void
    {
        $constante = TestField::create([
            'slug' => Str::random(22), 'test_definition_id' => $this->definition->id,
            'code' => 'factor_koh', 'label' => 'Factor KOH', 'type' => 'number',
            'is_reusable' => true, 'default_value' => '0.5531', 'sort_order' => 8,
        ]);
        $comun = TestField::where('code', 'peso_aceite')->firstOrFail();

        $this->post(
            route('lab_management.test_definitions.constants.update', $this->definition),
            ['values' => [$constante->id => '0.4987', $comun->id => 'no debería entrar']]
        )->assertSessionHasNoErrors();

        $this->assertSame('0.4987', $constante->fresh()->default_value);
        $this->assertNull($comun->fresh()->default_value);
    }

    public function test_la_comprobacion_de_formula_informa_sin_guardar(): void
    {
        $this->postJson(
            route('lab_management.test_definitions.fields.check_formula', $this->definition),
            ['formula' => 'volumen_gastado / peso_aceite', 'code' => 'resultado']
        )
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonPath('cycles', []);

        $this->assertDatabaseMissing('test_fields', ['code' => 'resultado']);
    }

    public function test_la_comprobacion_de_formula_delata_la_columna_inexistente(): void
    {
        $response = $this->postJson(
            route('lab_management.test_definitions.fields.check_formula', $this->definition),
            ['formula' => 'volumen_gastado / densidad']
        )->assertOk();

        $this->assertFalse($response->json('ok'));
        $this->assertNotEmpty($response->json('errors'));
    }
}
