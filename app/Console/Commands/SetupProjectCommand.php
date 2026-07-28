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
