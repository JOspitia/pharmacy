/**
 * KardexList - Historial de movimientos del Kardex (HU-08).
 *
 * Pantalla de SOLO LECTURA. El backend rechaza PUT/PATCH/DELETE sobre
 * /api/movements/{id} con 405 (RN-06: Kardex inmutable), asi que este
 * componente nunca expone acciones de edicion.
 *
 * Patron: mismo diseno que LotsList pero sin acciones destructive.
 */
import { useState } from 'react'
import { Button, Space, Table, Tag, Select, DatePicker, Tooltip } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { ReloadOutlined, HistoryOutlined } from '@ant-design/icons'
import { useApi } from '../../hooks/useApi'
import { ErrorAlert } from '../../components/ErrorAlert'
import { LoadingScreen } from '../../components/LoadingScreen'
import {
  buildMovementQuery,
  type Movement,
  type MovementFilters,
  type MovementListResponse,
  MOVEMENT_TYPES,
} from '../../lib/movements'
import type { Dayjs } from 'dayjs'

const { RangePicker } = DatePicker

/**
 * Color del Tag segun el tipo de movimiento.
 * - ENTRY, TRANSFER_IN: verde (entradas)
 * - TRANSFER_OUT, DISPENSATION, WITHDRAWAL: rojo (salidas)
 * - ADJUSTMENT: amarillo (caso especial)
 */
function typeColor(type: Movement['type']): string {
  switch (type) {
    case 'ENTRY':
    case 'TRANSFER_IN':
      return 'green'
    case 'ADJUSTMENT':
      return 'gold'
    case 'TRANSFER_OUT':
    case 'DISPENSATION':
    case 'WITHDRAWAL':
      return 'red'
    default:
      return 'default'
  }
}

/**
 * Formatea un delta con su signo. Positivo = verde, negativo = rojo.
 */
function quantityColor(qty: number): string {
  if (qty > 0) return '#16a34a'
  if (qty < 0) return '#dc2626'
  return '#64748b'
}

export function KardexList() {
  // Filtros
  const [type, setType] = useState<Movement['type'] | undefined>()
  const [dateRange, setDateRange] = useState<[Dayjs, Dayjs] | null>(null)
  const [page, setPage] = useState(1)

  const filters: MovementFilters = {
    type,
    from_date: dateRange?.[0]?.format('YYYY-MM-DD'),
    to_date: dateRange?.[1]?.format('YYYY-MM-DD'),
    page,
    per_page: 20,
  }
  const query = buildMovementQuery(filters)

  const { data, error, isLoading, refetch } = useApi<MovementListResponse>(
    query,
    [type, dateRange?.[0]?.toISOString(), dateRange?.[1]?.toISOString(), page],
  )

  const movements = data?.data ?? []
  const meta = data?.meta

  /**
   * Columnas de la tabla de movimientos. Sin acciones: el Kardex es inmutable.
   */
  const columns: ColumnsType<Movement> = [
    {
      title: 'Fecha',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 170,
      render: (date: string) =>
        new Date(date).toLocaleString('es-CO', {
          year: 'numeric',
          month: '2-digit',
          day: '2-digit',
          hour: '2-digit',
          minute: '2-digit',
        }),
    },
    {
      title: 'Tipo',
      key: 'type',
      width: 180,
      render: (_, record) => (
        <Tooltip title={record.type_label}>
          <Tag color={typeColor(record.type)} style={{ fontWeight: 600 }}>
            {record.type}
          </Tag>
        </Tooltip>
      ),
    },
    {
      title: 'Bodega',
      key: 'warehouse',
      width: 150,
      render: (_, record) => record.inventory?.warehouse_name ?? '—',
    },
    {
      title: 'Producto',
      key: 'product',
      width: 200,
      render: (_, record) => record.inventory?.product_name ?? '—',
    },
    {
      title: 'Lote',
      key: 'lot',
      width: 130,
      render: (_, record) => record.inventory?.lot_name ?? '—',
    },
    {
      title: 'Cantidad',
      dataIndex: 'quantity',
      key: 'quantity',
      width: 110,
      align: 'right',
      render: (qty: number) => (
        <span
          style={{
            color: quantityColor(qty),
            fontWeight: 700,
            fontFamily: "'Space Grotesk', sans-serif",
          }}
        >
          {qty > 0 ? `+${qty}` : qty}
        </span>
      ),
    },
    {
      title: 'Stock post-mov.',
      key: 'stock_after',
      width: 130,
      align: 'right',
      render: (_, record) => record.inventory?.quantity ?? '—',
    },
    {
      title: 'Motivo',
      dataIndex: 'reason',
      key: 'reason',
      render: (reason: string | null) =>
        reason ?? <span style={{ color: '#94a3b8' }}>—</span>,
    },
    {
      title: 'Usuario',
      key: 'performed_by',
      width: 160,
      render: (_, record) => record.performed_by_name ?? '—',
    },
  ]

  if (isLoading && !data) return <LoadingScreen />

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
            display: 'flex',
            alignItems: 'center',
            gap: 12,
          }}
        >
          <HistoryOutlined />
          Kardex
        </h1>
        <Space>
          <Button icon={<ReloadOutlined />} onClick={refetch}>
            Recargar
          </Button>
        </Space>
      </div>

      <ErrorAlert message={error?.message ?? null} />

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
          placeholder="Filtrar por tipo"
          style={{ minWidth: 220 }}
          value={type}
          onChange={(v) => setType(v)}
          options={MOVEMENT_TYPES.map((t) => ({ value: t.value, label: t.value }))}
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
              setType(undefined)
              setDateRange(null)
            }}
          >
            Limpiar
          </Button>
        </Tooltip>
      </div>

      <Table<Movement>
        rowKey="id"
        columns={columns}
        dataSource={movements}
        loading={isLoading}
        pagination={{
          current: page,
          total: meta?.total ?? 0,
          pageSize: meta?.per_page ?? 20,
          onChange: setPage,
          showSizeChanger: false,
        }}
        locale={{ emptyText: 'No hay movimientos registrados' }}
      />

    </>
  )
}
