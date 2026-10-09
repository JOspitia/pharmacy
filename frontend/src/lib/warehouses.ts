/**
 * Interfaz que define las propiedades de la bodega
 * @param id - ID de la bodega
 * @param name - Nombre de la bodega
 * @param description - Descripción de la bodega
 * @param status - Estado de la bodega
 * @param created_at - Fecha de creación de la bodega
 * @param updated_at - Fecha de actualización de la bodega
 * @param deleted_at - Fecha de eliminación de la bodega
 */
export interface Warehouse {
    id: string
    name: string
    description: string | null
    status: boolean
    created_at: string
    updated_at: string
    deleted_at: string | null
}

/**
 * Interfaz que define las propiedades de la carga útil de la bodega
 * @param name - Nombre de la bodega
 * @param description - Descripción de la bodega
 * @param status - Estado de la bodega
 */
export interface WarehousePayload {
    name: string
    description?: string | null
    status: boolean
}

/**
 * Interfaz que define las propiedades de la respuesta de la lista de bodegas
 * @param data - Datos de la lista de bodegas
 * @param meta - Metadatos de la lista de bodegas
 */
export interface WarehouseListResponse {
    data: Warehouse[]
    meta?: {
        current_page: number
        total: number
        per_page: number
        last_page: number
    }
}

/**
 * Interfaz que define las propiedades de la respuesta de la bodega
 * @param data - Datos de la bodega
 */
export interface WarehouseResponse {
    data: Warehouse
}
