/**
 * Context y tipo de AuthContext — separado del provider para Fast Refresh.
 */

import { createContext } from 'react'

/**
 * Interface que define las propiedades del AuthContext
 */
export interface AuthContextValue {
  token: string | null
  user: AuthUser | null
  isAuthenticated: boolean
  isLoading: boolean
  login: (name: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

/**
 * Interface que define las propiedades del AuthUser
 */
export interface AuthUser {
  id: number
  name: string
  email: string
}

/**
 * Context que almacena el estado de la aplicación
 */
export const AuthContext = createContext<AuthContextValue | undefined>(undefined)
