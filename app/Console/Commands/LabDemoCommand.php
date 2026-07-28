<?php

namespace App\Console\Commands;

use App\Models\Equipment;
use App\Models\QcChart;
use App\Models\Reception;
use App\Models\Worksheet;
use Database\Seeders\LabDemoWorksheetsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Los datos de demostración del laboratorio: los pone y los saca.
 *
 * Existe porque el seed completo deja el sistema con equipos, hojas de trabajo
 * y mediciones INVENTADAS, y eso está bien para evaluarlo y está mal para
 * empezar a trabajar de verdad. La forma de sacarlos no puede ser `migrate:fresh`:
 * eso se lleva puestos también los clientes reales, las pruebas, las fórmulas y
 * los instrumentos, que sí son datos del laboratorio.
 *
 *     php artisan lab:demo --limpiar    borra la demostración, deja lo real
 *     php artisan lab:demo              la vuelve a sembrar
 *
 * Todo lo que crea el sembrador lleva la marca DEMO —en `external_ref` de los
 * equipos, en las notas de las hojas, en el lote de la carta de control—, y esa
 * marca es justamente lo que hace que se pueda deshacer sin tocar nada más.
 */
class LabDemoCommand extends Command
{
    protected $signature = 'lab:demo
        {--limpiar : Borra los datos de demostración en vez de sembrarlos}';

    protected $description = 'Siembra o borra los datos de demostración del laboratorio';

    public function handle(): int
    {
        if (! $this->option('limpiar')) {
            Artisan::call('db:seed', ['--class' => LabDemoWorksheetsSeeder::class, '--force' => true]);
            $this->line(Artisan::output());

            return self::SUCCESS;
        }

        $marca = LabDemoWorksheetsSeeder::MARCA;

        $hojas = Worksheet::withoutGlobalScopes()->where('notes', 'like', $marca . '%')->pluck('id');
        $equipos = Equipment::withoutGlobalScopes()->where('external_ref', 'like', $marca . '-%')->pluck('id');
        $cartas = QcChart::withoutGlobalScopes()->where('control_lot', 'like', $marca . '-%')->pluck('id');
        $recepciones = Reception::withoutGlobalScopes()->where('code', 'like', $marca . '-REM-%')->pluck('id');

        if ($hojas->isEmpty() && $equipos->isEmpty() && $cartas->isEmpty() && $recepciones->isEmpty()) {
            $this->info('No hay datos de demostración. No hay nada que borrar.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Se van a borrar %d recepciones, %d hojas de trabajo, %d equipos y %d cartas de control de demostración.',
            $recepciones->count(),
            $hojas->count(),
            $equipos->count(),
            $cartas->count()
        ));

        if ($this->input->isInteractive() && ! $this->confirm('¿Continuar?', true)) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($hojas, $equipos, $cartas, $recepciones) {
            // El borrado en cascada del esquema se lleva las filas, los valores,
            // los resultados y los puntos de control colgados de cada hoja; y
            // las muestras y las pruebas pedidas colgadas de cada recepción.
            Worksheet::withoutGlobalScopes()->whereIn('id', $hojas)->forceDelete();
            QcChart::withoutGlobalScopes()->whereIn('id', $cartas)->forceDelete();
            Reception::withoutGlobalScopes()->whereIn('id', $recepciones)->forceDelete();
            Equipment::withoutGlobalScopes()->whereIn('id', $equipos)->forceDelete();
        });

        $this->info('Datos de demostración borrados. Las pruebas, las fórmulas, los instrumentos y los clientes quedan como estaban.');

        return self::SUCCESS;
    }
}
