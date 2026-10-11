<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Transfer;
use App\Models\TransferItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferItem>
 */
class TransferItemFactory extends Factory
{
    protected $model = TransferItem::class;

    public function definition(): array
    {
        return [
            'transfer_id' => Transfer::factory(),
            'inventory_id' => Inventory::factory(),
            'quantity' => $this->faker->numberBetween(1, 100),
            'received_quantity' => 0,
        ];
    }

    public function received(): static
    {
        return $this->state(fn () => [
            'received_quantity' => $this->faker->numberBetween(1, 100),
        ]);
    }
}
