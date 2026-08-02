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
     * ¿Se puede corregir la cantidad de muestras de esta entrega?
     *
     * Solo mientras sus números sean los ÚLTIMOS emitidos del año en su
     * workspace: ahí achicar o agrandar el lote no toca ningún número que otra
     * entrega haya citado. En cuanto otra recepción confirma después, la
     * corrección se cierra — para quitar queda la baja por muestra (que quema
     * el número), y para agregar, registrar otra entrega.
     */
    public function canAdjust(Reception $reception): bool
    {
        if (! $reception->isConfirmed()) {
            return false;
        }

        $year = (int) $reception->received_at->year;

        // Con una muestra ya dada de baja individualmente la semántica de
        // «corregir la cantidad» se vuelve ambigua (¿cuál era el error?):
        // en ese caso se sigue por el camino por-muestra.
        $todas = $reception->samples()->withTrashed()->get(['number', 'deleted_at']);

        if ($todas->isEmpty() || $todas->whereNotNull('deleted_at')->isNotEmpty()) {
            return false;
        }

        $ultimoDelAno = (int) DB::table('sample_counters')
            ->where('tenant_id', $reception->tenant_id)
            ->where('year', $year)
            ->value('last_number');

        return $ultimoDelAno === (int) $todas->max('number');
    }

    /**
     * Corrige la cantidad de muestras de una entrega YA CONFIRMADA.
     *
     * Es la salida para el «puse 32 y eran 20» (o 40): mientras los números de
     * la entrega sigan siendo la cola de la numeración del año, quitar el
     * excedente y RETROCEDER el contador no reutiliza nada — esos números nunca
     * salieron del laboratorio — y agregar toma los siguientes, contiguos.
     *
     * Esto NO contradice la regla «un número emitido no se reutiliza»: esa
     * regla protege contra reasignar el número de una muestra dada de baja EN
     * MEDIO de la historia (el defecto del sistema anterior). Acá se deshace la
     * punta de la secuencia, dentro de la misma ventana en que nadie más emitió.
     *
     * Las muestras que se quitan deben estar VÍRGENES: sin filas de bancada
     * (ni en papelera), sin resultados y sin informes. Con trabajo hecho, la
     * corrección no procede — eso ya no es un error de tipeo.
     *
     * @return array{added:int,removed:int}
     * @throws ValidationException
     */
    public function adjustSamples(Reception $reception, int $nuevoTotal): array
    {
        if ($nuevoTotal < 1 || $nuevoTotal > 500) {
            throw ValidationException::withMessages([
                'samples' => __('receptions.errors.no_samples'),
            ]);
        }

        $year = (int) $reception->received_at->year;

        return DB::transaction(function () use ($reception, $nuevoTotal, $year) {
            // El contador se BLOQUEA primero: congela la cola mientras se
            // verifica y se corrige, para que otra confirmación simultánea no
            // emita números en el medio de la maniobra.
            $contador = DB::table('sample_counters')
                ->where('tenant_id', $reception->tenant_id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $muestras = $reception->samples()->withTrashed()->orderBy('number')->get();

            $esCola = $reception->isConfirmed()
                && $contador !== null
                && $muestras->isNotEmpty()
                && $muestras->whereNotNull('deleted_at')->isEmpty()
                && (int) $contador->last_number === (int) $muestras->max('number');

            if (! $esCola) {
                throw ValidationException::withMessages([
                    'samples' => __('receptions.errors.adjust_not_tail'),
                ]);
            }

            $actual = $muestras->count();

            if ($nuevoTotal === $actual) {
                return ['added' => 0, 'removed' => 0];
            }

            if ($nuevoTotal < $actual) {
                $sobrantes = $muestras->slice($nuevoTotal);   // las de números más altos

                foreach ($sobrantes as $muestra) {
                    $this->assertVirgen($muestra);
                }

                foreach ($sobrantes as $muestra) {
                    // Borrado DURO, no papelera: estas muestras nunca
                    // existieron para el laboratorio. Las pruebas pedidas caen
                    // en cascada; bancada/resultados/informes no hay (recién
                    // se verificó).
                    $muestra->forceDelete();
                }

                // El contador retrocede: los números vuelven a estar
                // disponibles porque siguen siendo la punta de la secuencia.
                DB::table('sample_counters')
                    ->where('tenant_id', $reception->tenant_id)
                    ->where('year', $year)
                    ->update([
                        'last_number' => $contador->last_number - $sobrantes->count(),
                        'updated_at'  => now(),
                    ]);

                $this->auditarCorreccion($reception, $actual, $nuevoTotal);

                return ['added' => 0, 'removed' => $sobrantes->count()];
            }

            // Agrandar: los números nuevos salen del contador, contiguos a los
            // de la entrega porque el lote es la cola y el contador está
            // bloqueado en esta misma transacción.
            $numeros = $this->allocator->reserve($reception->tenant_id, $year, $nuevoTotal - $actual);

            foreach ($numeros as $numero) {
                Sample::create([
                    'slug'         => Str::random(22),
                    'reception_id' => $reception->id,
                    'tenant_id'    => $reception->tenant_id,
                    'year'         => $year,
                    'number'       => $numero,
                    'code'         => Sample::formatCode($year, $numero),
                    'sampled_at'   => $reception->received_at->toDateString(),
                    'is_urgent'    => (bool) $reception->is_urgent,
                    'created_by'   => auth()->id(),
                ]);
            }

            $this->auditarCorreccion($reception, $actual, $nuevoTotal);

            return ['added' => count($numeros), 'removed' => 0];
        });
    }

    /** La muestra que se quita no puede tener NADA hecho. */
    private function assertVirgen(Sample $muestra): void
    {
        $tieneBancada = \App\Models\WorksheetRow::withTrashed()
            ->where('sample_id', $muestra->id)
            ->exists();

        if ($tieneBancada || $muestra->results()->exists() || $muestra->reports()->exists()) {
            throw ValidationException::withMessages([
                'samples' => __('receptions.errors.adjust_has_work', ['code' => $muestra->code]),
            ]);
        }
    }

    /** La corrección queda en el historial de la entrega, con el antes y el después. */
    private function auditarCorreccion(Reception $reception, int $antes, int $despues): void
    {
        \App\Models\AuditLog::create([
            'user_id'        => auth()->id(),
            'event'          => 'samples_adjusted',
            'auditable_type' => Reception::class,
            'auditable_id'   => $reception->id,
            'module'         => 'receptions',
            'old_values'     => ['samples' => $antes],
            'new_values'     => ['samples' => $despues],
            'url'            => request()?->fullUrl(),
            'ip_address'     => request()?->ip(),
            'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
            'created_at'     => now(),
        ]);
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
