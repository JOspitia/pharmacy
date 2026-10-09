<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Clase para definir los campos que se van a devolver en el JSON al frontend.
 */
class SensorResource extends JsonResource
{
    /**
     * Transformar el recurso en un array.
     */
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'status'      => $this->status,
            'warehouse' => $this->relationLoaded('warehouse') && $this->warehouse
                    ? (new BodegaResource($this->warehouse))->only(['id', 'name'])
                    : null,  
            'created_by'  => $this->created_by,
            'updated_by'  => $this->updated_by,
            'created_at'  => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at'  => $this->updated_at ? $this->updated_at->toIso8601String() : null,
            'deleted_at'  => $this->deleted_at,
        ];
    }
}
