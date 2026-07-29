<?php

namespace App\Console\Commands;

use App\Services\Lab\WorksheetService;
use Illuminate\Console\Command;

/**
 * Bloquea las hojas de bancada que ya cumplieron su antigüedad.
 *
 * Corre solo, una vez por día. No hay botón: es el punto — el candado lo pone
 * el sistema y no depende de que alguien se acuerde. Lo que sí es manual, y
 * queda auditado, es DESBLOQUEAR.
 */
class LockAgedWorksheetsCommand extends Command
{
    protected $signature = 'worksheets:auto-lock {--months= : Sobrescribe el ajuste worksheets.auto_lock_months}';

    protected $description = 'Bloquea las hojas de bancada más viejas que el plazo configurado.';

    public function handle(WorksheetService $service): int
    {
        $meses = $this->option('months');
        $cuantas = $service->autoLockAged($meses === null ? null : (int) $meses);

        $this->info("Hojas bloqueadas: {$cuantas}");

        return self::SUCCESS;
    }
}
