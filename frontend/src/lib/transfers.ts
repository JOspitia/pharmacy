/**
 * Traslados entre bodegas - tipos para /api/transfers (HU-09).
 *
 * La maquina de 7 estados vive en la BD (transfer_statuses).
 * El frontend recibe el estado como { id, name, label } en el resource
 * y muestra el label al usuario.
 *
 * Decision D3 del proyecto: status_name tecnico en ingles (DRAFT, APPROVED,
 * IN_TRANSIT...), status_label en espanol (Borrador, Aprobado, En transito...).
 *
 * @see TransferResource en back/app/Http/Resources/TransferResource.php
 */

export type TransferStatusName =
  | 'DRAFT'
  | 'REQUESTED'
  | 'APPROVED'
  | 'IN_TRANSIT'
  | 'RECEIVED'
  | 'PARTIALLY_RECEIVED'
  | 'CANCELLED'

/** Relacion status dentro de TransferResource. */
export interface TransferStatusRef {
  id: string
  name: TransferStatusName
  label: string
}

/** Relacion from_warehouse / to_warehouse dentro de TransferResource. */
export interface TransferWarehouseRef {
  id: string
  name: string
}

/** Relacion lot dentro de TransferItemResource. */
export interface TransferLotRef {
  id: string
  name: string
  expiration_date: string | null
  days_until_expiration: number | null
}

/** Relacion product dentro de TransferItemResource. */
export interface TransferProductRef {
  id: string
  name: string
  cum: string | null
}

/** Relacion warehouse dentro de TransferItemResource. */
export interface TransferItemWarehouseRef {
  id: string
  name: string
}

/** Item de un traslado. Viene en Transfer.items[]. */
export interface TransferItem {
  id: string
  transfer_id: string
  inventory_id: string
  lot_name: string | null
  product_name: string | null
  product_sku: string | null
  warehouse_name: string | null
  lot_expiration_date: string | null
  requested_quantity: number
  approved_quantity: number | null
  delivered_quantity: number | null
  pending_quantity: number
  is_fully_received: boolean
  has_discrepancy: boolean
  lot: TransferLotRef | null
  product: TransferProductRef | null
  warehouse: TransferItemWarehouseRef | null
  created_at: string
}

/** Discrepancia de un traslado (RN-07). Viene en Transfer.discrepancies[]. */
export interface TransferDiscrepancy {
  id: string
  transfer_id: string
  transfer_item_id: string
  approved_quantity: number
  delivered_quantity: number
  difference: number
  status: 'PENDING' | 'RESOLVED' | 'WRITTEN_OFF'
  resolution_notes: string | null
  resolved_by: string | null
  resolved_at: string | null
  is_pending: boolean
  is_resolved: boolean
  resolved_by_user: { id: string; name: string } | null
  created_at: string
}

/**
 * Traslado completo. Es el tipo de cada item en TransferListResponse.data[].
 */
export interface Transfer {
  id: string
  transfer_number: string
  from_warehouse_id: string
  to_warehouse_id: string
  from_warehouse_name: string
  to_warehouse_name: string
  from_warehouse: TransferWarehouseRef
  to_warehouse: TransferWarehouseRef
  status_id: string
  status_name: TransferStatusName
  status_label: string
  is_cancellable: boolean
  is_terminal: boolean
  status: TransferStatusRef
  requested_by: string
  requested_by_name: string | null
  approved_by: string | null
  approved_by_name: string | null
  dispatched_by: string | null
  dispatched_by_name: string | null
  received_by: string | null
  received_by_name: string | null
  cancelled_by: string | null
  cancelled_by_name: string | null
  dispatched_at: string | null
  received_at: string | null
  cancelled_at: string | null
  notes: string | null
  items: TransferItem[]
  discrepancies: TransferDiscrepancy[]
  created_at: string
  updated_at: string
}

/** Respuesta paginada de GET /api/transfers. */
export interface TransferListResponse {
  data: Transfer[]
  meta?: {
    current_page: number
    total: number
    per_page: number
    last_page: number
  }
}

/** Filtros aceptados por GET /api/transfers. */
export interface TransferFilters {
  status_id?: string
  from_warehouse_id?: string
  to_warehouse_id?: string
  requested_by?: string
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}

/**
 * Construye la query string para GET /api/transfers a partir de los filtros.
 * Omite los filtros vacios para no contaminar la URL.
 */
export function buildTransferQuery(filters: TransferFilters): string {
  const params = new URLSearchParams()
  if (filters.status_id) params.set('status_id', filters.status_id)
  if (filters.from_warehouse_id) params.set('from_warehouse_id', filters.from_warehouse_id)
  if (filters.to_warehouse_id) params.set('to_warehouse_id', filters.to_warehouse_id)
  if (filters.requested_by) params.set('requested_by', filters.requested_by)
  if (filters.date_from) params.set('date_from', filters.date_from)
  if (filters.date_to) params.set('date_to', filters.date_to)
  if (filters.page) params.set('page', String(filters.page))
  if (filters.per_page) params.set('per_page', String(filters.per_page))
  const qs = params.toString()
  return qs ? `/api/transfers?${qs}` : '/api/transfers'
}

/** Payload para POST /api/transfers. */
export interface CreateTransferPayload {
  to_warehouse_id: string
  items: { inventory_id: string; requested_quantity: number }[]
  notes?: string
}

/** Payload para PATCH /api/transfers/{id}/approve. */
export interface ApproveTransferPayload {
  items: { transfer_item_id: string; approved_quantity: number }[]
}

/** Payload para PATCH /api/transfers/{id}/receive. */
export interface ReceiveTransferPayload {
  items: { transfer_item_id: string; delivered_quantity: number }[]
}

/** Payload para PATCH /api/transfers/{id}/cancel. */
export interface CancelTransferPayload {
  reason?: string
}

/**
 * Accion para resolver una discrepancia (PATCH /api/transfers/{id}/discrepancies/{did}/resolve).
 *
 * Ninguna de las dos mueve stock. Se distinguen solo por como queda
 * registrada la mercaderia que no llego:
 *
 * - 'resolve':     la diferencia se cerro por otra via (la unidad llego y ya
 *   se contabilizo en otro traslado, hubo reposicion, o se corrigio por
 *   ajuste). El traslado cierra como RECEIVED y el inventario no se toca.
 *   En pantalla: "Cerrar sin ajuste".
 * - 'write_off':   la mercaderia se perdio (merma, robo, dano en transporte).
 *   El traslado cierra como RECEIVED igual, pero queda marcado como perdida
 *   asumida para la auditoria. En pantalla: "Dar de baja".
 *
 * Para registrar que el faltante llego y moverlo al destino de verdad, la
 * accion no es esta: se reabre la recepcion del traslado
 * (PARTIALLY_RECEIVED) y se suma la cantidad.
 */
export type ResolveDiscrepancyAction = 'resolve' | 'write_off'

export interface ResolveDiscrepancyPayload {
  action: ResolveDiscrepancyAction
  notes?: string
}

/**
 * Colores de los badges de estado segun la convencion del proyecto:
 * - DRAFT: gris (borrador, editable)
 * - REQUESTED: azul (solicitado, pendiente de aprobacion)
 * - APPROVED: verde (aprobado, listo para despachar)
 * - IN_TRANSIT: naranja (en camino)
 * - RECEIVED: verde oscuro (recibido, terminal)
 * - PARTIALLY_RECEIVED: naranja (recibido parcial, con discrepancia)
 * - CANCELLED: rojo (anulado, terminal)
 */
export function statusBadgeColor(name: TransferStatusName): string {
  switch (name) {
    case 'DRAFT': return 'default'
    case 'REQUESTED': return 'blue'
    case 'APPROVED': return 'green'
    case 'IN_TRANSIT': return 'orange'
    case 'RECEIVED': return 'cyan'
    case 'PARTIALLY_RECEIVED': return 'gold'
    case 'CANCELLED': return 'red'
    default: return 'default'
  }
}

/**
 * Catalogo de estados de traslado para el filtro.
 * Hardcodeado en el frontend porque no tenemos endpoint de catalogo.
 */
export const TRANSFER_STATUSES: { value: string; label: string }[] = [
  { value: 'DRAFT', label: 'Borrador' },
  { value: 'REQUESTED', label: 'Solicitado' },
  { value: 'APPROVED', label: 'Aprobado' },
  { value: 'IN_TRANSIT', label: 'En transito' },
  { value: 'RECEIVED', label: 'Recibido' },
  { value: 'PARTIALLY_RECEIVED', label: 'Recibido parcial' },
  { value: 'CANCELLED', label: 'Anulado' },
]
