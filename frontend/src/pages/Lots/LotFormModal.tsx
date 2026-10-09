/**
 * LotFormModal — Form crear/editar lotes.
 * Se renderiza dentro de AppLayout.
 */

import { useEffect, useState } from 'react'
import {
  Button,
  Form,
  Input,
  InputNumber,
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
 * Interfaz que define los valores del formulario de lotes
 * @interface FormValues
 * @property {string} name - Nombre del lote
 * @property {string} product_id - ID del producto
 * @property {string} warehouse_id - ID de la bodega
 * @property {number} stock - Stock del lote
 * @property {Dayjs} expiration_date - Fecha de expiración del lote
 * @property {string} description - Descripción del lote
 */
interface FormValues {
  name: string
  product_id: string
  warehouse_id: string
  stock: number
  expiration_date: Dayjs
  description?: string
}

const NAME_REGEX = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-()°]+$/u

/**
 * Componente de formulario para crear o editar lotes.
 * 
 * @returns {JSX.Element}
 */
export function LotFormModal() {
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const isEdit = Boolean(id)
  const [form] = Form.useForm<FormValues>()
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const { generalMessage, getError } = useFormErrors(error)

  // Cargar lote existente (si es edit) y datos del form (products + bodegas)
  const lotPath = isEdit ? `/api/lots/${id}` : null
  const { data: lotData, isLoading: lotLoading } = useApi<LotResponse>(lotPath, [id])
  const { data: formData, isLoading: formDataLoading } = useApi<FormDataResponse>(
    '/api/lots/form-data',
  )

  /**
   * Hook para cargar los datos del lote
   * @param lotData - Datos del lote
   * @param formData - Datos del formulario
   * @param form - Formulario
   */
  useEffect(() => {
    if (lotData?.data && formData) {
      form.setFieldsValue({
        name: lotData.data.name,
        product_id: lotData.data.product_id,
        warehouse_id: lotData.data.warehouse_id,
        stock: lotData.data.stock,
        expiration_date: lotData.data.expiration_date
          ? dayjs(lotData.data.expiration_date)
          : null,
        description: lotData.data.description ?? '',
      })
    }
  }, [lotData, formData, form])

  /**
   * Hook useEffect para sincronizar el Select cuando formData llega después de lotData.
   * @param formData - Datos del formulario
   * @param lotData - Datos del lote
   * @param form - Formulario
   */
  useEffect(() => {
    if (lotData?.data && formData) {
      const t = setTimeout(() => {
        form.setFieldsValue({
          product_id: lotData.data.product_id,
          warehouse_id: lotData.data.warehouse_id,
        })
      }, 50)
      return () => clearTimeout(t)
    }
  }, [formData, lotData, form])

  /**
   * Event handler para el envío del formulario.
   * @param values - Valores del formulario
   */
  const handleSubmit = async (values: FormValues) => {
    setSubmitting(true)
    setError(null)
    try {
      // Definir el payload
      const payload: LotPayload = {
        name: values.name.trim(),
        product_id: values.product_id,
        warehouse_id: values.warehouse_id,
        stock: values.stock,
        expiration_date: values.expiration_date.format('YYYY-MM-DD'),
        description: values.description?.trim() || null,
      }

      // Verificar si es edición o creación
      if (isEdit && id) {
        // Actualizar lote
        await apiFetch(`/api/lots/${id}`, { method: 'PUT', body: payload })
        message.success('Lote actualizado')
      } else {
        // Crear lote
        await apiFetch('/api/lots', { method: 'POST', body: payload })
        message.success('Lote creado')
        form.resetFields()
      }

      // Navegar a la lista de lotes
      setTimeout(() => navigate('/lots'), 1500)
    } catch (err) {
      // Manejar errores
      setError(err)
    } finally {
      // Finalizar el envío del formulario
      setSubmitting(false)
    }
  }

  // Cargar datos del formulario y lote
  if ((isEdit && lotLoading) || formDataLoading) {
    return <LoadingScreen />
  }

  // Obtener los datos del formulario
  const products = formData?.products ?? []
  const bodegas = formData?.bodegas ?? []

  /**
   * Renderizar el formulario
   */
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
          warehouse_id: undefined,
          stock: 0,
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
          label="Bodega"
          name="warehouse_id"
          validateStatus={getError('warehouse_id') ? 'error' : undefined}
          help={getError('warehouse_id')}
          rules={[
            { required: true, message: 'Debe seleccionar una bodega' },
          ]}
        >
          <Select
            key={`warehouse-${formData ? 'loaded' : 'loading'}`}
            placeholder="Seleccione una bodega"
            options={bodegas.map((b) => ({ value: b.id, label: b.name }))}
            showSearch
            optionFilterProp="label"
          />
        </Form.Item>

        <Form.Item
          label="Stock"
          name="stock"
          validateStatus={getError('stock') ? 'error' : undefined}
          help={getError('stock')}
          rules={[
            { required: true, message: 'El stock es requerido' },
            { type: 'number', min: 0, message: 'El stock debe ser mayor o igual a 0' },
          ]}
        >
          <InputNumber style={{ width: '100%' }} min={0} />
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
