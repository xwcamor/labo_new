<?php

namespace App\Console\Commands;

use App\Models\Reception;
use App\Models\SampleReport;
use App\Models\Worksheet;
use Database\Seeders\LabFullReportSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * La muestra con LAS 29 PRUEBAS, para ver el informe completo.
 *
 * El sembrador de demostración carga cinco pruebas: alcanza para la bancada y
 * la carta de control, no para juzgar el informe. El informe cambia de forma con
 * el volumen —cuántas páginas salen, cómo se agrupan las familias, qué dice el
 * diagnóstico, dónde cae el sello de acreditación— y con cinco pruebas nada de
 * eso se ve.
 *
 *   php artisan lab:full-report              siembra y deja el informe en borrador
 *   php artisan lab:full-report --limpiar    borra todo lo que sembró
 *
 * Con `--pdf` deja además los dos PDF (moderno y clásico) en
 * `storage/app/comparacion`, que es lo que se mira.
 */
class LabFullReportCommand extends Command
{
    protected $signature = 'lab:full-report
        {--limpiar : Borra la recepción, las hojas y el informe que sembró}
        {--pdf : Deja los dos PDF en storage/app/comparacion}';

    protected $description = 'Una muestra con las 29 pruebas cargadas, para ver el informe completo';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Esto no se corre en producción.');

            return self::FAILURE;
        }

        if ($this->option('limpiar')) {
            return $this->limpiar();
        }

        $this->call('db:seed', ['--class' => LabFullReportSeeder::class, '--no-interaction' => true]);

        $muestra = Reception::withoutGlobalScopes()
            ->where('code', 'FULL-REM-01')
            ->first()?->samples()->first();

        if (! $muestra) {
            return self::FAILURE;
        }

        // El informe en BORRADOR, con su autodiagnóstico compuesto: es el estado
        // en el que el laboratorio lo revisa antes de emitirlo.
        $informe = SampleReport::withoutGlobalScopes()
            ->where('sample_id', $muestra->id)
            ->first();

        if (! $informe) {
            \Illuminate\Support\Facades\Auth::login(
                \App\Models\User::withoutGlobalScopes()->where('tenant_id', 1)->first()
            );

            $informe = app(\App\Services\Lab\SampleReportService::class)
                ->create($muestra, [], \Illuminate\Support\Facades\Auth::id());
        }

        app(\App\Services\Lab\DiagnosisTextService::class)->generate($muestra);

        $this->newLine();
        $this->info('Muestra:  ' . $muestra->code);
        $this->line('Informe:  ' . $informe->code . ' (borrador)');
        $this->line('Pantalla: /es/lab_management/receptions/'
            . $muestra->reception->slug . '  → pestaña Informes');

        if ($this->option('pdf')) {
            $this->newLine();
            $this->call('report:compare', ['sample' => $muestra->code]);
        }

        return self::SUCCESS;
    }

    private function limpiar(): int
    {
        $recepcion = Reception::withoutGlobalScopes()->where('code', 'FULL-REM-01')->first();

        if (! $recepcion) {
            $this->info('No hay nada que limpiar.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($recepcion) {
            $muestras = $recepcion->samples()->pluck('id');

            // Las hojas van con su marca de agua: se borran por ahí y no por la
            // muestra, porque una hoja agrupa varias muestras y en otra
            // instalación podría tener filas ajenas a esta demostración.
            $hojas = Worksheet::withoutGlobalScopes()->where('notes', 'like', LabFullReportSeeder::MARCA . '%')->get();

            foreach ($hojas as $hoja) {
                $hoja->rows()->each(function ($fila) {
                    $fila->values()->delete();
                    $fila->delete();
                });
                $hoja->forceDelete();
            }

            DB::table('results')->whereIn('sample_id', $muestras)->delete();
            SampleReport::withoutGlobalScopes()->whereIn('sample_id', $muestras)->forceDelete();
            DB::table('sample_tests')->whereIn('sample_id', $muestras)->delete();
            DB::table('sample_diagnoses')->whereIn('sample_id', $muestras)->delete();
            DB::table('samples')->whereIn('id', $muestras)->delete();
            $recepcion->forceDelete();
        });

        $this->info('Listo. Las pruebas, los instrumentos y los clientes quedan como estaban.');

        return self::SUCCESS;
    }
}
