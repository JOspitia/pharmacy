<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetAlertsRequest;
use App\Http\Resources\AlertResource;
use App\Http\Resources\ReadingResource;
use App\Services\ReadingService;
use Exception;
use Illuminate\Http\Request;

/**
 * Controlador delgado para la consulta de lecturas y alertas de sensores por bodega.
 * Toda la lógica de negocio está en ReadingService.
 */
class ReadingController extends Controller
{

    protected ReadingService $service;
    
    /**
     * Inyectar el servicio de lecturas.
     */
    public function __construct(ReadingService $service)
    {
        $this->service = $service;
        
    }

    /**
     * Listar lecturas paginadas para una bodega específica.
     */
    public function index(Request $request, string $bodegaId)
    {
        try {
            $readings = $this->service->getReadingsByBodega($bodegaId, $request->all());

            if ($readings === null) {
                return response()->json(['message' => 'Bodega no encontrada'], 404);
            }

            return ReadingResource::collection($readings);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Listar alertas paginadas para una bodega específica.
     * Diseñado para polling: usar ?created_after=ISO8601 para traer solo las nuevas.
     */
    public function alerts(GetAlertsRequest $request, string $bodegaId)
    {
        try {
            $alerts = $this->service->getAlertsByBodega($bodegaId, $request->all());

            if ($alerts === null) {
                return response()->json(['message' => 'Bodega no encontrada'], 404);
            }

            return AlertResource::collection($alerts);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
