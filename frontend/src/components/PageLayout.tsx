import { Layout } from 'antd';
import type { CSSProperties, ReactNode } from 'react';

/**
 * Props - Interface que define las propiedades del componente PageLayout
 * @param children - Contenido del componente
 * @param width - Ancho del contenedor
 * @param style - Estilos personalizados
 */
interface Props {
  children: ReactNode
  width?: number
  style?: CSSProperties
}

/**
 * PageLayout - Componente que muestra un layout reutilizable para páginas centradas
 */
export function PageLayout({ children, width = 400, style }: Props) {
  return (
    <Layout style={{
      minHeight: '100vh',
      alignItems: 'center',
      justifyContent: 'center',
      padding: 16,
      ...style
    }}>
      <div style={{ width }}>{children}</div>
    </Layout>
  );
}