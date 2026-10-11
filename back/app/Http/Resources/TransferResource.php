<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa un traslado (HU-09) hacia el frontend.
 *
 * Coherente con InventoryResource y MovementResource: expone strings
 * planos (compat con la doc HU-09 §10) y objetos anidados (id para
 * links de detalle). El estado, bodegas y actores se exponen como
 * sub-objetos {id, name} para que el frontend pueda renderizar
 * listas y enlaces sin pedir endpoints adicionales.
 *
 * El listado de items se delega a TransferItemResource para que las
 * reglas de transformacion de cada item (flags derivado, objects
 * anidados) vivan en su propio resource, no en este.
 */
class TransferResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,

            // Bodegas: el nombre aparece en la UI como "Bodega Origen -> Bodega Destino".
            'from_warehouse_id' => $this->from_warehouse_id,
            'to_warehouse_id' => $this->to_warehouse_id,
            'from_warehouse_name' => $this->bodegaOrigen?->name,
            'to_warehouse_name' => $this->bodegaDestino?->name,
            'from_warehouse' => $this->bodegaOrigen ? [
                'id' => $this->bodegaOrigen->id,
                'name' => $this->bodegaOrigen->name,
            ] : null,
            'to_warehouse' => $this->bodegaDestino ? [
                'id' => $this->bodegaDestino->id,
                'name' => $this->bodegaDestino->name,
            ] : null,

            // Estado: nombre tecnico y label en espanol.
            'status_id' => $this->status_id,
            'status_name' => $this->status?->name,
            'status_label' => $this->status?->label,
            'is_cancellable' => $this->status?->sePuedeAnular() ?? false,
            'is_terminal' => $this->status?->esTerminal() ?? false,
            'status' => $this->status ? [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'label' => $this->status->label,
            ] : null,

            // Actores: el frontend los muestra en el detalle del
            // traslado para auditoria rapida.
            'requested_by' => $this->requested_by,
            'requested_by_name' => $this->requestedBy?->name,
            'approved_by' => $this->approved_by,
            'approved_by_name' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->name),
            'dispatched_by' => $this->dispatched_by,
            'dispatched_by_name' => $this->whenLoaded('dispatchedBy', fn () => $this->dispatchedBy?->name),
            'received_by' => $this->received_by,
            'received_by_name' => $this->whenLoaded('receivedBy', fn () => $this->receivedBy?->name),
            'cancelled_by' => $this->cancelled_by,
            'cancelled_by_name' => $this->whenLoaded('cancelledBy', fn () => $this->cancelledBy?->name),

            // Timestamps de transicion: cuando paso cada fase.
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),

            'notes' => $this->notes,

            // Items: el detalle de que existencias se mueven.
            // TransferItemResource se encarga del shape de cada item.
            'items' => TransferItemResource::collection($this->whenLoaded('items')),

            // Discrepancias: solo presente si el traslado tuvo items
            // con delivered < approved al recibir. En otros estados
            // la relacion esta vacia y no se serializa.
            'discrepancies' => TransferDiscrepancyResource::collection($this->whenLoaded('discrepancies')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
