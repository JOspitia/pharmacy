<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transformar el recurso en un array
     */
    public function toArray($request)   
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // El frontend necesita el rol para decidir que pantallas y acciones
            // mostrar. Sanctum resuelve la autenticacion, no la autorizacion:
            // el token dice quien es, el rol dice que puede hacer.
            'role' => $this->role,
        ];
    }
}
