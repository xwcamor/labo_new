<?php

namespace App\Services\Lab;

use App\Models\Reception;
use App\Models\Sample;
use App\Models\SampleTest;
use App\Models\TestDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * El ciclo de la recepción: registrar lo que entra, emitir los correlativos y
 * declarar qué se le pide a cada muestra.
 *
 * Todo lo que cambia estado pasa por acá y no por los controladores, por el
 * mismo motivo por el que el cálculo pasa por el servidor: en el sistema
 * anterior estas reglas vivían en la vista, y un envío directo las salteaba.
 */
class ReceptionService
{
    public function __construct(
        private readonly SampleNumberAllocator $allocator = new SampleNumberAllocator(),
        private readonly SampleProgressService $progress = new SampleProgressService(),
    ) {
    }

    /**
     * Confirma la recepción y emite sus correlativos.
     *
     * Es el momento en que la recepción deja de ser un borrador: hasta acá se
     * corrige sin quemar números; a partir de acá las muestras existen, tienen
     * su correlativo y el laboratorio puede trabajarlas.
     *
     * @param  int $cuantas cuántas muestras trae la entrega
     * @throws ValidationException
     */
    public function confirm(Reception $reception, int $cuantas): Reception
    {
        if (! $reception->isDraft()) {
            throw ValidationException::withMessages([
                'status' => __('receptions.errors.not_draft'),
            ]);
        }

        if ($cuantas < 1) {
            throw ValidationException::withMessages([
                'samples' => __('receptions.errors.no_samples'),
            ]);
        }

        // El año del correlativo sale de la FECHA DE RECEPCIÓN, no del día en
        // que alguien confirma. Una entrega del 30 de diciembre que se confirma
        // el 2 de enero pertenece al ejercicio en que entró.
        $year = (int) $reception->received_at->year;

        return DB::transaction(function () use ($reception, $cuantas, $year) {
            // La reserva va DENTRO de esta transacción a propósito: si la
            // creación de las muestras falla, los números vuelven con ella y no
            // queda un hueco en la numeración.
            $numeros = $this->allocator->reserve($reception->tenant_id, $year, $cuantas);

            foreach ($numeros as $numero) {
                Sample::create([
                    'slug'         => Str::random(22),
                    'reception_id' => $reception->id,
                    'tenant_id'    => $reception->tenant_id,
                    'year'         => $year,
                    'number'       => $numero,
                    'code'         => Sample::formatCode($year, $numero),
                    'sampled_at'   => $reception->received_at->toDateString(),
                    // A bool y no tal cual: el modelo recién creado todavía no
                    // leyó el valor por defecto de la base, así que en memoria
                    // llega en nulo y la columna no lo admite.
                    'is_urgent'    => (bool) $reception->is_urgent,
                    'created_by'   => auth()->id(),
                ]);
            }

            $reception->forceFill([
                'status'       => Reception::STATUS_CONFIRMED,
                'confirmed_at' => now(),
                'confirmed_by' => auth()->id(),
            ])->save();

            return $reception->refresh();
        });
    }

    /**
     * Declara qué pruebas se le piden a una muestra.
     *
     * Recibe la lista COMPLETA de lo que se pide: lo que no viene y todavía no
     * se ensayó se da de baja; lo que viene y no estaba se agrega. Las que ya
     * tienen trabajo hecho NO se tocan, ni siquiera si dejan de pedirse — un
     * ensayo corrido tiene que seguir constando.
     *
     * @param  array<int,int> $testDefinitionIds
     * @return array{added:int,cancelled:int,kept:int}
     */
    public function requestTests(Sample $sample, array $testDefinitionIds): array
    {
        $pedidas = collect($testDefinitionIds)->map(fn ($id) => (int) $id)->unique();

        // Se verifica que existan: una prueba dada de baja del catálogo no se
        // puede pedir, y confiar en el id que manda el formulario es lo que
        // permitía en el sistema anterior que las casillas se desalinearan de
        // los nombres y se marcara la prueba equivocada.
        $validas = TestDefinition::whereIn('id', $pedidas)->pluck('id');

        $desconocidas = $pedidas->diff($validas);

        if ($desconocidas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'tests' => __('receptions.errors.unknown_test'),
            ]);
        }

        return DB::transaction(function () use ($sample, $validas) {
            $existentes = $sample->tests()->get()->keyBy('test_definition_id');

            $agregadas = 0;
            $dadasDeBaja = 0;
            $conservadas = 0;

            foreach ($validas as $id) {
                if ($existentes->has($id)) {
                    $prueba = $existentes[$id];

                    // Volver a pedir una que se había dado de baja la reactiva.
                    if ($prueba->status === SampleTest::STATUS_CANCELLED) {
                        $prueba->update(['status' => SampleTest::STATUS_PENDING]);
                    }

                    $conservadas++;
                    continue;
                }

                SampleTest::create([
                    'sample_id'          => $sample->id,
                    'test_definition_id' => $id,
                    'tenant_id'          => $sample->tenant_id,
                ]);

                $agregadas++;
            }

            foreach ($existentes as $id => $prueba) {
                if ($validas->contains($id)) {
                    continue;
                }

                // Ya tiene trabajo hecho: no se da de baja. Se conserva porque
                // el ensayo existió y el laboratorio responde por él.
                if (! $prueba->isOutstanding() && $prueba->status !== SampleTest::STATUS_PENDING) {
                    $conservadas++;
                    continue;
                }

                $prueba->update(['status' => SampleTest::STATUS_CANCELLED]);
                $dadasDeBaja++;
            }

            $this->progress->refreshSample($sample->fresh());

            return ['added' => $agregadas, 'cancelled' => $dadasDeBaja, 'kept' => $conservadas];
        });
    }

    /**
     * Aplica el mismo juego de pruebas a varias muestras de una vez.
     *
     * Es el caso normal: una entrega de veinte transformadores a los que se les
     * pide lo mismo. Sin esto, el laboratorio marca veinte veces las mismas
     * casillas, que es como el sistema anterior se ganó su botón de "Forzar
     * Pruebas" — un `update_all` masivo sin ninguna verificación.
     *
     * @param  \Illuminate\Support\Collection<int,Sample>|array<int,Sample> $samples
     * @param  array<int,int> $testDefinitionIds
     */
    public function requestTestsForMany($samples, array $testDefinitionIds): int
    {
        $hechas = 0;

        foreach ($samples as $sample) {
            $this->requestTests($sample, $testDefinitionIds);
            $hechas++;
        }

        return $hechas;
    }

    /**
     * Asigna el equipo del que se tomó la muestra.
     *
     * Se verifica que el equipo sea DEL CLIENTE de la recepción. En el sistema
     * anterior el desplegable filtraba por cliente pero cargaba en paralelo los
     * equipos de todos, y el guardado no lo verificaba: alcanzaba con un envío
     * directo para colgarle la muestra de un cliente al transformador de otro.
     *
     * @throws ValidationException
     */
    public function assignEquipment(Sample $sample, ?int $equipmentId): Sample
    {
        if ($equipmentId !== null) {
            $customerId = $sample->reception?->customer_id;

            $pertenece = \App\Models\Equipment::where('id', $equipmentId)
                ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
                ->exists();

            if (! $pertenece) {
                throw ValidationException::withMessages([
                    'equipment_id' => __('receptions.errors.equipment_not_of_customer'),
                ]);
            }
        }

        $sample->update(['equipment_id' => $equipmentId]);

        return $sample->refresh();
    }
}
