# API de Inventario Farmacéutico — Documentación para Frontend

> **Stack:** Laravel 9 + Sanctum (token-based auth) + SQL Server Express
> **Base URL:** `http://localhost:8000/api`
> **Versión:** 1.0 — Julio 2026

---

## 1. Autenticación

### 1.1 Flujo

```
1. POST /api/login  →  recibe { token }
2. Todas las requests subsecuentes incluyen:
   - Authorization: Bearer {token}
   - Accept: application/json
3. POST /api/logout → invalida el token
```

### 1.2 Login

```
POST /api/login
Content-Type: application/json
```

**Body:**
```json
{
  "name": "admin",
  "password": "password"
}
```

**NOTA:** Se autentica por `name`, NO por `email`.

**Response 200:**
```json
{
  "token": "1|abc123def456ghi789..."
}
```

**Response 422:**
```json
{
  "message": "The name field is required.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

### 1.3 Logout

```
POST /api/logout
Authorization: Bearer {token}
Accept: application/json
```

**Response 200:**
```json
{
  "message": "Sesión cerrada correctamente"
}
```

### 1.4 Usuario autenticado

```
GET /api/user
Authorization: Bearer {token}
Accept: application/json
```

**Response 200:**
```json
{
  "id": 1,
  "name": "admin",
  "email": "admin@example.com",
  "created_at": "...",
  "updated_at": "..."
}
```

---

## 2. Conceptos Generales

### 2.1 Headers obligatorios

**TODAS las requests protegidas** deben incluir:

```
Authorization: Bearer {token}
Accept: application/json
```

Sin `Accept: application/json`, Sanctum redirige a `/login` en vez de devolver 401 — el frontend recibe HTML y falla.

### 2.2 Formato de errores

| Tipo | HTTP | Formato | Ejemplo |
|---|---|---|---|
| Validación (FormRequest) | **422** | `{"message":"...","errors":{"campo":["error1"]}}` | Campo requerido, UUID inválido, valor fuera de rango |
| Regla de negocio (Service) | **409** | `{"message":"El nombre del sensor ya existe."}` | Duplicado, FK no existe, restricción de stock |
| No autenticado | **401** | Redirige a `/login` o JSON (depende de `Accept`) | Token faltante, token inválido |
| No encontrado | **404** | `{"message":"..."}` o body vacío | Route Model Binding no encuentra el registro |
| Error interno | **500** | `{"message":"..."}` con stack trace (solo en dev) | Bug, query malformada |

**Regla para el frontend:**
- Si el body tiene `errors.campo` → mostrar errores por campo en el formulario
- Si el body solo tiene `message` → mostrar toast/notificación

### 2.3 Paginación

Todos los listados retornan paginación con el formato:

```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "total": 45,
    "per_page": 15,
    "last_page": 3
  }
}
```

**Propiedades del `meta`:**
| Campo | Tipo | Descripción |
|---|---|---|
| `current_page` | int | Página actual |
| `total` | int | Total de registros (todas las páginas) |
| `per_page` | int | Registros por página |
| `last_page` | int | Última página disponible |

### 2.4 Formato de respuestas CRUD

**Creación (POST):**
```json
// 201 Created
{
  "message": "Sensor creado con éxito",
  "data": { ... }
}
```

**Actualización (PUT):**
```json
// 200 OK
{
  "message": "Sensor actualizado con éxito",
  "data": { ... }
}
```

**Eliminación (DELETE):**
```json
// 200 OK
{
  "message": "Sensor eliminado correctamente"
}
```

**Listado (GET):**
```json
// 200 OK
{
  "data": [ ... ],
  "meta": { ... }
}
```

**Toggle Status (PATCH):**
```json
// 200 OK
{
  "message": "Sensor activado con éxito",
  "data": { ... }
}
```

---

## 3. Entidades — Endpoints CRUD

### 3.1 Bodegas

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/bodegas` | Listar todas |
| `POST` | `/api/bodegas` | Crear nueva |
| `GET` | `/api/bodegas/{id}` | Ver una |
| `PUT` | `/api/bodegas/{id}` | Actualizar |
| `DELETE` | `/api/bodegas/{id}` | Soft delete |
| `PATCH` | `/api/bodegas/{id}/toggle-status` | Activar/Inactivar |

**Body de creación (POST):**
```json
{
  "name": "Bodega Principal Norte",
  "description": "Almacenamiento de medicamentos generales"
}
```

**Validaciones:**
| Campo | Reglas |
|---|---|
| `name` | requerido, string, max 60, regex (letras/acentos/ñ/dígitos/espacios/giones/paréntesis/°), único |
| `description` | nullable, string, max 300 |

**Response (POST 201):**
```json
{
  "message": "Bodega creada con éxito",
  "data": {
    "id": "uuid",
    "name": "Bodega Principal Norte",
    "description": "Almacenamiento de medicamentos generales",
    "status": true,
    "created_by": "1",
    "updated_by": null,
    "created_at": "2026-07-04T18:39:51+00:00",
    "updated_at": "2026-07-04T18:39:51+00:00",
    "deleted_at": null
  }
}
```

### 3.2 Grupos

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/groups` | Listar todos |
| `POST` | `/api/groups` | Crear nuevo |
| `GET` | `/api/groups/{id}` | Ver uno |
| `PUT` | `/api/groups/{id}` | Actualizar |
| `DELETE` | `/api/groups/{id}` | Soft delete |
| `PATCH` | `/api/groups/{id}/toggle-status` | Activar/Inactivar |
| `GET` | `/api/groups/select` | Lista optimizada para selects (id, name, solo activos) |

**Body de creación:**
```json
{
  "name": "Analgésicos",
  "description": "Medicamentos destinados a aliviar o reducir el dolor"
}
```

### 3.3 Productos

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/products` | Listar con paginación (default 15) |
| `POST` | `/api/products` | Crear nuevo |
| `GET` | `/api/products/{id}` | Ver uno (incluye unit, group) |
| `PUT` | `/api/products/{id}` | Actualizar |
| `DELETE` | `/api/products/{id}` | Soft delete |
| `PATCH` | `/api/products/{id}/toggle-status` | Activar/Inactivar |
| `GET` | `/api/products/form-data` | Datos para el formulario (groups + units activos) |

**Body de creación:**
```json
{
  "name": "Acetaminofén 500mg – Tableta",
  "cum": "19927641-1",
  "barcode": "7701234567890",
  "invima_registration": "INVIMA 2020M-0019854",
  "group_id": "uuid-del-grupo",
  "unit_id": "uuid-de-la-unidad",
  "description": "Analgésico y antipirético de uso común."
}
```

**Validaciones:**
| Campo | Reglas |
|---|---|
| `name` | requerido, max 100 |
| `cum` | requerido, max 30, único |
| `barcode` | nullable, max 60 |
| `invima_registration` | requerido, max 60, único |
| `group_id` | requerido, uuid, exists |
| `unit_id` | requerido, uuid, exists |
| `description` | nullable, string |

**Response (GET listado):**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Acetaminofén 500mg – Tableta",
      "cum": "19927641-1",
      "barcode": "7701234567890",
      "invima_registration": "INVIMA 2020M-0019854",
      "unit": { "id": "uuid", "name": "Tableta", "abbreviation": "TAB" },
      "group": { "id": "uuid", "name": "Analgésicos" },
      "description": "Analgésico y antipirético de uso común.",
      "status": true,
      "created_by": "1",
      "updated_by": null,
      "created_at": "2026-07-03T16:25:07+00:00",
      "updated_at": "2026-07-03T17:04:45+00:00"
    }
  ],
  "meta": { ... }
}
```

### 3.4 Lotes

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/lots` | Listar con paginación |
| `POST` | `/api/lots` | Crear nuevo |
| `GET` | `/api/lots/{id}` | Ver uno |
| `PUT` | `/api/lots/{id}` | Actualizar |
| `DELETE` | `/api/lots/{id}` | Soft delete |
| `PATCH` | `/api/lots/{id}/toggle-status` | Activar/Inactivar |
| `GET` | `/api/lots/form-data` | Datos para el formulario (products + bodegas activos) |

**Body de creación:**
```json
{
  "name": "Lote 001",
  "product_id": "uuid-del-producto",
  "warehouse_id": "uuid-de-la-bodega",
  "stock": 100,
  "expiration_date": "2026-08-15"
}
```

**Validaciones:**
| Campo | Reglas |
|---|---|
| `name` | requerido, max 120, regex, único |
| `product_id` | requerido, uuid, exists (activo, no soft-deleted) |
| `warehouse_id` | requerido, uuid, exists (activo) |
| `stock` | requerido, integer, min 0 |
| `expiration_date` | requerido, date |
| `description` | nullable, string, max 300 |

**Response (GET listado):**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Lote 001",
      "product_id": "uuid",
      "warehouse_id": "uuid",
      "stock": 100,
      "expiration_date": "2026-08-15",
      "status": true,
      "product": { "id": "uuid", "name": "Acetaminofén 1000mg – Tableta" },
      "warehouse": { "id": "uuid", "name": "Bodega Principal Norte" },
      "created_by": "1",
      "updated_by": null,
      "created_at": "2026-07-03T20:02:18+00:00",
      "updated_at": "2026-07-03T20:02:18+00:00"
    }
  ],
  "meta": { ... }
}
```

### 3.5 Sensores

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/sensors` | Listar todos |
| `POST` | `/api/sensors` | Crear nuevo |
| `GET` | `/api/sensors/{id}` | Ver uno |
| `PUT` | `/api/sensors/{id}` | Actualizar |
| `DELETE` | `/api/sensors/{id}` | Soft delete (cascade: lecturas + alertas) |
| `PATCH` | `/api/sensors/{id}/toggle-status` | Activar/Inactivar |
| `GET` | `/api/sensors/form-data` | Datos para el formulario (bodegas activas) |

**Body de creación:**
```json
{
  "name": "Sensor de Temperatura modelo X1",
  "description": "Sensor de temperatura marca Testo, rango -20°C a 50°C",
  "warehouse_id": "uuid-de-la-bodega"
}
```

**NOTA:** Relación 1:N — cada sensor pertenece a UNA bodega. El frontend debe enviar `warehouse_id` (singular), no un array.

**Response (GET listado):**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Sensor de Temperatura modelo X1",
      "description": "...",
      "status": true,
      "warehouse": {
        "id": "uuid",
        "name": "Bodega Principal Norte"
      },
      "created_by": "1",
      "updated_by": null,
      "created_at": "2026-07-04T18:45:37+00:00",
      "updated_at": "2026-07-04T18:45:37+00:00",
      "deleted_at": null
    }
  ]
}
```

---

## 4. Lecturas y Alertas (Monitoreo de Sensores)

### 4.1 Endpoint de Lecturas

```
GET /api/bodegas/{bodegaId}/readings
```

**Query params:**
| Param | Tipo | Default | Descripción |
|---|---|---|---|
| `sensor_id` | UUID | — | Filtra lecturas de un sensor específico |
| `date_from` | ISO 8601 | — | Fecha inicial del rango |
| `date_to` | ISO 8601 | — | Fecha final del rango |
| `limit` | int | 30 | Resultados por página (max 1000) |
| `alert_only` | 0/1 | 0 | Solo lecturas con alerta |
| `page` | int | 1 | Número de página |

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "sensor_id": "uuid",
      "temperature": 27.5,
      "humidity": 65.0,
      "recorded_at": "2026-07-04T19:07:00+00:00",
      "sensor": {
        "id": "uuid",
        "name": "Sensor Central",
        "warehouse": { "id": "uuid", "name": "Bodega Principal Norte" }
      },
      "alert": {
        "id": "uuid",
        "type": "temperature",
        "message": "Temperatura 27.5°C excede el umbral de 25°C",
        "created_at": "2026-07-04T19:07:00+00:00"
      }
    }
  ],
  "meta": { ... }
}
```

**NOTA:** `alert` es `null` si la lectura no disparó alerta. El frontend filtra con `r.alert !== null`.

**Response 404** (bodega no existe):
```json
{ "message": "Bodega no encontrada" }
```

**Response 422** (date_from > date_to):
```json
{ "message": "El rango de fechas no es válido" }
```

### 4.2 Endpoint de Alertas (para polling)

```
GET /api/bodegas/{bodegaId}/alerts
```

**Query params:**
| Param | Tipo | Descripción |
|---|---|---|
| `sensor_id` | UUID | Filtra alertas de un sensor |
| `type` | string | `temperature`, `humidity`, `both` |
| `created_after` | ISO 8601 | Solo alertas creadas después de este timestamp (para polling) |
| `date_from` | ISO 8601 | Rango de fechas |
| `date_to` | ISO 8601 | Rango de fechas |
| `limit` | int | Default 30, max 1000 |
| `page` | int | Default 1 |

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "type": "temperature",
      "message": "Temperatura 29.08°C excede el umbral de 25°C",
      "created_at": "2026-07-04T19:20:00+00:00",
      "reading": {
        "id": "uuid",
        "temperature": 29.08,
        "humidity": 37.71,
        "recorded_at": "2026-07-04T19:20:00+00:00",
        "sensor": {
          "id": "uuid",
          "name": "Sensor de Temperatura modelo X1",
          "warehouse": { "id": "uuid", "name": "Bodega Principal Norte" }
        }
      }
    }
  ],
  "meta": { ... }
}
```

**Ejemplo de polling (cada 10s):**
```typescript
let lastCheck = new Date().toISOString();
setInterval(async () => {
  const resp = await fetch(`/api/bodegas/${bodegaId}/alerts?created_after=${encodeURIComponent(lastCheck)}&limit=20`);
  const json = await resp.json();
  json.data.forEach(alert => mostrarNotificacion(alert));
  lastCheck = new Date().toISOString();
}, 10000);
```

---

## 5. Reporte de Inventario (HU-02)

```
GET /api/reportes/inventario
```

**Query params:**
| Param | Tipo | Ejemplo | Descripción |
|---|---|---|---|
| `cum` | texto | `?cum=19927641` | Búsqueda parcial ILIKE |
| `name` | texto | `?name=Acetaminofén` | Búsqueda parcial ILIKE |
| `bodega_id` | UUID | `?bodega_id=...` | Filtro exacto |
| `group_id` | UUID | `?group_id=...` | Filtro exacto |
| `lot_code` | texto | `?lot_code=Lote 001` | Filtro exacto |
| `estado` | enum | `?estado=por_vencer` | `con_stock`, `por_vencer`, `vencido`, `todos` |
| `date_from` | fecha | `?date_from=2026-07-01` | Rango de vencimiento desde |
| `date_to` | fecha | `?date_to=2026-12-31` | Rango de vencimiento hasta |
| `sort_by` | columna | `?sort_by=lots.stock` | Columna de orden (ver whitelist abajo) |
| `sort_dir` | asc/desc | `?sort_dir=desc` | Dirección de orden |
| `per_page` | int | `?per_page=30` | Default 30, max 100 |
| `page` | int | `?page=2` | Número de página |

**Columnas ordenables (`sort_by`):**
`products.cum`, `products.name`, `products.barcode`, `products.invima_registration`, `groups.name`, `units.name`, `lots.name`, `bodegas.name`, `lots.stock`, `lots.expiration_date`

**Response 200:**
```json
{
  "data": [
    {
      "cum": "19927641-2",
      "product_name": "Acetaminofén 1000mg – Tableta",
      "group_name": "Analgésicos",
      "barcode": "7701234567891",
      "invima": "INVIMA 2020M-0019855",
      "stock": 100,
      "unit": "Tableta (TAB)",
      "lot_code": "Lote 001",
      "bodega_name": "Bodega Principal Norte",
      "expiration_date": "2026-08-15",
      "alerta_exp": "Por vencer (42 días)",
      "alerta_color": "amarillo"
    }
  ],
  "meta": { ... }
}
```

**Indicadores de alerta de expiración:**
| `alerta_color` | Regla | `alerta_exp` |
|---|---|---|
| `"verde"` | `expiration_date > today + 30 días` | `"Vigente"` |
| `"amarillo"` | `expiration_date >= today AND <= today + 30 días` | `"Por vencer (N días)"` |
| `"rojo"` | `expiration_date < today` | `"Vencido (N días)"` |
| `"gris"` | `expiration_date IS NULL` | `"Sin fecha"` |

**NOTA:** El frontend NO envía `alerta_color` ni `alerta_exp` — el backend los computa en tiempo real. El frontend solo los muestra.

---

## 6. Selects — Datos para Formularios

| Endpoint | Retorna | Filtro |
|---|---|---|
| `GET /api/groups/select` | `[{ id, name }]` | Solo activos |
| `GET /api/units/select` | `[{ id, name, abbreviation }]` | Todos (sin filtro) |
| `GET /api/products/form-data` | `{ groups: [...], units: [...] }` | Grupos activos, todas las unidades |
| `GET /api/lots/form-data` | `{ products: [...], bodegas: [...] }` | Solo activos |
| `GET /api/sensors/form-data` | `{ bodegas: [...] }` | Solo bodegas activas |

---

## 7. Convenciones del Proyecto

### 7.1 IDs
- UUID v4 para todas las entidades (Bodega, Producto, Lote, Sensor, Reading, Alert, Group)
- `users.id` es BIGINT autoincremental (NO UUID)
- `created_by` / `updated_by` son BIGINT (FK a `users.id`)
- `product_id`, `warehouse_id`, `group_id`, `unit_id` son UUID (FK a sus respectivas entidades)

### 7.2 Fechas
- Todas las fechas en formato **ISO 8601 con zona UTC**: `"2026-07-04T18:45:37+00:00"`
- `expiration_date` y `recorded_at` son solo DATE (sin hora)
- El frontend convierte a hora local con `new Date(iso).toLocaleString()`

### 7.3 Status codes por operación
| Operación | Status esperado |
|---|---|
| Crear (POST) | 201 |
| Listar (GET) | 200 |
| Ver (GET) | 200 |
| Actualizar (PUT) | 200 |
| Eliminar (DELETE) | 200 |
| Toggle (PATCH) | 200 |
| Error validación | 422 |
| Error negocio | 409 |
| No autenticado | 401 |
| No encontrado | 404 |

### 7.4 Soft Delete
- Todas las entidades CRUD usan soft delete (columna `deleted_at`)
- Al hacer DELETE, el registro no se borra físicamente
- Los listados NO incluyen registros soft-deleted (Eloquent los excluye automáticamente)
- El toggle-status en un registro soft-deleted devuelve 404 (Route Model Binding)

### 7.5 Convención de nombres en el frontend
- Todas las rutas del backend están en español: `/api/bodegas`, `/api/grupos`, `/api/reportes`
- Las propiedades en el JSON están en inglés: `warehouse_id`, `created_at`, `expiration_date`
- El frontend puede usar camelCase internamente con un mapper

### 7.6 CORS
- Laravel 9 incluye CORS por defecto via `HandleCors` middleware
- Permitido: `http://localhost:3000` (o el puerto que uses para el frontend)

---

## 8. Flujo de Pantallas Sugerido

```
1. Login
   └─ POST /api/login → guardar token en localStorage/sessionStorage

2. Dashboard / Home

3. Grupos (CRUD simple — primer test del patrón)
   ├─ GET /api/groups → tabla
   ├─ POST /api/groups → formulario crear
   ├─ GET /api/groups/select → dropdown en otros formularios
   └─ PATCH /api/groups/{id}/toggle-status → switch activar/inactivar

4. Bodegas (CRUD simple)

5. Productos (CRUD con relaciones)
   ├─ GET /api/products/form-data → carga groups + units para los selects
   └─ POST /api/products → formulario con selects

6. Lotes (CRUD con relaciones)
   ├─ GET /api/lots/form-data → carga products + bodegas para los selects
   └─ POST /api/lots → formulario con selects

7. Sensores (CRUD + monitoreo)
   ├─ GET /api/sensors/form-data → carga bodegas para el select
   ├─ GET /api/bodegas/{id}/readings → tabla de lecturas con filtros
   └─ GET /api/bodegas/{id}/alerts?created_after=... → polling de alertas

8. Reporte de Inventario (búsqueda + filtros)
   ├─ GET /api/reportes/inventario?filtros... → tabla con paginación
   └─ Exportar Excel: frontend usa los datos de la API para generar XLSX
```

---

## 9. Ejemplos curl para probar

```bash
# Login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name":"admin","password":"password"}'

# Listar bodegas (con token)
curl http://localhost:8000/api/bodegas \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# Crear sensor
curl -X POST http://localhost:8000/api/sensors \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name":"Sensor Central","warehouse_id":"D092C02E-..."}'

# Reporte con filtros
curl "http://localhost:8000/api/reportes/inventario?bodega_id=...&estado=por_vencer&sort_by=products.name&sort_dir=asc" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# Alertas (polling)
curl "http://localhost:8000/api/bodegas/{id}/alerts?created_after=2026-07-04T19:00:00Z" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```
