# HU-10 — Dispensación de Medicamentos

**Proyecto:** Farmacia — Sistema de Gestión de Inventarios
**Fecha:** 2026-10-10
**Versión:** 1.0

---

## 1. Título y Descripción

**Título:** HU-10 — Dispensación de Medicamentos

**Descripción:** Permitir la dispensación de medicamentos a pacientes mediante prescripción médica. El sistema gestiona el ciclo completo: creación de prescripciones, validación de existencia (FEFO), descuento de stock vía Kardex (movimiento DISPENSACION), doble autorización para medicamentos de control especial (RN-05), y enmascaramiento de datos sensibles para auditores (RN-10). La dispensación es idempotente: una misma prescripción no puede dispensarse dos veces.

---

## 2. Modelo de Datos

### Tabla: `patients`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por la BD |
| document_type | varchar(20) | NOT NULL — CC, TI, CE, PASAPORTE, otro |
| document_number | varchar(30) | NOT NULL, UNIQUE |
| first_name | varchar(60) | NOT NULL |
| last_name | varchar(60) | NOT NULL |
| date_of_birth | date | NOT NULL |
| phone | varchar(20) | NULL |
| email | varchar(100) | NULL |
| eps | varchar(100) | NULL |
| created_by | uuid | FK → users (id), NOT NULL |
| created_at | timestamp | NOT NULL |
| updated_at | timestamp | NULL |

| FK | onDelete |
|---|---|
| created_by → users (id) | RESTRICT |

**Notas:**
- `document_number` es único a nivel global: un paciente se identifica por su documento.
- Los campos `phone`, `email`, `eps` son opcionales.
- No se usa SoftDeletes: el historial clínico debe ser trazable. Si el paciente deja de attenderse, se marca `is_active = false` y sus prescripciones y dispensaciones se conservan.

### Tabla: `patient_access_logs` (bitácora — RN-10)

RN-10 exige *"bitácora de quién consultó qué paciente, y ningún dato personal en los logs"*. Esta tabla es el cumplimiento explícito.

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por la BD |
| patient_id | uuid | FK → patients (id), NOT NULL |
| accessed_by | uuid | FK → users (id), NOT NULL |
| access_type | varchar(20) | NOT NULL — VIEW, CREATE, UPDATE, DISPENSE |
| ip_address | varchar(45) | NULL |
| created_at | timestamp | NOT NULL |

| FK | onDelete |
|---|---|
| patient_id → patients (id) | RESTRICT |
| accessed_by → users (id) | RESTRICT |

**Reglas de privacidad (RN-10):**
- La bitácora registra **quién** y **cuándo**, nunca **qué datos** vio: no sealmacena `document_number`, `phone` ni `email`.
- Los logs de aplicación (canal `laravel`) deben usar un middleware que redacte cualquier clave de patrón `patient.*`, `document_number`, `phone`, `email`.
- La escritura ocurre en `PatientService::find()` y `DispenseService::dispense()`, dentro de la misma transacción que la operación de negocio.

### Tabla: `prescriptions`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por la BD |
| prescription_number | varchar(20) | UNIQUE, NOT NULL, formato: PRE-YYYYMMDD-XXXX |
| patient_id | uuid | FK → patients (id), NOT NULL |
| prescriber_name | varchar(100) | NOT NULL — nombre del médico que prescribe |
| prescriber_license | varchar(30) | NOT NULL — número de licencia médica |
| notes | text | NULL |
| expires_at | date | NOT NULL — fecha de vencimiento de la prescripción |
| status | varchar(20) | NOT NULL, DEFAULT 'active' — active, dispensed, expired, cancelled |
| created_by | uuid | FK → users (id), NOT NULL |
| created_at | timestamp | NOT NULL |
| updated_at | timestamp | NULL |

| FK | onDelete |
|---|---|
| patient_id → patients (id) | RESTRICT |
| created_by → users (id) | RESTRICT |

**Estados de prescripción:**
| status | descripción |
|---|---|
| `active` | Vigente, puede dispensarse |
| `dispensed` | Ya dispensada completamente |
| `expired` | Superó expires_at sin dispensarse completamente |
| `cancelled` | Cancelada manualmente |

### Tabla: `prescription_items`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por la BD |
| prescription_id | uuid | FK → prescriptions (id), NOT NULL |
| product_id | uuid | FK → products (id), NOT NULL |
| quantity_prescribed | integer | NOT NULL, CHECK > 0 |
| quantity_dispensed | integer | NOT NULL, DEFAULT 0 |
| created_at | timestamp | NOT NULL |

| FK | onDelete |
|---|---|
| prescription_id → prescriptions (id) | CASCADE |
| product_id → products (id) | RESTRICT |

**Notas:**
- `quantity_dispensed` acumula lo dispensado. Si `quantity_dispensed >= quantity_prescribed`, el item está completo.
- Cuando todos los items de una prescripción están completos, la prescripción pasa a `dispensed`.

### Tabla: `dispenses`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por la BD |
| dispense_number | varchar(20) | UNIQUE, NOT NULL, formato: DIS-YYYYMMDD-XXXX |
| idempotency_key | varchar(64) | UNIQUE, NOT NULL — RN-09, ver nota abajo |
| prescription_id | uuid | FK → prescriptions (id), NOT NULL |
| patient_id | uuid | FK → patients (id), NOT NULL |
| dispensed_by | uuid | FK → users (id), NOT NULL — auxiliar que dispensó |
| authorized_by | uuid | FK → users (id), NULL — regente que autorizó (RN-05) |
| notes | text | NULL |
| created_at | timestamp | NOT NULL |

| FK | onDelete |
|---|---|
| prescription_id → prescriptions (id) | RESTRICT |
| patient_id → patients (id) | RESTRICT |
| dispensed_by → users (id) | RESTRICT |
| authorized_by → users (id) | SET NULL |

**Notas:**
- `authorized_by` es NULL para dispensas normales. Es obligatorio cuando al menos un item es de control especial (RN-05).
- Una dispensación es la respuesta a UNA operación de dispensar. Varios items pueden dispensarse en la misma dispensación.

**Idempotencia (RN-09).** El brief exige: *"si el cliente reintenta la misma solicitud, no se duplica la salida de stock"*. La constraint UNIQUE sobre `idempotency_key` es la garantía a nivel de base de datos, no una comprobación en PHP.

- El cliente envía `Idempotency-Key` como header HTTP (UUID) en cada `POST /api/dispenses`.
- Si la key ya existe, el servicio **devuelve la dispensación original** con HTTP 200 y no vuelve a descontar stock.
- Si es nueva, se procesa; una colisión concurrente de la misma key resuelve por la constraint UNIQUE y el segundo request recupera el registro existente.
- La key se valida como obligatoria antes de ejecutar cualquier lógica de negocio.

### Tabla: `dispense_items`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por la BD |
| dispense_id | uuid | FK → dispenses (id), NOT NULL |
| prescription_item_id | uuid | FK → prescription_items (id), NOT NULL |
| inventory_id | uuid | FK → inventories (id), NOT NULL — lote específico dispensado |
| quantity | integer | NOT NULL, CHECK > 0 |
| created_at | timestamp | NOT NULL |

| FK | onDelete |
|---|---|
| dispense_id → dispenses (id) | CASCADE |
| prescription_item_id → prescription_items (id) | RESTRICT |
| inventory_id → inventories (id) | RESTRICT |

**Notas:**
- `inventory_id` indica qué lote específico se dispensó (FEFO: el de vencimiento más próximo).
- Un `prescription_item_id` puede aparecer en varios `dispense_items` si se dispensó en partes (parcial).

---

## 3. Arquitectura en Capas

```
┌──────────────────────────────────────────────────────────────┐
│  Controller                                                       │
│  PatientController  →  lista/busca pacientes                    │
│  PrescriptionController  →  CRUD prescripciones                  │
│  DispenseController  →  dispensar, obtener siguiente lote FEFO   │
│  → recibe request, llama al service, retorna resource            │
└────────────────────────────┬─────────────────────────────────┘
                             │
              ┌──────────────▼──────────────┐
              │  Request                          │
              │  StorePrescriptionRequest          │
              │  → valida: patient_id, items[]    │
              │  DispensePrescriptionRequest      │
              │  → valida: prescription_id, items│
              │  StorePatientRequest               │
              │  → valida: documento, nombres     │
              └──────────────┬───────────────────┘
                             │
            ┌────────────────▼────────────────┐
            │  Service                               │
            │  DispenseService                      │
            │  → valida FEFO, obtiene siguiente     │
            │  → llama KardexService::recordMovement │
            │  → maneja idempotencia por prescripción│
            │  PrescriptionService                  │
            │  → crea prescripción + items           │
            │  PatientService                        │
            │  → CRUD pacientes                      │
            └────────────────┬──────────────────────┘
                             │
            ┌────────────────▼────────────────┐
            │  KardexService (inyectable)         │
            │  → recordMovement(DISPENSACION, ...)  │
            └─────────────────────────────────────┘

Resource ← serializa respuesta JSON al frontend
```

**Flujo de una dispensación:**

```
1.auxiliar_farmacia busca/crea paciente
2.auxiliar_farmacia crea prescripción con items (producto + cantidad_recetada)
3.regente_farmacia revisa y activa prescripción
4.auxiliar_farmacia inicia dispensación
  DispenseService::prepare(prescriptionId)
    → obtiene items pendientes (quantity_prescribed - quantity_dispensed > 0)
    → por cada item: busca siguiente lote FEFO disponible (Inventory con quantity > 0, ordenado por expiration_date ASC)
    → retorna items listos para dispensar con inventory_id y quantity disponible

5.auxiliar_farmacia confirma dispensación
  DispenseService::dispense(dispenseData)
    → valida que cada inventory_id aún tenga stock (FEFO puede cambiar si hubo movimientos concurrentes)
    → si algún item es control especial: requiere authorized_by (regente)
    → DB::transaction con lockForUpdate sobre cada inventory
    → KardexService::recordMovement(DISPENSACION, quantity)
    → actualiza prescription_items.quantity_dispensed
    → si todos los items completos: prescription.status → 'dispensed'
    → retorna dispense con items dispensados
```

---

## 4. Reglas de Negocio

| RN | Descripción | Implementación |
|---|---|---|
| RN-01 | Lote vencido no se dispensa | DispenseService valida lot.expiration_date >= today() antes de asignar FEFO |
| RN-02 | FEFO — primero en expirar, primero en dispensar | Inventories se consultan ordenados por expiration_date ASC, filtrados por product_id y quantity > 0 |
| RN-03 | Stock no negativo | KardexService valida inventory.quantity - quantity >= 0 antes de registrar DISPENSACION |
| RN-04 | Regente aprueba todo movimiento que modifique stock | La dispensación la ejecuta auxiliar; regente solo interviene en RN-05 (control especial) |
| RN-05 | Doble autorización para control especial | Si algún item de la prescripción tiene product.requires_authorization = true: se requieren DOS usuarios (dispensado_por + autorizado_por), ambos diferentes |
| RN-06 | Kardex inmutable | movements es inmutable; DISPENSACION se registra pero no se modifica ni elimina |
| RN-09 | No dispensar a sí mismo | dispensed_by != patient_id (no aplica — paciente no es usuario del sistema) |
| RN-10 | Datos de paciente enmascarados para auditor | PatientResource filtra campos sensibles según rol; auditor ve documentopartial (últimos 4 dígitos), phone oculto, email oculto |

---

## 5. Permisos (PermissionSeeder)

**Módulo: `patients`**

| Permiso | Descripción | Roles |
|---|---|---|
| crear-paciente | Registrar nuevos pacientes | regente_farmacia, auxiliar_farmacia |
| consultar-pacientes | Ver datos completos de pacientes | regente_farmacia, auxiliar_farmacia, medico |
| consultar-pacientes-enmascarados | Ver datos enmascarados | auditor |

**Módulo: `prescriptions`**

| Permiso | Descripción | Roles |
|---|---|---|
| crear-prescripcion | Crear nuevas prescripciones | regente_farmacia, medico |
| consultar-prescripciones | Listar y ver prescripciones | regente_farmacia, auxiliar_farmacia, medico |
| activar-prescripcion | Activar o cancelar prescripción | regente_farmacia |
| dispensar-prescripcion | Ejecutar dispensación | regente_farmacia, auxiliar_farmacia |

**Detalle de permisos por rol:**

| Rol | Crear paciente | Consultar paciente | Crear prescripción | Dispensar |
|---|---|---|---|---|
| regente_farmacia | ✅ | ✅ | ✅ | ✅ |
| auxiliar_farmacia | ✅ | ✅ | ❌ | ✅ |
| medico | ❌ | ✅ | ✅ | ❌ |
| auditor | ❌ | ✅ enmascarado | ❌ | ❌ |
| admin | ❌ | ✅ | ❌ | ❌ |

---

## 6. Suposiciones

1. Un paciente no es un usuario del sistema. No tiene login. Se identifica por documento.
2. Una prescripción puede tener varios items (multiproducto).
3. Una prescripción puede dispensarse parcialmente: varios dispense_records sobre la misma prescripción hasta completar.
4. FEFO se aplica a nivel de producto: si hay varios lotes del mismo producto, se dispensa primero el de vencimiento más próximo.
5. Si no hay stock disponible de un producto, ese item queda pendiente y la dispensación continúa con los demás items.
6. Una prescripción vencida (expires_at < today) no puede dispensarse.
7. Un item ya dispensado completamente (quantity_dispensed >= quantity_prescribed) no puede volver a dispensarse.
8. El auditor ve los datos del paciente enmascarados, pero no ve el nombre completo ni el documento completo.
9. `authorized_by` se registra en dispenses para control especial, pero no bloquea el flujo si es NULL para items normales.
10. La idempotencia de dispensación se controla por `prescription_item_id`: un item completo no se puede dispensar dos veces.

---

## 7. Fuera de Alcance (explícitamente excluido)

- Edición de una prescripción ya activada.
- Devoluciones de medicamentos dispensados.
- Reportes de dispensación por período, por producto, por paciente.
- Integración con sistemas de salud externos (EPS, ADRES).
- Notificaciones por correo al paciente o al médico.
- Historia clínica electrónica (HCE).
- Recetas electrónicas verificadas por firma digital.

---

## 8. Camino Feliz Principal (paso a paso)

### Registrar paciente

1. El auxiliar_farmacia o regente_farmacia accede a la pantalla de pacientes.
2. Llena: tipo documento, número, nombres, apellidos, fecha de nacimiento, teléfono (opcional), EPS (opcional).
3. El sistema valida que el número de documento no esté duplicado.
4. El sistema crea el paciente con created_by = usuario autenticado.
5. Retorna el paciente creado.

### Crear prescripción

1. El medico o regente_farmacia selecciona un paciente existente.
2. Ingresa: nombre del médico, número de licencia, fecha de vencimiento de la receta, notas (opcional).
3. Agrega items: producto (buscador) + cantidad prescrita.
4. El sistema valida que cada producto exista y tenga stock en alguna bodega.
5. El sistema crea la prescripción en status='active' con todos los items.
6. Retorna la prescripción creada.

### Preparar dispensación (FEFO)

1. El auxiliar_farmacia selecciona una prescripción active.
2. El sistema calcula los items pendientes: `quantity_prescribed - quantity_dispensed > 0`.
3. Por cada item pendiente, el sistema consulta Inventories ordenados por expiration_date ASC donde quantity > 0.
4. El sistema retorna una lista de items con: inventory_id, product_name, requested_quantity, available_quantity, expiration_date, days_until_expiration.
5. El auxiliar puede ajustar las cantidades a dispensar (no exceder available_quantity).

### Confirmar dispensación

1. El auxiliar_farmacia confirma la dispensación con las cantidades ajustadas.
2. Si algún item tiene product.requires_authorization = true:
   - El sistema requiere que un regente_farmacia autorice con su usuario y contraseña.
   - `authorized_by` se registra en la dispensación.
3. El sistema ejecuta DB::transaction con lockForUpdate sobre cada inventory.
4. Por cada item: KardexService::recordMovement(DISPENSACION, quantity).
5. Se actualizan prescription_items.quantity_dispensed.
6. Si todos los items están completos: prescription.status → 'dispensed'.
7. Se crea el registro de dispensación con dispense_number=DIS-YYYYMMDD-XXXX.
8. Retorna la dispensación con el detalle de lo dispensado.

### Consultar paciente (auditor — enmascarado)

1. El auditor consulta GET /api/patients/{id}.
2. El sistema retorna los datos con enmascaramiento:
   - `document_number`: últimos 4 dígitos + "****"
   - `phone`: "***********"
   - `email`: "****@****.com"
   - `first_name` y `last_name`: visibles

---

## 9. Contrato de API

### GET /api/patients

**Filtros:**
- `?document_number=xxx`
- `?first_name=xxx`
- `?last_name=xxx`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "document_type": "CC",
      "document_number": "1098765432",
      "first_name": "María",
      "last_name": "García",
      "date_of_birth": "1985-03-15",
      "phone": "3001234567",
      "eps": "Sura EPS",
      "created_at": "2026-01-15T10:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "total": 45 }
}
```

### POST /api/patients

```json
{
  "document_type": "CC",
  "document_number": "1098765432",
  "first_name": "María",
  "last_name": "García",
  "date_of_birth": "1985-03-15",
  "phone": "3001234567",
  "email": "maria@email.com",
  "eps": "Sura EPS"
}
```

### GET /api/patients/{id} (con enmascaramiento)

```json
{
  "data": {
    "id": "uuid",
    "document_type": "CC",
    "document_number": "****5432",
    "first_name": "María",
    "last_name": "García",
    "date_of_birth": "1985-03-15",
    "phone": "***********",
    "email": "****@****.com",
    "eps": "Sura EPS",
    "masked": true
  }
}
```

### GET /api/prescriptions

**Filtros:**
- `?patient_id=uuid`
- `?status=active|dispensed|expired|cancelled`
- `?date_from=YYYY-MM-DD`
- `?date_to=YYYY-MM-DD`

### POST /api/prescriptions

```json
{
  "patient_id": "uuid",
  "prescriber_name": "Dr. Carlos Mendoza",
  "prescriber_license": "MED-2024-8821",
  "notes": "Tomar con alimentos",
  "expires_at": "2026-11-10",
  "items": [
    { "product_id": "uuid", "quantity_prescribed": 30 },
    { "product_id": "uuid", "quantity_prescribed": 20 }
  ]
}
```

### GET /api/prescriptions/{id}

```json
{
  "data": {
    "id": "uuid",
    "prescription_number": "PRE-20261010-0001",
    "patient": { "id": "uuid", "first_name": "María", "last_name": "García" },
    "prescriber_name": "Dr. Carlos Mendoza",
    "prescriber_license": "MED-2024-8821",
    "status": "active",
    "expires_at": "2026-11-10",
    "items": [
      {
        "id": "uuid",
        "product_name": "Acetaminofén 500mg",
        "quantity_prescribed": 30,
        "quantity_dispensed": 10,
        "pending_quantity": 20
      }
    ],
    "created_at": "2026-10-10T09:00:00Z"
  }
}
```

### GET /api/dispenses/prepare/{prescription_id}

Retorna los items listos para dispensar con FEFO aplicado:

```json
{
  "data": {
    "prescription_id": "uuid",
    "prescription_number": "PRE-20261010-0001",
    "patient": { "id": "uuid", "first_name": "María", "last_name": "García" },
    "items_to_dispense": [
      {
        "prescription_item_id": "uuid",
        "product_id": "uuid",
        "product_name": "Acetaminofén 500mg",
        "quantity_prescribed": 30,
        "quantity_dispensed": 10,
        "pending_quantity": 20,
        "requires_authorization": false,
        "fefo_suggestion": {
          "inventory_id": "uuid",
          "lot_name": "LOTE-2026-001",
          "expiration_date": "2026-03-15",
          "days_until_expiration": 30,
          "available_quantity": 150,
          "quantity_to_dispense": 20
        }
      }
    ],
    "requires_special_authorization": false
  }
}
```

### POST /api/dispenses

```json
{
  "prescription_id": "uuid",
  "authorized_by": "uuid",
  "notes": "Dispensación parcial",
  "items": [
    {
      "prescription_item_id": "uuid",
      "inventory_id": "uuid",
      "quantity": 20
    }
  ]
}
```

**Nota:** `authorized_by` es obligatorio si `requires_special_authorization = true` en la respuesta de prepare.

### GET /api/dispenses

**Filtros:**
- `?prescription_id=uuid`
- `?patient_id=uuid`
- `?dispensed_by=uuid`
- `?date_from=YYYY-MM-DD`
- `?date_to=YYYY-MM-DD`

---

## 10. Caminos Tristes (errores esperados)

| # | Escenario | Comportamiento del Sistema |
|---|---|---|
| DP-01 | Dispensar con stock insuficiente | KardexService lanza InsufficientStockException. HTTP 422. Mensaje: "Stock insuficiente para el producto X" |
| DP-02 | Dispensar item ya completamente dispensado | HTTP 422. Mensaje: "Este item ya fue dispensado completamente" |
| DP-03 | Dispensar prescripción vencida | HTTP 422. Mensaje: "La prescripción está vencida y no puede dispensarse" |
| DP-04 | Dispensar prescripción cancelada | HTTP 422. Mensaje: "La prescripción está cancelada" |
| DP-05 | Producto de control especial sin autorización de regente | HTTP 422. Mensaje: "Este producto requiere autorización del regente de farmacia" |
| DP-06 | El mismo usuario intenta dispensar y autorizar (RN-05) | HTTP 422. Mensaje: "El autorizante no puede ser la misma persona que dispensa" |
| DP-07 | Lote seleccionado en FEFO ya no tiene stock (race condition) | HTTP 422. Mensaje: "El stock del lote seleccionado cambió, por favor reconsulte FEFO" |
| DP-08 | Paciente duplicado (mismo número de documento) | HTTP 409. Mensaje: "Ya existe un paciente con ese número de documento" |
| DP-09 | Prescripción sin items | HTTP 422. Validación de StorePrescriptionRequest |
| DP-10 | Producto sin inventario disponible para FEFO | El item se excluye de la preparación con mensaje: "Sin stock disponible para [producto]" |
| DP-11 | Permiso insuficiente para crear prescripción | HTTP 403 |
| DP-12 | Permiso insuficiente para dispensar | HTTP 403 |

---

## 11. Casos Límite y Casos Estúpidos

### Casos Límite

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| DL-01 | Dispensación parcial: solo algunos items tienen stock | Se dispensan los disponibles. Los pendientes quedan en pending. La prescripción permanece 'active'. |
| DL-02 | Último lote disponible con quantity menor a la solicitada | Se dispensa solo la cantidad disponible. El item queda pendiente si quantity_dispensed < quantity_prescribed. |
| DL-03 | Prescripción con 5 items, 3 sin stock | Prepare retorna 2 items con FEFO, 3 con mensaje "sin stock". El auxiliar decide si dispensa los 2 o no dispensa nada. |
| DL-04 | Lote vence exactamente hoy | Se dispensa (expiration_date >= today()). FEFO lo incluye si es el más próximo. |
| DL-05 | Auditor consulta paciente | Se retornan datos enmascarados. Los campos first_name y last_name SÍ son visibles. |
| DL-06 | Dos usuarios intentan dispensar la misma prescripción simultáneamente | lockForUpdate sobre inventories. El primero tiene éxito. El segundo recibe error de item ya dispensado (DP-02). |
| DL-07 | Prescription expira durante la preparación | El prepare no valida fecha de expiración. Al confirmar dispense, se valida. |
| DL-08 | authorized_by null para item normal (no control especial) | Se acepta. authorized_by solo es obligatorio para control especial. |

### Casos Estúpidos

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| DE-01 | quantity a dispensar > available en el lote seleccionado | HTTP 422. Mensaje: "Cantidad solicitada excede el stock disponible del lote" |
| DE-02 | Dispensar quantity = 0 | CHECK constraint en dispense_items.quantity > 0. HTTP 422 |
| DE-03 | Modificar una prescripción ya dispensada (parcial o completa) | HTTP 422. Solo se pueden modificar prescripciones en status 'active' sin items dispensados |
| DE-04 | Crear prescripción con producto que no existe | HTTP 404. Validación de product_id existe |
| DE-05 | Doble clic en "Confirmar dispensación" | Botón deshabilitado. Segundo request recibe error de idempotencia o se crea dispensación duplicada |
| DE-06 | Auditor intenta crear paciente | HTTP 403 |
| DE-07 | auxiliary_farmacia intenta crear prescripción | HTTP 403. Solo medico y regente pueden crear prescripción |

---

## 12. Criterios de Aceptación

### Pacientes

### CA-01: Registrar paciente
**Dado** que no existe un paciente con documento 1098765432
**Cuando** el auxiliar_farmacia envía POST /api/patients con datos válidos
**Entonces** se crea el paciente y se retorna con HTTP 201 PASS / FAIL

### CA-02: Rechazar paciente duplicado
**Dado** que existe un paciente con documento 1098765432
**Cuando** se envía POST /api/patients con el mismo número de documento
**Entonces** HTTP 409 PASS / FAIL
**Y** mensaje: "Ya existe un paciente con ese número de documento" PASS / FAIL

### CA-03: Paciente para auditor — datos enmascarados
**Dado** que existe un paciente con documento 1098765432, teléfono 3001234567, email test@email.com
**Cuando** el auditor consulta GET /api/patients/{id}
**Entonces** document_number = "****5432" PASS / FAIL
**Y** phone = "***********" PASS / FAIL
**Y** email = "****@****.com" PASS / FAIL
**Y** first_name y last_name son visibles PASS / FAIL
**Y** masked = true PASS / FAIL

### Prescripciones

### CA-04: Crear prescripción
**Dado** que existe un paciente y dos productos con stock
**Cuando** el medico envía POST /api/prescriptions con patient_id, prescriber, items
**Entonces** se crea la prescripción con status='active' PASS / FAIL
**Y** prescription_number = PRE-YYYYMMDD-XXXX PASS / FAIL
**Y** se crean los prescription_items asociados PASS / FAIL

### CA-05: Rechazar prescripción vencida
**Dado** que existe una prescripción con expires_at = yesterday
**Cuando** se envía POST /api/dispenses para esa prescripción
**Entonces** HTTP 422 PASS / FAIL
**Y** mensaje: "La prescripción está vencida" PASS / FAIL

### CA-06: Rechazar prescripción cancelada
**Dado** que existe una prescripción con status='cancelled'
**Cuando** se intenta dispensar
**Entonces** HTTP 422 PASS / FAIL

### FEFO y Stock

### CA-07: FEFO — selecciona lote con vencimiento más próximo
**Dado** que existe un producto con tres inventories:
  - Lote A: quantity=50, expiration_date = today()+15
  - Lote B: quantity=30, expiration_date = today()+45
  - Lote C: quantity=20, expiration_date = today()+10
**Cuando** se consulta GET /api/dispenses/prepare/{prescription_id}
**Entonces** el sistema sugiere el Lote C primero (vencimiento más próximo) PASS / FAIL
**Y** luego Lote A PASS / FAIL
**Y** luego Lote B PASS / FAIL

### CA-08: Rechazar dispensación de lote vencido (RN-01)
**Dado** que el FEFO selecciona un inventory cuyo lot tiene expiration_date < today
**Cuando** se confirma la dispensación
**Entonces** HTTP 422 PASS / FAIL
**Y** mensaje: "No se puede dispensar un lote vencido" PASS / FAIL

### CA-09: Stock no baja de cero (RN-03)
**Dado** que existe un inventory con quantity=5 y se intenta dispensar quantity=10
**Cuando** KardexService intenta registrar DISPENSACION
**Entonces** InsufficientStockException PASS / FAIL
**Y** inventory.quantity permanece en 5 PASS / FAIL

### CA-10: Dispensación parcial — algunos items sin stock
**Dado** que una prescripción tiene 3 items: A (stock=10), B (stock=0), C (stock=5)
**Cuando** se confirman los items A y C para dispensación
**Entonces** se crean dispense_items para A y C PASS / FAIL
**Y** B se excluye con mensaje "sin stock" PASS / FAIL
**Y** prescription.status permanece 'active' PASS / FAIL
**Y** prescription_items de A y C actualizan quantity_dispensed PASS / FAIL

### Control Especial y Autorización

### CA-11: Control especial requiere doble autorización (RN-05)
**Dado** que existe una prescripción con un item de control especial (requires_authorization=true)
**Cuando** se envía POST /api/dispenses sin authorized_by
**Entonces** HTTP 422 PASS / FAIL
**Y** mensaje: "Este producto requiere autorización del regente de farmacia" PASS / FAIL

### CA-12: Autorizante no puede ser el mismo que dispensa (RN-05)
**Dado** que existe una prescripción con item de control especial
**Cuando** se envía POST /api/dispenses con dispensed_by=usuarioA y authorized_by=usuarioA
**Entonces** HTTP 422 PASS / FAIL
**Y** mensaje: "El autorizante no puede ser la misma persona que dispensa" PASS / FAIL

### CA-13: Item normal no requiere autorización
**Dado** que existe una prescripción donde ningún item tiene requires_authorization=true
**Cuando** se envía POST /api/dispenses sin authorized_by
**Entonces** HTTP 200 PASS / FAIL
**Y** authorized_by es null en el registro PASS / FAIL

### Idempotencia

### CA-14: No dispensar item ya completamente dispensado
**Dado** que existe un prescription_item con quantity_prescribed=30 y quantity_dispensed=30
**Cuando** se intenta agregar ese item a una nueva dispensación
**Entonces** HTTP 422 PASS / FAIL
**Y** mensaje: "Este item ya fue dispensado completamente" PASS / FAIL

### CA-15: Actualizar quantity_dispensed acumulativo
**Dado** que existe un prescription_item con quantity_prescribed=30, quantity_dispensed=10
**Cuando** se dispensan 15 unidades más
**Entonces** quantity_dispensed se actualiza a 25 PASS / FAIL
**Y** pending_quantity = 5 PASS / FAIL

### CA-16: Prescription pasa a dispensed cuando todos los items están completos
**Dado** que todos los items de una prescripción tienen quantity_dispensed >= quantity_prescribed
**Cuando** se completa la última dispensación
**Entonces** prescription.status → 'dispensed' PASS / FAIL

### Kardex

### CA-17: Dispensación registra movimiento DISPENSACION en Kardex
**Dado** que se confirma una dispensación de 20 unidades
**Cuando** la transacción se completa
**Entonces** se crea un registro en movements con type=DISPENSACION, quantity=20 PASS / FAIL
**Y** inventory.quantity se decrementa en 20 PASS / FAIL

### Concurrencia

### CA-18: lockForUpdate previene race condition en stock
**Dado** que dos solicitudes simultáneas intentan dispensar el mismo inventory con quantity=10
**Cuando** ambas llegan al mismo tiempo
**Entonces** la segunda solicitud recibe InsufficientStockException PASS / FAIL
**Y** el stock final es consistente PASS / FAIL

### Permisos

### CA-19: auxiliar_farmacia NO puede crear prescripción
**Dado** que el usuario tiene rol auxiliar_farmacia
**Cuando** envía POST /api/prescriptions
**Entonces** HTTP 403 PASS / FAIL

### CA-20: medico NO puede dispensar
**Dado** que el usuario tiene rol medico
**Cuando** envía POST /api/dispenses
**Entonces** HTTP 403 PASS / FAIL

### CA-21: Auditor ve pacientes enmascarados en lista
**Dado** que el usuario tiene rol auditor
**Cuando** consulta GET /api/patients
**Entonces** todos los pacientes tienen masked=true PASS / FAIL
**Y** document_number, phone y email están enmascarados PASS / FAIL

### Idempotencia (RN-09)

### CA-22: Reintento con la misma Idempotency-Key no duplica la salida de stock
**Dado** que existe una dispensación con idempotency_key = "abc-123" que descontó 20 unidades
**Cuando** el cliente reenvía POST /api/dispenses con el mismo header Idempotency-Key: "abc-123" y el mismo body
**Entonces** HTTP 200 con la dispensación original PASS / FAIL
**Y** NO se crea un registro nuevo en dispenses PASS / FAIL
**Y** NO se genera un segundo movimiento DISPENSACION en Kardex PASS / FAIL
**Y** inventory.quantity NO vuelve a decrementar PASS / FAIL

### CA-23: Request sin Idempotency-Key se rechaza
**Dado** que un cliente envía POST /api/dispenses sin el header Idempotency-Key
**Cuando** se procesa la solicitud
**Entonces** HTTP 422 con mensaje "El header Idempotency-Key es obligatorio" PASS / FAIL
**Y** no se ejecuta ninguna lógica de negocio PASS / FAIL

### Bitácora de accesos (RN-10)

### CA-24: Toda consulta a un paciente queda registrada en la bitácora
**Dado** que el usuario auxiliar_farmacia consulta GET /api/patients/{id}
**Cuando** se completa la respuesta
**Entonces** se inserta un registro en patient_access_logs con accessed_by=auxiliar, access_type=VIEW, patient_id=ese paciente PASS / FAIL

### CA-25: La bitácora no contiene datos personales
**Dado** que se registra un acceso en patient_access_logs
**Cuando** se inspecciona el registro almacenado
**Entonces** no contiene document_number, phone ni email PASS / FAIL
**Y** solo registra quién, cuándo, a qué paciente y desde qué IP PASS / FAIL

### CA-26: Los logs de aplicación redactan datos de paciente
**Dado** que una operación registra un log en el canal de aplicación
**Cuando** el payload incluye claves con datos de paciente
**Entonces** el middleware de logging las redacta antes de escribir PASS / FAIL
**Y** ningún documento_number, phone o email aparece en el archivo de log PASS / FAIL

### FEFO multi-lote (RN-02)

### CA-27: Una dispensación puede tomar cantidades de varios lotes
**Dado** que un producto tiene 3 existencias en la misma bodega: Lote A (vence en 10 días, qty=5), Lote B (vence en 20 días, qty=8), Lote C (vence en 40 días, qty=20)
**Y** la prescripción pide 15 unidades de ese producto
**Cuando** se confirma la dispensación
**Entonces** se dispensan 5 del Lote A, 8 del Lote B y 2 del Lote C PASS / FAIL
**Y** se crean 3 registros en dispense_items PASS / FAIL
**Y** se crean 3 movimientos DISPENSACION distintos en Kardex PASS / FAIL
**Y** las existencias quedan A=0, B=0, C=18 PASS / FAIL

### Stock mínimo por bodega (RN-11)

### CA-28: La alerta de stock mínimo es por bodega
**Dado** que el producto P tiene min_stock=20 en Farmacia Central y min_stock=5 en Farmacia de Urgencias
**Y** en Central hay 18 unidades y en Urgencias hay 10
**Cuando** se consulta GET /api/inventories
**Entonces** la existencia de Central aparece en alerts.low_stock PASS / FAIL
**Y** la existencia de Urgencias NO aparece en alerts.low_stock PASS / FAIL

---

## 13. Dependencias con otras HU

```
HU-08 (Kardex + Inventories)
├── Inventories tabla existe
├── KardexService::recordMovement(DISPENSACION) disponible
└── Movements tabla disponible

HU-09 (Transfers)
└── Traspasos entre bodegas repone stock de inventarios

HU-10 (Dispensación)
├── Patient, Prescription, PrescriptionItem, Dispense, DispenseItem
├── DispenseService con FEFO
├── PatientService
├── PrescriptionService
└── PatientResource con enmascaramiento

HU-11 (Seeders de pacientes y prescripciones)
└── 3 pacientes, 3 prescripciones con items
```
