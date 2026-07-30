<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\Instruments\BulkInstrumentsActionJob;
use App\Models\AuditLog;
use App\Models\Instrument;
use Illuminate\Support\Facades\DB;

/**
 * InstrumentService — operaciones de negocio del modulo instruments.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aquí toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class InstrumentService
{
    public function create(array $data): Instrument
    {
        $instrument = new Instrument($data);
        $instrument->created_by = auth()->id();
        $instrument->save();
        return $instrument;
    }

    public function update(Instrument $instrument, array $data): Instrument
    {
        $instrument->update($data);
        return $instrument;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(Instrument $instrument, string $reason): void
    {
        $instrument->deleted_description = $reason;
        $instrument->deleted_by          = auth()->id();
        $instrument->is_active           = false;
        $instrument->saveQuietly();
        $instrument->delete();
    }

    public function restore(Instrument $instrument): Instrument
    {
        $instrument->deleted_description = null;
        $instrument->deleted_by          = null;
        $instrument->restore();
        return $instrument;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(Instrument $instrument, string $reason): void
    {
        DB::transaction(function () use ($instrument, $reason) {
            $locked = Instrument::onlyTrashed()->where('id', $instrument->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("Instrument {$instrument->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Instrument::class,
                'auditable_id'   => $locked->id,
                'event'          => 'force_deleted',
                'old_values'     => [
                    'name'        => $locked->name,
                    'description' => $locked->description,
                    'slug'        => $locked->slug,
                ],
                'new_values'     => null,
                'url'            => request()?->fullUrl(),
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'note'           => $reason,
                'module'         => 'instruments',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona el instrumento. Sufijo "(copia)" con sanity guard de 100 intentos.
     *
     * NO se copian ni el `serial` ni NADA de la calibración: el duplicado sirve
     * para dar de alta el segundo equipo igual al primero (mismo tipo, misma
     * marca, mismo modelo, misma ubicación), y arrastrarle el certificado del
     * original diría que un equipo está calibrado cuando todavía no se calibró.
     * Eso es exactamente lo que ISO 17025 no permite.
     *
     * El NOMBRE sí se sufija, porque es el código de calibración y es único: la
     * copia queda como "PP-LA-01C-100 (copia)" y el laboratorio le escribe el
     * código real del equipo nuevo. La DESCRIPCIÓN se copia tal cual — dos
     * buretas son las dos "Bureta", y eso es correcto.
     */
    public function duplicate(Instrument $instrument): ?Instrument
    {
        $base    = $instrument->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($instrument, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = Instrument::query()
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

            $clone = new Instrument($instrument->only([
                'is_active', 'sort_order', 'description', 'brand', 'model', 'location', 'notes',
            ]));
            $clone->name       = $candidate;
            $clone->created_by = auth()->id();
            $clone->save();

            return $clone;
        });
    }

    // ─── Bulk ops ──────────────────────────────────────────────────────────
    //
    // Auto-async: si count(ids) excede el umbral, dispatchamos el job y
    // devolvemos un payload "queued" para que el controller redirija con
    // mensaje de cola. Bajo el umbral, corre inline. El umbral vive en
    // BulkInstrumentsActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkInstrumentsActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkInstrumentsActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        return DB::transaction(function () use ($ids, $reason) {
            $instruments  = Instrument::whereIn('id', $ids)->get();
            $deletedIds = [];
            foreach ($instruments as $instrument) {
                $this->delete($instrument, $reason);
                $deletedIds[] = $instrument->id;
            }
            return ['queued' => false, 'count' => $instruments->count(), 'deleted' => $deletedIds];
        });
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkInstrumentsActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        return DB::transaction(function () use ($ids, $isActive, $count) {
            $instruments = Instrument::whereIn('id', $ids)->get();
            $changed   = 0;
            foreach ($instruments as $instrument) {
                if ((bool) $instrument->is_active === $isActive) continue;
                $instrument->update(['is_active' => $isActive]);
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
            BulkInstrumentsActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        return DB::transaction(function () use ($ids, $count) {
            $instruments = Instrument::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($instruments as $instrument) {
                $this->restore($instrument);
            }
            return ['queued' => false, 'count' => $count, 'restored' => $instruments->count()];
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
        $instruments = Instrument::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($instruments as $instrument) {
            $this->restore($instrument);
            $restored[] = $instrument->id;
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
            $byId  = Instrument::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $instrument = $byId[$change['id']] ?? null;
                if (!$instrument) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $instrument->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $instrument->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
