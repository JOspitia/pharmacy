<?php

namespace App\Services;

use App\Models\Bodega;
use Illuminate\Database\Eloquent\Collection;
use Exception;

/**
 * Clase para el manejo de la lógica de negocio de las bodegas.
 */
class BodegaService
{
    /**
     * Obtener todas las bodegas
     */
    public function getAllBodegas(): Collection
    {
        return Bodega::latest()->get();
    }

    /**
     * Crear una nueva bodega.
     */
    public function createBodega(array $data): Bodega
    {
        return Bodega::create($data);
    }

    /**
     * Actualizar una bodega existente.
     */
    public function updateBodega(Bodega $bodega, array $data): Bodega
    {   
        $bodega->update($data);
        return $bodega;
    }

    /**
     * Activar o inactivar una bodega.
     */
    public function toggleStatus(Bodega $bodega): Bodega
    {
        // Si la bodega esta activa y tiene lotes activos, no se puede inactivar
        if ($bodega->status && $this->hasActiveLots($bodega)) {
            throw new Exception('No se puede inactivar, tiene lotes activos');
        }

        $bodega->status = !$bodega->status;
        $bodega->save();

        return $bodega;
    }

    /** 
     * Eliminar una bodega.
     */
    public function deleteBodega(Bodega $bodega): void
    {
        // Si la bodega tiene lotes activos, no se puede eliminar
        if ($this->hasActiveLots($bodega)) {
            throw new Exception('No se puede eliminar, tiene lotes activos');
        }   

        $bodega->status = false;
        $bodega->save();
        $bodega->delete();
    }

    /**
     * Verificar si la bodega tiene lotes activos.
     */
    private function hasActiveLots(Bodega $bodega): bool
    {
        return $bodega->lots()->exists();
    }       
}
