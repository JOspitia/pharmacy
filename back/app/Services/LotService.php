<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\Inventory;
use App\Models\Lot;
use App\Models\Product;
use Exception;

class LotService
{

    /**
     * Obtener los datos necesarios para el formulario de lotes.
     *
     * El lote ya no elige bodega: la bodega se elige al registrar la entrada
     * en el Kardex, que es donde nace la existencia (HU-08). Por eso el
     * formulario de lotes ya no necesita la lista de bodegas.
     */
    public function getFormData(): array
    {
        return [
            'products' => Product::where('status', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]; 

    }

    /**
     * Obtener todos los lotes paginados.
     *
     * Ya no se carga la bodega del lote: el lote no pertenece a una bodega
     * (HU-08). Se cargan las existencias con su bodega y el Resource calcula
     * el stock total y las bodegas donde esta el lote.
     */
    public function getAllLots(int $perPage = 15)
    {
        return Lot::latest()
            ->with([
                'product:id,name',
                'inventories.bodega:id,name',
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
     *
     * El stock ya no esta en lots (HU-08, RN-01): vive en inventories, una
     * fila por bodega donde el lote esta. Un lote tiene stock si la suma de
     * sus existencias da mayor a cero, en cualquier bodega.
     *
     * Se consulta con una agregacion en vez de traer las relaciones cargadas
     * para no arrastrar N filas de inventario solo para sumar.
     */
    private function hasActiveStock(Lot $lot): bool
    {
        return Inventory::where('lot_id', $lot->id)->sum('quantity') > 0;
    }


}