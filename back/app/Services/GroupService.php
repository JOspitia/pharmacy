<?php

namespace App\Services;

use App\Models\Group;
use Exception;

class GroupService
{
    /**
     * Obtener todos los grupos
     */
    public function getAllGroups()
    {
        return Group::latest()->get(); 
    }

    /**
     * Crear un nuevo grupo.
     */
    public function createGroup(array $data): Group
    {
        return Group::create($data);
    }

    /**
     * Actualizar un grupo existente.
     */
    public function updateGroup(Group $group, array $data): Group
    {
        if (!$group->status) {
            throw new Exception('No es posible realizar esta acción, recargue la página');
        }

        $group->update($data);
        return $group;
    }

    /**
     * Activar o inactivar un grupo.
     */
    public function toggleStatus(Group $group): Group
    {
        if ($group->status && $this->hasActiveProducts($group)) {
            throw new Exception('No se puede inactivar, tiene productos activos');
        }

        $group->status = !$group->status;
        $group->save();
        
        return $group;
    }

    /**
     * Eliminar un grupo.
     */
    public function deleteGroup(Group $group): void
    {
        if ($this->hasActiveProducts($group)) {
            throw new Exception('No se puede eliminar, tiene productos activos');
        }

        $group->status = false;
        $group->save();
        $group->delete();
    }

    /**
     * Verificar si el grupo tiene productos activos.
     */
    private function hasActiveProducts(Group $group): bool
    {
        return $group->products()->whereNull('deleted_at')->exists();
    }
}