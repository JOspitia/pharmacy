// Archivo para manejar las peticiones a la API

// Constantes para el token y el evento de no autorizado
const TOKEN_KEY = 'auth_token'
const UNAUTHORIZED_EVENT = 'auth:unauthorized'

/**
 * Objeto para manejar el token de autenticación
 */
export const tokenStorage = {
  get: (): string | null => sessionStorage.getItem(TOKEN_KEY),
  set: (token: string) => sessionStorage.setItem(TOKEN_KEY, token),
  clear: () => sessionStorage.removeItem(TOKEN_KEY),
}

/**
 * Clase para manejar los errores de la API
 */
export class ApiError extends Error {
  status: number
  errors?: Record<string, string[]>

  /**
   * @param message - Mensaje de error
   * @param status - Estado HTTP
   * @param errors - Errores de validación
   */
  constructor(
    message: string,
    status: number,
    errors?: Record<string, string[]>,
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

// Tipo para los métodos HTTP
type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

// Interfaz para las opciones de la petición
export interface RequestOptions {
  method?: Method
  body?: unknown
  headers?: Record<string, string>
  signal?: AbortSignal
}

/**
 * Función para hacer peticiones a la API
 * @param path - Ruta de la API
 * @param options - Opciones de la petición
 * @returns Respuesta de la API
 */
export async function apiFetch<T = unknown>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  const { method = 'GET', body, headers = {}, signal } = options

  // Token de autenticación
  const token = tokenStorage.get()

  // Opciones de la petición
  const init: RequestInit = {
    method,
    headers: {
      Accept: 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
    ...(body ? { body: JSON.stringify(body) } : {}),
    ...(signal ? { signal } : {}),
  }

  // Petición a la API
  let response: Response
  try {
    response = await fetch(path, init)
  } catch {
    // fetch() rechaza cuando el backend no responde (red, CORS, server down)
    throw new ApiError(
      'No es posible establecer conexión con el servidor, intente nuevamente',
      0,
    )
  }

  // Si la respuesta es 204, devolver undefined
  if (response.status === 204) {
    return undefined as T
  }

  // Contenido de la respuesta
  const contentType = response.headers.get('content-type') ?? ''
  const isJson = contentType.includes('application/json')
  const payload = isJson ? await response.json() : await response.text()

  // Si la respuesta no es exitosa, lanzar un error
  if (!response.ok) {
    // Si la respuesta es 401, limpiar el token y lanzar un evento
    if (response.status === 401) {
      tokenStorage.clear()
      window.dispatchEvent(new CustomEvent(UNAUTHORIZED_EVENT))
    }
    // Mensaje de error
    const message =
      (isJson && payload?.message) || response.statusText || 'Error de red'
    // Errores de validación
    const errors = isJson && payload?.errors ? payload.errors : undefined
    throw new ApiError(message, response.status, errors)
  }

  // Devolver la respuesta
  return payload as T
}

// Constante para el evento de no autorizado
export { UNAUTHORIZED_EVENT }
