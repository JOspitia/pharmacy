/**
 * ReporteInventario - Pantalla de reporte de inventario de productos.
 */

import { useState } from 'react'
import { Button, Col, DatePicker, Form, Input, Row, Select, Space, Table, Tag } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { ReloadOutlined, SearchOutlined, ClearOutlined } from '@ant-design/icons'
import { useApi } from '../../hooks/useApi'
import { ErrorAlert } from '../../components/ErrorAlert'
import { LoadingScreen } from '../../components/LoadingScreen'
import type { InventarioRow, InventarioResponse, InventarioFiltros } from '../../lib/reports'
import type { FormDataResponse as ProductFormData } from '../../lib/products'
import type { FormDataResponse as SensorFormData } from '../../lib/sensors'
import { Dayjs } from 'dayjs'

/**
 * Devuelve el color según el campo alerta_color del backend
 * @param color - Color del alerta
 * @returns {string} - Color de Ant Design
 */
function mapAlertColor(color: string): string {
  switch (color) {
    case 'verde': return 'green'
    case 'amarillo': return 'orange'
    case 'rojo': return 'red'
    default: return 'default'
  }
}

/**
 * Construye la query string a partir de los filtros activos
 * @param filtros - Filtros del reporte
 * @param page - Página actual
 * @returns {string} - Query string
 */
function buildQuery(filtros: InventarioFiltros, page: number): string {
  const params = new URLSearchParams()
  params.set('page', String(page))
  if (filtros.cum) params.set('cum', filtros.cum)
  if (filtros.name) params.set('name', filtros.name)
  if (filtros.bodega_id) params.set('bodega_id', filtros.bodega_id)
  if (filtros.group_id) params.set('group_id', filtros.group_id)
  if (filtros.lot_code) params.set('lot_code', filtros.lot_code)
  if (filtros.estado) params.set('estado', filtros.estado)
  if (filtros.date_from) params.set('date_from', filtros.date_from)
  if (filtros.date_to) params.set('date_to', filtros.date_to)
  return `/api/reportes/inventario?${params.toString()}`
}

/**
 * Pantalla principal del reporte de inventario.
 * @returns {JSX.Element}
 */
export function ReportProducts() {
  const [form] = Form.useForm()
  const [page, setPage] = useState(1)
  const [filtros, setFiltros] = useState<InventarioFiltros>({})

  // Cargar datos para selects de bodega y grupo
  const { data: productFormData } = useApi<ProductFormData>('/api/products/form-data', [])
  const { data: sensorFormData } = useApi<SensorFormData>('/api/sensors/form-data', [])

  const bodegas = sensorFormData?.bodegas ?? []
  const grupos = productFormData?.groups ?? []

  // Consulta principal del reporte
  const query = buildQuery(filtros, page)
  const { data, error, isLoading, refetch } = useApi<InventarioResponse>(query, [query])

  const rows = data?.data ?? []
  const meta = data?.meta

  /**
   * Aplica los filtros del formulario
   * @param values - Valores del formulario
   */
  const handleSearch = (values: Record<string, unknown>) => {
    setPage(1)
    setFiltros({
      cum: (values.cum as string) || undefined,
      name: (values.name as string) || undefined,
      bodega_id: (values.bodega_id as string) || undefined,
      group_id: (values.group_id as string) || undefined,
      lot_code: (values.lot_code as string) || undefined,
      estado: (values.estado as InventarioFiltros['estado']) || undefined,

      /**
       * Convierte el rango de fechas en un string con formato YYYY-MM-DD
       */
      date_from: values.date_range
        ? (values.date_range as [Dayjs, Dayjs])[0]?.format('YYYY-MM-DD')
        : undefined,
      date_to: values.date_range
        ? (values.date_range as [Dayjs, Dayjs])[1]?.format('YYYY-MM-DD')
        : undefined,
    })
  }

  /** Limpia todos los filtros */
  const handleReset = () => {
    form.resetFields()
    setPage(1)
    setFiltros({})
  }

  /**
   * Columnas de la tabla.
   * @returns {ColumnsType<InventarioRow>} - Columnas de la tabla
   */
  const columns: ColumnsType<InventarioRow> = [
    {
      title: 'Producto',
      dataIndex: 'product_name',
      key: 'product_name',
      width: 220,
      render: (name: string) => (
        <strong style={{ fontFamily: "'Space Grotesk', sans-serif" }}>{name}</strong>
      ),
    },
    {
      title: 'CUM',
      dataIndex: 'cum',
      key: 'cum',
      width: 130,
    },
    {
      title: 'Grupo',
      dataIndex: 'group_name',
      key: 'group_name',
      width: 140,
      render: (v: string | null) => v ?? <span style={{ color: '#94a3b8' }}>—</span>,
    },
    {
      title: 'Lote',
      dataIndex: 'lot_code',
      key: 'lot_code',
      width: 130,
    },
    {
      title: 'Bodega',
      dataIndex: 'bodega_name',
      key: 'bodega_name',
      width: 180,
    },
    {
      title: 'Stock',
      dataIndex: 'stock',
      key: 'stock',
      width: 80,
      align: 'right',
    },
    {
      title: 'Vencimiento',
      dataIndex: 'expiration_date',
      key: 'expiration_date',
      width: 120,
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
      dataIndex: 'alerta_exp',
      key: 'alerta_exp',
      width: 180,
      render: (label: string, record: InventarioRow) => (
        <Tag color={mapAlertColor(record.alerta_color)}>{label}</Tag>
      ),
    },
  ]

  /**
   * Renderiza el componente.
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
          Reporte de Inventario
        </h1>
        <Button icon={<ReloadOutlined />} onClick={refetch}>
          Recargar
        </Button>
      </div>

      <div
        style={{
          background: '#f0f9ff',
          border: '1px solid #bae6fd',
          borderRadius: 8,
          padding: '16px 20px',
          marginBottom: 24,
        }}
      >
        <h3 style={{ margin: '0 0 16px', fontSize: 16, color: '#0e7490' }}>
          Filtros de búsqueda
        </h3>
        <Form form={form} layout="vertical" onFinish={handleSearch}>
          <Row gutter={16}>
            <Col xs={24} sm={12} md={6}>
              <Form.Item label="Nombre producto" name="name">
                <Input placeholder="Acetaminofén..." allowClear />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12} md={6}>
              <Form.Item label="CUM" name="cum">
                <Input placeholder="19927641..." allowClear />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12} md={6}>
              <Form.Item label="Código lote" name="lot_code">
                <Input placeholder="Lote 001..." allowClear />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12} md={6}>
              <Form.Item label="Estado" name="estado">
                <Select placeholder="Todos" allowClear>
                  <Select.Option value="con_stock">Con stock</Select.Option>
                  <Select.Option value="sin_stock">Sin stock</Select.Option>
                  <Select.Option value="por_vencer">Por vencer (30 días)</Select.Option>
                  <Select.Option value="vencido">Vencido</Select.Option>
                </Select>
              </Form.Item>
            </Col>
            <Col xs={24} sm={12} md={6}>
              <Form.Item label="Bodega" name="bodega_id">
                <Select
                  placeholder="Todas las bodegas"
                  allowClear
                  showSearch
                  optionFilterProp="label"
                  options={bodegas.map((b) => ({ value: b.id, label: b.name }))}
                />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12} md={6}>
              <Form.Item label="Grupo" name="group_id">
                <Select
                  placeholder="Todos los grupos"
                  allowClear
                  showSearch
                  optionFilterProp="label"
                  options={grupos.map((g) => ({ value: g.id, label: g.name }))}
                />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12} md={8}>
              <Form.Item label="Rango de vencimiento" name="date_range">
                <DatePicker.RangePicker
                  style={{ width: '100%' }}
                  format="YYYY-MM-DD"
                  placeholder={['Desde', 'Hasta']}
                />
              </Form.Item>
            </Col>
            <Col xs={24} sm={24} md={4} style={{ display: 'flex', alignItems: 'flex-end', paddingBottom: 24 }}>
              <Space>
                <Button
                  type="primary"
                  htmlType="submit"
                  icon={<SearchOutlined />}
                  loading={isLoading}
                >
                  Buscar
                </Button>
                <Button icon={<ClearOutlined />} onClick={handleReset}>
                  Limpiar
                </Button>
              </Space>
            </Col>
          </Row>
        </Form>
      </div>

      <ErrorAlert message={error?.message ?? null} />

      {meta && (
        <p style={{ color: '#64748b', marginBottom: 12 }}>
          {meta.total} resultado{meta.total !== 1 ? 's' : ''} encontrado{meta.total !== 1 ? 's' : ''}
        </p>
      )}

      {isLoading && !data ? (
        <LoadingScreen />
      ) : (
        <Table<InventarioRow>
          rowKey={(r) => `${r.lot_id}-${r.product_id}`}
          columns={columns}
          dataSource={rows}
          loading={isLoading}
          pagination={{
            current: page,
            total: meta?.total ?? 0,
            pageSize: meta?.per_page ?? 30,
            onChange: (p) => setPage(p),
            showSizeChanger: false,
            showTotal: (total) => `Total: ${total} registros`,
          }}
          locale={{ emptyText: 'No hay resultados para los filtros seleccionados' }}
          scroll={{ x: 1200 }}
        />
      )}
    </>
  )
}
