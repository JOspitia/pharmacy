/**
 * useAuth — hook para consumir el AuthContext.
 */

import { useContext } from 'react'
import { AuthContext, type AuthContextValue } from './authContextValue'

/**
 * Hook para consumir el AuthContext.
 * Separado de AuthContext.tsx para que Fast Refresh funcione correctamente.
 */
export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth debe usarse dentro de <AuthProvider>')
  }
  return ctx
}

export type { AuthContextValue }
