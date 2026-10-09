<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLotRequest;
use App\Http\Resources\LotResource;
use App\Models\Lot;
use App\Services\LotService;
use Exception;

/**
 * Controlador para los lotes.
 */
class LotController extends Controller
{
    /**
     * @var LotService
     */
    protected LotService $lotService;

    /**
     * Inyectar las dependencias
     */
    public function __construct(LotService $lotService)
    {
        $this->lotService = $lotService;
    }

    /**
     * Datos necesarios para cargar el formulario de lotes.
     */
    public function formData()
    {
        return response()->json($this->lotService->getFormData());
    }

    /**
     * Mostrar los lotes existentes con paginación.
     */
    public function index()
    {
        return LotResource::collection($this->lotService->getAllLots());
    }

    /**
     * Crear un nuevo lote.
     */
     public function store(StoreLotRequest $request)
    {
        try {
            $lot = $this->lotService->createLot($request->validated());
            return response()->json([
                'message' => 'Lote creado con éxito',
                'data'    => new LotResource($lot)
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    /**
     * Mostrar un lote específico.
     */
    public function show(Lot $lot)
    {
        $lot->load(['product', 'warehouse']);
        return new LotResource($lot);
    }

    /**
     * Actualizar un lote existente.
     */
    public function update(StoreLotRequest $request, Lot $lot)
    {
        try {
            $updated = $this->lotService->updateLot($lot, $request->validated());
            return response()->json([
                'message' => 'Lote actualizado con éxito',
                'data'    => new LotResource($updated)
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    /**
     * Eliminar un lote.
     */
    public function destroy(Lot $lot)
    {
        try {
            $this->lotService->deleteLot($lot);
            return response()->json([
                'message' => 'Lote eliminado con éxito'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    /**
     * Cambiar el estado activo/inactivo de un lote.
     */
    public function toggleStatus(Lot $lot)
    {
        try {
            $updatedLot = $this->lotService->toggleStatus($lot);
            $accion = $updatedLot->status ? 'activado' : 'inactivado';

            return response()->json([
                'message' => "Lote {$accion} con éxito",
                'data' => new LotResource($updatedLot)
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }
}
