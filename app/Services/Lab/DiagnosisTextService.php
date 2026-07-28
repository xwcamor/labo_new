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
 */
class DiagnosisTextService
{
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
            ->with(['analyte:id,name', 'field:id,label,report_visible'])
            ->get()
            ->filter(fn (Result $r) => $r->field?->report_visible ?? true)
            ->groupBy(fn (Result $r) => $porPrueba[$r->test_definition_id] ?? 'otros');
    }

    /**
     * @param  Collection<int,Result> $resultados
     */
    private function componer(Sample $sample, string $familia, Collection $resultados): ?string
    {
        // Un resultado SIN CRITERIO no cuenta ni como bueno ni como malo: nadie
        // lo comparó contra nada. Meterlo en la lista de "están dentro de los
        // valores sugeridos" sería afirmar algo que no se evaluó.
        $dentro = $resultados->where('spec_status', 'in_spec');
        $fuera  = $resultados->where('spec_status', 'out_of_spec');

        $caso = match (true) {
            $fuera->isEmpty()    => 'none',
            $fuera->count() === 1 => 'one',
            default              => 'many',
        };

        $plantilla = $this->elegir($familia, $sample, $caso);

        if ($plantilla === null) {
            return null;
        }

        return strtr($plantilla['body'], [
            '{ok}'     => $this->lista($dentro),
            '{failed}' => $this->lista($fuera),
            '{norm}'   => $this->norma($resultados),
            '{count}'  => (string) $fuera->count(),
        ]);
    }

    /**
     * La plantilla más específica que case. Mismo criterio que los cuadros de
     * límites: gana la que restringe más.
     *
     * @return array<string,mixed>|null
     */
    private function elegir(string $familia, Sample $sample, string $caso): ?array
    {
        $aceite = $sample->equipment?->oilType?->code;
        $tipo   = $sample->equipment?->equipmentType?->code;

        $candidatas = collect($this->plantillas())
            ->filter(fn ($p) => $p['family'] === $familia && $p['case'] === $caso)
            ->filter(fn ($p) => empty($p['oil_types']) || in_array($aceite, $p['oil_types'], true))
            ->filter(fn ($p) => empty($p['equipment_types']) || in_array($tipo, $p['equipment_types'], true));

        return $candidatas
            ->sortByDesc(fn ($p) => count($p['oil_types']) + count($p['equipment_types']))
            ->first();
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
        $nombres = $resultados
            ->map(fn (Result $r) => mb_strtolower((string) ($r->analyte?->name ?? $r->field?->label)))
            ->filter()
            ->unique()
            ->values();

        if ($nombres->isEmpty()) {
            return '—';
        }

        if ($nombres->count() === 1) {
            return $nombres->first();
        }

        $ultimo = $nombres->pop();

        return $nombres->implode(', ') . ' ' . __('reports.and') . ' ' . $ultimo;
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
        return (string) ($resultados->pluck('spec_source')->filter()->first() ?? '—');
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function plantillas(): array
    {
        if ($this->plantillas !== null) {
            return $this->plantillas;
        }

        $ruta = database_path('seeders/data/diagnosis_templates.json');

        if (! is_file($ruta)) {
            return $this->plantillas = [];
        }

        $datos = json_decode((string) file_get_contents($ruta), true) ?: [];

        return $this->plantillas = $datos['templates'] ?? [];
    }
}
