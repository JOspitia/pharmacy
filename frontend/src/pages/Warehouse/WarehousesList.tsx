/**
 * WarehousesList — Tabla de almacenes con paginación, búsqueda, toggle status y delete.
 * Se renderiza dentro de AppLayout
 */

import { useMemo, useState } from 'react'
import { Button, Input, Space, Table, Tag, Tooltip, Popconfirm, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { EditOutlined, DeleteOutlined, ReloadOutlined, ExperimentOutlined, AlertOutlined } from '@ant-design/icons'
import { useNavigate } from 'react-router-dom'
import { useApi } from '../../hooks/useApi'
import { apiFetch } from '../../lib/api'
import { ErrorAlert } from '../../components/ErrorAlert'
import { LoadingScreen } from '../../components/LoadingScreen'
import { SensorsModal } from '../../components/SensorsModal'
import { AlertsModal, type AlertListResponse } from '../../components/AlertsModal'
import type { Warehouse, WarehouseListResponse } from '../../lib/warehouses'

/**
 * Componente de lista de almacenes con paginación, búsqueda y acciones.
 * Se renderiza dentro de AppLayout.
 * @returns {JSX.Element}
 */
export function WarehousesList() {
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [sensorsModal, setSensorsModal] = useState<{
    open: boolean
    warehouseId: string
    warehouseName: string
  }>({ open: false, warehouseId: '', warehouseName: '' })
  const [alertsModal, setAlertsModal] = useState<{
    open: boolean
    warehouseId: string
    warehouseName: string
  }>({ open: false, warehouseId: '', warehouseName: '' })

  /**
   * Efecto para debouncing de la búsqueda.
   * @param search - Término de búsqueda
   */
  useMemo(() => {
    const t = setTimeout(() => setDebouncedSearch(search), 300)
    return () => clearTimeout(t)
  }, [search])

  /**
   * Construye la consulta para obtener las bodegas.
   * @param page - Página actual
   * @param debouncedSearch - Término de búsqueda debounced
   * @returns {string} - Consulta para obtener las bodegas
   */
  const query = `/api/bodegas?page=${page}${debouncedSearch ? `&name=${encodeURIComponent(debouncedSearch)}` : ''}`
  const { data, error, isLoading, refetch } = useApi<WarehouseListResponse>(query, [page, debouncedSearch])

  /**
   * Maneja el cambio de estado de la bodega.
   * @param bodega - Bodega a cambiar de estado
   */
  const handleToggleStatus = async (bodega: Warehouse) => {
    try {
      await apiFetch(`/api/bodegas/${bodega.id}/toggle-status`, { method: 'PATCH' })
      message.success(`Bodega ${bodega.status ? 'inactivada' : 'activada'}`)
      refetch()
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'No se pudo cambiar el estado'
      message.error(msg)
    }
  }

  /**
   * Maneja la eliminación de la bodega.
   * @param bodega - Bodega a eliminar
   */
  const handleDelete = async (bodega: Warehouse) => {
    try {
      await apiFetch(`/api/bodegas/${bodega.id}`, { method: 'DELETE' })
      message.success(`Bodega "${bodega.name}" eliminada`)
      refetch()
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'No se pudo eliminar la bodega'
      message.error(msg)
    }
  }

  if (isLoading && !data) return <LoadingScreen />

  const warehouses = data?.data ?? []
  const meta = data?.meta

  /**
   * Columnas de la tabla.
   * @returns {ColumnsType<Warehouse>} - Columnas de la tabla
   */
  const columns: ColumnsType<Warehouse> = [
    {
      title: 'Nombre',
      dataIndex: 'name',
      key: 'name',
      render: (name: string) => (
        <strong style={{ fontFamily: "'Space Grotesk', sans-serif" }}>{name}</strong>
      ),
    },
    {
      title: 'Descripción',
      dataIndex: 'description',
      key: 'description',
      render: (desc: string | null) =>
        desc ?? <span style={{ color: '#94a3b8' }}>—</span>,
    },
    {
      title: 'Fecha Registro',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 180,
      render: (date: string) =>
        new Date(date).toLocaleDateString('es-CO', {
          year: 'numeric',
          month: '2-digit',
          day: '2-digit',
        }),
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      width: 120,
      render: (status: boolean, record: Warehouse) => (
        <Popconfirm
          title={status ? '¿Inactivar esta bodega?' : '¿Activar esta bodega?'}
          onConfirm={() => handleToggleStatus(record)}
          okText="Sí"
          cancelText="No"
        >
          <Tag
            color={status ? 'cyan' : 'default'}
            style={{ cursor: 'pointer', userSelect: 'none' }}
          >
            {status ? '● Activo' : '○ Inactivo'}
          </Tag>
        </Popconfirm>
      ),
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 260,
      render: (_: unknown, record: Warehouse) => (
        <Space>
          <AlertButton
            warehouseId={record.id}
            onClick={() =>
              setAlertsModal({
                open: true,
                warehouseId: record.id,
                warehouseName: record.name,
              })
            }
          />
          <Tooltip title="Ver sensores">
            <Button
              type="text"
              icon={<ExperimentOutlined />}
              onClick={() =>
                setSensorsModal({
                  open: true,
                  warehouseId: record.id,
                  warehouseName: record.name,
                })
              }
            />
          </Tooltip>

          <Button
            type="text"
            icon={<EditOutlined />}
            onClick={() => navigate(`/bodegas/${record.id}/edit`)}
          />
          <Popconfirm
            title="¿Eliminar esta bodega?"
            description="Se marcará como inactivo. Si tiene productos activos, se bloqueará."
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
   * Renderiza la lista de bodegas.
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
          Bodegas
        </h1>
        <Space>
          <Button icon={<ReloadOutlined />} onClick={refetch}>
            Recargar
          </Button>
          <Button type="primary" onClick={() => navigate('/bodegas/new')}>
            + Nueva Bodega
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

      <Table<Warehouse>
        rowKey="id"
        columns={columns}
        dataSource={warehouses}
        loading={isLoading}
        pagination={{
          current: page,
          total: meta?.total ?? 0,
          pageSize: meta?.per_page ?? 15,
          onChange: setPage,
          showSizeChanger: false,
        }}
        locale={{ emptyText: 'No hay bodegas registradas' }}
      />

      <SensorsModal
        open={sensorsModal.open}
        onClose={() => setSensorsModal({ open: false, warehouseId: '', warehouseName: '' })}
        warehouseId={sensorsModal.warehouseId}
        warehouseName={sensorsModal.warehouseName}
      />

      <AlertsModal
        open={alertsModal.open}
        onClose={() => setAlertsModal({ open: false, warehouseId: '', warehouseName: '' })}
        warehouseId={alertsModal.warehouseId}
        warehouseName={alertsModal.warehouseName}
      />
    </>
  )
}

/**
 * Componente que muestra un botón indicando las alertas activas en la bodega
 * @param warehouseId - ID de la bodega
 * @param onClick - Función que se ejecuta al hacer clic en el botón
 * @returns {JSX.Element}
 */
function AlertButton({ warehouseId, onClick }: { warehouseId: string; onClick: () => void }) {
  const { data } = useApi<AlertListResponse>(
    `/api/bodegas/${warehouseId}/alerts`,
    [warehouseId],
  )
  const hasAlerts = (data?.data?.length ?? 0) > 0
  const count = data?.meta?.total ?? data?.data?.length ?? 0

  return (
    <Tooltip title={hasAlerts ? `${count} alerta${count !== 1 ? 's' : ''} activa${count !== 1 ? 's' : ''}` : 'Sin alertas'}>
      <Button
        type="text"
        icon={
          <AlertOutlined
            style={{
              color: hasAlerts ? '#e63535' : '#94a3b8',
              fontSize: 16,
            }}
          />
        }
        onClick={onClick}
      />
    </Tooltip>
  )
}
