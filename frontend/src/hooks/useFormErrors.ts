import { useMemo } from 'react'
import { ApiError } from '../lib/api'

/**
 * Hook para extraer y consultar errores de validación del backend (HTTP 422).
 * El backend retorna { message, errors: { campo: [msg1, msg2] } }.
 */

export function useFormErrors(error: unknown) {

  // Use para obtener los errores de validación
  const fieldErrors = useMemo<Record<string, string[]>>(() => {
    if (error instanceof ApiError && error.errors) {
      return error.errors
    }
    return {}
  }, [error])

  // Use para obtener el mensaje general de error
  const generalMessage = useMemo<string | null>(() => {
    if (error instanceof Error) {
      return error.message
    }
    return null
  }, [error])

  // Función para obtener el error de un campo específico
  const getError = (field: string): string | undefined =>
    fieldErrors[field]?.[0]

  // Función para verificar si un campo tiene error
  const hasError = (field: string): boolean =>
    Boolean(fieldErrors[field]?.length)

  // Devolver el objeto con los errores
  return { fieldErrors, generalMessage, getError, hasError }
}
