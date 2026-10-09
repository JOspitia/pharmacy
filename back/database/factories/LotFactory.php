<?php

namespace Database\Factories;

use App\Models\Bodega;
use App\Models\Lot;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lot>
 */
class LotFactory extends Factory
{
    protected $model = Lot::class;

    public function definition(): array
    {
        return [
            'name' => 'LOTE-' . $this->faker->unique()->bothify('????-####'),
            'product_id' => Product::factory(),
            'warehouse_id' => Bodega::factory(),
            'stock' => $this->faker->numberBetween(0, 500),
            // Por defecto una fecha futura, porque el dominio exige que un lote
            // no se cree vencido. Para el caso vencido hay un estado propio.
            'expiration_date' => now()->addDays($this->faker->numberBetween(10, 720))->toDateString(),
            'description' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /** Lote con fecha de vencimiento en el pasado. */
    public function vencido(): static
    {
        return $this->state(fn () => [
            'expiration_date' => now()->subDays($this->faker->numberBetween(1, 200))->toDateString(),
        ]);
    }

    /** Lote por vencer dentro de la ventana de 30 dias. */
    public function porVencer(): static
    {
        return $this->state(fn () => [
            'stock' => $this->faker->numberBetween(1, 100),
            'expiration_date' => now()->addDays($this->faker->numberBetween(1, 29))->toDateString(),
        ]);
    }

    /** Lote sin unidades. */
    public function sinStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }

    /** Lote inactivo. */
    public function inactivo(): static
    {
        return $this->state(fn () => ['status' => false]);
    }
}