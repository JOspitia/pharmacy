/**
 * WarehouseFormModal — Form crear/editar grupos.
 * Se renderiza dentro de AppLayout.
 */

import { useEffect, useState } from 'react'
import { Button, Form, Input, Switch, message } from 'antd'
import { useNavigate, useParams } from 'react-router-dom'
import { useApi } from '../../hooks/useApi'
import { useFormErrors } from '../../hooks/useFormErrors'
import { apiFetch } from '../../lib/api'
import { ErrorAlert } from '../../components/ErrorAlert'
import { LoadingScreen } from '../../components/LoadingScreen'
import type { WarehousePayload, WarehouseResponse } from '../../lib/warehouses'

interface FormValues {
  name: string
  description?: string
  status: boolean
}

/**
 * Componente de formulario para crear o editar almacenes.
 * 
 * @returns {JSX.Element}
 */
export function WarehouseFormModal() {
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const isEdit = Boolean(id)
  const [form] = Form.useForm<FormValues>()
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const { generalMessage, getError } = useFormErrors(error)

  const warehousePath = isEdit ? `/api/bodegas/${id}` : null
  const { data: warehouseData, isLoading } = useApi<WarehouseResponse>(warehousePath, [id])

  /**
   * Effecto para cargar los datos de la bodega si existe el ID.
   * @param warehouseData - Datos de la bodega
   * @param form - Formulario de Ant Design
   */
  useEffect(() => {
    if (warehouseData?.data) {
      form.setFieldsValue({
        name: warehouseData.data.name,
        description: warehouseData.data.description ?? '',
        status: warehouseData.data.status,
      })
    }
  }, [warehouseData, form])

  /**
   * Event handler para el envío del formulario.
   * @param values - Valores del formulario
   */
  const handleSubmit = async (values: FormValues) => {
    setSubmitting(true)
    setError(null)
    try {
      /**
       * Payload para el formulario de bodega
       */
      const payload: WarehousePayload = {
        name: values.name.trim(),
        description: values.description?.trim() || null,
        status: values.status,
      }

      /**
       * Si se está editando una bodega, se actualiza con PUT, si no, se crea con POST.
       * @param isEdit - Indica si se está editando una bodega
       * @param id - ID de la bodega
       * @param payload - Payload del formulario de bodega
       * @param message - Mensaje de Ant Design
       */
      if (isEdit && id) {
        await apiFetch(`/api/bodegas/${id}`, { method: 'PUT', body: payload })
        message.success('Bodega actualizada')
      } else {
        await apiFetch('/api/bodegas', { method: 'POST', body: payload })
        message.success('Bodega creada')
        form.resetFields()
      }
      /**
       * Navega a la lista de bodegas después de un breve delay.
       * El delay permite que el usuario vea el toast de éxito.
       * @param navigate - Función de navegación de React Router
       */
      setTimeout(() => navigate('/bodegas'), 1500)
    } catch (err) {
      setError(err)
    } finally {
      setSubmitting(false)
    }
  }

  /**
   * Renderiza la pantalla de carga si el almacén está cargando.
   * @param isEdit - Indica si se está editando un almacén
   * @param isLoading - Indica si el almacén está cargando
   * @returns {JSX.Element}
   */
  if (isEdit && isLoading) return <LoadingScreen />

  /**
   * Renderiza el formulario para crear o editar un almacén.
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
        {isEdit ? 'Editar Bodega' : 'Nueva Bodega'}
      </h1>

      <ErrorAlert message={generalMessage} />

      <Form<FormValues>
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{ name: '', description: '', status: true }}
      >
        <Form.Item
          label="Nombre"
          name="name"
          validateStatus={getError('name') ? 'error' : undefined}
          help={getError('name')}
          rules={[
            { required: true, message: 'El nombre es requerido' },
            { max: 60, message: 'Máximo 60 caracteres' },
            {
              pattern: /^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-()°]+$/u,
              message: 'Solo letras, números, espacios, guiones, paréntesis y °',
            },
          ]}
        >
          <Input placeholder="Bodega Farmacia" autoFocus />
        </Form.Item>

        <Form.Item
          label="Descripción"
          name="description"
          validateStatus={getError('description') ? 'error' : undefined}
          help={getError('description')}
          rules={[{ max: 300, message: 'Máximo 300 caracteres' }]}
        >
          <Input.TextArea rows={3} placeholder="Medicamentos para aliviar el dolor" />
        </Form.Item>

        {!isEdit && (
          <Form.Item label="Estado" name="status" valuePropName="checked">
            <Switch checkedChildren="Activo" unCheckedChildren="Inactivo" />
          </Form.Item>
        )}

        <Form.Item style={{ marginBottom: 0, marginTop: 24 }}>
          <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
            <Button onClick={() => navigate('/warehouses')}>Cancelar</Button>
            <Button type="primary" htmlType="submit" loading={submitting}>
              {isEdit ? 'Guardar cambios' : 'Crear bodega'}
            </Button>
          </div>
        </Form.Item>
      </Form>
    </>
  )
}
