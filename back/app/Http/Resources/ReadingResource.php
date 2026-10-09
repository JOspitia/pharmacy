<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Clase para definir los campos que se van a devolver en el JSON al frontend
 */
class ReadingResource extends JsonResource
{
    /**
     * Transformar el recurso en un array.
     */
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'sensor_id'   => $this->sensor_id,
            'temperature' => (float) $this->temperature,
            'humidity'    => (float) $this->humidity,
            'recorded_at' => $this->recorded_at ? $this->recorded_at->toIso8601String() : null,
            'sensor'      => $this->whenLoaded('sensor') && $this->sensor
                ? new SensorResource($this->sensor)
                : null,
            'alert'       => $this->whenLoaded('alert') && $this->alert
                ? [
                    'id'         => $this->alert->id,
                    'type'       => $this->alert->type,
                    'message'    => $this->alert->message,
                    'created_at' => $this->alert->created_at ? $this->alert->created_at->toIso8601String() : null,
                ]
                : null,
        ];
    }
}
