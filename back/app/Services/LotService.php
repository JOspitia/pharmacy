<?php

namespace App\Services;

use App\Models\Lot;
use App\Models\Product;
use App\Models\Bodega;
use Exception;

class LotService
{

    /**
     * Obtener los datos necesarios para el formulario de lotes.
     */
    public function getFormData(): array
    {
        return [
            'products' => Product::where('status', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'bodegas' => Bodega::where('status', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]; 

    }

    /**
     * Obtener todos los lotes paginados.
     */
    public function getAllLots(int $perPage = 15)
    {
        return Lot::latest()
            ->with([
                'product:id,name',
                'warehouse:id,name',
            ])
            ->paginate($perPage);
    }

    /**
     * Crear un lote.
     */
    public function createLot(array $data): Lot
    {
        // Validar que no existan duplicados
        $this->checkUniquenessConstraints($data);

        return Lot::create($data);
    }

    
    /**
     * Actualizar un lote existente.
     */
    public function updateLot(Lot $lot, array $data): Lot
    {
        // Validar que no existan duplicados
        $this->checkUniquenessConstraints($data, $lot->id);

        $lot->update($data);
        return $lot;
    }

    /**
     * Activar o inactivar un lote.
     */
    public function toggleStatus(Lot $lot): Lot
    {
        // Validar si el lote tiene stock activo
        if ($this->hasActiveStock($lot)) {
            throw new Exception("No se puede inactivar, el lote tiene stock activo");
        }
        
        $lot->status = !$lot->status;
        $lot->save();
        
        return $lot;
    }

    /**
     * Eliminar un lote.
     */
    public function deleteLot(Lot $lot): void
    {
        // Validar si el lote tiene stock activo
        if ($this->hasActiveStock($lot)) {
            throw new Exception("No se puede eliminar, el lote tiene stock activo");
        }
        
        $lot->status = false;
        $lot->save();
        $lot->delete();
    }

    /**
     * Verificar si existen lotes con los mismos valores
     */
    private function checkUniquenessConstraints(array $data, ?string $productId = null): void
    {
        // Campos que no deben repetirse
        $fields = [
            'name' => 'Nombre del lote', 
        ];   

        // Validar campos obligatorios
        foreach ($fields as $field => $fieldName) {
            $query = Lot::whereRaw("LOWER({$field}) = ?", [strtolower($data[$field])]);
            
            if ($productId) {
                $query->where('id', '!=', $productId);
            }

            if ($query->exists()) {
                throw new Exception("El campo {$fieldName} con valor {$data[$field]} ya existe.", 409);
            }
        }
        
    }

    /**
     * Verificar si el lote tiene stock activo.
     */
    private function hasActiveStock(Lot $lot): bool
    {
        return $lot->stock > 0;
    }


}