/**
 * globalTeardown - mata browsers zombies de corridas anteriores.
 *
 * Por que este archivo existe: cuando un test crashea, el browser de
 * Playwright a veces queda vivo en el sistema, ocupando memoria y
 * bloqueando el puerto del dev server. Sin cleanup, el siguiente
 * `npx playwright test` puede colgarse intentando hablar con un
 * browser muerto o quedarse sin handles.
 *
 * pkill -9 mata los procesos por nombre: no es preciso pero es
 * suficiente para un cleanup de CI/dev. En Windows no hay pkill, asi
 * que el comando es un no-op gracioso alli (Playwright ya maneja el
 * cleanup en su propio teardown interno en ese caso).
 */
export default async function globalTeardown(): Promise<void> {
  const { exec } = await import('node:child_process')
  await new Promise<void>((resolve) => {
    // -9 = SIGKILL, -f = match por linea de comando completa.
    // Las redirecciones a /dev/null y el `; true` final evitan que un
    // pkill sin matches devuelva exit code 1.
    exec(
      "pkill -9 -f 'chrome.*--headless' 2>/dev/null; pkill -9 -f 'playwright_chromium' 2>/dev/null; true",
      () => resolve(),
    )
  })
}
