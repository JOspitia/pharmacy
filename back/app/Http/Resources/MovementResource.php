<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa un movimiento del Kardex hacia el frontend (HU-08 §10).
 *
 * El movimiento es inmutable (RN-06): una vez creado no se modifica ni se borra.
 * El response incluye el inventory actualizado y los datos del usuario que lo
 * realizo para trazabilidad.
 */
class MovementResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'inventory_id' => $this->inventory_id,
            'type' => $this->type?->name,
            'type_label' => $this->type?->description,
            'quantity' => $this->quantity,
            'reason' => $this->reason,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'performed_by' => $this->performed_by,
            'performed_by_name' => $this->performedByUser?->name,
            'created_at' => $this->created_at?->toIso8601String(),

            // Inventory anidado: necesario para que el frontend sepa cuanto
            // stock queda en la existencia tras el movimiento.
            'inventory' => $this->whenLoaded('inventory', function () {
                return [
                    'id' => $this->inventory?->id,
                    'quantity' => $this->inventory?->quantity,
                    'lot_name' => $this->inventory?->lot?->name,
                    'product_name' => $this->inventory?->product?->name,
                    'warehouse_name' => $this->inventory?->bodega?->name,
                ];
            }),
        ];
    }
}
