<?php

namespace Database\Factories;

use App\Models\TestGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TestGroupFactory extends Factory
{
    protected $model = TestGroup::class;

    public function definition(): array
    {
        // `code` es NOT NULL y único en TODA la tabla (no por workspace): sin
        // unique() la segunda fila de cualquier test revienta el índice, no la
        // validación, y el error no dice nada útil.
        $name = $this->faker->unique()->words(2, true);

        return [
            'slug'       => Str::random(22),
            'code'       => Str::slug($name, '_'),
            'name'       => $name,
            'sort_order' => 0,
            'is_active'  => true,
        ];
    }

    /** Helper para tests que necesitan un nombre específico (asserts por nombre). */
    public function named(string $name): self
    {
        return $this->state(fn () => ['name' => $name]);
    }

    /** Helper para tests que fijan el código (la clave natural del grupo). */
    public function coded(string $code): self
    {
        return $this->state(fn () => ['code' => $code]);
    }

    /** Helper para crear grupos inactivos en tests de filtro. */
    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
