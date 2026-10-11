<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListMovementsRequest;
use App\Http\Requests\StoreMovementRequest;
use App\Http\Resources\MovementResource;
use App\Services\MovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de movimientos del Kardex (HU-08 §10).
 *
 * Solo traducion HTTP: recibe el request, llama al service y serializa la
 * respuesta con MovementResource. La conversion de excepciones de dominio a
 * codigos HTTP vive en Exceptions\Handler::register().
 */
class MovementController extends Controller
{
    public function __construct(private readonly MovementService $service)
    {
    }

    /**
     * GET /api/movements
     *
     * Filtros: inventory_id, type, performed_by, from_date, to_date.
     */
    public function index(ListMovementsRequest $request): JsonResponse
    {
        $filtros = $request->validated();

        $movements = $this->service->listar($filtros);

        $resource = MovementResource::collection($movements);

        // Mismo patron que InventoryController: el paginator ya agrega
        // links y un meta, asi que envolver a mano es la forma limpia de
        // controlar la forma del JSON sin pelearse con additional().
        return $resource->response()->setData([
            'data' => $resource->collection,
            'meta' => [
                'current_page' => $movements->currentPage(),
                'total' => $movements->total(),
                'per_page' => $movements->perPage(),
                'last_page' => $movements->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/movements
     */
    public function store(StoreMovementRequest $request): JsonResponse
    {
        $movimiento = $this->service->registrar(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'data' => new MovementResource($movimiento),
        ], 201);
    }

    // -------------------------------------------------------------------------
    // Inmutabilidad del Kardex (RN-06, CA-05)
    //
    // update y destroy existen para responder 405 con un mensaje util. La
    // capa 2 de inmutabilidad (trigger trg_movements_inmutable en la base)
    // es la garantia real, pero devolver 405 explicito le dice al cliente
    // "no lo intentes" sin tener que provocar el error de SQL.
    // -------------------------------------------------------------------------

    public function update(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'message' => 'El Kardex es inmutable. Para corregir un movimiento registre un ajuste con motivo.',
        ], 405);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'message' => 'El Kardex es inmutable. Para corregir un movimiento registre un ajuste con motivo.',
        ], 405);
    }
}
