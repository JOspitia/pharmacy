/**
 * AppRouter — React Router v7 con createBrowserRouter.
 * Usa <LoadingScreen /> en los layouts (refactor).
 */

import { Navigate, Outlet, createBrowserRouter, RouterProvider } from 'react-router-dom'
import { useAuth } from '../context/useAuth'
import { LoadingScreen } from '../components/LoadingScreen'
import { Login } from '../pages/Login'
import { Home } from '../pages/Home'

// Layout para rutas privadas
function PrivateLayout() {
	const { isAuthenticated, isLoading } = useAuth()

	if (isLoading) return <LoadingScreen />
	if (!isAuthenticated) return <Navigate to="/login" replace />

	return <Outlet />
}

// Layout para rutas públicas
function PublicLayout() {
	const { isAuthenticated, isLoading } = useAuth()

	if (isLoading) return <LoadingScreen />
	if (isAuthenticated) return <Navigate to="/Home" replace />

	return <Outlet />
}

// Router principal
const router = createBrowserRouter([
	// Layout para rutas públicas
	{
		element: <PublicLayout />,
		children: [
			// Ruta de login
			{ path: '/login', element: <Login /> }
		],
	},
	// Layout para rutas privadas
	{
		element: <PrivateLayout />,
		children: [
			// Ruta de inicio
			{ path: '/Home', element: <Home /> },
			// Redirección a la página de inicio
			{ path: '/', element: <Navigate to="/Home" replace /> },
		],
	},
	// Redirección a la página de inicio
	{ path: '*', element: <Navigate to="/" replace /> },
])

// Componente para el router principal
export function AppRouter() {
	return <RouterProvider router={router} />
}
