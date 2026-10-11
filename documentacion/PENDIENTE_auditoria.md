# Auditoría de requisitos — FARTMAR IPS

Generado contra: `Prueba_Tecnica_Senior_FARTMAR_Candidato (1) (1).docx.pdf`
Fecha: 2026-10-11
Stack actual: PHP/Laravel + React + Ant Design + Docker + SQL Server 2025 + GitHub Actions + CI con SQL Server en runners

---

## Changelog del estado del sistema

> Esta sección documenta el progreso de implementación desde la auditoría inicial
> (2026-10-09). Las HUs marcadas con ✅ están en `main` y operativas.

| HU | Módulo | Estado | Commits |
|----|--------|--------|---------|
| HU-01 | Productos | ✅ | — |
| HU-03 | Bodegas | ✅ | — |
| HU-04 | Alertas (90 días + stock mínimo) | ✅ | (parcial → completo en HU-08) |
| HU-05 v1 | Lotes (con bodega y stock) | ❌ deprecada | reemplazada por v2 |
| HU-05 v2 | Lotes (sin bodega, sin stock; derivado de `inventories`) | ✅ | — |
| HU-06 | Reportes | ✅ | — |
| HU-07 | Auth + Roles + Matriz de permisos | ✅ | 8df435c a e9837fc |
| HU-08 | Kardex + Inventarios | ✅ | d039652 a 85418ae |
| HU-09 | Traslados entre bodegas | ⏳ próximo | modelos y migraciones listos, falta TransferService + endpoints |
| HU-10 | Dispensación | ❌ pendiente | siguiente prioridad después de traslados |
| — | AI (asistente) | ❌ pendiente | fuera de alcance por ahora |

---

## Resumen ejecutivo (actualizado)

El sistema tiene un backend estructurado (Service pattern, Form Requests, API Resources)
y una base sólida de **139 tests de modelos + 93 tests E2E con Playwright**. La matriz
de permisos por rol (HU-07) y el Kardex con existencias inmutables (HU-08) están
implementados y verificados. Las pantallas de Inventario y Kardex en el frontend usan
Ant Design con paneles de alerta `Card + Statistic + List`.

**Lo que existe:** Auth con Sanctum, roles + permisos con `EnsureModule`, Productos, Bodegas,
Lotes (v2 sin stock propio), Kardex inmutable, Inventarios, Alertas (vencidos, por vencer,
bajo mínimo), Reportes.

**Lo que falta:** Traslados (modelos listos, falta servicio + endpoints), Dispensación,
AI assistant, y pantallas frontend de esos módulos.

---

## Lo que falta del núcleo (leer esto primero)

Dos de las tres cosas que son el sistema ya están hechas. **Kardex (RN-06) ✅** se
implementó en HU-08 con movimientos inmutables, trigger de BD, y consulta filtrable.
Faltan traslados y dispensación, que son el **35% del peso de evaluación** entre las dos.

| Módulo | Estado | Reglas | Peso |
|---|---|---|---|
| **Kardex** | ✅ **IMPLEMENTADO** (HU-08) | RN-06 | **15%** |
| **Traslados entre bodegas** | ⏳ en curso (modelos y migraciones listos) | RN-07, RN-08 | **15%** |
| **Dispensación** | ❌ pendiente | RN-01 a RN-05, RN-09 | **20%** |

### Kardex — lo que se implementó (HU-08)

- **Modelos:** `Movement` (inmutable, sin update ni delete, sin factory para tests),
  `Inventory` (terna `warehouse × product × lot`, RN-01).
- **Migraciones:** 11 nuevas migraciones, incluyendo índices únicos para RN-01 y
  trigger `trg_movements_inmutable` en la base que rechaza UPDATE/DELETE.
- **Servicios:** `MovementService` (escritura de movimientos + actualización de
  `inventories`), `InventoryService` (consulta filtrable), `BodegaService`
  (lotes en bodega, ahora incluye stock=0 para reabastecer, CA-12).
- **Controllers:** `MovementController` (index, store; PUT/PATCH/DELETE devuelven 405
  explícito), `InventoryController` (index con `stock_filter=zeroed` y `with_expiring=90`).
- **Excepciones:** `LotExpiredException`, `InsufficientStockException` y compañía,
  con `Handler.php` que las serializa a HTTP 422 con el mensaje del dominio.
- **Routes:** `/api/movements` (GET/POST), `/api/movements/{id}` (PUT/PATCH/DELETE → 405),
  `/api/inventories` (GET con filtros).
- **Tests PHP:** KardexTest, InventoryTest, BodegaServiceTest, MovementServiceTest,
  ExceptionTest, ~50 tests nuevos.
- **Frontend:** `KardexList` con tabla filtrable, `InventoriesList` con tabla + 3 cards
  de alerta (vencidos / por vencer / bajo mínimo) usando `Card + Statistic + List` de
  Ant Design, `MovementFormModal` para registrar movimientos.
- **E2E Playwright:** `kardex.spec.ts` (3 tests), `inventario.spec.ts` (4 tests).

### Próximo: Traslados

Modelos y migraciones ya están listos (en `main`). Falta el `TransferService` con la
máquina de 7 estados y los endpoints REST. El kardex ya está escribiendo movimientos
de tipo `TRANSFER_IN` / `TRANSFER_OUT`, así que el patrón de movimientos está claro.

---

## Parte A — Backend y base de datos

### Modelo de datos

| # | Requisito | Estado | Detalle |
|---|-----------|--------|---------|
| A-1 | CHECK constraint `stock >= 0` en `inventories.quantity` | ✅ | CHECK constraint en migrate. Reemplaza al original de `lots` (deprecado en HU-05 v2). MySQL/SQL Server rechaza INSERT/UPDATE con quantity negativo. |
| A-2 | CHECK constraint `stock_minimo >= 0` en `bodegas` | ✅ | Columna `stock_minimo integer default 0` en migrate. |
| A-3 | Índices únicos para RN-01 (existencia = bodega + producto + lote) | ✅ | **IMPLEMENTADO en HU-08.** Tabla `inventories` con `UNIQUE(warehouse_id, product_id, lot_id)`. La existencia es exactamente la terna. |
| A-4 | Lotes sin bodega ni stock propios (RN-01 con kardex) | ✅ | Tabla `lots` sin `warehouse_id` ni `stock`. Ambos son derivados de `inventories`. Ver HU-05 v2. |

### Modelos

| Modelo | Estado | Motivo | Tabla / Detalle |
|--------|--------|--------|-----------------|
| `Movement` | ✅ | RN-06: movimiento inmutable por cada cambio de stock | Tipos: `ENTRY`, `TRANSFER_IN`, `TRANSFER_OUT`, `DISPENSATION`, `WITHDRAWAL`, `ADJUSTMENT`. Inmutable por diseño (sin update ni delete en código) + trigger `trg_movements_inmutable` en BD. |
| `Inventory` | ✅ | RN-01: existencia = bodega × producto × lote | Tabla `inventories` con UNIQUE constraint. |
| `Alert` (panel) | ✅ | RN-11: alertas de stock mínimo y vencimiento | `InventoryController::index` devuelve `alerts: { expired, expiring_soon, low_stock }` en cada respuesta. |
| `Patient` | ❌ | RN-04: toda dispensación asociada a paciente | Pacientes sintéticos requeridos |
| `Prescription` | ❌ | RN-04: verificar prescripción vigente antes de dispensar | Con campos: patient_id, product_id, dosis, fecha_vigencia, cantidad_total, cantidad_dispensada |
| `Transfer` | ⏳ modelos listos, falta servicio | RN-07: máquina de estados del traslado | Estados: BORRADOR→SOLICITADO→APROBADO→EN_TRANSITO→RECIBIDO→RECIBIDO_PARCIAL→ANULADO |
| `TransferItem` | ⏳ modelos listos | Items de un traslado (producto + cantidad) | LineItems del traslado |
| `MovementReason` | ❌ | Motivo del ajuste RN-06 | Catálogo para ajustes |
| `AuditLog` | ❌ | RN-10: bitácora de quién consultó qué paciente | Acceso a datos sensibles |

### Roles y autorización — IMPLEMENTADO ✅

El brief NO pide un módulo de permisos. Pide *"autorización por rol con Policies o Gates"*.
Son cosas distintas: los permisos serían configurables en runtime desde una tabla; la
autorización por rol es un mapa fijo de rol → capacidades. El brief define la tabla de
roles de forma cerrada en su sección 3, así que se implementó con Gates.

**Lo implementado:**

| Pieza | Archivo | Estado |
|-------|---------|--------|
| Tabla roles | migración `000005` | ✅ |
| Tabla modules | migración `000006` | ✅ |
| Tabla permissions | migración `000009` | ✅ 28 CRUD + 12 capacidades |
| Tabla role_permissions | migración `000010` | ✅ pivot con CASCADE |
| Users.role → role_id | migración `000008` | ✅ ENUM → FK |
| drop role_modules | migración `000011` | ✅ tabla eliminada |
| RoleSeeder | `seeders/RoleSeeder.php` | ✅ 5 roles |
| ModuleSeeder | `seeders/ModuleSeeder.php` | ✅ 7 módulos en inglés |
| PermissionSeeder | `seeders/PermissionSeeder.php` | ✅ catálogo completo |
| RolePermissionSeeder | `seeders/RolePermissionSeeder.php` | ✅ matriz + sync() idempotente |
| Gate::before sin bypass | `AuthServiceProvider` | ✅ admin solo tiene lo que la matriz le da |
| hasPermission memoizado | `User.php` | ✅ 1 SQL para 30 verificaciones |
| EnsureModule | `Middleware/EnsureModule.php` | ✅ alias `module:` aplicado a todas las rutas |
| EnsureRole eliminado | — | ✅ |
| Module::permissions() HasMany | `Module.php` | ✅ (no belongsToMany) |
| UserResource | `UserResource.php` | ✅ `'role' => $this->role?->name` |

**Nota para el frontend:** cuando se necesite renderizar botones condicionales sin
quemar nombres de rol, exponer `'permissions' => $this->nombresPermiso()` en
UserResource. Eso requiere coordinar con el equipo frontend (rompe /api/user).

#### Matriz de acceso por rol — sistema completo (actualizada 2026-10-11)

Aplica el criterio **leer vs. escribir**, no "un rol por módulo". Si se usara un rol por
módulo se le quitaría al auxiliar el inventario, que el brief explícitamente le da.

**C = can_create · R = can_read · U = can_update · D = can_delete · — = sin acceso**

| Módulo | auxiliar | regente | medico | auditor | admin |
|---|---|---|---|---|---|
| **Bodegas** | R | C R U D | R | R | C R U D |
| **Grupos** | R | C R U D | — | R | C R U D |
| **Productos** | R | C R U D | R | R | C R U D |
| **Lotes** (HU-05 v2) | R U (sin delete) | C R U D | R | R | **R** (sin C/U/D) |
| **Kardex** movimientos (HU-08) | R | R C | R | R | R C |
| **Kardex** movimientos PUT/PATCH/DELETE | — (405) | — (405) | — (405) | — (405) | — (405) |
| **Inventarios** | R | R | R | R | R |
| **Sensores** | — | R | — | R | R |
| **Reportes** | R | R | R | R | R |
| **Usuarios** | — | — | — | — | C R U D |
| **Dispensación** (pendiente) | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Traslados** crear / recibir (pendiente) | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Traslados** aprobar / anular (pendiente) | ❌ | ✅ | ❌ | ❌ | ❌ |
| **Prescripciones** (pendiente) | ❌ | ❌ | ✅ crear | ❌ | ❌ |
| **Pacientes** (pendiente) | ❌ | ❌ | ✅ | ✅ enmascarado | ❌ |

> **Cambio importante HU-08:** admin y regente pueden **crear** movimientos de Kardex
> (POST `/api/movements`), no solo leer. Antes del HU-08, la fila "Kardex" solo
> tenía `R`. La creación de movimientos es la única acción de escritura en el
> Kardex — no hay update ni delete por diseño (RN-06).
>
> **Cambio importante HU-05 v2:** admin ahora tiene solo `R` en lotes (sin
> create/update/delete). El admin no carga stock ni corrige lotes; eso es
> responsabilidad del regente. La fila original decía "admin ✅ leer", que
> era correcto pero impreciso — el cambio de HU-05 v2 lo formaliza.

**Capacidades globales (`matrizCapacidades`):**

| Capacidad | admin | regente | auxiliar | medico | auditor |
|---|---|---|---|---|---|
| `gestionar-usuarios` | ✅ | — | — | — | — |
| `gestionar-catalogos` | ✅ | — | — | — | — |
| `dispensar` | — | ✅ | ✅ | — | — |
| `consultar-inventario` | — | ✅ | ✅ | ✅ | ✅ |
| `crear-traslado` | — | ✅ | ✅ | — | — |
| `recibir-traslado` | — | ✅ | ✅ | — | — |
| `aprobar-traslado` | — | ✅ | — | — | — |
| `autorizar-control-especial` | — | ✅ | — | — | — |
| `ajustar-inventario` | — | ✅ | — | — | — |
| `crear-prescripcion` | — | — | — | ✅ | — |
| `consultar-pacientes` | — | — | — | ✅ | — |
| `consultar-pacientes-enmascarados` | — | — | — | — | ✅ |

La tabla completa vive en `PERMISOS.md` (en la raíz del repo) y se valida con
**23 tests E2E** en `frontend/test/specs/permisos.spec.ts`.

Notas de diseño:

- **El médico no ve almacén.** La prescripción es un documento clínico que define el *techo*
  de lo que se administra; la dispensación es la que consume stock contra ese techo (RN-04).
  Recetar no requiere saber existencias.
- **El médico sí necesita elegir un producto** para recetar. Eso es catálogo de referencia
  (id, nombre, concentración, unidad), no almacén. Se resuelve con el endpoint
  `GET api/products/form-data`, que ya existe con el mismo patrón que `groups/select` y
  `units/select`.
- **Aprobar traslados es solo del regente** (RN-07), y el auxiliar nunca puede (RN-08
  se prohíbe explícitamente que quien solicita apruebe).
- **Supuesto documentado:** se eligió Gate sobre Policy. Una Policy decide sobre un modelo
  concreto (`$user->can('update', $product)`); acá las preguntas son de capacidad
  (`¿puede aprobar un traslado?`). Si más adelante se necesita "este regente puede aprobar
  *este* traslado en particular", corresponde una Policy.

#### Hallazgo: las capacidades nunca se exigían en los endpoints — ✅ RESUELTO (HU-09)

**Síntoma:** el middleware `EnsureModule` solo conoce el verbo HTTP. Traduce
`PATCH → transfers.can_update`, y el auxiliar **tiene** `can_update` sobre `transfers`
(el seeder le da `['can_read','can_create','can_update']`). Como todos los verbos de la
máquina de estados de traslados son PATCH, el auxiliar pasaba el filtro para aprobar,
despachar, anular y resolver discrepancias.

Peor: las capacidades existían en la base y en `PERMISOS.md`, pero **ningún código las
consultaba**. `Gate::before` estaba registrado y `PermissionCapabilitiesTest` verificaba
que `$user->can('aprobar-traslado')` devolviera el booleano correcto — o sea, probaba que
el Gate *respondía bien*, no que *alguien preguntara*. Los 153 tests pasaban con el hueco
abierto.

| Acción | Ruta | Exigía realmente | ¿Tenía auxiliar? | Antes del fix |
|---|---|---|---|---|
| Aprobar | `PATCH /approve` | `transfers.can_update` | sí | **podía** |
| Despachar | `PATCH /dispatch` | `transfers.can_update` | sí | **podía** |
| Anular | `PATCH /cancel` | `transfers.can_update` | sí | **podía** |
| Resolver discrepancia | `PATCH /discrepancies/{id}/resolve` | `transfers.can_update` | sí | **podía** |

**Resolución:** cada acción autoritativa consulta su capacidad en el service, que es donde
vive la lógica de negocio:

```php
// back/app/Services/TransferService.php
Gate::forUser($usuario)->authorize('aprobar-traslado');      // en aprobar()
Gate::forUser($usuario)->authorize('despachar-traslado');    // en despachar()
Gate::forUser($usuario)->authorize('anular-traslado');       // en anular()
Gate::forUser($usuario)->authorize('resolver-discrepancia'); // en resolverDiscrepancia()
```

Se usa `Gate::forUser()->authorize()` y no `$usuario->authorize()` porque el trait
`Authorizable` de esta versión de Laravel expone `can()`, `cant()` y `cannot()`, pero **no**
`authorize()`; llamar al método inexistente daba `BadMethodCallException` (HTTP 500).
`Gate::authorize()` lanza `AuthorizationException`, que el handler de Laravel traduce a
**403** — a diferencia de `KardexStateException`, que renderiza 422 y significaría "error de
negocio" en vez de "no autorizado".

**Verificado** contra la base de desarrollo: el auxiliar queda `transfers.can_update = SI`
pero `aprobar / anular / resolver = no`, y `Gate::forUser($auxiliar)->authorize(...)` lanza
`AuthorizationException` en las cuatro capacidades, mientras el regente pasa en todas.

**Cobertura pendiente:** `crear-traslado` y `recibir-traslado` tampoco se exigen en el
service. Hoy no abren ningún hueco (los dos roles con permiso CRUD sobre `transfers` ya los
tienen en la matriz), pero quedan como deuda del mismo tipo.

#### Hallazgo: el Gate::before de admin rompía RN-05 — ✅ RESUELTO

El hallazgo documentaba que `Gate::before` con bypass de admin hacía que `admin`
pasara **toda** capacidad sin enumerarla, evadiendo RN-05 (control especial
requiere regente) y RN-08 (segregación de traslados).

**Resolución:** se eliminó el bypass de `Gate::before`. Admin tiene exactamente
los permisos que la matriz le asigna. La matriz del Anexo A le da solo lectura
en stock y escritura en administración. RN-05 y RN-08 se cumplen sin código de
negocio adicional.

### Reglas de negocio

| # | Regla | Estado | Implementación |
|---|-------|--------|----------------|
| RN-01 | Existencia = bodega × producto × lote (ternaria) | ✅ | **IMPLEMENTADO en HU-08.** Tabla `inventories` con UNIQUE(warehouse_id, product_id, lot_id). El stock vive solo en `inventories.quantity`, no en el lote. El lote puede existir en N bodegas. |
| RN-01b | Lote vencido no se dispensa ni traslada | ❌ | Pendiente. La validación ira en DispensarService y TransferService. El lote ya expone `expiration_date` y `expiration_status` derivado en el `LotResource`. |
| RN-02 | FEFO en dispensación | ❌ | Pendiente. Se hara en DispensarService: ordenar lots por `expiration_date ASC` donde `status=true AND quantity>0`. |
| RN-03 | Stock nunca negativo (concurrencia) | ✅ | **IMPLEMENTADO en HU-08.** CHECK constraint en `inventories.quantity >= 0` rechaza INSERT/UPDATE. Ademas `MovementService` usa `SELECT ... FOR UPDATE` dentro de transaccion para serializar la escritura. |
| RN-04 | Dispensación vinculada a paciente + prescripción vigente | ❌ | Pendiente. |
| RN-05 | Control especial requiere autorización de regente | ⚠️ Parcial | Columna `requires_authorization boolean default false` en products. Falta la lógica de doble usuario en dispensación. |
| RN-06 | Kardex: movimientos inmutables | ✅ | **IMPLEMENTADO en HU-08.** Modelo `Movement` sin metodos `update` ni `delete` (inmutabilidad por diseno). Trigger de BD `trg_movements_inmutable` que rechaza UPDATE/DELETE a nivel SQL. El controller devuelve 405 explicito en PUT/PATCH/DELETE con el mensaje "El Kardex es inmutable. Para corregir un movimiento registre un ajuste con motivo." |
| RN-07 | Máquina de estados del traslado | ❌ | Pendiente. Modelos `Transfer` y `TransferItem` ya estan creados, falta `TransferService` + endpoints REST. |
| RN-08 | Segregación: quien solicita no aprueba | ❌ | Pendiente. Se validara en `TransferService::approve()`: `if ($transfer->requested_by === auth()->id()) throw new BusinessRuleException(...)`. |
| RN-09 | Idempotencia en dispensación | ❌ | Pendiente. Token de idempotencia en headers, guardado en BD. |
| RN-10 | Datos de paciente: acceso por rol + enmascarado + bitácora | ❌ | Pendiente. |
| RN-11 | Alertas: vencimiento ≤90 días + stock mínimo | ✅ | **IMPLEMENTADO en HU-08.** `InventoryController::index` devuelve `alerts: { expired, expiring_soon, low_stock }` en cada respuesta. `InventoriesList.tsx` los muestra en 3 cards de Ant Design (`Card + Statistic + List`). Filtros: `with_expiring=90` para "por vencer" y comparacion `quantity < min_stock` para "bajo mínimo". |

### Máquina de estados del traslado (RN-07)

```
BORRADOR → SOLICITADO → APROBADO → EN_TRANSITO → RECIBIDO
                            ↓              ↓
                      ANULADO       RECIBIDO_PARCIAL
                                        ↓
                                   ANULADO
```

| Transición | Permiso | Acción de stock |
|------------|---------|----------------|
| BORRADOR → SOLICITADO | auxiliar_farmacia | Ninguna |
| SOLICITADO → APROBADO | regente_farmacia (no quien solicitó) | Ninguna |
| APROBADO → EN_TRANSITO | regente_farmacia | Descontar stock en bodega origen |
| EN_TRANSITO → RECIBIDO | auxiliar_farmacia (destino) | Agregar stock en bodega destino |
| EN_TRANSITO → RECIBIDO_PARCIAL | auxiliar_farmacia (destino) | Stock parcial en destino |
| RECIBIDO_PARCIAL → ANULADO | regente_farmacia | Devolver stock no recibido al origen |
| Cualquiera → ANULADO | regente_farmacia | Revertir si ya se tocó stock |

### API REST — estado de los endpoints

| Método | URI | Estado | Controlador | Descripción |
|--------|-----|--------|-------------|-------------|
| GET | /api/movements | ✅ | `MovementController@index` | Historial filtrable por tipo, bodega, lote, fechas. Soporta `?type=ENTRY&from_date=...&to_date=...&warehouse_id=...&lot_id=...` |
| POST | /api/movements | ✅ | `MovementController@store` | Crear movimiento (solo admin y regente). Tipos: `ENTRY`, `ADJUSTMENT`, etc. |
| PUT/PATCH/DELETE | /api/movements/{id} | ✅ 405 | `MovementController@update/destroy` | **Inmutabilidad RN-06.** Devuelven 405 con mensaje explicito. |
| GET | /api/inventories | ✅ | `InventoryController@index` | Existencias con filtros `?stock_filter=zeroed&with_expiring=90&warehouse_id=...&product_id=...&lot_id=...`. Devuelve `alerts: { expired, expiring_soon, low_stock }` |
| POST | /api/dispense | ❌ | DispenseController | Dispensar a paciente con FEFO |
| GET | /api/dispense/{id} | ❌ | DispenseController | Consultar dispensación |
| POST | /api/transfers | ❌ | TransferController | Crear traslado (BORRADOR→SOLICITADO) |
| GET | /api/transfers | ❌ | TransferController | Listar traslados |
| GET | /api/transfers/{id} | ❌ | TransferController | Ver traslado |
| PATCH | /api/transfers/{id}/approve | ❌ | TransferController | Aprobar (RN-08) |
| PATCH | /api/transfers/{id}/dispatch | ❌ | TransferController | Despachar (EN_TRANSITO, descuenta stock) |
| PATCH | /api/transfers/{id}/receive | ❌ | TransferController | Recibir (RECIBIDO, agrega stock) |
| PATCH | /api/transfers/{id}/receive-partial | ❌ | TransferController | Recibir parcial |
| PATCH | /api/transfers/{id}/cancel | ❌ | TransferController | Anular |
| GET | /api/patients | ❌ | PatientController | Listar pacientes (auditor ve enmascarado) |
| GET | /api/patients/{id}/prescriptions | ❌ | PrescriptionController | Prescripciones vigentes |
| GET | /api/health | ✅ | HealthController | Health check básico |
| GET | /api/ready | ✅ | HealthController | Readiness con verificación de DB |

### Validación extra en Form Requests

| Request | Validaciones obligatorias |
|---------|--------------------------|
| `DispenseRequest` | patient_id existe, prescription_id existe y vigente, product_id, cantidad <= quantity_remaining |
| `TransferRequest` | warehouse_origin_id != warehouse_destiny_id, items no vacíos |
| `TransferDispatchRequest` | Solo si estado es APROBADO |
| `TransferReceiveRequest` | cantidad_recibida <= cantidad_enviada |

### Seguridad

| # | Requisito | Estado |
|---|-----------|--------|
| S-1 | Autorización por rol con Gates | ✅ 12 capacidades en `AuthServiceProvider` + `UserRoleTest` |
| S-2 | Middleware de rol por ruta | ✅ `EnsureModule` con alias `module:` aplicado a todas las rutas |
| S-3 | Auditor: datos enmascarados para rol `auditor` | ❌ Requiere modelo `Patient` (RN-10) |
| S-4 | No logs con datos de pacientes (RN-10) | ❌ No existe log channel separado |
| S-5 | Function calling del AI: solo lectura, respeta rol | ❌ AI no existe |
| S-6 | Rate limit compatible con suite E2E | ✅ `throttle:api` subido de 60 a 120 req/min en `RouteServiceProvider.php`. Necesario porque la suite de Playwright ejecuta ~93 requests en menos de 1 minuto. En producción conviene dejarlo en 60 y excluir esa suite del rate limit. |

### Documentación API

- OpenAPI (swagger.yaml) o colección Postman
- Debe documentar todos los endpoints, auth, errores

---

## Parte B — Frontend

### Pantallas existentes (HU-08)

| Pantalla | Componente | Estado | Detalle |
|----------|-----------|--------|---------|
| Login | `LoginPage` | ✅ | Form con Ant Design. Valida y muestra errores del backend. |
| Home | `HomePage` | ✅ | Landing con cards de acceso a las secciones. |
| Productos | `ProductsList` | ✅ | CRUD completo con paginacion del backend. |
| Grupos | `GroupsList` | ✅ | CRUD completo. |
| Bodegas | `WarehousesList` | ✅ | CRUD completo con toggle-status. |
| Lotes | `LotsList` | ✅ (HU-05 v2) | CRUD sin bodega/stock. Columna "Ubicación" con badges de bodegas + cantidades. |
| Kardex | `KardexList` | ✅ (HU-08) | Tabla filtrable por tipo, fecha, bodega. Read-only (RN-06). |
| Inventario | `InventoriesList` | ✅ (HU-08) | Tabla + 3 cards de alerta (vencidos / por vencer / bajo mínimo) con `Card + Statistic + List` de Ant Design. |
| Reportes | `ReportProducts` | ✅ | Reporte de inventario. |

### Pantallas pendientes

| Pantalla | Componente | Descripción | Prioridad |
|----------|-----------|-------------|-----------|
| Dispensación | `DispensacionPage` | Buscar paciente, ver prescripción, lotes FEFO, confirmar | Alta (HU-10) |
| Traslados | `TrasladosPage` | CRUD estados, discrepancias | Alta (HU-09) |
| Pacientes | `PatientsPage` | Listar y ver prescripciones | Media (HU-10) |

### Errores de usuario requeridos

- "Stock insuficiente" — cuando cantidad solicitada > stock disponible
- "Lote vencido" — cuando se intenta dispensar lote expirado
- "Falta autorización" — cuando se intenta dispensar control especial sin regente
- "Prescripción no vigente" — cuando la fecha de vigencia expiró
- "No puede aprobar su propio traslado" — segregación RN-08
- "Discrepancia:envió X, recibió Y" — al recibir parcial

### Prevención de doble clic

Todos los botones de confirmación deben deshabilitarse tras el primer click hasta recibir respuesta.

---

## Parte C — Inteligencia artificial

| # | Requisito | Estado |
|---|-----------|--------|
| AI-1 | Asistente de consulta en lenguaje natural | ❌ No existe |
| AI-2 | Function calling predefinido, solo lectura | ❌ No existe |
| AI-3 | Respeta rol del usuario | ❌ No existe |
| AI-4 | No envía datos de pacientes al modelo | ❌ No existe |
| AI-5 | Resiste inyección (instrucciones desde campos) | ❌ No existe |
| AI-6 | Provider configurable por .env + modo mock | ❌ No existe |
| AI-7 | Evaluación: 10 preguntas con respuesta esperada + script | ❌ No existe |
| AI-8 | Manejo de "no sé" / fuera de alcance | ❌ No existe |

### Funciones de ejemplo para function calling

```json
[
  {
    "name": "get_lotes_por_vencer",
    "description": "Lista lotes que vencen en los proximos N dias en una bodega",
    "parameters": { "bodega": "string", "dias": "integer" }
  },
  {
    "name": "get_stock_producto_bodega",
    "description": "Stock total de un producto en una bodega",
    "parameters": { "producto": "string", "bodega": "string" }
  },
  {
    "name": "get_lotes_vencidos",
    "description": "Lotes vencidos con stock en una bodega",
    "parameters": { "bodega": "string" }
  },
  {
    "name": "get_alertas_stock_minimo",
    "description": "Productos por debajo del stock minimo en una bodega",
    "parameters": { "bodega": "string" }
  }
]
```

---

## Parte D — Docker y DevOps

| # | Requisito | Estado |
|---|-----------|--------|
| D-1 | Staging environment en pipeline | ✅ `.github/workflows/deploy.yml` con jobs staging auto + production con approval gate |
| D-2 | Approval manual para producción | ✅ Environment `production` con `environment: url` y manual approval en deploy.yml |
| D-3 | Documento de estrategia de despliegue (1 página) | ❌ |
| D-4 | `/health` y `/ready` endpoints | ✅ `HealthController` + `HealthCheckTest` |
| D-5 | Logs estructurados con correlation ID | ❌ |
| D-6 | Verificación de salud de DB en `/ready` | ✅ En `HealthController::ready()` |

### Staging pipeline sugerido

```yaml
staging:
  needs: [build]
  environment: staging
  steps:
    - deploy to staging
    - run smoke tests
production:
  needs: [staging]
  environment: production
  type: manual   # approval gate
```

---

## Parte E — Calidad y documentación

### Estado actual de los tests

| Suite | Cantidad | Cobertura |
|-------|----------|-----------|
| Tests PHP (PHPUnit) | **139** | Modelos, servicios, controllers, Form Requests, Resources, Excepciones, Migraciones, Auth, Kardex, Inventarios, Bodegas, Lotes, Productos, Grupos, Sensores, Reportes, Permisos |
| Tests E2E (Playwright) | **93** | Login (5 roles + credenciales invalidas), Inventario (4), Kardex (3), Matriz de permisos (35: Kardex + Bodegas + Inventories + Lotes), RN-06 inmutabilidad (9), Mensaje 403 (1), Bodegas PUT/PATCH/DELETE con UUID dummy (15) |

### Tests faltantes

| Test | Por qué es obligatorio | Prioridad | Estado |
|------|----------------------|-----------|--------|
| Test FEFO | RN-02: la dispensación debe consumir lotes en orden de vencimiento | Alta | Pendiente (HU-10) |
| Test máquina de estados del traslado | RN-07: cada transición y sus precondiciones | Alta | Pendiente (HU-09) |
| Test de concurrencia sobre última unidad | RN-03: stock no puede quedar negativo con requests simultáneos | Alta | Pendiente (HU-10). El backend ya tiene `SELECT FOR UPDATE` + CHECK constraint; falta el test que lo demuestre. |
| Test idempotencia dispensación | RN-09: reintentar misma solicitud no duplica stock | Media | Pendiente (HU-10) |
| Test lote vencido no se dispensa | RN-01b | Media | Pendiente (HU-10) |
| Test segregación RN-08 | quien solicita no puede aprobar | Media | Pendiente (HU-09) |

### Documentos faltantes

| Documento | Descripción |
|-----------|-------------|
| `documentacion/DESPLIEGUE.md` | Estrategia de despliegue, rollback y backup de BD (máx 1 página) |
| `documentacion/API.md` o `openapi.yaml` | Documentación de la API |
| `AI_USAGE.md` | Ya existe, actualizar con todo lo usado hasta ahora |

---

## Datos semilla (Sección 6 del brief)

### Implementado (HU-08 + demo)

| Dato | Cantidad | Detalle | Estado |
|------|---------|---------|--------|
| Bodegas | 3 | Farmacia Central, Farmacia Urgencias, Bodega Hospitalizacion | ✅ `BodegaSeeder` |
| Usuarios | 5 | admin, regente, auxiliar, medico, auditor | ✅ `UserSeeder` (password `password`) |
| Grupos de productos | 6 | Analgesicos, Antibioticos, Antiinflamatorios, Control especial, Vitaminas, Cardiovascular | ✅ `GroupSeeder` |
| Medicamentos | 6 | Con CUM, unidad, requiere autorizacion (1 control especial) | ✅ `ProductSeeder` |
| Lotes | 18 | 2-3 por producto, con perfiles vencido / por vencer / ok | ✅ `LotSeeder` |
| Inventarios | 18 | Distribución del stock entre las 3 bodegas | ✅ `InventorySeeder` |
| Kardex demo | ~50 | Movimientos de ejemplo para visualizar el Kardex | ✅ `KardexDemoSeeder` |
| **DemoSeeder** | agrupador | Llama a los 6 seeders de demo | ✅ No se ejecuta en CI; vive en clase separada para no romper el flujo de testing |

> **Importante:** `DemoSeeder` está en una clase separada y NO se ejecuta en
> `migrate:fresh --seed` de CI. Solo se corre manualmente cuando se quiere
> un entorno de demo con datos realistas. Esto preserva los tests de CI
> que asumen una BD vacía al inicio.

### Pendiente

| Dato | Cantidad | Detalle | Estado |
|------|---------|---------|--------|
| Pacientes sintéticos | 3 | Datos completamente ficticios | ❌ Pendiente (HU-10) |
| Prescripciones vigentes | 3 | Una con control especial | ❌ Pendiente (HU-10) |

### Seeders pendientes

- `PatientSeeder` — 3 pacientes ficticios
- `PrescriptionSeeder` — prescripciones vigentes

---

## Priorización sugerida — estado al 2026-10-11

### ✅ Completado

- ✅ **Policies + middleware de rol** (HU-07): commits 8df435c a e9837fc
- ✅ **Kardex inmutable** (HU-08 §7): RN-06 implementado con trigger de BD
- ✅ **Alertas vencimiento 90d + stock mínimo** (HU-08 §3): RN-11
- ✅ **`/health` y `/ready`** endpoints
- ✅ **Pipeline staging + approval manual**: `.github/workflows/deploy.yml`
- ✅ **Seeders demo** (HU-08): `BodegaSeeder`, `GroupSeeder`, `ProductSeeder`, `LotSeeder`, `InventorySeeder`, `KardexDemoSeeder` + agrupador `DemoSeeder`
- ✅ **HU-05 v2** (lotes sin bodega/stock propios): derivado de `inventories`
- ✅ **Tests E2E Playwright**: 93 tests, incluye matriz de permisos por rol
- ✅ **Frontend Inventario + Kardex** con Ant Design (panel de alertas Card+Statistic+List)

### Pendiente — orden sugerido

#### Sprint 1 (~16h) — Núcleo operativo restante

1. **Traslados (HU-09)** — `TransferService` con máquina de 7 estados, endpoints REST, validacion RN-08, tests PHP y Playwright. Modelos y migraciones ya existen.
2. **Dispensación (HU-10)** — `DispensarService` con FEFO, idempotencia (RN-09), prescripción vigente (RN-04), control especial (RN-05), concurrencia (RN-03), modelos `Patient` + `Prescription`, tests PHP y Playwright.

#### Sprint 2 (~16h) — Seguridad, AI y frontend

3. **Pantallas frontend:** Dispensación + Traslados
4. **AI** — asistente con function calling + evaluación
5. **Docs:** OpenAPI (reemplaza API.md)
6. **Tests faltantes:** FEFO + máquina de estados + concurrencia + idempotencia + segregación RN-08
7. **RN-10:** bitácora de acceso a datos de pacientes (audit log + observer)

---

## Supuestos documentados

1. ~~MySQL 8 en lugar de PostgreSQL/SQL Server~~ — **CORREGIDO**: SQL Server 2025, el motor que recomienda el enunciado. El argumento original de LIKE case-insensitive no distinguía a MySQL de SQL Server; PostgreSQL era el único que obligaba a cambiar la búsqueda.
2. Sanctum para autenticación: Laravel Sanctum ya está instalado y configurado.
3. Las pruebas de modelo corren contra base separada `farmacia_testing` para no destruir datos de desarrollo.
4. **HU-05 v2 (lotes sin bodega/stock propios):** la versión original de la HU pedía que el lote tuviera `warehouse_id` y `stock`. La implementación real de HU-08 movió el stock a la tabla `inventories` (terna `warehouse × product × lot`, RN-01), así que `lots` ya no tiene esas columnas. El stock y la lista de bodegas del lote se calculan como campos derivados. La HU-05 fue actualizada para reflejar este cambio.
5. **Rate limit subido a 120 req/min:** la suite de Playwright ejecuta ~93 tests que hacen varias requests cada uno. El `throttle:60,1` original se quedaba corto. En producción conviene dejarlo en 60 y excluir la suite E2E del rate limit.
