<?php

namespace Database\Seeders;

use App\Models\Analyte;
use App\Models\EquipmentType;
use App\Models\OilType;
use App\Models\SpecLimit;
use App\Models\SpecSet;
use App\Models\Standard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Los cuadros de valores de orientación, como datos.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ SE ESTÁ REEMPLAZANDO                                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema Rails anterior, decidir contra qué límite se compara un
 * resultado era un árbol de `if/elsif` sobre el tipo de aceite, el tipo de
 * equipo y la tensión, con el límite escrito como TEXTO:
 *
 *     if @transformer_oil_type_id.to_i == 1
 *       if @num_ten <= 69
 *         aci_ori: "0.20 - máximo", rig_ori: "40.0 - mínimo", …
 *
 * Ese árbol está escrito TRES veces —dos completas y una parcial— y las copias
 * ya divergieron. Acá cada rama es una fila con sus criterios y su vigencia, y
 * los límites son NÚMEROS.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LAS CONDICIONES VAN POR CÓDIGO, NO POR ID                                │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El archivo dice `"oil_type_code": ["mineral"]` y no `"oil_type_id": 1`. La
 * diferencia importa: los ids del sistema anterior son de SU base, y una
 * instalación nueva no tiene por qué asignarlos igual. Si un día el mineral deja
 * de ser el 1, un cuadro anclado al id le aplicaría límites de mineral al aceite
 * equivocado, en silencio y sin que nada falle.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ SE SIEMBRA TAL CUAL, ANOMALÍAS INCLUIDAS                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Los números salen del sistema anterior y se cargan FIELES, aunque alguno sea
 * evidentemente un error. Las 16 anomalías están anotadas en el propio archivo
 * y las resuelve el laboratorio: elegir por criterio propio cuál es el valor
 * bueno sería cambiar el criterio con el que se emitieron informes, sin que
 * nadie lo haya decidido.
 *
 * Un cuadro puede venir SIN límites ("sin criterio en el sistema viejo", que el
 * Ruby escribía como "-"). Se siembra igual: que exista y esté vacío es una
 * afirmación —"para esta combinación no hay valores de referencia"— y es
 * distinto de que no exista.
 *
 * Idempotente: se ancla al código del cuadro.
 */
class LabSpecSetsSeeder extends Seeder
{
    /**
     * Los códigos del archivo → parámetro del sistema nuevo, y el método con el
     * que se midió cuando el código lo lleva adentro.
     *
     * Ésta es la traducción que deshace la confusión del sistema anterior. Allá
     * `rig_d1816` y `rig_d877` eran dos parámetros distintos, y `pot@25`,
     * `pot@90` y `pot@100` otros tres. En realidad son DOS parámetros medidos de
     * varias maneras, y por eso el límite se ata al par (parámetro, método): la
     * rigidez por D877 —electrodos planos, 2.54 mm— no se compara contra la de
     * D1816, que admite 1 o 2 mm, porque los kV no son equivalentes.
     *
     * Los códigos sin método (`ten`, `col`, `agu`, `aci`) son solo el nombre
     * corto del sistema anterior.
     */
    private const PARAMETROS = [
        'rig_d1816' => ['rig',    'rig_d1816_2mm'],
        'rig_d877'  => ['rig_ep', 'rig_d877'],
        'pot@25'    => ['fp25',   'fp_d924_25'],
        'pot@90'    => ['fp90',   'fp_d924_90'],
        'pot@100'   => ['fp100',  'fp_d924_100'],
        'ten'       => ['ift',    null],
        'col'       => ['color',  null],
        'agu'       => ['wat',    null],
        'aci'       => ['acid',   null],
    ];

    /** Los grupos del archivo → los del sistema nuevo. */
    private const GRUPOS = [
        'cromas' => SpecSet::GROUP_DGA,
        'fiquis' => SpecSet::GROUP_FIQUI,
        'fiqui'  => SpecSet::GROUP_FIQUI,
        'papel'  => SpecSet::GROUP_PAPER,
        'otros'  => SpecSet::GROUP_OTHER,
    ];

    public function run(): void
    {
        $ruta = database_path('seeders/data/spec_limits_legacy.json');

        if (! is_file($ruta)) {
            $this->command?->warn(
                'No se encontró spec_limits_legacy.json: los cuadros de límites quedan sin sembrar. '
                . 'Hasta que existan, los resultados se materializan SIN veredicto contra la norma '
                . '(en nulo, que significa "sin criterio" y NO "cumple").'
            );

            return;
        }

        $json = json_decode((string) file_get_contents($ruta), true) ?: [];
        $cuadros = $json['cuadros'] ?? [];

        if ($cuadros === []) {
            $this->command?->warn('spec_limits_legacy.json no trae cuadros.');

            return;
        }

        $analitos = Analyte::withoutGlobalScopes()->pluck('id', 'code');
        $aceites = $this->catalogo(OilType::class);
        $equipos = $this->catalogo(EquipmentType::class);
        $metodos = \App\Models\TestMethod::withoutGlobalScopes()->pluck('id', 'code');
        $normas = Standard::withoutGlobalScopes()->pluck('id', 'code');

        $sembrados = 0;
        $limites = 0;
        $problemas = [];

        foreach ($cuadros as $i => $c) {
            $condicion = $c['condicion'] ?? [];

            // Una condición con VARIOS aceites es una sola rama del Ruby que
            // cubre dos fluidos (el éster: soya y sintético juntos). Se
            // convierte en un cuadro por aceite: el esquema declara UN criterio
            // por columna a propósito, porque es lo que permite que la
            // resolución elija por especificidad sin desempatar a mano.
            $codigosAceite = $condicion['oil_type_code'] ?? [null];

            foreach ($codigosAceite as $n => $codigoAceite) {
                $codigo = $this->codigo($c, $i, $codigoAceite, count($codigosAceite) > 1 ? $n : null);

                $aceiteId = $codigoAceite !== null ? ($aceites[$codigoAceite] ?? null) : null;

                if ($codigoAceite !== null && $aceiteId === null) {
                    $problemas[] = "El tipo de aceite '{$codigoAceite}' no existe (cuadro {$codigo}).";
                    continue;
                }

                $codigoEquipo = $condicion['equipment_type_code'][0] ?? null;
                $equipoId = $codigoEquipo !== null ? ($equipos[$codigoEquipo] ?? null) : null;

                if ($codigoEquipo !== null && $equipoId === null) {
                    $problemas[] = "El tipo de equipo '{$codigoEquipo}' no existe (cuadro {$codigo}).";
                    continue;
                }

                $cuadro = SpecSet::withoutGlobalScopes()->where('code', $codigo)->first();

                if (! $cuadro) {
                    $cuadro = SpecSet::create([
                        'slug'              => Str::random(22),
                        'code'              => $codigo,
                        'label'             => $c['label'] ?? $codigo,
                        'group'             => self::GRUPOS[$c['grupo'] ?? 'fiqui'] ?? SpecSet::GROUP_FIQUI,
                        'standard_id'       => $normas[$c['norma_aceptacion'] ?? ''] ?? null,
                        'oil_type_id'       => $aceiteId,
                        'equipment_type_id' => $equipoId,
                        'voltage_from'      => $condicion['voltage_from'] ?? null,
                        'voltage_to'        => $condicion['voltage_to'] ?? null,
                        'source_note'       => $this->procedencia($c),
                        'is_active'         => true,
                        'tenant_id'         => null,
                    ]);
                    $sembrados++;
                } else {
                    // Si el laboratorio lo editó desde la pantalla no se pisa:
                    // solo se refresca de dónde salió.
                    $cuadro->fill(['source_note' => $this->procedencia($c)])->save();
                }

                $limites += $this->sembrarLimites($cuadro, $c['limites'] ?? [], $analitos, $metodos, $problemas);
            }
        }

        $this->command?->info("Cuadros de límites: {$sembrados} sembrados, {$limites} límites.");

        foreach (array_slice(array_unique($problemas), 0, 8) as $p) {
            $this->command?->warn("  · {$p}");
        }

        $this->avisarAnomalias($json['anomalias'] ?? []);
    }

    /**
     * Los límites de un cuadro.
     *
     * Los que el sistema anterior dejaba en "-" NO se guardan: un límite sin
     * operador no dice nada, y guardarlo haría creer que el parámetro tiene
     * criterio. Lo que sí queda es el cuadro, vacío, que es la afirmación
     * correcta: "para esta combinación no hay valores de referencia".
     *
     * @param  array<int,array<string,mixed>> $limites
     * @param  array<int,string> $problemas
     */
    private function sembrarLimites(SpecSet $cuadro, array $limites, $analitos, $metodos, array &$problemas): int
    {
        $hechos = 0;
        $orden = 0;

        foreach ($limites as $l) {
            $original = $l['analyte'] ?? null;

            if ($original === null || blank($l['operator'] ?? null)) {
                continue;
            }

            [$codigo, $codigoMetodo] = self::PARAMETROS[$original] ?? [$original, null];

            if (! isset($analitos[$codigo])) {
                $problemas[] = "El parámetro '{$original}' no existe (cuadro {$cuadro->code}).";
                continue;
            }

            $metodoId = $codigoMetodo !== null ? ($metodos[$codigoMetodo] ?? null) : null;

            [$min, $max, $texto] = $this->cotas($l);

            SpecLimit::updateOrCreate(
                [
                    'spec_set_id'    => $cuadro->id,
                    'analyte_id'     => $analitos[$codigo],
                    'test_method_id' => $metodoId,
                ],
                [
                    'operator'   => $l['operator'],
                    'min_value'  => $min,
                    'max_value'  => $max,
                    'text_value' => $texto,
                    // El texto literal del sistema anterior ("0.20 - máximo"),
                    // para poder cotejar cuadro por cuadro contra sus informes.
                    'display'    => $l['display'] ?? null,
                    'notes'      => $l['nota'] ?? null,
                    'sort_order' => ++$orden,
                ],
            );

            $hechos++;
        }

        return $hechos;
    }

    /**
     * De qué lado del límite está el valor declarado.
     *
     * @param  array<string,mixed> $l
     * @return array{0:?float,1:?float,2:?string}
     */
    private function cotas(array $l): array
    {
        $valor = $l['value'] ?? null;

        return match ($l['operator']) {
            '>='    => [$valor, null, null],
            'text'  => [null, null, (string) ($l['value'] ?? $l['text'] ?? $l['display'] ?? '')],
            default => [null, $valor, null],
        };
    }

    /**
     * El código del cuadro. Estable, porque es la clave de la siembra
     * idempotente: si cambiara entre corridas se duplicarían los cuadros.
     *
     * @param  array<string,mixed> $c
     */
    private function codigo(array $c, int $i, ?string $aceite, ?int $n): string
    {
        $base = Str::slug(($c['grupo'] ?? 'set') . '-' . ($c['label'] ?? $i), '_');
        $base = Str::limit($base, 60, '');

        if ($aceite !== null && $n !== null) {
            // La rama del Ruby cubría varios aceites: cada uno lleva el suyo.
            return $base . '__' . $aceite;
        }

        return $base;
    }

    /** @param array<string,mixed> $c */
    private function procedencia(array $c): string
    {
        $partes = array_filter([
            $c['origen'] ?? null,
            $c['nota'] ?? null,
        ]);

        return implode(' — ', $partes) ?: 'Extraído del sistema anterior.';
    }

    /**
     * El catálogo por código, o por nombre si no tiene columna de código.
     *
     * @param  class-string $modelo
     * @return \Illuminate\Support\Collection<string,int>
     */
    private function catalogo(string $modelo)
    {
        $tabla = (new $modelo())->getTable();

        if (\Illuminate\Support\Facades\Schema::hasColumn($tabla, 'code')) {
            return $modelo::withoutGlobalScopes()->whereNotNull('code')->pluck('id', 'code');
        }

        // Sin columna de código se empareja por el nombre normalizado, que es
        // como el archivo los nombra ("vegetal_soya" ← "Éster natural (soya)").
        return $modelo::withoutGlobalScopes()->get(['id', 'name'])
            ->mapWithKeys(fn ($m) => [Str::slug($m->name, '_') => $m->id]);
    }

    /**
     * @param array<int,array<string,mixed>> $anomalias
     */
    private function avisarAnomalias(array $anomalias): void
    {
        if ($anomalias === []) {
            return;
        }

        $graves = collect($anomalias)->where('gravedad', 'alta')->count();

        $this->command?->line(
            '  ' . count($anomalias) . ' anomalías del sistema anterior anotadas en '
            . 'spec_limits_legacy.json (' . $graves . ' graves). Las resuelve el laboratorio: '
            . 'corregirlas por criterio propio cambiaría lo que dicen los informes ya emitidos.'
        );
    }
}
