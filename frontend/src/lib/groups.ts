/**
 * Interfaz que define las propiedades del grupo
 * @param id - ID del grupo
 * @param name - Nombre del grupo
 * @param description - Descripción del grupo
 * @param status - Estado del grupo
 * @param created_at - Fecha de creación del grupo
 * @param updated_at - Fecha de actualización del grupo
 * @param deleted_at - Fecha de eliminación del grupo
 */
export interface Group {
  id: string
  name: string
  description: string | null
  status: boolean
  created_at: string
  updated_at: string
  deleted_at: string | null
}

/**
 * Interfaz que define las propiedades de la carga útil del grupo
 * @param name - Nombre del grupo
 * @param description - Descripción del grupo
 * @param status - Estado del grupo
 */
export interface GroupPayload {
  name: string
  description?: string | null
  status?: boolean
}

/**
 * Interface que define las propiedades de la respuesta de la lista de grupos
 * @param data - Datos de la lista de grupos
 * @param meta - Metadatos de la lista de grupos
 */
export interface GroupsListResponse {
  data: Group[]
  meta?: {
    current_page: number
    total: number
    per_page: number
    last_page: number
  }
}

/**
 * Interfaz que define las propiedades de la respuesta del grupo
 * @param data - Datos del grupo
 */
export interface GroupResponse {
  data: Group
}
