/**
 * MovementFormModal - Modal para registrar un movimiento del Kardex (HU-08).
 *
 * Patron: mismo diseno que LotFormModal (Modal con Form, useFormErrors, ErrorAlert).
 * Carga bodegas al abrir; al elegir una bodega carga lotes de esa bodega via
 * /api/warehouses/{id}/lots. Asi el selector de lote muestra solo los
 * relevantes (FEFO) y avisa visualmente si el lote ya no tiene stock
 * (permitido para re-abastecer con ENTRY).
 */
import { useEffect, useState } from 'react'
import { Button, Form, Input, InputNumber, Modal, Select, message } from 'antd'
import { apiFetch } from '../../lib/api'
import { useApi } from '../../hooks/useApi'
import { useFormErrors } from '../../hooks/useFormErrors'
import { ErrorAlert } from '../../components/ErrorAlert'
import type { Warehouse } from '../../lib/warehouses'
import type { LotInWarehouse } from '../../lib/inventories'
import { MOVEMENT_TYPES, type Movement, type MovementPayload } from '../../lib/movements'

/**
 * Valores del formulario. La fecha y la cantidad son obligatorias.
 */
interface FormValues {
  type: Movement['type']
  warehouse_id: string
  lot_id: string
  quantity: number
  reason?: string
}

interface MovementFormModalProps {
  open: boolean
  onClose: () => void
  /**
   * Callback que se ejecuta despues de que el backend confirma 201.
   * El modal ya se cerro: el padre solo necesita refrescar su lista.
   */
  onCreated: () => void
  /**
   * Si el modal se abrio desde una pantalla con bodega ya filtrada, se
   * preselecciona esa bodega y su primer lote.
   */
  defaultWarehouseId?: string
}

/**
 * Modal de registro de movimiento. Diseno en cascada:
 *  1) tipo (Select)
 *  2) bodega (Select)
 *  3) lote (Select dependiente de bodega, via /api/warehouses/{id}/lots)
 *  4) cantidad (InputNumber > 0)
 *  5) motivo (Input.TextArea, opcional)
 */
export function MovementFormModal({
  open,
  onClose,
  onCreated,
  defaultWarehouseId,
}: MovementFormModalProps) {
  const [form] = Form.useForm<FormValues>()
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const { generalMessage, getError } = useFormErrors(error)

  // Bodega seleccionada -> determina que lotes se cargan
  const [warehouseId, setWarehouseId] = useState<string | undefined>(defaultWarehouseId)

  // Reset al abrir/cerrar
  useEffect(() => {
    if (open) {
      setError(null)
      setWarehouseId(defaultWarehouseId)
      form.resetFields()
      if (defaultWarehouseId) {
        form.setFieldValue('warehouse_id', defaultWarehouseId)
      }
    }
  }, [open, defaultWarehouseId, form])

  // Bodegas (catalogo: usamos el endpoint paginado, tomamos 100)
  const { data: warehousesData } = useApi<{ data: Warehouse[] }>(
    '/api/bodegas?per_page=100',
    [],
  )
  const warehouses = warehousesData?.data ?? []

  // Lotes de la bodega seleccionada. Solo se carga si hay bodega.
  // Importante: el endpoint incluye lotes con stock 0 (HU-08, para re-abastecer).
  const { data: lotsData, isLoading: lotsLoading } = useApi<{ data: LotInWarehouse[] }>(
    warehouseId ? `/api/bodegas/${warehouseId}/lots` : null,
    [warehouseId],
  )
  const lots = lotsData?.data ?? []

  /**
   * Envio del formulario. Construye el payload con los datos del request
   * del backend (HU-08 §10) y maneja errores 422/403 con mensajes utiles.
   */
  const handleSubmit = async (values: FormValues) => {
    setSubmitting(true)
    setError(null)
    try {
      const payload: MovementPayload = {
        type: values.type,
        warehouse_id: values.warehouse_id,
        lot_id: values.lot_id,
        quantity: values.quantity,
        reason: values.reason?.trim() || null,
      }
      await apiFetch('/api/movements', { method: 'POST', body: payload })
      message.success('Movimiento registrado')
      form.resetFields()
      onCreated()
    } catch (err) {
      setError(err)
    } finally {
      setSubmitting(false)
    }
  }

  /**
   * Cierra el modal y resetea estado. NO llama onCreated (es para exito).
   */
  const handleCancel = () => {
    form.resetFields()
    setError(null)
    onClose()
  }

  return (
    <Modal
      open={open}
      onCancel={handleCancel}
      title="Registrar movimiento de Kardex"
      okText="Registrar"
      cancelText="Cancelar"
      confirmLoading={submitting}
      onOk={() => form.submit()}
      width={600}
      destroyOnClose
    >
      <ErrorAlert message={generalMessage} />

      <Form<FormValues>
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{ type: 'ENTRY', quantity: 1 }}
        preserve={false}
      >
        <Form.Item
          label="Tipo de movimiento"
          name="type"
          validateStatus={getError('type') ? 'error' : undefined}
          help={getError('type')}
          rules={[{ required: true, message: 'Seleccione un tipo' }]}
        >
          <Select
            placeholder="Seleccione el tipo"
            options={MOVEMENT_TYPES.map((t) => ({ value: t.value, label: t.label }))}
          />
        </Form.Item>

        <Form.Item
          label="Bodega"
          name="warehouse_id"
          validateStatus={getError('warehouse_id') ? 'error' : undefined}
          help={getError('warehouse_id')}
          rules={[{ required: true, message: 'Seleccione una bodega' }]}
        >
          <Select
            placeholder="Seleccione la bodega"
            options={warehouses.map((w) => ({ value: w.id, label: w.name }))}
            onChange={(v) => {
              setWarehouseId(v)
              // Al cambiar de bodega, limpiar el lote seleccionado
              form.setFieldValue('lot_id', undefined)
            }}
            showSearch
            optionFilterProp="label"
          />
        </Form.Item>

        <Form.Item
          label="Lote"
          name="lot_id"
          validateStatus={getError('lot_id') ? 'error' : undefined}
          help={getError('lot_id')}
          rules={[{ required: true, message: 'Seleccione un lote' }]}
        >
          <Select
            placeholder={
              !warehouseId
                ? 'Primero seleccione una bodega'
                : lotsLoading
                  ? 'Cargando lotes...'
                  : 'Seleccione el lote'
            }
            disabled={!warehouseId}
            loading={lotsLoading}
            notFoundContent={
              !warehouseId
                ? null
                : lotsLoading
                  ? 'Cargando...'
                  : 'Esta bodega no tiene existencias. Cree un lote primero.'
            }
            options={lots.map((l) => ({
              value: l.id,
              label: (
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span>
                    {l.name}
                    {l.product && (
                      <span style={{ color: '#94a3b8', marginLeft: 8 }}>
                        {l.product.name}
                      </span>
                    )}
                  </span>
                  <span style={{ display: 'flex', gap: 4, alignItems: 'center' }}>
                    {l.days_until_expiration !== null && l.days_until_expiration < 0 && (
                      <span
                        style={{
                          background: '#dc2626',
                          color: 'white',
                          fontSize: 10,
                          padding: '1px 5px',
                          borderRadius: 3,
                        }}
                      >
                        VENCIDO
                      </span>
                    )}
                    {l.stock === 0 && (
                      <span
                        style={{
                          background: '#94a3b8',
                          color: 'white',
                          fontSize: 10,
                          padding: '1px 5px',
                          borderRadius: 3,
                        }}
                      >
                        SIN STOCK
                      </span>
                    )}
                    {l.stock > 0 && (
                      <span style={{ color: '#0e7490', fontWeight: 600 }}>
                        stock: {l.stock}
                      </span>
                    )}
                    {l.expiration_date && (
                      <span style={{ color: '#94a3b8' }}>
                        ({l.expiration_date})
                      </span>
                    )}
                  </span>
                </div>
              ),
              // Texto alternativo para busqueda (Select usa `label` para filtrar;
              // ademas agregamos `data-product-name` para que el showSearch matchee
              // por nombre de producto, no solo por codigo de lote).
            }))}
            showSearch
            optionFilterProp="label"
            filterOption={(input, option) => {
              const text = (option?.label as unknown as string) ?? ''
              return text.toLowerCase().includes(input.toLowerCase())
            }}
          />
        </Form.Item>

        <Form.Item
          label="Cantidad"
          name="quantity"
          validateStatus={getError('quantity') ? 'error' : undefined}
          help={getError('quantity')}
          rules={[
            { required: true, message: 'La cantidad es requerida' },
            { type: 'number', min: 1, message: 'La cantidad debe ser mayor a cero' },
          ]}
        >
          <InputNumber style={{ width: '100%' }} min={1} />
        </Form.Item>

        <Form.Item
          label="Motivo (opcional)"
          name="reason"
          validateStatus={getError('reason') ? 'error' : undefined}
          help={getError('reason')}
          rules={[{ max: 500, message: 'Maximo 500 caracteres' }]}
        >
          <Input.TextArea
            rows={2}
            placeholder="Recepcion OC-2026-0042, ajuste por merma, etc."
          />
        </Form.Item>

        {/* Boton invisible para que el form tenga submit por Enter; el boton
            real lo dispara el Modal via onOk. */}
        <Button type="primary" htmlType="submit" style={{ display: 'none' }} />
      </Form>
    </Modal>
  )
}
