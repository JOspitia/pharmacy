<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UnitResource;
use App\Http\Resources\GroupResource;

/*
 * Clase para definir los campos que se van a devolver en el JSON al frontend, ocultando información sensible
 */
class ProductResource extends JsonResource
{
    /**
     * Transformar el recurso en un array.
     */
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'cum'         => $this->cum,
            'barcode'     => $this->barcode,
            'invima_registration' => $this->invima_registration,
            'unit' => $this->relationLoaded('unit') && $this->unit
                ? (new UnitResource($this->unit))->only(['id', 'name', 'abbreviation'])
                : null,
            'group' => $this->relationLoaded('group') && $this->group
                ? (new GroupResource($this->group))->only(['id', 'name'])
                : null,
            'description' => $this->description,
            'status'      => $this->status,
            'created_by'  => $this->created_by,
            'updated_by'  => $this->updated_by,
            'created_at'  => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at'  => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}