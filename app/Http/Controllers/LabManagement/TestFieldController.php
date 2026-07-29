<?php

namespace App\Http\Controllers\LabManagement;

use App\Http\Controllers\Controller;
use App\Models\Analyte;
use App\Models\TestDefinition;
use App\Models\TestField;
use App\Models\TestFieldOption;
use App\Services\Lab\FormulaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Las columnas de una prueba: el submenú "Columnas de los Módulos" del sistema
 * viejo, ahora DENTRO de la prueba y no como pantalla aparte.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ADENTRO Y NO COMO MENÚ SUELTO                                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema Rails había DOS pantallas que editaban la misma cosa: el
 * editor anidado dentro de la prueba y un CRUD suelto de columnas. Las dos se
 * desincronizaron: la de alta no mostraba el orden ni la bandera de
 * acreditación de las opciones, la de edición sí; y los encabezados de la tabla
 * de alta estaban cruzados respecto de las celdas (el botón de eliminar caía
 * bajo el rótulo "Mostrar en Reporte"). Una columna no significa nada fuera de
 * su prueba: su fórmula referencia a las otras columnas de esa misma prueba.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA FÓRMULA SE VALIDA AL GUARDAR LA PLANTILLA                             │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema viejo la fórmula era un bloque de JavaScript escrito en un
 * campo de texto de la configuración, guardado en la base e inyectado sin
 * escapar en la página de todos los analistas. Nadie la revisaba: un error de
 * tipeo se descubría cuando el ensayo salía en blanco, o con el texto "NaN"
 * guardado en la base.
 *
 * Acá la fórmula se analiza antes de aceptarla: se verifica que sea una
 * expresión válida, que solo referencie columnas que existan en ESTA prueba, y
 * que no haya un ciclo entre campos calculados.
 */
class TestFieldController extends Controller
{
    public function __construct(private readonly FormulaValidator $validator)
    {
    }

    /**
     * Pantalla del editor de columnas de una prueba.
     *
     * Vive en su propia ruta además de estar embebida en la ficha, porque la
     * plantilla de una prueba como cromatografía tiene decenas de columnas y
     * merece la pantalla completa.
     */
    public function index(TestDefinition $test_definition)
    {
        return Inertia::render('TestDefinitions/Fields', [
            'definition' => $test_definition->load('group:id,name'),
            'fields'     => $test_definition->fields()->with('options')->get(),
            'fieldTypes' => config('lab_field_types'),
            'roles'      => TestField::ROLES,
            'analytes'   => Analyte::where('is_active', true)
                ->orderBy('name')->get(['id', 'code', 'name', 'unit']),
        ]);
    }

    public function store(Request $request, TestDefinition $test_definition): RedirectResponse
    {
        $data = $this->validated($request, $test_definition);

        $field = new TestField($data);
        $field->test_definition_id = $test_definition->id;
        $field->slug = Str::random(22);
        $field->sort_order = $data['sort_order']
            ?? ((int) $test_definition->fields()->max('sort_order') + 1);
        $field->save();

        $this->syncOptions($field, $request->input('options', []));

        return back()->with('success', __('test_fields.created'));
    }

    public function update(Request $request, TestDefinition $test_definition, TestField $field): RedirectResponse
    {
        abort_unless($field->test_definition_id === $test_definition->id, 404);

        $field->update($this->validated($request, $test_definition, $field));
        $this->syncOptions($field, $request->input('options', []));

        return back()->with('success', __('test_fields.updated'));
    }

    /**
     * Baja de una columna.
     *
     * Se rechaza si alguna fórmula de la prueba la referencia. Es la corrección
     * del procedimiento del sistema viejo, que para agregar o quitar columnas
     * pedía pegar código temporal en una vista, desplegar, y después comentarlo
     * y volver a desplegar. Ahí una columna borrada dejaba fórmulas apuntando a
     * la nada y el ensayo salía vacío sin ningún aviso.
     */
    public function destroy(TestDefinition $test_definition, TestField $field): RedirectResponse
    {
        abort_unless($field->test_definition_id === $test_definition->id, 404);

        $usedBy = $test_definition->fields()
            ->whereNotNull('formula')
            ->where('id', '!=', $field->id)
            ->get()
            ->filter(fn (TestField $other) => in_array(
                $field->code,
                $this->validator->validate($other->formula)['uses'] ?? [],
                true
            ));

        if ($usedBy->isNotEmpty()) {
            return back()->withErrors([
                'field' => __('test_fields.errors.used_by_formula', [
                    'fields' => $usedBy->pluck('label')->implode(', '),
                ]),
            ]);
        }

        $field->delete();

        return back()->with('success', __('test_fields.deleted'));
    }

    /**
     * Reordenar.
     *
     * En el sistema viejo reordenar era la operación peligrosa del sistema: las
     * fórmulas referenciaban las columnas por su posición en la pantalla
     * (`col1`, `col8`), el código de la muestra se copiaba de `col1`, la norma
     * se leía de la columna 2 y el resultado se tomaba de la última. Su README
     * avisaba en mayúsculas que la columna resultado tenía que ser siempre la
     * última.
     *
     * Acá el orden es solo presentación: las fórmulas usan códigos y los roles
     * están declarados. Reordenar no puede romper nada.
     */
    public function reorder(Request $request, TestDefinition $test_definition): RedirectResponse
    {
        $data = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $ids = $test_definition->fields()->pluck('id')->all();

        DB::transaction(function () use ($data, $ids, $test_definition) {
            foreach ($data['order'] as $position => $id) {
                if (! in_array((int) $id, $ids, true)) {
                    continue;   // no se reordenan columnas de otra prueba
                }
                TestField::where('id', $id)
                    ->where('test_definition_id', $test_definition->id)
                    ->update(['sort_order' => $position + 1]);
            }
        });

        return back()->with('success', __('test_fields.reordered'));
    }

    /**
     * "Valores Constantes" del sistema viejo: los campos cuyo valor se arrastra
     * de una muestra a la siguiente (el factor de KOH, la temperatura ambiente)
     * con su valor actual.
     *
     * Era una pantalla separada, con su propio permiso, que editaba la columna
     * `reuse_value` de las columnas marcadas como constantes. Se conserva como
     * pantalla porque el supervisor la usa a diario —cambia el factor cuando
     * titula una solución nueva— y no tiene por qué entrar al editor de la
     * plantilla completa para eso.
     */
    public function constants(TestDefinition $test_definition)
    {
        return Inertia::render('TestDefinitions/Constants', [
            'definition' => $test_definition->load('group:id,name'),
            'fields'     => $test_definition->fields()
                ->where('is_reusable', true)
                ->get(['id', 'slug', 'code', 'label', 'unit', 'type', 'default_value', 'sort_order']),
        ]);
    }

    public function updateConstants(Request $request, TestDefinition $test_definition): RedirectResponse
    {
        $data = $request->validate([
            'values'   => ['required', 'array'],
            'values.*' => ['nullable', 'string', 'max:255'],
        ]);

        $reusable = $test_definition->fields()->where('is_reusable', true)->get()->keyBy('id');

        DB::transaction(function () use ($data, $reusable) {
            foreach ($data['values'] as $id => $value) {
                $field = $reusable->get((int) $id);

                // Solo se aceptan los campos que la prueba declara constantes.
                // El sistema viejo permitía asignación masiva sin filtro en
                // todos sus controladores de este módulo.
                if ($field === null) {
                    continue;
                }

                $field->update(['default_value' => $value]);
            }
        });

        return back()->with('success', __('test_fields.constants_updated'));
    }

    /**
     * Comprueba una fórmula sin guardarla, para el editor.
     *
     * Devuelve los errores, las columnas que referencia y los ciclos. Que el
     * supervisor vea el problema mientras escribe es la diferencia entre
     * corregir un tipeo y descubrirlo tres semanas después en un certificado.
     */
    public function checkFormula(Request $request, TestDefinition $test_definition): JsonResponse
    {
        $data = $request->validate([
            'formula' => ['required', 'string', 'max:1000'],
            'code'    => ['nullable', 'string', 'max:60'],
        ]);

        $codes = $test_definition->fields()->pluck('code')->all();
        $result = $this->validator->validate($data['formula'], $codes);

        // El ciclo se busca sobre el conjunto completo, con la fórmula nueva
        // puesta en su lugar: una fórmula puede ser válida por sí misma y
        // cerrar un círculo con otra.
        $formulas = $test_definition->fields()
            ->whereNotNull('formula')
            ->pluck('formula', 'code')
            ->all();

        if (! empty($data['code'])) {
            $formulas[$data['code']] = $data['formula'];
        }

        $result['cycles'] = $this->validator->detectCycles($formulas);

        return response()->json($result);
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request, TestDefinition $definition, ?TestField $field = null): array
    {
        $types = array_keys(config('lab_field_types', []));

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:60',
                // El código es el identificador que usan las fórmulas: tiene
                // que ser un nombre, no cualquier texto.
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('test_fields', 'code')
                    ->where('test_definition_id', $definition->id)
                    ->ignore($field?->id)
                    ->whereNull('deleted_at'),
            ],
            'label'             => ['required', 'string', 'max:200'],
            'type'              => ['required', Rule::in($types)],
            'role'              => ['required', Rule::in(TestField::ROLES)],
            'unit'              => ['nullable', 'string', 'max:30'],
            'decimals'          => ['nullable', 'integer', 'min:0', 'max:10'],
            'min_value'         => ['nullable', 'numeric'],
            // El `gte` solo se aplica si hay mínimo cargado. Sin esa condición,
            // cargar únicamente el máximo compara contra nulo y Laravel rechaza
            // el campo, con un mensaje que apunta al máximo cuando el problema
            // está en el campo de al lado.
            'max_value'         => array_filter([
                'nullable', 'numeric',
                $request->filled('min_value') ? 'gte:min_value' : null,
            ]),
            'is_required'       => ['boolean'],
            'is_locked'         => ['boolean'],
            'is_reusable'       => ['boolean'],
            'default_value'     => ['nullable', 'string', 'max:255'],
            'report_visible'    => ['boolean'],
            'replicates'        => ['nullable', 'integer', 'min:1', 'max:20'],
            'formula'           => ['nullable', 'string', 'max:1000'],
            'output_analyte_id' => ['nullable', 'integer', Rule::exists('analytes', 'id')],
            'sort_order'        => ['nullable', 'integer', 'min:0'],
        ]);

        if (filled($data['formula'] ?? null)) {
            $this->assertFormulaIsSound($definition, $data, $field);
        }

        return $data;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function assertFormulaIsSound(TestDefinition $definition, array $data, ?TestField $field): void
    {
        $codes = $definition->fields()->pluck('code')->all();

        // El campo que se está creando todavía no está en la base, pero su
        // fórmula puede referenciarse a sí misma por error: se agrega a la
        // lista para que el chequeo de ciclos lo detecte.
        $codes[] = $data['code'];

        $check = $this->validator->validate($data['formula'], array_unique($codes));

        if (! $check['ok']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'formula' => $check['errors'],
            ]);
        }

        $formulas = $definition->fields()
            ->whereNotNull('formula')
            ->when($field !== null, fn ($q) => $q->where('id', '!=', $field->id))
            ->pluck('formula', 'code')
            ->all();

        $formulas[$data['code']] = $data['formula'];

        $cycles = $this->validator->detectCycles($formulas);

        if ($cycles !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'formula' => __('test_fields.errors.formula_cycle', [
                    'path' => implode(' → ', $cycles[0]),
                ]),
            ]);
        }
    }

    /**
     * Sincroniza las opciones de una columna de selección.
     *
     * Las opciones NO se borran: se ocultan (`is_hidden`). Un ensayo cerrado
     * apunta a la opción que se eligió ese día, y borrarla dejaría el registro
     * histórico apuntando a la nada. El sistema viejo lo permitía, y por eso
     * tenía un parche que reinyectaba la opción seleccionada aunque estuviera
     * oculta o borrada, para no corromper el registro al editarlo. Ese parche
     * era funcionalidad necesaria, no un adorno.
     *
     * @param array<int,array<string,mixed>> $options
     */
    private function syncOptions(TestField $field, array $options): void
    {
        if (! (config("lab_field_types.{$field->type}.options") ?? false)) {
            return;
        }

        $keep = [];

        foreach (array_values($options) as $position => $option) {
            $value = is_array($option) ? ($option['value'] ?? null) : $option;

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $row = TestFieldOption::updateOrCreate(
                ['test_field_id' => $field->id, 'value' => trim($value)],
                [
                    'sort_order'         => $position + 1,
                    'is_hidden'          => (bool) (is_array($option) ? ($option['is_hidden'] ?? false) : false),
                    // El HECHO y el RÓTULO, por separado: la casilla decide si
                    // el informe estampa el sello del organismo acreditador; el
                    // texto es apenas el superíndice que se imprime junto a la
                    // norma. Mientras fueron una sola columna, "NA" —fuera del
                    // alcance— contaba como acreditado.
                    'is_accredited'      => (bool) (is_array($option) ? ($option['is_accredited'] ?? false) : false),
                    'accreditation_flag' => is_array($option) ? ($option['accreditation_flag'] ?? null) : null,
                ],
            );

            $keep[] = $row->id;
        }

        // Lo que el editor sacó de la lista se OCULTA, no se borra.
        TestFieldOption::where('test_field_id', $field->id)
            ->whereNotIn('id', $keep)
            ->update(['is_hidden' => true]);
    }
}
