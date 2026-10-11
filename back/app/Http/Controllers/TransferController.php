<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveTransferRequest;
use App\Http\Requests\CancelTransferRequest;
use App\Http\Requests\ReceiveTransferRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller de traslados (HU-09, RN-07).
 *
 * Solo traduccion HTTP: recibe el request, llama al service y
 * serializa con TransferResource. La conversion de excepciones de
 * dominio a codigos HTTP vive en Exceptions\Handler::register().
 *
 * Endpoints:
 *
 *   GET    /api/transfers                  index   (listar con filtros)
 *   GET    /api/transfers/{id}             show    (detalle)
 *   POST   /api/transfers                  store   (crear en DRAFT)
 *   PATCH  /api/transfers/{id}/approve     approve (RN-08 + RN-01)
 *   PATCH  /api/transfers/{id}/dispatch    dispatch (TRANSFER_OUT)
 *   PATCH  /api/transfers/{id}/receive     receive  (TRANSFER_IN + discrepancias)
 *   PATCH  /api/transfers/{id}/cancel      cancel   (is_cancellable)
 *
 * El `module:transfers` middleware se encarga del 403 segun rol.
 * El service valida las reglas de negocio (RN-01, RN-08, RN-07,
 * RN-03) y registra los movimientos del Kardex.
 */
class TransferController extends Controller
{
    public function __construct(private readonly TransferService $service)
    {
    }

    /**
     * GET /api/transfers
     *
     * Filtros (query string): status_id, from_warehouse_id,
     * to_warehouse_id, requested_by, date_from, date_to.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only([
            'status_id',
            'from_warehouse_id',
            'to_warehouse_id',
            'requested_by',
            'date_from',
            'date_to',
        ]);

        $traslados = $this->service->listar($filtros);

        $resource = TransferResource::collection($traslados);

        return $resource->response()->setData([
            'data' => $resource->collection,
            'meta' => [
                'current_page' => $traslados->currentPage(),
                'total' => $traslados->total(),
                'per_page' => $traslados->perPage(),
                'last_page' => $traslados->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/transfers/{id}
     */
    public function show(string $id): JsonResponse
    {
        $transfer = Transfer::with([
            'items', 'items.inventory.lot', 'items.inventory.product', 'items.inventory.bodega',
            'status',
            'bodegaOrigen', 'bodegaDestino',
            'requestedBy', 'approvedBy', 'dispatchedBy', 'receivedBy', 'cancelledBy',
            'discrepancies',
        ])->findOrFail($id);

        return response()->json([
            'data' => new TransferResource($transfer),
        ]);
    }

    /**
     * POST /api/transfers
     *
     * Crea el traslado en estado DRAFT. La HU-09 §10 define que el
     * body NO trae from_warehouse_id: el service lo deduce del
     * inventory_id de cada item (todos los items deben ser de la
     * misma bodega).
     *
     * Despues de crear, cargamos las relaciones (items, status,
     * bodegas) para que la respuesta del POST ya incluya los items
     * y no requiera un GET posterior. Coherente con la forma de
     * GET /api/transfers/{id}.
     */
    public function store(StoreTransferRequest $request): JsonResponse
    {
        $transfer = $this->service->crear(
            $request->validated(),
            $request->user()
        );

        // refresh() antes de load(): re-lee el modelo de la BD para
        // obtener el id normalizado (SQL Server lo guarda en MAYUSCULAS
        // aunque Str::uuid() lo genere en minusculas). Sin esto, el
        // load('items') no encuentra nada porque hace WHERE con el id
        // viejo (minusculas) contra la BD (mayusculas).
        $transfer->refresh();

        $transfer->load([
            'items', 'items.inventory.lot', 'items.inventory.product', 'items.inventory.bodega',
            'status',
            'bodegaOrigen', 'bodegaDestino',
            'requestedBy',
        ]);

        return response()->json([
            'data' => new TransferResource($transfer),
        ], 201);
    }

    /**
     * PATCH /api/transfers/{id}/approve
     *
     * DRAFT/REQUESTED -> APPROVED. Valida RN-08 (segregacion) y
     * RN-01 (lote no vencido) dentro de la transaccion del service.
     */
    public function approve(ApproveTransferRequest $request, string $id): JsonResponse
    {
        $transfer = Transfer::findOrFail($id);

        $transfer = $this->service->aprobar(
            $transfer,
            $request->validated()['items'] ?? [],
            $request->user()
        );

        return response()->json([
            'data' => new TransferResource($transfer),
        ]);
    }

    /**
     * PATCH /api/transfers/{id}/dispatch
     *
     * APPROVED -> IN_TRANSIT. Registra Kardex TRANSFER_OUT por
     * cada item aprobado. El service re-valida RN-01.
     *
     * Se llama dispatchTransfer() y NO dispatch() porque Controller
     * base ya tiene un metodo dispatch($job) para encolar jobs de
     * Laravel, y la firma choca.
     */
    public function dispatchTransfer(string $id, Request $request): JsonResponse
    {
        $transfer = Transfer::findOrFail($id);

        $transfer = $this->service->despachar($transfer, $request->user());

        return response()->json([
            'data' => new TransferResource($transfer),
        ]);
    }

    /**
     * PATCH /api/transfers/{id}/receive
     *
     * IN_TRANSIT -> RECEIVED o PARTIALLY_RECEIVED. Resuelve la
     * existencia destino (puede no existir) y registra Kardex
     * TRANSFER_IN. Si delivered < approved, crea discrepancia.
     */
    public function receive(ReceiveTransferRequest $request, string $id): JsonResponse
    {
        $transfer = Transfer::findOrFail($id);

        $transfer = $this->service->recibir(
            $transfer,
            $request->validated()['items'] ?? [],
            $request->user()
        );

        return response()->json([
            'data' => new TransferResource($transfer),
        ]);
    }

    /**
     * PATCH /api/transfers/{id}/cancel
     *
     * Cualquier estado cancelable -> CANCELLED. La verificacion
     * de is_cancellable se hace en el service contra
     * transfer_statuses, no en codigo.
     */
    public function cancel(CancelTransferRequest $request, string $id): JsonResponse
    {
        $transfer = Transfer::findOrFail($id);

        $transfer = $this->service->anular(
            $transfer,
            $request->validated()['reason'] ?? null,
            $request->user()
        );

        return response()->json([
            'data' => new TransferResource($transfer),
        ]);
    }
}
