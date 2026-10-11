<?php

namespace App\Services;

use App\Models\Inventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Servicio de consulta de existencias (HU-08 §10).
 *
 * Consulta existencias con filtros y genera las alertas de expiracion
 * y stock minimo que el endpoint GET /api/inventories devuelve en la
 * seccion "alerts".
 *
 * No modifica datos; toda la escritura va por KardexService.
 */
class InventoryService
{
    /**
     * Listado paginado de existencias con filtros.
     *
     * Orden default: expiration_date ASC (FEFO — primero el que vence primero).
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = Inventory::query()
            ->with(['lot', 'product', 'bodega']);

        $this->aplicarFiltros($query, $filtros);

        $sort = $filtros['sort'] ?? 'expiration_date';
        $order = $filtros['order'] ?? 'asc';

        // expiration_date no vive en inventories sino en lots. La peticion
        // lo ordena con un join sobre el lote, asi que el ORDER BY apunta a
        // la columna real, no a una del join con products.
        if ($sort === 'expiration_date') {
            $query->join('lots', 'inventories.lot_id', '=', 'lots.id')
                ->orderBy('lots.expiration_date', $order)
                ->select('inventories.*');
        } elseif ($sort === 'product_name') {
            $query->join('products', 'inventories.product_id', '=', 'products.id')
                ->orderBy('products.name', $order)
                ->select('inventories.*');
        } else {
            $query->orderBy($sort, $order);
        }

        $perPage = min(max((int) ($filtros['per_page'] ?? 30), 1), 100);

        return $query->paginate($perPage);
    }

    /**
     * Genera las alertas de la respuesta de inventario.
     *
     * Las alertas se calculan sobre TODAS las existencias que cumplen los filtros
     * activos, sin paginacion: un item alertado que quedo en la pagina 3 de un
     * listado seguiria necesitando atencion.
     *
     * @return array{expiring_soon: array, expired: array, low_stock: array}
     */
    public function generarAlertas(array $filtros): array
    {
        $query = Inventory::query()
            ->with(['lot', 'product', 'bodega']);

        $this->aplicarFiltros($query, $filtros);

        $inventories = $query->get();

        $expiringSoon = [];
        $expired = [];
        $lowStock = [];

        foreach ($inventories as $inv) {
            $dias = $inv->diasParaVencer();

            // Vencido: dias < 0
            if ($dias !== null && $dias < 0) {
                $expired[] = $this->formatoAlerta($inv, $dias);
                continue;
            }

            // Proximo a vencer: 1-90 dias
            if ($dias !== null && $dias <= 90) {
                $expiringSoon[] = $this->formatoAlerta($inv, $dias);
            }

            // Stock bajo: quantity <= min_stock
            if ($inv->estaBajoMinimo()) {
                $lowStock[] = [
                    'inventory_id' => $inv->id,
                    'product_name' => $inv->product?->name,
                    'lot_name' => $inv->lot?->name,
                    'warehouse_name' => $inv->bodega?->name,
                    'quantity' => $inv->quantity,
                    'min_stock' => $inv->min_stock,
                    'deficit' => $inv->deficit(),
                ];
            }
        }

        return [
            'expiring_soon' => $expiringSoon,
            'expired' => $expired,
            'low_stock' => $lowStock,
        ];
    }

    private function aplicarFiltros($query, array $filtros): void
    {
        if (! empty($filtros['warehouse_id'])) {
            $query->where('warehouse_id', $filtros['warehouse_id']);
        }

        if (! empty($filtros['product_id'])) {
            $query->where('product_id', $filtros['product_id']);
        }

        if (! empty($filtros['lot_id'])) {
            $query->where('lot_id', $filtros['lot_id']);
        }

        if (! empty($filtros['expiring_within'])) {
            $hoy = Carbon::today();
            $query->whereHas('lot', function ($q) use ($hoy, $filtros) {
                $q->whereDate('expiration_date', '>=', $hoy->toDateString())
                    ->whereDate('expiration_date', '<=', $hoy->copy()->addDays((int) $filtros['expiring_within'])->toDateString());
            });
        }
    }

    private function formatoAlerta(Inventory $inv, ?int $dias): array
    {
        return [
            'inventory_id' => $inv->id,
            'product_name' => $inv->product?->name,
            'lot_name' => $inv->lot?->name,
            'warehouse_name' => $inv->bodega?->name,
            'quantity' => $inv->quantity,
            'days_until_expiration' => $dias,
            'expiration_date' => $inv->lot?->expiration_date?->toDateString(),
        ];
    }
}
