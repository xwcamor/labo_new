<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Schema;

/**
 * Detecta y repara el desfase de migraciones de la fase 1.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ EXISTE ESTE COMANDO                                              │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Durante la fase 1, y solo durante la fase 1, las migraciones se editan EN SU
 * LUGAR: como todavía no hay ningún despliegue, corregir una migración sin
 * publicar es más limpio que arrastrar una migración de alteración por cada
 * ajuste.
 *
 * Eso tiene un costo, y este comando existe porque el costo se cobró: quien ya
 * había migrado con una versión anterior se queda con TABLAS HUÉRFANAS —creadas
 * por una migración que después se borró o se fusionó con otra— y con el
 * registro de esa migración en la tabla `migrations`. La próxima corrida
 * intenta crear una tabla que ya existe y muestra un error de SQL que no dice
 * qué hacer:
 *
 *     SQLSTATE[42P07]: Duplicate table: la relación «analytes» ya existe
 *
 * El caso concreto es `analytes`: se creaba con `make:module` en su propia
 * migración y después pasó a crearse dentro de `create_test_definitions_tables`,
 * con otra forma (unidad, decimales, grupo, dirección). La tabla vieja no sirve:
 * le faltan la mitad de las columnas.
 *
 * La alternativa es `php artisan migrate:fresh --seed`, que funciona y borra
 * todo. Este comando hace lo mismo conservando los datos que no tienen nada que
 * ver, que en una base de trabajo son casi todos.
 *
 * A partir del primer despliegue real este comando deja de tener sentido: de ahí
 * en adelante, migración nueva siempre, y el desfase no puede ocurrir.
 */
class LabDoctorCommand extends Command
{
    protected $signature = 'lab:doctor
        {--fix : Aplica la reparación. Sin esta opción solo informa}';

    protected $description = 'Detecta y repara el desfase de migraciones de la fase 1';

    /**
     * Tablas que quedaron huérfanas de una migración borrada, con la migración
     * que las creaba y la que las crea ahora.
     *
     * @var array<int,array{tabla:string,migracion_vieja:string,migracion_nueva:string,motivo:string}>
     */
    private const HUERFANAS = [
        [
            'tabla'           => 'analytes',
            'migracion_vieja' => '2026_07_28_045938_create_analytes_table',
            'migracion_nueva' => '2026_07_28_090000_create_test_definitions_tables',
            'motivo'          => 'La tabla vieja es el catálogo que generaba el scaffold (nombre y código). '
                               . 'La nueva agrega unidad, decimales, grupo y dirección, que es lo que usa el motor.',
        ],
    ];

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $problemas = $this->detectar();

        if ($problemas === []) {
            $this->info('La base está alineada con las migraciones. No hay nada que reparar.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Se encontró desfase entre la base y las migraciones:');
        $this->newLine();

        foreach ($problemas as $p) {
            $this->line("  <fg=yellow>{$p['tabla']}</> existe, pero la migración que la crea todavía no corrió.");
            $this->line("    La creó: {$p['migracion_vieja']} (esa migración ya no existe)");
            $this->line("    La crea: {$p['migracion_nueva']}");
            $this->line('    ' . wordwrap($p['motivo'], 88, "\n    "));
            $this->line("    Filas que se perderían: <fg=yellow>{$p['filas']}</>");
            $this->newLine();
        }

        if (! $fix) {
            $this->line('Para repararlo, conservando el resto de los datos:');
            $this->line('  <fg=green>php artisan lab:doctor --fix</>');
            $this->line('  <fg=green>php artisan migrate</>');
            $this->newLine();
            $this->line('La alternativa, que borra TODA la base local y la deja como nueva:');
            $this->line('  <fg=green>php artisan migrate:fresh --seed</>');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->repararlo($problemas);

        return self::SUCCESS;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function detectar(): array
    {
        if (! Schema::hasTable('migrations')) {
            return [];
        }

        $corridas = DB::table('migrations')->pluck('migration')->all();
        $problemas = [];

        foreach (self::HUERFANAS as $h) {
            // El desfase es exactamente esto: la tabla está, pero la migración
            // que hoy la crea no corrió. Si las dos condiciones no se dan
            // juntas, la base está bien y no hay nada que tocar.
            if (! Schema::hasTable($h['tabla'])) {
                continue;
            }

            if (in_array($h['migracion_nueva'], $corridas, true)) {
                continue;
            }

            $problemas[] = $h + [
                'filas'   => DB::table($h['tabla'])->count(),
                'huella'  => in_array($h['migracion_vieja'], $corridas, true),
            ];
        }

        return $problemas;
    }

    /**
     * @param array<int,array<string,mixed>> $problemas
     */
    private function repararlo(array $problemas): void
    {
        foreach ($problemas as $p) {
            if ($p['filas'] > 0 && ! $this->confirmarPerdida($p)) {
                $this->line("  Se omite {$p['tabla']}.");
                continue;
            }

            DB::transaction(function () use ($p) {
                Schema::dropIfExists($p['tabla']);
                $this->line("  Eliminada la tabla huérfana <fg=yellow>{$p['tabla']}</>.");

                if ($p['huella']) {
                    DB::table('migrations')->where('migration', $p['migracion_vieja'])->delete();
                    $this->line("  Eliminado el registro de {$p['migracion_vieja']}.");
                }
            });
        }

        $this->newLine();
        $this->info('Reparado. La secuencia completa, en este orden:');
        $this->newLine();
        $this->line('  <fg=green>php artisan migrate</>');
        $this->line('  <fg=green>php artisan db:seed --class=LabAnalytesSeeder</>');
        $this->line('     los 36 parámetros medibles del laboratorio');
        $this->line('  <fg=green>php artisan import:legacy-tests docs/migracion/esquema/catalogos-definiciones.sql</>');
        $this->line('     las 29 pruebas reales del sistema anterior, con sus columnas');
        $this->line('  <fg=green>php artisan lab:map-analytes</>');
        $this->line('     enlaza cada columna de resultado con su parámetro');
        $this->newLine();
        $this->line('El orden importa: enlazar necesita que existan las dos puntas.');
    }

    /**
     * @param array<string,mixed> $p
     */
    private function confirmarPerdida(array $p): bool
    {
        $this->warn("  La tabla {$p['tabla']} tiene {$p['filas']} filas y se va a eliminar.");
        $this->line('  Se vuelven a sembrar con LabAnalytesSeeder, que trae los 36 parámetros');
        $this->line('  reales del laboratorio. Lo que se pierde es lo que se haya cargado a mano.');

        return $this->confirm('  ¿Continuar?', true);
    }
}
