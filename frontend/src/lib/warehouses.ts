/**
 * Interfaz que define las propiedades de la bodega.
 * Mantener sincronizado con app/Http/Resources/BodegaResource.php del backend.
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
 * Interfaz que define las propiedades de la carga útil del grupo
 * @param name - Nombre del grupo
 */
export interface WarehousePayload {
    name: string
    description?: string | null
    status: boolean
}

/**
 * Interface que define las propiedades de la respuesta de la lista de grupos
 * @param data - Datos de la lista de grupos
 * @param meta - Metadatos de la lista de grupos
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
 * Interfaz que define las propiedades de la respuesta del grupo
 * @param data - Datos del grupo
 */
export interface WarehouseResponse {
    data: Warehouse
}
