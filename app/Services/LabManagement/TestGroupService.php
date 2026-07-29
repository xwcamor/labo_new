<?php

namespace App\Services\LabManagement;

use App\Jobs\LabManagement\TestGroups\BulkTestGroupsActionJob;
use App\Models\AuditLog;
use App\Models\TestGroup;
use Illuminate\Support\Facades\DB;

/**
 * TestGroupService — operaciones de negocio del modulo test_groups.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aquí toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class TestGroupService
{
    private const MODELO = \App\Models\TestGroup::class;

    public function create(array $data): TestGroup
    {
        $testGroup = new TestGroup($data);
        $testGroup->created_by = auth()->id();
        $testGroup->save();
        return $testGroup;
    }

    public function update(TestGroup $testGroup, array $data): TestGroup
    {
        $testGroup->update($data);
        return $testGroup;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(TestGroup $testGroup, string $reason): void
    {
        $testGroup->deleted_description = $reason;
        $testGroup->deleted_by          = auth()->id();
        $testGroup->is_active           = false;
        $testGroup->saveQuietly();
        $testGroup->delete();
    }

    public function restore(TestGroup $testGroup): TestGroup
    {
        $testGroup->deleted_description = null;
        $testGroup->deleted_by          = null;
        $testGroup->restore();
        return $testGroup;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(TestGroup $testGroup, string $reason): void
    {
        DB::transaction(function () use ($testGroup, $reason) {
            $locked = TestGroup::onlyTrashed()->where('id', $testGroup->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("TestGroup {$testGroup->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => TestGroup::class,
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
                'module'         => 'test_groups',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona el testGroup. Sufijo "(copia)" con sanity guard de 100 intentos.
     * El `cod` no se copia (es unique por tenant — se deja en null para que
     * el usuario lo ajuste manualmente al editar el clon).
     */
    public function duplicate(TestGroup $testGroup): ?TestGroup
    {
        $base    = $testGroup->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($testGroup, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = TestGroup::query()
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

            $clone = new TestGroup($testGroup->only(['is_active', 'sort_order']));
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

    // ─── Bulk ops ──────────────────────────────────────────────────────────
    //
    // Auto-async: si count(ids) excede el umbral, dispatchamos el job y
    // devolvemos un payload "queued" para que el controller redirija con
    // mensaje de cola. Bajo el umbral, corre inline. El umbral vive en
    // BulkTestGroupsActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkTestGroupsActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkTestGroupsActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        return DB::transaction(function () use ($ids, $reason) {
            $test_groups  = TestGroup::whereIn('id', $ids)->get();
            $deletedIds = [];
            foreach ($test_groups as $testGroup) {
                $this->delete($testGroup, $reason);
                $deletedIds[] = $testGroup->id;
            }
            return ['queued' => false, 'count' => $test_groups->count(), 'deleted' => $deletedIds];
        });
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkTestGroupsActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        return DB::transaction(function () use ($ids, $isActive, $count) {
            $test_groups = TestGroup::whereIn('id', $ids)->get();
            $changed   = 0;
            foreach ($test_groups as $testGroup) {
                if ((bool) $testGroup->is_active === $isActive) continue;
                $testGroup->update(['is_active' => $isActive]);
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
            BulkTestGroupsActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        return DB::transaction(function () use ($ids, $count) {
            $test_groups = TestGroup::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($test_groups as $testGroup) {
                $this->restore($testGroup);
            }
            return ['queued' => false, 'count' => $count, 'restored' => $test_groups->count()];
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
        $test_groups = TestGroup::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($test_groups as $testGroup) {
            $this->restore($testGroup);
            $restored[] = $testGroup->id;
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
            $byId  = TestGroup::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $testGroup = $byId[$change['id']] ?? null;
                if (!$testGroup) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active', 'sort_order'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $testGroup->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $testGroup->fill($patch)->save();
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
