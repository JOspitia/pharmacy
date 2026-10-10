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
            //
            // El rol es una relacion a roles, pero la clave 'role' sigue
            // exponiendo el nombre como string. Cambiar el contrato aqui
            // rompe AuthContext sin aviso, y ese campo no es un detalle de
            // implementacion: es la unica informacion de autorizacion que el
            // frontend recibe.
            'role' => $this->role?->name,
        ];
    }
}
