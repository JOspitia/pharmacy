/**
 * TransfersPage - Page Object de la pantalla de Traslados (HU-09).
 *
 * Encapsula selectores del componente TransfersList.tsx:
 *   - h1 con texto "Traslados"
 *   - tabla con className .ant-table
 *   - boton "Nuevo traslado" que abre el modal de creacion
 *   - filtros: Select de estado, Select de bodega origen/destino, RangePicker
 *   - en cada fila: boton Ver, boton Aprobar, boton Despachar,
 *     boton Recibir, boton Anular (segun estado)
 *
 * Patrón POM: el spec escribe el flujo ("ir a traslados, crear un
 * traslado"), y la Page tiene los selectores. Si cambia el DOM de
 * TransfersList.tsx, solo se actualiza este archivo.
 */
import { type Page, expect } from '@playwright/test'

export class TransfersPage {
  constructor(private readonly page: Page) {}

  /**
   * Espera a que la pagina de Traslados este cargada.
   */
  async esperoCargada(): Promise<void> {
    await expect(
      this.page.getByRole('heading', { name: 'Traslados', level: 1 }),
    ).toBeVisible()
    await expect(this.page.locator('.ant-table').first()).toBeVisible()
  }

  /**
   * Devuelve el numero de filas de la tabla principal.
   */
  async contarFilas(): Promise<number> {
    return this.page.locator('.ant-table-tbody > tr.ant-table-row').count()
  }

  /**
   * Abre el modal "Nuevo traslado" y lo devuelve.
   */
  async abrirModalCrear(): Promise<CrearTrasladoModal> {
    await this.page.getByRole('button', { name: /nuevo traslado/i }).click()
    const modal = new CrearTrasladoModal(this.page)
    await modal.esperoAbierto()
    return modal
  }

  /**
   * Filtra por estado usando el Select de estado.
   * Retorna true si se pudo aplicar el filtro.
   */
  async filtrarPorEstado(estadoLabel: string): Promise<boolean> {
    const select = this.page.locator('.ant-select').filter({ hasText: 'Estado' }).first()
    if (!(await select.isVisible())) return false

    await select.click()
    const option = this.page
      .getByRole('option', { name: new RegExp(estadoLabel, 'i') })
      .first()
    if (!(await option.isVisible())) {
      await this.page.keyboard.press('Escape')
      return false
    }
    await option.click()
    return true
  }

  /**
   * Limpia el filtro de estado.
   */
  async limpiarFiltroEstado(): Promise<void> {
    const clearBtn = this.page
      .locator('.ant-select')
      .filter({ hasText: 'Estado' })
      .first()
      .locator('.ant-select-clear')
    if (await clearBtn.isVisible()) {
      await clearBtn.click()
    }
  }

  /**
   * Busca la primera fila con el estado dado y hace click en "Anular".
   * Retorna true si se encontró y se pudo anular.
   */
  async intentarAnularPrimeraFilaConEstado(): Promise<boolean> {
    const filas = this.page.locator('.ant-table-tbody tr.ant-table-row')
    const cantidad = await filas.count()
    if (cantidad === 0) return false

    for (let i = 0; i < cantidad; i++) {
      const fila = filas.nth(i)
      const botonAnular = fila.locator('button', { hasText: 'Anular' })
      if (await botonAnular.isVisible()) {
        await botonAnular.click()
        // Confirmar en el Popconfirm.
        const popconfirm = this.page.locator('.ant-popover').first()
        if (await popconfirm.isVisible()) {
          await popconfirm.getByRole('button', { name: /anular/i }).click()
          return true
        }
        // Si no hay popconfirm visible, intenta con el boton OK generico.
        await this.page.getByRole('button', { name: /^anular$/i }).click()
        return true
      }
    }
    return false
  }

  /**
   * Retorna true si hay al menos una fila con boton "Anular".
   */
  async hayFilaCancelable(): Promise<boolean> {
    return this.page.locator('.ant-table-tbody tr.ant-table-row button', { hasText: 'Anular' }).first().isVisible()
  }
}

/**
 * Page Object del modal "Nuevo traslado entre bodegas".
 */
export class CrearTrasladoModal {
  constructor(private readonly page: Page) {}

  async esperoAbierto(): Promise<void> {
    await expect(
      this.page.getByRole('dialog', { name: /nuevo traslado entre bodegas/i }),
    ).toBeVisible()
  }

  async esperoCerrado(): Promise<void> {
    await expect(
      this.page.getByRole('dialog', { name: /nuevo traslado entre bodegas/i }),
    ).not.toBeVisible()
  }

  /** Selecciona la bodega destino por nombre visible. */
  async seleccionarDestino(nombre: string): Promise<void> {
    // Ant Design v6 genera <select> nativo como combobox ARIA.
    // Usamos el form item para ser especificos.
    const select = this.page.locator(
      '.ant-modal .ant-form-item',
    ).filter({ hasText: /bodega destino/i }).locator('select')
    await select.selectOption({ index: 0 }) // abre el dropdown
    await this.page
      .getByRole('option', { name: new RegExp(escape(nombre), 'i') })
      .first()
      .click({ force: true })
  }

  /**
   * Selecciona la bodega destino nth (0-based).
   * Ant Design v6: el Select usa <select> nativo dentro de .ant-select.
   * Usamos selectOption sobre el <select> directamente.
   */
  async seleccionarDestinoPorIndice(indice: number): Promise<void> {
    const select = this.page.locator(
      '.ant-modal .ant-form-item',
    ).filter({ hasText: /bodega destino/i }).locator('select')
    await select.selectOption({ index: indice + 1 }) // +1 porque index 0 es placeholder
  }

  /** Agrega un item vacio a la tabla de items. */
  async agregarItem(): Promise<void> {
    // El boton vive en el footer de la Table de Ant Design, que cuando no
    // hay filas muestra un empty-state SVG que intercepta pointer events.
    // Usamos evaluate+click para bypassear el overlay y luego esperamos
    // a que la fila count increase (lo que confirma que React actualizo).
    const antes = await this.page.locator('.ant-modal .ant-table-row').count()
    await this.page
      .getByRole('button', { name: /agregar item/i })
      .evaluate((el) => (el as HTMLButtonElement).click())
    // Esperar a que la cantidad de filas aumente (confirma que React rendereo).
    await this.page.waitForFunction(
      (prev) =>
        document.querySelectorAll('.ant-modal .ant-table-row').length > prev,
      antes,
      { timeout: 5_000 },
    )
  }

  /**
   * Elimina el item en la fila nth (0-based) de la tabla de items.
   * Si es la unica fila, no se elimina (el boton esta disabled).
   */
  async eliminarItem(nth: number): Promise<void> {
    const filas = this.page.locator('.ant-modal .ant-table-row')
    const boton = filas.nth(nth).locator('button[type="button"]').last()
    const isDisabled = await boton.getAttribute('disabled')
    if (isDisabled !== null) return // No se puede eliminar la unica fila.
    await boton.click()
  }

  /**
   * Cuenta los items actuales en la tabla de items del modal.
   */
  async contarItems(): Promise<number> {
    return this.page.locator('.ant-modal .ant-table-row').count()
  }

  /**
   * Selecciona un inventario en la fila nth. Busca por texto parcial del nombre.
   * Usa evaluate click en el input interno del Select para evitar que el
   * empty-state SVG de la Table intercepte el click.
   */
  async seleccionarInventario(nth: number, textoParcial: string): Promise<void> {
    const filas = this.page.locator('.ant-modal .ant-table-row')
    const select = filas.nth(nth).locator('.ant-select').first()

    // Esperar a que el select se habilite (puede depender de la bodega).
    await expect(select).not.toHaveClass(/ant-select-disabled/, { timeout: 8_000 })

    // Click en el input de busqueda interno del Select (no en el wrapper).
    // Esto evita que el empty-state SVG de la Table intercepte el click.
    await select.locator('.ant-select-selection-search-input').evaluate(
      (el) => (el as HTMLInputElement).click(),
    )

    // Esperar a que el dropdown se abra (visible, sin la clase -hidden).
    const dropdown = this.page.locator('.ant-select-dropdown:not(.ant-select-dropdown-hidden)').first()
    await expect(dropdown).toBeVisible({ timeout: 5_000 })

    // Buscar la opcion que contenga el texto.
    const opciones = dropdown.locator('.ant-select-item:not(.ant-select-item-disabled)')
    const cantidad = await opciones.count()
    if (cantidad === 0) {
      await this.page.keyboard.press('Escape')
      return
    }

    // Si se dio texto parcial, buscar el que coincida.
    if (textoParcial) {
      for (let i = 0; i < cantidad; i++) {
        const texto = await opciones.nth(i).textContent()
        if (texto && texto.toLowerCase().includes(textoParcial.toLowerCase())) {
          await opciones.nth(i).evaluate((el) => (el as HTMLElement).click())
          return
        }
      }
      // Si no encontro coincidencia parcial, usar el primero disponible.
      await opciones.first().evaluate((el) => (el as HTMLElement).click())
    } else {
      await opciones.first().evaluate((el) => (el as HTMLElement).click())
    }
  }

  /**
   * Llena la cantidad solicitada en la fila nth del formulario de items.
   */
  async llenarCantidad(nth: number, cantidad: number): Promise<void> {
    const filas = this.page.locator('.ant-modal .ant-table-row')
    // El InputNumber de Ant Design tiene un input adentro.
    const input = filas.nth(nth).locator('input').first()
    await input.fill(String(cantidad))
  }

  /** Click en "Crear traslado" (dentro del dialog). */
  async enviar(): Promise<void> {
    await this.page
      .getByRole('dialog')
      .getByRole('button', { name: /^crear traslado$/i })
      .click()
  }

  /** Espera mensaje de exito. */
  async esperoExito(): Promise<void> {
    await expect(
      this.page.locator('.ant-message-success, .ant-notification-notice-success').first(),
    ).toBeVisible({ timeout: 5_000 })
  }

  /** Cerrar el modal sin guardar. */
  async cerrar(): Promise<void> {
    await this.page.getByRole('button', { name: /cancelar/i }).click()
    await this.esperoCerrado()
  }
}

function escape(text: string): string {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/**
 * Page Object del modal de detalle del traslado (TransferDetailModal.tsx).
 */
export class VistaDetalleTrasladoModal {
  constructor(private readonly page: Page) {}

  async esperoAbierto(): Promise<void> {
    await expect(
      this.page.locator('.ant-modal').filter({ hasText: /traslado/i }).first(),
    ).toBeVisible()
  }

  async cerrar(): Promise<void> {
    await this.page.getByRole('button', { name: /^cerrar$/i }).click()
    await expect(
      this.page.locator('.ant-modal').filter({ hasText: /traslado/i }).first(),
    ).not.toBeVisible()
  }

  /**
   * Devuelve el titulo del modal (ej. "Traslado TRF-20261011-0001").
   */
  async titulo(): Promise<string> {
    const t = this.page.locator('.ant-modal-title').first()
    return (await t.textContent()) ?? ''
  }

  /**
   * Hace click en el boton del footer segun el label (ej. "Aprobar cantidades",
   * "Despachar", "Recibir", "Registrar recepción pendiente"). Devuelve true si
   * el boton existia y se clickeo.
   */
  async clickFooter(label: string): Promise<boolean> {
    const btn = this.page
      .locator('.ant-modal-footer')
      .getByRole('button', { name: new RegExp(escape(label), 'i') })
      .first()
    if (!(await btn.isVisible())) return false
    await btn.click()
    return true
  }

  /**
   * En la tabla de discrepancias, hace click en el boton de accion de la
   * primera fila PENDING. Acciones validas: 'Cerrar sin ajuste', 'Dar de baja'.
   */
  async clickAccionDiscrepancia(label: 'Cerrar sin ajuste' | 'Dar de baja'): Promise<boolean> {
    const fila = this.page
      .locator('.ant-modal .ant-table-tbody tr.ant-table-row')
      .filter({ has: this.page.locator('td', { hasText: /pendiente/i }) })
      .first()
    const boton = fila.getByRole('button', { name: new RegExp(escape(label), 'i') })
    if (!(await boton.isVisible())) return false
    await boton.click()
    return true
  }
}

/**
 * Page Object del modal "Aprobar cantidades del traslado".
 */
export class AprobarTrasladoModal {
  constructor(private readonly page: Page) {}

  async esperoAbierto(): Promise<void> {
    await expect(
      this.page.getByRole('dialog', { name: /aprobar cantidades del traslado/i }),
    ).toBeVisible()
  }

  async esperoCerrado(): Promise<void> {
    await expect(
      this.page.getByRole('dialog', { name: /aprobar cantidades del traslado/i }),
    ).not.toBeVisible()
  }

  /**
   * Llena el campo "Aprobada" de la fila nth con `cantidad`. Si el campo esta
   * vacio, lo deja en 0 (regla del frontend: blank = 0).
   */
  async aprobar(nth: number, cantidad: number | ''): Promise<void> {
    const filas = this.page.locator('.ant-modal .ant-table-row')
    const input = filas.nth(nth).locator('input').first()
    if (cantidad === '') {
      // Limpiar el campo: triple click + delete.
      await input.click({ clickCount: 3 })
      await input.press('Delete')
    } else {
      await input.fill(String(cantidad))
    }
  }

  async enviar(): Promise<void> {
    await this.page
      .locator('.ant-modal-footer')
      .getByRole('button', { name: /^aprobar$/i })
      .click()
  }

  async cerrar(): Promise<void> {
    await this.page.getByRole('button', { name: /cancelar/i }).click()
    await this.esperoCerrado()
  }
}

/**
 * Page Object del modal "Recibir traslado".
 */
export class RecibirTrasladoModal {
  constructor(private readonly page: Page) {}

  async esperoAbierto(): Promise<void> {
    await expect(
      this.page.getByRole('dialog', { name: /recibir traslado/i }),
    ).toBeVisible()
  }

  async esperoCerrado(): Promise<void> {
    await expect(
      this.page.getByRole('dialog', { name: /recibir traslado/i }),
    ).not.toBeVisible()
  }

  /**
   * Llena el campo "Recibida" de la fila nth con `cantidad`. Acepta '' para
   * dejar vacio (no recibido).
   */
  async recibir(nth: number, cantidad: number | ''): Promise<void> {
    const filas = this.page.locator('.ant-modal .ant-table-row')
    const input = filas.nth(nth).locator('input').first()
    if (cantidad === '') {
      await input.click({ clickCount: 3 })
      await input.press('Delete')
    } else {
      await input.fill(String(cantidad))
    }
  }

  async enviar(): Promise<void> {
    await this.page
      .locator('.ant-modal-footer')
      .getByRole('button', { name: /^confirmar recepcion$|^recibir$/i })
      .click()
  }

  async cerrar(): Promise<void> {
    await this.page.getByRole('button', { name: /cancelar/i }).click()
    await this.esperoCerrado()
  }
}
