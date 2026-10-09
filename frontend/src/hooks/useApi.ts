/**
 * useApi — Hook genérico para llamadas al backend con loading/error/data.
 * Usa el cliente api.ts (fetch + token automático + 401 → logout).
 *
 * Patrones aplicados:
 *   - React 19: useState funcional, sin useEffect innecesarios
 *   - rerender-functional-setstate: setData(prev => ...) para evitar closures stale
 *   - js-cache-storage: tokenStorage.get() cacheado a nivel de api.ts
 */

import { useCallback, useEffect, useRef, useState } from 'react'
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

  // Ref para forzar refetch (no causa re-render)
  const refetchRef = useRef(0)

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
  }, [path, ...deps])

  /**
   * Hook para ejecutar la petición a la API
   */
  useEffect(() => {
    void fetchData()
  }, [fetchData, refetchRef.current])

  /**
   * Función para refetch
   */
  const refetch = useCallback(() => {
    refetchRef.current += 1
    void fetchData()
  }, [fetchData])

  /**
   * Devuelve el objeto con los datos, error, estado de carga y función de refetch
   * @returns {UseApiResult<T>}
   */
  return { data, error, isLoading, refetch }
}
