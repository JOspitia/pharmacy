<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa una existencia hacia el frontend (HU-08 §10).
 *
 * Expone la terna completa de RN-01 (bodega + producto + lote) y los
 * calculados de alerta: estado de vencimiento y deficit de stock minimo.
 */
class InventoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'lot_id' => $this->lot_id,
            'lot_name' => $this->lot?->name,
            'product_name' => $this->product?->name,
            'product_sku' => $this->product?->cum,
            'warehouse_name' => $this->bodega?->name,
            'quantity' => $this->quantity,
            'min_stock' => $this->min_stock,
            'expiration_date' => $this->lot?->expiration_date?->toDateString(),
            'days_until_expiration' => $this->diasParaVencer(),
            'expiration_status' => $this->estadoVencimiento(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
