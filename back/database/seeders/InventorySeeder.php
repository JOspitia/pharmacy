<?php

namespace Database\Seeders;

use App\Models\Bodega;
use App\Models\Inventory;
use App\Models\Lot;
use Illuminate\Database\Seeder;

/**
 * Existencias iniciales para que la pantalla de Kardex (GET /api/inventories)
 * tenga data al validar el frontend.
 *
 * Distribuye los lotes del LotSeeder en las 3 bodegas del BodegaSeeder con
 * perfiles de stock pensados para ejercitar las 3 alertas del Inventario:
 *
 *   - Lote VENCIDO en Farmacia Central: stock > 0 -> aparece en alerts.expired.
 *   - Lote POR_VENCER en Farmacia Urgencias: stock BAJO (5) -> aparece en
 *     alerts.expiring_soon y tambien en alerts.low_stock si min_stock > 5.
 *   - Lote OK en Bodega Hospitalizacion: stock ALTO (100) -> sin alertas.
 *
 * Para evitar duplicados se chequea la terna UNIQUE(warehouse_id, product_id, lot_id)
 * de la tabla inventories. Si la fila ya existe se actualiza.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $bodegaCentral = Bodega::where('name', 'Farmacia Central')->first();
        $bodegaUrgencias = Bodega::where('name', 'Farmacia de Urgencias')->first();
        $bodegaHospi = Bodega::where('name', 'Bodega Hospitalizacion')->first();

        if (! $bodegaCentral || ! $bodegaUrgencias || ! $bodegaHospi) {
            throw new \RuntimeException(
                'InventorySeeder necesita las 3 bodegas del BodegaSeeder.'
            );
        }

        // Mapeo de bodega destino por cada lote. Todos los lotes de cada
        // producto se cargan en bodega Central (mas stock) y algunos van
        // a Urgencias o Hospitalizacion segun su perfil.
        $lotesPorSufijo = [
            'VENC' => $bodegaCentral,
            'PROX' => $bodegaUrgencias,
            'OK' => $bodegaHospi,
        ];

        $lotes = Lot::all();

        if ($lotes->isEmpty()) {
            throw new \RuntimeException(
                'InventorySeeder necesita lotes del LotSeeder. '
                . 'Corre LotSeeder antes que este seeder.'
            );
        }

        $contador = 0;
        foreach ($lotes as $lote) {
            // Extraer el sufijo del codigo: LOTE-2026-XX-VENC, -PROX, -OK
            $partes = explode('-', $lote->name);
            $sufijo = end($partes);
            $bodegaDestino = $lotesPorSufijo[$sufijo] ?? $bodegaCentral;

            // Stock segun perfil:
            //   - VENC: stock alto para que aparezca en alerts.expired
            //   - PROX: stock bajo (3) para que ademas aparezca en low_stock
            //     si min_stock del Inventory > 3
            //   - OK: stock alto (100), sin alertas
            $stock = match ($sufijo) {
                'VENC' => 25,
                'PROX' => 3,
                default => 100,
            };

            // min_stock por bodega: el de la bodega destino mas un plus
            // (asi el de PROX con stock=3 SIEMPRE cae en low_stock).
            $minStock = $bodegaDestino->stock_minimo + 10;

            Inventory::updateOrCreate(
                [
                    'warehouse_id' => $bodegaDestino->id,
                    'product_id' => $lote->product_id,
                    'lot_id' => $lote->id,
                ],
                [
                    'quantity' => $stock,
                    'min_stock' => $minStock,
                ],
            );
            $contador++;
        }

        $this->command?->info("InventorySeeder: {$contador} existencias creadas/actualizadas.");
    }
}
