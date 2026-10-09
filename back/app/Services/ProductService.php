<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Product;
use App\Models\Unit;
use Exception;

class ProductService
{
    /**
     * Datos necesarios para cargar el formulario de creación/edición de un producto.
     */
    public function getFormData(): array
    {
        return [
            'groups' => Group::where('status', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'units' => Unit::where('status', true)
                ->select('id', 'name', 'abbreviation')
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * Listar todos los productos con sus relaciones
     */
    public function getAllProducts(int $perPage = 15)
    {
        return Product::latest()
            ->with([
                'unit:id,name,abbreviation',
                'group:id,name',
            ])
            ->paginate($perPage);
    }

    /**
     * Crear un nuevo producto.
     */
    public function createProduct(array $data): Product
    {
        // Validar que las relaciones existan
        $this->validateRelations($data['unit_id'], $data['group_id']);
        // Validar que no existan duplicados
        $this->checkUniquenessConstraints($data);
        
        return Product::create($data);
    }

    /**
     * Actualizar un producto existente.
     */
    public function updateProduct(Product $product, array $data): Product
    {
        // Validar que las relaciones existan
        $this->validateRelations($data['unit_id'], $data['group_id']);
        // Validar que no existan duplicados
        $this->checkUniquenessConstraints($data, $product->id);
        
        $product->update($data);
        return $product;
    }

    /**
     * Activar o inactivar un producto.
     */
    public function toggleStatus(Product $product): Product
    {
        // Validar si el producto pertenece a un lote
        if ($this->hasActiveStockInLots($product)) {
            throw new Exception("No se puede eliminar, el producto tiene stock activo en uno o más lotes");
        }
        
        $product->status = !$product->status;
        $product->save();
        
        return $product;
    }

    /**
     * Eliminar un producto.
     */
    public function deleteProduct(Product $product): void
    {
        // Validar si el producto pertenece a un lote
        if ($this->hasActiveStockInLots($product)) {
            throw new Exception("No se puede eliminar, el producto tiene stock activo en uno o más lotes");
        }
        $product->status = false;
        $product->save();
        $product->delete();
    }

    /**
     * Validar que las relaciones existan
     */
    private function validateRelations(string $unitId, string $groupId): void
    {
        if (!Unit::where('id', $unitId)->where('status', true)->exists()) {
            throw new Exception("La unidad especificada no existe o está inactiva.");
        }

        if (!Group::where('id', $groupId)->where('status', true)->exists()) {
            throw new Exception("El grupo especificado no existe o está inactivo.");
        }
    }

    /**
     * Verificar si el producto pertenece a un lote
     */
    private function hasActiveStockInLots(Product $product): bool
    {
        return $product->lots()
            ->whereHas('inventories', function ($query) {
                $query->where('quantity', '>', 0)
                    ->whereNull('deleted_at');
            })
            ->exists();
    }
    
    /**
     * Verificar si existen productos con los mismos valores (sin incluir el producto actual)
     */
    private function checkUniquenessConstraints(array $data, ?string $productId = null): void
    {
        // Campos que no deben repetirse
        $fields = [
            'cum' => 'CUM', 
            'invima_registration' => 'registro Invima'
        ];   

        // Validar campos obligatorios
        foreach ($fields as $field => $fieldName) {
            $query = Product::whereRaw("LOWER({$field}) = ?", [strtolower($data[$field])]);
            
            if ($productId) {
                $query->where('id', '!=', $productId);
            }

            if ($query->exists()) {
                throw new Exception("El campo {$fieldName} con valor {$data[$field]} ya existe.", 409);
            }
        }
        
        // Verificar código de barras si no está vacío
        if (!empty($data['barcode'])) {
            $queryBarcode = Product::whereRaw("LOWER(barcode) = ?", [strtolower($data['barcode'])]);
            
            if ($productId) {
                $queryBarcode->where('id', '!=', $productId);
            }

            if ($queryBarcode->exists()) {
                throw new Exception("El campo código de barras con valor {$data['barcode']} ya existe.");
            }
        }
    }

}