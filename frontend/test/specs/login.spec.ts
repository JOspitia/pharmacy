/**
 * Specs de login parametrizados por rol.
 *
 * Estructura: un solo bloque de codigo que itera sobre los 5 roles
 * del sistema. Antes habia un test especifico para el regente, pero
 * queremos verificar que los 5 pueden hacer login y obtienen su
 * rol correcto.
 *
 * Decisiones de diseño:
 *   - `forEach` con un objeto por rol (no `test.describe.each`) porque
 *     permite nombrar el test de forma legible (admin vs regente vs ...)
 *     y agregar logs de contexto si falla uno solo.
 *   - El helper `loginViaApi` se reusa: ya hace POST + addInitScript +
 *     goto('/Home') y maneja el 401.
 */
import { test, expect } from '@playwright/test'

/**
 * Roles del sistema (seccion 3 del enunciado). La contrasena es la misma
 * para todos ("password") porque el UserSeeder los crea con hash comun.
 * El backend valida contra bcrypt, asi que cambiar la contrasena aca
 * requiere tocar el seeder.
 */
const ROLES = [
  { name: 'admin', rolEsperado: 'admin' },
  { name: 'regente', rolEsperado: 'regente_farmacia' },
  { name: 'auxiliar', rolEsperado: 'auxiliar_farmacia' },
  { name: 'medico', rolEsperado: 'medico' },
  { name: 'auditor', rolEsperado: 'auditor' },
] as const

test.describe('API /api/login parametrizado por rol', () => {
  for (const { name, rolEsperado } of ROLES) {
    test(`login de ${name} devuelve token y rol ${rolEsperado}`, async ({ request }) => {
      const response = await request.post('/api/login', {
        data: { name, password: 'password' },
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
      })

      expect(response.status(), `login de ${name} debe responder 200`).toBe(200)
      const body = await response.json()
      expect(body.token, `login de ${name} debe devolver un token`).toBeTruthy()
      expect(body.user.name).toBe(name)
      expect(body.user.role).toBe(rolEsperado)
    })
  }

  test('login con contrasena invalida devuelve 422', async ({ request }) => {
    const response = await request.post('/api/login', {
      data: { name: 'regente', password: 'wrong-password' },
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
    })

    expect(response.status()).toBe(422)
    const body = await response.json()
    expect(body.token).toBeUndefined()
    expect(body.message).toBeTruthy()
  })
})
