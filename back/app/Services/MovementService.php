<?php

namespace App\Services;

use App\Exceptions\KardexStateException;
use App\Exceptions\StockInsuficienteException;
use App\Models\Lot;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Servicio de movimientos del Kardex (HU-08 §10).
 *
 * Consulta y registro de movimientos. Toda la logica de negocio vive aqui:
 * resolucion de existencia, validacion de consistencia de bodega, y delegacion
 * a KardexService para la escritura transaccional con lock.
 *
 * El controller solo recibe el request, llama al service y serializa la respuesta.
 */
class MovementService
{
    public function __construct(
        private readonly KardexService $kardex
    ) {
    }

    /**
     * Listado paginado de movimientos con filtros (HU-08 §10).
     *
     * Filtros: inventory_id, type, performed_by, from_date, to_date.
     * Orden default: created_at DESC.
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = Movement::query()
            ->with(['type', 'performedByUser', 'inventory.lot', 'inventory.product', 'inventory.bodega']);

        if (! empty($filtros['inventory_id'])) {
            $query->where('inventory_id', $filtros['inventory_id']);
        }

        if (! empty($filtros['type'])) {
            $tipo = MovementType::porNombre($filtros['type']);
            if ($tipo) {
                $query->where('movement_type_id', $tipo->id);
            }
        }

        if (! empty($filtros['performed_by'])) {
            $query->where('performed_by', $filtros['performed_by']);
        }

        if (! empty($filtros['from_date'])) {
            $query->whereDate('created_at', '>=', $filtros['from_date']);
        }

        if (! empty($filtros['to_date'])) {
            $query->whereDate('created_at', '<=', $filtros['to_date']);
        }

        $perPage = min(max((int) ($filtros['per_page'] ?? 30), 1), 100);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Registra un movimiento del Kardex (HU-08 §10).
     *
     * Recibe los datos crudos del request, no objetos resueltos. Esto
     * mantiene al controller como un traductor HTTP puro: ni Lot::find ni
     * MovementType::porNombre viven alla, y un test del service puede
     * llamarlo sin preparar un FormRequest.
     *
     * Flujo:
     *   1. Resuelve el lote y el tipo de movimiento.
     *   2. Resuelve o crea la existencia por la terna bodega x producto x lote (RN-01).
     *   3. Valida consistencia de bodega (KE-03).
     *   4. KardexService ejecuta lockForUpdate, valida stock y graba.
     *   5. Carga las relaciones que el Resource necesita para serializar.
     *
     * @param  array{
     *     lot_id: string,
     *     warehouse_id: string,
     *     type: string,
     *     quantity: int,
     *     reference_type?: ?string,
     *     reference_id?: ?string,
     *     reason?: ?string
     * }  $datos  datos validados del request
     *
     * @throws StockInsuficienteException  cuando el stock no alcanza
     * @throws KardexStateException        cuando la existencia no corresponde a la bodega
     *                                     o cuando el tipo de movimiento no existe
     */
    public function registrar(array $datos, User $usuario): Movement
    {
        $lote = Lot::findOrFail($datos['lot_id']);
        $tipo = MovementType::porNombre($datos['type']);

        if ($tipo === null) {
            // Por la validacion del FormRequest esto no deberia pasar, pero
            // el service se defiende solo: si lo llaman por otra via y el
            // tipo no esta en la tabla, el error tiene que ser claro.
            throw new KardexStateException("Tipo de movimiento invalido: {$datos['type']}");
        }

        // Resolver o crear la existencia. El lote aporta product_id;
        // la bodega viene del request. Se usa lockForUpdate para que dos
        // recepciones simultaneas del mismo lote en la misma bodega no
        // creen dos filas.
        $existencia = $this->kardex->obtenerOCrearExistencia(
            $datos['warehouse_id'],
            $lote
        );

        // KE-03: la existencia resuelta debe corresponder a la bodega del request.
        // Si no coincide, algo esta mal en el llamado del cliente.
        if ($existencia->warehouse_id !== $datos['warehouse_id']) {
            throw new KardexStateException(
                'La existencia resuelta no corresponde a la bodega seleccionada.'
            );
        }

        $movimiento = $this->kardex->registrarMovimiento(
            $existencia,
            $tipo,
            (int) $datos['quantity'],
            $usuario,
            $datos['reference_type'] ?? null,
            $datos['reference_id'] ?? null,
            $datos['reason'] ?? null
        );

        // Las relaciones que el Resource necesita se cargan aca y no en el
        // controller: si quedan en el controller, el service devuelve un
        // modelo "incompleto" que rompe la serializacion.
        $movimiento->load([
            'type',
            'performedByUser',
            'inventory.lot',
            'inventory.product',
            'inventory.bodega',
        ]);

        return $movimiento;
    }
}
