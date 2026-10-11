/**
 * TransferDetailModal - Detalle de un traslado (HU-09).
 *
 * Modal de solo lectura que muestra toda la informacion de un traslado:
 * numero, bodegas, estado, items con quantities, discrepancies, actores
 * y timestamps. Desde aqui se accede a las acciones segun el estado.
 */
import { useState } from 'react'
import { Button, Descriptions, Divider, Form, Input, Modal, Space, Table, Tag, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { SwapOutlined, CheckOutlined, StopOutlined } from '@ant-design/icons'
import { apiFetch } from '../../lib/api'
import { statusBadgeColor } from '../../lib/transfers'
import type {
  ResolveDiscrepancyAction,
  ResolveDiscrepancyPayload,
  Transfer,
  TransferItem,
  TransferDiscrepancy,
} from '../../lib/transfers'

function formatDate(date: string | null): string {
  if (!date) return '—'
  return new Date(date).toLocaleString('es-CO', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const itemColumns: ColumnsType<TransferItem> = [
  {
    title: 'Producto',
    key: 'product',
    render: (_, record) => record.product?.name ?? '—',
  },
  {
    title: 'Lote',
    key: 'lot',
    width: 140,
    render: (_, record) => record.lot?.name ?? '—',
  },
  {
    title: 'Solicitada',
    dataIndex: 'requested_quantity',
    key: 'requested_quantity',
    width: 100,
    align: 'center',
  },
  {
    title: 'No aprobada',
    key: 'not_approved',
    width: 110,
    align: 'center',
    // Unidades que se pidieron pero el regente no autorizo. No viajan:
    // quedan como stock en la bodega de origen. Se calcula sobre la
    // cantidad aprobada, asi que antes de aprobar (null) se muestra '—'.
    render: (_, record) =>
      record.approved_quantity !== null
        ? Math.max(record.requested_quantity - record.approved_quantity, 0)
        : '—',
  },
  {
    title: 'Aprobada',
    dataIndex: 'approved_quantity',
    key: 'approved_quantity',
    width: 100,
    align: 'center',
    render: (v: number | null) => (v !== null ? v : '—'),
  },
  {
    title: 'Recibida',
    dataIndex: 'delivered_quantity',
    key: 'delivered_quantity',
    width: 100,
    align: 'center',
    render: (v: number | null) => (v !== null ? v : '—'),
  },
  {
    title: 'Pendiente',
    key: 'pending',
    width: 90,
    align: 'center',
    render: (_, record) =>
      record.approved_quantity !== null && record.delivered_quantity !== null
        ? record.approved_quantity - record.delivered_quantity
        : '—',
  },
]

function buildDiscrepancyColumns(
  onResolve: (d: TransferDiscrepancy, action: ResolveDiscrepancyAction) => void,
): ColumnsType<TransferDiscrepancy> {
  return [
    {
      title: 'Aprobado',
      dataIndex: 'approved_quantity',
      key: 'approved_quantity',
      width: 90,
      align: 'center',
    },
    {
      title: 'Recibido',
      dataIndex: 'delivered_quantity',
      key: 'delivered_quantity',
      width: 90,
      align: 'center',
    },
    {
      title: 'Diferencia',
      dataIndex: 'difference',
      key: 'difference',
      width: 90,
      align: 'center',
      render: (d: number) => <Tag color="gold">-{d}</Tag>,
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      width: 120,
      render: (status: string) => {
        const color = status === 'PENDING' ? 'gold' : status === 'WRITTEN_OFF' ? 'red' : 'green'
        const label = status === 'WRITTEN_OFF' ? 'Baja' : status === 'RESOLVED' ? 'Resuelta' : 'Pendiente'
        return <Tag color={color}>{label}</Tag>
      },
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 200,
      render: (_, record) =>
        record.status === 'PENDING' ? (
          <Space size={4}>
            <Button
              size="small"
              icon={<CheckOutlined />}
              onClick={() => onResolve(record, 'resolve')}
            >
              Cerrar sin ajuste
            </Button>
            <Button
              size="small"
              danger
              icon={<StopOutlined />}
              onClick={() => onResolve(record, 'write_off')}
            >
              Dar de baja
            </Button>
          </Space>
        ) : (
          <span style={{ color: '#94a3b8', fontSize: 12 }}>
            {record.resolution_notes ?? '—'}
          </span>
        ),
    },
  ]
}

export function TransferDetailModal({
  transfer,
  onClose,
  onApprove,
  onDispatch,
  onReceive,
}: {
  transfer: Transfer | null
  onClose: () => void
  /** Abre el modal para ajustar quantities aprobadas (DRAFT/REQUESTED). */
  onApprove: (transfer: Transfer) => void
  /** Ejecuta dispatch directamente (APPROVED -> IN_TRANSIT). */
  onDispatch: (transfer: Transfer) => void
  onReceive: (transfer: Transfer) => void
}) {
  const [resolving, setResolving] = useState<{
    discrepancia: TransferDiscrepancy
    action: ResolveDiscrepancyAction
  } | null>(null)

  if (!transfer) return null

  const isDraftOrRequested =
    transfer.status_name === 'DRAFT' || transfer.status_name === 'REQUESTED'
  const isApproved = transfer.status_name === 'APPROVED'
  const isInTransit = transfer.status_name === 'IN_TRANSIT'
  // Recepcion tardia: el traslado quedo con faltantes y hay una
  // discrepancia PENDING. El boton deja reabrir el modal de recibir
  // para sumar lo que llego despues.
  const isPartiallyReceived =
    transfer.status_name === 'PARTIALLY_RECEIVED' ||
    (transfer.discrepancies ?? []).some((d) => d.status === 'PENDING')
  const hasDiscrepancies = (transfer.discrepancies?.length ?? 0) > 0

  /**
   * Resolver discrepancia: cierra el registro y, si era la ultima
   * pendiente del traslado, el backend lo cierra como RECEIVED.
   * No mueve Kardex (RN-06). La correccion de inventario, si hace
   * falta, va por ajuste.
   */
  const handleResolver = async (
    discrepancia: TransferDiscrepancy,
    action: ResolveDiscrepancyAction,
    notes: string | null,
  ): Promise<void> => {
    try {
      const payload: ResolveDiscrepancyPayload = { action, notes: notes ?? undefined }
      await apiFetch(
        `/api/transfers/${transfer.id}/discrepancies/${discrepancia.id}/resolve`,
        { method: 'PATCH', body: payload },
      )
      message.success(
        action === 'resolve'
          ? 'Discrepancia cerrada sin ajuste de stock.'
          : 'Discrepancia dada de baja.',
      )
      onClose()
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'No se pudo cerrar la discrepancia'
      message.error(msg)
    }
  }

  return (
    <Modal
      open={Boolean(transfer)}
      onCancel={onClose}
      title={`Traslado ${transfer.transfer_number}`}
      width={1000}
      footer={
        <Space>
          <Button onClick={onClose}>Cerrar</Button>
          {isDraftOrRequested && (
            <Button
              type="primary"
              icon={<SwapOutlined />}
              onClick={() => onApprove(transfer)}
            >
              Aprobar cantidades
            </Button>
          )}
          {isApproved && (
            <Button
              type="primary"
              icon={<SwapOutlined />}
              onClick={() => onDispatch(transfer)}
            >
              Despachar
            </Button>
          )}
          {isInTransit && (
            <Button
              type="primary"
              color="cyan"
              icon={<SwapOutlined />}
              onClick={() => onReceive(transfer)}
            >
              Recibir
            </Button>
          )}
          {isPartiallyReceived && (
            <Button
              type="primary"
              color="cyan"
              icon={<SwapOutlined />}
              onClick={() => onReceive(transfer)}
            >
              Registrar recepción pendiente
            </Button>
          )}
        </Space>
      }
    >
      {/* Metadatos principales */}
      <Descriptions size="small" column={2} style={{ marginBottom: 16 }}>
        <Descriptions.Item label="Número">
          <strong>{transfer.transfer_number}</strong>
        </Descriptions.Item>
        <Descriptions.Item label="Estado">
          <Tag color={statusBadgeColor(transfer.status_name)} style={{ fontWeight: 600 }}>
            {transfer.status_label}
          </Tag>
        </Descriptions.Item>
        <Descriptions.Item label="Bodega origen">
          {transfer.from_warehouse?.name ?? transfer.from_warehouse_name}
        </Descriptions.Item>
        <Descriptions.Item label="Bodega destino">
          {transfer.to_warehouse?.name ?? transfer.to_warehouse_name}
        </Descriptions.Item>
        <Descriptions.Item label="Solicitado por">
          {transfer.requested_by_name ?? '—'}
        </Descriptions.Item>
        <Descriptions.Item label="Fecha">
          {formatDate(transfer.created_at)}
        </Descriptions.Item>
        {transfer.approved_by_name && (
          <Descriptions.Item label="Aprobado por">
            {transfer.approved_by_name}
          </Descriptions.Item>
        )}
        {transfer.dispatched_by_name && (
          <Descriptions.Item label="Despachado por">
            {transfer.dispatched_by_name} — {formatDate(transfer.dispatched_at)}
          </Descriptions.Item>
        )}
        {transfer.received_by_name && (
          <Descriptions.Item label="Recibido por">
            {transfer.received_by_name} — {formatDate(transfer.received_at)}
          </Descriptions.Item>
        )}
        {transfer.cancelled_by_name && (
          <Descriptions.Item label="Anulado por">
            {transfer.cancelled_by_name} — {formatDate(transfer.cancelled_at)}
          </Descriptions.Item>
        )}
      </Descriptions>

      {transfer.notes && (
        <div style={{ marginBottom: 16 }}>
          <strong>Notas:</strong> {transfer.notes}
        </div>
      )}

      <Divider style={{ margin: '12px 0' }} />

      {/* Items */}
      <div style={{ marginBottom: 8 }}>
        <strong>Ítems ({transfer.items?.length ?? 0})</strong>
      </div>
      <Table
        rowKey="id"
        size="small"
        columns={itemColumns}
        dataSource={transfer.items ?? []}
        pagination={false}
        // Con 7 columnas la tabla no entra en un celular: scroll horizontal
        // en vez de cortar los numeros de cantidad.
        scroll={{ x: 760 }}
        locale={{ emptyText: 'Sin ítems' }}
      />

      {/* Discrepancias */}
      {hasDiscrepancies && (
        <>
          <Divider style={{ margin: '12px 0' }} />
          <div style={{ marginBottom: 8 }}>
            <strong>Discrepancias ({transfer.discrepancies?.length})</strong>
          </div>
          <Table
            rowKey="id"
            size="small"
            columns={buildDiscrepancyColumns((d, action) => {
              setResolving({ discrepancia: d, action })
            })}
            dataSource={transfer.discrepancies ?? []}
            pagination={false}
            locale={{ emptyText: 'Sin discrepancias' }}
          />
        </>
      )}

      {resolving && (
        <ResolveDiscrepancyModal
          discrepancia={resolving.discrepancia}
          action={resolving.action}
          onCancel={() => setResolving(null)}
          onConfirm={async (notes) => {
            await handleResolver(resolving.discrepancia, resolving.action, notes)
            setResolving(null)
          }}
        />
      )}
    </Modal>
  )
}

/**
 * Modal chico para confirmar la resolucion de una discrepancia y capturar
 * la nota. Se invoca desde la tabla de discrepancias en el detalle.
 */
function ResolveDiscrepancyModal({
  discrepancia,
  action,
  onCancel,
  onConfirm,
}: {
  discrepancia: TransferDiscrepancy
  action: ResolveDiscrepancyAction
  onCancel: () => void
  onConfirm: (notes: string | null) => Promise<void>
}) {
  const [notes, setNotes] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const titulo =
    action === 'resolve'
      ? 'Cerrar la discrepancia sin ajuste'
      : 'Dar de baja la discrepancia'

  // El texto dice exactamente que pasa con el stock, porque las dos acciones
  // lo hacen igual y lo unico que las distingue es la consecuencia.
  const descripcion =
    action === 'resolve'
      ? 'Cierra el registro de la diferencia sin mover stock. Usalo cuando la mercaderia ya se contabilizo por otra via (llegada tardia registrada aparte, reposicion, correccion por ajuste).'
      : 'Confirma que la mercaderia se perdio. No se repone stock automaticamente: la correccion de inventario se hace por ajuste aparte.'

  return (
    <Modal
      open
      title={titulo}
      onCancel={onCancel}
      okText={action === 'resolve' ? 'Cerrar sin ajuste' : 'Dar de baja'}
      cancelText="Cancelar"
      okButtonProps={{ danger: action === 'write_off' }}
      confirmLoading={submitting}
      onOk={async () => {
        setSubmitting(true)
        try {
          await onConfirm(notes.trim() || null)
        } finally {
          setSubmitting(false)
        }
      }}
    >
      <p style={{ marginTop: 0, marginBottom: 12 }}>{descripcion}</p>
      <Descriptions size="small" column={1} bordered>
        <Descriptions.Item label="Aprobado">{discrepancia.approved_quantity}</Descriptions.Item>
        <Descriptions.Item label="Recibido">{discrepancia.delivered_quantity}</Descriptions.Item>
        <Descriptions.Item label="Faltante">
          <Tag color="gold">-{discrepancia.difference}</Tag>
        </Descriptions.Item>
      </Descriptions>
      <Form layout="vertical" style={{ marginTop: 12 }}>
        <Form.Item label="Nota (opcional)" help="Queda registrada en la auditoria de la discrepancia.">
          <Input.TextArea
            rows={3}
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            maxLength={1000}
            showCount
            placeholder={
              action === 'write_off'
                ? 'Ej: unidad rota en transporte, merma registrada...'
                : 'Ej: la unidad llego y ya se contabilizo en el traslado TRF-...'
            }
          />
        </Form.Item>
      </Form>
    </Modal>
  )
}
