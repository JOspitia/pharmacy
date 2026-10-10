# Auditoría de requisitos — FARTMAR IPS

Generado contra: `Prueba_Tecnica_Senior_FARTMAR_Candidato (1) (1).docx.pdf`
Fecha: 2026-10-09
Stack actual: PHP/Laravel + React + Docker + SQL Server 2025 + GitHub Actions + CI con SQL Server en runners

---

## Resumen ejecutivo

El sistema tiene un backend bien estructurado (Service pattern, Form Requests, API Resources) y una base sólida de 38 tests de modelos. Sin embargo, las reglas de negocio que representan el **65% del peso de evaluación** (dispensación + traslados + kardex) están ausentes, y las que representan el **25% adicional** (seguridad/roles + alertas) están parciales.

**Lo que existe:** CRUD Products/Lots/Groups/Bodegas/Sensors + Auth + Reporte inventario + toggle estado.
**Lo que falta:** todo lo que hace a este un sistema de farmacia real.

---

## Lo que falta del núcleo (leer esto primero)

Estas tres cosas son el sistema. Sin ellas el backend es un catálogo de medicamentos
con login, no una farmacia. Son el **50% del peso de evaluación** entre las tres
(RN-02 + RN-03 + RN-04 + RN-09 = dispensación 20%, RN-07 + RN-08 = traslados 15%,
RN-06 = kardex 15%).

| Módulo | Qué falta | Reglas | Peso |
|---|---|---|---|
| **Dispensación** | Modelo `Patient` + `Prescription` + `Dispense`, servicio con FEFO, idempotencia, control de lote vencido, autorización de control especial, concurrencia | RN-01 a RN-05, RN-09 | **20%** |
| **Traslados entre bodegas** | Modelos `Transfer` + `TransferItem`, máquina de 7 estados, crear/solicitar/aprobar/despachar/recibir/anular, segregación | RN-07, RN-08 | **15%** |
| **Kardex** | Modelo `Movement` inmutable, se escribe en **cada** operación que toque stock, consulta filtrable por producto/lote/bodega | RN-06 | **15%** |

Orden sugerido: **traslados primero**. Es el módulo que da nombre al sistema en el
enunciado, es el más mecánico (la máquina de estados es determinista y testeable), y
des exercising el cardinal: el kardex se escribe tanto en traslados como en
dispensación, así que conviene tener claro el patrón de movimientos antes de la
dispensación, que además es la parte más delicada por la concurrencia.

---

## Parte A — Backend y base de datos

### Modelo de datos

| # | Requisito | Estado | Detalle |
|---|-----------|--------|---------|
| A-1 | CHECK constraint `stock >= 0` en `lots` | ✅ | CHECK constraint `chk_lots_stock_non_negative` creado en migrate. Verificado: MySQL rechaza INSERT con stock negativo. Commit `c98c3b0` |
| A-2 | CHECK constraint `stock >= 0` en `bodegas` (stock mínimo) | ✅ | Columna `stock_minimo integer default 0` en migrate `c98c3b0`. Modelos y factories actualizados |
| A-3 | Índices compuestos únicos para RN-01 | ❌ | RN-01 dice: existencia = bodega + producto + lote. No hay constraint que impida duplicados |

### Modelos faltantes

| Modelo | Motivo | Tabla |
|--------|--------|-------|
| `Patient` | RN-04: toda dispensación asociada a paciente | Pacientes sintéticos requeridos |
| `Prescription` | RN-04: verificar prescripción vigente antes de dispensar | Con campos: patient_id, product_id, dosis, fecha_vigencia, cantidad_total, cantidad_dispensada |
| `Transfer` | RN-07: máquina de estados del traslado | Estados: BORRADOR→SOLICITADO→APROBADO→EN_TRANSITO→RECIBIDO→RECIBIDO_PARCIAL→ANULADO |
| `TransferItem` | Items de un traslado (producto + cantidad) | LineItems del traslado |
| `Movement` (Kardex) | RN-06: movimiento inmutable por cada cambio de stock | Tipos: entrada, salida_dispensacion, salida_traslado, entrada_traslado, ajuste |
| `MovementReason` | Motivo del ajuste RN-06 | Catálogo para ajustes |
| `Prescription` | RN-04: verificar prescripción vigente | Con control especial |
| `Alert` ya existe | RN-11: alertas de stock mínimo | Bodega ya tiene `stock_minimo` (commit `c98c3b0`). Falta el servicio que compare stock real vs mínimo y genere alertas |
| `AuditLog` | RN-10: bitácora de quién consultó qué paciente | Acceso a datos sensibles |

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

#### Matriz de acceso por rol — sistema completo

Aplica el criterio **leer vs. escribir**, no "un rol por módulo". Si se usara un rol por
módulo se le quitaría al auxiliar el inventario, que el brief explícitamente le da.

Módulos de la izquierda: los tres primeros **aún no existen** (dispensación, traslados,
kardex). Los otros cinco ya están construidos.

| Módulo | auxiliar | regente | medico | auditor | admin |
|---|---|---|---|---|---|
| **Dispensación** | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Traslados** crear / recibir | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Traslados** aprobar / anular | ❌ | ✅ | ❌ | ❌ | ❌ |
| **Kardex** | ✅ leer | ✅ leer | ❌ | ✅ leer | ✅ leer |
| **Prescripciones** | ❌ | ❌ | ✅ crear | ❌ | ❌ |
| **Pacientes** | ❌ | ❌ | ✅ | ✅ enmascarado | ❌ |
| **Almacén** (lotes, stock) | ✅ leer | ✅ leer + ajustar | ❌ | ✅ leer | ✅ leer |
| **Bodegas** | ✅ leer | ✅ escribir | ❌ | ✅ leer | ✅ escribir |
| **Catálogos** (grupos, unidades, productos) | ❌ | ❌ | ✅ leer mínimo | ✅ leer | ✅ escribir |
| **Usuarios** | ❌ | ❌ | ❌ | ❌ | ✅ |

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

#### Hallazgo: el Gate::before de admin rompía RN-05 — ✅ RESUELTO

El hallazgo documentaba que `Gate::before` con bypass de admin hacía que `admin`
pasara **toda** capacidad sin enumerarla, evadiendo RN-05 (control especial
requiere regente) y RN-08 (segregación de traslados).

**Resolución:** se eliminó el bypass de `Gate::before`. Admin tiene exactamente
los permisos que la matriz le asigna. La matriz del Anexo A le da solo lectura
en stock y escritura en administración. RN-05 y RN-08 se cumplen sin código de
negocio adicional.

### Reglas de negocio

| # | Regla | Estado | Implementación requerida |
|---|-------|--------|------------------------|
| RN-01 | Lote vencido no se dispensa ni traslada | ❌ | Validación en DispensarService y TransferService que bloquee si `expiration_date < today()` |
| RN-02 | FEFO en dispensación | ❌ | Ordenar lots por `expiration_date ASC` donde `status=true AND stock>0` |
| RN-03 | Stock nunca negativo (concurrencia) | ❌ | `DB::select(DB::raw('SELECT * FROM lots WHERE ... FOR UPDATE'))` dentro de transacción |
| RN-04 | Dispensación vinculada a paciente + prescripción vigente | ❌ | Verificar `Prescription::where('patient_id', $id)->where('product_id', $productId)->where('fecha_vigencia', '>=', today())->first()` |
| RN-05 | Control especial requiere autorización de regente | ⚠️ Parcial | Columna `requires_authorization boolean default false` en products (commit `c98c3b0`). Falta la lógica de doble usuario en dispensación |
| RN-06 | Kardex: movimientos inmutables | ❌ | Crear registro `Movement` en cada operación que modifique stock. Sin update ni delete |
| RN-07 | Máquina de estados del traslado | ❌ | Lógica completa de transiciones (ver tabla de estados) |
| RN-08 | Segregación: quien solicita no aprueba | ❌ | Validar `transfer.requested_by != transfer.approved_by` |
| RN-09 | Idempotencia en dispensación | ❌ | Token de idempotencia en headers, guardar en BD para evitar duplicados |
| RN-10 | Datos de paciente: acceso por rol + enmascarado + bitácora | ❌ | Role middleware + observer en Patient/Prescription que registre quién accedió |
| RN-11 | Alertas: vencimiento ≤90 días + stock mínimo | ⚠️ Parcial | Filtro `por_vencer` corregido a 90 días (commit `c98c3b0`). Falta la lógica de alerta de stock mínimo por bodega |

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

### API REST — endpoints faltantes

| Método | URI | Controlador | Descripción |
|--------|-----|------------|-------------|
| POST | /api/dispense | DispenseController | Dispensar a paciente con FEFO |
| GET | /api/dispense/{id} | DispenseController | Consultar dispensación |
| GET | /api/kardex | KardexController | Historial filtrable por producto/lote/bodega |
| GET | /api/kardex/{lot_id} | KardexController | Movimientos de un lote |
| POST | /api/transfers | TransferController | Crear traslado (BORRADOR→SOLICITADO) |
| GET | /api/transfers | TransferController | Listar traslados |
| GET | /api/transfers/{id} | TransferController | Ver traslado |
| PATCH | /api/transfers/{id}/approve | TransferController | Aprobar (RN-08) |
| PATCH | /api/transfers/{id}/dispatch | TransferController | Despachar (EN_TRANSITO, descuenta stock) |
| PATCH | /api/transfers/{id}/receive | TransferController | Recibir (RECIBIDO, agrega stock) |
| PATCH | /api/transfers/{id}/receive-partial | TransferController | Recibir parcial |
| PATCH | /api/transfers/{id}/cancel | TransferController | Anular |
| GET | /api/alerts/stock | AlertController | Alertas de stock mínimo por bodega |
| GET | /api/patients | PatientController | Listar pacientes (auditor ve enmascarado) |
| GET | /api/patients/{id}/prescriptions | PrescriptionController | Prescripciones vigentes |
| GET | /api/health | HealthController | Health check básico |
| GET | /api/ready | HealthController | Readiness con verificación de DB |

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

### Documentación API

- OpenAPI (swagger.yaml) o colección Postman
- Debe documentar todos los endpoints, auth, errores

---

## Parte B — Frontend

### Pantallas faltantes

| Pantalla | Componente | Descripción | Prioridad |
|----------|-----------|-------------|-----------|
| Dispensación | `DispensacionPage` | Buscar paciente, ver prescripción, lotes FEFO, confirmar | Alta |
| Traslados | `TrasladosPage` | CRUD estados, discrepancias | Alta |
| Inventario | `InventarioPage` | Ya existe consulta, agregar alertas visuales | Media |
| Kardex | `KardexPage` | Historial filtrable | Media |

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

### Tests faltantes

| Test | Por qué es obligatorio | Prioridad |
|------|----------------------|-----------|
| Test FEFO | RN-02: la dispensación debe consumir lotes en orden de vencimiento | Alta |
| Test máquina de estados del traslado | RN-07: cada transición y sus precondiciones | Alta |
| Test de concurrencia sobre última unidad | RN-03: stock no puede quedar negativo con requests simultáneos | Alta |
| Test idempotencia dispensación | RN-09: reintentar misma solicitud no duplica stock | Media |
| Test lote vencido no se dispensa | RN-01 | Media |
| Test segregación RN-08 | quien solicita no puede aprobar | Media |

### Documentos faltantes

| Documento | Descripción |
|-----------|-------------|
| `documentacion/DESPLIEGUE.md` | Estrategia de despliegue, rollback y backup de BD (máx 1 página) |
| `documentacion/API.md` o `openapi.yaml` | Documentación de la API |
| `AI_USAGE.md` | Ya existe, actualizar con todo lo usado hasta ahora |

---

## Datos semilla faltantes (Sección 6 del brief)

| Dato | Cantidad | Detalle |
|------|---------|---------|
| Bodegas | 3 | Farmacia Central, Farmacia Urgencias, Bodega Hospitalización |
| Usuarios | 5 | ✅ **Hecho** — `UserSeeder`, contrasena `password` para todos |
| Medicamentos | 6 | Con 2-3 lotes cada uno |
| Lotes vencidos | ≥1 | expiration_date < today() |
| Lotes por vencer (<30 días) | ≥1 | expiration_date dentro de 30 días |
| Medicamento control especial | 1 | requires_authorization = true |
| Pacientes sintéticos | 3 | Datos completamente ficticios |
| Prescripciones vigentes | 3 | Una con control especial |

### Seeders a crear

- `BodegaSeeder` — 3 bodegas
- `GroupSeeder` — grupos de productos
- `ProductSeeder` — 6 medicamentos (uno control especial)
- `LotSeeder` — 2-3 lotes por producto, con uno vencido y uno <30 días
- `PatientSeeder` — 3 pacientes ficticios
- `PrescriptionSeeder` — prescripciones vigentes

---

## Priorización sugerida para ~40 horas restantes

### Semana 1 (~20h) — Núcleo operativo

1. **Migraciones nuevas:** Movement, Transfer, TransferItem, Patient, Prescription, AuditLog + CHECK constraints (users.role ✅)
2. **Seeders completos** (habilita todo el sistema)
3. **Traslados entre bodegas** — modelo + servicio + controlador + tests (RN-07, RN-08)
4. **Dispensación** — FEFO, idempotencia, prescripción vigente + tests (RN-02, RN-03, RN-04, RN-09)

### Semana 2 (~20h) — Seguridad, AI y frontend

5. **Kardex** — movimientos inmutables (RN-06)
6. ~~**Policies + middleware de rol**~~ ✅ **IMPLEMENTADO** (HU-07, ver commits 8df435c a e9837fc)
7. **Alertas de stock mínimo** (RN-11, corregir 30→90 días)
8. **Pantallas frontend:** Dispensación + Traslados
9. **AI** — asistente con function calling + evaluación
10. **Docs:** OpenAPI (reemplaza API.md) + `/health` `/ready` ✅
11. ~~**Pipeline:** staging + approval manual~~ ✅ `.github/workflows/deploy.yml`
12. **Tests faltantes:** FEFO + máquina de estados + concurrencia

---

## Supuestos documentados

1. ~~MySQL 8 en lugar de PostgreSQL/SQL Server~~ — **CORREGIDO**: SQL Server 2025, el motor que recomienda el enunciado. El argumento original de LIKE case-insensitive no distinguía a MySQL de SQL Server; PostgreSQL era el único que obligaba a cambiar la búsqueda.
2. Sanctum para autenticación: Laravel Sanctum ya está instalado y configurado.
3. Las pruebas de modelo corren contra base separada `farmacia_testing` para no destruir datos de desarrollo.
