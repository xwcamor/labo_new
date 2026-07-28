<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\EquipmentTypes\BulkEquipmentTypesActionJob;
use App\Models\AuditLog;
use App\Models\EquipmentType;
use Illuminate\Support\Facades\DB;

/**
 * EquipmentTypeService â€” operaciones de negocio del modulo equipment_types.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aquí toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class EquipmentTypeService
{
    public function create(array $data): EquipmentType
    {
        $equipmentType = new EquipmentType($data);
        $equipmentType->created_by = auth()->id();
        $equipmentType->save();
        return $equipmentType;
    }

    public function update(EquipmentType $equipmentType, array $data): EquipmentType
    {
        $equipmentType->update($data);
        return $equipmentType;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(EquipmentType $equipmentType, string $reason): void
    {
        $equipmentType->deleted_description = $reason;
        $equipmentType->deleted_by          = auth()->id();
        $equipmentType->is_active           = false;
        $equipmentType->saveQuietly();
        $equipmentType->delete();
    }

    public function restore(EquipmentType $equipmentType): EquipmentType
    {
        $equipmentType->deleted_description = null;
        $equipmentType->deleted_by          = null;
        $equipmentType->restore();
        return $equipmentType;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(EquipmentType $equipmentType, string $reason): void
    {
        DB::transaction(function () use ($equipmentType, $reason) {
            $locked = EquipmentType::onlyTrashed()->where('id', $equipmentType->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("EquipmentType {$equipmentType->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => EquipmentType::class,
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
                'module'         => 'equipment_types',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona el equipmentType. Sufijo "(copia)" con sanity guard de 100 intentos.
     * El `cod` no se copia (es unique por tenant â€” se deja en null para que
     * el usuario lo ajuste manualmente al editar el clon).
     */
    public function duplicate(EquipmentType $equipmentType): ?EquipmentType
    {
        $base    = $equipmentType->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($equipmentType, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = EquipmentType::query()
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

            $clone = new EquipmentType($equipmentType->only(['is_active', 'sort_order']));
            $clone->name       = $candidate;
            $clone->code       = null;
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
    // BulkEquipmentTypesActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkEquipmentTypesActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkEquipmentTypesActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        return DB::transaction(function () use ($ids, $reason) {
            $equipmentTypes  = EquipmentType::whereIn('id', $ids)->get();
            $deletedIds = [];
            foreach ($equipmentTypes as $equipmentType) {
                $this->delete($equipmentType, $reason);
                $deletedIds[] = $equipmentType->id;
            }
            return ['queued' => false, 'count' => $equipmentTypes->count(), 'deleted' => $deletedIds];
        });
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkEquipmentTypesActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        return DB::transaction(function () use ($ids, $isActive, $count) {
            $equipmentTypes = EquipmentType::whereIn('id', $ids)->get();
            $changed   = 0;
            foreach ($equipmentTypes as $equipmentType) {
                if ((bool) $equipmentType->is_active === $isActive) continue;
                $equipmentType->update(['is_active' => $isActive]);
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
            BulkEquipmentTypesActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        return DB::transaction(function () use ($ids, $count) {
            $equipmentTypes = EquipmentType::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($equipmentTypes as $equipmentType) {
                $this->restore($equipmentType);
            }
            return ['queued' => false, 'count' => $count, 'restored' => $equipmentTypes->count()];
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
        $equipmentTypes = EquipmentType::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($equipmentTypes as $equipmentType) {
            $this->restore($equipmentType);
            $restored[] = $equipmentType->id;
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
            $byId  = EquipmentType::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $equipmentType = $byId[$change['id']] ?? null;
                if (!$equipmentType) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $equipmentType->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $equipmentType->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
