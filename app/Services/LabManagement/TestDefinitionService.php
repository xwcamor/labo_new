<?php

namespace App\Services\LabManagement;

use App\Jobs\LabManagement\TestDefinitions\BulkTestDefinitionsActionJob;
use App\Models\AuditLog;
use App\Models\TestDefinition;
use Illuminate\Support\Facades\DB;

/**
 * TestDefinitionService — operaciones de negocio del modulo test_definitions.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aquí toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class TestDefinitionService
{
    private const MODELO = \App\Models\TestDefinition::class;

    public function create(array $data): TestDefinition
    {
        $testDefinition = new TestDefinition($this->conCalibracion($this->conOrden($data)));
        $testDefinition->created_by = auth()->id();
        $testDefinition->save();
        return $testDefinition;
    }

    public function update(TestDefinition $testDefinition, array $data): TestDefinition
    {
        $testDefinition->update($this->conCalibracion($this->conOrden($data, $testDefinition)));
        return $testDefinition;
    }

    /**
     * Marca que el control de calidad de la prueba lo decidió una persona.
     *
     * ┌──────────────────────────────────────────────────────────────────────┐
     * │ POR QUÉ HACE FALTA LA MARCA                                          │
     * └──────────────────────────────────────────────────────────────────────┘
     * `requires_control = false` significa dos cosas incompatibles: "nadie lo
     * configuró" y "el supervisor decidió que esta prueba no lleva patrón". Sin
     * distinguirlas, el sembrador de fábrica no puede refrescar los valores sin
     * pisar decisiones — y lo haría en silencio: el laboratorio se enteraría
     * cuando una hoja que venía publicándose se niegue a publicarse.
     *
     * En cuanto alguien toca una de las tres casillas desde la ficha, la fila
     * pasa a ser suya y el seeder no la vuelve a escribir.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function conCalibracion(array $data): array
    {
        $casillas = ['requires_control', 'requires_duplicate', 'is_grouped'];

        foreach ($casillas as $casilla) {
            if (array_key_exists($casilla, $data)) {
                $data['qc_policy_set_at'] = now();

                break;
            }
        }

        return $data;
    }

    /**
     * El ORDEN es opcional en el formulario, y sin esto la prueba nueva se caía
     * al guardar.
     *
     * La columna es NOT NULL con `default(0)`, pero un default de base solo
     * actúa cuando la columna NO viaja en el INSERT: el formulario mandaba
     * `sort_order = null` explícito y Postgres rechazaba la fila con una
     * violación de not-null que llegaba a la pantalla como error 500. Dejar el
     * campo vacío es lo normal —el laboratorio no quiere pensar la posición de
     * cada prueba—, así que el hueco lo llena el servidor.
     *
     * Se pone AL FINAL de su grupo, no en 0: una prueba nueva que aparece
     * primera en el desplegable, delante de las que el laboratorio ya venía
     * usando, es un cambio de orden que nadie pidió.
     *
     * @param  array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function conOrden(array $data, ?TestDefinition $existente = null): array
    {
        // Al editar, un campo que no viene en el envío no se toca. Solo se
        // resuelve el que llegó explícitamente vacío.
        if (! array_key_exists('sort_order', $data) || $data['sort_order'] !== null) {
            return $data;
        }

        // Si ya tenía posición y el envío la vacía, se conserva: vaciar el campo
        // en el formulario de edición no es pedir que la prueba se mueva.
        if ($existente && $existente->sort_order !== null) {
            $data['sort_order'] = $existente->sort_order;

            return $data;
        }

        $grupo = $data['test_group_id'] ?? $existente?->test_group_id;

        $data['sort_order'] = (int) TestDefinition::withTrashed()
            ->when($grupo, fn ($q) => $q->where('test_group_id', $grupo))
            ->max('sort_order') + 1;

        return $data;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(TestDefinition $testDefinition, string $reason): void
    {
        $testDefinition->deleted_description = $reason;
        $testDefinition->deleted_by          = auth()->id();
        $testDefinition->is_active           = false;
        $testDefinition->saveQuietly();
        $testDefinition->delete();
    }

    public function restore(TestDefinition $testDefinition): TestDefinition
    {
        $testDefinition->deleted_description = null;
        $testDefinition->deleted_by          = null;
        $testDefinition->restore();
        return $testDefinition;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(TestDefinition $testDefinition, string $reason): void
    {
        DB::transaction(function () use ($testDefinition, $reason) {
            $locked = TestDefinition::onlyTrashed()->where('id', $testDefinition->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("TestDefinition {$testDefinition->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => TestDefinition::class,
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
                'module'         => 'test_definitions',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona el testDefinition. Sufijo "(copia)" con sanity guard de 100 intentos.
     * El `cod` no se copia (es unique por tenant — se deja en null para que
     * el usuario lo ajuste manualmente al editar el clon).
     */
    public function duplicate(TestDefinition $testDefinition): ?TestDefinition
    {
        $base    = $testDefinition->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($testDefinition, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = TestDefinition::query()
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

            $clone = new TestDefinition($testDefinition->only(['is_active', 'sort_order']));
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
    // BulkTestDefinitionsActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkTestDefinitionsActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkTestDefinitionsActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        return DB::transaction(function () use ($ids, $reason) {
            $test_definitions  = TestDefinition::whereIn('id', $ids)->get();
            $deletedIds = [];
            foreach ($test_definitions as $testDefinition) {
                $this->delete($testDefinition, $reason);
                $deletedIds[] = $testDefinition->id;
            }
            return ['queued' => false, 'count' => $test_definitions->count(), 'deleted' => $deletedIds];
        });
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkTestDefinitionsActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        return DB::transaction(function () use ($ids, $isActive, $count) {
            $test_definitions = TestDefinition::whereIn('id', $ids)->get();
            $changed   = 0;
            foreach ($test_definitions as $testDefinition) {
                if ((bool) $testDefinition->is_active === $isActive) continue;
                $testDefinition->update(['is_active' => $isActive]);
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
            BulkTestDefinitionsActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        return DB::transaction(function () use ($ids, $count) {
            $test_definitions = TestDefinition::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($test_definitions as $testDefinition) {
                $this->restore($testDefinition);
            }
            return ['queued' => false, 'count' => $count, 'restored' => $test_definitions->count()];
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
        $test_definitions = TestDefinition::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($test_definitions as $testDefinition) {
            $this->restore($testDefinition);
            $restored[] = $testDefinition->id;
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
            $byId  = TestDefinition::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $testDefinition = $byId[$change['id']] ?? null;
                if (!$testDefinition) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $testDefinition->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $testDefinition->fill($patch)->save();
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
