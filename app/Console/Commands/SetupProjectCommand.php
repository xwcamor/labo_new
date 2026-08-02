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

        // El enlace public/storage. Sin él NINGUNA imagen subida se ve: ni el
        // logo del membrete, ni el sello de acreditación, ni las fotos de
        // perfil. Falla en silencio —un 403 y un recuadro vacío—, así que es
        // exactamente el tipo de paso que no puede quedar en un README.
        Artisan::call('storage:link');

        // Una vista ancha por prueba, generada desde su propia definición. Es
        // la tabla por prueba que el laboratorio pide para leer y exportar, sin
        // el costo de tenerla como tabla física (ver el comando).
        Artisan::call('lab:build-views');

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

        $this->avisarMembreteSinCargar();
    }

    /**
     * El membrete del informe: qué falta cargar para que el papel salga entero.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ POR QUÉ ESTE AVISO EXISTE                                            │
     * └──────────────────────────────────────────────────────────────────────┘
     * El logotipo, el sello del organismo acreditador y el párrafo del
     * certificado son DATOS DEL WORKSPACE, no archivos con nombre fijo: el
     * número de certificado vence y otro laboratorio se acredita con otro
     * organismo. Y sin el dato cargado el informe no dibuja nada — a propósito,
     * porque imprimir el logotipo o el sello de OTRO laboratorio es lo peor que
     * puede hacer este papel.
     *
     * El problema es que ese silencio se lee como una función que falta. El
     * informe sale sin membrete, sin sello y sin el párrafo de la acreditación,
     * y desde afuera no hay forma de distinguir «no está construido» de «no
     * está cargado». Este aviso lo distingue, que es lo mismo que hace el
     * resumen de abajo con las tablas vacías.
     *
     * No se siembra ningún valor de fábrica: no hay ninguno correcto. Un
     * logotipo de muestra sería el de otra empresa y un número de certificado
     * inventado convertiría el informe en una declaración falsa.
     */
    private function avisarMembreteSinCargar(): void
    {
        $tenant = \App\Models\Tenant::query()->orderBy('id')->first();

        if (! $tenant) {
            return;
        }

        $faltan = array_keys(array_filter([
            'el logotipo de la empresa (arriba a la izquierda de cada hoja)' => blank($tenant->logo),
            'el sello del organismo acreditador (arriba a la derecha, solo en las hojas acreditadas)' => blank($tenant->accreditation_logo),
            'el párrafo del certificado (al pie de las hojas acreditadas)' => blank($tenant->accreditation_note),
        ]));

        if ($faltan === []) {
            return;
        }

        $this->line('');
        $this->warn('  El membrete del informe está sin cargar. Falta:');

        foreach ($faltan as $que) {
            $this->line("    · {$que}");
        }

        $this->line('');
        $this->line('  Los informes salen SIN eso hasta que se cargue en <fg=green>/workspace</> (Mi workspace).');
        $this->line('  No se siembra un valor de fábrica a propósito: el logotipo y el sello de');
        $this->line('  otro laboratorio no pueden salir en un papel que firma este.');
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
            // Sin al menos una persona acá, el alta de recepciones no se puede
            // completar (el autorizador del ingreso es obligatorio).
            ['Personal que autoriza',    $cuenta('entry_authorizers'), '/business_management/entry_authorizers'],
            ['Recepciones',              $cuenta('receptions'),       '/lab_management/receptions'],
            ['Muestras',                 $cuenta('samples'),          ''],
            ['Pruebas pedidas',          $cuenta('sample_tests'),     ''],
            ['Hojas de trabajo',         $cuenta('worksheets'),       '/lab_management/worksheets'],
            ['Normas',                   $cuenta('standards'),        ''],
            ['Cuadros de límites',       $cuenta('spec_sets'),        ''],
            ['Resultados',               $cuenta('results'),          ''],
            ['   dentro de norma',       \Illuminate\Support\Facades\Schema::hasTable('results')
                ? \Illuminate\Support\Facades\DB::table('results')->where('spec_status', 'in_spec')->count() : 0, ''],
            ['   fuera de norma',        \Illuminate\Support\Facades\Schema::hasTable('results')
                ? \Illuminate\Support\Facades\DB::table('results')->where('spec_status', 'out_of_spec')->count() : 0, ''],
            ['   sin criterio',          \Illuminate\Support\Facades\Schema::hasTable('results')
                ? \Illuminate\Support\Facades\DB::table('results')->whereNull('spec_status')->count() : 0, ''],
            // Las cuatro listas que llenan el formulario del informe. Van en el
            // resumen porque es el único lugar donde su ausencia se nota a
            // tiempo: sin sembrar, el formulario abre igual y los cuatro
            // desplegables salen vacíos, que se lee como «todavía no cargaron
            // nada» y no como «falta correr el seeder».
            ['Listas del informe',       $cuenta('report_catalogs'),  '/lab_management/report_catalogs'],
            // El informe es el PRODUCTO del laboratorio y lo que el seed base
            // existe para poder abrir. No estaba en el resumen, así que había que
            // ir a buscarlo a la ficha de la entrega para saber si se había
            // emitido.
            ['Informes emitidos',        \Illuminate\Support\Facades\Schema::hasTable('sample_reports')
                ? \Illuminate\Support\Facades\DB::table('sample_reports')
                    ->where('status', 'issued')->whereNull('deleted_at')->count() : 0,
                '/lab_management/reports'],
            ['Cartas de control',        $cuenta('qc_charts'),        '/lab_management/qc_charts'],
        ];

        $this->line('');
        $this->line('  <fg=cyan>El laboratorio quedó cargado así:</>');
        $this->line('');

        foreach ($filas as [$nombre, $total, $ruta]) {
            $color = $total > 0 ? "green" : (str_starts_with($nombre, " ") ? "yellow" : "red");
            // Relleno con mb_str_pad: sprintf cuenta BYTES, y con "Parámetros"
            // o "Cámara" la columna queda corrida un carácter por cada acento.
            $etiqueta = $nombre . str_repeat(' ', max(0, 26 - mb_strlen($nombre)));
            $this->line(sprintf('    %s <fg=%s>%6d</>  %s', $etiqueta, $color, $total, $ruta));
        }

        // Qué registro es, con nombre y apellido: el seed base deja UNO y la
        // gracia es poder abrir su informe sin buscarlo.
        if (\Illuminate\Support\Facades\Schema::hasTable('sample_reports')) {
            $informe = \Illuminate\Support\Facades\DB::table('sample_reports')
                ->join('samples', 'samples.id', '=', 'sample_reports.sample_id')
                ->join('receptions', 'receptions.id', '=', 'samples.reception_id')
                ->whereNull('sample_reports.deleted_at')
                ->orderBy('sample_reports.id')
                ->first([
                    'sample_reports.code as informe', 'sample_reports.slug',
                    'samples.code as muestra', 'receptions.code as entrega',
                ]);

            if ($informe) {
                $this->line('');
                $this->line('  <fg=cyan>El registro de demostración:</>');
                $this->line('');
                $this->line("    Entrega {$informe->entrega} · muestra {$informe->muestra} · informe <fg=green>{$informe->informe}</>");
                $this->line('    Las 29 pruebas del catálogo, cargadas y validadas.');
                $this->line('');
                $this->line("    PDF moderno   <fg=green>/lab_management/reports/{$informe->slug}/pdf</>");
                $this->line("    PDF clásico   <fg=green>/lab_management/reports/{$informe->slug}/pdf-clasico</>");
            }
        }

        $this->line('');
        $this->line('  Los equipos y las mediciones son de DEMOSTRACIÓN. Para sacarlos sin');
        $this->line('  perder lo demás: <fg=green>php artisan lab:demo --limpiar</>');
        $this->line('  Para la demostración GRANDE (6 equipos, 37 muestras, 31 informes):');
        $this->line('  <fg=green>php artisan db:seed --class=LabDemoWorksheetsSeeder</>');
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
