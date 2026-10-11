/**
 * KardexPage - Page Object de la pantalla del Kardex (historial).
 *
 * Encapsula selectores de KardexList.tsx:
 *   - h1 con texto "Kardex"
 *   - tabla con clase .ant-table
 *   - filtros: Select de tipo y RangePicker de fechas
 *   - filas con la cantidad con signo (+N verde, -N rojo)
 *
 * El Kardex es solo lectura: no hay botones de accion. El test valida
 * que la pantalla carga, muestra data y aplica filtros.
 */
import { type Page, expect } from '@playwright/test'

export class KardexPage {
  constructor(private readonly page: Page) {}

  async esperoCargada(): Promise<void> {
    await expect(this.page.getByRole('heading', { name: 'Kardex', level: 1 })).toBeVisible()
    await expect(this.page.locator('.ant-table').first()).toBeVisible()
  }

  /**
   * Aplica un filtro por tipo. Devuelve true si la opcion existia
   * (filtro aplicado) o false si el dropdown estaba vacio (no hay
   * movimientos de ese tipo y el filtro no se aplica).
   *
   * El click sobre la opcion del dropdown se hace via evaluate(el.click())
   * porque la opcion puede quedar fuera del viewport (el dropdown se
   * abre hacia abajo) y el scrollIntoView automatico de Playwright
   * a veces no la trae. El click nativo del DOM es la unica opcion
   * robusta para selects de Ant Design.
   */
  async filtrarPorTipo(tipo: string): Promise<boolean> {
    const select = this.page
      .locator('.ant-form, div')
      .filter({ hasText: /^Filtrar por tipo/ })
      .locator('.ant-select')
      .first()
    await select.click()

    const option = this.page
      .getByRole('option', { name: new RegExp(`^${tipo}$`) })
      .first()

    if ((await option.count()) === 0) {
      // El dropdown mostro "Sin datos": el tipo no tiene Kardex. Cerrar
      // el dropdown para no contaminar el siguiente test.
      await this.page.keyboard.press('Escape')
      return false
    }

    await option.evaluate((el) => (el as HTMLElement).click())
    return true
  }

  /**
   * Devuelve el numero de filas visibles en la tabla. Despues de filtrar
   * por un tipo que no tiene movimientos, devuelve 0.
   */
  async contarFilas(): Promise<number> {
    return this.page.locator('.ant-table-tbody > tr.ant-table-row').count()
  }

  /**
   * Verifica que la primera celda de la primera fila contiene el texto
   * esperado. Util para confirmar que el filtro se aplico.
   */
  async esperoPrimeraFilaContexto(texto: string | RegExp): Promise<void> {
    const regex = typeof texto === 'string' ? new RegExp(texto, 'i') : texto
    const primeraCelda = this.page.locator('.ant-table-tbody > tr.ant-table-row').first()
    await expect(primeraCelda).toContainText(regex)
  }
}
