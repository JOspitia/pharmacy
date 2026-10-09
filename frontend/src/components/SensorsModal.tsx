/**
 * SensorsModal — Modal de gestión de sensores de una bodega específica.
 *
 */

import { useState } from 'react'
import { Button, Form, Input, Modal, Popconfirm, Space, Table, Tag, message, } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { EditOutlined, DeleteOutlined, ReloadOutlined } from '@ant-design/icons'
import { useApi } from '../hooks/useApi'
import { apiFetch } from '../lib/api'
import type { Sensor, SensorListResponse, SensorPayload } from '../lib/sensors'

const NAME_REGEX = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-()°.]+$/u

/**
 * Props para el componente SensorsModal
 */
interface SensorsModalProps {
  open: boolean
  onClose: () => void
  warehouseId: string
  warehouseName: string
}

/**
 * Valores del formulario de sensores
 */
interface FormValues {
  name: string
  description?: string
}

/**
 * Componente de modal de gestión de sensores de una bodega específica.
 * @param open - Boolean que indica si el modal está abierto
 * @param onClose - Función que se ejecuta cuando se cierra el modal
 * @param warehouseId - ID de la bodega
 * @param warehouseName - Nombre de la bodega
 * @returns JSX.Element
 */
export function SensorsModal({ open, onClose, warehouseId, warehouseName }: SensorsModalProps) {
  const [form] = Form.useForm<FormValues>()
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const [editingId, setEditingId] = useState<string | null>(null)
  const [showForm, setShowForm] = useState(false)

  /**
   * Hook para cargar sensores de la bodega
   */
  const { data, error: listError, isLoading, refetch } = useApi<SensorListResponse>(
    open ? `/api/sensors?warehouse_id=${warehouseId}` : null,
    [warehouseId],
  )

  const sensors = data?.data ?? []

  /**
   * Maneja el cambio de estado del sensor
   * @param sensor - Sensor a cambiar de estado
   */
  const handleToggleStatus = async (sensor: Sensor) => {
    try {
      await apiFetch(`/api/sensors/${sensor.id}/toggle-status`, { method: 'PATCH' })
      message.success(`Sensor ${sensor.status ? 'inactivado' : 'activado'}`)
      refetch()
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'No se pudo cambiar el estado'
      message.error(msg)
    }
  }

  /**
   * Maneja la eliminación del sensor
   * @param sensor - Sensor a eliminar
   */
  const handleDelete = async (sensor: Sensor) => {
    try {
      await apiFetch(`/api/sensors/${sensor.id}`, { method: 'DELETE' })
      message.success(`Sensor "${sensor.name}" eliminado`)
      refetch()
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'No se pudo eliminar el sensor'
      message.error(msg)
    }
  }

  /**
   * Maneja el envío del formulario
   * @param values - Valores del formulario
   */
  const handleSubmit = async (values: FormValues) => {
    setSubmitting(true)
    setError(null)
    try {
      // Construye el payload para crear o actualizar el sensor
      const payload: SensorPayload = {
        name: values.name.trim(),
        description: values.description?.trim() || null,
        warehouse_id: warehouseId,
      }
      if (editingId) {
        // Actualiza el sensor
        await apiFetch(`/api/sensors/${editingId}`, { method: 'PUT', body: payload })
        message.success('Sensor actualizado')
      } else {
        // Crea el sensor
        await apiFetch('/api/sensors', { method: 'POST', body: payload })
        message.success('Sensor creado')
      }
      // Cierra el formulario y resetea los campos
      setShowForm(false)
      setEditingId(null)
      form.resetFields()
      // Recarga la lista de sensores
      refetch()
    } catch (err) {
      setError(err)
    } finally {
      setSubmitting(false)
    }
  }

  /**
   * Maneja la edición del sensor
   * @param sensor - Sensor a editar
   */
  const handleEdit = (sensor: Sensor) => {
    setEditingId(sensor.id)
    setShowForm(true)
    form.setFieldsValue({
      name: sensor.name,
      description: sensor.description ?? '',
    })
  }

  /**
   * Maneja la cancelación del formulario
   */
  const handleCancelForm = () => {
    setShowForm(false)
    setEditingId(null)
    form.resetFields()
    setError(null)
  }

  /**
   * Columnas de la tabla de sensores
   */
  const columns: ColumnsType<Sensor> = [
    {
      title: 'Nombre',
      dataIndex: 'name',
      key: 'name',
      render: (name: string) => (
        <strong style={{ fontFamily: "'Space Grotesk', sans-serif" }}>{name}</strong>
      ),
    },
    {
      title: 'Descripción',
      dataIndex: 'description',
      key: 'description',
      render: (desc: string | null) =>
        desc ?? <span style={{ color: '#94a3b8' }}>—</span>,
    },
    {
      title: 'Fecha Registro',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 130,
      render: (date: string) =>
        date
          ? new Date(date).toLocaleDateString('es-CO', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
          })
          : '—',
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      width: 100,
      render: (status: boolean, record: Sensor) => (
        <Popconfirm
          title={status ? '¿Inactivar este sensor?' : '¿Activar este sensor?'}
          onConfirm={() => handleToggleStatus(record)}
          okText="Sí"
          cancelText="No"
        >
          <Tag
            color={status ? 'green' : 'default'}
            style={{ cursor: 'pointer', userSelect: 'none' }}
          >
            {status ? '● Activo' : '○ Inactivo'}
          </Tag>
        </Popconfirm>
      ),
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 100,
      render: (_, record: Sensor) => (
        <Space>
          <Button
            type="text"
            icon={<EditOutlined />}
            onClick={() => handleEdit(record)}
          />
          <Popconfirm
            title="¿Eliminar este sensor?"
            description="Se marcará como inactivo. Las lecturas históricas se preservan."
            onConfirm={() => handleDelete(record)}
            okText="Eliminar"
            cancelText="Cancelar"
            okButtonProps={{ danger: true }}
          >
            <Button type="text" danger icon={<DeleteOutlined />} />
          </Popconfirm>
        </Space>
      ),
    },
  ]

  /**
   * Renderiza el modal de sensores
   */
  return (
    <Modal
      open={open}
      onCancel={onClose}
      footer={null}
      width={900}
      title={
        <span style={{ fontFamily: "'Space Grotesk', sans-serif", color: '#0e7490' }}>
          Sensores de {warehouseName}
        </span>
      }
    >
      {showForm && (
        <div
          style={{
            background: '#f0f9ff',
            border: '1px solid #bae6fd',
            borderRadius: 8,
            padding: 16,
            marginBottom: 16,
          }}
        >
          <h3 style={{ margin: '0 0 12px', fontSize: 16, color: '#0e7490' }}>
            {editingId ? 'Editar sensor' : 'Nuevo sensor'}
          </h3>
          <Form<FormValues>
            form={form}
            layout="vertical"
            onFinish={handleSubmit}
          >
            <Form.Item
              label="Nombre"
              name="name"
              rules={[
                { required: true, message: 'El nombre es requerido' },
                { max: 60, message: 'Máximo 60 caracteres' },
                { pattern: NAME_REGEX, message: 'Solo letras, números, espacios, guiones, paréntesis, ° y puntos' },
              ]}
            >
              <Input placeholder="Sensor de Temperatura" autoFocus />
            </Form.Item>
            <Form.Item
              label="Descripción"
              name="description"
              rules={[{ max: 300, message: 'Máximo 300 caracteres' }]}
            >
              <Input.TextArea rows={2} placeholder="Descripción (opcional)" />
            </Form.Item>
            <Form.Item style={{ marginBottom: 0 }}>
              <Space>
                <Button type="primary" htmlType="submit" loading={submitting}>
                  {editingId ? 'Guardar' : 'Crear'}
                </Button>
                <Button onClick={handleCancelForm}>Cancelar</Button>
                {error !== null && (
                  <span style={{ color: '#dc2626', fontSize: 12 }}>
                    {error instanceof Error ? error.message : 'Error'}
                  </span>
                )}
              </Space>
            </Form.Item>
          </Form>
        </div>
      )}

      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          marginBottom: 16,
        }}
      >
        <h3 style={{ margin: 0, fontSize: 16, color: '#64748b' }}>
          {sensors.length} sensor{sensors.length !== 1 ? 'es' : ''}
        </h3>
        <Space>
          <Button icon={<ReloadOutlined />} onClick={refetch} size="small">
            Recargar
          </Button>
          <Button
            type="primary"
            size="small"
            onClick={() => {
              setShowForm(true)
              setEditingId(null)
              form.resetFields()
            }}
          >
            + Nuevo Sensor
          </Button>
        </Space>
      </div>

      <Table<Sensor>
        rowKey="id"
        columns={columns}
        dataSource={sensors}
        loading={isLoading}
        rowClassName={() => listError ? 'ant-table-row-selected' : ''}
        pagination={false}
        locale={{ emptyText: 'No hay sensores registrados en esta bodega' }}
        size="small"
      />
    </Modal>
  )
}
