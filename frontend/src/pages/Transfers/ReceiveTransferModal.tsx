/**
 * ReceiveTransferModal - Recibir un traslado en transito (HU-09, RN-07).
 *
 * Se abre desde la lista o el detalle de un traslado en estado IN_TRANSIT.
 * Permite al auxiliar o regente registrar la cantidad recibida por item.
 * Si delivered < approved, se genera una discrepancia (RN-07).
 *
 * Backend: PATCH /api/transfers/{id}/receive
 * Valida: delivered_quantity <= approved_quantity.
 */
import { useMemo, useState } from 'react'
import { Form, Modal, InputNumber, Table, Alert, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { apiFetch } from '../../lib/api'
import { useFormErrors } from '../../hooks/useFormErrors'
import { ErrorAlert } from '../../components/ErrorAlert'
import type { ReceiveTransferPayload, Transfer, TransferItem } from '../../lib/transfers'

/**
 * El formulario solo captura el campo editable (`delivered_quantity`).
 * `transfer_item_id` se toma de initialItems al armar el payload.
 */
interface FormValues {
  items?: Record<string | number, { delivered_quantity?: number | null } | null>
}

interface ReceiveItem extends TransferItem {
  delivered_quantity_input: number | null
  /** Posicion en el array de items: define el field path del Form.Item. */
  index: number
}

function LotExpirationBadge({ date }: { date: string | null }) {
  if (!date) return null
  return (
    <span style={{ fontSize: 11, color: '#94a3b8' }}>
      {' '}
      (vence {date})
    </span>
  )
}

export function ReceiveTransferModal({
  transfer,
  onClose,
  onReceived,
}: {
  transfer: Transfer | null
  onClose: () => void
  onReceived: () => void
}) {
  const [form] = Form.useForm<FormValues>()
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const { generalMessage } = useFormErrors(error)

  // Pre-llena con lo YA RECIBIDO, no con lo aprobado.
  //
  // En la primera recepcion ambos son 0, asi que el campo arranca en 0 y el
  // auxiliar escribe lo que llego. En una recepcion tardia (traslado en
  // PARTIALLY_RECEIVED) lo recibido puede ser menor que lo aprobado: mostrar
  // la cantidad aprobada ahi diria "recibiste 2" cuando solo llego 1. Con
  // delivered_quantity el campo arranca en la realidad (1) y el auxiliar
  // suma lo que falte.
  const initialItems = useMemo<ReceiveItem[]>(
    () =>
      (transfer?.items ?? []).map((item, i) => ({
        ...item,
        index: i,
        delivered_quantity_input: item.delivered_quantity ?? 0,
      })),
    [transfer],
  )

  // Limpiar error residual al abrir el modal.
  if (!transfer) return null

  // Recepcion tardia: el traslado ya paso por una entrega incompleta y
  // tiene discrepancias pendientes. El formulario se reabre en modo
  // "sumar lo que falto", no "cargar de cero".
  const isLateReception =
    transfer.status_name === 'PARTIALLY_RECEIVED' ||
    (transfer.discrepancies ?? []).some((d) => d.status === 'PENDING')

  const handleSubmit = async (values: FormValues) => {
    setSubmitting(true)
    setError(null)
    try {
      // Cruzar la cantidad capturada por el form con el id del item, que
      // viene de initialItems y no del store del formulario. El fallback
      // es lo ya recibido, nunca lo aprobado: si el form vino vacio se
      // conserva la realidad en vez de inventar una entrega.
      const payload: ReceiveTransferPayload = {
        items: initialItems.map((item) => ({
          transfer_item_id: item.id,
          delivered_quantity: Number(
            values.items?.[item.index]?.delivered_quantity ??
              item.delivered_quantity ??
              0,
          ),
        })),
      }
      const result = await apiFetch<{ data: Transfer }>(
        `/api/transfers/${transfer.id}/receive`,
        { method: 'PATCH', body: payload },
      )
      const receivedTransfer = result.data
      const hasDiscrepancies =
        receivedTransfer.status_name === 'PARTIALLY_RECEIVED' ||
        (receivedTransfer.discrepancies?.length ?? 0) > 0

      if (hasDiscrepancies) {
        message.warning(
          `Traslado recibido parcialmente. ${receivedTransfer.discrepancies?.length} discrepancia(s) registrada(s).`,
        )
      } else {
        message.success(`Traslado ${transfer.transfer_number} recibido completamente`)
      }
      onReceived()
    } catch (err) {
      setError(err)
    } finally {
      setSubmitting(false)
    }
  }

  const columns: ColumnsType<ReceiveItem> = [
    {
      title: 'Producto',
      key: 'product',
      render: (_, record) => record.product?.name ?? '—',
    },
    {
      title: 'Lote',
      key: 'lot',
      width: 130,
      render: (_, record) => (
        <span>
          {record.lot?.name ?? '—'}
          {record.lot_expiration_date && (
            <LotExpirationBadge date={record.lot_expiration_date} />
          )}
        </span>
      ),
    },
    {
      title: 'Aprobada',
      dataIndex: 'approved_quantity',
      key: 'approved_quantity',
      width: 90,
      align: 'center',
    },
    {
      title: 'Recibida (edit.)',
      key: 'delivered_quantity_input',
      width: 130,
      render: (_, record) => (
        <Form.Item
          name={['items', record.index, 'delivered_quantity']}
          rules={[
            { required: true, message: 'Requerida' },
            {
              type: 'number',
              min: 0,
              max: record.approved_quantity ?? record.requested_quantity,
              message: `Maximo ${record.approved_quantity ?? record.requested_quantity}`,
            },
          ]}
          style={{ margin: 0 }}
          initialValue={record.delivered_quantity_input}
        >
          <InputNumber
            min={0}
            max={record.approved_quantity ?? record.requested_quantity}
            style={{ width: '100%' }}
          />
        </Form.Item>
      ),
    },
    {
      title: 'Diferencia',
      key: 'diff',
      width: 80,
      align: 'center',
      render: (_, record) => {
        const approved = record.approved_quantity ?? record.requested_quantity
        const delivered = record.delivered_quantity_input ?? 0
        return (
          <span
            style={{
              color: delivered < approved ? '#d4380d' : '#16a34a',
              fontWeight: 600,
            }}
          >
            {delivered < approved ? `+${approved - delivered}` : '—'}
          </span>
        )
      },
    },
  ]

  return (
    <Modal
      open={Boolean(transfer)}
      onCancel={onClose}
      title={`Recibir traslado ${transfer.transfer_number}`}
      okText="Confirmar recepcion"
      cancelText="Cancelar"
      confirmLoading={submitting}
      onOk={() => form.submit()}
      width={680}
      destroyOnClose
    >
      <ErrorAlert message={generalMessage} />

      <Alert
        message="Recepción de traslado"
        description={
          isLateReception ? (
            <span>
              Este traslado ya tenía una recepción incompleta. Sumá a lo
              recibido lo que acaba de llegar a{' '}
              <strong>{transfer.to_warehouse?.name ?? transfer.to_warehouse_name}</strong>.
              Si completas lo aprobado, la discrepancia se cierra sola.
            </span>
          ) : (
            <span>
              Ingresa lo que llegó de cada ítem a{' '}
              <strong>{transfer.to_warehouse?.name ?? transfer.to_warehouse_name}</strong>.
              Si llega menos de lo aprobado, queda registrada la diferencia.
            </span>
          )
        }
        type="info"
        showIcon
        style={{ marginBottom: 16 }}
      />

      <Form<FormValues>
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{
          // Solo el campo editable: el id se resuelve en handleSubmit.
          items: initialItems.map((i) => ({
            delivered_quantity: i.delivered_quantity_input,
          })),
        }}
      >
        {/* Sin Form.List: ver la nota en ApproveTransferModal. */}
        <Table
          rowKey="id"
          size="small"
          columns={columns}
          dataSource={initialItems}
          pagination={false}
          locale={{ emptyText: 'Sin ítems' }}
        />
      </Form>
    </Modal>
  )
}
