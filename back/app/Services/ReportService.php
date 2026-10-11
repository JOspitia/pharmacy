<?php

namespace App\Services;

use App\Models\Inventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Reporte de inventario.
 *
 * CAMBIO HU-08 (RN-01): el reporte paso de listar lotes a listar existencias.
 *
 * Antes la consulta arrancaba en lots y hacia join con bodegas por
 * lots.warehouse_id, o sea una fila por lote. Ahora arranca en inventories,
 * que es donde vive el stock, y da una fila por existencia: bodega x producto
 * x lote.
 *
 * El grano no cambio en la practica porque antes un lote solo podia estar en
 * una bodega, pero ahora el modelo lo permite y el reporte lo refleja. Cuando
 * un traslado deje el mismo lote en dos bodegas, el reporte mostrara las dos
 * filas, que es la informacion que se quiere ver.
 */
class ReportService
{
    /**
     * Columnas permitidas para ordenamiento (whitelist anti-SQL injection).
     *
     * lots.stock desaparece: la columna no existe. El equivalente es
     * inventories.quantity, que es el stock de esa existencia concreta.
     */
    public const SORTABLE_COLUMNS = [
        'products.cum',
        'products.name',
        'products.barcode',
        'products.invima_registration',
        'groups.name',
        'units.name',
        'lots.name',
        'bodegas.name',
        'inventories.quantity',
        'lots.expiration_date',
    ];

    /** Estados válidos del filtro de lote */
    private const VALID_ESTADOS = ['con_stock', 'por_vencer', 'vencido', 'todos'];

    /** Paginación por defecto y máximo */
    private const PER_PAGE_DEFAULT = 30;
    private const PER_PAGE_MAX = 100;

    /**
     * Consulta el reporte de inventario paginado con filtros.
     */
    public function getInventario(array $filters = []): LengthAwarePaginator
    {
        $query = Inventory::query()
            ->select([
                'inventories.id',
                'lots.id as lot_id',
                'lots.name as lot_code',
                'inventories.quantity',
                'lots.expiration_date',
                'lots.status as lot_status',
                'products.cum',
                'products.name as product_name',
                'products.barcode',
                'products.invima_registration',
                'bodegas.name as bodega_name',
                'groups.name as group_name',
                'units.name as unit_name',
                'units.abbreviation as unit_abbr',
            ])
            ->join('lots', function ($join) {
                $join->on('inventories.lot_id', '=', 'lots.id')
                    ->where('lots.status', true)
                    ->whereNull('lots.deleted_at');
            })
            ->join('products', function ($join) {
                $join->on('lots.product_id', '=', 'products.id')
                    ->where('products.status', true)
                    ->whereNull('products.deleted_at');
            })
            ->join('bodegas', function ($join) {
                $join->on('inventories.warehouse_id', '=', 'bodegas.id')
                    ->where('bodegas.status', true)
                    ->whereNull('bodegas.deleted_at');
            })
            ->join('groups', function ($join) {
                $join->on('products.group_id', '=', 'groups.id')
                    ->where('groups.status', true)
                    ->whereNull('groups.deleted_at');
            })
            ->join('units', function ($join) {
                $join->on('products.unit_id', '=', 'units.id')
                    ->where('units.status', true);
            });

        $this->applyTextFilter($query, $filters);
        $this->applySelectFilter($query, $filters);
        $this->applyEstadoFilter($query, $filters);
        $this->applyDateRangeFilter($query, $filters);
        $sortBy = $filters['sort_by'] ?? null;
        $sortDir = strtolower($filters['sort_dir'] ?? 'asc');
        $this->applySorting($query, $sortBy, $sortDir);
        $perPage = min(
            max((int) ($filters['per_page'] ?? self::PER_PAGE_DEFAULT), 1),
            self::PER_PAGE_MAX
        );

        return $query->paginate($perPage);
    }

    /**
     * Filtros de texto: CUM y nombre de producto (búsqueda parcial ILIKE).
     */
    private function applyTextFilter($query, array $filters): void
    {
        if (! empty($filters['cum'])) {
            $query->where('products.cum', 'like', '%' . $filters['cum'] . '%');
        }

        if (! empty($filters['name'])) {
            $query->where('products.name', 'like', '%' . $filters['name'] . '%');
        }
    }

    /**
     * Filtros de select: bodega, grupo, código de lote.
     *
     * bodega_id filtra contra inventories.warehouse_id, no contra
     * lots.warehouse_id: la columna del lote ya no existe.
     */
    private function applySelectFilter($query, array $filters): void
    {
        if (! empty($filters['bodega_id'])) {
            $query->where('inventories.warehouse_id', $filters['bodega_id']);
        }

        if (! empty($filters['group_id'])) {
            $query->where('products.group_id', $filters['group_id']);
        }

        if (! empty($filters['lot_code'])) {
            $query->where('lots.name', $filters['lot_code']);
        }
    }

    /**
     * Filtro de estado de lote.
     *
     * El filtro "vencido" sigue exigiendo stock mayor a cero: un lote vencido
     * sin existencias no aparece en el reporte de inventario porque no hay
     * inventario que reportar.
     */
    private function applyEstadoFilter($query, array $filters): void
    {
        $estado = $filters['estado'] ?? 'todos';

        switch ($estado) {
            case 'con_stock':
                $query->where('inventories.quantity', '>', 0);
                break;

            case 'por_vencer':
                $query->where('inventories.quantity', '>', 0)
                    ->whereDate('lots.expiration_date', '>=', Carbon::today()->toDateString())
                    ->whereDate('lots.expiration_date', '<=', Carbon::today()->addDays(90)->toDateString());
                break;

            case 'vencido':
                $query->where('inventories.quantity', '>', 0)
                    ->whereDate('lots.expiration_date', '<', Carbon::today()->toDateString());
                break;

            default: 
                break;
        }
    }

    /**
     * Filtro de rango de fechas de vencimiento.
     */
    private function applyDateRangeFilter($query, array $filters): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate('lots.expiration_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('lots.expiration_date', '<=', $filters['date_to']);
        }
    }

    /**
     * Ordenamiento dinámico con whitelist de columnas (previene SQL injection).
     */
    private function applySorting($query, ?string $sortBy, string $sortDir): void
    {
        $sortDir = in_array($sortDir, ['asc', 'desc']) ? $sortDir : 'asc';

        if ($sortBy && in_array($sortBy, self::SORTABLE_COLUMNS)) {
            $query->orderBy($sortBy, $sortDir);
            return;
        }

        // Default: nombre de producto A-Z
        $query->orderBy('products.name', 'asc');
    }
}