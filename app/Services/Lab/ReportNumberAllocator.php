<?php

namespace App\Services\Lab;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Emite los correlativos de informe: REP-LAB-2026-0800.
 *
 * Es el mismo mecanismo que `SampleNumberAllocator` y por los mismos motivos.
 * El del sistema anterior era:
 *
 *     last = RemReport.where(deleted: 0, num_report_year: año)
 *                     .order(:num_report_small).last
 *     self.num_report_small = last.nil? ? 1 : last.num_report_small + 1
 *
 * Sin bloqueo —dos emisiones a la vez se llevan el mismo número— y con el filtro
 * `deleted: 0`, que devolvía a la fila el número del último informe dado de baja.
 * Reemitirlo significa que dos clientes tienen papeles con el mismo código y el
 * portal de verificación no puede decir cuál es cuál. El repositorio viejo trae
 * un archivo dedicado a buscar reportes duplicados: ya había pasado.
 *
 * Acá el contador nunca retrocede: un número emitido queda quemado aunque su
 * informe se dé de baja.
 */
class ReportNumberAllocator
{
    /**
     * Reserva el próximo correlativo del año.
     *
     * Tiene que llamarse DENTRO de la transacción que crea el informe: si la
     * creación falla, la reserva se deshace con ella y no queda un hueco.
     */
    public function reserve(?int $tenantId, int $year): int
    {
        if (! DB::transactionLevel()) {
            throw new RuntimeException(
                'El correlativo del informe se reserva dentro de la transacción que lo crea.'
            );
        }

        $this->ensureCounter($tenantId, $year);

        if (DB::getDriverName() === 'pgsql') {
            $fila = DB::selectOne(
                'UPDATE report_counters SET last_number = last_number + 1, updated_at = ? '
                . 'WHERE tenant_id IS NOT DISTINCT FROM ? AND year = ? RETURNING last_number',
                [now(), $tenantId, $year]
            );

            if ($fila === null) {
                throw new RuntimeException("No se pudo reservar el correlativo de informe del año {$year}.");
            }

            return (int) $fila->last_number;
        }

        // SQLite (las pruebas): bloquear, leer y escribir dentro de la misma
        // transacción — el bloqueo que al sistema anterior le faltaba.
        $fila = DB::table('report_counters')
            ->where('tenant_id', $tenantId)->where('year', $year)
            ->lockForUpdate()->first();

        $nuevo = (int) ($fila->last_number ?? 0) + 1;

        DB::table('report_counters')
            ->where('tenant_id', $tenantId)->where('year', $year)
            ->update(['last_number' => $nuevo, 'updated_at' => now()]);

        return $nuevo;
    }

    /** Cuál sería el próximo, solo para mostrarlo. El real es el de `reserve()`. */
    public function peek(?int $tenantId, int $year): int
    {
        return (int) DB::table('report_counters')
            ->where('tenant_id', $tenantId)->where('year', $year)
            ->value('last_number') + 1;
    }

    private function ensureCounter(?int $tenantId, int $year): void
    {
        $existe = DB::table('report_counters')
            ->where('tenant_id', $tenantId)->where('year', $year)->exists();

        if ($existe) {
            return;
        }

        DB::table('report_counters')->insertOrIgnore([
            'tenant_id'   => $tenantId,
            'year'        => $year,
            'last_number' => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
}
