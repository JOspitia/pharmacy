<?php

namespace App\Services;

use App\Exceptions\KardexStateException;
use App\Exceptions\StockInsuficienteException;
use App\Models\Inventory;
use App\Models\Lot;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * KardexService: unico lugar por donde se toca el stock.
 *
 * RN-03: "El stock nunca puede quedar negativo, incluso si llegan solicitudes
 * simultaneas por la ultima unidad."
 *
 * La garantia no esta en este archivo: esta en tres capas, y este servicio es
 * la que las coordina.
 *
 *   1. lockForUpdate() serializa a quienes compiten por la misma existencia.
 *      Sin esto, dos requests leen quantity=1, ambos ven que hay stock, y
 *      ambos restan: el resultado es -1.
 *   2. El CHECK quantity >= 0 en la base es el ultimo muro. Si algo se
 *      escapara del lock, la base lo rechaza.
 *   3. La transaccion envuelve la resta y el INSERT del movimiento juntos. Si
 *      el movimiento fallara, la resta no queda aplicada.
 *
 * El orden importa y no es negociable: se bloquea la fila, se recalcula el
 * stock sobre el valor bloqueado, y recien ahi se escribe.
 *
 * Todos los metodos son transaccionales. Si se llama a este servicio desde
 * una transaccion ya abierta (por ejemplo, un traslado que descuenta en origen
 * y suma en destino), el comportamiento se compone bien porque lockForUpdate
 * es reentrante dentro de la misma conexion.
 */
class KardexService
{
    /**
     * Registra un movimiento y aplica su efecto sobre la existencia.
     *
     * La cantidad que se guarda en el kardex es el delta con signo, no el
     * absoluto: guardar la cantidad positiva siempre, y deducir el signo del
     * tipo, haria imposible releer el historico sin volver a consultar el tipo.
     *
     * @param  int  $cantidad  siempre positiva; el signo lo decide el tipo
     *
     * @throws StockInsuficienteException
     * @throws KardexStateException
     */
    public function registrarMovimiento(
        Inventory $existencia,
        MovementType $tipo,
        int $cantidad,
        ?User $usuario,
        ?string $referenciaTipo = null,
        ?string $referenciaId = null,
        ?string $motivo = null
    ): Movement {
        // Un ajuste puede ser legítimamente negativo; el resto de tipos no.
        $admiteNegativo = $tipo->admiteAmbosSignos();
        $delta = $this->calcularDelta($tipo, $cantidad);

        if ($admiteNegativo) {
            if ($delta === 0) {
                throw new KardexStateException('La cantidad de un movimiento no puede ser cero.');
            }
        } elseif ($cantidad <= 0) {
            throw new KardexStateException('La cantidad de un movimiento debe ser mayor a cero.');
        }

        if ($usuario === null) {
            throw new KardexStateException('Todo movimiento necesita un usuario responsable (RN-05/RN-06).');
        }

        return DB::transaction(function () use ($existencia, $delta, $tipo, $cantidad, $usuario, $referenciaTipo, $referenciaId, $motivo) {
            // Se vuelve a leer la fila con lockForUpdate en lugar de usar la
            // instancia que trae el llamador: el stock que importa es el que
            // esta bloqueado ahora, no el que el caller vio antes del lock.
            $bloqueada = Inventory::whereKey($existencia->id)->lockForUpdate()->first();

            if ($bloqueada === null) {
                throw new StockInsuficienteException("La existencia {$existencia->id} no existe.");
            }

            $nuevoStock = $bloqueada->quantity + $delta;

            // Chequeo explicito ademas del CHECK de la base. El CHECK es el
            // ultimo muro y su mensaje es generico; este da el numero real, que
            // es lo que necesita ver quien esta frente a la pantalla.
            if ($nuevoStock < 0) {
                throw new StockInsuficienteException(sprintf(
                    'Stock insuficiente: se solicitan %d unidades y solo hay %d disponibles.',
                    abs($cantidad),
                    $bloqueada->quantity
                ));
            }

            $bloqueada->quantity = $nuevoStock;
            $bloqueada->save();

            return Movement::create([
                'inventory_id' => $bloqueada->id,
                'movement_type_id' => $tipo->id,
                'quantity' => $delta,
                'reason' => $motivo,
                'reference_type' => $referenciaTipo,
                'reference_id' => $referenciaId,
                'performed_by' => $usuario->id,
            ]);
        });
    }

    /**
     * Calcula el delta con signo segun la direccion del tipo.
     *
     * IN_OUT (ajuste) hereda el signo de la cantidad: por definicion un ajuste
     * puede sumar o restar. Los otros dos tipos tienen direccion fija y el
     * signo se impone, aunque venga al reves.
     *
     * Por eso el parametro se llama cantidad y no cantidadPositiva: en el caso
     * IN_OUT puede llegar negativo, y por eso la guarda de $cantidad > 0 de
     * arriba solo aplica a los tipos con direccion fija.
     */
    private function calcularDelta(MovementType $tipo, int $cantidad): int
    {
        return match ($tipo->direction) {
            MovementType::DIRECCION_ENTRADA => abs($cantidad),
            MovementType::DIRECCION_SALIDA => -abs($cantidad),
            MovementType::DIRECCION_AMBAS => $cantidad,
            default => throw new KardexStateException("Tipo de movimiento con direccion desconocida: {$tipo->direction}"),
        };
    }

    /**
     * Resuelve la existencia de una terna RN-01 y la crea si no existe.
     *
     * Se usa al recibir un traslado (la bodega destino puede no tener ese lote)
     * y al registrar la primera entrada de un lote nuevo.
     *
     * El lote aporta product_id; la bodega la aporta el llamador. Los dos se
     * combinan en la terna, que es lo que RN-01 define.
     *
     * El lock es pesimista a proposito (SELECT antes del INSERT) para que dos
     * recepciones simultaneas del mismo lote en la misma bodega no se amontonan
     * en dos filas: la segunda ve la fila de la primera y actualiza en vez de
     * insertar.
     */
    public function obtenerOCrearExistencia(
        string $bodegaId,
        Lot $lote,
        int $cantidadInicial = 0,
        ?int $minStock = null
    ): Inventory {
        return DB::transaction(function () use ($bodegaId, $lote, $cantidadInicial, $minStock) {
            $existente = Inventory::where('warehouse_id', $bodegaId)
                ->where('product_id', $lote->product_id)
                ->where('lot_id', $lote->id)
                ->lockForUpdate()
                ->first();

            if ($existente !== null) {
                if ($minStock !== null && $existente->min_stock === null) {
                    $existente->min_stock = $minStock;
                    $existente->save();
                }

                return $existente;
            }

            return Inventory::create([
                'warehouse_id' => $bodegaId,
                'product_id' => $lote->product_id,
                'lot_id' => $lote->id,
                'quantity' => $cantidadInicial,
                'min_stock' => $minStock,
            ]);
        });
    }
}