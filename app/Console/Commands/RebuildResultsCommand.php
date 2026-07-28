<?php

namespace App\Console\Commands;

use App\Models\Result;
use App\Models\Worksheet;
use App\Services\Lab\ResultMaterializer;
use Illuminate\Console\Command;

/**
 * Reconstruye la capa `results` desde las hojas de trabajo validadas.
 *
 * Este comando es la prueba de que las dos capas están bien separadas: si
 * `results` no se puede regenerar entero desde lo que cargó el analista,
 * entonces guarda algo que no está en ningún otro lado y deja de ser una capa
 * derivada para pasar a ser un original que hay que cuidar.
 *
 * Cuándo se corre:
 *   · Después de corregir la fórmula de una columna, para que los ensayos ya
 *     cargados reflejen el cálculo bueno.
 *   · Después de declarar a qué parámetro alimenta una columna que estaba sin
 *     declarar (el caso de los nueve gases de cromatografía).
 *   · Después de importar el histórico.
 *
 * Lo que NO toca nunca: `worksheet_values`. Esa es la constancia de lo que hizo
 * el analista y se reconstruye desde ella, no al revés.
 */
class RebuildResultsCommand extends Command
{
    protected $signature = 'lab:rebuild-results
        {--test= : Código de una prueba, para reconstruir solo esa}
        {--from= : Fecha desde (AAAA-MM-DD)}
        {--fresh : Borra los resultados del alcance antes de reconstruir}
        {--dry-run : Informa lo que haría, sin escribir}';

    protected $description = 'Reconstruye la capa de resultados desde las hojas de trabajo validadas';

    public function handle(ResultMaterializer $materializer): int
    {
        $query = Worksheet::query()
            ->withoutGlobalScopes()          // es una tarea de mantenimiento: cruza workspaces
            ->where('status', Worksheet::STATUS_VALIDATED)
            ->with('definition.fields.analyte');

        if ($code = $this->option('test')) {
            $query->whereHas('definition', fn ($q) => $q->where('code', $code));
        }

        if ($from = $this->option('from')) {
            $query->whereDate('run_date', '>=', $from);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->warn('No hay hojas validadas en ese alcance. Nada que reconstruir.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $this->info(($dry ? 'SIMULACIÓN — ' : '') . "Hojas validadas en el alcance: {$total}");

        if ($this->option('fresh') && ! $dry) {
            // Se borra por hoja y no con un TRUNCATE: el alcance puede ser una
            // sola prueba, y vaciar la tabla entera para reconstruir una parte
            // dejaría sin resultados a todo lo que no entra en el alcance.
            $rowIds = (clone $query)->with('rows:id,worksheet_id')->get()
                ->flatMap(fn (Worksheet $w) => $w->rows->pluck('id'));
            $borrados = Result::whereIn('worksheet_row_id', $rowIds)->delete();
            $this->line("  Resultados borrados antes de reconstruir: {$borrados}");
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $escritos = 0;
        $salteados = [];

        (clone $query)->chunkById(100, function ($hojas) use ($materializer, $dry, $bar, &$escritos, &$salteados) {
            foreach ($hojas as $hoja) {
                if (! $dry) {
                    $resultado = $materializer->forWorksheet($hoja);
                    $escritos += $resultado['written'];
                    foreach ($resultado['skipped'] as $s) {
                        $salteados[$s['reason']] = ($salteados[$s['reason']] ?? 0) + 1;
                    }
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($dry) {
            $this->info('Simulación: no se escribió nada.');

            return self::SUCCESS;
        }

        $this->info("Resultados escritos: {$escritos}");

        if ($salteados !== []) {
            $this->newLine();
            $this->warn('Filas que no se pudieron materializar:');
            foreach ($salteados as $motivo => $cantidad) {
                $this->line("  {$motivo}: {$cantidad}");
            }
            $this->newLine();
            $this->line('  sin_equipo: la fila no tiene equipo asignado, así que su');
            $this->line('    resultado no se podría consultar por nadie.');
            $this->line('  sin_columnas_de_resultado: la prueba no declara qué columna');
            $this->line('    alimenta qué parámetro. Se declara en el editor de columnas.');
        }

        return self::SUCCESS;
    }
}
