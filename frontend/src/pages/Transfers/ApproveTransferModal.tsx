/**
 * ApproveTransferModal - Aprobar cantidades de un traslado (HU-09).
 *
 * Se abre desde el detalle de un traslado en estado DRAFT o REQUESTED.
 * Permite al regente ajustar las quantities aprobadas por item
 * (pueden ser menores o iguales a las solicitadas).
 *
 * Una vez aprobado, el regente puede hacer click en "Despachar" para
 * marcar el traslado como EN_TRANSIT (desde la lista o el detalle).
 *
 * Backend: PATCH /api/transfers/{id}/approve
 * Valida: RN-08 (solicitante != aprobador), RN-01 (lotes no vencidos).
 */
import { useMemo, useState } from 'react'
import { Form, Modal, InputNumber, Table, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { apiFetch } from '../../lib/api'
import { useFormErrors } from '../../hooks/useFormErrors'
import { ErrorAlert } from '../../components/ErrorAlert'
import type {
  ApproveTransferPayload,
  Transfer,
  TransferItem,
} from '../../lib/transfers'

/**
 * El formulario solo captura el campo editable (`approved_quantity`).
 * Sin Form.List, antd guarda `items` como objeto con claves numericas y no
 * arrastra los campos hermanos, asi que `transfer_item_id` se toma de
 * initialItems (datos ya conocidos) al armar el payload.
 */
interface FormValues {
  items?: Record<string | number, { approved_quantity?: number | null } | null>
}

/**
 * Un item con su quantity aprobada editable.
 */
interface ApproveItem extends TransferItem {
  approved_quantity_input: number | null
  /** Posicion en el array de items: define el field path del Form.Item. */
  index: number
}

export function ApproveTransferModal({
  transfer,
  onClose,
  onDispatched,
}: {
  transfer: Transfer | null
  onClose: () => void
  onDispatched: () => void
}) {
  const [form] = Form.useForm<FormValues>()
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const { generalMessage } = useFormErrors(error)

  // useMemo ANTES del early return: los hooks no pueden ejecutarse
  // condicionalmente. Además mantiene estable la referencia del array para
  // que el dataSource de la Table no cambie en cada render (eso fazia que
  // React remontara los InputNumber y se perdiera lo ya tipeado).
  const initialItems = useMemo<ApproveItem[]>(
    () =>
      (transfer?.items ?? []).map((item, i) => ({
        ...item,
        index: i,
        approved_quantity_input: item.approved_quantity,
      })),
    [transfer],
  )

  if (!transfer) return null

  const handleSubmit = async (values: FormValues) => {
    setSubmitting(true)
    setError(null)
    try {
      // Cruzar la cantidad capturada por el form con el id del item, que
      // viene de initialItems y no del store del formulario.
      //
      // Un campo en blanco significa "no aprobar este item" -> 0. Nunca se
      // rellena con requested_quantity a espaldas del usuario: pedir 5 y
      // aprobar 3 es una decision legitima (el sistema puede no tener el
      // stock real), y ese 2 restante se queda en la bodega de origen.
      const payload: ApproveTransferPayload = {
        items: initialItems.map((item) => ({
          transfer_item_id: item.id,
          approved_quantity: Number(values.items?.[item.index]?.approved_quantity ?? 0),
        })),
      }
      await apiFetch(`/api/transfers/${transfer.id}/approve`, {
        method: 'PATCH',
        body: payload,
      })
      message.success(`Traslado ${transfer.transfer_number} aprobado`)
      onDispatched()
    } catch (err) {
      setError(err)
    } finally {
      setSubmitting(false)
    }
  }

  const columns: ColumnsType<ApproveItem> = [
    {
      title: 'Producto',
      key: 'product',
      render: (_, record) => record.product?.name ?? '—',
    },
    {
      title: 'Lote',
      key: 'lot',
      width: 130,
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
      title: 'Aprobada (edit.)',
      key: 'approved_quantity_input',
      width: 130,
      render: (_, record) => (
        <Form.Item
          name={['items', record.index, 'approved_quantity']}
          rules={[
            // 0 es un valor valido: significa "no aprobar este item".
            // El campo puede quedar en blanco, que se envia como 0.
            {
              type: 'number',
              min: 0,
              max: record.requested_quantity,
              message: `La cantidad aprobada debe estar entre 0 y ${record.requested_quantity}`,
            },
          ]}
          style={{ margin: 0 }}
        >
          <InputNumber
            min={0}
            max={record.requested_quantity}
            placeholder="Sin aprobar"
            style={{ width: '100%' }}
          />
        </Form.Item>
      ),
    },
  ]

  return (
    <Modal
      open={Boolean(transfer)}
      onCancel={onClose}
      title={`Aprobar cantidades del traslado ${transfer.transfer_number}`}
      okText="Aprobar"
      cancelText="Cancelar"
      confirmLoading={submitting}
      onOk={() => form.submit()}
      width={640}
      destroyOnClose
    >
      <ErrorAlert message={generalMessage} />

      <p style={{ marginBottom: 16, color: '#64748b', fontSize: 13 }}>
        Ajuste las cantidades aprobadas por ítem. La cantidad aprobada no puede
        exceder la solicitada. Dejar un campo en blanco es lo mismo que
        aprobar <strong>0</strong>: ese ítem no se despacha y las unidades
        no despachadas permanecen en la bodega de origen. Luego de aprobar,
        haga click en <strong>&quot;Despachar&quot;</strong> para marcar el
        traslado como En tránsito y descontar el stock de la bodega origen.
      </p>

      <Form<FormValues>
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{
          // Solo el campo editable: el id se resuelve en handleSubmit.
          items: initialItems.map((i) => ({
            approved_quantity: i.approved_quantity_input,
          })),
        }}
      >
        {/* Sin Form.List: los items son fijos (no se agregan ni quitan en
            este modal), asi que no hay nada que dinamizar. Form.List exige
            que su funcion hija renderice los campos a partir de `fields`; al
            ignorarla, se re-sincronizaba en cada cambio y borraba lo ya
            capturado. La Table con Form.Item por fila alcanza. */}
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
