<?php

namespace Database\Factories;

use App\Models\Bodega;
use App\Models\Inventory;
use App\Models\Lot;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movement>
 */
class MovementFactory extends Factory
{
    protected $model = Movement::class;

    public function definition(): array
    {
        // movement_types ya existe en la base (seedeado desde la migration).
        $tipo = MovementType::inRandomOrder()->first()
            ?? MovementType::factory()->create();

        // Crear las dependencias sin factories anidados: el problema con
        // Inventory::factory() o Lot::factory() como valores de attributo es que
        // setRelations() de HasFactory confunde 'inventory_id' con la relacion
        // inventory() (que es un BelongsTo a Inventory). Se resuelven explicitamente.
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create();
        $existencia = Inventory::factory()->create([
            'warehouse_id' => $bodega->id,
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
        ]);
        $usuario = User::factory()->create();

        return [
            'inventory_id' => $existencia->id,
            'movement_type_id' => $tipo->id,
            'quantity' => $this->faker->numberBetween(1, 100),
            'reason' => $this->faker->optional()->sentence(),
            'performed_by' => $usuario->id,
        ];
    }
}
