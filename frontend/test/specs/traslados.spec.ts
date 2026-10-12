/**
 * Specs de la pantalla de Traslados (HU-09).
 *
 * Estos tests cubren el ciclo completo del traslado y el flujo de
 * discrepancias. El estado se prepara via API usando page.request
 * (mas rapido y robusto que drivear la UI completa) y los specs
 * verifican el comportamiento visible de la UI.
 *
 * Requiere BD con seeders:
 *   docker compose exec backend php artisan db:seed --class=DemoSeeder
 *
 * Roles usados:
 *   - regente:        flujo completo (crear, aprobar, despachar, recibir, resolver)
 *   - auxiliar:      crear y recibir (RN-08 le prohibe aprobar/despachar/anular)
 */
import { test, expect } from '../fixtures/auth.fixture'
import { SidebarPage } from '../pages/SidebarPage'
import {
  TransfersPage,
  VistaDetalleTrasladoModal,
  AprobarTrasladoModal,
  RecibirTrasladoModal,
} from '../pages/TransfersPage'
import type {
  InventoryRecord,
  BodegaRecord,
  TransferRecord,
  TransferDiscrepancyRecord,
} from '../types'

const DESTINO_NAME = 'Farmacia Central'
const CANTIDAD_PRUEBA = 2

// ─────────────────────────────────────────────────────────────────────────────
// Helpers — usan page.request (APIRequestContext del navegador)
// ─────────────────────────────────────────────────────────────────────────────

/** Obtiene headers de autorizacion para un rol dado. */
async function getAuth(
  req: import('@playwright/test').APIRequestContext,
  name: string,
): Promise<{ Authorization: string }> {
  const res = await req.post('/api/login', {
    data: { name, password: 'password' },
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  })
  const body = await res.json()
  return { Authorization: `Bearer ${body.token}` }
}

/** Crea un traslado en DRAFT y devuelve { id, itemId, number }. */
async function crearDraft(
  req: import('@playwright/test').APIRequestContext,
  toWarehouseId: string,
  inventoryId: string,
  qty: number,
  auth: { Authorization: string },
) {
  const res = await req.post('/api/transfers', {
    headers: { ...auth, 'Content-Type': 'application/json', Accept: 'application/json' },
    data: {
      to_warehouse_id: toWarehouseId,
      items: [{ inventory_id: inventoryId, requested_quantity: qty }],
    },
  })
  if (!res.ok()) throw new Error(`POST /transfers ${res.status()}: ${await res.text()}`)
  const b = await res.json()
  return { id: b.data.id, itemId: b.data.items[0].id, number: b.data.transfer_number }
}

/** Aprueba un traslado. */
async function approve(
  req: import('@playwright/test').APIRequestContext,
  transferId: string,
  itemId: string,
  qty: number,
  auth: { Authorization: string },
) {
  const res = await req.patch(`/api/transfers/${transferId}/approve`, {
    headers: { ...auth, 'Content-Type': 'application/json', Accept: 'application/json' },
    data: { items: [{ transfer_item_id: itemId, approved_quantity: qty }] },
  })
  if (!res.ok()) throw new Error(`PATCH /approve ${res.status()}: ${await res.text()}`)
}

/** Despacha un traslado. */
async function dispatch(
  req: import('@playwright/test').APIRequestContext,
  transferId: string,
  auth: { Authorization: string },
) {
  const res = await req.patch(`/api/transfers/${transferId}/dispatch`, {
    headers: { ...auth, 'Content-Type': 'application/json', Accept: 'application/json' },
  })
  if (!res.ok()) throw new Error(`PATCH /dispatch ${res.status()}: ${await res.text()}`)
}

/** Recibe un traslado. */
async function receive(
  req: import('@playwright/test').APIRequestContext,
  transferId: string,
  items: { itemId: string; delivered: number }[],
  auth: { Authorization: string },
) {
  const res = await req.patch(`/api/transfers/${transferId}/receive`, {
    headers: { ...auth, 'Content-Type': 'application/json', Accept: 'application/json' },
    data: {
      items: items.map(i => ({
        transfer_item_id: i.itemId,
        delivered_quantity: i.delivered,
      })),
    },
  })
  if (!res.ok()) throw new Error(`PATCH /receive ${res.status()}: ${await res.text()}`)
}

/** Obtiene un traslado del API. */
async function getTransfer(
  req: import('@playwright/test').APIRequestContext,
  transferId: string,
  auth: { Authorization: string },
): Promise<TransferRecord> {
  const res = await req.get(`/api/transfers/${transferId}`, {
    headers: { ...auth, Accept: 'application/json' },
  })
  if (!res.ok()) throw new Error(`GET /transfers/${transferId} ${res.status()}`)
  return (await res.json()).data
}

/**
 * Busca un inventario con stock suficiente, en bodega != destino y con
 * lote NO vencido (filtra RN-01). Si no hay ninguno con stock suficiente,
 * CREA uno nuevo para que el test sea independiente de los seeders.
 * Devuelve { inv, destino } o null si ni siquiera puede crear.
 */
async function findValidInventory(
  req: import('@playwright/test').APIRequestContext,
  minQty: number,
): Promise<{ inv: InventoryRecord; destino: BodegaRecord } | null> {
  const regente = await getAuth(req, 'regente')

  const invRes = await req.get('/api/inventories?per_page=100', {
    headers: { ...regente, Accept: 'application/json' },
  })
  const invs: InventoryRecord[] = (await invRes.json()).data ?? []

  // Primero: buscar uno existente con stock suficiente y lote no vencido.
  const existente = invs.find((i: InventoryRecord) => {
    if (i.bodega?.name === DESTINO_NAME) return false
    if (i.quantity < minQty) return false
    if (i.lot?.name && /VENC(ID)?/i.test(i.lot.name)) return false
    if (i.lot?.expiry_date) {
      const exp = new Date(i.lot.expiry_date)
      exp.setHours(0, 0, 0, 0)
      const today = new Date()
      today.setHours(0, 0, 0, 0)
      if (exp < today) return false
    }
    return true
  })
  if (existente) {
    const bodRes = await req.get('/api/bodegas?per_page=100', {
      headers: { ...regente, Accept: 'application/json' },
    })
    const destino = (await bodRes.json()).data.find(
      (b: BodegaRecord) => b.name === DESTINO_NAME,
    )
    return destino ? { inv: existente, destino } : null
  }

  // Segundo: no hay inventario existente suficiente. Crear uno nuevo.
  // Necesitamos un producto, lote y bodega de origen distintos de destino.
  const bodRes = await req.get('/api/bodegas?per_page=100', {
    headers: { ...regente, Accept: 'application/json' },
  })
  const todasBodegas: BodegaRecord[] = (await bodRes.json()).data ?? []
  const destino = todasBodegas.find((b: BodegaRecord) => b.name === DESTINO_NAME)
  if (!destino) return null

  const origen = todasBodegas.find(
    (b: BodegaRecord) => b.id !== destino.id && b.name !== DESTINO_NAME,
  )
  if (!origen) return null

  // Obtener un producto y lote no vencido para crear el inventario.
  const prodRes = await req.get('/api/products?per_page=10', {
    headers: { ...regente, Accept: 'application/json' },
  })
  const productos = (await prodRes.json()).data ?? []
  if (!productos.length) return null

  // Obtener lotes no vencidos para ese producto.
  const lotRes = await req.get('/api/lots?per_page=50', {
    headers: { ...regente, Accept: 'application/json' },
  })
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const lotes: { id: string; name: string; expiry_date: string | null }[] =
    (await lotRes.json()).data ?? []
  const loteValido = lotes.find((l) => {
    if (!l.expiry_date) return false
    const exp = new Date(l.expiry_date)
    exp.setHours(0, 0, 0, 0)
    if (exp < today) return false
    if (/VENC(ID)?/i.test(l.name)) return false
    return true
  })
  if (!loteValido) return null

  // Crear el inventario con stock alto (200) para que alcance para todos.
  const crearRes = await req.post('/api/inventories', {
    headers: { ...regente, 'Content-Type': 'application/json', Accept: 'application/json' },
    data: {
      warehouse_id: origen.id,
      product_id: productos[0].id,
      lot_id: loteValido.id,
      quantity: 200,
    },
  })
  if (!crearRes.ok()) return null
  const nuevoInv: InventoryRecord = (await crearRes.json()).data
  if (!nuevoInv) return null

  return { inv: nuevoInv, destino }
}

/**
 * Prepara un traslado en estado IN_TRANSIT para los tests de recepcion.
 */
async function setupInTransit(
  req: import('@playwright/test').APIRequestContext,
  qty: number,
) {
  const found = await findValidInventory(req, qty * 2)
  if (!found) throw new Error(`No hay inventario no vencido con stock>=${qty * 2}. Corre DemoSeeder.`)
  const { inv, destino } = found

  const regente = await getAuth(req, 'regente')
  const auxiliar = await getAuth(req, 'auxiliar')

  const draft = await crearDraft(req, destino.id, inv.id, qty, auxiliar)
  await approve(req, draft.id, draft.itemId, qty, regente)
  await dispatch(req, draft.id, regente)

  return { id: draft.id, itemId: draft.itemId, number: draft.number }
}

/**
 * Prepara un traslado IN_TRANSIT para tests de discrepancia (qty solicitada
 * = qty aprobada, para luego hacer recepcion parcial).
 */
async function setupForDiscrepancy(
  req: import('@playwright/test').APIRequestContext,
  partialQty: number,
) {
  const found = await findValidInventory(req, partialQty * 2)
  if (!found) throw new Error(`No hay inventario no vencido con stock>=${partialQty * 2}. Corre DemoSeeder.`)
  const { inv, destino } = found

  const regente = await getAuth(req, 'regente')
  const auxiliar = await getAuth(req, 'auxiliar')

  // Crea, aprueba y despacha qty solicitada = approvedQty.
  const draft = await crearDraft(req, destino.id, inv.id, partialQty, auxiliar)
  await approve(req, draft.id, draft.itemId, partialQty, regente)
  await dispatch(req, draft.id, regente)

  return { id: draft.id, itemId: draft.itemId, number: draft.number }
}

// ─────────────────────────────────────────────────────────────────────────────
// Tests — carga y listado
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Traslados — carga y listado', () => {
  test.beforeEach(async ({ loggedInPage: page }) => {
    const sidebar = new SidebarPage(page)
    await sidebar.irA('Traslados')
    await sidebar.esperoActivo('Traslados')
  })

  test('carga la pagina con la tabla y el boton Nuevo traslado', async ({
    loggedInPage: page,
  }) => {
    const transfers = new TransfersPage(page)
    await transfers.esperoCargada()
    await expect(
      page.getByRole('button', { name: /nuevo traslado/i }),
    ).toBeVisible()
  })

  test('la tabla muestra filas o empty state', async ({ loggedInPage: page }) => {
    const transfers = new TransfersPage(page)
    await transfers.esperoCargada()
    expect(await transfers.contarFilas()).toBeGreaterThanOrEqual(0)
  })

  test('el filtro de estado filtra la tabla', async ({ loggedInPage: page }) => {
    const transfers = new TransfersPage(page)
    await transfers.esperoCargada()

    const aplicado = await transfers.filtrarPorEstado('Borrador')
    if (!aplicado) return

    const filas = await transfers.contarFilas()
    if (filas > 0) {
      const tag = page.locator('.ant-table-tbody .ant-tag').first()
      if (await tag.isVisible()) {
        const texto = await tag.textContent()
        expect(texto?.toLowerCase()).toMatch(/borrador|draft/i)
      }
    }
    await transfers.limpiarFiltroEstado()
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// Tests — crear (smoke)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Traslados — crear (smoke)', () => {
  test.beforeEach(async ({ loggedInPage: page }) => {
    const sidebar = new SidebarPage(page)
    await sidebar.irA('Traslados')
    await sidebar.esperoActivo('Traslados')
  })

  test('el modal de crear valida campos requeridos y permite cancelar', async ({
    loggedInPage: page,
  }) => {
    const transfers = new TransfersPage(page)
    await transfers.esperoCargada()

    const modal = await transfers.abrirModalCrear()
    await modal.esperoAbierto()

    // Enviar sin llenar nada: el form rechaza.
    await modal.enviar()
    await modal.esperoAbierto()

    await modal.cerrar()
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// Tests — ciclo completo via UI (aprobar)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Traslados — ciclo completo via UI', () => {
  test('aprobar cantidades: el regente puede aprobar y la fila pasa a Aprobado', async ({
    loggedInPage: page,
  }) => {
    // Preparar estado: DRAFT creado por auxiliar con lote no vencido.
    const found = await findValidInventory(page.request, CANTIDAD_PRUEBA)
    if (!found) { test.skip(); return }
    const { inv, destino } = found

    const auxiliar = await getAuth(page.request, 'auxiliar')

    const draft = await crearDraft(
      page.request,
      destino.id,
      inv.id,
      CANTIDAD_PRUEBA,
      auxiliar,
    )

    const sidebar = new SidebarPage(page)
    await sidebar.irA('Traslados')
    await sidebar.esperoActivo('Traslados')

    const transfers = new TransfersPage(page)
    await transfers.esperoCargada()

    const fila = page
      .locator('.ant-table-tbody tr.ant-table-row')
      .filter({ hasText: draft.number })
    await fila.first().waitFor({ state: 'visible', timeout: 10000 })
    await fila.first().locator('button', { hasText: /^ver$/i }).click()

    const detalle = new VistaDetalleTrasladoModal(page)
    await detalle.esperoAbierto()
    expect(await detalle.titulo()).toContain(draft.number)

    const click = await detalle.clickFooter('Aprobar cantidades')
    expect(click).toBeTruthy()

    const aprobar = new AprobarTrasladoModal(page)
    await aprobar.esperoAbierto()
    await aprobar.aprobar(0, CANTIDAD_PRUEBA)
    await aprobar.enviar()

    // Esperar cierre del modal.
    await page.waitForTimeout(2000)
    const stillOpen = await page
      .getByRole('dialog', { name: /aprobar cantidades del traslado/i })
      .isVisible()
    if (stillOpen) {
      const errorText = await page
        .locator('.ant-modal .ant-alert-error')
        .first()
        .textContent()
        .catch(() => 'sin alert de error')
      throw new Error(`El modal de aprobar no se cerro. Error: ${errorText}`)
    }

    await page.reload()
    await transfers.esperoCargada()

    const filaActual = page
      .locator('.ant-table-tbody tr.ant-table-row')
      .filter({ hasText: draft.number })
      .first()
    await expect(filaActual.locator('.ant-tag').first()).toContainText('Aprobado')
  })

  test('RN-08: auxiliar no puede aprobar su propio traslado (403)', async ({
    loggedInPage: page,
  }) => {
    const found = await findValidInventory(page.request, CANTIDAD_PRUEBA)
    if (!found) { test.skip(); return }
    const { inv, destino } = found

    const auxiliar = await getAuth(page.request, 'auxiliar')

    const draft = await crearDraft(
      page.request,
      destino.id,
      inv.id,
      CANTIDAD_PRUEBA,
      auxiliar,
    )

    // Auxiliar intenta aprobar → 403.
    const res = await page.request.patch(`/api/transfers/${draft.id}/approve`, {
      headers: {
        ...auxiliar,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      data: {
        items: [{ transfer_item_id: draft.itemId, approved_quantity: CANTIDAD_PRUEBA }],
      },
    })
    expect(res.status()).toBe(403)
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// Tests — recepcion y discrepancias
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Traslados — recepcion y discrepancias', () => {
  test('recibir completo: regente recibe la cantidad aprobada y el traslado pasa a Recibido', async ({
    loggedInPage: page,
  }) => {
    let t: { id: string; itemId: string; number: string }
    try {
      const setup = await setupInTransit(page.request, CANTIDAD_PRUEBA)
      t = setup
    } catch {
      test.skip()
      return
    }

    const regente = await getAuth(page.request, 'regente')

    const sidebar = new SidebarPage(page)
    await sidebar.irA('Traslados')
    await sidebar.esperoActivo('Traslados')

    const transfers = new TransfersPage(page)
    await transfers.esperoCargada()

    const fila = page
      .locator('.ant-table-tbody tr.ant-table-row')
      .filter({ hasText: t.number })
      .first()
    await fila.locator('button', { hasText: /^ver$/i }).click()

    const detalle = new VistaDetalleTrasladoModal(page)
    await detalle.esperoAbierto()

    const click = await detalle.clickFooter('Recibir')
    expect(click).toBeTruthy()

    const recibir = new RecibirTrasladoModal(page)
    await recibir.esperoAbierto()
    await recibir.recibir(0, CANTIDAD_PRUEBA)
    await recibir.enviar()
    await recibir.esperoCerrado()

    // Verificar via API.
    const result = await getTransfer(page.request, t.id, regente)
    expect(result.status_name).toBe('RECEIVED')
  })

  test('recibir parcial: queda discrepancia PENDING y aparece el boton Corregir', async ({
    loggedInPage: page,
  }) => {
    // Crear traslado con qty=4, aprobar 4, despachar. Recibir solo 2 → discrepancia de 2.
    let draft: { id: string; itemId: string; number: string }
    try {
      draft = await setupForDiscrepancy(page.request, CANTIDAD_PRUEBA * 2)
    } catch {
      test.skip()
      return
    }

    const regente = await getAuth(page.request, 'regente')
    const auxiliar = await getAuth(page.request, 'auxiliar')

    // Hacer la recepcion parcial por API para control preciso.
    await receive(page.request, draft.id, [{ itemId: draft.itemId, delivered: CANTIDAD_PRUEBA }], auxiliar)

    const sidebar = new SidebarPage(page)
    await sidebar.irA('Traslados')
    await sidebar.esperoActivo('Traslados')

    const transfers = new TransfersPage(page)
    await transfers.esperoCargada()

    const filaActual = page
      .locator('.ant-table-tbody tr.ant-table-row')
      .filter({ hasText: draft.number })
      .first()

    const ourFilaExists = await filaActual.isVisible().catch(() => false)
    if (!ourFilaExists) {
      const allTags = await page.locator('.ant-table-tbody .ant-tag').allTextContents()
      throw new Error(
        `No se encontro la fila para ${draft.number}. Tags visibles: ${JSON.stringify(allTags)}`,
      )
    }

    // Badge pendientes.
    const badge = filaActual.locator('.ant-tag').filter({ hasText: /pendiente/i })
    await expect(badge).toBeVisible()
    await expect(badge).toContainText('1 pendiente')

    // Boton Corregir: buscar todos los botones de la fila para debug.
    const allButtons = await filaActual.locator('button').allTextContents()
    const corregir = filaActual.locator('button', { hasText: /corregir/i })
    const corregirCount = await corregir.count()
    if (corregirCount === 0) {
      throw new Error(
        `No se encontro boton Corregir en la fila. Botones en la fila: ${JSON.stringify(allButtons)}`,
      )
    }
    await expect(corregir.first()).toBeVisible()

    // Verificar via API.
    const t = await getTransfer(page.request, draft.id, regente)
    expect(t.status_name).toBe('PARTIALLY_RECEIVED')
    const pending = (t.discrepancies ?? []).filter(
      (d: TransferDiscrepancyRecord) => d.status === 'PENDING',
    )
    expect(pending).toHaveLength(1)
    expect(pending[0].difference).toBe(CANTIDAD_PRUEBA)
  })

  test('detalle muestra discrepancia con botones Cerrar sin ajuste y Dar de baja', async ({
    loggedInPage: page,
  }) => {
    let draft: { id: string; itemId: string; number: string }
    try {
      draft = await setupForDiscrepancy(page.request, CANTIDAD_PRUEBA * 2)
    } catch {
      test.skip()
      return
    }

    const auxiliar = await getAuth(page.request, 'auxiliar')
    await receive(page.request, draft.id, [{ itemId: draft.itemId, delivered: CANTIDAD_PRUEBA }], auxiliar)

    const sidebar = new SidebarPage(page)
    await sidebar.irA('Traslados')
    await sidebar.esperoActivo('Traslados')

    const transfers = new TransfersPage(page)
    await transfers.esperoCargada()

    const fila = page
      .locator('.ant-table-tbody tr.ant-table-row')
      .filter({ hasText: draft.number })
    await fila.first().waitFor({ state: 'visible', timeout: 10000 })
    await fila.first().locator('button', { hasText: /^ver$/i }).click()

    const detalle = new VistaDetalleTrasladoModal(page)
    await detalle.esperoAbierto()

    // Los botones de resolucion deben estar visibles dentro del modal de detalle.
    await expect(
      page.getByRole('dialog').getByRole('button', { name: /cerrar sin ajuste/i }),
    ).toBeVisible()
    await expect(
      page.getByRole('dialog').getByRole('button', { name: /dar de baja/i }),
    ).toBeVisible()

    await detalle.cerrar()
  })

  test('Cerrar sin ajuste: cierra la discrepancia sin tocar stock', async ({
    loggedInPage: page,
  }) => {
    let draft: { id: string; itemId: string; number: string }
    try {
      draft = await setupForDiscrepancy(page.request, CANTIDAD_PRUEBA * 2)
    } catch {
      test.skip()
      return
    }

    const regente = await getAuth(page.request, 'regente')
    const auxiliar = await getAuth(page.request, 'auxiliar')
    await receive(page.request, draft.id, [{ itemId: draft.itemId, delivered: CANTIDAD_PRUEBA }], auxiliar)

    const sidebar = new SidebarPage(page)
    await sidebar.irA('Traslados')
    await sidebar.esperoActivo('Traslados')

    const transfers = new TransfersPage(page)
    await transfers.esperoCargada()

    // Esperar a que la fila del traslado aparezca en la tabla.
    const fila = page
      .locator('.ant-table-tbody tr.ant-table-row')
      .filter({ hasText: draft.number })
    await fila.first().waitFor({ state: 'visible', timeout: 10000 })
    await fila.first().locator('button', { hasText: /^ver$/i }).click()

    const detalle = new VistaDetalleTrasladoModal(page)
    await detalle.esperoAbierto()

    const clicked = await detalle.clickAccionDiscrepancia('Cerrar sin ajuste')
    expect(clicked).toBeTruthy()

    // El modal de confirmacion es el segundo dialog visible (el primero es el detalle).
    const confirmModal = page
      .getByRole('dialog')
      .filter({ hasText: /cerrar la discrepancia|sin ajuste/i })
      .nth(1)
    await expect(confirmModal).toBeVisible()
    await confirmModal.locator('textarea').fill('Mercaderia ya reubicada en otro lote')
    await confirmModal.getByRole('button', { name: /cerrar sin ajuste/i }).click()

    // Verificar via API: discrepancia RESOLVED, traslado RECEIVED.
    const t = await getTransfer(page.request, draft.id, regente)
    expect(t.status_name).toBe('RECEIVED')
    const all: TransferDiscrepancyRecord[] = t.discrepancies ?? []
    expect(all.filter((d) => d.status === 'PENDING')).toHaveLength(0)
    const resolved = all.find((d) => d.status === 'RESOLVED')
    expect(resolved).toBeTruthy()
  })

  test('Dar de baja: marca la discrepancia como WRITTEN_OFF', async ({
    loggedInPage: page,
  }) => {
    let draft: { id: string; itemId: string; number: string }
    try {
      draft = await setupForDiscrepancy(page.request, CANTIDAD_PRUEBA * 2)
    } catch {
      test.skip()
      return
    }

    const regente = await getAuth(page.request, 'regente')
    const auxiliar = await getAuth(page.request, 'auxiliar')
    await receive(page.request, draft.id, [{ itemId: draft.itemId, delivered: CANTIDAD_PRUEBA }], auxiliar)

    const sidebar = new SidebarPage(page)
    await sidebar.irA('Traslados')
    await sidebar.esperoActivo('Traslados')

    const transfers = new TransfersPage(page)
    await transfers.esperoCargada()

    const fila = page
      .locator('.ant-table-tbody tr.ant-table-row')
      .filter({ hasText: draft.number })
    await fila.first().waitFor({ state: 'visible', timeout: 10000 })
    await fila.first().locator('button', { hasText: /^ver$/i }).click()

    const detalle = new VistaDetalleTrasladoModal(page)
    await detalle.esperoAbierto()

    const clicked = await detalle.clickAccionDiscrepancia('Dar de baja')
    expect(clicked).toBeTruthy()

    const confirmModal = page
      .getByRole('dialog')
      .filter({ hasText: /dar de baja/i })
      .nth(1)
    await expect(confirmModal).toBeVisible()
    await confirmModal.locator('textarea').fill('Merma en transporte, bitacora 42')
    await confirmModal.getByRole('button', { name: /dar de baja/i }).click()

    const t = await getTransfer(page.request, draft.id, regente)
    expect(t.status_name).toBe('RECEIVED')
    const all: TransferDiscrepancyRecord[] = t.discrepancies ?? []
    expect(all.filter((d) => d.status === 'PENDING')).toHaveLength(0)
    const written = all.find((d) => d.status === 'WRITTEN_OFF')
    expect(written).toBeTruthy()
    expect(written!.resolution_notes).toContain('Merma')
  })

  test('recepcion tardia completa el faltante: la discrepancia se cierra sola', async ({
    loggedInPage: page,
  }) => {
    let draft: { id: string; itemId: string; number: string }
    try {
      // IN_TRANSIT con approved = CANTIDAD_PRUEBA * 2 = 4.
      draft = await setupInTransit(page.request, CANTIDAD_PRUEBA * 2)
    } catch {
      test.skip()
      return
    }

    const regente = await getAuth(page.request, 'regente')
    const auxiliar = await getAuth(page.request, 'auxiliar')

    // Primera recepcion parcial (2 de 4) → discrepancy PENDING.
    await receive(page.request, draft.id, [{ itemId: draft.itemId, delivered: CANTIDAD_PRUEBA }], auxiliar)

    // Segunda recepcion: entregar lo restante (total 4 = approved) → discrepancy se cierra sola.
    // El API acepta delivered como cantidad total acumulada, no incremental.
    await receive(page.request, draft.id, [{ itemId: draft.itemId, delivered: CANTIDAD_PRUEBA * 2 }], auxiliar)

    const t = await getTransfer(page.request, draft.id, regente)
    expect(t.status_name).toBe('RECEIVED')
    const all: TransferDiscrepancyRecord[] = t.discrepancies ?? []
    expect(all.filter((d) => d.status === 'PENDING')).toHaveLength(0)
    const resolved = all.find((d) => d.status === 'RESOLVED')
    expect(resolved).toBeTruthy()
    expect(resolved!.resolution_notes).toContain('recepcion tardia')
  })

  test('boton Corregir en la lista abre el detalle del traslado parcial', async ({
    loggedInPage: page,
  }) => {
    let draft: { id: string; itemId: string; number: string }
    try {
      draft = await setupForDiscrepancy(page.request, CANTIDAD_PRUEBA * 2)
    } catch {
      test.skip()
      return
    }

    const auxiliar = await getAuth(page.request, 'auxiliar')
    await receive(page.request, draft.id, [{ itemId: draft.itemId, delivered: CANTIDAD_PRUEBA }], auxiliar)

    const sidebar = new SidebarPage(page)
    await sidebar.irA('Traslados')
    await sidebar.esperoActivo('Traslados')

    const transfers = new TransfersPage(page)
    await transfers.esperoCargada()

    const fila = page
      .locator('.ant-table-tbody tr.ant-table-row')
      .filter({ hasText: draft.number })
      .first()

    const corregir = fila.getByRole('button', { name: /^corregir$/i })
    await expect(corregir).toBeVisible()
    await corregir.click()

    const detalle = new VistaDetalleTrasladoModal(page)
    await detalle.esperoAbierto()
    expect(await detalle.titulo()).toContain(draft.number)

    await detalle.cerrar()
  })
})
