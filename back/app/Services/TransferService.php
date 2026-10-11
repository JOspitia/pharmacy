<?php

namespace App\Services;

use App\Exceptions\KardexStateException;
use App\Exceptions\StockInsuficienteException;
use App\Models\Inventory;
use App\Models\MovementType;
use App\Models\Transfer;
use App\Models\TransferDiscrepancy;
use App\Models\TransferItem;
use App\Models\TransferStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Logica de negocio de traslados (HU-09, RN-07).
 *
 * El controller solo traduce HTTP. Toda la logica vive aca:
 *   - validaciones de negocio (no de formato: las de formato
 *     viven en StoreTransferRequest, ApproveTransferRequest, etc.)
 *   - transiciones de la maquina de 7 estados
 *   - escritura de movimientos del Kardex al despachar/recibir
 *   - generacion de discrepancias cuando delivered < approved
 *
 * Tres reglas de negocio concentran la complejidad:
 *
 *   RN-01 (HU-08): un lote vencido no se aprueba ni se despacha.
 *     El service chequea expiration_date < today en approve() y
 *     dispatch() y tira KardexStateException con el mensaje de la
 *     HU-09 §11 (TT-03).
 *
 *   RN-08: quien solicita no aprueba. En approve() se valida que
 *     $transfer->requested_by !== $usuario->id; si coinciden, se
 *     tira KardexStateException.
 *
 *   RN-07 / RN-03: el stock se mueve de origen a destino. En
 *     dispatch() se hace un Kardex TRANSFER_OUT por item; en
 *     receive() se hace un Kardex TRANSFER_IN por item, ambos
 *     dentro de la misma DB::transaction con lockForUpdate.
 *
 * Todas las operaciones de escritura van con DB::transaction.
 * Las validaciones de stock, lote vencido y segregacion se hacen
 * DENTRO de la transaccion, no antes: si la validacion pasa pero
 * la escritura falla (por ejemplo, FK violada, stock concurrente
 * consumido), la transaccion hace rollback y no queda nada a medias.
 */
class TransferService
{
    public function __construct(private readonly KardexService $kardex)
    {
    }

    /**
     * Crea un traslado en estado DRAFT (estado inicial, lo asigna el
     * modelo en su creating()).
     *
     * Validaciones de negocio que no pueden estar en el FormRequest:
     *   - Todos los items deben pertenecer a la MISMA bodega (la
     *     bodega origen). Si vienen de bodegas distintas, se tira
     *     KardexStateException: un traslado sale de UNA bodega.
     *   - Cada item: requested_quantity <= inventory.quantity
     *     (RN-01, stock disponible en origen).
     *
     * Se hace todo dentro de DB::transaction con lockForUpdate
     * sobre los inventarios, para que dos requests simultaneos no
     * vean stock que el otro se llevo.
     *
     * @param  array{to_warehouse_id:string, notes:?string, items:array<int,array{inventory_id:string, requested_quantity:int}>}  $datos
     */
    public function crear(array $datos, ?User $usuario): Transfer
    {
        if ($usuario === null) {
            throw new KardexStateException('Se necesita un usuario autenticado para crear un traslado.');
        }

        return DB::transaction(function () use ($datos, $usuario) {
            // Normalizar el UUID del body del cliente a mayusculas: SQL
            // Server devuelve los UUIDs en MAYUSCULAS, pero el cliente
            // puede enviarlos en minusculas. Sin normalizar, la
            // comparacion con from_warehouse_id (que SI viene de la BD
            // en mayusculas) falla.
            $toWarehouseId = strtoupper($datos['to_warehouse_id']);

            $itemsPayload = $datos['items'];
            $inventoryIds = collect($itemsPayload)->pluck('inventory_id')->all();

            // Una sola query con lockForUpdate: trae todas las existencias
            // involucradas, indexadas por id, con su cantidad y bodega.
            // El lock es pesimista a proposito: si otro request esta
            // viendo el mismo inventario, esperamos.
            //
            // SQL Server devuelve los UUIDs en MAYUSCULAS, pero el cliente
            // los puede enviar en minusculas. Indexamos por id en mayusculas
            // para que keyBy('id') y get() matcheen independientemente del
            // case que el cliente haya usado.
            $inventarios = Inventory::whereIn('id', $inventoryIds)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn ($inv) => strtoupper($inv->id));

            if ($inventarios->count() !== count(array_unique($inventoryIds))) {
                throw new KardexStateException('Uno o mas inventarios seleccionados no existen.');
            }

            // Todos los items deben ser de la MISMA bodega origen.
            $warehouseIds = $inventarios->pluck('warehouse_id')->unique();
            if ($warehouseIds->count() > 1) {
                throw new KardexStateException(
                    'Todos los items del traslado deben pertenecer a la misma bodega de origen. '
                    . 'Se recibieron items de ' . $warehouseIds->count() . ' bodegas distintas.'
                );
            }

            $fromWarehouseId = $warehouseIds->first();

            if (strtoupper((string) $fromWarehouseId) === strtoupper((string) $toWarehouseId)) {
                throw new KardexStateException(
                    'La bodega de origen y destino no pueden ser la misma.'
                );
            }

            // Stock suficiente para cada item.
            foreach ($itemsPayload as $pos => $item) {
                $inv = $inventarios->get(strtoupper($item['inventory_id']));
                if ($inv === null) {
                    throw new KardexStateException("El inventario {$item['inventory_id']} no existe.");
                }
                if ($item['requested_quantity'] > $inv->quantity) {
                    throw new StockInsuficienteException(sprintf(
                        'Stock insuficiente: el ítem %d pide %d unidades y solo hay %d disponibles en la bodega de origen.',
                        $pos + 1,
                        $item['requested_quantity'],
                        $inv->quantity
                    ));
                }
            }

            // Crear el encabezado + items. El modelo setea transfer_number
            // y requested_by en su creating().
            $transfer = new Transfer();
            $transfer->from_warehouse_id = $fromWarehouseId;
            $transfer->to_warehouse_id = $toWarehouseId;
            $transfer->notes = $datos['notes'] ?? null;
            $transfer->save();

            foreach ($itemsPayload as $item) {
                TransferItem::create([
                    'transfer_id' => $transfer->id,
                    'inventory_id' => $item['inventory_id'],
                    'requested_quantity' => $item['requested_quantity'],
                    'delivered_quantity' => 0,
                ]);
            }

            // El controller se encarga de cargar las relaciones que
            // necesita para la respuesta. Devolver el modelo "pelado"
            // evita una query extra (load) y le da al controller el
            // control sobre el shape.
            return $transfer;
        });
    }

    /**
     * Lista traslados con filtros.
     *
     * @param  array{status_id?:?string, from_warehouse_id?:?string, to_warehouse_id?:?string, requested_by?:?int, date_from?:?string, date_to?:?string}  $filtros
     */
    public function listar(array $filtros): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        // `discrepancies` se carga SOLO cuando sigue PENDING. El listado
        // existe para que el usuario vea que hay algo esperando decision;
        // traer tambien las ya resueltas infla el payload sin aportar nada
        // ahi. El detalle (show) si las trae todas.
        $query = Transfer::query()->with([
            'items',
            'status',
            'bodegaOrigen',
            'bodegaDestino',
            'requestedBy',
            'discrepancies' => fn ($q) => $q->where('status', TransferDiscrepancy::STATUS_PENDING),
        ]);

        if (! empty($filtros['status_id'])) {
            $query->where('status_id', $filtros['status_id']);
        }
        if (! empty($filtros['from_warehouse_id'])) {
            $query->where('from_warehouse_id', $filtros['from_warehouse_id']);
        }
        if (! empty($filtros['to_warehouse_id'])) {
            $query->where('to_warehouse_id', $filtros['to_warehouse_id']);
        }
        if (! empty($filtros['requested_by'])) {
            $query->where('requested_by', $filtros['requested_by']);
        }
        if (! empty($filtros['date_from'])) {
            $query->whereDate('created_at', '>=', $filtros['date_from']);
        }
        if (! empty($filtros['date_to'])) {
            $query->whereDate('created_at', '<=', $filtros['date_to']);
        }

        return $query->orderByDesc('created_at')->paginate(20);
    }

    /**
     * Aprueba un traslado (estado DRAFT o REQUESTED -> APPROVED).
     *
     * Validaciones de negocio (ademas de las del FormRequest):
     *   - Estado: solo DRAFT o REQUESTED pueden aprobarse.
     *   - RN-08: requested_by != usuario actual.
     *   - RN-01: para cada item, lot.expiration_date >= today.
     *
     * @param  array<int, array{transfer_item_id:string, approved_quantity:int}>  $itemsAprobados
     */
    public function aprobar(Transfer $transfer, array $itemsAprobados, ?User $usuario): Transfer
    {
        if ($usuario === null) {
            throw new KardexStateException('Se necesita un usuario autenticado para aprobar un traslado.');
        }

        // El middleware EnsureModule solo conoce el verbo HTTP: un PATCH
        // exige transfers.can_update, que el auxiliar tiene. La capacidad
        // `aprobar-traslado` es mas fina y hay que preguntarla aca.
        // Gate::authorize() lanza AuthorizationException, que Laravel
        // traduce a 403 (a diferencia de KardexStateException, que es 422).
        Gate::forUser($usuario)->authorize('aprobar-traslado');

        return DB::transaction(function () use ($transfer, $itemsAprobados, $usuario) {
            $transfer = Transfer::whereKey($transfer->id)->lockForUpdate()->first();

            $this->asegurarEstadoEn($transfer, ['DRAFT', 'REQUESTED']);

            // RN-08: quien solicita no aprueba.
            if ((int) $transfer->requested_by === (int) $usuario->id) {
                throw new KardexStateException(
                    'El aprobador no puede ser el mismo que solicito el traslado (RN-08).'
                );
            }

            // Map transfer_item_id => approved_quantity para validar y aplicar.
            $aprobados = collect($itemsAprobados)->keyBy('transfer_item_id');

            $items = $transfer->items()->lockForUpdate()->get();

            foreach ($items as $item) {
                $aprobado = $aprobados->get($item->id);
                if ($aprobado === null) {
                    throw new KardexStateException("Falta la cantidad aprobada para el item {$item->id}.");
                }
                if ($aprobado['approved_quantity'] > $item->requested_quantity) {
                    throw new KardexStateException(sprintf(
                        'La cantidad aprobada (%d) no puede superar la solicitada (%d) en el item %s.',
                        $aprobado['approved_quantity'],
                        $item->requested_quantity,
                        $item->id
                    ));
                }

                // RN-01: lote no vencido.
                $lote = $item->inventory?->lot;
                if ($lote !== null && $lote->expiration_date !== null) {
                    $hoy = \Illuminate\Support\Carbon::today();
                    if ($lote->expiration_date->lt($hoy)) {
                        throw new KardexStateException(
                            "No se pueden aprobar traslados con lotes vencidos (RN-01). Lote {$lote->name} vencio el {$lote->expiration_date->toDateString()}."
                        );
                    }
                }

                $item->approved_quantity = $aprobado['approved_quantity'];
                $item->save();
            }

            $transfer->status_id = TransferStatus::porNombre('APPROVED')->id;
            $transfer->approved_by = $usuario->id;
            $transfer->save();

            return $transfer->fresh(['items', 'status']);
        });
    }

    /**
     * Despacha un traslado (estado APPROVED -> IN_TRANSIT).
     *
     * Es el unico momento en que se mueve stock del origen. Se
     * registra un Kardex TRANSFER_OUT por cada item aprobado. El
     * RN-01 (lote no vencido) se vuelve a validar por si vencio
     * despues de aprobar.
     */
    public function despachar(Transfer $transfer, ?User $usuario): Transfer
    {
        if ($usuario === null) {
            throw new KardexStateException('Se necesita un usuario autenticado para despachar un traslado.');
        }

        // Ver la nota en aprobar(): el verbo PATCH no distingue despachar
        // de cualquier otra edicion, la capacidad si.
        Gate::forUser($usuario)->authorize('despachar-traslado');

        $tipo = MovementType::porNombre('TRANSFER_OUT');
        if ($tipo === null) {
            throw new KardexStateException('Tipo de movimiento TRANSFER_OUT no existe en la base.');
        }

        return DB::transaction(function () use ($transfer, $usuario, $tipo) {
            $transfer = Transfer::whereKey($transfer->id)->lockForUpdate()->first();
            $this->asegurarEstadoEn($transfer, ['APPROVED']);

            $items = $transfer->items()->lockForUpdate()->get();

            foreach ($items as $item) {
                $aprobado = $item->approved_quantity ?? 0;
                if ($aprobado <= 0) {
                    continue; // items no aprobados no se mueven
                }

                // RN-01: re-validar vencimiento por si vencio despues de aprobar.
                $lote = $item->inventory?->lot;
                if ($lote !== null && $lote->expiration_date !== null) {
                    $hoy = \Illuminate\Support\Carbon::today();
                    if ($lote->expiration_date->lt($hoy)) {
                        throw new KardexStateException(
                            "No se pueden despachar traslados con lotes vencidos (RN-01). Lote {$lote->name} vencio el {$lote->expiration_date->toDateString()}."
                        );
                    }
                }

                $this->kardex->registrarMovimiento(
                    $item->inventory,
                    $tipo,
                    $aprobado,
                    $usuario,
                    'Transfer',
                    $transfer->id,
                    "Despacho de traslado {$transfer->transfer_number}"
                );
            }

            $transfer->status_id = TransferStatus::porNombre('IN_TRANSIT')->id;
            $transfer->dispatched_by = $usuario->id;
            $transfer->dispatched_at = now();
            $transfer->save();

            return $transfer->fresh(['items', 'status']);
        });
    }

    /**
     * Recibe un traslado (estado IN_TRANSIT -> RECEIVED o PARTIALLY_RECEIVED).
     *
     * Crea o resuelve la existencia en la bodega destino (la terna
     * bodega×producto×lote puede no existir ahi todavia) y registra
     * un Kardex TRANSFER_IN por la cantidad efectivamente recibida.
     * Si delivered < approved para un item, se crea una discrepancia
     * y el traslado termina en PARTIALLY_RECEIVED.
     *
     * @param  array<int, array{transfer_item_id:string, delivered_quantity:int}>  $itemsRecibidos
     */
    public function recibir(Transfer $transfer, array $itemsRecibidos, ?User $usuario): Transfer
    {
        if ($usuario === null) {
            throw new KardexStateException('Se necesita un usuario autenticado para recibir un traslado.');
        }

        $tipo = MovementType::porNombre('TRANSFER_IN');
        if ($tipo === null) {
            throw new KardexStateException('Tipo de movimiento TRANSFER_IN no existe en la base.');
        }

        return DB::transaction(function () use ($transfer, $itemsRecibidos, $usuario, $tipo) {
            $transfer = Transfer::whereKey($transfer->id)->lockForUpdate()->first();

            // Permite IN_TRANSIT (recepcion inicial) y PARTIALLY_RECEIVED
            // (recepcion tardia de un faltante). En el segundo caso, los
            // items que ya tienen delivered_quantity > 0 no se pueden
            // re-ingresar: el delta entre el nuevo delivered y el anterior
            // es lo que realmente se mueve al destino.
            $this->asegurarEstadoEn($transfer, ['IN_TRANSIT', 'PARTIALLY_RECEIVED']);

            $items = $transfer->items()->lockForUpdate()->get();
            $recibidos = collect($itemsRecibidos)->keyBy('transfer_item_id');

            $alMenosUnaDiscrepancia = false;

            foreach ($items as $item) {
                $recibido = $recibidos->get($item->id);
                if ($recibido === null) {
                    throw new KardexStateException("Falta la cantidad recibida para el item {$item->id}.");
                }

                $approved = $item->approved_quantity ?? $item->requested_quantity;
                $deliveredAnterior = $item->delivered_quantity ?? 0;
                $deliveredNuevo = $recibido['delivered_quantity'];

                if ($deliveredNuevo > $approved) {
                    throw new KardexStateException(sprintf(
                        'La cantidad recibida (%d) no puede superar la aprobada (%d) en el item %s.',
                        $deliveredNuevo,
                        $approved,
                        $item->id
                    ));
                }

                // No se puede "des-recibir": si la recepcion anterior ya
                // registro unidades, el nuevo valor no puede ser menor.
                if ($deliveredNuevo < $deliveredAnterior) {
                    throw new KardexStateException(sprintf(
                        'La cantidad recibida para el item %s no puede ser menor que la ya registrada (%d).',
                        $item->id,
                        $deliveredAnterior
                    ));
                }

                // El delta es lo que realmente entra al destino: en la
                // primera recepcion delta = deliveredNuevo; en re-recepciones
                // delta = deliveredNuevo - deliveredAnterior.
                $delta = $deliveredNuevo - $deliveredAnterior;

                $item->delivered_quantity = $deliveredNuevo;
                $item->save();

                if ($delta > 0) {
                    $lote = $item->inventory?->lot;
                    if ($lote === null) {
                        throw new KardexStateException("El item {$item->id} no tiene un lote asociado.");
                    }

                    $existenciaDestino = $this->kardex->obtenerOCrearExistencia(
                        $transfer->to_warehouse_id,
                        $lote,
                        0
                    );

                    $this->kardex->registrarMovimiento(
                        $existenciaDestino,
                        $tipo,
                        $delta,
                        $usuario,
                        'Transfer',
                        $transfer->id,
                        $deliveredAnterior > 0
                            ? "Recepcion tardia de traslado {$transfer->transfer_number}"
                            : "Recepcion de traslado {$transfer->transfer_number}"
                    );
                }

                // RN-07: si el total delivered sigue siendo menor que approved,
                // la discrepancia queda PENDING (o se actualiza si ya existia).
                $existente = TransferDiscrepancy::where('transfer_item_id', $item->id)
                    ->where('status', TransferDiscrepancy::STATUS_PENDING)
                    ->first();

                if ($deliveredNuevo < $approved) {
                    $alMenosUnaDiscrepancia = true;
                    if ($existente !== null) {
                        // Recepcion tardia parcial: ajustar el delivered
                        // y la diferencia en la discrepancia existente.
                        $existente->delivered_quantity = $deliveredNuevo;
                        $existente->difference = $approved - $deliveredNuevo;
                        $existente->save();
                    } else {
                        TransferDiscrepancy::create([
                            'transfer_id' => $transfer->id,
                            'transfer_item_id' => $item->id,
                            'approved_quantity' => $approved,
                            'delivered_quantity' => $deliveredNuevo,
                            'difference' => $approved - $deliveredNuevo,
                            'status' => TransferDiscrepancy::STATUS_PENDING,
                        ]);
                    }
                } elseif ($existente !== null) {
                    // La recepcion tardia completo lo que faltaba: la
                    // discrepancia ya no representa un faltante, asi que se
                    // cierra sola. Dejarla PENDING con difference = 0 seria
                    // un estado imposible de explicar ("faltante de 0") que
                    // ademas mantendria el traslado en PARTIALLY_RECEIVED sin
                    // que nadie pueda cerrarlo de verdad.
                    $existente->delivered_quantity = $deliveredNuevo;
                    $existente->difference = 0;
                    $existente->status = TransferDiscrepancy::STATUS_RESOLVED;
                    $existente->resolution_notes = 'Completada por recepcion tardia.';
                    $existente->resolved_by = $usuario->id;
                    $existente->resolved_at = now();
                    $existente->save();
                }
            }

            // Si alguna discrepancia quedo PENDING (y solo si quedo), el
            // traslado se mantiene en PARTIALLY_RECEIVED. Si ya estamos
            // re-recibiendo y todo se completo, pasar a RECEIVED.
            $pendientes = TransferDiscrepancy::where('transfer_id', $transfer->id)
                ->where('status', TransferDiscrepancy::STATUS_PENDING)
                ->exists();
            $alMenosUnaDiscrepancia = $alMenosUnaDiscrepancia || $pendientes;

            $transfer->status_id = TransferStatus::porNombre(
                $alMenosUnaDiscrepancia ? 'PARTIALLY_RECEIVED' : 'RECEIVED'
            )->id;
            $transfer->received_by = $usuario->id;
            $transfer->received_at = now();
            $transfer->save();

            return $transfer->fresh(['items', 'discrepancies', 'status']);
        });
    }

    /**
     * Resuelve una discrepancia puntual sin tocar el stock.
     *
     * Acciones posibles:
     *   - 'resolve':     el faltante llego en otro momento, no se re-recibe
     *                    por este endpoint, solo se cierra el registro.
     *                    No mueve Kardex. La trazabilidad del faltante
     *                    queda en el campo resolution_notes.
     *   - 'write_off':   la diferencia se asume como perdida. No se
     *                    repone stock (RN-06: la correccion de inventario
     *                    se hace por ajuste, no por este endpoint).
     *                    Queda registrado en resolution_notes quien y
     *                    por que.
     *
     * Cuando todas las discrepancias del traslado pasan a RESOLVED o
     * WRITTEN_OFF, el traslado se cierra como RECEIVED.
     */
    public function resolverDiscrepancia(
        Transfer $transfer,
        TransferDiscrepancy $discrepancia,
        string $accion,
        ?string $notes,
        ?User $usuario,
    ): TransferDiscrepancy {
        if ($usuario === null) {
            throw new KardexStateException('Se necesita un usuario autenticado para resolver una discrepancia.');
        }

        // Dar de baja una discrepancia es asumir una perdida de stock:
        // decision autoritativa, exclusiva del regente.
        Gate::forUser($usuario)->authorize('resolver-discrepancia');

        if ($discrepancia->transfer_id !== $transfer->id) {
            throw new KardexStateException('La discrepancia no pertenece a este traslado.');
        }

        if ($discrepancia->status !== TransferDiscrepancy::STATUS_PENDING) {
            throw new KardexStateException(
                "La discrepancia ya esta en estado {$discrepancia->status} y no se puede resolver de nuevo."
            );
        }

        $nuevoEstado = match ($accion) {
            'resolve' => TransferDiscrepancy::STATUS_RESOLVED,
            'write_off' => TransferDiscrepancy::STATUS_WRITTEN_OFF,
            default => throw new KardexStateException(
                "Accion de resolucion invalida: {$accion}. Use 'resolve' o 'write_off'."
            ),
        };

        return DB::transaction(function () use ($transfer, $discrepancia, $nuevoEstado, $notes, $usuario) {
            $transfer = Transfer::whereKey($transfer->id)->lockForUpdate()->first();

            $discrepancia->status = $nuevoEstado;
            $discrepancia->resolution_notes = $notes;
            $discrepancia->resolved_by = $usuario->id;
            $discrepancia->resolved_at = now();
            $discrepancia->save();

            // Si no quedan discrepancias PENDING en este traslado,
            // cerrarlo como RECEIVED.
            $pendientes = TransferDiscrepancy::where('transfer_id', $transfer->id)
                ->where('status', TransferDiscrepancy::STATUS_PENDING)
                ->exists();

            if (! $pendientes) {
                $transfer->status_id = TransferStatus::porNombre('RECEIVED')->id;
                $transfer->save();
            }

            return $discrepancia->fresh();
        });
    }

    /**
     * Anula un traslado (estado DRAFT, REQUESTED, APPROVED, IN_TRANSIT
     * o PARTIALLY_RECEIVED -> CANCELLED).
     *
     * La anulacion solo es valida si el estado actual es cancelable
     * (is_cancellable=true en la fila de transfer_statuses). Eso
     * consulta directo a la BD, no a una constante en codigo: si
     * manana se decide que se puede cancelar desde APPROVED, es un
     * UPDATE sobre la fila, no un deploy.
     */
    public function anular(Transfer $transfer, ?string $motivo, ?User $usuario): Transfer
    {
        if ($usuario === null) {
            throw new KardexStateException('Se necesita un usuario autenticado para anular un traslado.');
        }

        // Anular es decision autoritativa: la matriz se la da solo al
        // regente. Sin esta linea, el auxiliar podria anular porque
        // tiene transfers.can_update.
        Gate::forUser($usuario)->authorize('anular-traslado');

        return DB::transaction(function () use ($transfer, $motivo, $usuario) {
            $transfer = Transfer::whereKey($transfer->id)->lockForUpdate()->first();
            $this->asegurarCancelable($transfer);

            $transfer->status_id = TransferStatus::porNombre('CANCELLED')->id;
            $transfer->cancelled_by = $usuario->id;
            $transfer->cancelled_at = now();

            // Si vino motivo, lo agregamos a las notas (separado por \n\n
            // si ya habia notas). Las notas del traslado quedan como
            // historial de la razon de la cancelacion.
            if ($motivo !== null && $motivo !== '') {
                $transfer->notes = $transfer->notes
                    ? $transfer->notes . "\n\n[CANCELADO] " . $motivo
                    : '[CANCELADO] ' . $motivo;
            }

            $transfer->save();

            return $transfer->fresh(['items', 'status']);
        });
    }

    /**
     * Verifica que el estado del traslado esta en uno de los esperados.
     * Si no, tira KardexStateException con un mensaje que dice desde
     * que estado se intento la transicion.
     */
    private function asegurarEstadoEn(Transfer $transfer, array $esperados): void
    {
        $statusName = $transfer->status?->name;
        if (! in_array($statusName, $esperados, true)) {
            throw new KardexStateException(sprintf(
                'El traslado esta en estado %s; se esperaba uno de: %s.',
                $statusName ?? 'desconocido',
                implode(', ', $esperados)
            ));
        }
    }

    /**
     * Verifica que el estado del traslado es cancelable segun
     * transfer_statuses.is_cancellable. La consulta es contra el
     * modelo (que tiene la fila cacheada via with()), no contra la
     * BD, pero como el traslado esta lockForUpdate'd al inicio de
     * la transaccion, la lectura es consistente.
     */
    private function asegurarCancelable(Transfer $transfer): void
    {
        $status = $transfer->status;
        if ($status === null || ! $status->sePuedeAnular()) {
            throw new KardexStateException(
                "El traslado esta en estado {$status?->name} y no se puede anular."
            );
        }
    }
}
