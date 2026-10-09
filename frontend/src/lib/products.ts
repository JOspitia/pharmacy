/**
 * Interfaz que define las propiedades del producto
 * @param id - ID del producto
 * @param name - Nombre del producto
 * @param cum - Código Único de Medicamento del producto
 * @param barcode - Código de barras del producto
 * @param invima_registration - Registro Invima del producto
 * @param description - Descripción del producto
 * @param status - Estado del producto
 * @param group_id - ID del grupo del producto
 * @param unit_id - ID de la unidad del producto
 * @param group - Grupo del producto
 * @param unit - Unidad del producto
 * @param created_at - Fecha de creación del producto
 * @param updated_at - Fecha de actualización del producto
 * @param deleted_at - Fecha de eliminación del producto
 */
export interface Product {
  id: string
  name: string
  cum: string
  barcode: string | null
  invima_registration: string
  description?: string | null
  status: boolean
  group_id?: string
  unit_id?: string
  group?: { id: string; name: string }
  unit?: { id: string; name: string; abbreviation?: string }
  created_at: string
  updated_at: string
  deleted_at: string | null
}

/**
 * Interfaz que define las propiedades del payload del producto
 * @param name - Nombre del producto
 * @param cum - Código Único de Medicamento del producto
 * @param barcode - Código de barras del producto
 * @param invima_registration - Registro Invima del producto
 * @param description - Descripción del producto
 * @param group_id - ID del grupo del producto
 * @param unit_id - ID de la unidad del producto
 */
export interface ProductPayload {
  name: string
  cum: string
  barcode?: string | null
  invima_registration: string
  description?: string | null
  group_id: string
  unit_id: string
}

/**
 * Interfaz que define las propiedades de la respuesta del backend para una lista de productos
 * @param data - Array de productos
 * @param meta - Metadatos de la paginación
 */
export interface ProductListResponse {
  data: Product[]
  meta?: {
    current_page: number
    total: number
    per_page: number
    last_page: number
  }
}

/**
 * Interfaz que define las propiedades de la respuesta del backend para un producto
 * @param data - Producto
 */
export interface ProductResponse {
  data: Product
}

/**
 * Interfaz que define las propiedades de la respuesta del backend para un formulario de productos
 * @param groups - Array de grupos
 * @param units - Array de unidades
 */
export interface FormDataResponse {
  groups: { id: string; name: string }[]
  units: { id: string; name: string; abbreviation?: string }[]
}
