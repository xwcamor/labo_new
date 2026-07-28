<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use PDO;

#[AsCommand(
    name: 'setup:project',
    description: 'Initialize the system: recreate the database (or refresh tables), run migrations and seeders.'
)]
class SetupProjectCommand extends Command
{
    public function handle(): int
    {
        // Hard guard: this command is destructive (drops all tables).
        // Refuse to run on production no matter what. Complementa el candado
        // global DB::prohibitDestructiveCommands (AppServiceProvider): ese
        // bloquea el migrate:fresh interno, pero el DROP DATABASE crudo de
        // MySQL (PDO) no pasa por artisan — este guard lo corta antes.
        if (app()->environment('production')) {
            $this->error('Refusing to run setup:project on APP_ENV=production. Use [php artisan migrate] for incremental changes.');
            return self::FAILURE;
        }

        $connection = Config::get('database.default');
        $cfg        = Config::get("database.connections.{$connection}");
        $dbName     = $cfg['database'] ?? null;

        if (! $dbName) {
            $this->error("No database configured for connection [{$connection}].");
            return self::FAILURE;
        }

        $env = app()->environment();
        $this->warn("Environment: [{$env}] | Driver: [{$connection}] | DB: [{$dbName}]");
        $this->warn("This will DROP ALL TABLES and re-run migrations + seeders.");

        // if (! $this->confirm("Are you sure you want to continue?")) {
        //     $this->warn('Task cancelled.');
        //     return self::SUCCESS;
        // }

        match ($connection) {
            'mysql' => $this->recreateMysql($cfg),
            'pgsql' => $this->info("Postgres detected — skipping DROP DATABASE (requires superuser). Using migrate:fresh instead."),
            default => $this->warn("Driver [{$connection}] not specifically handled — relying on migrate:fresh."),
        };

        $this->info("Running migrate:fresh --seed ...");
        Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);
        $this->info(Artisan::output());

        $this->verifyBaseData();
        $this->resumenDelLaboratorio();

        $this->info("Project successfully initialized.");
        return self::SUCCESS;
    }

    /**
     * Verifica que los datos base quedaron cargados. Fase 2: sumar el chequeo
     * de spec_sets / spec_limits (el equivalente al motor de reglas).
     */
    private function verifyBaseData(): void
    {
        $this->line('');
        $ent = \App\Models\Plan::findBySlug('enterprise');
        $hasSharing = $ent?->hasFeature('report_sharing') ?? false;
        $this->line('  enterprise.report_sharing: ' . ($hasSharing ? 'yes' : 'NO'));
        if (! $hasSharing) {
            $this->error('  report_sharing missing in plans. Run: php artisan db:seed --class=PlansSeeder --force');
        }
    }

    /**
     * Qué quedó cargado y dónde se ve.
     *
     * Hasta ahora el comando terminaba diciendo "Project successfully
     * initialized" y nada más, y las tablas del laboratorio quedaban vacías: no
     * había forma de distinguir un sistema recién sembrado de un sistema roto.
     * Este resumen es lo que responde esa pregunta sin abrir el navegador — y si
     * alguno de estos números sale en cero, ahí está el problema.
     */
    private function resumenDelLaboratorio(): void
    {
        $cuenta = fn (string $tabla) => \Illuminate\Support\Facades\Schema::hasTable($tabla)
            ? \Illuminate\Support\Facades\DB::table($tabla)->count()
            : 0;

        $filas = [
            ['Pruebas de muestras',      $cuenta('test_definitions'), '/lab_management/test_definitions'],
            ['Columnas de las pruebas',  $cuenta('test_fields'),      ''],
            ['Columnas calculadas',      \Illuminate\Support\Facades\Schema::hasTable('test_fields')
                ? \Illuminate\Support\Facades\DB::table('test_fields')->whereNotNull('formula')->count() : 0, ''],
            ['Parámetros medibles',      $cuenta('analytes'),         ''],
            ['Instrumentos',             $cuenta('instruments'),      '/business_management/instruments'],
            ['Clientes',                 $cuenta('customers'),        '/business_management/customers'],
            ['Equipos (demostración)',   $cuenta('equipment'),        '/business_management/equipment'],
            ['Hojas de trabajo',         $cuenta('worksheets'),       '/lab_management/worksheets'],
            ['Resultados',               $cuenta('results'),          ''],
            ['Cartas de control',        $cuenta('qc_charts'),        '/lab_management/qc_charts'],
        ];

        $this->line('');
        $this->line('  <fg=cyan>El laboratorio quedó cargado así:</>');
        $this->line('');

        foreach ($filas as [$nombre, $total, $ruta]) {
            $color = $total > 0 ? 'green' : 'red';
            // Relleno con mb_str_pad: sprintf cuenta BYTES, y con "Parámetros"
            // o "Cámara" la columna queda corrida un carácter por cada acento.
            $etiqueta = $nombre . str_repeat(' ', max(0, 26 - mb_strlen($nombre)));
            $this->line(sprintf('    %s <fg=%s>%6d</>  %s', $etiqueta, $color, $total, $ruta));
        }

        $this->line('');
        $this->line('  Los equipos y las mediciones son de DEMOSTRACIÓN. Para sacarlos sin');
        $this->line('  perder lo demás: <fg=green>php artisan lab:demo --limpiar</>');
    }

    private function recreateMysql(array $cfg): void
    {
        try {
            $pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']}", $cfg['username'], $cfg['password']);
            $pdo->exec("DROP DATABASE IF EXISTS `{$cfg['database']}`;");
            $this->info("Database `{$cfg['database']}` dropped.");
            $pdo->exec("CREATE DATABASE `{$cfg['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $this->info("Database `{$cfg['database']}` created.");
        } catch (\Exception $e) {
            $this->error("Error recreating MySQL database: " . $e->getMessage());
        }
    }
}
