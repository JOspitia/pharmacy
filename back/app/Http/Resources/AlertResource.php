<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Clase para encapsular la respuesta de alertas hacia el frontend.
 * Diseñado para el endpoint de polling: GET /api/warehouses/{id}/alerts
 */
class AlertResource extends JsonResource
{
    /**
     * Transformar el recurso en un array.
     */
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'message'    => $this->message,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'reading'    => $this->whenLoaded('reading') && $this->reading
                ? [
                    'id'          => $this->reading->id,
                    'temperature' => (float) $this->reading->temperature,
                    'humidity'    => (float) $this->reading->humidity,
                    'recorded_at' => $this->reading->recorded_at ? $this->reading->recorded_at->toIso8601String() : null,
                    'sensor'      => $this->reading->sensor
                        ? [
                            'id'   => $this->reading->sensor->id,
                            'name' => $this->reading->sensor->name,
                            'warehouse' => $this->reading->sensor->warehouse
                                ? [
                                    'id'   => $this->reading->sensor->warehouse->id,
                                    'name' => $this->reading->sensor->warehouse->name,
                                ]
                                : null,
                        ]
                        : null,
                ]
                : null,
        ];
    }
}
