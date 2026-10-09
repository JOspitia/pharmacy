/**
 * Home — Contenido central de la página de inicio.
 * El sidebar + header + footer viven en AppLayout.
 * Esta pantalla solo renderiza el contenido que va dentro del <Content />.
 */

import { Typography } from 'antd'

/**
 * Home - Componente que muestra la página de inicio
 * @returns {JSX.Element}
 */
export function Home() {
  /**
   * Renderiza la página de inicio.
   * @returns {JSX.Element}
   */
  return (
    <>
      <Typography.Title level={2} style={{ fontFamily: "'Space Grotesk', sans-serif", color: '#0e7490' }}>
        Bienvenido, {localStorage.getItem('user_name') || 'usuario'}
      </Typography.Title>
      <Typography.Paragraph type="secondary">
        Aquí puedes gestionar tu inventario, sensores y lotes de forma centralizada.
      </Typography.Paragraph>

      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
          gap: 16,
          marginTop: 32,
        }}
      >
        <CardLink title="Grupos" description="Gestiona los grupos de productos" path="/groups" />
        <CardLink title="Bodegas" description="Administra las bodegas" path="/bodegas" />
        <CardLink title="Productos" description="CRUD de productos" path="/products" />
        <CardLink title="Lotes" description="Parametrización de lotes" path="/lots" />
      </div>
    </>
  )
}

/**
 * Interface para las props del componente CardLink
 * @param title - Título de la tarjeta
 * @param description - Descripción de la tarjeta
 * @param path - Ruta de la tarjeta
 */
interface CardLinkProps {
  title: string
  description: string
  path: string
}

/**
 * CardLink - Componente que muestra una tarjeta con un enlace
 * @param title - Título de la tarjeta
 * @param description - Descripción de la tarjeta
 * @param path - Ruta de la tarjeta
 * @returns {JSX.Element}
 */
function CardLink({ title, description, path }: CardLinkProps) {
  return (
    <a
      href={path}
      style={{
        display: 'block',
        padding: 20,
        background: '#fff',
        border: '1px solid #e5e7eb',
        borderRadius: 8,
        textDecoration: 'none',
        color: 'inherit',
        transition: 'all 0.2s',
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.borderColor = '#0e7490'
        e.currentTarget.style.transform = 'translateY(-2px)'
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.borderColor = '#e5e7eb'
        e.currentTarget.style.transform = 'translateY(0)'
      }}
    >
      <h3 style={{ margin: '0 0 8px', fontFamily: "'Space Grotesk', sans-serif", color: '#0e7490' }}>
        {title}
      </h3>
      <p style={{ margin: 0, color: '#64748b', fontSize: 14 }}>{description}</p>
    </a>
  )
}
