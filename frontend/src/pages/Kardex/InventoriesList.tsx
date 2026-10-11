/**
 * InventoriesList - Tabla de existencias del Kardex (HU-08).
 *
 * Muestra el stock por (bodega, producto, lote) con alertas arriba:
 * lotes vencidos, por vencer y bajo minimo. El boton "Registrar movimiento"
 * abre el modal para ajustar stock.
 *
 * Patron: mismo diseno que LotsList y WarehousesList (Tabla + filtros + alertas).
 */
import { useState } from 'react'
import { Button, Space, Table, Tag, Tooltip, Select, DatePicker, message, Card, Statistic, List, Empty } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import {
  PlusOutlined,
  ReloadOutlined,
  WarningOutlined,
  ClockCircleOutlined,
  ArrowDownOutlined,
} from '@ant-design/icons'
import { useApi } from '../../hooks/useApi'
import { ErrorAlert } from '../../components/ErrorAlert'
import { LoadingScreen } from '../../components/LoadingScreen'
import {
  buildInventoryQuery,
  type Inventory,
  type InventoryFilters,
  type InventoryListResponse,
} from '../../lib/inventories'
import type { Warehouse } from '../../lib/warehouses'
import { MovementFormModal } from './MovementFormModal'
import { type Dayjs } from 'dayjs'

const { RangePicker } = DatePicker

/**
 * Color del Tag segun el estado de vencimiento.
 * - vencido: rojo
 * - critico (1-30d): naranja
 * - proximo (31-90d): amarillo
 * - ok: verde
 */
function statusColor(status: Inventory['expiration_status']): string {
  switch (status) {
    case 'vencido':
      return 'red'
    case 'critico':
      return 'orange'
    case 'proximo':
      return 'gold'
    case 'ok':
      return 'green'
    default:
      return 'default'
  }
}

/**
 * Tabla de inventarios con paginacion propia.
 *
 * Se reinicializa automaticamente cuando cambia la key (los filtros del padre),
 * reseteando page a 1 sin necesidad de setState en un effect. Este patron
 * (key-driven remount) es el que la documentacion de React recomienda para
 * resetear estado cuando cambian "identity" de los datos.
 */
function InventoryTable({
  inventories,
  meta,
  isLoading,
  onPageChange,
}: {
  inventories: Inventory[]
  meta?: { total: number; per_page: number }
  isLoading: boolean
  onPageChange: (page: number) => void
}) {
  const [page, setPage] = useState(1)

  /**
   * Columnas de la tabla de existencias.
   */
  const columns: ColumnsType<Inventory> = [
    {
      title: 'Lote',
      dataIndex: ['lot', 'name'],
      key: 'lot_name',
      width: 160,
      render: (_, record) => (
        <strong style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
          {record.lot?.name ?? '—'}
        </strong>
      ),
    },
    {
      title: 'Producto',
      key: 'product',
      width: 220,
      render: (_, record) => (
        <div>
          <div>{record.product?.name ?? '—'}</div>
          {record.product?.cum && (
            <div style={{ fontSize: 11, color: '#94a3b8' }}>CUM {record.product.cum}</div>
          )}
        </div>
      ),
    },
    {
      title: 'Bodega',
      key: 'warehouse',
      width: 160,
      render: (_, record) => record.bodega?.name ?? '—',
    },
    {
      title: 'Stock',
      dataIndex: 'quantity',
      key: 'quantity',
      width: 90,
      align: 'right',
      render: (qty: number) => (
        <Tag color={qty > 0 ? 'cyan' : 'default'} style={{ fontWeight: 600 }}>
          {qty}
        </Tag>
      ),
    },
    {
      title: 'Min',
      dataIndex: 'min_stock',
      key: 'min_stock',
      width: 70,
      align: 'right',
      render: (min: number | null) =>
        min ?? <span style={{ color: '#94a3b8' }}>—</span>,
    },
    {
      title: 'Vencimiento',
      key: 'expiration',
      width: 200,
      render: (_, record) => {
        if (!record.expiration_date) return '—'
        const date = new Date(record.expiration_date).toLocaleDateString('es-CO', {
          year: 'numeric',
          month: '2-digit',
          day: '2-digit',
        })
        const days = record.days_until_expiration
        const suffix =
          days === null
            ? ''
            : days < 0
              ? ` (hace ${Math.abs(days)}d)`
              : days === 0
                ? ' (hoy)'
                : ` (${days}d)`
        return (
          <Space size={4}>
            <span>{date}</span>
            <Tag color={statusColor(record.expiration_status)} style={{ margin: 0 }}>
              {days === null ? '—' : days < 0 ? `vencido${suffix}` : `${days}d`}
            </Tag>
          </Space>
        )
      },
    },
  ]

  return (
    <Table<Inventory>
      rowKey="id"
      columns={columns}
      dataSource={inventories}
      loading={isLoading}
      pagination={{
        current: page,
        total: meta?.total ?? 0,
        pageSize: meta?.per_page ?? 15,
        onChange: (p) => {
          setPage(p)
          onPageChange(p)
        },
        showSizeChanger: false,
      }}
      locale={{ emptyText: 'No hay existencias registradas' }}
    />
  )
}

/**
 * Componente principal de la pantalla de Inventario.
 */
export function InventoriesList() {
  // Filtros del query
  const [warehouseId, setWarehouseId] = useState<string | undefined>()
  const [productId, setProductId] = useState<string | undefined>()
  const [expiringRange, setExpiringRange] = useState<[Dayjs, Dayjs] | null>(null)

  // Modal de registrar movimiento
  const [movementModalOpen, setMovementModalOpen] = useState(false)

  // Construir query con filtros
  const filters: InventoryFilters = {
    warehouse_id: warehouseId,
    product_id: productId,
    expiring_within: expiringRange
      ? Math.max(1, expiringRange[1].diff(expiringRange[0], 'day'))
      : undefined,
    page: 1,
    per_page: 15,
  }
  const query = buildInventoryQuery(filters)

  // Fetch con react-actualizacion por cambios de filtros.
  // El dependency array no incluye `page` del sub-componente porque el
  // sub-componente se reinicia solo cuando cambia la key (filtros).
  const { data, error, isLoading, refetch } = useApi<InventoryListResponse>(
    query,
    [warehouseId, productId, expiringRange?.[0]?.toISOString(), expiringRange?.[1]?.toISOString()],
  )

  // Bodegas para el filtro (reutilizamos el endpoint existente que ya devuelve
  // bodegas paginadas; el selector no necesita paginacion, asi que tomamos 100)
  const { data: warehousesData } = useApi<{ data: Warehouse[] }>(
    '/api/bodegas?per_page=100',
    [],
  )

  const inventories = data?.data ?? []
  const meta = data?.meta
  const alerts = data?.alerts

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
          Inventario
        </h1>
        <Space>
          <Button icon={<ReloadOutlined />} onClick={refetch}>
            Recargar
          </Button>
          <Button
            type="primary"
            icon={<PlusOutlined />}
            onClick={() => setMovementModalOpen(true)}
          >
            Registrar movimiento
          </Button>
        </Space>
      </div>

      <ErrorAlert message={error?.message ?? null} />

      {/* Panel de alertas: tres columnas (vencidos, por vencer, bajo stock) */}
      {alerts && (
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(3, 1fr)',
            gap: 12,
            marginBottom: 24,
          }}
        >
          <AlertsCard
            title="Lotes vencidos"
            icon={<WarningOutlined />}
            tagColor="red"
            emptyText="Ninguna existencia vencida"
            data={alerts.expired.map((a) => ({
              key: a.inventory_id,
              title: a.product_name ?? '—',
              sub: a.lot_name ?? '—',
              tag: `hace ${Math.abs(a.days_until_expiration ?? 0)}d`,
            }))}
          />
          <AlertsCard
            title="Proximos a vencer (90d)"
            icon={<ClockCircleOutlined />}
            tagColor="orange"
            emptyText="Ninguna existencia por vencer"
            data={alerts.expiring_soon.map((a) => ({
              key: a.inventory_id,
              title: a.product_name ?? '—',
              sub: a.lot_name ?? '—',
              tag: `${a.days_until_expiration ?? '?'}d`,
            }))}
          />
          <AlertsCard
            title="Bajo stock minimo"
            icon={<ArrowDownOutlined />}
            tagColor="cyan"
            emptyText="Ninguna existencia bajo minimo"
            data={alerts.low_stock.map((a) => ({
              key: a.inventory_id,
              title: a.product_name ?? '—',
              sub: a.lot_name ?? '—',
              tag: `faltan ${Math.abs(a.deficit ?? 0)}`,
            }))}
          />
        </div>
      )}

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
          placeholder="Filtrar por bodega"
          style={{ minWidth: 220 }}
          value={warehouseId}
          onChange={(v) => setWarehouseId(v)}
          options={(warehousesData?.data ?? []).map((w) => ({
            value: w.id,
            label: w.name,
          }))}
        />
        <RangePicker
          onChange={(v) => setExpiringRange(v as [Dayjs, Dayjs] | null)}
          placeholder={['Vence desde', 'Vence hasta']}
          format="YYYY-MM-DD"
          allowClear
        />
        <Tooltip title="Limpiar filtros">
          <Button
            onClick={() => {
              setWarehouseId(undefined)
              setProductId(undefined)
              setExpiringRange(null)
            }}
          >
            Limpiar
          </Button>
        </Tooltip>
      </div>

      {/* La key fuerza un remount (y page=1) cuando cambian los filtros.
          Es el patron documentado en React para "resetting state" sin
          necesidad de setState dentro de un effect. */}
      <InventoryTable
        key={`inv-table-${warehouseId ?? 'all'}-${productId ?? 'all'}`}
        inventories={inventories}
        meta={meta}
        isLoading={isLoading}
        onPageChange={() => {}}
      />

      <MovementFormModal
        open={movementModalOpen}
        onClose={() => setMovementModalOpen(false)}
        onCreated={() => {
          setMovementModalOpen(false)
          message.success('Movimiento registrado')
          refetch()
        }}
        defaultWarehouseId={warehouseId}
      />
    </>
  )
}

/**
 * Card de alerta con lista resumida. Reutilizada por las 3 columnas
 * del panel (vencidos, por vencer, bajo stock).
 *
 * Patron Ant Design:
 *   - Card bordered como contenedor (el top border teal es el acento
 *     de marca y se aplica via style.headStyle).
 *   - Statistic arriba para el conteo, con el icono como `prefix`.
 *   - List con items de tamaño chico, cada uno con un Tag al final
 *     mostrando el delta (dias vencidos, dias restantes, deficit).
 *   - Empty cuando no hay items (estado limpio, en vez de texto gris).
 */
interface AlertItem {
  key: string
  title: string
  sub: string
  tag: string
}

/**
 * Color de marca de la app: el mismo teal que usan los h1 del resto
 * de las paginas (LotsList, BodegasList, etc.) y el sidebar de marca.
 * Lo usamos como acento del top border de los 3 cards de alertas
 * para que el panel se vea coherente con el resto de la UI.
 */
const BRAND_COLOR = '#0e7490'

function AlertsCard({
  title,
  icon,
  tagColor,
  data,
  emptyText,
}: {
  title: string
  icon: React.ReactNode
  /** Color del Tag de Ant Design: 'red' | 'orange' | 'cyan' | etc. */
  tagColor: string
  data: AlertItem[]
  emptyText: string
}) {
  // Mostramos hasta 4 items en el resumen; el resto se indica con "+N más".
  const visible = data.slice(0, 4)
  const overflow = data.length - visible.length

  return (
    <Card
      size="small"
      bordered
      // Acento de marca: top border teal de 3px. Lo aplicamos via
      // headStyle porque Ant Design no expone `borderTop` como prop.
      style={{ borderTop: `3px solid ${BRAND_COLOR}` }}
      styles={{
        body: { padding: 12 },
        header: { borderBottom: 'none', padding: '8px 12px 0' },
      }}
      title={
        <Statistic
          title={title}
          value={data.length}
          prefix={icon}
          valueStyle={{ fontSize: 18, color: BRAND_COLOR, fontWeight: 600 }}
        />
      }
    >
      {data.length === 0 ? (
        <Empty
          image={Empty.PRESENTED_IMAGE_SIMPLE}
          description={emptyText}
          styles={{ image: { height: 24 } }}
        />
      ) : (
        <List<AlertItem>
          size="small"
          split={false}
          dataSource={visible}
          renderItem={(item) => (
            <List.Item style={{ padding: '4px 0' }}>
              <div
                style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  width: '100%',
                  gap: 8,
                }}
              >
                <div style={{ minWidth: 0, flex: 1 }}>
                  <div
                    style={{
                      fontSize: 13,
                      fontWeight: 500,
                      overflow: 'hidden',
                      textOverflow: 'ellipsis',
                      whiteSpace: 'nowrap',
                    }}
                  >
                    {item.title}
                  </div>
                  <div style={{ fontSize: 11, color: '#94a3b8' }}>{item.sub}</div>
                </div>
                <Tag color={tagColor} style={{ margin: 0 }}>
                  {item.tag}
                </Tag>
              </div>
            </List.Item>
          )}
        />
      )}
      {overflow > 0 && (
        <div
          style={{
            color: '#94a3b8',
            fontSize: 11,
            marginTop: 4,
            textAlign: 'right',
          }}
        >
          +{overflow} más
        </div>
      )}
    </Card>
  )
}
