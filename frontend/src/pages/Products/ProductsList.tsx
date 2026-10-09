/**
 * ProductsList — Tabla de productos con paginación, búsqueda y eliminar.
 * Se renderiza dentro de AppLayout
 */
import { useMemo, useState } from 'react'
import { Button, Input, Space, Table, Popconfirm, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { EditOutlined, DeleteOutlined, ReloadOutlined } from '@ant-design/icons'
import { useNavigate } from 'react-router-dom'
import { useApi } from '../../hooks/useApi'
import { apiFetch } from '../../lib/api'
import { ErrorAlert } from '../../components/ErrorAlert'
import { LoadingScreen } from '../../components/LoadingScreen'
import type { Product, ProductListResponse } from '../../lib/products'

/**
 * Renderiza la lista de productos.
 * @returns {JSX.Element}
 */
export function ProductsList() {

  // Hook para la navegación
  const navigate = useNavigate()

  // Props para el manejo de la paginación y búsqueda
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  /**
   * Hook para manejar el debounce del buscador
   */
  useMemo(() => {
    const t = setTimeout(() => setDebouncedSearch(search), 300)
    return () => clearTimeout(t)
  }, [search])

  /**
   * Query para la API
   * @param page - Página actual
   * @param debouncedSearch - Valor del buscador
   * @returns {string} Query para la API
   */
  const query = `/api/products?page=${page}${debouncedSearch ? `&name=${encodeURIComponent(debouncedSearch)}` : ''}`
  const { data, error, isLoading, refetch } = useApi<ProductListResponse>(query, [page, debouncedSearch])

  /**
   * Elimina un producto
   * @param product - Producto a eliminar
   * @returns {Promise<void>}
   */
  const handleDelete = async (product: Product) => {
    try {
      await apiFetch(`/api/products/${product.id}`, { method: 'DELETE' })
      message.success(`Producto "${product.name}" eliminado`)
      refetch()
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'No se pudo eliminar el producto'
      message.error(msg)
    }
  }

  /**
   * Renderiza la pantalla de carga si es necesario
   * @returns {JSX.Element}
   */
  if (isLoading && !data) return <LoadingScreen />

  /**
   * Datos de la lista de productos
   * @returns {Product[]} Lista de productos
   */
  const products = data?.data ?? []

  /**
   * Metadatos de la lista de productos
   * @returns {Object} Metadatos de la lista de productos
   */
  const meta = data?.meta

  /**
   * Columnas de la tabla
   * @returns {ColumnsType<Product>} Columnas de la tabla
   */
  const columns: ColumnsType<Product> = [
    {
      title: 'Nombre',
      dataIndex: 'name',
      key: 'name',
      width: 220,
      render: (name: string) => (
        <strong style={{ fontFamily: "'Space Grotesk', sans-serif" }}>{name}</strong>
      ),
    },
    {
      title: 'CUM',
      dataIndex: 'cum',
      key: 'cum',
      width: 130,
    },
    {
      title: 'Código Barras',
      dataIndex: 'barcode',
      key: 'barcode',
      width: 150,
      render: (barcode: string | null) =>
        barcode ?? <span style={{ color: '#94a3b8' }}>—</span>,
    },
    {
      title: 'Invima',
      dataIndex: 'invima_registration',
      key: 'invima_registration',
      width: 170,
    },
    {
      title: 'Fecha Registro',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 120,
      render: (date: string) =>
        new Date(date).toLocaleDateString('es-CO', {
          year: 'numeric',
          month: '2-digit',
          day: '2-digit',
        }),
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 120,
      render: (_: unknown, record: Product) => (
        <Space>
          <Button
            type="text"
            icon={<EditOutlined />}
            onClick={() => navigate(`/products/${record.id}/edit`)}
          />
          <Popconfirm
            title="¿Eliminar este producto?"
            description="Se marcará como inactivo. Si tiene lotes con stock, se bloqueará."
            onConfirm={() => handleDelete(record)}
            okText="Eliminar"
            cancelText="Cancelar"
            okButtonProps={{ danger: true }}
          >
            <Button type="text" danger icon={<DeleteOutlined />} />
          </Popconfirm>
        </Space>
      ),
    },
  ]

  /**
   * Renderiza la tabla de productos
   * @returns {JSX.Element}
   */
  return (
    <>
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          marginBottom: 24,
        }}
      >
        <h1
          style={{
            fontFamily: "'Space Grotesk', sans-serif",
            fontSize: 28,
            fontWeight: 700,
            color: '#0e7490',
            margin: 0,
          }}
        >
          Productos
        </h1>
        <Space>
          <Button icon={<ReloadOutlined />} onClick={refetch}>
            Recargar
          </Button>
          <Button type="primary" onClick={() => navigate('/products/new')}>
            + Nuevo Producto
          </Button>
        </Space>
      </div>

      <ErrorAlert message={error?.message ?? null} />

      <div style={{ marginBottom: 16 }}>
        <Input.Search
          placeholder="Buscar por nombre..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onSearch={(v) => setSearch(v)}
          allowClear
          style={{ maxWidth: 320 }}
        />
      </div>

      <Table<Product>
        rowKey="id"
        columns={columns}
        dataSource={products}
        loading={isLoading}
        pagination={{
          current: page,
          total: meta?.total ?? 0,
          pageSize: meta?.per_page ?? 15,
          onChange: setPage,
          showSizeChanger: false,
        }}
        locale={{ emptyText: 'No hay productos registrados' }}
      />
    </>
  )
}
