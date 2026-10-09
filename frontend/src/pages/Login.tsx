import { useState } from 'react'
import { Button, Card, Form, Input, Typography } from 'antd'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/useAuth'
import { useFormErrors } from '../hooks/useFormErrors'
import { PageLayout } from '../components/PageLayout'
import { ErrorAlert } from '../components/ErrorAlert'
import { UserOutlined, LockOutlined } from '@ant-design/icons'

/**
 * FormValues - Interface que define los valores del formulario de login
 */

interface FormValues {
	name: string
	password: string
}

/**
 * Login - Componente que muestra el formulario de login
 * @param login - Función que se ejecuta cuando se envia el formulario
 * @param navigate - Función que se ejecuta cuando se navega a otra pagina
 * @param loading - Estado de carga
 * @param error - Errores del formulario
 */

export function Login() {

	// Funciones y estados globales para manejar el estado de la aplicacion
	const { login } = useAuth()
	const navigate = useNavigate()

	// Estados locales del componente
	const [loading, setLoading] = useState(false)
	const [error, setError] = useState<unknown>(null)

	// Hook personalizado para manejar los errores del formulario
	const { generalMessage, fieldErrors, getError } = useFormErrors(error)

	// Constantes para manejar el estado del formulario
	const showAlert = generalMessage && Object.keys(fieldErrors).length === 0
	const [form] = Form.useForm<FormValues>()

	// Función que se ejecuta cuando se envia el formulario
	const onFinish = async (values: FormValues) => {
		setLoading(true)
		setError(null)
		try {
			await login(values.name, values.password)
			navigate('/Home', { replace: true })
		} catch (err) {
			setError(err)
		} finally {
			setLoading(false)
		}
	}

	return (
		<PageLayout>
			<Card
				bordered={false}
				style={{
					width: '100%',
					margin: '0 auto',
					borderRadius: '12px',
				}}
				bodyStyle={{ padding: '32px 24px' }}
			>
				<div style={{ textAlign: 'center', marginBottom: '32px' }}>
					<Typography.Title level={3} style={{ margin: 0, color: '#1f2937' }}>
						Bienvenido
					</Typography.Title>
					<Typography.Text type="secondary">
						Ingresa tus credenciales para continuar
					</Typography.Text>
				</div>

				{showAlert && <ErrorAlert message={generalMessage} onClose={() => setError(null)} />}

				<Form<FormValues>
					form={form}
					layout="vertical"
					onFinish={onFinish}
					size="large"
					requiredMark={false}
				>
					<Form.Item
						label={<span style={{ fontWeight: 500 }}>Usuario</span>}
						name="name"
						validateStatus={getError('name') ? 'error' : undefined}
						help={getError('name')}
						rules={[{ required: true, message: 'Usuario requerido' }]}
					>
						<Input
							prefix={<UserOutlined style={{ color: '#bfbfbf' }} />}
							placeholder="Usuario"
							autoComplete="username"
						/>
					</Form.Item>

					<Form.Item
						label={<span style={{ fontWeight: 500 }}>Contraseña</span>}
						name="password"
						validateStatus={getError('password') ? 'error' : undefined}
						help={getError('password')}
						rules={[{ required: true, message: 'Contraseña requerida' }]}
						style={{ marginBottom: '12px' }}
					>
						<Input.Password
							prefix={<LockOutlined style={{ color: '#bfbfbf' }} />}
							placeholder="••••••••"
							autoComplete="current-password"
						/>
					</Form.Item>

					<Form.Item style={{ marginBottom: 0 }}>
						<Button
							type="primary"
							htmlType="submit"
							loading={loading}
							block
							style={{
								height: '40px',
								fontWeight: 600,
								borderRadius: '6px'
							}}
						>
							{loading ? 'Verificando...' : 'Ingresar'}
						</Button>
					</Form.Item>
				</Form>
			</Card>
		</PageLayout>
	)
}
