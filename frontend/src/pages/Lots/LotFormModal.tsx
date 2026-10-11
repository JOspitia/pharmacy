/**
 * LotFormModal — Form crear/editar lotes.
 *
 * HU-08 (RN-01): el lote ya no tiene bodega ni stock propias. El stock
 * pertenece a la existencia (bodega x producto x lote) y se crea al
 * registrar un movimiento de Kardex (POST /api/movements con type=ENTRY).
 *
 * Por eso el form solo pide: codigo, producto, fecha de vencimiento,
 * descripcion. Sin bodega, sin stock. Si el regente quiere cargar
 * mercancia, va a la pantalla de Kardex y registra un ENTRY.
 */
import { useEffect, useState } from 'react'
import {
  Button,
  Form,
  Input,
  Select,
  DatePicker,
  message,
} from 'antd'
import { useNavigate, useParams } from 'react-router-dom'
import { useApi } from '../../hooks/useApi'
import { useFormErrors } from '../../hooks/useFormErrors'
import { apiFetch } from '../../lib/api'
import { ErrorAlert } from '../../components/ErrorAlert'
import { LoadingScreen } from '../../components/LoadingScreen'
import type { FormDataResponse, LotPayload, LotResponse } from '../../lib/lots'
import dayjs, { type Dayjs } from 'dayjs'

/**
 * Valores del formulario de lotes (HU-08).
 */
interface FormValues {
  name: string
  product_id: string
  expiration_date: Dayjs
  description?: string
}

const NAME_REGEX = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-()°]+$/u

/**
 * Componente de formulario para crear o editar lotes.
 */
export function LotFormModal() {
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const isEdit = Boolean(id)
  const [form] = Form.useForm<FormValues>()
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const { generalMessage, getError } = useFormErrors(error)

  // HU-08: form-data ya no trae bodegas porque el lote no las elige.
  // Solo carga productos (necesarios para asignar el lote a uno).
  const lotPath = isEdit ? `/api/lots/${id}` : null
  const { data: lotData, isLoading: lotLoading } = useApi<LotResponse>(lotPath, [id])
  const { data: formData, isLoading: formDataLoading } = useApi<FormDataResponse>(
    '/api/lots/form-data',
  )

  useEffect(() => {
    if (lotData?.data && formData) {
      form.setFieldsValue({
        name: lotData.data.name,
        product_id: lotData.data.product?.id ?? '',
        expiration_date: lotData.data.expiration_date
          ? dayjs(lotData.data.expiration_date)
          : undefined,
        description: lotData.data.description ?? '',
      })
    }
  }, [lotData, formData, form])

  const handleSubmit = async (values: FormValues) => {
    setSubmitting(true)
    setError(null)
    try {
      const payload: LotPayload = {
        name: values.name.trim(),
        product_id: values.product_id,
        expiration_date: values.expiration_date.format('YYYY-MM-DD'),
        description: values.description?.trim() || null,
      }

      if (isEdit && id) {
        await apiFetch(`/api/lots/${id}`, { method: 'PUT', body: payload })
        message.success('Lote actualizado')
      } else {
        await apiFetch('/api/lots', { method: 'POST', body: payload })
        message.success('Lote creado')
        form.resetFields()
      }

      setTimeout(() => navigate('/lots'), 1500)
    } catch (err) {
      setError(err)
    } finally {
      setSubmitting(false)
    }
  }

  if ((isEdit && lotLoading) || formDataLoading) {
    return <LoadingScreen />
  }

  const products = formData?.products ?? []

  return (
    <>
      <h1
        style={{
          fontFamily: "'Space Grotesk', sans-serif",
          fontSize: 28,
          fontWeight: 700,
          color: '#0e7490',
          margin: '0 0 24px',
        }}
      >
        {isEdit ? 'Editar Lote' : 'Nuevo Lote'}
      </h1>

      <ErrorAlert message={generalMessage} />

      <Form<FormValues>
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{
          name: '',
          product_id: undefined,
          expiration_date: undefined,
          description: '',
        }}
      >
        <Form.Item
          label="Código"
          name="name"
          validateStatus={getError('name') ? 'error' : undefined}
          help={getError('name')}
          rules={[
            { required: true, message: 'El código es requerido' },
            { max: 120, message: 'Máximo 120 caracteres' },
            {
              pattern: NAME_REGEX,
              message: 'Solo letras, números, espacios, guiones, paréntesis y °',
            },
          ]}
        >
          <Input placeholder="Lote 001" autoFocus />
        </Form.Item>

        <Form.Item
          label="Producto"
          name="product_id"
          validateStatus={getError('product_id') ? 'error' : undefined}
          help={getError('product_id')}
          rules={[
            { required: true, message: 'Debe seleccionar un producto' },
          ]}
        >
          <Select
            key={`product-${formData ? 'loaded' : 'loading'}`}
            placeholder="Seleccione un producto"
            options={products.map((p) => ({ value: p.id, label: p.name }))}
            showSearch
            optionFilterProp="label"
          />
        </Form.Item>

        <Form.Item
          label="Fecha de Vencimiento"
          name="expiration_date"
          validateStatus={getError('expiration_date') ? 'error' : undefined}
          help={getError('expiration_date')}
          rules={[
            { required: true, message: 'La fecha de vencimiento es requerida' },
          ]}
        >
          <DatePicker
            style={{ width: '100%' }}
            format="YYYY-MM-DD"
            placeholder="Seleccione una fecha"
          />
        </Form.Item>

        <Form.Item
          label="Descripción"
          name="description"
          validateStatus={getError('description') ? 'error' : undefined}
          help={getError('description')}
          rules={[{ max: 300, message: 'Máximo 300 caracteres' }]}
        >
          <Input.TextArea rows={3} placeholder="Descripción del lote (opcional)" />
        </Form.Item>

        <Form.Item style={{ marginBottom: 0, marginTop: 24 }}>
          <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
            <Button onClick={() => navigate('/lots')}>Cancelar</Button>
            <Button type="primary" htmlType="submit" loading={submitting}>
              {isEdit ? 'Guardar cambios' : 'Crear lote'}
            </Button>
          </div>
        </Form.Item>
      </Form>
    </>
  )
}
