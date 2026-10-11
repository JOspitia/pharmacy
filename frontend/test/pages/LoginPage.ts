/**
 * LoginPage - Page Object de la pantalla de login.
 *
 * El form usa Ant Design. Los inputs son `.ant-input` dentro de
 * un Form.Item que tiene como label el texto del campo.
 *
 * Uso:
 *   const login = new LoginPage(page)
 *   await login.completar('regente', 'password')
 *   await login.EsperoEstarEnHome()
 */
import { type Page, expect } from '@playwright/test'

export class LoginPage {
  constructor(private readonly page: Page) {}

  /**
   * Va a /login. La navegacion directa evita depender del sidebar
   * (que no existe en esta pantalla porque AppLayout solo se monta
   * para usuarios autenticados).
   */
  async goto(): Promise<void> {
    await this.page.goto('/login')
  }

  /**
   * Completa el formulario y hace click en "Iniciar sesion".
   */
  async completar(name: string, password: string): Promise<void> {
    // Ant Design renderiza cada Form.Item como un .ant-form-item con
    // label. Localizamos por el label visible y subimos al input.
    const nameInput = this.page.locator('.ant-form-item', { hasText: 'Usuario' }).locator('input')
    const passInput = this.page.locator('.ant-form-item', { hasText: /Contraseña/i }).locator('input')

    await nameInput.fill(name)
    await passInput.fill(password)

    // El boton dice "Ingresar" (loading cambia el texto a "Verificando...").
    await this.page.getByRole('button', { name: /ingresar/i }).click()
  }

  /**
   * Espera a que la URL deje de ser /login (la pantalla redirige a
   * /Home tras login exitoso). timeout generoso porque depende de
   * que el backend responda.
   */
  async esperoEstarEnHome(): Promise<void> {
    await this.page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 10_000 })
    await expect(this.page).toHaveURL(/\/Home/)
  }

  /**
   * Espera a que aparezca el mensaje de error de Ant Design.
   * Ant Design usa `.ant-message-error` o `.ant-notification` segun
   * el contexto. Cubrimos los dos.
   */
  async esperoError(mensaje: RegExp | string): Promise<void> {
    const regex = typeof mensaje === 'string' ? new RegExp(mensaje, 'i') : mensaje
    await expect(
      this.page.locator('.ant-message, .ant-notification').filter({ hasText: regex }).first(),
    ).toBeVisible()
  }
}
