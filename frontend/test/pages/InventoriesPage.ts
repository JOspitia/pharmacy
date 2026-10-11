/**
 * InventoriesPage - Page Object de la pantalla de Inventario.
 *
 * Encapsula selectores del componente InventoriesList.tsx:
 *   - h1 con texto "Inventario"
 *   - tabla con className .ant-table
 *   - 3 cards de alertas (vencidos / por vencer / bajo stock) en el panel
 *     superior; cada uno es un div con un span que contiene el titulo.
 *   - boton "Registrar movimiento" que abre el modal
 *   - el modal de movimiento tiene un form con: tipo, bodega, lote, cantidad, motivo
 *
 * Patrón POM: el spec escribe el flujo ("ir a inventario, ver la tabla,
 * abrir el modal"), y la Page tiene los selectores. Si cambia el DOM de
 * InventoriesList.tsx, solo se actualiza este archivo.
 */
import { type Page, expect } from '@playwright/test'

export class InventoriesPage {
  constructor(private readonly page: Page) {}

  /**
   * Espera a que la pagina de Inventario este cargada. La verificacion
   * se hace por el h1 (siempre presente) y por la tabla (puede no tener
   * filas si la BD esta vacia, pero el wrapper de Ant Design si).
   */
  async esperoCargada(): Promise<void> {
    await expect(this.page.getByRole('heading', { name: 'Inventario', level: 1 })).toBeVisible()
    await expect(this.page.locator('.ant-table').first()).toBeVisible()
  }

  /**
   * Devuelve el locator de un card de alerta por su titulo.
   * Los titulos son: "Lotes vencidos", "Proximos a vencer (90d)",
   * "Bajo stock minimo".
   *
   * Patron: cada alerta es un `.ant-card` cuyo head contiene un
   * `.ant-statistic`. El titulo del Statistic es un div con clase
   * `.ant-statistic-title` que tiene el texto del alerta (sin el
   * conteo: el conteo esta en `.ant-statistic-content-value`).
   * Localizamos por el titulo y subimos al `.ant-card` contenedor.
   */
  cardDeAlerta(titulo: string) {
    return this.page
      .locator('.ant-card', {
        has: this.page.locator('.ant-statistic-title', {
          hasText: new RegExp(`^${escapeRegExp(titulo)}$`),
        }),
      })
      .first()
  }

  /**
   * Cuenta los items dentro de un card de alerta. Sirve para verificar
   * que la pagina mostro el panel correcto: si la BD tiene lotes
   * vencidos, el card "Lotes vencidos" debe listar al menos uno.
   */
  async contarAlertas(titulo: string): Promise<number> {
    const card = this.cardDeAlerta(titulo)
    if (!(await card.isVisible().catch(() => false))) return 0
    // Los items son divs hijos del card con un tag al final; contamos
    // los que tienen un div con un Tag adentro.
    return card.locator('.ant-tag').count()
  }

  /**
   * Verifica que el card de alerta es visible. Util para los tests que
   * asumen que el panel superior existe siempre.
   */
  async esperoAlertaVisible(titulo: string): Promise<void> {
    await expect(this.cardDeAlerta(titulo), `card "${titulo}"`).toBeVisible()
  }

  /**
   * Hace click en "Registrar movimiento" y espera a que el modal se
   * monte. El modal tiene como titulo "Registrar movimiento de Kardex".
   */
  async abrirModalRegistrarMovimiento(): Promise<RegistrarMovimientoModal> {
    await this.page.getByRole('button', { name: /registrar movimiento/i }).click()
    const modal = new RegistrarMovimientoModal(this.page)
    await modal.esperoAbierto()
    return modal
  }

  /**
   * Devuelve el numero de filas de la tabla principal.
   */
  async contarFilas(): Promise<number> {
    return this.page.locator('.ant-table-tbody > tr.ant-table-row').count()
  }
}

/**
 * Page Object del modal "Registrar movimiento de Kardex".
 * Vive en su propia clase porque tiene comportamiento aislado:
 * selectores en cascada, validacion cliente, y un submit propio.
 */
export class RegistrarMovimientoModal {
  constructor(private readonly page: Page) {}

  /**
   * Espera a que el modal este visible. Ant Design envuelve el modal
   * en un `.ant-modal` con role="dialog".
   */
  async esperoAbierto(): Promise<void> {
    await expect(
      this.page.getByRole('dialog', { name: /registrar movimiento de kardex/i }),
    ).toBeVisible()
  }

  async esperoCerrado(): Promise<void> {
    await expect(
      this.page.getByRole('dialog', { name: /registrar movimiento de kardex/i }),
    ).not.toBeVisible()
  }

  /**
   * Selecciona el tipo de movimiento por su label visible. Click
   * programatico en la opcion (mismo motivo que KardexPage: el dropdown
   * de Ant Design a veces queda fuera del viewport).
   */
  async seleccionarTipo(label: string): Promise<void> {
    await this.page
      .locator('.ant-form-item', { hasText: 'Tipo de movimiento' })
      .locator('.ant-select')
      .click()
    await this.page
      .getByRole('option', { name: new RegExp(escapeRegExp(label), 'i') })
      .first()
      .evaluate((el) => (el as HTMLElement).click())
  }

  /**
   * Selecciona la bodega por su nombre. El selector de bodega se
   * habilita recien cuando se completa el de tipo.
   */
  async seleccionarBodega(nombre: string): Promise<void> {
    await this.page
      .locator('.ant-form-item', { hasText: 'Bodega' })
      .locator('.ant-select')
      .click()
    await this.page
      .getByRole('option', { name: new RegExp(escapeRegExp(nombre), 'i') })
      .first()
      .evaluate((el) => (el as HTMLElement).click())
  }

  /**
   * Selecciona el lote por el codigo visible (ej. LOTE-2026-AC-VENC).
   * Espera a que el selector este habilitado (depende de la bodega).
   */
  async seleccionarLote(nombre: string): Promise<void> {
    const select = this.page
      .locator('.ant-form-item', { hasText: 'Lote' })
      .locator('.ant-select')
    // Espera a que se habilite (despues de elegir bodega).
    await expect(select).not.toHaveClass(/ant-select-disabled/, { timeout: 10_000 })
    await select.click()
    await this.page
      .getByRole('option', { name: new RegExp(escapeRegExp(nombre), 'i') })
      .first()
      .evaluate((el) => (el as HTMLElement).click())
  }

  /**
   * Llena la cantidad en el InputNumber.
   */
  async llenarCantidad(valor: number): Promise<void> {
    const input = this.page
      .locator('.ant-form-item', { hasText: 'Cantidad' })
      .locator('input')
    await input.fill(String(valor))
  }

  async llenarMotivo(motivo: string): Promise<void> {
    const input = this.page
      .locator('.ant-form-item', { hasText: /^Motivo/ })
      .locator('textarea')
    await input.fill(motivo)
  }

  /**
   * Click en el boton "Registrar" del modal. Espera a que el modal
   * se cierre como confirmacion de exito (o espera a un error).
   *
   * `exact: true` es necesario porque en la pagina padre tambien hay un
   * boton "Registrar movimiento" que matchearia con un selector parcial.
   * Ademas, restringimos al dialog para no clickear el boton de la
   * pagina padre.
   */
  async enviar(): Promise<void> {
    await this.page
      .getByRole('dialog')
      .getByRole('button', { name: 'Registrar', exact: true })
      .click()
  }

  /**
   * Espera un mensaje de exito de Ant Design (toast verde).
   */
  async esperoExito(): Promise<void> {
    await expect(
      this.page.locator('.ant-message-success, .ant-notification-notice-success').first(),
    ).toBeVisible({ timeout: 5_000 })
  }
}

function escapeRegExp(text: string): string {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
