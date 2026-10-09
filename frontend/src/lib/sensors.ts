/**
 * Interfaz que define las propiedades del sensor
 * @param id - ID del sensor
 * @param name - Nombre del sensor
 * @param description - Descripción del sensor
 * @param status - Estado del sensor
 * @param warehouse_id - ID de la bodega
 * @param created_at - Fecha de creación del sensor
 * @param updated_at - Fecha de actualización del sensor
 * @param deleted_at - Fecha de eliminación del sensor
 */
export interface Sensor {
  id: string
  name: string
  description: string | null
  status: boolean
  warehouse_id: string
  created_at: string
  updated_at: string
  deleted_at: string | null
}

/**
 * Interfaz que define las propiedades del payload del sensor
 * @param name - Nombre del sensor
 * @param description - Descripción del sensor
 * @param status - Estado del sensor
 * @param warehouse_id - ID de la bodega
 */
export interface SensorPayload {
  name: string
  description?: string | null
  status?: boolean
  warehouse_id: string
}

/**
 * Interfaz que define las propiedades de la respuesta del backend para una lista de sensores
 * @param data - Array de sensores
 * @param meta - Metadatos de la paginación
 */
export interface SensorListResponse {
  data: Sensor[]
  meta?: {
    current_page: number
    total: number
    per_page: number
    last_page: number
  }
}

/**
 * Interfaz que define las propiedades de la respuesta del backend para un sensor
 * @param data - Sensor
 */
export interface SensorResponse {
  data: Sensor
}

/**
 * Interfaz que define las propiedades de la respuesta del backend para un formulario de sensores
 * @param bodegas - Array de bodegas
 */
export interface FormDataResponse {
  bodegas: { id: string; name: string }[]
}
