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
     *
     * HU-08 (RN-01): el lote ya no tiene bodega propia. Se cargan las
     * existencias con su bodega para que LotResource pueda calcular el stock
     * total y la lista de bodegas donde está.
     */
    public function show(Lot $lot)
    {
        $lot->load(['product', 'inventories.bodega']);
        return new LotResource($lot);
    }

    /**
     * Actualizar un lote existente.
     */
    public function update(StoreLotRequest $request, Lot $lot)
    {
        try {
            $this->lotService->updateLot($lot, $request->validated());
            $lot->load(['product', 'inventories.bodega']);

            return response()->json([
                'message' => 'Lote actualizado con éxito',
                'data' => new LotResource($lot),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
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
                'message' => 'Lote eliminado con éxito',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        }
    }

    /**
     * Cambiar el estado activo/inactivo de un lote.
     */
    public function toggleStatus(Lot $lot)
    {
        try {
            $this->lotService->toggleStatus($lot);
            $lot->load(['product', 'inventories.bodega']);
            $accion = $lot->status ? 'activado' : 'inactivado';

            return response()->json([
                'message' => "Lote {$accion} con éxito",
                'data' => new LotResource($lot),
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }
}
