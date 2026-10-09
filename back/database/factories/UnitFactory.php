<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        $nombre = $this->faker->unique()->randomElement([
            'Tableta', 'Capsula', 'Mililitro', 'Gramo',
            'Frasco', 'Ampolla', 'Sobre', 'Caja',
        ]);

        return [
            'name' => $nombre,
            'abbreviation' => mb_substr($nombre, 0, 3),
            'status' => true,
        ];
    }

    /** Unidad inactiva. */
    public function inactivo(): static
    {
        return $this->state(fn () => ['status' => false]);
    }
}