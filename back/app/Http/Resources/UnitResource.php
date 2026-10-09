<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Clase para definir los campos que se van a devolver en el JSON al frontend.
 */
class UnitResource extends JsonResource
{
    protected array $fields = [];

    /**
     * Solo obtener campos específicos
     */
    public function only(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * Transformar el recurso en un array.
     */
    public function toArray($request): array
    {
        $data =  [
            'id'           => $this->id,
            'name'         => $this->name,
            'abbreviation' => $this->abbreviation,
            'description'  => $this->description,
            'status'       => $this->status,
            'created_at'   => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at'   => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];

        // Filtrar campos si se especificaron
        if (!empty($this->fields)) {
            return array_intersect_key($data, array_flip($this->fields));
        }

        return $data;
    }
}
