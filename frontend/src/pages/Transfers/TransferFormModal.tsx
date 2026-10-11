/**
 * TransferFormModal - Modal para crear un traslado entre bodegas (HU-09).
 *
 * Patron: mismo diseno que MovementFormModal (Modal con Form, useFormErrors, ErrorAlert).
 *
 * Flujo de llenado:
 *   1) Seleccionar bodega destino (required).
 *   2) Agregar items: cada item es (inventory_id, requested_quantity).
 *      El primer item elegido determina la bodega ORIGEN (se deduce del
 *      inventory y se muestra bloqueada). Los items siguientes solo
 *      muestran inventarios de esa misma bodega origen.
 *   3) Agregar notas (opcional).
 *   4) Enviar POST /api/transfers.
 *
 * Validaciones del backend (HTTP 422):
 *   - Stock insuficiente (CA-14)
 *   - Bodega origen == destino (CA-12)
 *   - Items de bodegas distintas
 */
import { useEffect, useState } from 'react'
import { Button, Form, Input, Modal, Select, InputNumber, Table, message } from 'antd'
import { PlusOutlined, DeleteOutlined } from '@ant-design/icons'
import type { ColumnsType } from 'antd/es/table'
import { apiFetch } from '../../lib/api'
import { useApi } from '../../hooks/useApi'
import { useFormErrors } from '../../hooks/useFormErrors'
import { ErrorAlert } from '../../components/ErrorAlert'
import type { Warehouse } from '../../lib/warehouses'
import type { Inventory, InventoryListResponse } from '../../lib/inventories'
import { buildInventoryQuery } from '../../lib/inventories'
import type { CreateTransferPayload, Transfer } from '../../lib/transfers'

interface FormValues {
  to_warehouse_id: string
  notes?: string
}

interface ItemRow {
  key: string
  inventory_id: string
  requested_quantity: number
}

/**
 * Construye el label que se muestra en el Select de inventario.
 * Muestra: producto / lote / stock disponible / vencimiento.
 */
function inventoryLabel(inv: Inventory): string {
  const stock = inv.quantity
  const exp = inv.expiration_date
    ? ` (vence ${inv.expiration_date})`
    : ''
  return [
    inv.product?.name ?? '—',
    '/',
    inv.lot?.name ?? '—',
    '/ stock:',
    stock,
    inv.expiration_status === 'vencido' ? ' [VENCIDO]' : '',
    exp,
  ].join(' ')
}

export function TransferFormModal({
  open,
  onClose,
  onCreated,
}: {
  open: boolean
  onClose: () => void
  onCreated: (transfer: Transfer) => void
}) {
  const [form] = Form.useForm<FormValues>()
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const { generalMessage, getError } = useFormErrors(error)

  // Items agregados al traslado
  const [items, setItems] = useState<ItemRow[]>([])
  // Bodega origen bloqueada (deducida del primer item)
  const [originWarehouse, setOriginWarehouse] = useState<Warehouse | null>(null)

  // Fetch de bodegas para el selector destino
  const { data: warehousesData } = useApi<{ data: Warehouse[] }>(
    '/api/bodegas?per_page=200',
    [],
  )
  const warehouses = warehousesData?.data ?? []

  // Fetch de inventarios. Si tenemos originWarehouse, filtramos por el;
  // si no, mostramos todos para que el usuario elija el origen.
  // Se usa buildInventoryQuery (consistente con InventoriesList).
  const inventoryFilters = {
    ...(originWarehouse ? { warehouse_id: originWarehouse.id } : {}),
    per_page: 100,
  }
  const { data: inventoriesData, isLoading: inventoriesLoading } = useApi<InventoryListResponse>(
    buildInventoryQuery(inventoryFilters),
    [originWarehouse?.id],
  )
  const inventories = (inventoriesData?.data ?? []).filter(
    (inv) => inv.product != null && inv.quantity > 0,
  )

  // Reset al abrir/cerrar
  useEffect(() => {
    if (open) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setError(null)
      setItems([])
      setOriginWarehouse(null)
      form.resetFields()
    }
  }, [open, form])

  /**
   * Agrega un item vacio a la lista.
   */
  const addItem = () => {
    setItems((prev) => [
      ...prev,
      { key: `item-${Date.now()}`, inventory_id: '', requested_quantity: 1 },
    ])
  }

  /**
   * Elimina un item por key.
   */
  const removeItem = (key: string) => {
    setItems((prev) => {
      const next = prev.filter((i) => i.key !== key)
      // Si se eliminaron todos los items, liberar la bodega origen.
      if (next.length === 0) {
        setOriginWarehouse(null)
      }
      return next
    })
  }

  /**
   * Cuando se selecciona un inventario en un item, deducir la bodega
   * origen del primer item y bloquearla para los siguientes.
   */
  const handleInventoryChange = (key: string, inventoryId: string) => {
    setItems((prev) => prev.map((item) => (item.key === key ? { ...item, inventory_id: inventoryId } : item)))

    // Si este es el primer item con inventario, deducir la bodega origen.
    if (!originWarehouse && inventoryId) {
      const selected = inventories.find((i) => i.id === inventoryId)
      if (selected?.bodega) {
        setOriginWarehouse(selected.bodega as unknown as Warehouse)
      }
    }
  }

  /**
   * Columns del item row. Inline porque es simple.
   */
  const itemColumns: ColumnsType<ItemRow> = [
    {
      title: 'Inventario',
      key: 'inventory_id',
      width: 480,
      render: (_, record) => {
        // Inventarios ya elegidos en OTRAS filas. El mismo inventario
        // (producto + lote) es una unica existencia fisica: repetirlo en
        // dos lineas es redundante y el backend lo rechaza con 422
        // (distinct en StoreTransferRequest). Deshabilitarlo aca evita
        // que el usuario llegue a ver el error.
        const usadosEnOtras = new Set(
          items
            .filter((i) => i.key !== record.key && i.inventory_id)
            .map((i) => i.inventory_id),
        )

        return (
        <Select
          showSearch
          placeholder={
            inventoriesLoading
              ? 'Cargando inventarios...'
              : originWarehouse
                ? `Inventarios de ${originWarehouse.name}`
                : 'Seleccione (establece origen)'
          }
          style={{ width: '100%' }}
          value={record.inventory_id || undefined}
          onChange={(v) => handleInventoryChange(record.key, v)}
          loading={inventoriesLoading}
          // El nombre del producto puede ser largo: se muestra completo en el
          // desplegable (dropdownMatchSelectWidth=false) pero se trunca con
          // puntos suspensivos dentro de la celda para no romper el layout.
          dropdownMatchSelectWidth={false}
          maxTagCount={1}
          filterOption={(input, option) =>
            (option?.label as unknown as string ?? '')
              .toLowerCase()
              .includes(input.toLowerCase())
          }
          options={inventories.map((inv) => ({
            value: inv.id,
            label: inventoryLabel(inv),
            disabled: inv.quantity <= 0 || usadosEnOtras.has(inv.id),
          }))}
        />
        )
      },
    },
    {
      title: 'Cantidad solicitada',
      key: 'requested_quantity',
      width: 240,
      render: (_, record) => (
        <InputNumber
          min={1}
          value={record.requested_quantity}
          onChange={(v) =>
            setItems((prev) =>
              prev.map((item) =>
                item.key === record.key
                  ? { ...item, requested_quantity: v ?? 1 }
                  : item,
              ),
            )
          }
          style={{ width: '100%' }}
        />
      ),
    },
    {
      title: '',
      key: 'actions',
      width: 80,
      render: (_, record) => (
        <Button
          type="text"
          danger
          icon={<DeleteOutlined />}
          onClick={() => removeItem(record.key)}
          disabled={items.length === 1}
        />
      ),
    },
  ]

  /**
   * Envio del formulario.
   */
  const handleSubmit = async (values: FormValues) => {
    if (items.length === 0) {
      message.error('Agregue al menos un ítem al traslado')
      return
    }

    const invalidItem = items.find((i) => !i.inventory_id || i.requested_quantity < 1)
    if (invalidItem) {
      message.error('Complete todos los ítems antes de enviar')
      return
    }

    setSubmitting(true)
    setError(null)
    try {
      const payload: CreateTransferPayload = {
        to_warehouse_id: values.to_warehouse_id,
        notes: values.notes?.trim() || undefined,
        items: items.map((i) => ({
          inventory_id: i.inventory_id,
          requested_quantity: i.requested_quantity,
        })),
      }
      const result = await apiFetch<{ data: Transfer }>('/api/transfers', {
        method: 'POST',
        body: payload,
      })
      onCreated(result.data)
    } catch (err) {
      setError(err)
    } finally {
      setSubmitting(false)
    }
  }

  /**
   * Cierra el modal y resetea estado.
   */
  const handleCancel = () => {
    form.resetFields()
    setError(null)
    onClose()
  }

  // Conflicto de bodega origen: solo cuando un item YA eligio inventario y
  // ese inventario pertenece a otra bodega.
  //
  // Una fila recien agregada tiene inventory_id === '' y todavia no puede
  // estar en conflicto: `inventories.find()` devuelve undefined y comparar
  // `undefined !== originWarehouse.id` daba true (falso positivo que hacia
  // aparecer el aviso apenas se agregaba la segunda fila).
  const isWarehouseConflict =
    originWarehouse != null &&
    items.some((i) => {
      if (!i.inventory_id) return false
      const inv = inventories.find((x) => x.id === i.inventory_id)
      // Si el inventario todavia no esta en la lista (refetch en curso al
      // fijar el origen) no hay nada que reportar.
      if (!inv) return false
      return inv.bodega?.id !== originWarehouse.id
    })

  return (
    <Modal
      open={open}
      onCancel={handleCancel}
      title="Nuevo traslado entre bodegas"
      okText="Crear traslado"
      cancelText="Cancelar"
      confirmLoading={submitting}
      onOk={() => form.submit()}
      width={900}
      // El ancho fijo se recorta solo a `calc(100vw - 32px)` (CSS de antd),
      // asi que en celular el modal ocupa el viewport sin scroll horizontal.
      // Lo que hay que limitar es el ALTO: sin esto el contenido se pasa de
      // la pantalla y las notas quedan inalcanzables.
      styles={{ body: { maxHeight: '70vh', overflowY: 'auto' } }}
      destroyOnClose
    >
      <ErrorAlert message={generalMessage} />

      {/* Indicador de bodega origen (deducida del primer item) */}
      {originWarehouse && (
        <div
          style={{
            background: '#f6ffed',
            border: '1px solid #b7eb8f',
            borderRadius: 4,
            padding: '8px 12px',
            marginBottom: 16,
            fontSize: 13,
          }}
        >
          <strong>Bodega origen:</strong> {originWarehouse.name}
          {' '}— Los próximos ítems se filtran automáticamente.
        </div>
      )}

      {isWarehouseConflict && (
        <div
          style={{
            background: '#fff2e8',
            border: '1px solid #ffbb96',
            borderRadius: 4,
            padding: '8px 12px',
            marginBottom: 16,
            fontSize: 13,
            color: '#d4380d',
          }}
        >
          Los ítems deben ser todos de la misma bodega origen.
          Cambie el origen o quite los ítems en conflicto.
        </div>
      )}

      <Form<FormValues>
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{ requested_quantity: 1 }}
      >
        <Form.Item
          label="Bodega destino"
          name="to_warehouse_id"
          validateStatus={getError('to_warehouse_id') ? 'error' : undefined}
          help={getError('to_warehouse_id')}
          rules={[{ required: true, message: 'Seleccione la bodega destino' }]}
        >
          <Select
            placeholder="Seleccione la bodega destino"
            showSearch
            optionFilterProp="label"
            options={warehouses.map((w) => ({ value: w.id, label: w.name }))}
          />
        </Form.Item>

        {/* Items del traslado */}
        <div style={{ marginBottom: 8 }}>
          <strong>Ítems del traslado</strong>
        </div>
        <Table
          rowKey="key"
          columns={itemColumns}
          dataSource={items}
          pagination={false}
          size="small"
          // table-layout:fixed + scroll.x|utc obligan a respetar los anchos de
          // columna. Sin esto, un nombre de producto largo estira la columna
          // inventario y empuja el encabezado "Cantidad solicitada" y los
          // botones de eliminar fuera del modal.
          tableLayout="fixed"
          scroll={{ x: 820 }}
          footer={() => (
            <Button
              type="dashed"
              icon={<PlusOutlined />}
              onClick={addItem}
              block
            >
              Agregar ítem
            </Button>
          )}
        />

        <Form.Item
          label="Notas (opcional)"
          name="notes"
          validateStatus={getError('notes') ? 'error' : undefined}
          help={getError('notes')}
          style={{ marginTop: 16 }}
        >
          <Input.TextArea
            rows={2}
            placeholder="Urgente para atencion de pacientes, etc."
            maxLength={1000}
          />
        </Form.Item>
      </Form>
    </Modal>
  )
}
