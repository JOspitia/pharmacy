<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Exception;

/**
 * Controlador para los productos.
 */
class ProductController extends Controller
{
    /**
     * @var ProductService
     */
    protected ProductService $productService;

    /**
     * Inyectar las dependencias
     */
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Datos necesarios para cargar el formulario de productos.
     */
    public function formData()
    {
        return response()->json($this->productService->getFormData());
    }

    /**
     * Mostrar los productos existentes con paginación.
     */
    public function index()
    {
        return ProductResource::collection($this->productService->getAllProducts());
    }

    /**
     * Crear un nuevo producto.
     */
    public function store(StoreProductRequest $request)
    {
        try {
            $product = $this->productService->createProduct($request->validated());
            return response()->json([
                'message' => 'Producto creado con éxito',
                'data'    => new ProductResource($product)
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    /**
     * Mostrar un producto específico.
     */
    public function show(Product $product)
    {
        $product->load(['unit', 'group']);
        return new ProductResource($product);
    }

    /**
     * Actualizar un producto existente.
     */
    public function update(StoreProductRequest $request, Product $product)
    {
        try {
            $updated = $this->productService->updateProduct($product, $request->validated());
            return response()->json([
                'message' => 'Producto actualizado con éxito',
                'data'    => new ProductResource($updated)
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    /**
     * Eliminar un producto.
     */
    public function destroy(Product $product)
    {
        try {
            $this->productService->deleteProduct($product);
            return response()->json([
                'message' => 'Producto eliminado con éxito'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    /**
     * Cambiar el estado activo/inactivo de un producto.
     */
    public function toggleStatus(Product $product)
    {
        try {
            $updatedProduct = $this->productService->toggleStatus($product);
            $accion = $updatedProduct->status ? 'activado' : 'inactivado';

            return response()->json([
                'message' => "Producto {$accion} con éxito",
                'data' => new ProductResource($updatedProduct)
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }
}
