<?php

namespace Database\Factories;

use App\Models\TestDefinition;
use App\Models\TestGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TestDefinitionFactory extends Factory
{
    protected $model = TestDefinition::class;

    public function definition(): array
    {
        // `code` es NOT NULL y único en TODA la tabla (no por workspace): sin
        // unique() la segunda fila de cualquier test revienta el índice, no la
        // validación.
        $name = $this->faker->unique()->words(2, true);

        return [
            'slug'       => Str::random(22),
            'code'       => Str::slug($name, '_'),
            'name'       => $name,
            // Sin grupo por defecto: la FK es nullable y crear un grupo por
            // cada prueba de test llenaría el catálogo de ruido.
            'test_group_id'      => null,
            'has_control'        => false,
            'requires_control'   => false,
            'requires_duplicate' => false,
            'is_grouped'         => false,
            'replicates' => 1,
            'sort_order' => 0,
            'is_active'  => true,
        ];
    }

    /** Helper para tests que necesitan un nombre específico (asserts por nombre). */
    public function named(string $name): self
    {
        return $this->state(fn () => ['name' => $name]);
    }

    /** Helper para tests que fijan el código (la clave natural de la prueba). */
    public function coded(string $code): self
    {
        return $this->state(fn () => ['code' => $code]);
    }

    /** Cuelga la prueba de un grupo (el que se pase, o uno nuevo). */
    public function inGroup(TestGroup|int|null $group = null): self
    {
        return $this->state(fn () => [
            'test_group_id' => $group instanceof TestGroup
                ? $group->id
                : ($group ?? TestGroup::factory()->create()->id),
        ]);
    }

    /**
     * Prueba con control de calidad completo: la hoja no acepta muestras hasta
     * tener patrón y duplicado cargados.
     */
    public function controlled(): self
    {
        return $this->state(fn () => [
            'has_control'        => true,
            'requires_control'   => true,
            'requires_duplicate' => true,
        ]);
    }

    /** Medición repetida sobre la misma muestra (el caso de la rigidez). */
    public function replicated(int $times = 6): self
    {
        return $this->state(fn () => ['replicates' => $times]);
    }

    /** Helper para crear pruebas inactivas en tests de filtro. */
    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
