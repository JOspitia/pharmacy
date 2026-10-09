import React from 'react';
import { Alert } from 'antd';

/**
 * Componente que muestra una alerta de error
 * @param message - Mensaje de error
 * @param onClose - Función que se ejecuta cuando se cierra la alerta
 * @param style - Estilos personalizados
 */
interface Props {
  message: string | null
  onClose?: () => void
  style?: React.CSSProperties
}

export function ErrorAlert({ message, onClose, style }: Props) {
  if (!message) return null

  return (
    <Alert
      title={message}
      type="error"
      closable={{ closeIcon: true, onClose: () => { onClose?.(); }, 'aria-label': 'close' }}
      style={{ marginBottom: 16, ...style }}
    />
  )
}