<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBodegaRequest;
use App\Http\Resources\BodegaResource;
use App\Models\Bodega;
use App\Services\BodegaService;
use Exception;

/**
 * Controlador para las bodegas.
 */
class BodegaController extends Controller
{
    // Atributo para almacenar el servicio
    protected BodegaService $bodegaService;

    /**
     * Inyectar las dependencias necesarias.
     */
    public function __construct(BodegaService $bodegaService)
    {
        $this->bodegaService = $bodegaService;
    }

    /**
     * Mostrar las bodegas existentes
     */
    public function index()
    {
        return BodegaResource::collection($this->bodegaService->getAllBodegas());
    }

    /**
     * Crear una nueva bodega
     */
    public function store(StoreBodegaRequest $request)
    {
        try {
            $bodega = $this->bodegaService->createBodega($request->validated());
            return response()->json([
                'message' => 'Bodega creada con éxito', 
                'data' => new BodegaResource($bodega)
            ], 201);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Mostrar una bodega específica
     */
    public function show(Bodega $bodega)
    {
        return response()->json([
            'data' => new BodegaResource($bodega)
        ], 200);
    }

    /**
     * Actualizar una bodega
     */
    public function update(StoreBodegaRequest $request, Bodega $bodega)
    {
        try {
            $updatedBodega = $this->bodegaService->updateBodega($bodega, $request->validated());

            return response()->json([
                'message' => 'Bodega actualizada con éxito', 
                'data' => new BodegaResource($updatedBodega)
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Activar o inactivar una bodega
     */
    public function toggleStatus(Bodega $bodega)
    {
        try {
            $updatedBodega = $this->bodegaService->toggleStatus($bodega);
            $accion = $updatedBodega->status ? 'activado' : 'inactivado';

            return response()->json([
                'message' => "Bodega {$accion} con éxito", 
                'data' => new BodegaResource($updatedBodega)
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Eliminar una bodega
     */
    public function destroy(Bodega $bodega)
    {
        try {
            $this->bodegaService->deleteBodega($bodega);
            return response()->json(['message' => 'Bodega eliminada correctamente'], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }
}
