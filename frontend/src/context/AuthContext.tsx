import {
  useCallback,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'

// Importar funciones y constantes de api
import { apiFetch, tokenStorage, UNAUTHORIZED_EVENT } from '../lib/api'
// Importar el context y el hook useAuth
import { AuthContext, type AuthContextValue } from './authContextValue'


// Interfaz para el usuario autenticado
export interface AuthUser {
  id: number
  name: string
  email: string
}

interface LoginResponse {
  token: string
}

/**
 * AuthProvider - Provider del Context API de autenticación
 * @param children - Contenido del componente
 * @returns Context de autenticación
 */
export function AuthProvider({ children }: { children: ReactNode }) {

  // useState para almacenar el token, el usuario y el estado de carga
  const [token, setToken] = useState<string | null>(() => tokenStorage.get())
  const [user, setUser] = useState<AuthUser | null>(null)
  const [isLoading, setIsLoading] = useState<boolean>(Boolean(token))

  // useCallback para evitar recreación del callback
  const loadUser = useCallback(async () => {
    try {
      const me = await apiFetch<AuthUser>('/api/user')
      setUser(me)
    } catch {
      tokenStorage.clear()
      setToken(null)
      setUser(null)
    } finally {
      setIsLoading(false)
    }
  }, [])

  // Efecto para cargar el usuario si existe token
  useEffect(() => {
    if (token) {
      void loadUser()
    }
  }, [token, loadUser])


  // Efecto para manejar el evento de logout
  useEffect(() => {
    const handler = () => {
      setToken(null)
      setUser(null)
    }
    window.addEventListener(UNAUTHORIZED_EVENT, handler)
    return () => window.removeEventListener(UNAUTHORIZED_EVENT, handler)
  }, [])

  // Función para iniciar sesión
  const login = useCallback(async (name: string, password: string) => {
    const data = await apiFetch<LoginResponse>('/api/login', {
      method: 'POST',
      body: { name, password },
    })
    tokenStorage.set(data.token)
    setToken(data.token)
    await loadUser()
  }, [loadUser])

  // Función para cerrar sesión
  const logout = useCallback(async () => {
    try {
      await apiFetch('/api/logout', { method: 'POST' })
    } catch {
      // ignorar errores de logout
    }
    tokenStorage.clear()
    setToken(null)
    setUser(null)
  }, [])

  // useMemo para evitar recreación del objeto
  const value = useMemo<AuthContextValue>(
    () => ({
      token,
      user,
      isAuthenticated: Boolean(token) && Boolean(user),
      isLoading,
      login,
      logout,
    }),
    [token, user, isLoading, login, logout],
  )

  // Renderizado del provider
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
