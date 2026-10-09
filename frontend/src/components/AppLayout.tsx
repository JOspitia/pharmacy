/**
 * AppLayout — Layout principal con sidebar + header + footer.
 * Usa <Outlet /> de React Router para renderizar la ruta hija.
 * Las rutas anidadas se renderizan dentro de <Content /> sin recargar.
 *
 * Patrón SaaS clásico: chrome (sidebar+header+footer) siempre visible,
 * el contenido central cambia según la ruta.
 */

import { Layout, Menu, Button, theme } from 'antd'
import {
  AppstoreOutlined,
  DatabaseOutlined,
  InboxOutlined,
  ShoppingOutlined,
  TeamOutlined,
  LogoutOutlined,
} from '@ant-design/icons'
import { Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/useAuth'
import type { ReactNode } from 'react'

// Importaciones de componentes de componentes de UI
const { Header, Footer, Sider, Content } = Layout

/**
 * Interfaz que define la estructura de un item de navegación
 * @interface NavItem
 */
interface NavItem {
  key: string
  label: string
  icon: ReactNode
  path: string
}

/**
 * Items de navegación
 * @type {NavItem[]}
 */
const navItems: NavItem[] = [
  { key: '1', label: 'Inicio', icon: <AppstoreOutlined />, path: '/Home' },
  { key: '2', label: 'Grupos', icon: <TeamOutlined />, path: '/groups' },
  { key: '3', label: 'Bodegas', icon: <DatabaseOutlined />, path: '/bodegas' },
  { key: '4', label: 'Productos', icon: <ShoppingOutlined />, path: '/products' },
  { key: '5', label: 'Lotes', icon: <InboxOutlined />, path: '/lots' },
]

/**
 * AppLayout - Componente que muestra el layout principal
 * @returns {JSX.Element}
 */
export function AppLayout() {

  /**
   * Hook para obtener el usuario y la función de logout
   * @returns {UseAuthReturn}
   */
  const { logout } = useAuth()

  /**
   * Hook para navegar entre rutas
   * @returns {UseNavigateReturn}
   */
  const navigate = useNavigate()

  /**
   * Hook para obtener la ruta actual
   * @returns {UseLocationReturn}
   */
  const location = useLocation()

  /**
   * Hook para obtener el tema actual
   * @returns {ThemeToken}
   */
  const { token: { colorBgContainer } } = theme.useToken()

  /**
   * Item activo según la ruta actual
   * @returns {string}
   */
  const activeKey =
    navItems.find((item) => location.pathname.startsWith(item.path))?.key ?? '1'

  /**
   * Layout principal
   * @returns {JSX.Element}
   */
  return (
    <Layout style={{ minHeight: '100vh' }}>
      {/*Sider - Barra lateral*/}
      <Sider collapsible breakpoint="lg" collapsedWidth="80">
        {/*Logo*/}
        <div
          style={{
            height: 64,
            margin: 16,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            color: 'white',
          }}
        >
          <h2>App Farmacia</h2>
        </div>
        <Menu
          theme="dark"
          mode="inline"
          selectedKeys={[activeKey]}
          items={navItems.map((item) => ({
            key: item.key,
            icon: item.icon,
            label: item.label,
            onClick: () => navigate(item.path),
          }))}
        />
      </Sider>

      <Layout>
        {/*Header - Barra de navegación superior*/}
        <Header
          style={{
            background: colorBgContainer,
            display: 'flex',
            justifyContent: 'flex-end',
            alignItems: 'center',
            paddingInline: 24,
          }}
        >
          <Button
            type="primary"
            icon={<LogoutOutlined />}
            onClick={logout}
          >
            Cerrar Sesión
          </Button>
        </Header>

        {/*Content - Contenido principal*/}
        <Content
          style={{
            margin: '24px 16px',
            padding: 24,
            background: colorBgContainer,
            borderRadius: 8,
            minHeight: 280,
          }}
        >
          {/* Aquí se renderiza la ruta hija */}
          <Outlet />
        </Content>

        {/*Footer - Pie de página*/}
        <Footer style={{ textAlign: 'center' }}>
          Prueba Técnica - Johan Ospitia
        </Footer>
      </Layout>
    </Layout>
  )
}
