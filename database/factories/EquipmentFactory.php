<?php

namespace Database\Factories;

use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    public function definition(): array
    {
        return [
            'slug'      => Str::random(22),
            'name'      => $this->faker->unique()->words(2, true),
            'is_active' => true,
            'serial' => $this->faker->word(),
            'tag' => $this->faker->word(),
            'voltage_kv_hv' => $this->faker->randomFloat(2, 0, 9999),
            'voltage_kv_lv' => $this->faker->randomFloat(2, 0, 9999),
            'power_mva' => $this->faker->randomFloat(2, 0, 9999),
            'phases' => $this->faker->numberBetween(0, 1000),
            'manufacture_year' => $this->faker->numberBetween(0, 1000),
            'oil_volume' => $this->faker->randomFloat(2, 0, 9999),
            'external_ref' => $this->faker->word(),
        ];
    }

    /** Helper para tests que necesitan un nombre específico (asserts por nombre). */
    public function named(string $name): self
    {
        return $this->state(fn () => ['name' => $name]);
    }

    /** Helper para crear equipment inactivos en tests de filtro. */
    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
