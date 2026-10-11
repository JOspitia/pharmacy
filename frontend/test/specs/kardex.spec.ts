/**
 * Specs de la pantalla del Kardex (historial de movimientos).
 *
 * A diferencia del Inventario, el Kardex es solo lectura: no expone
 * acciones destructive. Los tests validan que carga, muestra la
 * estructura correcta y aplica filtros.
 */
import { test, expect } from '../fixtures/auth.fixture'
import { SidebarPage } from '../pages/SidebarPage'
import { KardexPage } from '../pages/KardexPage'

test.describe('Kardex', () => {
  test.beforeEach(async ({ loggedInPage: page }) => {
    const sidebar = new SidebarPage(page)
    await sidebar.irA('Kardex')
    await sidebar.esperoActivo('Kardex')
  })

  test('carga la pagina con la tabla de movimientos', async ({ loggedInPage: page }) => {
    const kardex = new KardexPage(page)
    await kardex.esperoCargada()
  })

  test('filtra por tipo ENTRY', async ({ loggedInPage: page }) => {
    const kardex = new KardexPage(page)
    await kardex.esperoCargada()

    const aplicado = await kardex.filtrarPorTipo('ENTRY')
    expect(aplicado, 'la opcion ENTRY deberia existir (KardexDemoSeeder la crea)').toBe(true)

    const filas = await kardex.contarFilas()
    if (filas > 0) {
      await kardex.esperoPrimeraFilaContexto(/ENTRY/i)
    }
  })

  test('filtra por tipo TRANSFER_OUT (o el dropdown muestra vacio si no hay)', async ({
    loggedInPage: page,
  }) => {
    const kardex = new KardexPage(page)
    await kardex.esperoCargada()

    // TRANSFER_OUT solo aparece si hay traslados (HU-09). Como HU-09
    // no esta implementado, el dropdown puede estar vacio. Si
    // filtrarPorTipo devuelve false, no hay nada que verificar.
    const aplicado = await kardex.filtrarPorTipo('TRANSFER_OUT')
    if (!aplicado) {
      return
    }
    const filas = await kardex.contarFilas()
    if (filas > 0) {
      await kardex.esperoPrimeraFilaContexto(/TRANSFER_OUT/i)
    }
  })
})
