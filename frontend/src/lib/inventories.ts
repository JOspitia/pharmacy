/**
 * Inventario - tipos para /api/inventories (HU-08).
 *
 * El backend serializa la existencia con un JOIN a lots, products y bodegas,
 * por lo que estos vienen como sub-objetos con `id` y `name` (y `cum` para
 * producto, que es el equivalente colombiano del SKU).
 *
 * @see /api/inventories en `back/app/Http/Controllers/InventoryController.php`
 */
export interface Inventory {
  id: string
  lot_id: string
  /** Stock actual de la existencia (puede ser 0). */
  quantity: number
  /** Umbral por bodega. Si `quantity <= min_stock`, aparece en alerts.low_stock. */
  min_stock: number | null
  /** Dias hasta el vencimiento del lote. Negativo = ya vencido. Null si el lote no tiene fecha. */
  days_until_expiration: number | null
  /** Estado del lote: `ok` (>90d), `proximo` (31-90d), `critico` (1-30d), `vencido` (<0). */
  expiration_status: 'ok' | 'proximo' | 'critico' | 'vencido' | 'desconocido'
  /** Fecha de vencimiento del lote (formato YYYY-MM-DD). */
  expiration_date: string | null
  created_at: string
  lot: { id: string; name: string } | null
  product: { id: string; name: string; cum: string } | null
  bodega: { id: string; name: string } | null
}

/**
 * Item de una alerta de la respuesta de /api/inventories.
 * Sale en la seccion `alerts` del response, agrupado por tipo.
 */
export interface InventoryAlert {
  inventory_id: string
  product_name: string | null
  lot_name: string | null
  warehouse_name: string | null
  quantity: number
  /** Solo para expiring_soon y expired. */
  days_until_expiration?: number
  /** Solo para expiring_soon y expired. */
  expiration_date?: string | null
  /** Solo para low_stock. */
  min_stock?: number | null
  /** Solo para low_stock. Negativo si quantity < min_stock. */
  deficit?: number | null
}

/**
 * Respuesta completa de GET /api/inventories.
 * `meta` lo agrega Laravel; `alerts` lo agrega el controller y siempre viene
 * presente (arrays vacios si no hay nada que reportar).
 */
export interface InventoryListResponse {
  data: Inventory[]
  meta?: {
    current_page: number
    total: number
    per_page: number
    last_page: number
  }
  alerts?: {
    expiring_soon: InventoryAlert[]
    expired: InventoryAlert[]
    low_stock: InventoryAlert[]
  }
}

/**
 * Filtros aceptados por GET /api/inventories.
 * `expiring_within` solo incluye lotes que vencen dentro de N dias desde hoy.
 */
export interface InventoryFilters {
  warehouse_id?: string
  product_id?: string
  lot_id?: string
  expiring_within?: number
  sort?: 'expiration_date' | 'quantity' | 'product_name'
  order?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

/**
 * Construye la query string para GET /api/inventories a partir de los filtros.
 * Omite los filtros vacios para no contaminar la URL.
 */
export function buildInventoryQuery(filters: InventoryFilters): string {
  const params = new URLSearchParams()
  if (filters.warehouse_id) params.set('warehouse_id', filters.warehouse_id)
  if (filters.product_id) params.set('product_id', filters.product_id)
  if (filters.lot_id) params.set('lot_id', filters.lot_id)
  if (filters.expiring_within !== undefined) {
    params.set('expiring_within', String(filters.expiring_within))
  }
  if (filters.sort) params.set('sort', filters.sort)
  if (filters.order) params.set('order', filters.order)
  if (filters.page) params.set('page', String(filters.page))
  if (filters.per_page) params.set('per_page', String(filters.per_page))
  const qs = params.toString()
  return qs ? `/api/inventories?${qs}` : '/api/inventories'
}

/**
 * Lote en una bodega especifica. Sale de GET /api/warehouses/{id}/lots.
 * Es el payload del selector del formulario de Kardex (CA-12).
 */
export interface LotInWarehouse {
  id: string
  name: string
  expiration_date: string | null
  days_until_expiration: number | null
  stock: number
  min_stock: number | null
  product: { id: string; name: string; cum: string } | null
}
