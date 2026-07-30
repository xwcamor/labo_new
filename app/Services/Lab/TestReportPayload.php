<?php

namespace App\Services\Lab;

use App\Models\Result;
use App\Models\Sample;
use App\Models\SampleTest;
use App\Models\Worksheet;
use App\Models\SampleDiagnosis;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;

/**
 * Lo que dice el informe de ensayo de UNA muestra.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL LÍMITE NO SE VUELVE A CALCULAR AL IMPRIMIR                            │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Es la diferencia que más importa respecto del informe anterior. Allá el
 * límite era un TEXTO guardado en la fila del informe ("16 (máximo)") y la
 * vista lo convertía a número en el momento de imprimir:
 *
 *     @orientation = (rem_report_detail.aci_ori.strip.delete! "(máximo)").to_f
 *     if rem_report_detail.aci_val.to_f <= @orientation   # verde
 *
 * `String#delete!` devuelve **nil** cuando no encontró nada que borrar. En el
 * cuadro "De voltaje · Mineral" el límite del acetileno está cargado como "16",
 * sin la palabra "(máximo)": ahí `delete!` devuelve nil, `nil.to_f` es 0.0, y el
 * informe imprime 16 como valor de orientación mientras colorea el resultado
 * contra 0 ppm. O sea: el número que el cliente lee y el criterio con el que se
 * lo juzga son distintos, y nada avisa.
 *
 * Acá el veredicto ya viene decidido y CONGELADO en el resultado
 * (`spec_status`, `spec_min`, `spec_max`, `spec_source`), escrito cuando se
 * validó la hoja. El informe no compara nada: lee. El texto del límite se ARMA
 * a partir de los mismos números con los que se decidió, así que no pueden
 * discrepar.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ SIN CRITERIO NO ES "CUMPLE"                                              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Un `spec_status` nulo significa que para ese parámetro no había cuadro de
 * límites aplicable. Se imprime como raya, nunca como conforme, y el pie del
 * informe lo dice. Un informe que muestra en negro un valor que nadie comparó
 * contra nada es peor que uno incompleto.
 *
 * Solo entran resultados de pruebas VALIDADAS: el informe es lo que el
 * laboratorio firma.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA CABECERA SE REPITE EN CADA PÁGINA, Y ESO NO ES REDUNDANCIA            │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El informe acreditado lleva UNA PÁGINA POR ENSAYO y repite en todas el logo,
 * el número de informe, los datos del cliente y los del equipo. Una hoja suelta
 * —fotocopiada, escaneada, adjunta a un correo— tiene que poder identificarse
 * sola. Por eso este payload devuelve la cabecera UNA vez y la vista la dibuja N
 * veces, en vez de condensar todo en una página.
 *
 * Cada sección trae además sus CONDICIONES DE ENSAYO (fecha de análisis,
 * temperatura de la muestra, temperatura y humedad del laboratorio), que salen
 * de la hoja de bancada que produjo esos resultados y no de la muestra: dos
 * ensayos de la misma muestra se corren días distintos, en condiciones
 * distintas, y el papel tiene que decir las de cada uno.
 */
class TestReportPayload
{
    /**
     * @return array<string,mixed>
     */
    public function forSample(Sample $sample, ?\App\Models\SampleReport $report = null): array
    {
        $sample->loadMissing([
            'reception.customer:id,name,address',
            'reception.sampler:id,name',
            'equipment.customer:id,name',
            'equipment.equipmentType:id,name',
            'equipment.oilType:id,name',
            'equipment.location:id,name',
            'equipment.substation:id,name',
            'equipment.brand:id,name',
            'equipment.tapChangerType:id,name',
            'equipment.preservation:id,name',
            'tests.definition.group:id,name,sort_order',
        ]);

        $secciones = $this->secciones($sample, $report);

        return [
            'sample'    => $this->cabeceraMuestra($sample, $report),
            'customer'  => $this->cabeceraCliente($sample),
            'equipment' => $this->cabeceraEquipo($sample),
            'sections'  => $secciones,
            'analysis'  => $this->analisis($sample, $secciones),
            'notes'     => $this->notas($sample),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function cabeceraMuestra(Sample $sample, ?\App\Models\SampleReport $report = null): array
    {
        return [
            'code'         => $sample->code,
            'year'         => $sample->year,
            'number'       => $sample->number,
            // El número del INFORME es otro que el de la muestra: un informe se
            // reemite (corrección, ampliación) sin que la muestra cambie. Sale
            // del informe que se está imprimiendo; sin informe —vista previa
            // desde la muestra— cae al número declarado y, en última instancia,
            // al código de muestra, en vez de salir en blanco.
            'report_number' => $report?->code ?: ($sample->report_number ?: $sample->code),
            'description'  => $sample->description,
            'sampling_reason' => $sample->sampling_reason,
            'sampling_point'  => $sample->sampling_point,
            'sampled_at'   => $sample->sampled_at,
            'received_at'  => $sample->reception?->received_at,
            'service_order' => $sample->reception?->service_order,
            'reception'    => $sample->reception?->code,
            'sampler'      => $sample->reception?->sampler?->name ?? $sample->reception?->sampler_name,
            'contact_info' => $sample->reception?->contact_info,
            // El usuario final NO siempre es el cliente: una contratista manda
            // muestras del transformador de la minera, y el informe tiene que
            // decir de quién es el equipo.
            'end_user'     => $sample->reception?->end_user,

            // Condiciones de CAMPO al tomar la muestra. Se devuelven crudas
            // (float o null) y la vista decide el formato: null es null y sale
            // raya, nunca "0.00". En el sistema anterior se guardaban como
            // texto y se imprimían con `to_f.round(2)`, así que un campo vacío
            // salía "0.00" y era indistinguible de una medición real de cero.
            'oil_temp_c'        => $this->numero($sample->oil_temp_c),
            'equipment_temp_c'  => $this->numero($sample->equipment_temp_c),
            'ambient_temp_c'    => $this->numero($sample->ambient_temp_c),
            'relative_humidity' => $this->numero($sample->relative_humidity),
        ];
    }

    /**
     * Decimal de la base a float, conservando el nulo.
     *
     * `(float) null` es 0.0, y ese casteo silencioso es exactamente el bug del
     * informe anterior: la diferencia entre "no se midió" y "midió cero" se
     * perdía en la conversión.
     */
    private function numero(mixed $valor): ?float
    {
        return ($valor === null || $valor === '') ? null : (float) $valor;
    }

    /**
     * @return array<string,mixed>
     */
    private function cabeceraCliente(Sample $sample): array
    {
        // El cliente del informe es el de la RECEPCIÓN, no el del equipo: es
        // quien encargó el ensayo y a quien se le factura. Coinciden salvo que
        // alguien haya movido el equipo de empresa después de la entrega, y en
        // ese caso el informe tiene que seguir diciendo quién lo pidió.
        $customer = $sample->reception?->customer ?? $sample->equipment?->customer;

        return [
            'name'    => $customer?->name,
            'address' => $customer?->address,
            'id'      => $customer?->id,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function cabeceraEquipo(Sample $sample): array
    {
        $e = $sample->equipment;

        if (! $e) {
            return ['missing' => true];
        }

        return [
            'missing'    => false,
            'name'       => $e->name,
            'serial'     => $e->serial,
            'tag'        => $e->tag,
            'type'       => $e->equipmentType?->name,
            'oil_type'   => $e->oilType?->name,
            'location'   => $e->location?->name,
            'substation' => $e->substation?->name,
            // Las dos placas se arman en el MODELO, no acá: la ficha, el
            // informe y los exports imprimen "500 / 220 / 33" con la misma
            // regla. Los valores sueltos siguen viajando para quien necesite
            // el número y no la placa.
            'voltage'    => $e->voltage_label,
            'power'      => $e->power_label,
            'voltage_hv' => $this->numero($e->voltage_kv_hv),
            'voltage_lv' => $this->numero($e->voltage_kv_lv),
            'voltage_tv' => $this->numero($e->voltage_kv_tv),
            'power_mva'  => $this->numero($e->power_mva),
            'year'       => $e->manufacture_year,

            // Los campos que la cabecera acreditada pide y que hasta ahora no
            // viajaban. Todos salen del EQUIPO, que es de donde el informe
            // anterior los sacaba: son "datos proporcionados por el cliente" y
            // así los rotula la tabla.
            'brand'       => $e->brand?->name,              // fabricante
            'tap_changer' => $e->tapChangerType?->name,     // conmutador
            'preservation' => $e->preservation?->name,      // sistema de preservación
            // La marca COMERCIAL del aceite, distinta del TIPO (mineral,
            // silicona, vegetal). No viajaba en el payload y el blade tenía una
            // raya clavada en su celda, con un comentario que afirmaba que el
            // esquema no la guardaba: sí la guarda, y el informe clásico ya la
            // imprimía. El papel nuevo mostraba un hueco donde había un dato.
            'oil_brand'   => $e->oil_brand,
            'oil_volume'  => $this->numero($e->oil_volume),
            'oil_volume_unit' => $e->oil_volume_unit,
            // "En operación". Se guarda como clave (new | in_service |
            // out_of_service) y la vista la traduce con el mismo archivo de
            // idioma que usa la ficha del equipo: una sola redacción para los
            // dos lugares.
            'service_state' => $e->service_state,
        ];
    }

    /**
     * Una sección por prueba, con sus filas de resultado.
     *
     * @return array<int,array<string,mixed>>
     */
    private function secciones(Sample $sample, ?\App\Models\SampleReport $report = null): array
    {
        // Solo las pruebas que se validaron o ya se informaron. Una prueba
        // pendiente o en proceso no tiene resultado firmado, y publicarla como
        // sección vacía sugiere que se midió y dio cero.
        $pruebas = $sample->tests
            ->whereIn('status', [SampleTest::STATUS_VALIDATED, SampleTest::STATUS_REPORTED])
            ->sortBy(fn ($t) => [$t->definition?->group?->sort_order ?? 999, $t->definition?->sort_order ?? 999]);

        // Y, si se está imprimiendo un informe concreto, solo las que ESE
        // informe publica. Es lo que el emisor marcó en "Mostrar en el informe":
        // el mismo juego de resultados puede salir completo o recortado, y la
        // decisión es del papel, no de la muestra.
        if ($report !== null) {
            $visibles = $report->visibleTestIds();
            $pruebas  = $pruebas->whereIn('id', $visibles);
        }

        if ($pruebas->isEmpty()) {
            return [];
        }

        $resultados = Result::query()
            ->where('sample_id', $sample->id)
            ->whereIn('test_definition_id', $pruebas->pluck('test_definition_id'))
            ->with(['field:id,label,unit,decimals,detection_limit,sort_order,report_visible', 'analyte:id,code,name'])
            ->orderBy('test_definition_id')
            ->get()
            ->groupBy('test_definition_id');

        // ┌──────────────────────────────────────────────────────────────┐
        // │ LA PÁGINA ES LA FAMILIA, NO LA PRUEBA SUELTA                 │
        // └──────────────────────────────────────────────────────────────┘
        // El informe acreditado dedica UNA página a "ENSAYOS FISICO-QUIMICOS"
        // con las trece pruebas en una sola tabla, y una página a cada una de
        // las demás (cromatografía, PCB, furanos…). Por eso la columna NORMA es
        // por FILA: dentro de la página fisicoquímica cada parámetro se midió
        // con su propio método (D974 el número ácido, D1816 la rigidez).
        //
        // Sacar una página por prueba parece más ordenado y no lo es: rompe el
        // formato acreditado y convierte una hoja de trece filas en trece
        // hojas, cada una repitiendo la cabecera entera.
        //
        // Qué pruebas comparten página es un DATO (`report_comment_group`), no
        // una lista escrita acá: el laboratorio reagrupa desde el editor de la
        // prueba.
        $porFamilia = [];

        foreach ($pruebas as $prueba) {
            $codigo  = $prueba->definition?->code;
            $familia = $prueba->definition?->report_comment_group ?: $codigo;

            $porFamilia[$familia] ??= [
                'test'   => null,
                'code'   => $familia,
                'group'  => $prueba->definition?->group?->name,
                'family' => $familia,
                'rows'   => [],
                'tests'  => [],
            ];

            $porFamilia[$familia]['tests'][] = $prueba;
        }

        $secciones = [];

        foreach ($porFamilia as $familia => $bloque) {
            // El ítem se numera DENTRO de la página, no corrido entre páginas:
            // cada hoja se lee sola y su primera fila es el ítem 1.
            $item = 0;
            $filas = [];
            $codigos = [];

            foreach ($bloque['tests'] as $prueba) {
                $norma = $this->normaDelEnsayo($sample, $prueba->test_definition_id);
                $delEnsayo = $this->filas($resultados->get($prueba->test_definition_id, collect()), $item);

                // La norma del MÉTODO viaja con cada fila, que es como la
                // imprime el informe acreditado.
                foreach ($delEnsayo as $fila) {
                    $filas[] = $fila + [
                        'method'        => $norma['label'],
                        'accreditation' => $norma['flag'],
                        'accredited'    => $norma['accredited'],
                    ];
                }

                if ($delEnsayo !== []) {
                    $codigos[] = $prueba->definition?->code;
                }
            }

            if ($filas === []) {
                continue;
            }

            // El título de la página. Con una sola prueba es su nombre; con
            // varias, el nombre del GRUPO del catálogo ("Fisico Quimico", el
            // que el laboratorio edita en Grupos de pruebas). Antes salía de
            // una lista escrita a mano en el archivo de idioma
            // (`reports.family.*`): una palabra que el sistema inventó y que
            // el laboratorio no podía cambiar desde ninguna pantalla. Esa
            // lista queda solo de respaldo para pruebas sin grupo asignado.
            $titulo = count($bloque['tests']) === 1
                ? $bloque['tests'][0]->definition?->name
                : ($bloque['group'] ?: __('reports.family.' . $familia));

            $secciones[] = [
                'test'   => $titulo,
                'code'   => $familia,
                'group'  => $bloque['group'],
                'family' => $familia,
                'rows'   => $filas,
                'conditions' => $this->condiciones($sample, $bloque['tests'][0]->test_definition_id, $filas),
                'footnote'   => $this->notaAlPie($codigos[0] ?? $familia),
                // Cuántas filas DE ESTA PÁGINA salieron sin criterio. El total
                // del informe va en la última página; acá interesa el de la
                // hoja que se tiene en la mano, porque es la que se fotocopia
                // suelta.
                'no_criteria' => count(array_filter($filas, fn ($f) => $f['status'] === null)),
                // ¿Esta PÁGINA tiene algún método dentro del alcance
                // acreditado? De eso depende que lleve el sello del organismo y
                // el párrafo del certificado. Se resuelve acá y no en la
                // plantilla porque es una afirmación sobre el alcance, no una
                // decisión de maquetado.
                'accredited'     => (bool) array_filter($filas, fn ($f) => ! empty($f['accredited'])),
                'not_accredited' => (bool) array_filter(
                    $filas,
                    fn ($f) => empty($f['accredited']) && ! empty($f['accreditation'])
                ),
            ];
        }

        return $secciones;
    }

    /**
     * Las condiciones bajo las que se corrió ESTE ensayo.
     *
     * Salen de la hoja de bancada que produjo estos resultados —no de la
     * muestra— porque son propias de la corrida: dos ensayos de la misma
     * muestra se hacen días distintos y con el laboratorio en otro estado. El
     * informe anterior las tenía por familia (`fiq_lab_tem`, `cro_lab_tem`…),
     * que es la misma idea escrita quince veces.
     *
     * La "(*) Norma de referencia" es el CRITERIO de aceptación aplicado, y sale
     * congelado de los propios resultados (`spec_source`). No se vuelve a
     * resolver el cuadro al imprimir: si el laboratorio corrige un cuadro
     * mañana, el informe emitido tiene que seguir diciendo contra qué se juzgó.
     *
     * @param  array<int,array<string,mixed>> $filas
     * @return array<string,mixed>
     */
    private function condiciones(Sample $sample, ?int $testDefinitionId, array $filas): array
    {
        $hoja = $testDefinitionId
            ? Worksheet::query()
                ->join('worksheet_rows', 'worksheet_rows.worksheet_id', '=', 'worksheets.id')
                ->where('worksheet_rows.sample_id', $sample->id)
                ->where('worksheets.test_definition_id', $testDefinitionId)
                ->whereNull('worksheet_rows.deleted_at')
                ->orderByDesc('worksheets.run_date')
                ->first([
                    'worksheets.run_date',
                    'worksheets.ambient_temp_c',
                    'worksheets.ambient_humidity',
                    'worksheets.lab_pressure_hpa',
                    'worksheets.sample_temp_c',
                ])
            : null;

        $criterios = collect($filas)->pluck('criterion')->filter()->unique()->values();

        return [
            'standard'         => $criterios->isEmpty() ? null : $criterios->implode(' · '),
            'run_date'         => $hoja?->run_date,
            'sample_temp_c'    => $this->numero($hoja?->sample_temp_c),
            'ambient_temp_c'   => $this->numero($hoja?->ambient_temp_c),
            'ambient_humidity' => $this->numero($hoja?->ambient_humidity),
            // La presión del laboratorio. El informe de cromatografía la
            // imprime —un volumen de gas depende de la presión a la que se
            // midió— y hasta ahora no existía columna donde guardarla, así que
            // el papel mostraba una raya fija.
            'lab_pressure_hpa' => $this->numero($hoja?->lab_pressure_hpa),
        ];
    }

    /**
     * La nota al pie propia de una prueba — la "(1) Tipo de celda: MC2A…" del
     * informe anterior.
     *
     * Vive en el archivo de idioma (`reports.test_footnote.{código}`) y no en la
     * vista: en el sistema anterior estaba escrita dentro del parcial de
     * fisicoquímicos, así que cambiar la celda del espinterómetro —una decisión
     * del laboratorio— exigía tocar HTML. Acá el laboratorio agrega una línea
     * por prueba en `resources/lang/{es,en}/reports.php` y no hay ni migración
     * ni sembrador de por medio. La prueba que no declara nota no imprime línea.
     */
    private function notaAlPie(?string $codigo): ?string
    {
        if (! $codigo) {
            return null;
        }

        $clave = 'reports.test_footnote.' . $codigo;

        return Lang::has($clave) ? __($clave) : null;
    }

    /**
     * El "ANÁLISIS DE RESULTADOS (opiniones e interpretaciones)" — la última
     * página del informe.
     *
     * Una fila por FAMILIA de ensayo, no por prueba: en el informe acreditado
     * las trece pruebas fisicoquímicas comparten un párrafo y cromatografía
     * tiene el suyo. Qué familia le toca a cada prueba es un dato
     * (`test_definitions.report_comment_group`).
     *
     * El texto sale de `sample_diagnoses`, donde queda GUARDADO lo que se
     * generó y lo que el analista corrigió. No se compone al imprimir: es una
     * opinión firmada, y tiene que poder reimprimirse igual.
     *
     * @param  array<int,array<string,mixed>> $secciones
     * @return array<int,array<string,mixed>>
     */
    private function analisis(Sample $sample, array $secciones): array
    {
        if ($secciones === []) {
            return [];
        }

        $textos = SampleDiagnosis::query()
            ->where('sample_id', $sample->id)
            ->get()
            ->keyBy('family');

        $filas = [];

        foreach ($secciones as $seccion) {
            $familia = $seccion['family'];

            if ($familia === null) {
                continue;
            }

            if (! isset($filas[$familia])) {
                $filas[$familia] = [
                    'family' => $familia,
                    'label'  => $seccion['test'],
                    'group'  => $seccion['group'],
                    'body'   => $textos->get($familia)?->body,
                    // Si la frase la escribió una persona, el informe lo dice:
                    // ante una auditoría hay que poder distinguir la opinión
                    // que compuso el motor de la que redactó el analista.
                    'edited' => (bool) $textos->get($familia)?->is_edited,
                    'tests'  => 0,
                ];
            }

            $filas[$familia]['tests']++;
        }

        // Con una sola prueba en la familia el rótulo es el nombre de la
        // prueba; cuando el laboratorio agrupa varias bajo la misma familia, el
        // del grupo. Así la fila dice algo en los dos casos, sin una lista de
        // nombres escrita en el código.
        foreach ($filas as $clave => $fila) {
            if ($fila['tests'] > 1) {
                $filas[$clave]['label'] = $fila['group'] ?? $clave;
            }
        }

        return array_values($filas);
    }

    /**
     * Con qué NORMA se corrió el ensayo, y si ese método está acreditado.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ LA NORMA DEL MÉTODO NO ES LA NORMA DEL CRITERIO                      │
     * └──────────────────────────────────────────────────────────────────────┘
     * Son dos cosas distintas y el informe tiene que decir la primera:
     *
     *   · ASTM D974 es CÓMO se mide el número ácido.
     *   · IEEE C57.106 es CONTRA QUÉ se juzga el valor obtenido.
     *
     * La columna "Norma" del informe del laboratorio siempre fue la del método,
     * y sale de la columna "Norma" de la hoja de trabajo: la eligió el analista
     * al correr el ensayo, así que es la que de verdad se usó y no la que
     * debería haberse usado. El criterio va en el pie, una vez.
     *
     * La MARCA DE ACREDITACIÓN viaja con la opción elegida
     * (`test_field_options.accreditation_flag`): dentro de una misma prueba hay
     * métodos acreditados y no acreditados, y el cliente tiene derecho a saber
     * cuál le corrieron. Es un requisito de la ISO 17025, no un adorno.
     *
     * @return array{label: ?string, flag: ?string, accredited: bool}
     */
    private function normaDelEnsayo(Sample $sample, ?int $testDefinitionId): array
    {
        if (! $testDefinitionId) {
            return ['label' => null, 'flag' => null, 'accredited' => false];
        }

        $fila = \App\Models\WorksheetValue::query()
            ->join('worksheet_rows', 'worksheet_rows.id', '=', 'worksheet_values.worksheet_row_id')
            ->join('test_fields', 'test_fields.id', '=', 'worksheet_values.test_field_id')
            ->leftJoin('test_field_options', 'test_field_options.id', '=', 'worksheet_values.option_id')
            ->where('worksheet_rows.sample_id', $sample->id)
            ->where('test_fields.test_definition_id', $testDefinitionId)
            ->where('test_fields.role', 'standard')
            ->whereNull('worksheet_rows.deleted_at')
            ->orderByDesc('worksheet_values.id')
            ->first([
                'test_field_options.value as opcion',
                'test_field_options.accreditation_flag as flag',
                'test_field_options.is_accredited as acreditado',
                'worksheet_values.value_text as texto',
            ]);

        return [
            'label' => $fila?->opcion ?? $fila?->texto,
            'flag'  => $fila?->flag,
            // El HECHO, no el rótulo. Estuvieron en la misma columna y "NA"
            // —fuera del alcance— contaba como acreditado.
            'accredited' => (bool) ($fila?->acreditado ?? false),
        ];
    }

    /**
     * @param  Collection<int,Result> $resultados
     * @return array<int,array<string,mixed>>
     */
    private function filas(Collection $resultados, int &$item): array
    {
        $filas = [];

        $ordenados = $resultados
            // La columna decide si se publica. Es un dato de la plantilla
            // (`report_visible`), no una lista de banderas por parámetro como
            // las 13 columnas `*_display` de la tabla del informe anterior.
            ->filter(fn (Result $r) => $r->field?->report_visible ?? true)
            ->sortBy(fn (Result $r) => $r->field?->sort_order ?? 999);

        foreach ($ordenados as $resultado) {
            $filas[] = [
                'item'     => ++$item,
                'analyte'  => $resultado->analyte?->name ?? $resultado->field?->label,
                // El código del parámetro. No se imprime: lo usa quien tiene
                // que cruzar esta fila con otra cosa (la maqueta del informe
                // viejo, un envío a TrafoDex) sin depender del nombre visible.
                'code'     => $resultado->analyte?->code,
                'unit'     => $resultado->unit ?? $resultado->field?->unit,
                // De dónde salió el LÍMITE (el cuadro de aceptación aplicado).
                // No es la norma del método: ver `normaDelEnsayo`.
                'criterion' => $resultado->spec_source,
                'value'    => $this->valor($resultado),
                'limit'    => $this->limite($resultado),
                'status'   => $resultado->spec_status,
            ];
        }

        return $filas;
    }

    /**
     * El resultado, con su signo de censura.
     *
     * ">75 kV" no es 75: es "el equipo llegó a su tope sin que el aceite
     * rompiera". El sistema anterior guardaba el signo dentro del texto del
     * valor y lo perdía al convertir a número, así que el informe decía 75.
     */
    private function valor(Result $resultado): string
    {
        if ($resultado->value_num === null) {
            return (string) ($resultado->value_text ?? '—');
        }

        $campo = $resultado->field;

        // ┌──────────────────────────────────────────────────────────────┐
        // │ POR DEBAJO DEL LÍMITE DE DETECCIÓN NO SE INFORMA UN NÚMERO   │
        // └──────────────────────────────────────────────────────────────┘
        // El método no distingue 0.4 ppm de 0.7 ppm de hidrógeno: publicar el
        // número sugiere una precisión que el ensayo no tiene. El informe
        // acreditado imprimía "< 1", y eso es lo correcto.
        //
        // Solo afecta a la IMPRESIÓN. El veredicto ya se decidió al validar la
        // hoja, con el valor medido, y no se toca acá — si el límite de
        // detección cambiara lo que se compara, el papel y el criterio
        // volverían a discrepar, que es el error del sistema viejo.
        //
        // El "<" que el analista tipeó gana: si él declaró el censurado, es su
        // lectura y no la del catálogo.
        $lod = $campo?->detection_limit;
        if ($resultado->qualifier === null && $lod !== null && (float) $resultado->value_num < (float) $lod) {
            return '< ' . rtrim(rtrim(number_format((float) $lod, 6, '.', ''), '0'), '.');
        }

        $numero = $this->comoTexto((float) $resultado->value_num, $campo?->decimals);

        return match ($resultado->qualifier) {
            'gt' => '> ' . $numero,
            'lt' => '< ' . $numero,
            default => $numero,
        };
    }

    /**
     * El número tal como sale impreso.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ NOTACIÓN CIENTÍFICA CUANDO EL NÚMERO NO ES LEGIBLE DE OTRA FORMA     │
     * └──────────────────────────────────────────────────────────────────────┘
     * La resistividad volumétrica de un aceite bueno son unos 8,65 × 10¹² Ω·cm.
     * Con el formato decimal el informe imprimía `8650000000000.00`: catorce
     * cifras que nadie puede leer ni comparar con la muestra anterior, y dos
     * decimales que además fingen una precisión de centésimas de ohm sobre un
     * número del orden del billón.
     *
     * Se pasa a notación científica cuando el valor es muy grande (≥ 10⁶) o muy
     * chico (≤ 10⁻⁴, y distinto de cero), que es exactamente el rango donde el
     * decimal deja de servir. Entre esos dos topes —o sea en todos los ensayos
     * normales: acidez 0.28, agua 42, rigidez 65.8— no cambia nada.
     *
     * Los decimales del catálogo se respetan como MANTISA: `decimals = 2` sobre
     * 8.65e12 da `8.65 × 10^12`, no dos decimales del entero completo.
     */
    private function comoTexto(float $valor, ?int $decimales): string
    {
        $magnitud = abs($valor);
        $cientifica = $magnitud !== 0.0 && ($magnitud >= 1e6 || $magnitud <= 1e-4);

        if ($cientifica) {
            $exponente = (int) floor(log10($magnitud));
            $mantisa   = $valor / (10 ** $exponente);

            // La mantisa con 2 decimales por omisión: es lo que informan las
            // normas para esta clase de magnitudes. Si el catálogo pide más, se
            // le hace caso; si pide 0, se deja 1 para no imprimir "9 x 10^12"
            // cuando el dato es 8.65.
            $cifras = $decimales !== null ? max(1, min($decimales, 4)) : 2;

            return rtrim(rtrim(number_format($mantisa, $cifras, '.', ''), '0'), '.')
                . ' x 10^' . $exponente;
        }

        return $decimales !== null
            ? number_format($valor, $decimales, '.', '')
            : rtrim(rtrim(number_format($valor, 6, '.', ''), '0'), '.');
    }

    /**
     * El "valor de orientación" que lee el cliente.
     *
     * Se ARMA con los mismos números contra los que se decidió el veredicto
     * (`spec_min` / `spec_max`, congelados en el resultado), en vez de guardarse
     * como una frase suelta que después hay que volver a interpretar. Es lo que
     * hace imposible que el número impreso y el criterio aplicado difieran.
     */
    private function limite(Result $resultado): string
    {
        $min = $resultado->spec_min;
        $max = $resultado->spec_max;

        if ($min === null && $max === null) {
            return '—';
        }

        $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 6, '.', ''), '0'), '.');

        if ($min !== null && $max !== null) {
            return $n($min) . ' – ' . $n($max);
        }

        return $max !== null
            ? $n($max) . ' ' . __('reports.limit_max')
            : $n($min) . ' ' . __('reports.limit_min');
    }

    /**
     * Las advertencias que el informe tiene que llevar impresas.
     *
     * @return array<int,string>
     */
    private function notas(Sample $sample): array
    {
        $notas = [];

        // Acá había una nota contando los resultados sin criterio de aceptación.
        // Se quitó: en la tabla esos parámetros ya salen con RAYA en el límite y
        // en el veredicto, así que el papel no afirma conformidad de nada.
        // Repetirlo al pie convertía en advertencia lo que es normal —el cliente
        // pide medir parámetros que su norma no acota— y una advertencia que
        // aparece siempre deja de leerse, incluidas las dos que sí importan y
        // que siguen abajo: ensayos pendientes y muestra sin equipo.

        $pendientes = $sample->tests
            ->whereIn('status', [SampleTest::STATUS_PENDING, SampleTest::STATUS_IN_PROGRESS])
            ->count();

        if ($pendientes > 0) {
            // Que falten ensayos no impide emitir —el cliente suele pedir el
            // parcial—, pero tiene que estar dicho en el papel.
            $notas[] = __('reports.note_pending', ['count' => $pendientes]);
        }

        if (! $sample->equipment_id) {
            $notas[] = __('reports.note_no_equipment');
        }

        return $notas;
    }
}
