/**
 * GroupsList — Tabla de grupos con paginación, búsqueda, toggle status y delete.
 * Se renderiza dentro de AppLayout
 */

import { useMemo, useState } from 'react'
import { Button, Input, Space, Table, Tag, Popconfirm, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { EditOutlined, DeleteOutlined, ReloadOutlined } from '@ant-design/icons'
import { useNavigate } from 'react-router-dom'
import { useApi } from '../../hooks/useApi'
import { apiFetch } from '../../lib/api'
import { ErrorAlert } from '../../components/ErrorAlert'
import { LoadingScreen } from '../../components/LoadingScreen'
import type { Group, GroupsListResponse } from '../../lib/groups'

/**
 * Componente de lista de grupos con paginación, búsqueda y acciones.
 * Se renderiza dentro de AppLayout.
 * @returns {JSX.Element}
 */
export function GroupsList() {
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  /**
   * Efecto para debouncing de la búsqueda.
   * @param search - Término de búsqueda
   */
  useMemo(() => {
    const t = setTimeout(() => setDebouncedSearch(search), 300)
    return () => clearTimeout(t)
  }, [search])

  /**
   * Construye la consulta para obtener los grupos.
   * @param page - Página actual
   * @param debouncedSearch - Término de búsqueda debounced
   * @returns {string} - Consulta para obtener los grupos
   */
  const query = `/api/groups?page=${page}${debouncedSearch ? `&name=${encodeURIComponent(debouncedSearch)}` : ''}`
  const { data, error, isLoading, refetch } = useApi<GroupsListResponse>(query, [page, debouncedSearch])

  /**
   * Maneja el cambio de estado del grupo.
   * @param group - Grupo a cambiar de estado
   */
  const handleToggleStatus = async (group: Group) => {
    try {
      await apiFetch(`/api/groups/${group.id}/toggle-status`, { method: 'PATCH' })
      message.success(`Grupo ${group.status ? 'inactivado' : 'activado'}`)
      refetch()
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'No se pudo cambiar el estado'
      message.error(msg)
    }
  }

  /**
   * Maneja la eliminación del grupo.
   * @param group - Grupo a eliminar
   */
  const handleDelete = async (group: Group) => {
    try {
      await apiFetch(`/api/groups/${group.id}`, { method: 'DELETE' })
      message.success(`Grupo "${group.name}" eliminado`)
      refetch()
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'No se pudo eliminar el grupo'
      message.error(msg)
    }
  }

  if (isLoading && !data) return <LoadingScreen />

  const groups = data?.data ?? []
  const meta = data?.meta

  /**
   * Columnas de la tabla.
   * @returns {ColumnsType<Group>} - Columnas de la tabla
   */
  const columns: ColumnsType<Group> = [
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
      width: 180,
      render: (date: string) =>
        new Date(date).toLocaleDateString('es-CO', {
          year: 'numeric',
          month: '2-digit',
          day: '2-digit',
        }),
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      width: 120,
      render: (status: boolean, record: Group) => (
        <Popconfirm
          title={status ? '¿Inactivar este grupo?' : '¿Activar este grupo?'}
          onConfirm={() => handleToggleStatus(record)}
          okText="Sí"
          cancelText="No"
        >
          <Tag
            color={status ? 'cyan' : 'default'}
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
      width: 160,
      render: (_: unknown, record: Group) => (
        <Space>
          <Button
            type="text"
            icon={<EditOutlined />}
            onClick={() => navigate(`/groups/${record.id}/edit`)}
          />
          <Popconfirm
            title="¿Eliminar este grupo?"
            description="Se marcará como inactivo. Si tiene productos activos, se bloqueará."
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
   * Renderiza la lista de grupos.
   * @returns {JSX.Element}
   */
  return (
    <>
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          marginBottom: 24,
        }}
      >
        <h1
          style={{
            fontFamily: "'Space Grotesk', sans-serif",
            fontSize: 28,
            fontWeight: 700,
            color: '#0e7490',
            margin: 0,
          }}
        >
          Grupos
        </h1>
        <Space>
          <Button icon={<ReloadOutlined />} onClick={refetch}>
            Recargar
          </Button>
          <Button type="primary" onClick={() => navigate('/groups/new')}>
            + Nuevo Grupo
          </Button>
        </Space>
      </div>

      <ErrorAlert message={error?.message ?? null} />

      <div style={{ marginBottom: 16 }}>
        <Input.Search
          placeholder="Buscar por nombre..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onSearch={(v) => setSearch(v)}
          allowClear
          style={{ maxWidth: 320 }}
        />
      </div>

      <Table<Group>
        rowKey="id"
        columns={columns}
        dataSource={groups}
        loading={isLoading}
        pagination={{
          current: page,
          total: meta?.total ?? 0,
          pageSize: meta?.per_page ?? 15,
          onChange: setPage,
          showSizeChanger: false,
        }}
        locale={{ emptyText: 'No hay grupos registrados' }}
      />
    </>
  )
}
