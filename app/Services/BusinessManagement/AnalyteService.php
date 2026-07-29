<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\Analytes\BulkAnalytesActionJob;
use App\Models\AuditLog;
use App\Models\Analyte;
use Illuminate\Support\Facades\DB;

/**
 * AnalyteService â€” operaciones de negocio del modulo analytes.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aquí toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class AnalyteService
{
    private const MODELO = \App\Models\Analyte::class;

    public function create(array $data): Analyte
    {
        $analyte = new Analyte($data);
        $analyte->created_by = auth()->id();
        $analyte->save();
        return $analyte;
    }

    public function update(Analyte $analyte, array $data): Analyte
    {
        $analyte->update($data);
        return $analyte;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(Analyte $analyte, string $reason): void
    {
        $analyte->deleted_description = $reason;
        $analyte->deleted_by          = auth()->id();
        $analyte->is_active           = false;
        $analyte->saveQuietly();
        $analyte->delete();
    }

    public function restore(Analyte $analyte): Analyte
    {
        $analyte->deleted_description = null;
        $analyte->deleted_by          = null;
        $analyte->restore();
        return $analyte;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(Analyte $analyte, string $reason): void
    {
        DB::transaction(function () use ($analyte, $reason) {
            $locked = Analyte::onlyTrashed()->where('id', $analyte->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("Analyte {$analyte->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Analyte::class,
                'auditable_id'   => $locked->id,
                'event'          => 'force_deleted',
                'old_values'     => [
                    'name' => $locked->name,
                    'code' => $locked->code,
                    'slug' => $locked->slug,
                ],
                'new_values'     => null,
                'url'            => request()?->fullUrl(),
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'note'           => $reason,
                'module'         => 'analytes',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona el analyte. Sufijo "(copia)" con sanity guard de 100 intentos.
     * El `cod` no se copia (es unique por tenant â€” se deja en null para que
     * el usuario lo ajuste manualmente al editar el clon).
     */
    public function duplicate(Analyte $analyte): ?Analyte
    {
        $base    = $analyte->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($analyte, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = Analyte::query()
                    ->when($isPgsql,
                        fn ($q) => $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$candidate]),
                        fn ($q) => $q->whereRaw('LOWER(name) = LOWER(?)', [$candidate]),
                    )
                    ->lockForUpdate()
                    ->exists();

                if (!$exists) break;
                $candidate = $base . ' ' . $i;
                $i++;
                if ($i > 100) return null;
            }

            $clone = new Analyte($analyte->only(['is_active', 'sort_order']));
            $clone->name       = $candidate;
            // El código se DERIVA del nombre del duplicado. En estas tablas
            // es obligatorio, así que dejarlo en nulo —lo que hacía el
            // scaffold— rompía el duplicar con un error de la base en la cara
            // del usuario. Se acota a 60 y se desempata con un sufijo si otro
            // ya lo tiene: el índice del código es único.
            $clone->code       = $this->codigoLibre($candidate);
            $clone->created_by = auth()->id();
            $clone->save();

            return $clone;
        });
    }

    // â”€â”€â”€ Bulk ops â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //
    // Auto-async: si count(ids) excede el umbral, dispatchamos el job y
    // devolvemos un payload "queued" para que el controller redirija con
    // mensaje de cola. Bajo el umbral, corre inline. El umbral vive en
    // BulkAnalytesActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkAnalytesActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkAnalytesActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        return DB::transaction(function () use ($ids, $reason) {
            $analytes  = Analyte::whereIn('id', $ids)->get();
            $deletedIds = [];
            foreach ($analytes as $analyte) {
                $this->delete($analyte, $reason);
                $deletedIds[] = $analyte->id;
            }
            return ['queued' => false, 'count' => $analytes->count(), 'deleted' => $deletedIds];
        });
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkAnalytesActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        return DB::transaction(function () use ($ids, $isActive, $count) {
            $analytes = Analyte::whereIn('id', $ids)->get();
            $changed   = 0;
            foreach ($analytes as $analyte) {
                if ((bool) $analyte->is_active === $isActive) continue;
                $analyte->update(['is_active' => $isActive]);
                $changed++;
            }
            return ['queued' => false, 'count' => $count, 'changed' => $changed];
        });
    }

    /**
     * @return array{queued: bool, count: int, restored?: int}
     */
    public function bulkRestore(array $ids): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkAnalytesActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        return DB::transaction(function () use ($ids, $count) {
            $analytes = Analyte::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($analytes as $analyte) {
                $this->restore($analyte);
            }
            return ['queued' => false, 'count' => $count, 'restored' => $analytes->count()];
        });
    }

    /**
     * Undo dentro del window de 60s. Defense in depth: solo restaura las filas
     * que matchean deleted_by = userId, no cualquier id del claim.
     *
     * @param int[] $claimIds
     * @return int[] ids efectivamente restaurados
     */
    public function undoLastDelete(array $claimIds, int $userId): array
    {
        $analytes = Analyte::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($analytes as $analyte) {
            $this->restore($analyte);
            $restored[] = $analyte->id;
        }
        return $restored;
    }

    /**
     * Batch update de name + is_active. Persistencia en transaccion para
     * atomicidad. Skip filas sin cambio real para evitar audit log noise.
     *
     * @return int touched count
     */
    public function editAllUpdate(array $changes): int
    {
        $touched = 0;

        DB::transaction(function () use ($changes, &$touched) {
            $ids   = array_column($changes, 'id');
            $byId  = Analyte::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $analyte = $byId[$change['id']] ?? null;
                if (!$analyte) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $analyte->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $analyte->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }

    /**
     * Un código libre derivado de un nombre.
     *
     * `numero_acido`, `numero_acido_2`… El duplicado necesita uno propio porque
     * el código es único y obligatorio; nulo no es opción y repetir el del
     * original tampoco.
     */
    private function codigoLibre(string $nombre): string
    {
        $base = \Illuminate\Support\Str::limit(\Illuminate\Support\Str::slug($nombre, '_'), 60, '');
        $codigo = $base;
        $i = 2;

        while (static::MODELO::withoutGlobalScopes()->where('code', $codigo)->exists()) {
            $codigo = $base . '_' . $i++;

            if ($i > 100) {
                return $base . '_' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(4));
            }
        }

        return $codigo;
    }
}
