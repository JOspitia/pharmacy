<?php

namespace Database\Factories;

use App\Models\Bodega;
use App\Models\Inventory;
use App\Models\Lot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 *
 * Factory de existencias (HU-08, RN-01).
 *
 * Resolucion explícita de relaciones: los IDs de lot y bodega se resuelven
 * antes del INSERT. Si se deja el factory lazy (Lot::factory() como valor de
 * atributo), Eloquent resuelve la FK a 0 y la base rechaza el UUID incompatible
 * con el int 0.
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        // Resolver aqui y no lazily para que los IDs esten disponibles
        // al momento del INSERT. Sin esto, lot_id recibe un factory object
        // que Eloquent convierte a 0, y la FK UUID de SQL Server lo rechaza.
        $lote = Lot::factory()->create();
        $bodega = Bodega::factory()->create();

        return [
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'warehouse_id' => $bodega->id,
            'quantity' => $this->faker->numberBetween(0, 200),
            'min_stock' => null,
        ];
    }

    public function conCantidad(int $cantidad): static
    {
        return $this->state(fn () => ['quantity' => $cantidad]);
    }

    public function vacia(): static
    {
        return $this->state(fn () => ['quantity' => 0]);
    }

    public function bajoMinimo(?int $minStock = null): static
    {
        return $this->state(fn () => [
            'min_stock' => $minStock ?? $this->faker->numberBetween(10, 50),
            'quantity' => $this->faker->numberBetween(0, 5),
        ]);
    }
}
