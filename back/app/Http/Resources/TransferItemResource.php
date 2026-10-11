<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa una linea de detalle de un traslado (HU-09, RN-07).
 *
 * Expone las tres quantities (requested, approved, delivered) con
 * strings planos para compat con la doc, y objetos anidados para
 * que el frontend pueda enlazar a detalle. Los helpers del modelo
 * (pendiente, recibidoCompleto, tieneDiscrepancia) se exponen como
 * flags booleanos para que el frontend no tenga que recalcular.
 */
class TransferItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $lote = $this->inventory?->lot;
        $producto = $this->inventory?->product;
        $bodega = $this->inventory?->bodega;

        return [
            'id' => $this->id,
            'transfer_id' => $this->transfer_id,
            'inventory_id' => $this->inventory_id,

            // Strings planos: compat con la doc HU-09 §10.
            'lot_name' => $lote?->name,
            'product_name' => $producto?->name,
            'product_sku' => $producto?->cum,
            'warehouse_name' => $bodega?->name,
            'lot_expiration_date' => $lote?->expiration_date?->toDateString(),

            // Las tres quantities: la diferencia entre ellas define
            // el estado del item.
            'requested_quantity' => $this->requested_quantity,
            'approved_quantity' => $this->approved_quantity,
            'delivered_quantity' => $this->delivered_quantity,
            'pending_quantity' => $this->pendiente(),

            // Flags derivados: el frontend los usa para decidir si
            // muestra la columna de discrepancia, el badge "recibido",
            // etc. Sin que tenga que recalcular.
            'is_fully_received' => $this->recibidoCompleto(),
            'has_discrepancy' => $this->tieneDiscrepancia(),

            // Objetos anidados: el frontend los consume asi para
            // tener disponible el id (links a detalle, navegacion).
            // Si la relacion no esta cargada, el sub-objeto es null.
            'lot' => $lote ? [
                'id' => $lote->id,
                'name' => $lote->name,
                'expiration_date' => $lote->expiration_date?->toDateString(),
            ] : null,
            'product' => $producto ? [
                'id' => $producto->id,
                'name' => $producto->name,
                'cum' => $producto->cum,
            ] : null,
            'warehouse' => $bodega ? [
                'id' => $bodega->id,
                'name' => $bodega->name,
            ] : null,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
