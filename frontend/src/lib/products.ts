/**
 * Tipos del módulo de Productos (HU-01 backend).
 * Mantener sincronizados con app/Http/Resources/ProductResource.php del backend.
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

export interface ProductPayload {
  name: string
  cum: string
  barcode?: string | null
  invima_registration: string
  description?: string | null
  group_id: string
  unit_id: string
}

export interface ProductListResponse {
  data: Product[]
  meta?: {
    current_page: number
    total: number
    per_page: number
    last_page: number
  }
}

export interface ProductResponse {
  data: Product
}

export interface FormDataResponse {
  groups: { id: string; name: string }[]
  units: { id: string; name: string; abbreviation?: string }[]
}
