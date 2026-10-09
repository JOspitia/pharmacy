/**
 * AlertsModal — Modal de alertas de sensores de una bodega específica.
 */
import { useEffect, useState } from 'react'
import { Modal, Table, Tag } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useApi } from '../hooks/useApi'

/**
 * @description Interfaz que representa una alerta devuelta por el backend.
 */
export interface Alert {
  id: string
  type: string
  message: string
  created_at: string | null
  reading: {
    id: string
    temperature: number
    humidity: number
    recorded_at: string | null
    sensor: {
      id: string
      name: string
      warehouse: { id: string; name: string } | null
    } | null
  } | null
}

/**
 * @description Interfaz que representa una lista de alertas devuelta por el backend.
 */
export interface AlertListResponse {
  data: Alert[]
  meta?: {
    current_page: number
    total: number
    per_page: number
    last_page: number
  }
}

/**
 * Props para el componente AlertsModal
 */
interface AlertsModalProps {
  open: boolean
  onClose: () => void
  warehouseId: string
  warehouseName: string
}

/**
 * Formatea una fecha ISO en formato local colombiano.
 */
function formatDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('es-CO', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/**
 * Devuelve el color del Tag según el tipo de alerta.
 */
function alertColor(type: string): string {
  if (type === 'temperature') return 'red'
  if (type === 'humidity') return 'orange'
  return 'volcano'
}

/**
 * Devuelve la etiqueta amigable del tipo de alerta.
 */
function alertLabel(type: string): string {
  if (type === 'temperature') return 'Temperatura'
  if (type === 'humidity') return 'Humedad'
  return type
}

/**
 * Componente de modal de mostrar alertas de una bodega específica.
 * @param open - Boolean que indica si el modal está abierto
 * @param onClose - Función que se ejecuta cuando se cierra el modal
 * @param warehouseId - ID de la bodega
 * @param warehouseName - Nombre de la bodega
 */
export function AlertsModal({ open, onClose, warehouseId, warehouseName }: AlertsModalProps) {
  const [page, setPage] = useState(1)

  // Resetear a página 1 cuando cambia la bodega o se abre el modal
  useEffect(() => {
    if (open) setPage(1)
  }, [open, warehouseId])

  /**
   * Hook para cargar alertas de la bodega
   */
  const { data, isLoading } = useApi<AlertListResponse>(
    open ? `/api/bodegas/${warehouseId}/alerts?page=${page}` : null,
    [warehouseId, open, page],
  )

  const alerts = data?.data ?? []
  const meta = data?.meta

  /**
   * Columnas de la tabla de alertas
   */
  const columns: ColumnsType<Alert> = [
    {
      title: 'Tipo',
      dataIndex: 'type',
      key: 'type',
      width: 120,
      render: (type: string) => (
        <Tag color={alertColor(type)}>{alertLabel(type)}</Tag>
      ),
    },
    {
      title: 'Mensaje',
      dataIndex: 'message',
      key: 'message',
      render: (msg: string) => (
        <span style={{ fontFamily: "'Space Grotesk', sans-serif" }}>{msg}</span>
      ),
    },
    {
      title: 'Sensor',
      key: 'sensor',
      width: 160,
      render: (_: unknown, record: Alert) =>
        record.reading?.sensor?.name ?? <span style={{ color: '#94a3b8' }}>—</span>,
    },
    {
      title: 'Temperatura',
      key: 'temperature',
      width: 120,
      render: (_: unknown, record: Alert) =>
        record.reading != null ? `${record.reading.temperature} °C` : '—',
    },
    {
      title: 'Humedad',
      key: 'humidity',
      width: 110,
      render: (_: unknown, record: Alert) =>
        record.reading != null ? `${record.reading.humidity} %` : '—',
    },
    {
      title: 'Fecha lectura',
      key: 'recorded_at',
      width: 160,
      render: (_: unknown, record: Alert) =>
        formatDate(record.reading?.recorded_at ?? null),
    }
  ]

  /**
   * Renderizado del modal
   */
  return (
    <Modal
      open={open}
      onCancel={onClose}
      footer={null}
      width={1000}
      title={
        <span
          style={{
            fontFamily: "'Space Grotesk', sans-serif",
            display: 'flex',
            alignItems: 'center',
            gap: 8,
          }}
        >
          Alertas de {warehouseName}
        </span>
      }
    >
      <Table<Alert>
        rowKey="id"
        columns={columns}
        dataSource={alerts}
        loading={isLoading}
        pagination={
          meta
            ? {
              current: meta.current_page,
              total: meta.total,
              pageSize: meta.per_page,
              showSizeChanger: false,
              onChange: (p: number) => setPage(p),
            }
            : false
        }
        locale={{ emptyText: 'No hay alertas registradas para esta bodega' }}
        size="small"
      />
    </Modal>
  )
}
