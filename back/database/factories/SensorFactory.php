<?php

namespace Database\Factories;

use App\Models\Sensor;
use App\Models\User;
use App\Models\Bodega;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sensor>
 */
class SensorFactory extends Factory
{
    protected $model = Sensor::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->word()) . ' Sensor',
            'description' => $this->faker->optional()->sentence(),
            // La columna es NOT NULL y el modelo solo la rellena si hay
            // sesion iniciada, cosa que no ocurre en las pruebas.
            'created_by' => User::factory(),
            'warehouse_id' => Bodega::factory(),
            'status' => true,
        ];
    }

    /** Sensor inactivo. */
    public function inactivo(): static
    {
        return $this->state(fn () => ['status' => false]);
    }
}
