<?php

namespace Database\Factories;

use App\Models\Reading;
use App\Models\Sensor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reading>
 */
class ReadingFactory extends Factory
{
    protected $model = Reading::class;

    public function definition(): array
    {
        return [
            // El id se genera en el boot del modelo si viene vacio.
            'sensor_id' => Sensor::factory(),
            'temperature' => $this->faker->randomFloat(2, 15, 35),
            'humidity' => $this->faker->randomFloat(2, 30, 90),
            // recorded_at es el unico timestamp; no usa $timestamps de Eloquent.
            'recorded_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
