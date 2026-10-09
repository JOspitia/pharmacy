/**
 * LoadingScreen — pantalla de carga centrada con Spin grande de Ant Design.
 */


import React from 'react';
import { Spin } from 'antd';

/**
 * Componente que muestra una pantalla de carga
 * @param style - Estilos personalizados
 */

interface Props {
  style?: React.CSSProperties
}

export function LoadingScreen({ style }: Props) {
  return (
    <div
      style={{
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        minHeight: '100vh',
        ...style
      }}
    >
      <Spin size="large" />
    </div>
  )
}
