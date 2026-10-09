/**
 * ProductFormModal — Form crear/editar productos.
 * Se renderiza dentro de AppLayout.
 */

import { useNavigate, useParams } from 'react-router-dom'
import { useApi } from '../../hooks/useApi'
import { useFormErrors } from '../../hooks/useFormErrors'
import { apiFetch } from '../../lib/api'
import { ErrorAlert } from '../../components/ErrorAlert'
import { LoadingScreen } from '../../components/LoadingScreen'
import { useEffect, useState } from 'react'
import { Button, Form, Input, Select, message } from 'antd'
import type { FormDataResponse, ProductPayload, ProductResponse } from '../../lib/products'


/**
 * Interface que define los valores del formulario de productos
 */
interface FormValues {
  name: string
  cum: string
  barcode?: string
  invima_registration: string
  group_id: string
  unit_id: string
  description?: string
}

const NAME_REGEX = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-()°.]+$/u

/**
 * Formulario para crear o editar productos
 * @returns {JSX.Element}
 */
export function ProductFormModal() {
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const isEdit = Boolean(id)
  const [form] = Form.useForm<FormValues>()
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const { generalMessage, getError } = useFormErrors(error)

  /**
   * Path de la API para obtener los datos del producto si existe el ID.
   * @param isEdit - Indica si se está editando un producto
   * @param id - ID del producto
   */
  const productPath = isEdit ? `/api/products/${id}` : null
  const { data: productData, isLoading: productLoading } = useApi<ProductResponse>(productPath, [id])
  const { data: formData, isLoading: formDataLoading } = useApi<FormDataResponse>(
    '/api/products/form-data',
  )

  /**
   * Effecto para cargar los datos del producto si existe el ID.
   * @param productData - Datos del producto
   * @param formData - Datos del formulario (groups, units)
   * @param form - Formulario de Ant Design
   */
  useEffect(() => {
    if (productData?.data && formData) {
      form.setFieldsValue({
        name: productData.data.name,
        cum: productData.data.cum,
        barcode: productData.data.barcode ?? '',
        invima_registration: productData.data.invima_registration,
        group_id: productData.data.group?.id ?? '',
        unit_id: productData.data.unit?.id ?? '',
        description: productData.data.description ?? '',
      })
    }
  }, [productData, formData, form])

  /**
   * Hook para forzar un setFieldsValue para que el Select se sincronice con los datos del producto.
   */
  useEffect(() => {
    if (productData?.data && formData) {
      const t = setTimeout(() => {
        form.setFieldsValue({
          group_id: productData.data.group?.id ?? '',
          unit_id: productData.data.unit?.id ?? '',
        })
      }, 50)
      return () => clearTimeout(t)
    }
  }, [formData, productData, form])

  /**
   * Event handler para el envío del formulario.
   * @param values - Valores del formulario
   */
  const handleSubmit = async (values: FormValues) => {
    setSubmitting(true)
    setError(null)
    try {
      const payload: ProductPayload = {
        name: values.name.trim(),
        cum: values.cum.trim(),
        barcode: values.barcode?.trim() ? values.barcode.trim() : null,
        invima_registration: values.invima_registration.trim(),
        group_id: values.group_id,
        unit_id: values.unit_id,
        description: values.description?.trim() || null,
      }
      if (isEdit && id) {
        await apiFetch(`/api/products/${id}`, { method: 'PUT', body: payload })
        message.success('Producto actualizado')
      } else {
        await apiFetch('/api/products', { method: 'POST', body: payload })
        message.success('Producto creado')
        form.resetFields()
      }
      setTimeout(() => navigate('/products'), 1500)
    } catch (err) {
      setError(err)
    } finally {
      setSubmitting(false)
    }
  }

  /**
   * Renderiza la pantalla de carga si el producto o los datos del formulario están cargando.
   * @param isEdit - Indica si se está editando un producto
   * @param productLoading - Indica si el producto está cargando
   * @param formDataLoading - Indica si los datos del formulario están cargando
   * @returns {JSX.Element}
   */
  if ((isEdit && productLoading) || formDataLoading) {
    return <LoadingScreen />
  }

  /**
   * Grupos y unidades para el formulario.
   * @param formData - Datos del formulario
   */
  const groups = formData?.groups ?? []
  const units = formData?.units ?? []

  /**
   * Renderiza el formulario para crear o editar un producto.
   * @returns {JSX.Element}
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
        {isEdit ? 'Editar Producto' : 'Nuevo Producto'}
      </h1>

      <ErrorAlert message={generalMessage} />

      <Form<FormValues>
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{
          name: '',
          cum: '',
          barcode: '',
          invima_registration: '',
          group_id: undefined,
          unit_id: undefined,
          description: '',
        }}
      >
        <Form.Item
          label="Nombre"
          name="name"
          validateStatus={getError('name') ? 'error' : undefined}
          help={getError('name')}
          rules={[
            { required: true, message: 'El campo nombre es obligatorio' },
            { max: 120, message: 'Máximo 120 caracteres' },
            {
              pattern: NAME_REGEX,
              message:
                'Solo letras, números, espacios, guiones, paréntesis, ° y puntos',
            },
          ]}
        >
          <Input placeholder="Paracetamol 500mg – Tableta" autoFocus />
        </Form.Item>

        <Form.Item
          label="CUM"
          name="cum"
          validateStatus={getError('cum') ? 'error' : undefined}
          help={getError('cum')}
          rules={[
            { required: true, message: 'El campo CUM es obligatorio' },
            { max: 60, message: 'Máximo 60 caracteres' },
          ]}
        >
          <Input placeholder="19927641-1" />
        </Form.Item>

        <Form.Item
          label="Código de Barras"
          name="barcode"
          validateStatus={getError('barcode') ? 'error' : undefined}
          help={getError('barcode')}
          rules={[{ max: 60, message: 'Máximo 60 caracteres' }]}
        >
          <Input placeholder="7701234567890 (opcional)" />
        </Form.Item>

        <Form.Item
          label="Registro Invima"
          name="invima_registration"
          validateStatus={getError('invima_registration') ? 'error' : undefined}
          help={getError('invima_registration')}
          rules={[
            { required: true, message: 'El campo registro Invima es obligatorio' },
            { max: 60, message: 'Máximo 60 caracteres' },
          ]}
        >
          <Input placeholder="INVIMA 2020M-0019854" />
        </Form.Item>

        <Form.Item
          label="Grupo"
          name="group_id"
          validateStatus={getError('group_id') ? 'error' : undefined}
          help={getError('group_id')}
          rules={[
            { required: true, message: 'Debe seleccionar un grupo' },
          ]}
        >
          <Select
            key={`group-${formData ? 'loaded' : 'loading'}`}
            placeholder="Seleccione un grupo"
            options={groups.map((g) => ({ value: g.id, label: g.name }))}
            showSearch
            filterOption={(input, option) =>
              (option?.label ?? '').toString().toLowerCase().includes(input.toLowerCase())
            }

          />
        </Form.Item>

        <Form.Item
          label="Unidad de Medida"
          name="unit_id"
          validateStatus={getError('unit_id') ? 'error' : undefined}
          help={getError('unit_id')}
          rules={[
            { required: true, message: 'Debe seleccionar una unidad de medida' },
          ]}
        >
          <Select
            key={`unit-${formData ? 'loaded' : 'loading'}`}
            placeholder="Seleccione una unidad"
            options={units.map((u) => ({
              value: u.id,
              label: u.abbreviation ? `${u.name} (${u.abbreviation})` : u.name,
            }))}
            showSearch
            filterOption={(input, option) =>
              (option?.label ?? '').toLowerCase().includes(input.toLowerCase())
            }
          />
        </Form.Item>

        <Form.Item
          label="Descripción"
          name="description"
          validateStatus={getError('description') ? 'error' : undefined}
          help={getError('description')}
          rules={[{ max: 300, message: 'Máximo 300 caracteres' }]}
        >
          <Input.TextArea rows={3} placeholder="Descripción del producto (opcional)" />
        </Form.Item>

        <Form.Item style={{ marginBottom: 0, marginTop: 24 }}>
          <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
            <Button onClick={() => navigate('/products')}>Cancelar</Button>
            <Button type="primary" htmlType="submit" loading={submitting}>
              {isEdit ? 'Guardar cambios' : 'Crear producto'}
            </Button>
          </div>
        </Form.Item>
      </Form>
    </>
  )
}
