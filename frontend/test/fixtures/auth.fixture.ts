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

export const REGENTE = {
  name: 'regente',
  password: 'password',
}

export const test = base.extend<{ loggedInPage: Page }>({
  loggedInPage: async ({ page }, use) => {
    await loginViaApi(page, REGENTE.name, REGENTE.password)
    // goto('/Home') al final para resetear el estado del page: el
    // test anterior puede haber dejado un modal abierto, errores en
    // el form, o un filtro aplicado en la URL. Empezar desde /Home
    // garantiza que cada test corre contra un estado conocido.
    await page.goto('/Home')
    await page.waitForLoadState('domcontentloaded')
    await use(page)
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

