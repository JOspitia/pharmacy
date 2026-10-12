/**
 * Tipos compartidos de los tests E2E.
 *
 * Duplican (de forma minima) los types de src/lib para evitar importar
 * desde el codigo de la aplicacion (distintos tsconfig).
 */

/** Estado tecnico del traslado. */
export type TransferStatusName =
  | 'DRAFT'
  | 'REQUESTED'
  | 'APPROVED'
  | 'IN_TRANSIT'
  | 'RECEIVED'
  | 'PARTIALLY_RECEIVED'
  | 'CANCELLED'

/** Estado de una discrepancia. */
export type DiscrepancyStatus = 'PENDING' | 'RESOLVED' | 'WRITTEN_OFF'

/** Warehouse reducido (lo que devuelve la API en las relaciones). */
export interface WarehouseRef {
  id: string
  name: string
}

/** Lot reducido. */
export interface LotRef {
  id: string
  name: string
  expiry_date: string | null
}

/** Product reducido. */
export interface ProductRef {
  id: string
  name: string
}

/** Inventario segun lo devuelve la API. */
export interface InventoryRecord {
  id: string
  warehouse_id?: string
  bodega?: WarehouseRef
  lot?: LotRef
  product?: ProductRef
  quantity: number
}

/** Bodega tal como la devuelve GET /api/bodegas. */
export interface BodegaRecord {
  id: string
  name: string
}

/** Item de un traslado segun la API. */
export interface TransferItemRecord {
  id: string
  inventory_id: string
  requested_quantity: number
  approved_quantity: number | null
  delivered_quantity: number | null
  difference: number | null
}

/** Discrepancia de un traslado segun la API. */
export interface TransferDiscrepancyRecord {
  id: string
  status: DiscrepancyStatus
  difference: number
  resolution_notes: string | null
  resolved_at: string | null
}

/** Traslado tal como lo devuelve la API. */
export interface TransferRecord {
  id: string
  transfer_number: string
  status_name: TransferStatusName
  status_label?: string
  is_terminal?: boolean
  is_cancellable?: boolean
  from_warehouse?: WarehouseRef
  to_warehouse?: WarehouseRef
  discrepancies?: TransferDiscrepancyRecord[]
  items?: TransferItemRecord[]
}
