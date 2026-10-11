<?php

namespace Database\Seeders;

use App\Models\Bodega;
use App\Models\Inventory;
use App\Models\Lot;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Movimientos historicos del Kardex (HU-08, RN-06).
 *
 * Genera una linea base de movimientos para que la pantalla /kardex
 * muestre data al validar el frontend, en lugar de estar vacia.
 *
 * Para cada existencia con stock > 0 en InventorySeeder, crea 2-3
 * movimientos sinteticos:
 *
 *   - Una ENTRY inicial (cantidad completa, fecha de hace 30 dias) con
 *     motivo "Carga inicial demo".
 *   - 0-2 movimientos menores aleatorios (ADJUSTMENT, WITHDRAWAL) para
 *     que el Kardex tenga variedad de tipos.
 *
 * Los movimientos se crean con `created_at` en el pasado (no respeta el
 * timestamp por defecto) para que la columna temporal tenga sentido:
 * si todos tuvieran la misma fecha, el orden del Kardex seria arbitrario.
 */
class KardexDemoSeeder extends Seeder
{
    public function run(): void
    {
        $entry = MovementType::where('name', 'ENTRY')->first();
        $adjustment = MovementType::where('name', 'ADJUSTMENT')->first();
        $withdrawal = MovementType::where('name', 'WITHDRAWAL')->first();

        if (! $entry || ! $adjustment || ! $withdrawal) {
            throw new \RuntimeException(
                'KardexDemoSeeder necesita los tipos de movimiento sembrados por la migration. '
                . 'Aplica las migraciones antes que este seeder.'
            );
        }

        $regente = User::whereHas('role', fn ($q) => $q->where('name', 'regente_farmacia'))->first();
        if (! $regente) {
            throw new \RuntimeException(
                'KardexDemoSeeder necesita un usuario regente_farmacia del UserSeeder.'
            );
        }

        $existencias = Inventory::with(['lot', 'bodega'])->get();

        if ($existencias->isEmpty()) {
            throw new \RuntimeException(
                'KardexDemoSeeder necesita existencias del InventorySeeder. '
                . 'Corre InventorySeeder antes que este seeder.'
            );
        }

        $contador = 0;
        foreach ($existencias as $existencia) {
            $stockActual = $existencia->quantity;

            if ($stockActual <= 0) {
                // Si la existencia esta vacia no simulamos historico: el
                // usuario puede registrar la primera ENTRY desde la UI.
                continue;
            }

            // ENTRY inicial: la cantidad COMPLETA, hace 30 dias.
            $this->crearMovimiento(
                $existencia,
                $entry,
                $regente->id,
                $stockActual,
                'Carga inicial demo',
                now()->subDays(30),
            );
            $contador++;

            // 0-2 movimientos menores: ajusta la mitad del stock
            // aleatoriamente para que la pantalla muestre variedad.
            $movimientosExtras = random_int(0, 2);
            for ($i = 0; $i < $movimientosExtras; $i++) {
                $cantidad = random_int(1, max(1, intdiv($stockActual, 2)));
                // Alternar entre ADJUSTMENT (suma) y WITHDRAWAL (resta).
                $esSuma = ($i % 2) === 0;
                $tipo = $esSuma ? $adjustment : $withdrawal;
                $delta = $esSuma ? $cantidad : -$cantidad;
                $motivo = $esSuma ? 'Ajuste por conteo fisico' : 'Retiro por vencimiento parcial';

                $this->crearMovimiento(
                    $existencia,
                    $tipo,
                    $regente->id,
                    $delta,
                    $motivo,
                    now()->subDays(random_int(1, 25)),
                );
                $contador++;
            }
        }

        $this->command?->info("KardexDemoSeeder: {$contador} movimientos creados.");
    }

    /**
     * Crea un movimiento y lo persiste con created_at retroactivo.
     *
     * Se hace un INSERT directo via Eloquent + setRawAttributes para que el
     * timestamp del Kardex respete la fecha del seed y no la fecha actual.
     */
    private function crearMovimiento(
        Inventory $existencia,
        MovementType $tipo,
        string $usuarioId,
        int $cantidad,
        string $motivo,
        \Carbon\Carbon $fecha,
    ): void {
        $movimiento = new Movement([
            'inventory_id' => $existencia->id,
            'movement_type_id' => $tipo->id,
            'quantity' => $cantidad,
            'reason' => $motivo,
            'reference_type' => null,
            'reference_id' => null,
            'performed_by' => $usuarioId,
        ]);

        // Forzar created_at: el modelo tiene timestamps en created_at
        // solamente (UPDATED_AT = null) pero created_at no acepta valores
        // por defecto via mass assignment, asi que se setea via setRawAttributes.
        $movimiento->setRawAttributes(
            array_merge($movimiento->getAttributes(), [
                'created_at' => $fecha,
            ]),
            true,
        );

        $movimiento->save();
    }
}
