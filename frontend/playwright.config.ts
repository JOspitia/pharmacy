/**
 * Playwright config para los E2E del frontend.
 *
 * Estructura de la carpeta test/ (patron POM):
 *
 *   test/
 *   ├── playwright.config.ts       <- este archivo
 *   ├── fixtures/
 *   │   └── auth.fixture.ts        <- login automatico via storageState
 *   ├── pages/                     <- Page Objects: un archivo por pantalla
 *   │   ├── LoginPage.ts
 *   │   ├── SidebarPage.ts         <- navegacion comun (click en menu)
 *   │   ├── InventoriesPage.ts
 *   │   └── KardexPage.ts
 *   └── specs/                     <- test specs (lo que corre Playwright)
 *       ├── login.spec.ts
 *       ├── inventario.spec.ts
 *       └── kardex.spec.ts
 *
 * Convenciones POM:
 *   - Cada Page Object encapsula los selectores y acciones de UNA pantalla.
 *   - Los metodos se nombran como acciones del usuario: `login()`, `irAInventario()`.
 *   - Los specs NO contienen selectores: usan las Pages. Cambiar el DOM no rompe
 *     los tests, solo hay que actualizar el Page Object correspondiente.
 *
 * Ejecucion:
 *   npm run test:e2e             (headless)
 *   npm run test:e2e:headed      (con ventana visible)
 *   npm run test:e2e:ui          (modo UI con watch)
 */
import { defineConfig, devices } from '@playwright/test'

const BASE_URL = process.env.E2E_BASE_URL ?? 'http://localhost:5173'

export default defineConfig({
  testDir: './test/specs',
  // Timeout por test: 30s porque los E2E incluyen navegacion + clicks + waits.
  timeout: 30_000,
  // Timeout por accion individual (click, fill, etc): 5s. Si un locator
  // no aparece en 5s, la accion falla rapido en vez de colgarse hasta el
  // timeout global. Esto es lo que evita el efecto "se queda pegado 30s
  // y el comando nunca termina".
  expect: { timeout: 5_000 },

  // Reintentar una vez en caso de fallo. En CI los flakes son comunes y
  // un retry con setup fresco suele distinguirlos de bugs reales.
  // En local, cero retries: un fallo es un fallo, el dev lo ve rapido.
  retries: process.env.CI ? 2 : 0,

  // Correr los tests en paralelo. Cada spec es independiente.
  workers: process.env.CI ? 2 : undefined,

  // Parar la corrida al primer fallo. Sin esto, si un test cuelga
  // (por ejemplo, un click que nunca termina de resolverse), los demas
  // tests siguen corriendo y la salida es dificil de leer. Con
  // --max-failures=1 falla rapido y permite iterar.
  maxFailures: process.env.CI ? undefined : 1,

  // Reporter: 'list' local, 'github' en CI (mas denso para Actions).
  reporter: process.env.CI ? 'github' : 'list',

  use: {
    baseURL: BASE_URL,
    // Toma screenshot cuando un test falla: util para debug local.
    screenshot: 'only-on-failure',
    // Captura trace (network + DOM) cuando falla.
    trace: 'retain-on-failure',
    // Timeout por cada interaccion (click, fill, goto, etc): 10s.
    // Si el browser no responde, Playwright falla rapido en vez de
    // esperar el timeout global del test.
    actionTimeout: 10_000,
    // Tiempo maximo que Playwright espera a que la pagina "termine
    // de cargar" (networkidle). Si la app tiene un fetch que nunca
    // termina, este limite lo corta.
    navigationTimeout: 15_000,
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  // No levanto el dev server desde Playwright: el script npm asume que
  // el dev server ya esta corriendo (frontend + backend en Docker).
  // Para un setup autocontenido se puede usar webServer aqui, pero
  // nuestro docker-compose ya provee los servicios.

  // globalTeardown: matamos cualquier proceso chrome/chromium que
  // haya quedado huerfano de una corrida anterior. Esto evita el
  // clasico problema de "los tests se quedan pegados" cuando un test
  // crashea y deja browsers zombies.
  // Nota: globalTeardown espera un PATH a archivo, no una funcion inline.
  globalTeardown: './test/fixtures/global-teardown.ts',
})
