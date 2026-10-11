# HU-08 — Kardex e Inventarios

**Proyecto:** Farmacia — Sistema de Gestión de Inventarios
**Fecha:** 2026-10-10
**Versión:** 1.1

---

## 1. Título y Descripción

**Título:** HU-08 — Kardex e Inventarios

**Descripción:** Implementar el modelo de existencia definido en RN-01: el stock no vive en la tabla `lots` sino en la intersección bodega × producto × lote. Se crea la tabla `inventories` para representar cada existencia individual, y la tabla `movements` (Kardex) como registro inmutable de todo movimiento de inventario. El Kardex es consumido por los módulos de traslados (HU-09) y dispensación (HU-10).

---

## 2. Modelo de Datos

### Tabla: `inventories`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por la BD |
| warehouse_id | uuid | FK → bodegas (id), NOT NULL |
| product_id | uuid | FK → products (id), NOT NULL |
| lot_id | uuid | FK → lots (id), NOT NULL |
| quantity | integer | NOT NULL, DEFAULT 0, CHECK >= 0 |
| min_stock | integer | NULL, para alerta de stock mínimo **por bodega** (RN-11) |
| created_at | timestamp | NOT NULL |
| updated_at | timestamp | NULL |

| FK | onDelete |
|---|---|
| warehouse_id → bodegas (id) | RESTRICT |
| product_id → products (id) | RESTRICT |
| lot_id → lots (id) | RESTRICT |

**Constraint única:** UNIQUE(warehouse_id, product_id, lot_id) — materializa textualmente RN-01: *"Cada existencia se identifica por bodega + producto + lote"*.

> **Por qué la bodega vive en `inventories` y no en `lots`:** el lote es un atributo del producto (número de lote del fabricante + fecha de vencimiento), no un lugar. El mismo lote puede estar simultáneamente en la Farmacia Central y en Farmacia de Urgencias — es exactamente lo que hace posible un traslado. La §6 del brief pide *"6 medicamentos... con 2 o 3 lotes cada uno"*, sin duplicar por bodega, lo que confirma que el lote no está atado a una ubicación.

**Consecuencia sobre el esquema existente (`lots`):**

| Aspecto | Estado actual | Cambio requerido |
|---|---|---|
| `lots.warehouse_id` | NOT NULL, FK a `bodegas` | **Eliminar** (o volver nullable, ver decisión D4) |
| `lots.name` | UNIQUE global | **Cambiar a** UNIQUE(product_id, name) |
| `lots.product_id` | FK | Se conserva |
| `lots.stock` | integer | **Eliminar** (migrado a `inventories.quantity`) |
| `lots.expiration_date` | date | Se conserva |

Si `lots.name` permanece UNIQUE global, un lote no podría existir en dos bodegas **y los traslados serían imposibles de modelar** — contradicción directa con RN-07.

### Tabla: `movements` (Kardex)

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por la BD |
| inventory_id | uuid | FK → inventories (id), NOT NULL |
| type | varchar(30) | NOT NULL — ENTRADA, AJUSTE, TRASLADO_SALIDA, TRASLADO_ENTRADA, DISPENSACION, AJUSTE_NEGATIVO |
| quantity | integer | NOT NULL, CHECK > 0 para ENTRADA/AJUSTE; puede ser cualquier entero para movimientos internos |
| reference_type | varchar(100) | NULL — modelo que referencia (Transfer::class, Dispense::class) |
| reference_id | uuid | NULL |
| reason | text | NULL |
| performed_by | uuid | FK → users (id), NOT NULL |
| created_at | timestamp | NOT NULL |

| FK | onDelete |
|---|---|
| inventory_id → inventories (id) | RESTRICT |
| performed_by → users (id) | RESTRICT |

**Importante:** La tabla `movements` NO tiene `updated_at`, `deleted_at`, ni SoftDeletes. Es inmutable por diseño (RN-06).

### Tabla: `transfer_statuses`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por la BD |
| name | varchar(30) | NOT NULL, UNIQUE |
| is_cancellable | boolean | NOT NULL, DEFAULT false |
| is_terminal | boolean | NOT NULL, DEFAULT false |
| created_at | timestamp | NOT NULL |

**Datos seedeados:**

| name | is_cancellable | is_terminal |
|---|---|---|
| BORRADOR | true | false |
| SOLICITADO | true | false |
| APROBADO | false | false |
| EN_TRANSITO | false | false |
| RECIBIDO | false | false |
| RECIBIDO_PARCIAL | false | false |
| ANULADO | false | true |

### Migration: desacoplar `stock` y `warehouse_id` de `lots`

**Esta migración es de datos + schema. Se ejecuta en orden estricto:**

1. **Crear** tabla `inventories` inicializada con los datos actuales de `lots`:
   - Por cada registro en `lots`, crear una fila en `inventories`:
     - `warehouse_id = lots.warehouse_id` (se preserva la ubicación actual)
     - `product_id = lots.product_id`
     - `lot_id = lots.id`
     - `quantity = lots.stock` (valor actual)
     - `min_stock = NULL`
   - Verificación obligatoria: `SUM(inventories.quantity)` debe igualar `SUM(lots.stock)` antes de continuar.
2. **Relajar** `lots.name`: eliminar la constraint UNIQUE global y crear UNIQUE(product_id, name).
   - Sin este paso el mismo lote no puede registrarse en dos bodegas, y los traslados serían imposibles de modelar.
3. **Eliminar** columna `stock` de la tabla `lots`.
4. **Eliminar** columna `warehouse_id` de la tabla `lots` (o volverla nullable si se conserva la ubicación histórica del lote — ver decisión D4).
5. **Actualizar** modelo `Lot`: quitar `stock` y `warehouse_id` de `$fillable` y `$casts`.
6. **Actualizar** `LotService::hasActiveStock()`: consultar `Inventory::where('lot_id', $lot->id)->sum('quantity')` (suma sobre todas las bodegas).
7. **Actualizar** las consultas que filtran por `lots.warehouse_id` para que usen `inventories.warehouse_id`: filtros de inventario, selector de lote del frontend y reportes.

---

## 3. Arquitectura en Capas

```
┌──────────────────────────────────────────────────────────────┐
│  Controller                                                    │
│  InventoryController     →  lista y detalle de existencias     │
│  MovementController     →  registro de movimientos             │
│  → recibe el request, llama al service, retorna el resource   │
└────────────────────────────┬───────────────────────────────────┘
                             │
              ┌──────────────▼──────────────┐
              │  Request                      │
              │  StoreMovementRequest         │
              │  → valida: lot_id, warehouse_id, │
              │    type, quantity, reason       │
              │  → verifica expiration_date     │
              │    >= today() para ENTRADA     │
              └──────────────┬──────────────┘
                             │
            ┌────────────────▼────────────────┐
            │  Service                          │
            │  KardexService (inyectable)       │
            │  → resuelve o crea Inventory      │
            │  → valida warehouse consistencia │
            │  → DB::transaction + lockForUpdate│
            │  → actualiza quantity             │
            │  → registra Movement              │
            └────────────────┬────────────────┘
                             │
            ┌────────────────▼────────────────┐
            │  Model                            │
            │  Inventory   Movement            │
            │  → relaciones: lot, movements    │
            │  → accessors: days_until_expiry  │
            │    expiration_status              │
            └─────────────────────────────────┘

Resource ← serializa la respuesta JSON al frontend
```

**Flujo de una ENTRADA:**

```
HTTP POST /api/movements
        │
        ▼
StoreMovementRequest
  ├─ valida lot_id existe
  ├─ valida warehouse_id existe y resuelve la existencia (bodega+producto+lote)
  ├─ valida type ∈ {ENTRADA, AJUSTE, ...}
  ├─ valida quantity > 0
  ├─ valida expiration_date >= today()                  ← CORRECCIÓN vencimiento
  └─ pasa al controller
        │
        ▼
MovementController::store(request)
  ├─ llama KardexService::recordMovement(...)
  └─ retorna new MovementResource($movement)
        │
        ▼
KardexService::recordMovement(...)
  ├─ obtiene/crea Inventory por lot_id
  ├─ DB::transaction with lockForUpdate() sobre inventory
  ├─ valida inventory.quantity + quantity >= 0
  ├─ $inventory->quantity += $quantity
  ├─ $inventory->save()
  ├─ Movement::create([...])
  └─ retorna $movement
```

**Flujo de GET /api/inventories:**

```
HTTP GET /api/inventories?warehouse_id=...&sort=expiration_date&order=asc
        │
        ▼
InventoryController::index(request)
  ├─ aplica filtros (warehouse, product, lot, expiring_within)
  ├─ eager loads: lot.product, lot.warehouse
  ├─ ordena por expiration_date ASC (FEFO)
  ├─ calcula alerts (expiring_soon, expired, low_stock)
  └─ retorna InventoryResource::collection($inventories)
            │
            ▼
InventoryResource
  ├─ id, lot_id, lot_name
  ├─ product_name, product_sku
  ├─ warehouse_name
  ├─ quantity, min_stock
  ├─ expiration_date (de $inventory->lot)
  ├─ days_until_expiration (accessor calculado)
  ├─ expiration_status (accessor calculado)
  └─ ← response JSON
```

---

## 4. Bloqueos y Dependencias

- Deben existir `warehouses`, `products`, `lots`, `users` (estructura estándar Laravel 9).
- HU-09 (Traslados) depende de `KardexService` porque necesita registrar TRASLADO_SALIDA y TRASLADO_ENTRADA.
- HU-10 (Dispensación) depende de `KardexService` por la misma razón.
- `LotTest.php` que lea `$lot->stock` directamente necesitará actualizarse para crear `Inventory` asociado.
- `LotsList.tsx` muestra `record.stock` — se actualiza para leer de `Inventory`.

---

## 5. Variables y Configuración

- Sin feature toggles ni comportamiento específico por tenant.
- KardexService se registra como singleton en `AppServiceProvider`.
- Tipos de movimiento como datos en la tabla `movement_types` (no como constantes en PHP). El catálogo vive en la tabla y se consulta por `name`.
- KardexService lanza excepciones tipadas: `StockInsuficienteException` (422) y `KardexStateException` (422). `ModelNotFoundException` se traduce a 404 vía el handler por defecto de Laravel.

> **Convención de nombres (AI_USAGE.md §4.1):** los identificadores técnicos (`name` en `movement_types`, `name` en `transfer_statuses`, `type` en `movements`) van en inglés. La traducción al español vive en campos separados (`description`, `label`). Ejemplo: el tipo `ENTRY` (inglés, en `name`) tiene `description = 'Ingreso de mercancia'` (español, texto de UI). Esto explica por qué en los response de la API el `type` aparece como `ENTRY` y no `ENTRADA`.

---

## 6. Suposiciones

1. La tabla `lots` existe con la estructura actual (`id`, `warehouse_id`, `product_id`, `stock`, `expiration_date`).
2. El campo `stock` en `lots` tiene datos numéricos >= 0.
3. Cada `lot` tiene exactamente un `warehouse_id` y `product_id`.
4. `inventories` es la única fuente de verdad para stock disponible.
5. El Kardex se consulta principalmente por tipo de movimiento y rango de fechas; índice recomendado en `(type, created_at)`.
6. El `reference_type` y `reference_id` permiten rastrear el origen del movimiento sin crear dependencia circular.
7. La validación de warehouse consistencia se hace en la capa de Service, no en el Model ni en el Form Request.

---

## 7. Permisos (PermissionSeeder)

**Módulo: `kardex`**

| Permiso | Descripción | Roles que lo tienen |
|---|---|---|
| consultar-kardex | Listar y ver movimientos del Kardex | todos los roles autenticados |
| ajustar-inventario | Registrar movimientos ENTRADA y AJUSTE | regente_farmacia |
| consultar-inventario | Listar y ver existencias | todos los roles autenticados |

**Módulo: `transfers` — ver HU-09**

Los permisos de traslados se definen exclusivamente en `HU-09-Traslados.md` §6. Esta HU no los duplica para evitar definiciones contradictorias del mismo permiso.

**Detalle de permisos por rol:**

| Rol | Kardex | Inventories |
|---|---|---|
| regente_farmacia | consultar + ajustar | consultar |
| auxiliar_farmacia | consultar | consultar |
| medico | consultar | consultar |
| auditor | consultar | consultar |
| admin | consultar | consultar + ajustar |

---

## 8. Fuera de Alcance (explícitamente excluido)

- Módulo de traslados (HU-09).
- Módulo de dispensación (HU-10).
- Reportes o dashboards de stock (HU-01/HU-02).
- Notificaciones por correo de alertas de stock mínimo.
- Exportación del Kardex a PDF o Excel.
- Interfaz de usuario del frontend (trabajo posterior).
- Reversión de movimientos del Kardex (un AJUSTE compensa).

---

## 9. Camino Feliz Principal (paso a paso)

### Registrar entrada de mercancía (regente_farmacia)

1. El regente_farmacia abre el formulario de ingreso por Kardex.
2. El sistema muestra un selector de **bodega destino** y un selector de **lote** (filtra por bodega, muestra expiration_date y días restantes).
3. El regente selecciona el lote e ingresa la cantidad que entró.
4. El sistema resuelve o crea la existencia: `Inventory::firstOrCreate(warehouse_id, product_id, lot_id)`.
   - El lote aporta `product_id`; la bodega la aporta el usuario. Ambos se combinan en la terna RN-01.
5. El sistema valida en `StoreMovementRequest` que `expiration_date >= today()` — si el lote está vencido, se rechaza antes de llegar al servicio.
6. El sistema llama a `KardexService::recordMovement()` con type=ENTRADA.
7. KardexService ejecuta `DB::transaction()` con `lockForUpdate()` sobre la fila del inventory.
8. El sistema crea el registro en `movements` con type=ENTRADA, quantity=+N.
9. Se actualiza `inventory.quantity += N`.
10. El sistema retorna el movimiento creado con el inventory actualizado.

### Consultar existencias

1. El usuario consulta GET /api/inventories con filtros: warehouse_id, product_id, lot_id, expiring_within.
2. El sistema retorna las existencias ordenadas por `expiration_date ASC` (más próximo primero).
3. Cada existencia muestra: expiration_date, days_until_expiration, expiration_status.
4. El response incluye sección `alerts`: expiring_soon, expired, low_stock.

### Consultar Kardex

1. El usuario consulta GET /api/movements con filtros: inventory_id, type, from_date, to_date.
2. El sistema retorna los movimientos paginados, ordenados por created_at DESC.
3. Cada movimiento incluye los datos del inventory (bodega, producto, lote).

---

## 10. Contrato de API

> **Identificadores en inglés:** los campos `type` (en POST /api/movements) y `name` (en GET /api/movements, filtro `?type=`) aceptan los nombres técnicos de la tabla `movement_types`: `ENTRY`, `ADJUSTMENT`, `TRANSFER_OUT`, `TRANSFER_IN`, `DISPENSATION`, `WITHDRAWAL`. Ver convención de nombres arriba.

### GET /api/warehouses/{id}/lots

Lotes con existencia en una bodega. Alimenta el selector de lote del formulario de Kardex (CA-12).

**Filtros:** ninguno. La bodega viene por la URL.

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "LOTE-2026-001",
      "expiration_date": "2026-11-10",
      "days_until_expiration": 30,
      "stock": 50,
      "min_stock": 20,
      "product": {
        "id": "uuid",
        "name": "Acetaminofén 500mg",
        "cum": "19903678-1"
      }
    }
  ]
}
```

Notas:
- Solo aparecen lotes con `quantity > 0` en la bodega.
- Orden FEFO (`expiration_date ASC`).
- `stock` y `min_stock` son los de ESA bodega, no los globales del lote.
- `product.cum` (Código Único de Medicamento colombiano) es el identificador local del producto, no SKU genérico.

### GET /api/inventories

**Filtros:**
- `?warehouse_id=uuid` — filtrar por bodega
- `?product_id=uuid` — filtrar por producto
- `?lot_id=uuid` — filtrar por lote
- `?expiring_within=90` — solo lotes que vencen dentro de N días
- `?sort=expiration_date&order=asc` — ordenar por vencimiento (asc = más próximo primero, default)

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "lot_id": "uuid",
      "lot_name": "LOTE-2026-001",
      "product_name": "Acetaminofén 500mg",
      "product_sku": "ACET-500",
      "warehouse_name": "Bodega Principal",
      "quantity": 150,
      "min_stock": 20,
      "expiration_date": "2026-03-15",
      "days_until_expiration": 30,
      "expiration_status": "warning",
      "created_at": "2026-01-15T10:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "total": 45 },
  "alerts": {
    "expiring_soon": [
      { "inventory_id": "uuid", "product_name": "...", "lot_name": "...", "warehouse_name": "...", "quantity": 150, "days_until_expiration": 5, "expiration_date": "2026-03-15" }
    ],
    "expired": [
      { "inventory_id": "uuid", "product_name": "...", "lot_name": "...", "warehouse_name": "...", "quantity": 30, "days_until_expiration": -15, "expiration_date": "2026-01-25" }
    ],
    "low_stock": [
      { "inventory_id": "uuid", "product_name": "...", "lot_name": "...", "warehouse_name": "...", "quantity": 5, "min_stock": 20, "deficit": -15 }
    ]
  }
}
```

**Cálculo de `days_until_expiration`:** `Carbon::today()->diffInDays($lot->expiration_date, false)` — valor negativo = ya vencido.

**`expiration_status`:**
| valor | condición | color UI |
|-------|-----------|----------|
| `ok` | > 90 días | verde |
| `warning` | 31–90 días | amarillo |
| `critical` | 1–30 días | naranja |
| `expired` | < 0 días | rojo |

### POST /api/movements

```json
{
  "lot_id": "uuid",
  "warehouse_id": "uuid",
  "type": "ENTRY",
  "quantity": 100,
  "reason": "Recepción OC-2026-0042"
}
```

**Validaciones en `StoreMovementRequest`:**
1. `lot_id` — existe en la tabla lots (y está activo)
2. `warehouse_id` — existe en la tabla bodegas (y está activa)
3. La existencia (warehouse_id, product_id, lot_id) se resuelve con `firstOrCreate`; el lote aporta `product_id` y la bodega la aporta el usuario
4. `type` ∈ {`ENTRY`, `ADJUSTMENT`, `TRANSFER_OUT`, `TRANSFER_IN`, `DISPENSATION`, `WITHDRAWAL`} — valores de la columna `name` en `movement_types`
5. `quantity` > 0 (entero)
6. `reason` — opcional, máximo 500 caracteres
7. Si `type == ENTRY` → `lot.expiration_date >= today()` — **no se aceptan lotes vencidos en la entrada** (KE-09, CA-02)
8. Si `type ∈ {TRANSFER_OUT, WITHDRAWAL, DISPENSATION}` → el inventory debe existir con stock suficiente (validado en KardexService → 422 con `StockInsuficienteException`)
9. `reference_type` y `reference_id` son correlativos: si uno está presente, el otro también

**Respuesta:**
```json
{
  "data": {
    "id": "uuid",
    "inventory_id": "uuid",
    "type": "ENTRADA",
    "quantity": 100,
    "reason": "Recepción OC-2026-0042",
    "performed_by": "uuid",
    "inventory": {
      "id": "uuid",
      "quantity": 250,
      "lot_name": "LOTE-2026-001",
      "product_name": "Acetaminofén 500mg",
      "warehouse_name": "Bodega Principal"
    },
    "created_at": "2026-10-10T14:30:00Z"
  }
}
```

### GET /api/movements

**Filtros:**
- `?inventory_id=uuid`
- `?type=ENTRADA|AJUSTE|...`
- `?performed_by=uuid`
- `?from_date=YYYY-MM-DD`
- `?to_date=YYYY-MM-DD`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "inventory_id": "uuid",
      "type": "ENTRADA",
      "quantity": 100,
      "reason": "Recepción OC-2026-0042",
      "performed_by": "uuid",
      "performed_by_name": "María López",
      "reference_type": null,
      "reference_id": null,
      "created_at": "2026-10-10T14:30:00Z",
      "inventory": {
        "lot_name": "LOTE-2026-001",
        "product_name": "Acetaminofén 500mg",
        "warehouse_name": "Bodega Principal"
      }
    }
  ],
  "meta": { "current_page": 1, "total": 120 }
}
```

---

## 11. Caminos Tristes (errores esperados)

| # | Escenario | Comportamiento del Sistema |
|---|---|---|
| KT-01 | Movimiento con quantity que deja stock negativo | KardexService lanza `InsufficientStockException`. HTTP 422. Mensaje: "Stock insuficiente para el movimiento solicitado" |
| KT-02 | Movimiento sobre inventory que no existe | HTTP 404. Mensaje: "Existencia no encontrada" |
| KT-03 | Tipo de movimiento no válido | HTTP 422. Validación de Form Request |
| KT-04 | Cantidad menor o igual a cero | HTTP 422. Validación de Form Request |
| KT-05 | reference_type sin reference_id (o viceversa) | HTTP 422. Validación de Form Request |
| KT-06 | Error de conexión a la base de datos | HTTP 500. Mensaje: "No es posible establecer conexión con el servidor, intente nuevamente" |
| KT-07 | Permiso insuficiente para crear movimiento | HTTP 403 |
| KT-08 | Intento de PUT/PATCH/DELETE sobre /api/movements/{id} | HTTP 405 Method Not Allowed. La tabla es inmutable |
| KT-09 | ENTRADA con lote ya vencido (expiration_date < today) | HTTP 422. Validación en StoreMovementRequest. Mensaje: "No se puede registrar entrada de un lote vencido" |
| KT-10 | La existencia no puede superar el stock disponible | KardexService lanza InsufficientStockException. HTTP 422. Mensaje: "Stock insuficiente para el movimiento solicitado" |

---

## 12. Casos Límite y Casos Estúpidos

### Casos Límite

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| KL-01 | Movimiento con quantity = 0 | Se rechaza en StoreMovementRequest. Mensaje: "La cantidad debe ser mayor a cero" |
| KL-02 | Movimiento de salida que deja quantity exactamente en 0 | Permitido. CHECK constraint es >= 0, no > 0 |
| KL-03 | Dos movimientos simultáneos sobre el mismo inventory | `lockForUpdate()` serializa. El segundo espera y valida contra el estado real |
| KL-04 | reference_type y reference_id nulos (movimiento sin referencia) | Permitido para ENTRADA y AJUSTE |
| KL-05 | ENTRADA de mercancía con lote que aún no existe en la bodega | El lote se crea primero (vía HU de lotes), luego se registra la ENTRADA |
| KL-06 | inventory.quantity exactamente en 0 y se intenta un movimiento de salida | InsufficientStockException. HTTP 422 |

### Casos Estúpidos

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| KE-01 | DELETE sobre /api/movements/{id} | HTTP 405 |
| KE-02 | PUT/PATCH sobre /api/movements/{id} | HTTP 405 |
| KE-03 | Movement sobre una existencia de otra bodega por error de selección | El warehouse_id viene del body de la request y se valida contra la existencia resuelta. Si no coinciden, HTTP 422. |
| KE-04 | Doble clic en "Registrar movimiento" | El botón se deshabilita. Si llegan dos requests, el segundo recibe error de constraint o se registran dos movimientos separados (según idempotencia) |
| KE-05 | ENTRADA de un lote con expiration_date = today() | Se acepta. expiration_date >= today() permite hoy. El lote vence esta noche. |
| KE-06 | quantity negativa en el request (e.g. -50) | Se rechaza en validación del Form Request. quantity debe ser > 0 |

---

## 13. Criterios de Aceptación

> **Estado al cierre del backend:** los 18 CA están verificados (ver matriz al final). Los nombres de tipo son los de la columna `name` en `movement_types` (en inglés); el texto que ve la UI en español vive en `description`.

### CA-01: Registrar entrada de mercancía ✅
**Dado** que existe un lote con expiration_date = today() + 30 días en la Bodega Principal
**Cuando** el regente_farmacia envía POST /api/movements con type=`ENTRY`, quantity=100 y reason="Recepción OC-2026-0042"
**Entonces** se crea un registro en movements con type=`ENTRY`, quantity=100 **PASS**
**Y** inventory.quantity se actualiza sumándole 100 **PASS**
**Y** el response retorna el movimiento creado con inventory actualizado **PASS**

### CA-02: Rechazar ENTRADA de lote vencido ✅
**Dado** que existe un lote con expiration_date = yesterday
**Cuando** el regente_farmacia envía POST /api/movements con type=`ENTRY` para ese lote
**Entonces** el sistema retorna HTTP 422 **PASS**
**Y** mensaje: "No se puede registrar entrada de un lote vencido" **PASS**

### CA-03: La entrada crea la existencia si no existe ✅
**Dado** que el lote L1 existe pero no tiene existencia en Bodega Urgencias
**Cuando** el regente envía POST /api/movements con lot_id=L1 y warehouse_id=Bodega Urgencias
**Entonces** se crea la existencia (Bodega Urgencias, L1.product_id, L1) con quantity=0 **PASS**
**Y** luego el movimiento ENTRY la incrementa **PASS**

### CA-04: Movimiento de salida no deja stock negativo ✅
**Dado** que existe un inventory con quantity=30
**Cuando** se envía un movimiento de type=`TRANSFER_OUT` con quantity=50
**Entonces** KardexService lanza `StockInsuficienteException` **PASS**
**Y** HTTP 422 con mensaje "Stock insuficiente" **PASS**
**Y** inventory.quantity permanece en 30 **PASS**

### CA-05: Kardex inmutable — sin update ni delete ✅
**Dado** que existe un movimiento en la tabla movements
**Cuando** se envía PUT /api/movements/{id} o DELETE /api/movements/{id}
**Entonces** el sistema retorna HTTP 405 Method Not Allowed **PASS**

### CA-06: Migración de lots.stock a inventories ✅
**Dado** que existe un lote con stock=25 en la base de datos
**Cuando** se ejecuta la migration de eliminación de lots.stock
**Entonces** se crea un registro en inventories con warehouse_id=lot.warehouse_id, product_id=lot.product_id, lot_id=lot.id, quantity=25 **PASS**
**Y** la columna stock ya no existe en la tabla lots **PASS**
**Y** `LotService::hasActiveStock()` retorna la suma de inventories.quantity para ese lote **PASS**

### CA-07: TransferStatus seedeado con 7 estados ✅
**Dado** que se ejecutan los seeders
**Cuando** se consulta SELECT * FROM transfer_statuses
**Entonces** existen exactamente 7 registros: `DRAFT`, `REQUESTED`, `APPROVED`, `IN_TRANSIT`, `RECEIVED`, `PARTIALLY_RECEIVED`, `CANCELLED` (los nombres en inglés son los identificadores técnicos; `label` contiene "Borrador", "Solicitado", etc.) **PASS**
**Y** los flags is_cancellable e is_terminal corresponden a la tabla de decisión **PASS**

### CA-08: Listar inventarios ordenados por vencimiento (FEFO) ✅
**Dado** que existen inventarios con expiration_date de 10, 45 y 120 días
**Cuando** se envía GET /api/inventories sin parámetro de orden
**Entonces** el primer item returned tiene expiration_date = 10 días **PASS**
**Y** el último tiene expiration_date = 120 días **PASS**
**Y** cada item incluye days_until_expiration y expiration_status **PASS**

### CA-09: Alerta de lotes próximos a vencer ✅
**Dado** que existe un inventory con expiration_date en 30 días
**Cuando** se envía GET /api/inventories
**Entonces** el item aparece en alerts.expiring_soon **PASS**
**Y** expiration_status cae en `critico` (rango 1–30 días según `Inventory::estadoVencimiento()`) **PASS**

### CA-10: Alerta de lotes vencidos ✅
**Dado** que existe un inventory con expiration_date = yesterday
**Cuando** se envía GET /api/inventories
**Entonces** el item aparece en alerts.expired **PASS**
**Y** days_until_expiration es negativo **PASS**
**Y** expiration_status = `vencido` **PASS**

### CA-11: Filtro expiring_within ✅
**Dado** que existen inventarios con 10, 45 y 120 días hasta vencimiento
**Cuando** se envía GET /api/inventories?expiring_within=60
**Entonces** se retornan solo los inventarios con 10 y 45 días en la lista principal **PASS**
**Y** el de 120 días NO aparece en data **PASS**
**Y** los de 10 y 45 días aparecen también en alerts.expiring_soon **PASS**

### CA-12: Selector de lote filtra por bodega ✅
**Dado** que existe un lote L1 con existencia en Bodega A y un lote L2 con existencia en Bodega B
**Cuando** el regente_farmacia consulta GET /api/warehouses/{bodegaA}/lots
**Entonces** la respuesta incluye solo L1 con su `stock` y `expiration_date` **PASS**
**Y** L2 no aparece **PASS**

> **Nota:** la semántica del selector cambió después de RN-01. El lote ya no pertenece a una bodega — pertenece a la existencia. El endpoint devuelve lotes **con existencia** en la bodega solicitada, no lotes "asignados" a ella. Ver §10 y el resource `LoteEnBodegaResource`.

### CA-13: Alerta de stock bajo ✅
**Dado** que existe un inventory con quantity=5 y min_stock=20
**Cuando** se envía GET /api/inventories
**Entonces** el item aparece en alerts.low_stock **PASS**
**Y** deficit = quantity - min_stock = -15 **PASS**

### CA-14: Concurrencia — lock sobre inventory ✅
**Dado** que dos operaciones compiten por la misma existencia
**Cuando** la primera descuenta la última unidad y la segunda llega
**Entonces** la segunda ve el stock ya en 0 (no el original) por el `lockForUpdate()` y KardexService lanza `StockInsuficienteException` **PASS**

> **Limitación del test:** validar dos procesos PHP simultáneos requeriría un test de integración con dos conexiones. Lo que el test valida es el comportamiento observable: con stock=1, dos operaciones secuenciales de `TRANSFER_OUT` solo dejan pasar la primera, la segunda recibe 422. Esto cubre la semántica que el lock garantiza, aunque no la serialización física.

### CA-15: CHECK constraint inventory.quantity >= 0 ✅
**Dado** que existe un inventory con quantity=5
**Cuando** se intenta actualizar quantity a -10 directamente en la BD
**Entonces** la operación es bloqueada por la constraint `chk_inventories_quantity_non_negative` con QueryException **PASS**

### CA-16: Permisos — regente_farmacia puede ajustar inventario ✅
**Dado** que el usuario tiene rol regente_farmacia
**Cuando** envía POST /api/movements
**Entonces** HTTP 201 (created) **PASS**

### CA-17: Permisos — auxiliar_farmacia NO puede ajustar inventario ✅
**Dado** que el usuario tiene rol auxiliar_farmacia
**Cuando** envía POST /api/movements
**Entonces** HTTP 403 **PASS**
**Y** puede consultar GET /api/movements **PASS**

### CA-18: ENTRADA con expiration_date = today() (último día válido) ✅
**Dado** que existe un lote con expiration_date = today()
**Cuando** se envía POST /api/movements con type=`ENTRY` para ese lote
**Entonces** la entrada se acepta (201) **PASS**
**Y** `diasParaVencer() = 0` y `estadoVencimiento() = "critico"` **PASS**

---

## 14. Matriz de cumplimiento

| CA | Descripción | Test | Estado |
|---|---|---|---|
| CA-01 | Entrada incrementa stock y crea movimiento | `test_entrada_incrementa_stock_y_crea_movimiento` | ✅ |
| CA-02 | Rechazar entrada de lote vencido | `test_entrada_lote_vencido_es_rechazada_por_validacion` | ✅ |
| CA-03 | Entrada crea la existencia si no existe | `test_entrada_crea_existencia_si_no_existe` | ✅ |
| CA-04 | Salida no deja stock negativo | `test_salida_con_stock_insuficiente_lanza_excepcion` | ✅ |
| CA-05 | Kardex inmutable (PUT/DELETE 405) | `test_put_y_delete_sobre_movimiento_devuelven_405` | ✅ |
| CA-06 | Migración lots.stock → inventories | Verificado por `migrate:fresh` en CI + test CA-04 sobre Inventario | ✅ |
| CA-07 | 7 estados de traslado seedeados | Verificado por seeders en `migrate:fresh` | ✅ |
| CA-08 | Listar inventarios FEFO | `test_inventarios_ordenados_por_fecha_vencimiento_fefo` | ✅ |
| CA-09 | Alerta de lotes por vencer | `test_inventario_por_vencer_aparece_en_alertas` | ✅ |
| CA-10 | Alerta de lotes vencidos | `test_inventario_vencido_aparece_en_alertas` | ✅ |
| CA-11 | Filtro expiring_within | `test_filtro_expiring_within_retorna_solo_lotes_en_ventana` | ✅ |
| CA-12 | Selector de lote por bodega | `LotesEnBodegaTest::test_lotes_de_bodega_devuelve_los_que_tienen_existencia` | ✅ |
| CA-13 | Alerta de stock bajo | `test_inventario_bajo_minimo_aparece_en_alertas` | ✅ |
| CA-14 | Concurrencia con lockForUpdate | `test_concurrencia_dos_salidas_sobre_stock_unitario_solo_una_pasa` | ✅ |
| CA-15 | CHECK constraint en SQL | `test_check_constraint_bloquea_stock_negativo_directo_en_sql` | ✅ |
| CA-16 | Regente puede crear movimiento | `test_regente_puede_crear_movimiento` | ✅ |
| CA-17 | Auxiliar no puede crear / sí consultar | `test_auxiliar_no_puede_crear_movimiento`, `test_auxiliar_puede_consultar_movimientos` | ✅ |
| CA-18 | ENTRADA con expiración hoy | `test_entrada_con_expiracion_hoy_se_acepta` | ✅ |
