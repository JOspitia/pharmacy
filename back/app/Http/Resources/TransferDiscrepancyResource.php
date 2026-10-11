<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa una discrepancia de recepcion de un traslado (HU-09, RN-07).
 *
 * Una discrepancia se crea cuando delivered < approved al recibir
 * un traslado. La diferencia queda PENDING hasta que se resuelva
 * (o se asuma la perdida como WRITTEN_OFF). La correccion de stock
 * NO la hace este resource: la hace el usuario via Kardex (RN-06).
 */
class TransferDiscrepancyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'transfer_id' => $this->transfer_id,
            'transfer_item_id' => $this->transfer_item_id,
            'approved_quantity' => $this->approved_quantity,
            'delivered_quantity' => $this->delivered_quantity,
            'difference' => $this->difference,
            'status' => $this->status,
            'resolution_notes' => $this->resolution_notes,
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->resolved_at?->toIso8601String(),

            // Flags derivados.
            'is_pending' => $this->isPending(),
            'is_resolved' => $this->isResolved(),

            // Resolutor: el usuario que cerro la discrepancia. Solo
            // presente si status es RESOLVED o WRITTEN_OFF.
            'resolved_by_user' => $this->whenLoaded('resolvedBy', function () {
                return $this->resolvedBy ? [
                    'id' => $this->resolvedBy->id,
                    'name' => $this->resolvedBy->name,
                ] : null;
            }),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
