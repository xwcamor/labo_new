<?php

namespace App\Services\Lab;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Emite los correlativos de muestra: 2026-0695.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ QUÉ HACÍA EL SISTEMA ANTERIOR, Y POR QUÉ NO SERVÍA                       │
 * └──────────────────────────────────────────────────────────────────────────┘
 *     @num_correlatives.times do |i|
 *       @last = RemCorrelative.where("deleted = 0 AND year_test = ?", año)
 *                             .order(:num_test).last
 *       @max  = @last.nil? ? 1 : @last.num_test.to_i + 1
 *       RemCorrelative.create(year_test: año, num_test: @max)
 *     end
 *
 * Cuatro problemas, los cuatro reales:
 *
 *   1. SIN BLOQUEO. Dos recepciones confirmadas a la vez leen el mismo último
 *      número y emiten el mismo correlativo. La validación de unicidad estaba
 *      COMENTADA en el modelo, y su alcance era la remisión, no el año.
 *   2. REUTILIZABA NÚMEROS. El filtro `deleted = 0` hacía que, si el correlativo
 *      más alto del año se daba de baja, el siguiente lote lo volviera a emitir
 *      — ahora para otra muestra. En un laboratorio eso es un resultado
 *      atribuido al equipo equivocado.
 *   3. CONSULTABA DENTRO DEL BUCLE. Reservar 40 correlativos eran 40 consultas
 *      de búsqueda del último más 40 inserciones.
 *   4. ORDENABA POR UNA COLUMNA QUE DESPUÉS CONVERTÍA (`.to_i`), señal de que
 *      el propio autor no confiaba en el tipo.
 *
 * Que esto pasó en producción no es una suposición: el repositorio del sistema
 * anterior tiene dos archivos dedicados a buscar correlativos duplicados.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ CÓMO SE HACE ACÁ                                                         │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Una fila contadora por (workspace, año). Reservar N números es UN update con
 * la fila bloqueada, que devuelve el tope nuevo:
 *
 *     UPDATE sample_counters SET last_number = last_number + :n ... RETURNING
 *
 * El bloqueo lo pone el propio UPDATE: mientras dura la transacción, cualquier
 * otra reserva del mismo (workspace, año) espera. No hay ventana entre leer y
 * escribir porque no hay lectura previa.
 *
 * Y el contador NUNCA retrocede. Un correlativo emitido queda quemado aunque su
 * muestra se dé de baja. Eso es lo que garantiza que un número identifique
 * siempre a la misma muestra, que es la única razón por la que un número de
 * muestra sirve para algo.
 */
class SampleNumberAllocator
{
    /**
     * Reserva un bloque de correlativos y devuelve los números.
     *
     * Tiene que llamarse DENTRO de una transacción: quien reserva es quien crea
     * las muestras, y si esa creación falla la reserva se deshace con ella. Si
     * se llamara suelta, un error posterior dejaría un hueco en la numeración.
     *
     * @param  int $cuantos cuántos correlativos se necesitan
     * @return array<int,int> los números, en orden
     *
     * @throws RuntimeException
     */
    public function reserve(?int $tenantId, int $year, int $cuantos): array
    {
        if ($cuantos < 1) {
            return [];
        }

        if (! DB::transactionLevel()) {
            throw new RuntimeException(
                'Los correlativos se reservan dentro de la transacción que crea las muestras. '
                . 'Reservarlos sueltos deja huecos en la numeración si la creación falla.'
            );
        }

        $ultimo = $this->bumpCounter($tenantId, $year, $cuantos);
        $primero = $ultimo - $cuantos + 1;

        return range($primero, $ultimo);
    }

    /**
     * Cuál sería el próximo número, sin reservarlo.
     *
     * Es solo para mostrarlo en la pantalla de recepción ("se emitirán del 0696
     * al 0715"). NO se usa para emitir: entre que se muestra y se confirma
     * pueden entrar otras recepciones, y el número real es el que devuelve
     * `reserve()`.
     */
    public function peek(?int $tenantId, int $year): int
    {
        $ultimo = DB::table('sample_counters')
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->value('last_number');

        return (int) $ultimo + 1;
    }

    /**
     * Suma al contador y devuelve el tope nuevo, creando la fila del año si es
     * la primera muestra del ejercicio.
     */
    private function bumpCounter(?int $tenantId, int $year, int $cuantos): int
    {
        $this->ensureCounter($tenantId, $year);

        if (DB::getDriverName() === 'pgsql') {
            $fila = DB::selectOne(
                'UPDATE sample_counters SET last_number = last_number + ?, updated_at = ? '
                . 'WHERE tenant_id IS NOT DISTINCT FROM ? AND year = ? RETURNING last_number',
                [$cuantos, now(), $tenantId, $year]
            );

            if ($fila === null) {
                throw new RuntimeException("No se pudo reservar el correlativo del año {$year}.");
            }

            return (int) $fila->last_number;
        }

        // SQLite (las pruebas) no tiene RETURNING en update por esta vía. Se
        // bloquea la fila, se lee y se escribe dentro de la misma transacción,
        // que es lo que `lockForUpdate` garantiza — y es justamente el bloqueo
        // que al sistema anterior le faltaba.
        $fila = DB::table('sample_counters')
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        $nuevo = (int) ($fila->last_number ?? 0) + $cuantos;

        DB::table('sample_counters')
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->update(['last_number' => $nuevo, 'updated_at' => now()]);

        return $nuevo;
    }

    private function ensureCounter(?int $tenantId, int $year): void
    {
        $existe = DB::table('sample_counters')
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->exists();

        if ($existe) {
            return;
        }

        // `insertOrIgnore` y no `insert`: dos recepciones simultáneas del primer
        // día del año llegan las dos hasta acá, y la segunda tiene que
        // encontrarse la fila creada, no un error de clave duplicada.
        DB::table('sample_counters')->insertOrIgnore([
            'tenant_id'   => $tenantId,
            'year'        => $year,
            'last_number' => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
}
