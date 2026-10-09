<?php

namespace Database\Factories;

use App\Models\Bodega;
use App\Models\Group;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(3, true)),
            'cum' => (string) $this->faker->unique()->numerify('##########-#'),
            'barcode' => (string) $this->faker->unique()->numerify('#############'),
            'invima_registration' => 'INVIMA ' . $this->faker->unique()->bothify('####M-#######'),
            'description' => $this->faker->optional()->sentence(),
            'group_id' => Group::factory(),
            'unit_id' => Unit::factory(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Producto inactivo.
     *
     * El estado se fija de forma explicita porque el modelo ya nace activo y
     * los tests necesitan poder crear el estado contrario.
     */
    public function inactivo(): static
    {
        return $this->state(fn () => ['status' => false]);
    }
}