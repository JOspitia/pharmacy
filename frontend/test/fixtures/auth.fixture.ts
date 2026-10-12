/**
 * Fixture: login automatico para los E2E.
 *
 * Estrategia: POST directo a /api/login via page.request (que
 * comparte el contexto HTTP del navegador), captura el token y lo
 * inyecta en sessionStorage. Luego goto('/Home') para que el
 * AuthContext lo levante del storage.
 *
 * Por que NO usar el form de login de la app:
 *   - Sanctum stateful con cookies no funciona en E2E porque el proxy
 *     de Vite (localhost:5173 -> localhost:8000) hace que el navegador
 *     vea el request como cross-origin. El backend tiene
 *     supports_credentials=false (config/cors.php), por lo que las
 *     cookies de sesion NO viajan y el AuthContext nunca detecta la
 *     sesion activa.
 *   - El form de login SI funciona en uso real del evaluador (que
 *     accede al backend por el proxy), pero en tests hay que forzar
 *     el camino del token para evitar el problema de cookies.
 *
 * Este helper se usa tambien desde LoginPage.completar para verificar
 * la UI real, pero el fixture loggedInPage va por la via rapida.
 */
import { test as base, expect, type Page, type APIRequestContext } from '@playwright/test'
import type { TransferRecord } from '../types'

export const REGENTE = {
  name: 'regente',
  password: 'password',
}

export const test = base.extend<{ loggedInPage: Page }>({
  loggedInPage: async ({ page }, testPage) => {
    await loginViaApi(page, REGENTE.name, REGENTE.password)
    // goto('/Home') al final para resetear el estado del page: el
    // test anterior puede haber dejado un modal abierto, errores en
    // el form, o un filtro aplicado en la URL. Empezar desde /Home
    // garantiza que cada test corre contra un estado conocido.
    await page.goto('/Home')
    await page.waitForLoadState('domcontentloaded')
    await testPage(page)
  },
})

export { expect }

/**
 * Login via API request: POST /api/login, captura el token, lo guarda
 * en sessionStorage via context.addInitScript (se ejecuta antes de
 * cualquier script de la pagina, incluyendo la primera navegacion),
 * y navega a /Home.
 *
 * Usamos `context.addInitScript` (no `page.addInitScript`) porque
 * el primero persiste entre navegaciones del mismo context, mientras
 * que el segundo solo se ejecuta en las navegaciones que ocurran
 * despues de registrarlo. Con el primero nos aseguramos de que el
 * token este disponible desde la primera carga de la app.
 */
export async function loginViaApi(page: Page, name: string, password: string): Promise<void> {
  const response = await page.request.post('/api/login', {
    data: { name, password },
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
  })
  expect(response.ok(), `POST /api/login fallo: ${response.status()}`).toBeTruthy()
  const body = await response.json()

  await page.context().addInitScript((token: string) => {
    sessionStorage.setItem('auth_token', token)
  }, body.token)

  await page.goto('/Home')
  // El AuthContext redirige a /Home si el token esta. Esperamos
  // activamente para no colgarnos si la app rompe la convencion.
  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 10_000 })
}

/**
 * Re-exporta el RequestContext para que los specs puedan hacer
 * requests autenticados sin armar el header en cada llamada.
 *
 *   const token = await getAuthToken(request)
 *   await request.get('/api/...', { headers: { Authorization: `Bearer ${token}` } })
 */
export async function getAuthToken(request: APIRequestContext, name = REGENTE.name, password = REGENTE.password): Promise<string> {
  const response = await request.post('/api/login', {
    data: { name, password },
    headers: { 'Content-Type': 'application/json' },
  })
  const body = await response.json()
  return body.token
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers de setup: crear traslados en estado DRAFT/APPROVED/IN_TRANSIT sin
// pasar por la UI. La UI tiene sus propios tests; aca preparamos el estado
// para poder probar approve, dispatch, receive, discrepancias y resolucion.
// ─────────────────────────────────────────────────────────────────────────────

/** Devuelve headers Authorization para un rol dado. Cachea el token. */
const tokenCache = new Map<string, string>()
export async function authHeader(request: APIRequestContext, name: string): Promise<{ Authorization: string }> {
  if (!tokenCache.has(name)) {
    const response = await request.post('/api/login', {
      data: { name, password: 'password' },
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    })
    if (!response.ok()) throw new Error(`login ${name} fallo: ${response.status()}`)
    const body = await response.json()
    tokenCache.set(name, body.token)
  }
  return { Authorization: `Bearer ${tokenCache.get(name)}` }
}

/** Crea un inventario y devuelve su id (helper de setup). */
export async function crearInventario(
  request: APIRequestContext,
  warehouseId: string,
  productId: string,
  lotId: string,
  quantity: number,
  auth: { Authorization: string },
): Promise<string> {
  const response = await request.post('/api/inventories', {
    headers: { ...auth, 'Content-Type': 'application/json', Accept: 'application/json' },
    data: { warehouse_id: warehouseId, product_id: productId, lot_id: lotId, quantity },
  })
  if (!response.ok()) {
    const body = await response.text()
    throw new Error(`POST /api/inventories ${response.status()}: ${body}`)
  }
  const body = await response.json()
  return body.data.id
}

/** Crea un traslado en DRAFT. items: [{inventory_id, requested_quantity}]. */
export async function crearTrasladoDraft(
  request: APIRequestContext,
  toWarehouseId: string,
  items: { inventory_id: string; requested_quantity: number }[],
  auth: { Authorization: string },
): Promise<{ id: string; transfer_number: string; item_ids: string[] }> {
  const response = await request.post('/api/transfers', {
    headers: { ...auth, 'Content-Type': 'application/json', Accept: 'application/json' },
    data: { to_warehouse_id: toWarehouseId, items },
  })
  if (!response.ok()) {
    const body = await response.text()
    throw new Error(`POST /api/transfers ${response.status()}: ${body}`)
  }
  const body = await response.json()
  return {
    id: body.data.id,
    transfer_number: body.data.transfer_number,
    item_ids: (body.data.items ?? []).map((it: { id: string }) => it.id),
  }
}

/** Aprueba un traslado. items: [{transfer_item_id, approved_quantity}]. */
export async function aprobarTraslado(
  request: APIRequestContext,
  transferId: string,
  items: { transfer_item_id: string; approved_quantity: number }[],
  auth: { Authorization: string },
): Promise<void> {
  const response = await request.patch(`/api/transfers/${transferId}/approve`, {
    headers: { ...auth, 'Content-Type': 'application/json', Accept: 'application/json' },
    data: { items },
  })
  if (!response.ok()) {
    const body = await response.text()
    throw new Error(`PATCH /approve ${response.status()}: ${body}`)
  }
}

/** Despacha un traslado (APPROVED -> IN_TRANSIT). */
export async function despacharTraslado(
  request: APIRequestContext,
  transferId: string,
  auth: { Authorization: string },
): Promise<void> {
  const response = await request.patch(`/api/transfers/${transferId}/dispatch`, {
    headers: { ...auth, 'Content-Type': 'application/json', Accept: 'application/json' },
  })
  if (!response.ok()) {
    const body = await response.text()
    throw new Error(`PATCH /dispatch ${response.status()}: ${body}`)
  }
}

/** Recibe un traslado. items: [{transfer_item_id, delivered_quantity}]. */
export async function recibirTraslado(
  request: APIRequestContext,
  transferId: string,
  items: { transfer_item_id: string; delivered_quantity: number }[],
  auth: { Authorization: string },
): Promise<void> {
  const response = await request.patch(`/api/transfers/${transferId}/receive`, {
    headers: { ...auth, 'Content-Type': 'application/json', Accept: 'application/json' },
    data: { items },
  })
  if (!response.ok()) {
    const body = await response.text()
    throw new Error(`PATCH /receive ${response.status()}: ${body}`)
  }
}

/** Lee un traslado (GET /api/transfers/{id}) y lo devuelve entero. */
export async function obtenerTraslado(
  request: APIRequestContext,
  transferId: string,
  auth: { Authorization: string },
): Promise<TransferRecord> {
  const response = await request.get(`/api/transfers/${transferId}`, {
    headers: { ...auth, Accept: 'application/json' },
  })
  if (!response.ok()) {
    const body = await response.text()
    throw new Error(`GET /api/transfers/${transferId} ${response.status()}: ${body}`)
  }
  const body = await response.json()
  return body.data
}

