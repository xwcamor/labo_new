<?php

namespace Database\Factories;

use App\Models\Instrument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InstrumentFactory extends Factory
{
    protected $model = Instrument::class;

    public function definition(): array
    {
        // El NOMBRE es el código de calibración y es único; la DESCRIPCIÓN es el
        // tipo de equipo y se repite a propósito — en el laboratorio real hay
        // tres equipos descritos como "Bureta".
        $calibrated = $this->faker->dateTimeBetween('-10 months', '-1 month');

        return [
            'slug'   => Str::random(22),
            'name'   => strtoupper($this->faker->unique()->bothify('PP-LA-01C-##?')),
            'description' => $this->faker->randomElement([
                'Bureta', 'Balanza analítica', 'Cromatógrafo', 'Titulador Karl Fischer',
                'Equipo de rigidez dieléctrica', 'Viscosímetro',
            ]),
            'brand'  => $this->faker->randomElement(['Mettler Toledo', 'Megger', 'Agilent', 'Metrohm']),
            'model'  => strtoupper($this->faker->bothify('??-###')),
            'serial' => strtoupper($this->faker->bothify('SN-#####')),
            'calibrated_at'           => $calibrated->format('Y-m-d'),
            'calibration_due_at'      => (clone $calibrated)->modify('+1 year')->format('Y-m-d'),
            'calibration_certificate' => strtoupper($this->faker->bothify('CAL-2026-###')),
            'location'  => $this->faker->randomElement(['Laboratorio de aceites', 'Sala de cromatografía', 'Bancada 2']),
            'is_active' => true,
        ];
    }

    /** Helper para tests que fijan el nombre (la clave natural del equipo). */
    public function named(string $name): self
    {
        return $this->state(fn () => ['name' => $name]);
    }

    /** Helper para tests que fijan la descripción (el tipo de equipo). */
    public function described(string $description): self
    {
        return $this->state(fn () => ['description' => $description]);
    }

    /** Helper para crear instrumentos inactivos en tests de filtro. */
    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /** Calibración vencida: el caso que el semáforo tiene que atrapar. */
    public function expired(): self
    {
        return $this->state(fn () => [
            'calibrated_at'      => now()->subMonths(14)->toDateString(),
            'calibration_due_at' => now()->subMonths(2)->toDateString(),
        ]);
    }

    /** Vence dentro de la ventana de aviso (por defecto, 30 días). */
    public function dueSoon(): self
    {
        return $this->state(fn () => [
            'calibrated_at'      => now()->subMonths(11)->toDateString(),
            'calibration_due_at' => now()->addDays(10)->toDateString(),
        ]);
    }

    /** Sin fecha de vencimiento: no se puede afirmar que estuviera calibrado. */
    public function withoutCalibration(): self
    {
        return $this->state(fn () => [
            'calibrated_at'           => null,
            'calibration_due_at'      => null,
            'calibration_certificate' => null,
        ]);
    }
}
