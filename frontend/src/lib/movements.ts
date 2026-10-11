/**
 * Movimientos del Kardex - tipos para /api/movements (HU-08).
 *
 * Un movimiento es INMUTABLE: el backend rechaza PUT/PATCH/DELETE con 405.
 * Por eso el frontend solo expone listar y crear.
 *
 * El `type` que viene en el response es el `name` tecnico en ingles
 * (ENTRY, TRANSFER_OUT, etc.); `type_label` es la `description` en espanol
 * lista para mostrar al usuario.
 *
 * @see `back/app/Http/Resources/MovementResource.php`
 */
export interface Movement {
  id: string
  inventory_id: string
  /** Identificador tecnico del tipo. Identico al que se envia en POST. */
  type: 'ENTRY' | 'ADJUSTMENT' | 'TRANSFER_OUT' | 'TRANSFER_IN' | 'DISPENSATION' | 'WITHDRAWAL'
  /** Texto en espanol para mostrar al usuario. */
  type_label: string
  /** Delta con signo aplicado al stock: positivo suma, negativo resta. */
  quantity: number
  reason: string | null
  reference_type: string | null
  reference_id: string | null
  performed_by: string
  performed_by_name: string | null
  created_at: string
  /** Bloque anidado con datos del inventory afectado. */
  inventory: {
    id: string
    quantity: number
    lot_name: string | null
    product_name: string | null
    warehouse_name: string | null
  } | null
}

/**
 * Respuesta paginada de GET /api/movements.
 */
export interface MovementListResponse {
  data: Movement[]
  meta?: {
    current_page: number
    total: number
    per_page: number
    last_page: number
  }
}

/**
 * Filtros aceptados por GET /api/movements.
 * `from_date` y `to_date` en formato YYYY-MM-DD.
 */
export interface MovementFilters {
  inventory_id?: string
  type?: Movement['type']
  performed_by?: string
  from_date?: string
  to_date?: string
  page?: number
  per_page?: number
}

/**
 * Construye la query string para GET /api/movements a partir de los filtros.
 */
export function buildMovementQuery(filters: MovementFilters): string {
  const params = new URLSearchParams()
  if (filters.inventory_id) params.set('inventory_id', filters.inventory_id)
  if (filters.type) params.set('type', filters.type)
  if (filters.performed_by) params.set('performed_by', filters.performed_by)
  if (filters.from_date) params.set('from_date', filters.from_date)
  if (filters.to_date) params.set('to_date', filters.to_date)
  if (filters.page) params.set('page', String(filters.page))
  if (filters.per_page) params.set('per_page', String(filters.per_page))
  const qs = params.toString()
  return qs ? `/api/movements?${qs}` : '/api/movements'
}

/**
 * Payload para POST /api/movements.
 * - `quantity` > 0: el signo se calcula del tipo (ENTRY suma, TRANSFER_OUT resta, etc.)
 * - Para ENTRY el backend rechaza lotes con `expiration_date < today()`.
 */
export interface MovementPayload {
  lot_id: string
  warehouse_id: string
  type: Movement['type']
  quantity: number
  reason?: string | null
  reference_type?: string | null
  reference_id?: string | null
}

/**
 * Item del catalogo de tipos de movimiento. Hardcodeado en el frontend
 * porque no tenemos endpoint de catalogo: los nombres tecnicos viven en
 * la tabla `movement_types` y el backend los valida.
 */
export const MOVEMENT_TYPES: { value: Movement['type']; label: string }[] = [
  { value: 'ENTRY', label: 'Entrada de mercancia' },
  { value: 'ADJUSTMENT', label: 'Ajuste de inventario' },
  { value: 'TRANSFER_OUT', label: 'Salida por traslado' },
  { value: 'TRANSFER_IN', label: 'Entrada por traslado' },
  { value: 'DISPENSATION', label: 'Dispensacion a paciente' },
  { value: 'WITHDRAWAL', label: 'Retiro (merma o vencimiento)' },
]
