# HU-09 — Traslados entre Bodegas

**Proyecto:** Farmacia — Sistema de Gestión de Inventarios
**Fecha:** 2026-10-10
**Versión:** 1.1

---

## 1. Título y Descripción

**Título:** HU-09 — Traslados entre Bodegas

**Descripción:** Permitir la solicitud de traslado de productos (existencias) entre bodegas. El traslado atraviesa una máquina de 7 estados (BORRADOR → SOLICITADO → APROBADO → EN_TRANSITO → RECIBIDO / RECIBIDO_PARCIAL / ANULADO). La solicitud la crea el auxiliar_farmacia o regente_farmacia; la aprobación y el despacho los realiza exclusivamente el regente_farmacia. Un lote vencido (expiration_date < today) no puede ser aprobado ni despachado (RN-01).

---

## 2. Modelo de Datos

### Tabla: `transfers`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por la BD |
| transfer_number | varchar(20) | UNIQUE, NOT NULL, formato: TRF-YYYYMMDD-XXXX |
| from_warehouse_id | uuid | FK → warehouses (id), NOT NULL |
| to_warehouse_id | uuid | FK → warehouses (id), NOT NULL |
| status_id | uuid | FK → transfer_statuses (id), NOT NULL |
| requested_by | uuid | FK → users (id), NOT NULL |
| approved_by | uuid | FK → users (id), NULL |
| notes | text | NULL |
| created_at | timestamp | NOT NULL |
| updated_at | timestamp | NULL |

| FK | onDelete |
|---|---|
| from_warehouse_id → warehouses (id) | RESTRICT |
| to_warehouse_id → warehouses (id) | RESTRICT |
| status_id → transfer_statuses (id) | RESTRICT |
| requested_by → users (id) | RESTRICT |
| approved_by → users (id) | SET NULL |

### Tabla: `transfer_items`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por la BD |
| transfer_id | uuid | FK → transfers (id), NOT NULL |
| inventory_id | uuid | FK → inventories (id), NOT NULL |
| requested_quantity | integer | NOT NULL, CHECK > 0 |
| approved_quantity | integer | NULL |
| delivered_quantity | integer | NULL |
| created_at | timestamp | NOT NULL |

| FK | onDelete |
|---|---|
| transfer_id → transfers (id) | CASCADE |
| inventory_id → inventories (id) | RESTRICT |

### Tabla: `transfer_discrepancies`

RN-07 exige: *"Lo no recibido queda como discrepancia pendiente de resolución"*. La tabla materializa esa obligación de forma consultable.

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por la BD |
| transfer_id | uuid | FK → transfers (id), NOT NULL |
| transfer_item_id | uuid | FK → transfer_items (id), NOT NULL |
| approved_quantity | integer | NOT NULL — lo aprobado |
| delivered_quantity | integer | NOT NULL — lo efectivamente recibido |
| difference | integer | NOT NULL, CHECK >= 0 — approved − delivered |
| status | varchar(20) | NOT NULL, DEFAULT 'PENDING' — PENDING, RESOLVED, WRITTEN_OFF |
| resolution_notes | text | NULL |
| resolved_by | uuid | FK → users (id), NULL |
| resolved_at | timestamp | NULL |
| created_at | timestamp | NOT NULL |

| FK | onDelete |
|---|---|
| transfer_id → transfers (id) | CASCADE |
| transfer_item_id → transfer_items (id) | CASCADE |
| resolved_by → users (id) | SET NULL |

**Reglas:**
- Se crea una fila por cada item donde `delivered_quantity < approved_quantity` al ejecutar `receive()`.
- El traslado pasa a `RECIBIDO_PARCIAL` si hay al menos una discrepancia abierta; a `RECIBIDO` si no.
- Resolver una discrepancia es una operación separada (`PATCH /api/transfers/{id}/discrepancies/{did}/resolve`) y **no** devuelve stock automáticamente: la corrección stock se hace por ajuste en Kardex (RN-06).

---

## 3. Arquitectura en Capas

```
┌──────────────────────────────────────────────────────────────┐
│  Controller                                                    │
│  TransferController                                            │
│  → recibe request, llama TransferService, retorna Resource    │
└────────────────────────────┬─────────────────────────────────┘
                             │
              ┌──────────────▼──────────────┐
              │  Request                        │
              │  StoreTransferRequest            │
              │  → valida: items, warehouse_id   │
              │  ApproveTransferRequest          │
              │  → valida: approved_quantities   │
              │  ReceiveTransferRequest         │
              │  → valida: delivered_quantities  │
              └──────────────┬──────────────┘
                             │
            ┌────────────────▼────────────────┐
            │  Service                           │
            │  TransferService                   │
            │  (inyecta KardexService)           │
            │  → valida RN-01: lotes no vencidos│
            │  → valida RN-08: requested!=approved│
            │  → dispatch: llama kardex.salida   │
            │  → receive: llama kardex.entrada  │
            └────────────────┬────────────────┘
                             │
            ┌────────────────▼────────────────┐
            │  KardexService (inyectable)         │
            │  → recordMovement(..., TRASLADO_*)   │
            │  → DB::transaction + lockForUpdate   │
            └────────────────┬────────────────┘
                             │
            ┌────────────────▼────────────────┐
            │  Model                               │
            │  Transfer   TransferItem              │
            │  TransferStatus (seeded data)        │
            │  Inventory   Lot   Warehouse          │
            └─────────────────────────────────────┘

Resource ← serializa la respuesta JSON al frontend
```

**Flujo completo de un traslado:**

```
Solicitar (POST /api/transfers)
  StoreTransferRequest → valida items, from_warehouse != to_warehouse
    TransferService::request(items[], fromWarehouse, toWarehouse, notes)
      → crea Transfer (status=BORRADOR)
      → crea TransferItems
      → retorna TransferResource

Aprobar (PATCH /api/transfers/{id}/approve)
  ApproveTransferRequest → valida approved_quantities
    TransferService::approve(transfer, approvedItems[])
      → valida RN-08: requested_by != authenticated user
      → valida RN-01: para cada item, lot.expiration_date >= today()
      → actualiza Transfer.status → APROBADO, approved_by → user
      → actualiza TransferItems.approved_quantity

Despachar (PATCH /api/transfers/{id}/dispatch)
    TransferService::dispatch(transfer)
      → valida status == APROBADO
      → valida RN-01: cada lot no vencido
      → KardexService::recordMovement(TRASLADO_SALIDA, quantity) por cada item
      → Transfer.status → EN_TRANSITO

Recibir (PATCH /api/transfers/{id}/receive)
  ReceiveTransferRequest → valida delivered_quantities
    TransferService::receive(transfer, receivedItems[])
      → KardexService::recordMovement(TRASLADO_ENTRADA, quantity)
        sobre inventory en bodega DESTINO (o crea si no existe la terna)
      → status → RECIBIDO o RECIBIDO_PARCIAL

Anular (PATCH /api/transfers/{id}/cancel)
    TransferService::cancel(transfer, reason)
      → valida TransferStatus.is_cancellable == true
      → Transfer.status → ANULADO
      → NO registra movimientos de stock
```

---

## 4. Máquina de Estados

```
                        ┌─────────────────────┐
                        │      BORRADOR        │
                        │   (cancelable=true)   │
                        └──────────┬───────────┘
                                   │
                        ┌──────────▼───────────┐
                        │      SOLICITADO       │◄──────────┐
                        │   (cancelable=true)   │           │
                        └──────────┬───────────┘           │
                                   │                       │
               ┌──────────────────┴──────────────────┐    │
               │                                         │    │
    ┌──────────▼───────────┐              ┌──────────▼───────────┐
    │       ANULADO        │              │       APROBADO        │
    │  (terminal=true)     │              │  (cancelable=false)   │
    └─────────────────────┘              └──────────┬───────────┘
                                                    │
                                         ┌──────────▼───────────┐
                                         │     EN_TRANSITO      │
                                         │  (cancelable=false)   │
                                         └──────────┬───────────┘
                                                    │
                              ┌─────────────────────┴─────────────────────┐
                              │                                               │
                   ┌──────────▼───────────┐                   ┌──────────▼───────────┐
                   │      RECIBIDO       │                   │  RECIBIDO_PARCIAL   │
                   │  (terminal=true)    │                   │  (terminal=false)    │
                   └─────────────────────┘                   └──────────┬───────────┘
                                                                         │
                                                              ┌──────────▼───────────┐
                                                              │       ANULADO        │
                                                              │  (terminal=true)      │
                                                              └─────────────────────┘
```

---

## 5. Reglas de Negocio

| RN | Descripción | Implementación |
|---|---|---|
| RN-01 | Lote vencido no se traslada | TransferService valida lot.expiration_date >= today() en approve() y dispatch() |
| RN-03 | Stock no negativo | KardexService valida quantity >= 0 en cada movimiento |
| RN-06 | Kardex inmutable | movements no tiene update ni delete |
| RN-07 | 7 estados de transferencia | Tabla transfer_statuses con flags is_cancellable e is_terminal |
| RN-07 | Stock sale del origen al despachar, entra al destino al recibir | `dispatch()` descuenta con TRASLADO_SALIDA; `receive()` suma con TRASLADO_ENTRADA |
| RN-07 | Lo no recibido queda como discrepancia pendiente de resolución | Al recibir, la diferencia entre `approved_quantity` y `delivered_quantity` se persiste en la tabla `transfer_discrepancies` |
| RN-08 | Solicitante ≠ aprobador | TransferService::approve() valida requested_by != authenticated_user |

---

## 6. Permisos (PermissionSeeder)

**Módulo: `transfers`**

| Permiso | Descripción | Roles que lo tienen |
|---|---|---|
| crear-traslado | Solicitar traslados | auxiliar_farmacia, regente_farmacia |
| aprobar-traslado | Aprobar traslados | regente_farmacia |
| despachar-traslado | Despachar traslados aprobados | regente_farmacia |
| recibir-traslado | Recibir traslados en bodega destino | auxiliar_farmacia, regente_farmacia |
| anular-traslado | Anular traslados en estado cancelable | auxiliar_farmacia, regente_farmacia |
| consultar-traslados | Listar y ver traslados | todos los roles |

**Detalle de permisos por rol:**

| Rol | Solicitar | Aprobar | Despachar | Recibir | Anular | Consultar |
|---|---|---|---|---|---|---|
| regente_farmacia | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| auxiliar_farmacia | ✅ | ❌ | ❌ | ✅ | ✅ | ✅ |
| auditor | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| medico | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| admin | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |

> **Nota:** Según la sección 3 del brief, `auxiliar_farmacia` puede "crear y recibir traslados". El flujo operativo natural es: el auxiliar solicita el reabastecimiento (crear), el regente lo aprueba (aprobar), y el auxiliar recibe en destino (recibir). Por eso `crear-traslado` incluye solicitar traslado, y `aprobar-traslado` y `despachar-traslado` quedan exclusivos del regente.

---

## 7. Suposiciones

1. La tabla `inventories` existe y tiene datos (proveniente de HU-08).
2. `KardexService` está registrado como singleton inyectable.
3. `TransferStatus` con los 7 estados está seedeado (HU-08).
4. Un `transfer_item.inventory_id` apunta a un `inventory` de la bodega de origen (`from_warehouse`).
5. Al recibir (receive), el inventory de destino se resuelve por la terna bodega×producto×lote. Si no existe, se crea.
6. El flujo normal es: SOLICITUD → APROBACIÓN → DESPACHO → RECEPCIÓN.
7. Un traslado puede ser recibido parcialmente.
8. ANULADO es terminal: no hay transiciones salientes.
9. Transfer number usa formato `TRF-YYYYMMDD-XXXX` con secuencia diaria.

---

## 8. Fuera de Alcance (explícitamente excluido)

- Interfaz de usuario del frontend para traslados.
- Notificaciones por correo o alertas al aprobar/despachar.
- Informe de traslados pendientes o históricos.
- Recepción parcial con reposición de stock en origen.
- Devoluciones desde bodega destino a origen.
- Traslados con productos que no están en un inventory (deben existir primero).

---

## 9. Camino Feliz Principal (paso a paso)

### Solicitar traslado

1. El auxiliar_farmacia o regente_farmacia selecciona bodega destino y agrega items: inventory_id + cantidad solicitada.
2. El sistema valida que requested_quantity <= inventory.quantity (stock disponible).
3. El sistema valida que from_warehouse != to_warehouse.
4. El sistema crea el `transfer` con status=BORRADOR y transfer_number=TRF-YYYYMMDD-XXXX.
5. Se crea un `transfer_item` por cada item.
6. El sistema retorna el traslado creado.

### Aprobar traslado (regente_farmacia)

1. El regente_farmacia revisa la solicitud.
2. El sistema muestra los items solicitados con su stock disponible.
3. El regente ingresa quantities aprobadas (pueden ser menores).
4. El sistema valida RN-08: requested_by != authenticated_user.
5. El sistema valida RN-01: para cada item, lot.expiration_date >= today().
6. El sistema actualiza transfer.status → APROBADO, approved_by → regente.
7. Se actualizan transfer_items.approved_quantity.

### Despachar traslado

1. El regente hace clic en "Despachar" sobre un traslado APROBADO.
2. El sistema valida RN-01 nuevamente: cada lot no vencido.
3. El sistema ejecuta `KardexService::recordMovement(TRASLADO_SALIDA)` por cada item.
4. Se actualiza transfer.status → EN_TRANSITO.
5. El stock en la bodega de origen se descuenta.

### Recibir traslado

1. El auxiliar o regente en bodega destino recibe los items.
2. El sistema permite recibir cantidad total o parcial.
3. El sistema crea movimientos TRASLADO_ENTRADA sobre el inventory de destino.
4. Si la terna bodega×producto×lote no existe en destino, se crea el inventory.
5. Se actualiza transfer.status → RECIBIDO o RECIBIDO_PARCIAL.
6. El stock en la bodega de destino se incrementa.

### Anular traslado

1. El auxiliar_farmacia o regente_farmacia solicita la anulación sobre un traslado en estado BORRADOR o SOLICITADO.
2. El sistema verifica que transfer_status.is_cancellable = true.
3. Se actualiza transfer.status → ANULADO.
4. No se registran movimientos de stock.

---

## 10. Contrato de API

### GET /api/transfers

**Filtros:**
- `?status_id=uuid`
- `?from_warehouse_id=uuid`
- `?to_warehouse_id=uuid`
- `?requested_by=uuid`
- `?date_from=YYYY-MM-DD`
- `?date_to=YYYY-MM-DD`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "transfer_number": "TRF-20261010-0001",
      "from_warehouse": { "id": "uuid", "name": "Bodega Principal" },
      "to_warehouse": { "id": "uuid", "name": "Bodega Urgencias" },
      "status": { "id": "uuid", "name": "SOLICITADO" },
      "requested_by": { "id": "uuid", "name": "Juan Pérez" },
      "approved_by": null,
      "items": [
        {
          "id": "uuid",
          "inventory_id": "uuid",
          "product_name": "Acetaminofén 500mg",
          "lot_name": "LOTE-2026-001",
          "requested_quantity": 50,
          "approved_quantity": null,
          "delivered_quantity": null,
          "days_until_expiration": 30
        }
      ],
      "notes": "Urgente para atención de pacientes",
      "created_at": "2026-10-10T14:30:00Z"
    }
  ],
  "meta": { "current_page": 1, "total": 12 }
}
```

### POST /api/transfers

```json
{
  "to_warehouse_id": "uuid",
  "items": [
    {
      "inventory_id": "uuid",
      "requested_quantity": 50
    }
  ],
  "notes": "Urgente para atención de pacientes"
}
```

**Validaciones en `StoreTransferRequest`:**
1. `to_warehouse_id` — existe
2. `items` — array, mínimo 1 item
3. Para cada item:
   - `inventory_id` existe
   - la existencia pertenece a la bodega de origen del traslado
   - `requested_quantity > 0`
   - `requested_quantity <= inventory.quantity`

### PATCH /api/transfers/{id}/approve

```json
{
  "items": [
    { "transfer_item_id": "uuid", "approved_quantity": 45 }
  ]
}
```

**Validaciones en `ApproveTransferRequest`:**
1. Transfer existe y status = BORRADOR o SOLICITADO
2. authenticated_user tiene permiso aprobar-traslado
3. RN-08: authenticated_user.id != transfer.requested_by
4. Para cada item: approved_quantity <= requested_quantity
5. Para cada item: inventory.lot.expiration_date >= today() (RN-01)

### PATCH /api/transfers/{id}/dispatch

Sin body. Valida:
1. Transfer existe y status = APROBADO
2. authenticated_user tiene permiso despachar-traslado
3. Para cada item: inventory.lot.expiration_date >= today() (RN-01)

### PATCH /api/transfers/{id}/receive

```json
{
  "items": [
    { "transfer_item_id": "uuid", "delivered_quantity": 45 }
  ]
}
```

**Validaciones:**
1. Transfer existe y status = EN_TRANSITO
2. authenticated_user tiene permiso recibir-traslado
3. Para cada item: delivered_quantity <= approved_quantity
4. Si todos delivered_quantity == 0 → RECIBIDO_PARCIAL sin movimientos

### PATCH /api/transfers/{id}/cancel

```json
{
  "reason": "Ya no se necesita"
}
```

**Validaciones:**
1. Transfer existe y status permite cancelación (is_cancellable = true)
2. authenticated_user tiene permiso anular-traslado

---

## 11. Caminos Tristes (errores esperados)

| # | Escenario | Comportamiento del Sistema |
|---|---|---|
| TT-01 | Solicitar cantidad mayor al stock disponible | HTTP 422. Mensaje: "Cantidad solicitada excede el stock disponible en el inventario" |
| TT-02 | Aprobar con requested_by == authenticated_user (RN-08) | HTTP 422. Mensaje: "El aprobador no puede ser el mismo que solicitó el traslado" |
| TT-03 | Aprobar con lote vencido (RN-01) | HTTP 422. Mensaje: "No se pueden aprobar traslados con lotes vencidos" |
| TT-04 | Despachar traslado no aprobado | HTTP 422. Mensaje: "El traslado debe estar aprobado para poder ser despachado" |
| TT-05 | Recibir más de lo aprobado | HTTP 422. Mensaje: "La cantidad recibida no puede exceder la cantidad aprobada" |
| TT-06 | Anular en estado APROBADO | HTTP 422. Mensaje: "No se puede anular un traslado que ya fue aprobado" |
| TT-07 | Transición de estado no definida | HTTP 422. Mensaje: "Transición de estado no permitida" |
| TT-08 | Bodega origen = bodega destino | HTTP 422. Mensaje: "La bodega de origen y destino no pueden ser la misma" |
| TT-09 | Transfer number duplicado | UNIQUE constraint. Error 500 en la segunda solicitud del mismo día |
| TT-10 | Aprobar sin tener permiso aprobar-traslado | HTTP 403 |

---

## 12. Casos Límite y Casos Estúpidos

### Casos Límite

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| TL-01 | approved_quantity = 0 en todos los items | El traslado se aprueba pero con quantity 0. El despacho lo impide |
| TL-02 | Recibir parcialmente: algunos items en 0 | status → RECIBIDO_PARCIAL. Los items con delivered=0 no generan movimiento |
| TL-03 | Transfer number colisión (mismo día, misma secuencia) | UNIQUE constraint. Segunda solicitud falla. Se resuelve con lock o MAX(sequence)+1 |
| TL-04 | Todos los delivered_quantity = 0 | status → RECIBIDO_PARCIAL. No se crean movimientos. Sin cambio de stock |
| TL-05 | Bodega de origen sin stock para ningún item | Permite crear la solicitud. El regente ve stock 0 y debe ajustar quantities |

### Casos Estúpidos

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| TE-01 | Solicitar traslado con quantity <= 0 | CHECK constraint en transfer_items.requested_quantity > 0. HTTP 422 |
| TE-02 | Doble clic en "Aprobar" | Botón deshabilita. Segundo request recibe error de estado (no puede aprobar dos veces) |
| TE-03 | Modificar items de un traslado ya aprobado | HTTP 422. Estado APROBADO no permite modificación |
| TE-04 | Intentar aprobar un traslado ya despachado | HTTP 422. Estado EN_TRANSITO no permite approve |
| TE-05 | Recibir sin tener permiso | HTTP 403 |
| TE-06 | Intentar despachar un traslado ya recibido | HTTP 422. Estado terminal |
| TE-07 | Solicitar traslado sin items | HTTP 422. StoreTransferRequest valida mínimo 1 item |

---

## 13. Criterios de Aceptación

### CA-01: Solicitar traslado (Camino Feliz)
**Dado** que existe un inventory con quantity >= 50 en la Bodega Principal
**Cuando** el usuario envía POST /api/transfers con to_warehouse_id=Bodega Urgencias, items=[{inventory_id, requested_quantity=50}]
**Entonces** se crea un transfer con status=BORRADOR y transfer_number=TRF-YYYYMMDD-XXXX PASS / FAIL
**Y** se crean los transfer_items asociados PASS / FAIL

### CA-02: Aprobar traslado (Camino Feliz)
**Dado** que existe un transfer en estado BORRADOR
**Cuando** el regente_farmacia envía PATCH /api/transfers/{id}/approve con approved_quantities
**Entonces** transfer.status → APROBADO PASS / FAIL
**Y** approved_by → regente_farmacia PASS / FAIL
**Y** cada transfer_item.approved_quantity se actualiza PASS / FAIL

### CA-03: Rechazar approve por lote vencido (RN-01)
**Dado** que existe un transfer con un item cuyo lot tiene expiration_date < today
**Cuando** el regente_farmacia intenta aprobar
**Entonces** HTTP 422 PASS / FAIL
**Y** mensaje: "No se pueden aprobar traslados con lotes vencidos" PASS / FAIL

### CA-04: Rechazar approve por requested_by == approved_by (RN-08)
**Dado** que el usuario A solicita un traslado y es también regente_farmacia
**Cuando** el usuario A intenta aprobar su propio traslado
**Entonces** HTTP 422 PASS / FAIL
**Y** mensaje: "El aprobador no puede ser el mismo que solicitó" PASS / FAIL

### CA-05: Despachar traslado
**Dado** que existe un transfer en estado APROBADO con items aprobados
**Cuando** se envía PATCH /api/transfers/{id}/dispatch
**Entonces** transfer.status → EN_TRANSITO PASS / FAIL
**Y** se crean movimientos TRASLADO_SALIDA por cada item PASS / FAIL
**Y** inventory.quantity en bodega origen se decrementa PASS / FAIL

### CA-06: Rechazar dispatch con lote vencido (RN-01)
**Dado** que existe un transfer en estado APROBADO y un item venció después de la aprobación
**Cuando** se envía PATCH /api/transfers/{id}/dispatch
**Entonces** HTTP 422 PASS / FAIL
**Y** mensaje: "No se pueden despachar traslados con lotes vencidos" PASS / FAIL

### CA-07: Recibir traslado completo
**Dado** que existe un transfer en estado EN_TRANSITO
**Cuando** se envía PATCH /api/transfers/{id}/receive con delivered_quantities = approved_quantities
**Entonces** transfer.status → RECIBIDO PASS / FAIL
**Y** se crean movimientos TRASLADO_ENTRADA por cada item PASS / FAIL
**Y** inventory.quantity en bodega destino se incrementa PASS / FAIL

### CA-08: Recibir parcialmente
**Dado** que existe un transfer en estado EN_TRANSITO con 3 items aprobados
**Cuando** se reciben solo 2 items (el tercero con delivered_quantity=0)
**Entonces** transfer.status → RECIBIDO_PARCIAL PASS / FAIL
**Y** se crean movimientos TRASLADO_ENTRADA solo para los 2 items recibidos PASS / FAIL

### CA-09: Crear existence en bodega destino si no existe
**Dado** que en la bodega destino no existe la terna bodega×producto×lote
**Cuando** se recibe un traslado con ese item
**Entonces** TransferService ejecuta de forma atómica: `Inventory::firstOrCreate(warehouse_id, product_id, lot_id)` — el lote aporta el producto, la bodega se toma del traslado PASS / FAIL
**Y** el movimiento TRASLADO_ENTRADA se registra sobre esa existencia (existente o recién creada) PASS / FAIL
**Y** la operación completa (crear + movimiento) está dentro de la misma transacción DB PASS / FAIL

### CA-09b: Lo no recibido queda como discrepancia pendiente (RN-07)
**Dado** que existe un transfer en estado EN_TRANSITO con un item aprobado en 50 unidades
**Cuando** se recibe con delivered_quantity = 30
**Entonces** se crea un registro en transfer_discrepancies con approved=50, delivered=30, difference=20, status='PENDING' PASS / FAIL
**Y** transfer.status → RECIBIDO_PARCIAL PASS / FAIL
**Y** resolver la discrepancia es una operación separada y NO devuelve stock automáticamente: la corrección se hace por ajuste en Kardex (RN-06) PASS / FAIL

### CA-09c: Recepción completa no genera discrepancias
**Dado** que existe un transfer en estado EN_TRANSITO con un item aprobado en 50 unidades
**Cuando** se recibe con delivered_quantity = 50
**Entonces** transfer.status → RECIBIDO PASS / FAIL
**Y** no se crea ningún registro en transfer_discrepancies PASS / FAIL

### CA-10: Anular traslado en estado SOLICITADO
**Dado** que existe un transfer en estado SOLICITADO
**Cuando** se envía PATCH /api/transfers/{id}/cancel
**Entonces** transfer.status → ANULADO PASS / FAIL
**Y** no se registran movimientos de stock PASS / FAIL

### CA-11: No se puede anular en estado APROBADO
**Dado** que existe un transfer en estado APROBADO
**Cuando** se envía PATCH /api/transfers/{id}/cancel
**Entonces** HTTP 422 PASS / FAIL
**Y** mensaje: "No se puede anular un traslado que ya fue aprobado" PASS / FAIL

### CA-12: Validar bodega origen ≠ bodega destino
**Dado** que el usuario selecciona la misma bodega como origen y destino
**Cuando** se envía POST /api/transfers
**Entonces** HTTP 422 PASS / FAIL
**Y** mensaje: "La bodega de origen y destino no pueden ser la misma" PASS / FAIL

### CA-13: Máquina de estados — transición inválida
**Dado** que existe un transfer en estado EN_TRANSITO
**Cuando** se intenta PATCH /api/transfers/{id}/approve
**Entonces** HTTP 422 PASS / FAIL
**Y** mensaje: "Transición de estado no permitida" PASS / FAIL

### CA-14: Stock no disponible para solicitud
**Dado** que existe un inventory con quantity=10
**Cuando** se envía POST /api/transfers con requested_quantity=50
**Entonces** HTTP 422 PASS / FAIL
**Y** mensaje: "Cantidad solicitada excede el stock disponible" PASS / FAIL

### CA-15: Transfer number único y auto-generado
**Dado** que es el primer traslado del día
**Cuando** se crea un traslado
**Entonces** transfer_number = TRF-YYYYMMDD-0001 PASS / FAIL
**Y** el segundo traslado del día recibe TRF-YYYYMMDD-0002 PASS / FAIL

### CA-16: Permisos — regente_farmacia tiene todos los permisos de traslado
**Dado** que el usuario tiene rol regente_farmacia
**Cuando** envía POST /api/transfers (solicitar)
**Entonces** HTTP 200 PASS / FAIL
**Cuando** envía PATCH /api/transfers/{id}/approve
**Entonces** HTTP 200 PASS / FAIL
**Cuando** envía PATCH /api/transfers/{id}/dispatch
**Entonces** HTTP 200 PASS / FAIL
**Cuando** envía PATCH /api/transfers/{id}/receive
**Entonces** HTTP 200 PASS / FAIL
**Cuando** envía PATCH /api/transfers/{id}/cancel
**Entonces** HTTP 200 PASS / FAIL

### CA-17: Auxiliar_farmacia puede crear y recibir traslados, pero NO aprobar
**Dado** que el usuario tiene rol auxiliar_farmacia
**Cuando** envía POST /api/transfers (solicitar)
**Entonces** HTTP 200 PASS / FAIL
**Cuando** envía PATCH /api/transfers/{id}/approve
**Entonces** HTTP 403 PASS / FAIL
**Cuando** envía PATCH /api/transfers/{id}/receive
**Entonces** HTTP 200 PASS / FAIL

### CA-18: Listar traslados con filtros
**Dado** que existen traslados en varios estados y bodegas
**Cuando** se envía GET /api/transfers?status_id={uuid}&from_warehouse_id={uuid}
**Entonces** se retornan solo los traslados que coinciden con ambos filtros PASS / FAIL

### CA-19: RN-03 — Kardex no deja stock negativo en despacho
**Dado** que existe un inventory con quantity=5 en bodega origen
**Cuando** se intenta despachar un transfer con approved_quantity=10 para ese item
**Entonces** KardexService lanza InsufficientStockException al intentar TRASLADO_SALIDA PASS / FAIL
**Y** HTTP 422 con mensaje "Stock insuficiente para el movimiento solicitado" PASS / FAIL
**Y** inventory.quantity permanece en 5 PASS / FAIL
