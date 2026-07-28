<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\Equipment\BulkEquipmentActionJob;
use App\Models\AuditLog;
use App\Models\Equipment;
use Illuminate\Support\Facades\DB;

/**
 * EquipmentService â€” operaciones de negocio del modulo equipment.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aquí toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class EquipmentService
{
    public function create(array $data): Equipment
    {
        $equipment = new Equipment($data);
        $equipment->created_by = auth()->id();
        $equipment->save();
        return $equipment;
    }

    public function update(Equipment $equipment, array $data): Equipment
    {
        $equipment->update($data);
        return $equipment;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(Equipment $equipment, string $reason): void
    {
        $equipment->deleted_description = $reason;
        $equipment->deleted_by          = auth()->id();
        $equipment->is_active           = false;
        $equipment->saveQuietly();
        $equipment->delete();
    }

    public function restore(Equipment $equipment): Equipment
    {
        $equipment->deleted_description = null;
        $equipment->deleted_by          = null;
        $equipment->restore();
        return $equipment;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(Equipment $equipment, string $reason): void
    {
        DB::transaction(function () use ($equipment, $reason) {
            $locked = Equipment::onlyTrashed()->where('id', $equipment->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("Equipment {$equipment->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Equipment::class,
                'auditable_id'   => $locked->id,
                'event'          => 'force_deleted',
                'old_values'     => [
                    'name'   => $locked->name,
                    'serial' => $locked->serial,
                    'tag'    => $locked->tag,
                    'slug'   => $locked->slug,
                ],
                'new_values'     => null,
                'url'            => request()?->fullUrl(),
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'note'           => $reason,
                'module'         => 'equipment',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona el equipment. Sufijo "(copia)" con sanity guard de 100 intentos.
     * El `cod` no se copia (es unique por tenant â€” se deja en null para que
     * el usuario lo ajuste manualmente al editar el clon).
     */
    public function duplicate(Equipment $equipment): ?Equipment
    {
        $base    = $equipment->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($equipment, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = Equipment::query()
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

            // Duplicar un equipo se usa para un banco de unidades gemelas: se
            // copia TODO menos la chapa. El clon del scaffold arrastraba
            // `sort_order` y `code` —dos columnas que esta tabla no tiene— y
            // dejaba fuera el cliente y las características, o sea que salía un
            // equipo huérfano que no servía para nada.
            $clone = new Equipment($equipment->only([
                'customer_id', 'customer_location_id', 'customer_area_id', 'customer_substation_id',
                'equipment_type_id', 'oil_type_id', 'brand_id', 'tap_changer_type_id',
                'transformer_preservation_id',
                'voltage_kv_hv', 'voltage_kv_lv', 'power_mva', 'phases', 'manufacture_year',
                'oil_volume', 'oil_volume_unit', 'service_state', 'is_active',
            ]));
            $clone->name       = $candidate;
            // La serie y el tag NO se copian: son únicos por definición, y
            // copiarlos chocaría contra el índice.
            $clone->serial     = null;
            $clone->tag        = null;
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
    // BulkEquipmentActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkEquipmentActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkEquipmentActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        return DB::transaction(function () use ($ids, $reason) {
            $equipment  = Equipment::whereIn('id', $ids)->get();
            $deletedIds = [];
            foreach ($equipment as $equipment) {
                $this->delete($equipment, $reason);
                $deletedIds[] = $equipment->id;
            }
            return ['queued' => false, 'count' => $equipment->count(), 'deleted' => $deletedIds];
        });
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkEquipmentActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        return DB::transaction(function () use ($ids, $isActive, $count) {
            $equipment = Equipment::whereIn('id', $ids)->get();
            $changed   = 0;
            foreach ($equipment as $equipment) {
                if ((bool) $equipment->is_active === $isActive) continue;
                $equipment->update(['is_active' => $isActive]);
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
            BulkEquipmentActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        return DB::transaction(function () use ($ids, $count) {
            $equipment = Equipment::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($equipment as $equipment) {
                $this->restore($equipment);
            }
            return ['queued' => false, 'count' => $count, 'restored' => $equipment->count()];
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
        $equipment = Equipment::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($equipment as $equipment) {
            $this->restore($equipment);
            $restored[] = $equipment->id;
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
            $byId  = Equipment::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $equipment = $byId[$change['id']] ?? null;
                if (!$equipment) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $equipment->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $equipment->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
