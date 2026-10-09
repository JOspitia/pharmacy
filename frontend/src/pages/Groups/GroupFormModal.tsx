/**
 * GroupFormModal — Form crear/editar grupos.
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
import type { GroupPayload, GroupResponse } from '../../lib/groups'

interface FormValues {
  name: string
  description?: string
  status: boolean
}

/**
 * Componente de formulario para crear o editar grupos.
 * 
 * @returns {JSX.Element}
 */
export function GroupFormModal() {
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const isEdit = Boolean(id)
  const [form] = Form.useForm<FormValues>()
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const { generalMessage, getError } = useFormErrors(error)

  const groupPath = isEdit ? `/api/groups/${id}` : null
  const { data: groupData, isLoading } = useApi<GroupResponse>(groupPath, [id])

  /**
   * Effecto para cargar los datos del grupo si existe el ID.
   * @param groupData - Datos del grupo
   * @param form - Formulario de Ant Design
   */
  useEffect(() => {
    if (groupData?.data) {
      form.setFieldsValue({
        name: groupData.data.name,
        description: groupData.data.description ?? '',
        status: groupData.data.status,
      })
    }
  }, [groupData, form])

  /**
   * Event handler para el envío del formulario.
   * @param values - Valores del formulario
   */
  const handleSubmit = async (values: FormValues) => {
    setSubmitting(true)
    setError(null)
    try {
      /**
       * Payload para el formulario de grupo
       */
      const payload: GroupPayload = {
        name: values.name.trim(),
        description: values.description?.trim() || null,
        status: values.status,
      }

      /**
       * Si se está editando un grupo, se actualiza con PUT, si no, se crea con POST.
       * @param isEdit - Indica si se está editando un grupo
       * @param id - ID del grupo
       * @param payload - Payload del formulario de grupo
       * @param message - Mensaje de Ant Design
       */
      if (isEdit && id) {
        await apiFetch(`/api/groups/${id}`, { method: 'PUT', body: payload })
        message.success('Grupo actualizado')
      } else {
        await apiFetch('/api/groups', { method: 'POST', body: payload })
        message.success('Grupo creado')
        form.resetFields()
      }
      /**
       * Navega a la lista de grupos después de un breve delay.
       * El delay permite que el usuario vea el toast de éxito.
       * @param navigate - Función de navegación de React Router
       */
      setTimeout(() => navigate('/groups'), 1500)
    } catch (err) {
      setError(err)
    } finally {
      setSubmitting(false)
    }
  }

  /**
   * Renderiza la pantalla de carga si el grupo está cargando.
   * @param isEdit - Indica si se está editando un grupo
   * @param isLoading - Indica si el grupo está cargando
   * @returns {JSX.Element}
   */
  if (isEdit && isLoading) return <LoadingScreen />

  /**
   * Renderiza el formulario para crear o editar un grupo.
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
        {isEdit ? 'Editar Grupo' : 'Nuevo Grupo'}
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
          <Input placeholder="Analgésicos" autoFocus />
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
            <Button onClick={() => navigate('/groups')}>Cancelar</Button>
            <Button type="primary" htmlType="submit" loading={submitting}>
              {isEdit ? 'Guardar cambios' : 'Crear grupo'}
            </Button>
          </div>
        </Form.Item>
      </Form>
    </>
  )
}
