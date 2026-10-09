/**
 * Interfaz que define las propiedades del lote
 * @interface Lot
 * @property {string} id - ID del lote
 * @property {string} name - Nombre del lote
 * @property {number} stock - Stock del lote
 * @property {string} expiration_date - Fecha de expiración del lote
 * @property {string | null} description - Descripción del lote
 * @property {boolean} status - Estado del lote
 * @property {string} product_id - ID del producto
 * @property {string} warehouse_id - ID de la bodega
 * @property {object | undefined} product - Producto
 * @property {object | undefined} warehouse - Bodega
 * @property {string} derived_status - Estado derivado del lote
 * @property {string} created_at - Fecha de creación del lote
 * @property {string} updated_at - Fecha de actualización del lote
 * @property {string | null} deleted_at - Fecha de eliminación del lote
 */
export interface Lot {
  id: string
  name: string
  stock: number
  expiration_date: string
  description: string | null
  status: boolean
  product_id: string
  warehouse_id: string
  product?: { id: string; name: string }
  warehouse?: { id: string; name: string }
  derived_status: string
  created_at: string
  updated_at: string
  deleted_at: string | null
}

/**
 * Interfaz que define las propiedades del payload de un lote
 * @interface LotPayload
 * @property {string} name - Nombre del lote
 * @property {string} product_id - ID del producto
 * @property {string} warehouse_id - ID de la bodega
 * @property {number} stock - Stock del lote
 * @property {string} expiration_date - Fecha de expiración del lote
 * @property {string | null} description - Descripción del lote
 * @property {boolean} status - Estado del lote
 */
export interface LotPayload {
  name: string
  product_id: string
  warehouse_id: string
  stock: number
  expiration_date: string
  description?: string | null
  status?: boolean
}

/**
 * Interfaz que define las propiedades de la respuesta de la lista de lotes
 * @interface LotListResponse
 * @property {Lot[]} data - Datos de la lista de lotes
 * @property {object | undefined} meta - Metadatos de la lista de lotes
 */
export interface LotListResponse {
  data: Lot[]
  meta?: {
    current_page: number
    total: number
    per_page: number
    last_page: number
  }
}

/**
 * Interfaz que define las propiedades de la respuesta de un lote
 * @interface LotResponse
 * @property {Lot} data - Datos del lote
 */
export interface LotResponse {
  data: Lot
}

/**
 * Interfaz que define las propiedades de la respuesta del formulario de lotes
 * @interface FormDataResponse
 * @property {object[]} products - Productos
 * @property {object[]} bodegas - Bodegas
 */
export interface FormDataResponse {
  products: { id: string; name: string }[]
  bodegas: { id: string; name: string }[]
}
