<?php

namespace App\Services\Lab;

use App\Models\Result;
use App\Models\Sample;
use App\Models\SampleDiagnosis;
use App\Models\SampleTest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * El texto del "ANÁLISIS DE RESULTADOS (opiniones e interpretaciones)".
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LO QUE EL SISTEMA ANTERIOR HACÍA BIEN, Y HAY QUE CONSERVAR               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El flujo era el correcto y se respeta tal cual: un botón pre-carga el texto,
 * el analista lo corrige, se guarda, y se puede volver a diagnosticar. Ese
 * ciclo es lo que hace que un informe salga rápido sin que el motor tenga la
 * última palabra sobre una opinión que firma una persona.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LO QUE HABÍA QUE CAMBIAR                                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Las frases vivían en 1134 líneas de ERB, con condicionales anidados por tipo
 * de aceite × tipo de equipo × cuántos parámetros fallaron, y con los nombres
 * de los parámetros escritos a mano dentro de cada rama. Consecuencias reales:
 *
 *   · Cambiar una frase exigía un programador, y el texto es responsabilidad
 *     del laboratorio, no del programador.
 *   · Los nombres de los parámetros estaban repetidos en cada rama: agregar un
 *     ensayo obligaba a tocar todas.
 *   · La lista de "los que están bien" se armaba concatenando `if`s, así que
 *     terminaba en coma colgando ("agua, están dentro de…") — se ve en el
 *     propio ERB.
 *
 * Acá las frases son PLANTILLAS EN DATOS y las listas se arman con los nombres
 * reales de los parámetros que se midieron EN ESA MUESTRA. Nunca se nombra un
 * ensayo que no se corrió, y la coma final no depende de la suerte.
 *
 * Si ninguna plantilla casa, NO se inventa texto: el párrafo queda vacío para
 * que lo escriba el analista. Un motor que rellena con una frase genérica es
 * peor que uno que se calla, porque el informe sale firmado igual.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ NO ALCANZA CON "NINGUNO / UNO / VARIOS"                          │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Ese conteo se apoya en `spec_status`, o sea en que exista un cuadro de
 * límites para el parámetro. Nueve de las quince familias NO tienen cuadro —ni
 * lo tenían en el sistema anterior— y sin embargo sí tienen diagnóstico: el
 * pasivador se lee contra 50 y 70 ppm, el grado de polimerización contra 1000,
 * 650 y 350, el inhibidor contra 0,08 %. Allá esos cortes estaban clavados en
 * el ERB; acá son BANDAS de la plantilla (`bands[]`), con su propio texto cada
 * una, y el laboratorio las mueve editando datos.
 *
 * Y hay familias donde el párrafo tiene que CITAR el número medido ("se detectó
 * 7,3 mg/kg de dibencil disulfuro"). Para eso están los marcadores `{value}`:
 * el valor y su unidad salen del resultado, no escritos dentro de la frase.
 */
class DiagnosisTextService
{
    /**
     * El idioma en el que están escritas las plantillas de `diagnosis_templates.json`.
     *
     * Hoy hay UNA sola redacción, en español, y es la que el laboratorio viene
     * firmando. Todo lo que la frase intercala —el conector de la enumeración,
     * y cualquier palabra que se agregue— tiene que resolverse en ESTE idioma y
     * no en el ambiente, o el texto sale mezclado y así queda guardado.
     *
     * Cuando existan las plantillas en inglés, esto pasa a ser un campo de la
     * plantilla y deja de ser una constante.
     */
    private const IDIOMA_PLANTILLAS = 'es';

    /**
     * Marca interna de "este hueco quedó sin nada".
     *
     * No es un texto que se imprima: es una señal para que
     * `quitarFrasesVacias()` descarte la frase entera. Un carácter de control,
     * para que no pueda aparecer nunca en una plantilla escrita por una persona.
     */
    private const VACIO = "\x00vacio\x00";

    /** @var array<int,array<string,mixed>>|null */
    private ?array $plantillas = null;

    /**
     * Genera (o regenera) el análisis de todas las familias de una muestra.
     *
     * @param  bool $pisarEditados Si es falso —lo normal— respeta lo que una
     *                             persona haya escrito a mano.
     * @return Collection<int,SampleDiagnosis>
     */
    public function generate(Sample $sample, bool $pisarEditados = false): Collection
    {
        $familias = $this->familias($sample);
        $salida = collect();

        foreach ($familias as $familia => $resultados) {
            $existente = SampleDiagnosis::firstOrNew([
                'sample_id' => $sample->id,
                'family'    => $familia,
            ]);

            if ($existente->exists && $existente->is_edited && ! $pisarEditados) {
                $salida->push($existente);
                continue;
            }

            $existente->fill([
                'body'         => $this->componer($sample, $familia, $resultados),
                'is_edited'    => false,
                'generated_at' => Carbon::now(),
                'tenant_id'    => $sample->tenant_id,
            ])->save();

            $salida->push($existente);
        }

        return $salida;
    }

    /**
     * Los resultados INFORMABLES de la muestra, agrupados por familia.
     *
     * Solo cuentan las pruebas validadas: opinar sobre un ensayo que todavía no
     * se firmó es opinar sobre un número que puede cambiar.
     *
     * @return Collection<string,Collection<int,Result>>
     */
    private function familias(Sample $sample): Collection
    {
        $pruebas = $sample->tests()
            ->whereIn('status', [SampleTest::STATUS_VALIDATED, SampleTest::STATUS_REPORTED])
            ->with('definition:id,code,report_comment_group')
            ->get();

        if ($pruebas->isEmpty()) {
            return collect();
        }

        $porPrueba = $pruebas->pluck('definition.report_comment_group', 'test_definition_id')
            ->filter();

        return Result::query()
            ->where('sample_id', $sample->id)
            ->whereIn('test_definition_id', $porPrueba->keys())
            ->with(['analyte:id,code,name,unit,decimals', 'field:id,label,report_visible'])
            ->get()
            ->filter(fn (Result $r) => $r->field?->report_visible ?? true)
            ->groupBy(fn (Result $r) => $porPrueba[$r->test_definition_id] ?? 'otros');
    }

    /**
     * @param  Collection<int,Result> $resultados
     */
    private function componer(Sample $sample, string $familia, Collection $resultados): ?string
    {
        // Sin un solo resultado no se opina. Parece obvio y no lo es: la
        // plantilla de "todo en orden" es justo la que se dispararía sola con
        // la colección vacía, y "no se detectó presencia de metales" dicho
        // sobre cero mediciones es la misma afirmación falsa que un cuadro de
        // límites ausente leído como "cumple".
        if ($resultados->isEmpty()) {
            return null;
        }

        // La primera plantilla, de la más específica a la más general, que se
        // pueda APLICAR de verdad. No alcanza con que case la familia: si pide
        // una banda y el valor medido no cae en ninguna, esa plantilla no
        // aplica y se sigue buscando; si no queda ninguna, el párrafo va vacío.
        foreach ($this->candidatas($familia, $sample) as $plantilla) {
            $texto = $this->aplicar($plantilla, $resultados);

            if ($texto !== null) {
                return $texto;
            }
        }

        return null;
    }

    /**
     * Las plantillas de la familia que sirven para esta muestra, de la más
     * específica a la más general. Mismo criterio que los cuadros de límites:
     * gana la que restringe más.
     *
     * @return Collection<int,array<string,mixed>>
     */
    private function candidatas(string $familia, Sample $sample): Collection
    {
        $aceite = $sample->equipment?->oilType?->code;
        $tipo   = $sample->equipment?->equipmentType?->code;

        return collect($this->plantillas())
            ->filter(fn ($p) => ($p['family'] ?? null) === $familia)
            ->filter(fn ($p) => empty($p['oil_types']) || in_array($aceite, $p['oil_types'], true))
            ->filter(fn ($p) => empty($p['equipment_types']) || in_array($tipo, $p['equipment_types'], true))
            ->sortByDesc(fn ($p) => count($p['oil_types'] ?? []) + count($p['equipment_types'] ?? []))
            ->values();
    }

    /**
     * El texto de UNA plantilla, o null si esa plantilla no aplica.
     *
     * @param  array<string,mixed>    $plantilla
     * @param  Collection<int,Result> $resultados
     */
    private function aplicar(array $plantilla, Collection $resultados): ?string
    {
        // Los que el párrafo tiene que llamar por su nombre. Normalmente son
        // los que están fuera de norma; con `threshold` la plantilla cambia ese
        // criterio por un corte de valor, que es como el sistema anterior
        // decidía qué metales nombrar (presencia por encima de 0,05 ppm) sin
        // que existiera cuadro de límites para ellos.
        $senalados = $this->senalados($plantilla, $resultados);
        $caso      = $plantilla['case'] ?? 'any';

        if ($caso !== 'any' && $caso !== $this->caso($senalados)) {
            return null;
        }

        // De qué resultado sale `{value}`: el que la plantilla declare, o el
        // único de la familia. Con varios y sin declarar, no se elige uno al
        // azar — se prefiere el párrafo vacío a citar el número equivocado.
        $medido = isset($plantilla['analyte'])
            ? $this->porCodigo($resultados, (string) $plantilla['analyte'])
            : ($resultados->count() === 1 ? $resultados->first() : null);

        $cuerpo = $plantilla['body'] ?? null;

        if (! empty($plantilla['bands'])) {
            $banda = $this->banda($plantilla['bands'], $medido);

            if ($banda === null) {
                return null;
            }

            $cuerpo = $banda['body'] ?? null;
        }

        if (! is_string($cuerpo) || $cuerpo === '') {
            return null;
        }

        // Un resultado SIN CRITERIO no cuenta ni como bueno ni como malo: nadie
        // lo comparó contra nada. Meterlo en la lista de "están dentro de los
        // valores sugeridos" sería afirmar algo que no se evaluó.
        $cuerpo = strtr($cuerpo, [
            '{ok}'            => $this->lista($resultados->where('spec_status', Result::SPEC_IN)),
            '{failed}'        => $this->lista($senalados),
            '{failed_values}' => $this->listaConValor($senalados),
            '{norm}'          => $this->norma($resultados),
            '{count}'         => (string) $senalados->count(),
        ]);

        $cuerpo = $this->quitarFrasesVacias($cuerpo);

        if ($cuerpo === '') {
            return null;
        }

        return $this->reemplazarValores($cuerpo, $resultados, $medido);
    }

    /**
     * Cuántos parámetros hay que señalar, en las tres categorías que las
     * plantillas distinguen.
     *
     * @param  Collection<int,Result> $senalados
     */
    private function caso(Collection $senalados): string
    {
        return match (true) {
            $senalados->isEmpty()     => 'none',
            $senalados->count() === 1 => 'one',
            default                   => 'many',
        };
    }

    /**
     * Los resultados que el párrafo tiene que nombrar.
     *
     * @param  array<string,mixed>    $plantilla
     * @param  Collection<int,Result> $resultados
     * @return Collection<int,Result>
     */
    private function senalados(array $plantilla, Collection $resultados): Collection
    {
        if (! isset($plantilla['threshold'])) {
            return $resultados->where('spec_status', Result::SPEC_OUT);
        }

        $umbral = (float) $plantilla['threshold'];

        // "Por encima del corte" tiene que ser CIERTO, no probable. Un "<1 ppm"
        // contra un corte de 0,05 no afirma presencia: dice que el instrumento
        // no llegó a verlo. Solo se señala cuando TODO el intervalo posible del
        // resultado queda por encima del corte.
        return $resultados->filter(function (Result $r) use ($umbral) {
            [$desde, , $desdeIncluido] = $this->intervalo($r);

            return $desde !== null
                && ($desde > $umbral || ($desde === $umbral && ! $desdeIncluido));
        })->values();
    }

    /**
     * La banda cuyo rango contiene al valor medido.
     *
     * Las bandas son [min, max): el mínimo entra y el máximo no, salvo que la
     * banda declare `max_inclusive` / `min_exclusive`. Es la forma en que están
     * escritos los cortes del sistema anterior (`>= 450 && < 700`), y el
     * pasivador necesita las dos excepciones porque allá el escalón del medio
     * era `>= 50 && <= 70` y el de arriba `> 70`.
     *
     * Tiene que tocar UNA sola banda. Dos bandas que se pisan son un error de
     * datos, y resolverlo por orden de aparición lo dejaría escondido dentro de
     * un párrafo que sale firmado. Un valor CENSURADO ("<5", ">70") tampoco es
     * un punto sino un intervalo: si toca dos bandas, en cuál cae es una
     * pregunta que el ensayo no respondió.
     *
     * @param  array<int,array<string,mixed>> $bandas
     * @return array<string,mixed>|null
     */
    private function banda(array $bandas, ?Result $medido): ?array
    {
        if ($medido === null) {
            return null;
        }

        [$desde, $hasta, $desdeIncluido, $hastaIncluido] = $this->intervalo($medido);

        if ($desde === null && $hasta === null) {
            return null;
        }

        $tocadas = array_values(array_filter(
            $bandas,
            fn ($b) => $this->seTocan(
                $desde, $hasta, $desdeIncluido, $hastaIncluido,
                isset($b['min']) ? (float) $b['min'] : null,
                isset($b['max']) ? (float) $b['max'] : null,
                ! ($b['min_exclusive'] ?? false),
                (bool) ($b['max_inclusive'] ?? false),
            )
        ));

        return count($tocadas) === 1 ? $tocadas[0] : null;
    }

    /**
     * Qué valores puede tener realmente un resultado.
     *
     * Un número a secas es un punto. Un "<5" es todo lo que hay por debajo de
     * 5 y un ">70" todo lo que hay por encima: tratarlos como el número que
     * llevan al lado es el error que el sistema anterior cometía al limpiar el
     * signo antes de convertir.
     *
     * @return array{0:?float,1:?float,2:bool,3:bool} desde, hasta, ¿entra el desde?, ¿entra el hasta?
     */
    private function intervalo(Result $resultado): array
    {
        if ($resultado->value_num === null) {
            return [null, null, false, false];
        }

        $v = (float) $resultado->value_num;

        return match ($resultado->qualifier) {
            Result::QUALIFIER_LT => [null, $v, false, false],
            Result::QUALIFIER_GT => [$v, null, false, false],
            default              => [$v, $v, true, true],
        };
    }

    /** ¿Se solapan dos intervalos? Los nulos son infinito. */
    private function seTocan(
        ?float $aDesde, ?float $aHasta, bool $aDesdeInc, bool $aHastaInc,
        ?float $bDesde, ?float $bHasta, bool $bDesdeInc, bool $bHastaInc,
    ): bool {
        $porDebajo = function (?float $desde, bool $desdeInc, ?float $hasta, bool $hastaInc): bool {
            if ($desde === null || $hasta === null) {
                return false;               // un extremo infinito nunca corta
            }

            return $desde > $hasta || ($desde === $hasta && ! ($desdeInc && $hastaInc));
        };

        return ! $porDebajo($aDesde, $aDesdeInc, $bHasta, $bHastaInc)
            && ! $porDebajo($bDesde, $bDesdeInc, $aHasta, $aHastaInc);
    }

    /**
     * Los marcadores que citan el número medido.
     *
     *   {value}      12.4 mg/kg   — el valor con su unidad
     *   {value_num}  12.4         — el valor pelado, para frases que ya
     *                               escriben la unidad
     *   {unit}       mg/kg
     *
     * Con `:codigo` se pide un parámetro concreto de la familia
     * (`{value:passivator}`); sin él, el que la plantilla declaró en `analyte`.
     * Y con `[n]` se toma el n-ésimo tramo de un código compuesto: el ISO 4406
     * es "18/16/13" y el párrafo explica cada dígito por separado.
     *
     * @param  Collection<int,Result> $resultados
     */
    private function reemplazarValores(string $cuerpo, Collection $resultados, ?Result $medido): string
    {
        return (string) preg_replace_callback(
            // `value_num` va antes que `value`: con la alternancia al revés el
            // motor de expresiones tiene que retroceder para acertarle.
            '/\{(value_num|value|unit)(?::([a-z0-9_]+))?(?:\[(\d+)\])?\}/i',
            function (array $m) use ($resultados, $medido) {
                $r = isset($m[2]) && $m[2] !== '' ? $this->porCodigo($resultados, $m[2]) : $medido;

                if ($r === null) {
                    return '—';
                }

                $unidad = (string) ($r->unit ?? $r->analyte?->unit ?? '');
                $numero = (string) ($r->display ?? '');

                if (($m[3] ?? '') !== '') {
                    // Tramo de un código compuesto. Sin unidad: "18" de
                    // "18/16/13" no son 18 de nada.
                    $tramos = explode('/', $numero);

                    return trim($tramos[((int) $m[3]) - 1] ?? '—');
                }

                return match (strtolower($m[1])) {
                    'unit'      => $unidad !== '' ? $unidad : '—',
                    'value_num' => $numero !== '' ? $numero : '—',
                    default     => trim($numero . ' ' . $unidad) ?: '—',
                };
            },
            $cuerpo,
        );
    }

    /**
     * @param  Collection<int,Result> $resultados
     */
    private function porCodigo(Collection $resultados, string $codigo): ?Result
    {
        return $resultados->first(fn (Result $r) => $r->analyte?->code === $codigo);
    }

    /**
     * "número ácido, rigidez dieléctrica y contenido de agua".
     *
     * Con la conjunción antes del último y sin coma colgando. En el sistema
     * anterior la lista se armaba concatenando condicionales, y cuando el
     * último no aplicaba quedaba la coma al aire.
     *
     * @param  Collection<int,Result> $resultados
     */
    private function lista(Collection $resultados): string
    {
        return $this->enumerar($resultados->map(
            fn (Result $r) => mb_strtolower((string) ($r->analyte?->name ?? $r->field?->label))
        ));
    }

    /**
     * "7.3 ppm de aluminio y 2.1 ppm de cobre".
     *
     * La misma lista, pero citando cuánto se midió de cada uno. Es lo que el
     * párrafo de metales necesita, y en el sistema anterior se armaba con ocho
     * variables `@str_metN` concatenadas —cada una terminada en coma— así que
     * la frase salía impresa con una coma antes de "como compuestos metálicos".
     *
     * @param  Collection<int,Result> $resultados
     */
    private function listaConValor(Collection $resultados): string
    {
        return $this->enumerar($resultados->map(function (Result $r) {
            $nombre = mb_strtolower((string) ($r->analyte?->name ?? $r->field?->label));
            $unidad = (string) ($r->unit ?? $r->analyte?->unit ?? '');

            return trim(trim($r->display . ' ' . $unidad) . ' ' . __('reports.of') . ' ' . $nombre);
        }));
    }

    /**
     * Une con la conjunción antes del último y sin coma colgando.
     *
     * @param  Collection<int,string> $partes
     */
    private function enumerar(Collection $partes): string
    {
        $partes = $partes->filter()->unique()->values();

        if ($partes->isEmpty()) {
            return self::VACIO;
        }

        if ($partes->count() === 1) {
            return (string) $partes->first();
        }

        $ultimo = $partes->pop();

        // El conector se resuelve en el idioma de la PLANTILLA, no en el que
        // tenga la aplicación en ese instante. Con `__('reports.and')` a secas
        // el papel salía diciendo "contenido de agua and rigidez dieléctrica":
        // el cuerpo de la plantilla está en español y el conector se traducía
        // con el idioma ambiente, que en una consola, una cola o una sesión en
        // inglés es otro. Y el texto queda GUARDADO, así que ese engendro se
        // congelaba y se imprimía en el informe del cliente.
        return $partes->implode(', ') . ' ' . __('reports.and', [], self::IDIOMA_PLANTILLAS) . ' ' . $ultimo;
    }

    /**
     * La norma del criterio con el que se juzgó. Sale del propio resultado
     * (`spec_source`, congelado al validar), no de una constante escrita en la
     * frase: si el laboratorio cambia de edición de la norma, el texto la sigue
     * sin que nadie lo edite.
     *
     * @param  Collection<int,Result> $resultados
     */
    private function norma(Collection $resultados): string
    {
        return (string) ($resultados->pluck('spec_source')->filter()->first() ?? self::VACIO);
    }

    /**
     * Borra las frases que quedaron hablando de la nada.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ POR QUÉ NO ALCANZA CON PONER UNA RAYA                                │
     * └──────────────────────────────────────────────────────────────────────┘
     * Cuando la lista de un hueco queda vacía, poner "—" produce una frase que
     * AFIRMA algo sobre nada: en el informe de una muestra donde ningún
     * fisicoquímico tenía criterio de aceptación salió impreso "Los resultados
     * obtenidos de las pruebas de — están dentro de los valores sugeridos por la
     * Norma IEEE C57.106-2015". Eso no es un hueco cosmético: es una conformidad
     * declarada sobre parámetros que nadie comparó contra nada, en un papel
     * firmado.
     *
     * Los cuerpos de las plantillas son una frase por línea, así que la línea
     * que quedó con un hueco vacío se descarta entera. Si se descartan todas, la
     * familia queda SIN texto —y el informe lo dice— en vez de con una frase a
     * medias.
     */
    private function quitarFrasesVacias(string $cuerpo): string
    {
        $lineas = preg_split('/\R/', $cuerpo) ?: [];

        $lineas = array_filter(
            $lineas,
            fn (string $linea) => ! str_contains($linea, self::VACIO),
        );

        return trim(implode("\n", $lineas));
    }

    /**
     * Las plantillas con las que se redacta el análisis.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ LA BASE MANDA; EL ARCHIVO ES EL VALOR DE FÁBRICA                     │
     * └──────────────────────────────────────────────────────────────────────┘
     * Primero se buscan en `diagnosis_templates`, prefiriendo las del workspace
     * sobre las globales (`scopeForTenant`): así el laboratorio ajusta SU
     * redacción desde la pantalla, sin un despliegue y sin tocar el estándar de
     * los demás.
     *
     * Si la tabla está vacía —una instalación recién migrada, o los tests que no
     * corren el seeder— se cae al JSON del repositorio. Eso no es un respaldo
     * decorativo: es lo que hace que el informe nunca salga sin su análisis por
     * un seeder que nadie corrió.
     *
     * @return array<int,array<string,mixed>>
     */
    private function plantillas(): array
    {
        if ($this->plantillas !== null) {
            return $this->plantillas;
        }

        $deLaBase = $this->plantillasDeLaBase();

        return $this->plantillas = $deLaBase !== [] ? $deLaBase : $this->plantillasDeFabrica();
    }

    /**
     * Las plantillas de la base, con la personalización del workspace pisando a
     * la de fábrica.
     *
     * El descarte se hace por (familia + caso + analito): una plantilla propia
     * reemplaza a la global de su MISMO caso, no a todas las de la familia. Si
     * el laboratorio solo reescribió el texto de "ningún resultado fuera de
     * norma", el resto de los casos sigue con la redacción estándar.
     *
     * @return array<int,array<string,mixed>>
     */
    private function plantillasDeLaBase(): array
    {
        if (! app()->bound('db') || ! \Illuminate\Support\Facades\Schema::hasTable('diagnosis_templates')) {
            return [];
        }

        $tenantId = auth()->user()?->tenant_id;

        // `withoutGlobalScopes` porque el resolvedor tiene que ver las globales
        // (tenant nulo) además de las del workspace, y el scope del trait deja
        // fuera unas u otras según quién esté mirando. El filtro correcto lo
        // aplica `forTenant`, que es explícito sobre los dos casos.
        $filas = \App\Models\DiagnosisTemplate::withoutGlobalScopes()
            ->forTenant($tenantId)
            ->get();

        $porClave = [];

        foreach ($filas as $fila) {
            $clave = $fila->family . '|' . $fila->case . '|' . ($fila->analyte ?? '');

            // La primera gana: `forTenant` ya ordenó las del workspace antes.
            if (array_key_exists($clave, $porClave)) {
                continue;
            }

            $porClave[$clave] = array_filter([
                'family'          => $fila->family,
                'case'            => $fila->case,
                'oil_types'       => $fila->oil_types ?? [],
                'equipment_types' => $fila->equipment_types ?? [],
                'analyte'         => $fila->analyte,
                'threshold'       => $fila->threshold !== null ? (float) $fila->threshold : null,
                'bands'           => $fila->bands,
                'body'            => $fila->body,
            ], fn ($v) => $v !== null);
        }

        return array_values($porClave);
    }

    /**
     * Los valores de fábrica del repositorio.
     *
     * @return array<int,array<string,mixed>>
     */
    private function plantillasDeFabrica(): array
    {
        $ruta = database_path('seeders/data/diagnosis_templates.json');

        if (! is_file($ruta)) {
            return [];
        }

        $datos = json_decode((string) file_get_contents($ruta), true) ?: [];

        return $datos['templates'] ?? [];
    }
}
