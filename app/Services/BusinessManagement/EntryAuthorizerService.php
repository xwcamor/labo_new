<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\EntryAuthorizers\BulkEntryAuthorizersActionJob;
use App\Models\AuditLog;
use App\Models\EntryAuthorizer;
use Illuminate\Support\Facades\DB;

/**
 * EntryAuthorizerService â€” operaciones de negocio del modulo entry_authorizers.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aquí toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class EntryAuthorizerService
{
    public function create(array $data): EntryAuthorizer
    {
        $entryAuthorizer = new EntryAuthorizer($this->conImagen($data));
        $entryAuthorizer->created_by = auth()->id();
        $entryAuthorizer->save();
        return $entryAuthorizer;
    }

    public function update(EntryAuthorizer $entryAuthorizer, array $data): EntryAuthorizer
    {
        $entryAuthorizer->update($this->conImagen($data, $entryAuthorizer));
        return $entryAuthorizer;
    }

    /**
     * Guarda la firma escaneada, si vino una — mismo criterio que
     * `SignatureService`: sin archivo nuevo la clave se saca del arreglo
     * (mandarla en nulo borraría la firma existente al editar el nombre), y la
     * anterior se elimina del disco al reemplazarse.
     *
     * @param  array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function conImagen(array $data, ?EntryAuthorizer $registro = null): array
    {
        $archivo = $data['image'] ?? null;

        if (! $archivo instanceof \Illuminate\Http\UploadedFile) {
            unset($data['image']);

            return $data;
        }

        if ($registro?->image) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($registro->image);
        }

        $data['image'] = $archivo->store('entry-authorizers', 'public');

        return $data;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(EntryAuthorizer $entryAuthorizer, string $reason): void
    {
        $entryAuthorizer->deleted_description = $reason;
        $entryAuthorizer->deleted_by          = auth()->id();
        $entryAuthorizer->is_active           = false;
        $entryAuthorizer->saveQuietly();
        $entryAuthorizer->delete();
    }

    public function restore(EntryAuthorizer $entryAuthorizer): EntryAuthorizer
    {
        $entryAuthorizer->deleted_description = null;
        $entryAuthorizer->deleted_by          = null;
        $entryAuthorizer->restore();
        return $entryAuthorizer;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(EntryAuthorizer $entryAuthorizer, string $reason): void
    {
        DB::transaction(function () use ($entryAuthorizer, $reason) {
            $locked = EntryAuthorizer::onlyTrashed()->where('id', $entryAuthorizer->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("EntryAuthorizer {$entryAuthorizer->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => EntryAuthorizer::class,
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
                'module'         => 'entry_authorizers',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona el entryAuthorizer. Sufijo "(copia)" con sanity guard de 100 intentos.
     * El `cod` no se copia (es unique por tenant â€” se deja en null para que
     * el usuario lo ajuste manualmente al editar el clon).
     */
    public function duplicate(EntryAuthorizer $entryAuthorizer): ?EntryAuthorizer
    {
        $base    = $entryAuthorizer->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($entryAuthorizer, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = EntryAuthorizer::query()
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

            $clone = new EntryAuthorizer($entryAuthorizer->only(['is_active', 'sort_order']));
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
    // BulkEntryAuthorizersActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkEntryAuthorizersActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkEntryAuthorizersActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        return DB::transaction(function () use ($ids, $reason) {
            $entry_authorizers  = EntryAuthorizer::whereIn('id', $ids)->get();
            $deletedIds = [];
            foreach ($entry_authorizers as $entryAuthorizer) {
                $this->delete($entryAuthorizer, $reason);
                $deletedIds[] = $entryAuthorizer->id;
            }
            return ['queued' => false, 'count' => $entry_authorizers->count(), 'deleted' => $deletedIds];
        });
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkEntryAuthorizersActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        return DB::transaction(function () use ($ids, $isActive, $count) {
            $entry_authorizers = EntryAuthorizer::whereIn('id', $ids)->get();
            $changed   = 0;
            foreach ($entry_authorizers as $entryAuthorizer) {
                if ((bool) $entryAuthorizer->is_active === $isActive) continue;
                $entryAuthorizer->update(['is_active' => $isActive]);
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
            BulkEntryAuthorizersActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        return DB::transaction(function () use ($ids, $count) {
            $entry_authorizers = EntryAuthorizer::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($entry_authorizers as $entryAuthorizer) {
                $this->restore($entryAuthorizer);
            }
            return ['queued' => false, 'count' => $count, 'restored' => $entry_authorizers->count()];
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
        $entry_authorizers = EntryAuthorizer::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($entry_authorizers as $entryAuthorizer) {
            $this->restore($entryAuthorizer);
            $restored[] = $entryAuthorizer->id;
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
            $byId  = EntryAuthorizer::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $entryAuthorizer = $byId[$change['id']] ?? null;
                if (!$entryAuthorizer) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $entryAuthorizer->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $entryAuthorizer->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
