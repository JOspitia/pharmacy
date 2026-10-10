/**
 * useApi — Hook genérico para llamadas al backend con loading/error/data.
 * Usa el cliente api.ts (fetch + token automático + 401 → logout).
 *
 * Patrones aplicados:
 *   - React 19: useState funcional, sin useEffect innecesarios
 *   - rerender-functional-setstate: setData(prev => ...) para evitar closures stale
 *   - js-cache-storage: tokenStorage.get() cacheado a nivel de api.ts
 */

import { useCallback, useEffect, useState } from 'react'
import { ApiError, apiFetch } from '../lib/api'

/**
 * Interfaz que representa el resultado de una llamada a la API
 * @template T - Tipo de datos que se esperan de la API
 */
export interface UseApiResult<T> {
  data: T | null
  error: ApiError | null
  isLoading: boolean
  refetch: () => void
}

/**
 * Hook genérico para llamadas al backend con loading/error/data.
 * @template T - Tipo de datos que se esperan de la API
 * @param path - Ruta de la API
 * @param deps - Dependencias del hook
 * @returns Objeto con los datos, error, estado de carga y función de refetch
 */
export function useApi<T>(path: string | null, deps: unknown[] = []): UseApiResult<T> {

  // Estado para almacenar los datos
  const [data, setData] = useState<T | null>(null)

  // Estado para almacenar errores
  const [error, setError] = useState<ApiError | null>(null)

  // Estado para manejar el loading
  const [isLoading, setIsLoading] = useState<boolean>(Boolean(path))

  /**
   * Función que realiza la petición a la API
   */
  const fetchData = useCallback(async () => {

    // Si no hay path, no se hace nada
    if (!path) {
      setData(null)
      setIsLoading(false)
      return
    }

    // Setea el estado de loading a true
    setIsLoading(true)

    // Setea el estado de error a null
    setError(null)

    // Intenta hacer la petición a la API
    try {
      const result = await apiFetch<T>(path)

      // Setea los datos
      setData(result)
    } catch (err) {
      // Si hay error, lo setea
      if (err instanceof ApiError) {
        setError(err)
      } else {
        setError(new ApiError('Error de red', 0))
      }
    } finally {
      // Setea el estado de loading a false
      setIsLoading(false)
    }
  // El hook acepta un array de dependencias de longitud variable, asi que
    // el array de dependencias de useCallback no puede ser un literal. Sin esto
    // el callback se recrearia en cada render y el efecto de abajo se
    // ejecutaria infinitamente.
    //
    // Se silencian DOS reglas porque las dos disparan aqui y por la misma causa:
    //
    //   use-memo          exige que la lista de dependencias sea un literal.
    //                     Un spread la convierte en expresion, no literal.
    //   exhaustive-deps   no puede verificar estaticamente que un spread
    //                     contenga las dependencias correctas.
    //
    // Ninguna de las dos se puede satisfacer sin cambiar la firma publica
    // del hook: el llamador decide cuantas dependencias pasar.
    // eslint-disable-next-line react-hooks/use-memo, react-hooks/exhaustive-deps
  }, [path, ...deps])

  /**
   * Hook para ejecutar la petición a la API
   */
  useEffect(() => {
    void fetchData()
  }, [fetchData])

  /**
   * Función para refetch
   */
  const refetch = useCallback(() => {
    void fetchData()
  }, [fetchData])

  /**
   * Devuelve el objeto con los datos, error, estado de carga y función de refetch
   * @returns {UseApiResult<T>}
   */
  return { data, error, isLoading, refetch }
}
