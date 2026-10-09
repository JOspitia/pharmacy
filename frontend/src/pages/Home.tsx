import { Layout, Menu, Button, Typography, theme } from 'antd';
import { AppstoreOutlined, DatabaseOutlined, LogoutOutlined } from '@ant-design/icons';
import { useAuth } from '../context/useAuth';

const { Header, Footer, Sider, Content } = Layout;

/**
 * Home - Componente que muestra la pantalla de inicio
 */

export function Home() {

  // Hook personalizado para manejar el estado de la aplicacion
  const { user, logout } = useAuth();

  // Hook personalizado para manejar los estilos de la aplicacion
  const { token: { colorBgContainer } } = theme.useToken();

  // Renderizado del componente
  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Sider collapsible breakpoint="lg" collapsedWidth="80">
        <div style={{ height: 64, margin: 16, display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'white' }} ><h2>App Farmacia</h2></div>
        <Menu theme="dark" mode="inline" defaultSelectedKeys={['1']}>
          <Menu.Item key="1" icon={<AppstoreOutlined />}>Inicio</Menu.Item>
          <Menu.Item key="2" icon={<DatabaseOutlined />}>Bodegas</Menu.Item>
        </Menu>
      </Sider>

      <Layout>
        <Header style={{ background: colorBgContainer, display: 'flex', justifyContent: 'flex-end', alignItems: 'center' }}>
          <Button type="primary" icon={<LogoutOutlined />} onClick={logout}>Cerrar Sesión</Button>
        </Header>

        <Content style={{ margin: '24px 16px', padding: 24, background: colorBgContainer, borderRadius: 8 }}>
          <Typography.Title level={2}>Bienvenido, {user?.name}</Typography.Title>
          <Typography.Paragraph type="secondary">
            Aquí puedes gestionar tu inventario, sensores y lotes de forma centralizada.
          </Typography.Paragraph>
        </Content>

        <Footer style={{ textAlign: 'center' }}>
          <Typography.Text type="secondary">Prueba Técnica - Johan Ospitia</Typography.Text>
        </Footer>
      </Layout>
    </Layout>
  );
}