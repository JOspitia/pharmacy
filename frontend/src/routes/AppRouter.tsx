/**
 * AppRouter — React Router v7 con rutas anidadas.
 * AppLayout es el chrome (sidebar + header + footer) y contiene <Outlet />.
 * Las rutas hijas se renderizan dentro del <Content /> de AppLayout.
 */

import { Navigate, createBrowserRouter, RouterProvider } from 'react-router-dom'
import { useAuth } from '../context/useAuth'
import { LoadingScreen } from '../components/LoadingScreen'
import { AppLayout } from '../components/AppLayout'
import { Login } from '../pages/Login'
import { Home } from '../pages/Home'
import { GroupsList, GroupFormModal } from '../pages/Groups'
import { WarehouseFormModal, WarehousesList } from '../pages/Warehouse'

/**
 * PrivateLayout - Layout para rutas privadas
 * @returns {JSX.Element}
 */
function PrivateLayout() {
	const { isAuthenticated, isLoading } = useAuth()

	if (isLoading) return <LoadingScreen />
	if (!isAuthenticated) return <Navigate to="/login" replace />

	return <AppLayout />
}

/**
 * PublicLayout - Layout para rutas públicas
 * @returns {JSX.Element}
 */
function PublicLayout() {
	const { isAuthenticated, isLoading } = useAuth()

	if (isLoading) return <LoadingScreen />
	if (isAuthenticated) return <Navigate to="/Home" replace />

	return <Login />
}

/**
 * Router principal
 * @returns {RouterProvider} - Router de React Router
 */
const router = createBrowserRouter([
	{
		element: <PrivateLayout />,
		children: [
			// Ruta base de inicio
			{ path: '/Home', element: <Home /> },
			// Rutas del módulo de grupos agrupadas
			{
				path: '/groups',
				children: [
					{ index: true, element: <GroupsList /> },
					{ path: 'new', element: <GroupFormModal /> },
					{ path: ':id/edit', element: <GroupFormModal /> },
				]
			},
			{
				path: '/bodegas',
				children: [
					{ index: true, element: <WarehousesList /> },
					{ path: 'new', element: <WarehouseFormModal /> },
					{ path: ':id/edit', element: <WarehouseFormModal /> },
				]
			},
			// Ruta de redirección a la ruta base de inicio
			{ path: '/', element: <Navigate to="/Home" replace /> },
			// Ruta de redirección a la ruta base de inicio para rutas no encontradas
			{ path: '*', element: <Navigate to="/" replace /> },
		],
	},
	{
		element: <PublicLayout />,
		children: [
			{ path: '/login', element: <Login /> },
		],
	}
])

/**
 * AppRouter - Componente que muestra el router principal
 * @returns {RouterProvider} - Router de React Router
 */
export function AppRouter() {
	return <RouterProvider router={router} />
}
