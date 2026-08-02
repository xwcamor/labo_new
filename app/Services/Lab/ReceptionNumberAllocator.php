<?php

namespace App\Services\Lab;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * El correlativo de la ENTREGA.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ES UNA CLASE APARTE Y NO UN PARÁMETRO DE LA OTRA                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * `SampleNumberAllocator` hace lo mismo sobre `sample_counters`, y lo primero
 * que se piensa es generalizarla con un parámetro de tabla. No se hizo: esa
 * clase resuelve una carrera —el sistema anterior leía el último número con un
 * SELECT y lo escribía después, así que dos altas simultáneas emitían el mismo—
 * y está cubierta por pruebas de concurrencia. Meterle un parámetro para
 * reutilizarla acá obliga a re-probar las dos numeraciones cada vez que se toca
 * cualquiera de las dos.
 *
 * Son sesenta líneas duplicadas contra el riesgo de romper la numeración de las
 * muestras, que es lo que el laboratorio factura. Se duplica.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ SE EMITE AL REGISTRAR, NO AL CONFIRMAR                                   │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Al revés que los correlativos de las MUESTRAS, que se emiten recién al
 * confirmar porque hasta ahí la entrega es un borrador que se corrige sin
 * quemar números. Acá el número identifica a la entrega desde que existe: es lo
 * que se escribe en la caja de frascos y lo que va en la URL. Un borrador sin
 * nombre no se puede citar por teléfono.
 */
class ReceptionNumberAllocator
{
    /** REC-2026-0042. */
    public static function format(int $year, int $number): string
    {
        return sprintf('REC-%d-%04d', $year, $number);
    }

    /**
     * Toma el próximo número del año y lo devuelve.
     *
     * Tiene que llamarse DENTRO de la transacción que crea la entrega: si la
     * creación falla, la reserva se deshace con ella y no queda un hueco.
     *
     * @throws RuntimeException
     */
    public function next(?int $tenantId, int $year): int
    {
        if (! DB::transactionLevel()) {
            throw new RuntimeException(
                'El correlativo de la entrega se reserva dentro de la transacción que la crea. '
                . 'Reservarlo suelto deja un hueco en la numeración si la creación falla.'
            );
        }

        $this->ensureCounter($tenantId, $year);

        if (DB::getDriverName() === 'pgsql') {
            $fila = DB::selectOne(
                'UPDATE reception_counters SET last_number = last_number + 1, updated_at = ? '
                . 'WHERE tenant_id IS NOT DISTINCT FROM ? AND year = ? RETURNING last_number',
                [now(), $tenantId, $year]
            );

            if ($fila === null) {
                throw new RuntimeException("No se pudo reservar el correlativo de entrega del año {$year}.");
            }

            return (int) $fila->last_number;
        }

        // SQLite (las pruebas) no tiene RETURNING por esta vía: se bloquea la
        // fila, se lee y se escribe dentro de la misma transacción, que es
        // justamente el bloqueo que al sistema anterior le faltaba.
        $fila = DB::table('reception_counters')
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        $nuevo = (int) ($fila->last_number ?? 0) + 1;

        DB::table('reception_counters')
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->update(['last_number' => $nuevo, 'updated_at' => now()]);

        return $nuevo;
    }

    private function ensureCounter(?int $tenantId, int $year): void
    {
        $existe = DB::table('reception_counters')
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->exists();

        if ($existe) {
            return;
        }

        // `insertOrIgnore` y no `insert`: dos entregas simultáneas del primer
        // día del año llegan las dos hasta acá, y la segunda tiene que
        // encontrarse la fila creada, no un error de clave duplicada.
        DB::table('reception_counters')->insertOrIgnore([
            'tenant_id'   => $tenantId,
            'year'        => $year,
            'last_number' => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
}
