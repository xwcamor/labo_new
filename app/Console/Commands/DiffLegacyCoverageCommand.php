<?php

namespace App\Console\Commands;

use App\Models\Sample;
use App\Services\Lab\LegacyReportRenderer;
use App\Services\Lab\TestReportPayload;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * QUÉ IMPRIME EL PAPEL VIEJO Y QUÉ NO IMPRIMIMOS NOSOTROS.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ESTE COMANDO EXISTE                                              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `report:compare` genera los dos informes de la misma muestra para ponerlos
 * uno al lado del otro. Sirve para discutir la MAQUETA, y no sirve para lo
 * otro: si una fila falta en los dos, se ven igual de vacíos y nadie lo nota.
 *
 * Los cuatro defectos que aparecieron el 2026-08-02 —la hoja de fisicoquímicos
 * con once filas donde el papel viejo trae trece, los tres azufres impresos sin
 * valor, la sección de análisis con los títulos y ningún párrafo, y la hoja
 * titulada «OTROS»— se encontraron TODOS a mano, mirando. Ninguno habría
 * aparecido en una comparación de PDF contra PDF.
 *
 * Este comando compara por PARÁMETRO IMPRESO, que es la unidad que importa:
 * para cada hoja del papel viejo, qué filas trae y con qué valor, contra lo que
 * produce el sistema nuevo para la MISMA muestra. Lo que el viejo imprime y
 * nosotros no, sale listado con su motivo.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ DE DÓNDE SALE «LO QUE EL VIEJO IMPRIME»                                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 * De las CONSTANTES del renderizador de la plantilla clásica, que se
 * transcribieron de las plantillas ERB fila por fila: `FIQUIS` son los trece
 * ítems numerados de la hoja de fisicoquímicos y `GASES` los nueve de la
 * cromatografía. No es una lista escrita para este comando —eso solo
 * verificaría que una copia coincide con otra—: es la misma que dibuja el
 * papel, así que si alguien le saca una fila, este comando lo dice.
 *
 * Para el resto de las hojas la referencia son las FAMILIAS de
 * `config/legacy_report.php`, que también salen de los parciales del viejo (un
 * archivo por prueba).
 */
#[AsCommand(
    name: 'lab:diff-viejo',
    description: 'Compara, parámetro por parámetro, lo que imprime el informe viejo contra lo que produce el nuevo',
)]
class DiffLegacyCoverageCommand extends Command
{
    protected $signature = 'lab:diff-viejo
                            {sample? : Código o slug de la muestra; si falta, la que más pruebas tenga}
                            {--todas : Recorre TODAS las muestras con resultados, no una}';

    public function handle(): int
    {
        $muestras = $this->option('todas')
            ? $this->conResultados()
            : collect([$this->unaMuestra()])->filter();

        if ($muestras->isEmpty()) {
            $this->error('No hay ninguna muestra con resultados. Corré primero `php artisan setup:project`.');

            return self::FAILURE;
        }

        $huecos = 0;

        foreach ($muestras as $muestra) {
            $huecos += $this->revisar($muestra);
        }

        $this->line('');

        if ($huecos === 0) {
            $this->info('Sin huecos: todo lo que el papel viejo imprime, el nuevo lo produce.');

            return self::SUCCESS;
        }

        $this->warn("{$huecos} hueco(s). Cada uno es una fila que el cliente veía y ahora no.");

        // No es un fallo del comando: el hueco puede ser una decisión tomada
        // (particulas y metales todavía no declaran sus parámetros). Devuelve
        // éxito para no romper un pipeline; lo que importa es la lista.
        return self::SUCCESS;
    }

    /**
     * Una muestra y sus huecos.
     *
     * @return int cuántos parámetros del papel viejo no se produjeron
     */
    private function revisar(Sample $muestra): int
    {
        $this->line('');
        $this->line("  <fg=cyan>Muestra {$muestra->code}</>"
            . ' · ' . ($muestra->equipment?->oilType?->name ?? 'sin aceite')
            . ' · ' . ($muestra->equipment?->equipmentType?->name ?? 'sin tipo'));
        $this->line('');

        $datos = app(TestReportPayload::class)->forSample($muestra);

        // Lo que el nuevo produce, indexado por código de parámetro. Se mira el
        // PAYLOAD y no la tabla `results` a propósito: entre una y otro hay una
        // capa —la visibilidad de la prueba en el informe, el corte por hoja—,
        // y lo que el cliente ve es lo que sale del payload.
        $producidos = [];

        foreach ($datos['sections'] as $seccion) {
            foreach ($seccion['rows'] as $fila) {
                $codigo = $fila['code'] ?? null;

                if ($codigo !== null) {
                    $producidos[$codigo] = $fila['value'] ?? null;
                }
            }
        }

        $huecos = 0;
        $huecos += $this->compararHoja('Fisicoquímicos', $this->esperadoFiquis(), $producidos, $muestra);
        $huecos += $this->compararHoja('Cromatografía', $this->esperadoGases(), $producidos, $muestra);
        $huecos += $this->compararAnalisis($datos);

        return $huecos;
    }

    /**
     * Una hoja: qué filas trae el papel viejo y cuáles produjo el nuevo.
     *
     * @param  array<string,string>      $esperado  código de parámetro => rótulo del viejo
     * @param  array<string,string|null> $producidos
     */
    private function compararHoja(string $hoja, array $esperado, array $producidos, Sample $muestra): int
    {
        $faltan = [];
        $vacios = [];

        foreach ($esperado as $codigo => $rotulo) {
            if (! array_key_exists($codigo, $producidos)) {
                $faltan[] = [$codigo, $rotulo, $this->porQueFalta($codigo, $muestra)];

                continue;
            }

            // IMPRESO PERO VACÍO. Es el caso que una comparación de PDF contra
            // PDF no distingue de «no está»: la fila sale, con su norma y su
            // unidad, y la celda del resultado en blanco. Fue lo que pasó con
            // los tres azufres.
            $valor = trim((string) ($producidos[$codigo] ?? ''));

            if ($valor === '' || $valor === '—' || $valor === '-') {
                $vacios[] = [$codigo, $rotulo];
            }
        }

        $this->line(sprintf(
            '    %-18s %d de %d',
            $hoja,
            count($esperado) - count($faltan),
            count($esperado),
        ));

        foreach ($faltan as [$codigo, $rotulo, $motivo]) {
            $this->line("      <fg=red>falta</>   {$rotulo} ({$codigo}) — {$motivo}");
        }

        foreach ($vacios as [$codigo, $rotulo]) {
            $this->line("      <fg=yellow>vacío</>   {$rotulo} ({$codigo}) — la fila se imprime sin valor");
        }

        return count($faltan) + count($vacios);
    }

    /**
     * El análisis de resultados: una familia con hoja y sin párrafo es un hueco.
     *
     * El papel viejo imprimía una opinión por familia. Una hoja con su título y
     * ninguna línea debajo es exactamente lo que el cliente lee como que el
     * laboratorio no dijo nada.
     *
     * @param  array<string,mixed> $datos
     */
    private function compararAnalisis(array $datos): int
    {
        $sinTexto = [];

        foreach ($datos['analysis'] as $fila) {
            if (trim((string) ($fila['body'] ?? '')) === '') {
                $sinTexto[] = $fila['label'] ?? $fila['family'];
            }
        }

        $total = count($datos['analysis']);

        $this->line(sprintf(
            '    %-18s %d de %d familias con párrafo',
            'Análisis',
            $total - count($sinTexto),
            $total,
        ));

        foreach ($sinTexto as $familia) {
            $this->line("      <fg=red>sin texto</> {$familia}");
        }

        return count($sinTexto);
    }

    /**
     * Por qué no se produjo ese parámetro.
     *
     * Sin el motivo la lista obliga a investigar cada hueco desde cero, y los
     * motivos son pocos y siempre los mismos. El orden va de la causa más
     * profunda a la más superficial: sin columna declarada no hay resultado
     * posible, y recién si la hay tiene sentido preguntar si la prueba se pidió.
     */
    private function porQueFalta(string $codigo, Sample $muestra): string
    {
        $analito = \App\Models\Analyte::withoutGlobalScopes()->where('code', $codigo)->first();

        if (! $analito) {
            return 'el parámetro no existe en el catálogo';
        }

        $columna = \App\Models\TestField::where('output_analyte_id', $analito->id)->first();

        if (! $columna) {
            return 'ninguna columna lo alimenta (declararlo en analyte_map.json)';
        }

        $pedida = $muestra->tests()
            ->where('test_definition_id', $columna->test_definition_id)
            ->first();

        if (! $pedida) {
            return 'la prueba no se pidió para esta muestra';
        }

        if (! in_array($pedida->status, ['validated', 'reported'], true)) {
            return "la prueba está en «{$pedida->status}», no validada";
        }

        return 'la prueba está validada pero no produjo resultado (revisar la bancada)';
    }

    /** @return array<string,string> */
    private function esperadoFiquis(): array
    {
        return $this->deLaConstante('FIQUIS');
    }

    /** @return array<string,string> */
    private function esperadoGases(): array
    {
        return $this->deLaConstante('GASES');
    }

    /**
     * Las filas del papel viejo, leídas de la constante que las DIBUJA.
     *
     * Se lee por reflexión y no se copia: una lista copiada solo verificaría
     * que dos copias coinciden. Así, si alguien saca una fila del renderizador,
     * este comando deja de exigirla — y si se la saca por error, la hoja
     * impresa y la comparación se equivocan JUNTAS, que es visible, en vez de
     * la comparación diciendo que todo está bien.
     *
     * @return array<string,string>
     */
    private function deLaConstante(string $nombre): array
    {
        $constante = (new \ReflectionClass(LegacyReportRenderer::class))->getConstant($nombre);

        return collect($constante)
            ->mapWithKeys(fn (array $fila, string $codigo) => [$codigo => trim((string) $fila[1])])
            ->all();
    }

    private function unaMuestra(): ?Sample
    {
        $codigo = $this->argument('sample');

        if ($codigo) {
            return Sample::withoutGlobalScopes()
                ->where('code', $codigo)->orWhere('slug', $codigo)
                ->first();
        }

        return $this->conResultados()->first();
    }

    /** @return \Illuminate\Support\Collection<int,Sample> */
    private function conResultados()
    {
        return Sample::withoutGlobalScopes()
            ->whereHas('tests', fn ($q) => $q->whereIn('status', ['validated', 'reported']))
            ->withCount(['tests as validadas' => fn ($q) => $q->whereIn('status', ['validated', 'reported'])])
            ->orderByDesc('validadas')
            ->get();
    }
}
