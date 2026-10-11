/**
 * TransfersList — Lista de traslados entre bodegas (HU-09).
 *
 * Muestra todos los traslados con filtros por estado, bodega origen,
 * bodega destino y rango de fechas. Los acciones disponibles dependen
 * del estado del traslado y de los permisos del usuario:
 *
 *   DRAFT/REQUESTED -> boton Cancelar
 *   APPROVED        -> boton Despachar (regente)
 *   IN_TRANSIT      -> boton Recibir
 *
 * Patron: mismo diseno que InventoriesList y KardexList (Tabla + filtros).
 */
import { useState } from 'react'
import {
  Button, Space, Table, Tag, Select, DatePicker, Tooltip, Popconfirm, message,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import {
  PlusOutlined, ReloadOutlined, SwapOutlined, StopOutlined, WarningOutlined,
} from '@ant-design/icons'
import { useApi } from '../../hooks/useApi'
import { apiFetch } from '../../lib/api'
import { ErrorAlert } from '../../components/ErrorAlert'
import { LoadingScreen } from '../../components/LoadingScreen'
import {
  buildTransferQuery,
  statusBadgeColor,
  TRANSFER_STATUSES,
  type Transfer,
  type TransferFilters,
  type TransferListResponse,
} from '../../lib/transfers'
import type { Warehouse } from '../../lib/warehouses'
import type { Dayjs } from 'dayjs'
import { TransferFormModal } from './TransferFormModal'
import { TransferDetailModal } from './TransferDetailModal'
import { ApproveTransferModal } from './ApproveTransferModal'
import { ReceiveTransferModal } from './ReceiveTransferModal'

const { RangePicker } = DatePicker

export function TransfersList() {
  // Filtros
  const [statusName, setStatusName] = useState<string | undefined>()
  const [fromWarehouseId, setFromWarehouseId] = useState<string | undefined>()
  const [toWarehouseId, setToWarehouseId] = useState<string | undefined>()
  const [dateRange, setDateRange] = useState<[Dayjs, Dayjs] | null>(null)
  const [page, setPage] = useState(1)

  // Modales
  const [createOpen, setCreateOpen] = useState(false)
  const [detailTransfer, setDetailTransfer] = useState<Transfer | null>(null)
  const [approveTransfer, setApproveTransfer] = useState<Transfer | null>(null)
  const [receiveTransfer, setReceiveTransfer] = useState<Transfer | null>(null)

  // Filtros para la query
  const filters: TransferFilters = {
    status_id: statusName,
    from_warehouse_id: fromWarehouseId,
    to_warehouse_id: toWarehouseId,
    date_from: dateRange?.[0]?.format('YYYY-MM-DD'),
    date_to: dateRange?.[1]?.format('YYYY-MM-DD'),
    page,
    per_page: 20,
  }
  const query = buildTransferQuery(filters)

  const { data, error, isLoading, refetch } = useApi<TransferListResponse>(
    query,
    [statusName, fromWarehouseId, toWarehouseId, dateRange?.[0]?.toISOString(), dateRange?.[1]?.toISOString(), page],
  )

  // Bodegas para los filtros (reutilizamos el endpoint existente)
  const { data: warehousesData } = useApi<{ data: Warehouse[] }>(
    '/api/bodegas?per_page=200',
    [],
  )
  const warehouses = warehousesData?.data ?? []

  const transfers = data?.data ?? []
  const meta = data?.meta

  // -------------------------------------------------------------------------
  // Acciones
  // -------------------------------------------------------------------------

  const handleDispatch = async (transfer: Transfer) => {
    try {
      await apiFetch(`/api/transfers/${transfer.id}/dispatch`, { method: 'PATCH' })
      message.success(`Traslado ${transfer.transfer_number} marcado como En transito`)
      void refetch()
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'No se pudo despachar el traslado'
      message.error(msg)
    }
  }

  const handleCancel = async (transfer: Transfer) => {
    try {
      await apiFetch(`/api/transfers/${transfer.id}/cancel`, { method: 'PATCH' })
      message.success(`Traslado ${transfer.transfer_number} anulado`)
      void refetch()
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'No se pudo anular el traslado'
      message.error(msg)
    }
  }

  // -------------------------------------------------------------------------
  // Columnas de la tabla
  // -------------------------------------------------------------------------
  const columns: ColumnsType<Transfer> = [
    {
      title: 'Número',
      dataIndex: 'transfer_number',
      key: 'transfer_number',
      width: 180,
      render: (num: string) => (
        <strong style={{ fontFamily: "'Space Grotesk', sans-serif" }}>{num}</strong>
      ),
    },
    {
      title: 'Origen',
      key: 'from_warehouse',
      width: 150,
      render: (_, record) => record.from_warehouse?.name ?? record.from_warehouse_name ?? '—',
    },
    {
      title: 'Destino',
      key: 'to_warehouse',
      width: 150,
      render: (_, record) => record.to_warehouse?.name ?? record.to_warehouse_name ?? '—',
    },
    {
      title: 'Estado',
      key: 'status',
      width: 200,
      render: (_, record) => {
        // Discrepancias que siguen esperando decision. El backend solo
        // carga las PENDING en el listado, pero el filtro explicito evita
        // que un cambio futuro ahi se convierta en un badge mentiroso.
        const pendientes = (record.discrepancies ?? []).filter(
          (d) => d.status === 'PENDING',
        ).length

        return (
          <Space size={4} wrap>
            <Tag color={statusBadgeColor(record.status_name)} style={{ fontWeight: 600 }}>
              {record.status_label}
            </Tag>
            {pendientes > 0 && (
              <Tooltip title={`${pendientes} discrepancia(s) esperando corrección`}>
                <Tag color="volcano" style={{ fontWeight: 600 }}>
                  {pendientes} pendiente{pendientes > 1 ? 's' : ''}
                </Tag>
              </Tooltip>
            )}
          </Space>
        )
      },
    },
    {
      title: 'Solicitado por',
      key: 'requested_by',
      width: 150,
      render: (_, record) => record.requested_by_name ?? '—',
    },
    {
      title: 'Ítems',
      key: 'items',
      width: 80,
      align: 'center',
      render: (_, record) => record.items?.length ?? 0,
    },
    {
      title: 'Fecha',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 160,
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
      // Con "Corregir" al lado de "Ver" / "Aprobar" / "Anular" la columna
      // necesita mas ancho; los botones no se recortan.
      width: 290,
      render: (_, record) => {
        const isCancelled = record.status_name === 'CANCELLED'
        const isTerminal = record.is_terminal
        const isCancellable = record.is_cancellable
        const isDraftOrRequested =
          record.status_name === 'DRAFT' || record.status_name === 'REQUESTED'
        const isApproved = record.status_name === 'APPROVED'
        const isInTransit = record.status_name === 'IN_TRANSIT'

        // Discrepancias esperando correccion. Solo tiene sentido mostrarlas
        // cuando el traslado no esta cancelado ni en estado terminal: en
        // cualquiera de esos dos casos no hay nada que corregir y el boton
        // seria ruido.
        const pendientes = (record.discrepancies ?? []).filter(
          (d) => d.status === 'PENDING',
        ).length
        const muestraCorreccion = pendientes > 0 && !isCancelled && !isTerminal

        if (isCancelled || isTerminal) {
          return (
            <Button size="small" onClick={() => setDetailTransfer(record)}>
              Ver
            </Button>
          )
        }

        return (
          <Space size={4} onClick={(e) => e.stopPropagation()}>
            <Button size="small" onClick={() => setDetailTransfer(record)}>
              Ver
            </Button>
            {isDraftOrRequested && (
              <Button
                size="small"
                type="primary"
                icon={<SwapOutlined />}
                onClick={() => setApproveTransfer(record)}
              >
                Aprobar
              </Button>
            )}
            {isApproved && (
              <Popconfirm
                title="Despachar traslado"
                description={`Despachar ${record.transfer_number}? Se descontara stock de la bodega origen.`}
                onConfirm={() => handleDispatch(record)}
                okText="Despachar"
                cancelText="No"
              >
                <Button size="small" type="primary" icon={<SwapOutlined />}>
                  Despachar
                </Button>
              </Popconfirm>
            )}
            {isInTransit && (
              <Button
                size="small"
                type="primary"
                color="cyan"
                icon={<SwapOutlined />}
                onClick={() => setReceiveTransfer(record)}
              >
                Recibir
              </Button>
            )}
            {muestraCorreccion && (
              <Button
                size="small"
                danger
                icon={<WarningOutlined />}
                onClick={() => setDetailTransfer(record)}
              >
                Corregir
              </Button>
            )}
            {isCancellable && (
              <Popconfirm
                title="Anular traslado"
                description={`Anular ${record.transfer_number}?`}
                onConfirm={() => handleCancel(record)}
                okText="Anular"
                cancelText="No"
                okButtonProps={{ danger: true }}
              >
                <Button size="small" danger icon={<StopOutlined />}>
                  Anular
                </Button>
              </Popconfirm>
            )}
          </Space>
        )
      },
    },
  ]

  if (isLoading && !data) return <LoadingScreen />

  return (
    <>
      {/* Header */}
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
          Traslados
        </h1>
        <Space>
          <Button icon={<ReloadOutlined />} onClick={refetch}>
            Recargar
          </Button>
          <Button
            type="primary"
            icon={<PlusOutlined />}
            onClick={() => setCreateOpen(true)}
          >
            Nuevo traslado
          </Button>
        </Space>
      </div>

      <ErrorAlert message={error?.message ?? null} />

      {/* Filtros */}
      <div
        style={{
          display: 'flex',
          gap: 12,
          marginBottom: 16,
          alignItems: 'center',
          flexWrap: 'wrap',
        }}
      >
        <Select
          allowClear
          placeholder="Estado"
          style={{ minWidth: 160 }}
          value={statusName}
          onChange={(v) => setStatusName(v)}
          options={TRANSFER_STATUSES}
        />
        <Select
          allowClear
          placeholder="Bodega origen"
          style={{ minWidth: 180 }}
          value={fromWarehouseId}
          onChange={(v) => setFromWarehouseId(v)}
          options={warehouses.map((w) => ({ value: w.id, label: w.name }))}
        />
        <Select
          allowClear
          placeholder="Bodega destino"
          style={{ minWidth: 180 }}
          value={toWarehouseId}
          onChange={(v) => setToWarehouseId(v)}
          options={warehouses.map((w) => ({ value: w.id, label: w.name }))}
        />
        <RangePicker
          onChange={(v) => setDateRange(v as [Dayjs, Dayjs] | null)}
          placeholder={['Desde', 'Hasta']}
          format="YYYY-MM-DD"
          allowClear
        />
        <Tooltip title="Limpiar filtros">
          <Button
            onClick={() => {
              setStatusName(undefined)
              setFromWarehouseId(undefined)
              setToWarehouseId(undefined)
              setDateRange(null)
            }}
          >
            Limpiar
          </Button>
        </Tooltip>
      </div>

      <Table<Transfer>
        rowKey="id"
        columns={columns}
        dataSource={transfers}
        loading={isLoading}
        pagination={{
          current: page,
          total: meta?.total ?? 0,
          pageSize: meta?.per_page ?? 20,
          onChange: setPage,
          showSizeChanger: false,
        }}
        locale={{ emptyText: 'No hay traslados registrados' }}
        onRow={(record) => ({
          style: { cursor: 'pointer' },
          onClick: () => setDetailTransfer(record),
        })}
      />

      {/* Modales */}
      <TransferFormModal
        open={createOpen}
        onClose={() => setCreateOpen(false)}
        onCreated={(transfer) => {
          setCreateOpen(false)
          message.success(`Traslado ${transfer.transfer_number} creado`)
          void refetch()
        }}
      />

      <TransferDetailModal
        transfer={detailTransfer}
        onClose={() => setDetailTransfer(null)}
        onApprove={(transfer) => {
          setDetailTransfer(null)
          setApproveTransfer(transfer)
        }}
        onDispatch={(transfer) => {
          setDetailTransfer(null)
          void handleDispatch(transfer)
        }}
        onReceive={(transfer) => {
          setDetailTransfer(null)
          setReceiveTransfer(transfer)
        }}
      />

      <ApproveTransferModal
        transfer={approveTransfer}
        onClose={() => setApproveTransfer(null)}
        onDispatched={() => {
          setApproveTransfer(null)
          void refetch()
        }}
      />

      <ReceiveTransferModal
        transfer={receiveTransfer}
        onClose={() => setReceiveTransfer(null)}
        onReceived={() => {
          setReceiveTransfer(null)
          void refetch()
        }}
      />
    </>
  )
}
