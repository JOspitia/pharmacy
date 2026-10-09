/**
 * LotsList — Tabla de lotes con paginación, búsqueda, toggle status y delete.
 * Se renderiza dentro de AppLayout
 */
import { useMemo, useState } from 'react'
import { Button, Input, Space, Table, Tag, Popconfirm, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import {
  EditOutlined,
  DeleteOutlined,
  ReloadOutlined,
} from '@ant-design/icons'
import { useNavigate } from 'react-router-dom'
import { useApi } from '../../hooks/useApi'
import { apiFetch } from '../../lib/api'
import { ErrorAlert } from '../../components/ErrorAlert'
import { LoadingScreen } from '../../components/LoadingScreen'
import type { Lot, LotListResponse } from '../../lib/lots'

/**
 * LotsList - Componente que muestra la lista de lotes
 * @returns {JSX.Element}
 */
export function LotsList() {
  // Hooks de estado
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  /**
   * Hook que debouncea la búsqueda
   * @param search - Término de búsqueda
   * @returns {void}
   */
  useMemo(() => {
    const t = setTimeout(() => setDebouncedSearch(search), 300)
    return () => clearTimeout(t)
  }, [search])

  // Crear la consulta a la API
  const query = `/api/lots?page=${page}${debouncedSearch ? `&name=${encodeURIComponent(debouncedSearch)}` : ''}`
  const { data, error, isLoading, refetch } = useApi<LotListResponse>(query, [page, debouncedSearch])

  // Event handler para cambiar el estado del lote
  const handleToggleStatus = async (lot: Lot) => {
    try {
      // Cambiar estado del lote
      await apiFetch(`/api/lots/${lot.id}/toggle-status`, { method: 'PATCH' })
      message.success(`Lote ${lot.status ? 'inactivado' : 'activado'}`)
      refetch()
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'No se pudo cambiar el estado'
      message.error(msg)
    }
  }

  // Event handler para eliminar el lote
  const handleDelete = async (lot: Lot) => {
    try {
      // Eliminar lote
      await apiFetch(`/api/lots/${lot.id}`, { method: 'DELETE' })
      message.success(`Lote "${lot.name}" eliminado`)
      refetch()
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'No se pudo eliminar el lote'
      message.error(msg)
    }
  }

  // Mostrar pantalla de carga si está cargando
  if (isLoading && !data) return <LoadingScreen />

  // Obtener los lotes y metadatos
  const lots = data?.data ?? []
  const meta = data?.meta

  // Definir columnas de la tabla
  const columns: ColumnsType<Lot> = [
    {
      title: 'Código',
      dataIndex: 'name',
      key: 'name',
      render: (name: string) => (
        <strong style={{ fontFamily: "'Space Grotesk', sans-serif" }}>{name}</strong>
      ),
    },
    {
      title: 'Producto',
      dataIndex: ['product', 'name'],
      key: 'product_name',
      width: 200,
      render: (_, record) => record.product?.name ?? '—',
    },
    {
      title: 'Bodega',
      dataIndex: ['warehouse', 'name'],
      key: 'warehouse_name',
      width: 180,
      render: (_, record) => record.warehouse?.name ?? '—',
    },
    {
      title: 'Stock',
      dataIndex: 'stock',
      key: 'stock',
      width: 80,
      render: (stock: number) => stock,
    },
    {
      title: 'Vencimiento',
      dataIndex: 'expiration_date',
      key: 'expiration_date',
      width: 130,
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      width: 140,
      render: (status: boolean, record: Lot) => (
        <Popconfirm
          title={status ? '¿Inactivar este lote?' : '¿Activar este lote?'}
          description={
            record.stock > 0
              ? 'El lote tiene stock activo. No se puede inactivar.'
              : undefined
          }
          onConfirm={() => handleToggleStatus(record)}
          okText="Sí"
          cancelText="No"
          disabled={record.stock > 0}
        >
          <Tag
            color={status ? 'cyan' : 'default'}
            style={{ cursor: record.stock > 0 ? 'not-allowed' : 'pointer', userSelect: 'none' }}
          >
            {status ? '● Activo' : '○ Inactivo'}
          </Tag>
        </Popconfirm>
      ),
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 120,
      render: (_, record: Lot) => (
        <Space>
          <Button
            type="text"
            icon={<EditOutlined />}
            onClick={() => navigate(`/lots/${record.id}/edit`)}
          />
          <Popconfirm
            title="¿Eliminar este lote?"
            description="Se marcará como inactivo. Si tiene stock activo, se bloqueará."
            onConfirm={() => handleDelete(record)}
            okText="Eliminar"
            cancelText="Cancelar"
            okButtonProps={{ danger: true }}
            disabled={record.stock > 0}
          >
            <Button type="text" danger icon={<DeleteOutlined />} />
          </Popconfirm>
        </Space>
      ),
    },
  ]

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
          Lotes
        </h1>
        <Space>
          <Button icon={<ReloadOutlined />} onClick={refetch}>
            Recargar
          </Button>
          <Button type="primary" onClick={() => navigate('/lots/new')}>
            + Nuevo Lote
          </Button>
        </Space>
      </div>

      <ErrorAlert message={error?.message ?? null} />

      <div style={{ marginBottom: 16 }}>
        <Input.Search
          placeholder="Buscar por código..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onSearch={(v) => setSearch(v)}
          allowClear
          style={{ maxWidth: 320 }}
        />
      </div>

      <Table<Lot>
        rowKey="id"
        columns={columns}
        dataSource={lots}
        loading={isLoading}
        pagination={{
          current: page,
          total: meta?.total ?? 0,
          pageSize: meta?.per_page ?? 15,
          onChange: setPage,
          showSizeChanger: false,
        }}
        locale={{ emptyText: 'No hay lotes registrados' }}
      />
    </>
  )
}
