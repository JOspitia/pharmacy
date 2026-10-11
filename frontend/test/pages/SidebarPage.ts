/**
 * SidebarPage - Page Object del menu lateral (AppLayout).
 *
 * Encapsula los selectores del Sider de Ant Design para que los demas
 * Page Objects puedan navegar a una seccion sin saber que existe un
 * Sider.Menu. Si el dia de mañana la navegacion pasa a un Drawer o a
 * tabs, solo se cambia este archivo.
 *
 * Selectores:
 *   - El menu es un `.ant-menu`. Cada item es un `li.ant-menu-item`
 *     con un link cuyo texto es el label visible (ej. "Inventario").
 *   - Navegamos por el texto visible en lugar de por href porque el href
 *     es una prop de React Router y no aparece en el DOM directamente.
 */
import { type Page, expect } from '@playwright/test'

export class SidebarPage {
  constructor(private readonly page: Page) {}

  /**
   * Hace click en el item del sidebar cuyo texto visible coincide
   * con `label` (case-insensitive, match exacto).
   */
  async irA(label: string): Promise<void> {
    const item = this.page.locator('.ant-menu-item', { hasText: new RegExp(`^${escapeRegExp(label)}$`, 'i') })
    await expect(item, `item del sidebar "${label}"`).toBeVisible()
    await item.click()
  }

  /**
   * Verifica que el item del sidebar con ese label esta marcado como
   * activo (la clase `.ant-menu-item-selected` lo indica).
   */
  async esperoActivo(label: string): Promise<void> {
    const item = this.page.locator('.ant-menu-item-selected', {
      hasText: new RegExp(`^${escapeRegExp(label)}$`, 'i'),
    })
    await expect(item).toBeVisible()
  }
}

/**
 * Escapa caracteres especiales de regex para que `hasText` con RegExp
 * no falle si el label tiene puntos, parentesis, etc.
 */
function escapeRegExp(text: string): string {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
