<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->word()),
            // La columna es NOT NULL y el modelo solo la rellena si hay
            // sesion iniciada, cosa que no ocurre en las pruebas.
            'created_by' => User::factory(),
            'status' => true,
        ];
    }

    /** Grupo inactivo. */
    public function inactivo(): static
    {
        return $this->state(fn () => ['status' => false]);
    }
}