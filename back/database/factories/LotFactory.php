<?php

namespace Database\Factories;

use App\Models\Bodega;
use App\Models\Inventory;
use App\Models\Lot;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lot>
 *
 * CAMBIO HU-08 (RN-01): el lote ya no tiene stock ni bodega.
 *
 * El stock pertenece a la existencia (bodega x producto x lote), y el lote es
 * un atributo del producto. Por eso este factory crea solo el lote, y el stock
 * se crea aparte con InventoryFactory, o con el estado conExistencia() que
 * encadena una existencia en una bodega concreta.
 *
 * Los estados conStock()/sinStock() se conservan con el nombre que ya usan los
 * tests, pero ahora aplica sobre la existencia que se crea junto al lote, no a una
 * columna del lote.
 */
class LotFactory extends Factory
{
    protected $model = Lot::class;

    public function definition(): array
    {
        return [
            'name' => 'LOTE-' . $this->faker->unique()->bothify('????-####'),
            'product_id' => Product::factory(),
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
            'expiration_date' => now()->addDays($this->faker->numberBetween(1, 29))->toDateString(),
        ]);
    }

    /**
     * Lote con una existencia en una bodega, con la cantidad indicada.
     *
     * Es el estado que reemplaza a conStock(): el stock ya no es una columna
     * del lote, es una fila de inventories que lo referencia.
     */
    public function conExistencia(?Bodega $bodega = null, int $cantidad = 0): static
    {
        return $this->afterCreating(function (Lot $lote) use ($bodega, $cantidad) {
            Inventory::factory()->create([
                'lot_id' => $lote->id,
                'product_id' => $lote->product_id,
                'warehouse_id' => $bodega?->id ?? Bodega::factory(),
                'quantity' => $cantidad,
            ]);
        });
    }

    /** Lote con unidades en una bodega. */
    public function conStock(?Bodega $bodega = null): static
    {
        return $this->conExistencia($bodega, $this->faker->numberBetween(1, 500));
    }

    /**
     * Lote con una existencia en cero.
     *
     * Antes era una columna stock = 0; ahora es una existencia con quantity 0.
     * La diferencia importa: el lote existe en la bodega pero sin unidades,
     * que es el estado real de un lote recibido y todavia no-location.
     */
    public function sinStock(?Bodega $bodega = null): static
    {
        return $this->conExistencia($bodega, 0);
    }

    /** Lote inactivo. */
    public function inactivo(): static
    {
        return $this->state(fn () => ['status' => false]);
    }
}