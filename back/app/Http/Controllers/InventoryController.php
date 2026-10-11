<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListInventoriesRequest;
use App\Http\Resources\InventoryResource;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador de existencias (HU-08 §10).
 *
 * Solo lectura. Toda la escritura va por MovementController -> KardexService.
 */
class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $service)
    {
    }

    /**
     * GET /api/inventories
     *
     * Filtros: warehouse_id, product_id, lot_id, expiring_within, sort, order.
     * Orden default: expiration_date ASC (FEFO).
     */
    public function index(ListInventoriesRequest $request): JsonResponse
    {
        $filtros = $request->validated();

        $inventories = $this->service->listar($filtros);
        $alertas = $this->service->generarAlertas($filtros);

        $resource = InventoryResource::collection($inventories);

        // additional() sobre una ResourceCollection paginada se aplana en el
        // top-level del JSON. Pero los warnings de Laravel 9 sobre "additional
        // data with paginated resource" generan logs ruidosos. La forma
        // limpia es envolver a mano y agregar la seccion de alertas.
        return $resource->response()->setData([
            'data' => $resource->collection,
            'meta' => [
                'current_page' => $inventories->currentPage(),
                'total' => $inventories->total(),
                'per_page' => $inventories->perPage(),
                'last_page' => $inventories->lastPage(),
            ],
            'alerts' => $alertas,
        ]);
    }
}
