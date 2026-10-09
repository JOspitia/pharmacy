<?php

namespace Database\Factories;

use App\Models\Bodega;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bodega>
 */
class BodegaFactory extends Factory
{
    protected $model = Bodega::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique->word()) . ' ' . $this->faker->unique()->numberBetween(1, 9999),
            'description' => $this->faker->optional()->sentence(),
            // La columna es NOT NULL y el modelo solo la rellena si hay
            // sesion iniciada, cosa que no ocurre en las pruebas.
            'created_by' => User::factory(),
            'status' => true,
        ];
    }

    /** Bodega inactiva. */
    public function inactivo(): static
    {
        return $this->state(fn () => ['status' => false]);
    }
}