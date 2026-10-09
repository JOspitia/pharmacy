/**
 * Interfaz que define las propiedades del reporte inventario productos
 * @param id - ID del producto
 * @param name - Nombre del producto
 * @param cum - CUM del producto
 * @param barcode - Código de barras del producto
 * @param invima_registration - Registro Invima del producto
 * @param group_name - Nombre del grupo
 * @param lot_id - ID del lote
 * @param lot_code - Código del lote
 * @param stock - Stock del producto
 * @param expiration_date - Fecha de vencimiento del producto
 * @param bodega_id - ID de la bodega
 * @param bodega_name - Nombre de la bodega
 * @param alerta_color - Color de alerta del producto
 * @param alerta_exp - Alerta de vencimiento del producto
 */
export interface InventarioRow {
  product_id: string
  product_name: string
  cum: string
  barcode: string | null
  invima_registration: string
  group_name: string | null
  lot_id: string
  lot_code: string
  stock: number
  expiration_date: string
  bodega_id: string
  bodega_name: string
  alerta_color: string
  alerta_exp: string
}

/**
 * Interfaz que define las propiedades de la respuesta del backend para una lista de inventario productos
 * @param data - Array de inventario productos
 * @param meta - Metadatos de la paginación
 */
export interface InventarioResponse {
  data: InventarioRow[]
  meta: {
    current_page: number
    total: number
    per_page: number
    last_page: number
  }
}

/**
 * Interfaz que define las propiedades de los filtros disponibles para el reporte de inventario productos
 * @param cum - CUM del producto
 * @param name - Nombre del producto
 * @param bodega_id - ID de la bodega
 * @param group_id - ID del grupo
 * @param lot_code - Código del lote
 * @param estado - Estado del inventario productos
 * @param date_from - Fecha inicial del reporte
 * @param date_to - Fecha final del reporte
 */
export interface InventarioFiltros {
  cum?: string
  name?: string
  bodega_id?: string
  group_id?: string
  lot_code?: string
  estado?: '' | 'con_stock' | 'sin_stock' | 'por_vencer' | 'vencido'
  date_from?: string
  date_to?: string
}
