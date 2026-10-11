# HU-05 – Gestión de Lotes (Inventario)

> **Changelog HU-05:**
>
> - **v2 (post HU-08):** el lote deja de tener bodega y stock propios.
>   - La columna `warehouse_id` se elimina de `lots`. Un lote ahora puede
>     estar en **N bodegas** vía la tabla intermedia `inventories`
>     (RN-01 de HU-08).
>   - La columna `stock` se elimina de `lots`. El stock es **derivado**:
>     se calcula como la suma de `inventories.quantity` para ese lote.
>   - El form de crear/editar lote ya **no pide bodega ni stock**: el
>     stock se asigna via Kardex (HU-08) registrando un movimiento de
>     tipo `ENTRY` desde la pantalla de Kardex.
>   - La columna "Bodega" en la tabla se reemplaza por **"Ubicación"**:
>     lista de bodegas donde el lote tiene existencia, con la cantidad
>     en cada una (badges).
>   - Se eliminan las validaciones "no eliminar / no inactivar si
>     `stock` > 0" — el stock no vive más en el lote.

## 1. Título y Descripción

**Título:** HU-05 – Gestión de Lotes (Inventario)

**Descripción:** Permitir al usuario gestionar lotes como la entidad de trazabilidad de inventario. Un lote pertenece a un **producto**, tiene código único y fecha de vencimiento. El stock del lote es **derivado** (suma de existencias en todas las bodegas) y se gestiona via Kardex (HU-08), no en este formulario. Un lote puede estar en **N bodegas** simultáneamente, según las existencias que se le hayan registrado.

La pantalla contiene un formulario en la parte superior y una tabla de lotes en la parte inferior, siguiendo el mismo patrón CRUD de HU-03.

## 2. Modelo de Datos

Tabla: `lots`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por el sistema (lado del servidor/BD) |
| name | varchar(120) | UNIQUE, NOT NULL, unicidad insensible a mayúsculas/minúsculas. Representa el código del lote (se muestra como "Código" en la interfaz) |
| product_id | uuid | FK → products (id), NOT NULL, onDelete RESTRICT |
| expiration_date | date | NOT NULL, debe ser una fecha futura |
| status | boolean | NOT NULL, valor por defecto true (true=Activo, false=Inactivo) |
| created_by | uuid | FK → users (id), NOT NULL, onDelete RESTRICT |
| updated_by | uuid | FK → users (id), permite nulo, onDelete SET NULL |
| created_at | timestamp | NOT NULL, asignado automáticamente por Eloquent |
| updated_at | timestamp | permite nulo, asignado automáticamente por Eloquent |
| deleted_at | timestamp | permite nulo (SoftDeletes) |

**Cambios respecto a la versión anterior:**

- ❌ `warehouse_id` — **eliminado**. Un lote no pertenece a una bodega.
  Vive en la terna `(warehouse, product, lot)` de la tabla `inventories`.
- ❌ `stock` — **eliminado**. El stock es derivado: `stock = SUM(inventories.quantity WHERE lot_id = X)`.

Todos los nombres de columna están en inglés (compatibles con Eloquent: `created_by` para belongsTo, `created_at`/`updated_at` para timestamps, `deleted_at` para SoftDeletes).

| FK | onDelete |
|---|---|
| `product_id` → products (id) | RESTRICT |
| `created_by` → users (id) | RESTRICT |
| `updated_by` → users (id) | SET NULL |

### Campos derivados (no se persisten en `lots`)

| Campo derivado | Origen | Descripción |
|---|---|---|
| `stock` | `SUM(inventories.quantity WHERE lot_id = X)` | Suma de existencias del lote en todas las bodegas. Puede ser 0. |
| `warehouses` | `inventories` join `bodegas` | Array de `{id, name, quantity}` con las bodegas donde el lote tiene existencia y la cantidad en cada una. |

Ambos campos se calculan en el backend (HU-08 §3) y se exponen en el `LotResource` que se serializa al frontend.

## 3. Bloqueos y Dependencias

- Debe existir la tabla `users` (estructura estándar de Laravel 9).
- Debe existir la tabla `products` (HU-01 – Productos) — el campo `product_id` es FK hacia ella.
- Debe existir la tabla `bodegas` (HU-03 – Bodegas) — se usa como origen de datos para el campo derivado `warehouses`, pero **no** como FK de `lots`.
- Debe existir la tabla `inventories` (HU-08 – Kardex) — fuente de los campos derivados `stock` y `warehouses`.
- Debe estar definido el diseño UI/UX del formulario (campos: `name`, `product_id`, `expiration_date`, `description`).
- Esta HU es un prerrequisito para HU-08 (Kardex), ya que un movimiento de Kardex requiere un `lot_id` válido.

## 4. Variables y Configuración

| Variable | Tipo | Entornos | Valor por defecto | Descripción |
|---|---|---|---|---|
| LOT_CODE_MAX_LENGTH | integer | todos | 120 | Longitud máxima permitida para el campo `name`. |
| EXPIRATION_FUTURE_DAYS | integer | todos | 1 | Diferencia mínima de días entre `expiration_date` y la fecha actual (debe ser > hoy). |
| DERIVED_STATE_FILTER | string | todos | "Todos" | Filtro derivado para listados: "Todos", "Con stock", "Por vencer (30 días)", "Vencido". No se almacena en BD. |
| LOTS_TABLE_PAGE_SIZE | integer | todos | 10 | Cantidad de filas visibles por página en la tabla del frontend (paginación del lado del cliente). |

> **Eliminado:** `STOCK_MIN_VALUE` ya no aplica (el stock no es input de este formulario).

### Estados Derivados de Lote

| Estado | Regla de negocio | Uso |
|---|---|---|
| Con stock | `stock > 0` (sin importar `expiration_date`) | Filtro en la tabla |
| Sin stock | `stock = 0` (sin importar `expiration_date`) | Filtro en la tabla |
| Por vencer (30 días) | `stock > 0` AND `expiration_date` BETWEEN today AND today + 30 (inclusive ambos extremos) | Filtro en la tabla |
| Vencido | `stock > 0` AND `expiration_date < today` (estricto, no incluye today) | Filtro en la tabla |
| Todos | Sin restricciones de stock ni fecha | Filtro en la tabla |

Estos estados son COMPUTADOS en tiempo de consulta por el backend. No son columnas en la tabla `lots`. Las comparaciones de fecha usan solo la parte DATE (sin hora).

- Sin restricciones de rol o permisos — todos los usuarios pueden realizar todas las acciones.
- Sin feature toggles ni comportamiento específico por tenant.
- Sin comportamiento específico por entorno; los seeders pueden ejecutarse en todos los entornos.
- Lotes ilimitados (en el futuro una tabla de configuración global podría agregar límites, fuera de alcance por ahora).
- El backend retorna todos los lotes activos (se esperan pocos registros); el frontend maneja filtrado, búsqueda y paginación del lado del cliente.

## 5. Suposiciones

1. La tabla `users` existe con la estructura estándar de Laravel 9 (`id`, `name`, `email`, `password`, `timestamps`).
2. La tabla `products` (HU-01) existe y contiene al menos un producto antes de crear un lote.
3. La tabla `inventories` (HU-08) existe. La existencia de un lote en una bodega se modela con un registro en `inventories` (RN-01: terna `warehouse × product × lot`).
4. El diseño UI del formulario de lote incluye exactamente los campos: `name` (texto), `product_id` (select de productos), `expiration_date` (selector de fecha) y `description` (textarea opcional). **No** pide bodega ni stock.
5. Los seeders pueden ejecutarse en cualquier entorno (dev, staging, production).
6. No existen restricciones de infraestructura por ahora; si se requiere, limitar a 100 lotes mediante configuración global (fuera de alcance).
7. `created_at` se asigna automáticamente por Eloquent al crear el registro.
8. El UUID `id` es generado automáticamente por el sistema (lado del servidor/BD), no se proporciona externamente.
9. La unicidad de `name` se valida tanto en el frontend (antes del envío) como en el backend (antes de la persistencia), y es insensible a mayúsculas/minúsculas.
10. `created_by` se obtiene del usuario de la sesión autenticada y no puede elegirse manualmente en el formulario.
11. La `expiration_date` debe ser una fecha válida posterior a la fecha actual; las fechas pasadas o iguales a hoy se rechazan.
12. El stock del lote es **derivado**: se calcula sumando `quantity` en `inventories` para ese `lot_id`. Para asignar stock a un lote recién creado, el regente debe ir a la pantalla de Kardex (HU-08) y registrar un movimiento de tipo `ENTRY` con la bodega y la cantidad deseadas.
13. El select de producto muestra solo productos no eliminados (deleted_at IS NULL).

## 6. Fuera de Alcance (explícitamente excluido)

- Asignación de lotes a productos desde la pantalla de productos (pertenece a HU-01 Productos).
- Carga o ajuste de stock del lote desde esta pantalla (pertenece a HU-08 Kardex; se hace registrando un movimiento de tipo `ENTRY`).
- Dashboard/reportes de stock por lote (pertenece a HU-02 Búsqueda de Productos).
- Descuento automático de stock (pertenece a futuras HU de ventas/despacho).
- Alertas por vencimiento (pertenecen a HU-04 o una HU futura).
- Operaciones masivas (importación/exportación en lote).
- Notificaciones por correo electrónico — solo confirmación visual en el frontend.
- Migración de datos legados.
- Tabla de configuración global para límites del sistema (HU futura, no esta).
- Gestión de roles y permisos.
- Filtrado y paginación del lado del backend (solo frontend para esta HU).

## 7. Camino Feliz Principal (paso a paso)

### Listar y Crear
1. El usuario navega a la pantalla "Lotes".
2. El sistema muestra un formulario de registro en la parte superior con los campos: `name` (entrada de texto, máximo 120 caracteres), `product_id` (select de productos activos), `expiration_date` (selector de fecha) y `description` (textarea opcional). **No** hay campo de bodega ni de stock en este formulario.
3. Debajo del formulario, una tabla lista todos los lotes existentes con las columnas: Código, Producto, **Ubicación** (lista de bodegas donde el lote tiene existencia, con la cantidad en cada una), Stock (suma total), Vencimiento, Estado, Acciones. Cada fila tiene botones de acción: Editar, Switch Activar/Inactivar, Eliminar.
4. El usuario completa el formulario y hace clic en "Guardar".
5. El frontend valida: `name` es obligatorio, no vacío después de trim, máximo 120 caracteres (entrada bloqueada a 120); `product_id` es obligatorio; `expiration_date` es obligatoria y posterior a la fecha actual.
6. El backend valida: unicidad de `name` (insensible a mayúsculas/minúsculas), elimina espacios al inicio y al final, existencia de `product_id`, `expiration_date` futura. Si es válido, crea el registro con `created_by` de la sesión, `created_at` = hora del servidor, `status` = true. **El lote recién creado tiene `stock = 0`** hasta que se le registren existencias via Kardex.
7. El sistema retorna respuesta de éxito. La tabla se refresca mostrando el nuevo registro. El formulario se reinicia.
8. Si el usuario quiere cargar stock, navega a la pantalla de Kardex y registra un movimiento `ENTRY` con la bodega y la cantidad deseadas. El lote aparece ahora en la lista de Ubicación de ese lote y en la columna Stock con la cantidad sumada.

### Editar
1. El usuario hace clic en "Editar" en una fila de lote.
2. El formulario superior se precarga con los valores actuales: `name`, `product_id`, `expiration_date`, `description` y `status`. **No** se precargan bodega ni stock.
3. El usuario modifica los campos y hace clic en "Guardar".
4. Aplican las mismas reglas de validación. El backend actualiza `updated_by` y `updated_at`. Los campos derivados `stock` y `warehouses` no se ven afectados (siguen derivándose de `inventories`).
5. La tabla se refresca mostrando el registro actualizado.

### Editar sin cambios
1. El usuario hace clic en "Editar", no realiza cambios y hace clic en "Guardar".
2. El sistema actualiza `updated_by` y `updated_at` (no hay bloqueo de "sin cambios detectados").

### Activar/Inactivar
1. El usuario alterna el switch en una fila de lote.
2. El sistema alterna y guarda el `status` **sin chequear stock** (un lote inactivo puede tener existencias; el stock se gestiona por separado). La tabla se refresca.

### Eliminar (soft delete)
1. El usuario hace clic en "Eliminar" en una fila de lote.
2. El sistema muestra un diálogo de confirmación.
3. Si confirma → soft delete (`deleted_at` poblado, `status` establecido a inactivo). El registro desaparece de la tabla. **No se valida el stock**: la limpieza de existencias (si corresponde) se hace via Kardex por separado, no al eliminar el lote.

### Cargar stock en un lote
1. El usuario crea un lote (este formulario) con `stock = 0`.
2. El usuario navega a la pantalla de Kardex.
3. Hace clic en "Registrar movimiento", elige tipo `ENTRY`, selecciona la bodega, el lote recién creado y la cantidad.
4. El sistema crea un movimiento de Kardex y, con él, una fila en `inventories` para ese `(bodega, producto, lote)` con la cantidad indicada.
5. Al volver a la pantalla de Lotes, el lote muestra la bodega en la columna Ubicación y la cantidad sumada en la columna Stock.

### Estado vacío
- Cuando no existen lotes, la tabla muestra: "No hay lotes registrados".

### Estado de carga
- Mientras cualquier operación esté en progreso, mostrar un spinner de carga y deshabilitar el botón de envío para prevenir envíos duplicados.

## 8. Caminos Tristes (errores esperados)

| # | Escenario | Comportamiento del Sistema |
|---|---|---|
| SP-01 | El usuario envía con `name` vacío o solo espacios | El frontend resalta el campo como obligatorio, muestra el mensaje: "El campo código es obligatorio". El backend también valida. |
| SP-02 | `name` excede 120 caracteres | La entrada se bloquea a 120 caracteres en el frontend. El backend retorna error de validación 422 si de alguna forma se excede. |
| SP-03 | `name` ya existe (insensible a mayúsculas/minúsculas) | El backend retorna HTTP 409 Conflict. El frontend muestra error visual: "El código de lote ya existe". |
| SP-04 | Error de conexión a la base de datos al guardar | El frontend muestra: "No es posible establecer conexión con el servidor, intente nuevamente". |
| SP-05 | El usuario edita y borra `name` | Mismo comportamiento que SP-01. |
| SP-06 | El usuario edita `name` a uno que ya existe | Mismo comportamiento que SP-03. |
| SP-07 | Otro usuario eliminó/inactivó el lote mientras el usuario actual lo edita | El backend detecta que el registro ya no existe o cambió de estado. Retorna error. El frontend muestra: "No es posible realizar esta acción, recargue la página". |
| SP-08 | El usuario intenta eliminar un lote ya eliminado | Igual que SP-07: "No es posible realizar esta acción, recargue la página". |
| SP-09 | El usuario intenta inactivar un lote ya inactivado | Igual que SP-07: "No es posible realizar esta acción, recargue la página". |
| SP-10 | El usuario no selecciona `product_id` | El frontend resalta el campo como obligatorio, muestra: "El campo producto es obligatorio". El backend retorna 422 si se evade. |
| SP-11 | `expiration_date` es anterior o igual a la fecha actual | El frontend y el backend bloquean con el mensaje: "La fecha de vencimiento debe ser posterior a la fecha actual". |
| SP-12 | El usuario envía un `product_id` que no existe o está eliminado | El backend valida la FK y retorna 422. |
| ~~SP-10/11/14 originales~~ | ~~Inactivar/eliminar con stock > 0 / stock negativo~~ | **Eliminados.** El stock no es columna de `lots` ni bloquea estas acciones. |

## 9. Casos Límite y Casos Estúpidos

### Casos Límite

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| CL-01 | Lote recién creado sin existencias (`stock = 0`, sin bodegas) | Permitido. El lote existe pero no tiene unidades. Aparece en la tabla con Stock = 0 y la columna Ubicación vacía (o con un mensaje "Sin existencias"). |
| CL-02 | `name` con espacios al inicio/final (ej. "  LOTE-A001  ") | El frontend y el backend recortan solo los espacios al inicio y al final (los espacios internos se conservan). La validación se ejecuta después del trim. |
| CL-03 | `name` con caracteres acentuados, ñ, guiones, números, paréntesis | Permitido (ej. "Lote N°3 – Medicamentos Controlados"). |
| CL-04 | Códigos que difieren solo por mayúsculas/minúsculas (ej. "LOTE-A001" vs "lote-a001") | Tratados como duplicados (insensible a mayúsculas). El backend retorna 409. |
| CL-05 | Dos usuarios crean un lote con el mismo `name` simultáneamente | Ambos pasan la validación del frontend. El primero tiene éxito. El segundo recibe 409 del backend: "El código de lote ya existe". |
| CL-06 | El usuario abre la edición del mismo lote en varias pestañas y guarda cambios diferentes | El primer guardado tiene éxito y actualiza la BD. Los guardados posteriores reciben error: "No es posible realizar esta acción, recargue la página". |
| CL-07 | No existen lotes | La tabla muestra: "No hay lotes registrados". |
| CL-08 | `expiration_date` = exactamente hoy | Rechazado. Debe ser estrictamente posterior a la fecha actual (>= no es válido). |
| CL-09 | Múltiples existencias del mismo lote en distintas bodegas | Permitido. Cada combinación `(bodega, producto, lote)` puede tener una fila en `inventories` con su cantidad. La columna Ubicación lista todas las bodegas y la cantidad en cada una. |
| CL-10 | Mismo lote en varias bodegas con cantidades distintas | Permitido. La columna Stock muestra la suma total. La columna Ubicación muestra los badges por bodega con su cantidad individual. |
| CL-11 | Misma `(producto, lote)` en bodegas distintas | Permitido, no hay unicidad por bodega — la unicidad es global sobre `name`. |
| CL-12 | Lote con `stock = 0` y el usuario intenta eliminar | El soft delete procede normalmente (no se valida stock). |
| CL-13 | Lote inactivo con existencias | Permitido. La inactivación no chequea stock. Si el usuario quiere evitar que se dispense un lote inactivo, eso se valida en otra capa (por ejemplo, al registrar movimientos de salida en Kardex). |
| CL-14 | Producto seleccionado es eliminado mientras el usuario completa el formulario | El backend valida que el `product_id` exista y no esté soft-deleted; si no, retorna 422. |
| CL-15 | Lote sin descripción | Permitido. `description` es opcional (nullable). |

### Casos Estúpidos

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| CE-01 | El usuario pega JSON, HTML o scripts en `name` | Se rechaza/sanitiza. El `name` permite: letras (incluyendo acentos y ñ), dígitos, espacios, guiones, paréntesis y símbolos de grado. No permite HTML, scripts, ni emojis. |
| CE-02 | El usuario escribe solo emojis como `name` | El frontend restringe la entrada; los emojis no son aceptados. |
| CE-03 | El usuario envía `name` compuesto solo por espacios | Después del trim se trata como vacío → mismo comportamiento que SP-01. |
| CE-04 | Doble clic rápido en "Guardar" | El botón de envío se deshabilita mientras la solicitud está en progreso (se muestra spinner de carga). |
| CE-05 | El usuario manipula el frontend para enviar `stock` o `warehouse_id` en el body | El backend ignora esos campos. `stock` y `warehouse_id` no son parte del modelo `Lot`; si vienen en el body, `StoreLotRequest`/`UpdateLotRequest` los descartan. |
| CE-06 | El usuario envía `expiration_date` en formato inválido (ej. "2025-13-45") | El frontend y el backend rechazan el formato; se muestra error de validación. |
| CE-07 | El usuario envía un `product_id` que no existe en la BD | El backend valida la FK y retorna 422. |
| CE-08 | El usuario intenta enviar `created_by`, `updated_by` o `status` manualmente | El backend ignora o sobrescribe estos valores; no pueden ser manipulados por el cliente. |

## 10. Criterios de Aceptación

### CA-01: Crear Lote (Camino Feliz)
**Dado** que el usuario está en la pantalla de Lotes  
**Cuando** el usuario completa un `name` válido y único (1–120 caracteres, recortado), selecciona un `product_id` válido, ingresa una `expiration_date` futura, y opcionalmente una `description`, luego hace clic en "Guardar"  
**Entonces** el lote se crea con `status=true`, `created_at`=hora actual del servidor, `created_by`=usuario autenticado, `stock` derivado = 0 (sin existencias aún)   PASS / FAIL  
**Y** se muestra la respuesta de éxito   PASS / FAIL  
**Y** la tabla inferior se refresca mostrando el nuevo lote con Stock = 0 y la columna Ubicación vacía   PASS / FAIL  
**Y** el formulario se reinicia a vacío   PASS / FAIL

### CA-02: Listar Lotes (Camino Feliz)
**Dado** que existen lotes en el sistema  
**Cuando** el usuario navega a la pantalla de Lotes  
**Entonces** la tabla muestra todos los lotes con las columnas: Código, Producto, **Ubicación**, **Stock**, Vencimiento, Estado, Acciones   PASS / FAIL  
**Y** cada fila muestra los botones de acción: Editar, Switch Activar/Inactivar, Eliminar   PASS / FAIL

### CA-03: Lista Vacía
**Dado** que no existen lotes en el sistema  
**Cuando** el usuario navega a la pantalla de Lotes  
**Entonces** la tabla muestra: "No hay lotes registrados"   PASS / FAIL

### CA-04: Editar Lote (Camino Feliz)
**Dado** que existe un lote  
**Cuando** el usuario hace clic en "Editar" en la fila de ese lote  
**Entonces** el formulario superior se precarga con los valores actuales: `name`, `product_id`, `expiration_date`, `description` (sin bodega, sin stock)   PASS / FAIL  
**Y** el usuario puede modificar y guardar, lo que actualiza el registro con `updated_by` y `updated_at`   PASS / FAIL  
**Y** la tabla se refresca mostrando los datos actualizados   PASS / FAIL

### CA-05: Activar/Inactivar Lote
**Dado** que existe un lote  
**Cuando** el usuario alterna el switch Activar/Inactivar  
**Entonces** el campo `status` se alterna y persiste (sin importar el stock)   PASS / FAIL  
**Y** la tabla se refresca   PASS / FAIL

### CA-05b: Inactivar lote con existencias no está bloqueado
**Dado** que existe un lote con `stock` derivado > 0 (tiene existencias en al menos una bodega)  
**Cuando** el usuario alterna el switch para inactivar  
**Entonces** el sistema **permite** la inactivación (no se bloquea por stock)   PASS / FAIL

### CA-06: Eliminar Lote (soft delete)
**Dado** que existe un lote  
**Cuando** el usuario hace clic en "Eliminar" y confirma el diálogo  
**Entonces** el lote se elimina mediante soft delete (`deleted_at` poblado, `status` establecido a false)   PASS / FAIL  
**Y** el registro desaparece de la tabla   PASS / FAIL  
**Y** la eliminación no se bloquea por el stock (las existencias asociadas se mantienen en `inventories` hasta que se gestionen por separado, si corresponde)   PASS / FAIL

### CA-07: Validar Código Obligatorio
**Dado** que el usuario está completando el formulario de lote  
**Cuando** el usuario envía con `name` vacío o solo espacios (después de trim)  
**Entonces** el frontend resalta el campo   PASS / FAIL  
**Y** muestra: "El campo código es obligatorio"   PASS / FAIL  
**Y** el registro NO se guarda   PASS / FAIL

### CA-08: Validar Código Duplicado
**Dado** que ya existe un lote llamado "LOTE-A001"  
**Cuando** el usuario intenta crear o editar un lote con `name` "lote-a001" (o "Lote-A001")  
**Entonces** el backend retorna HTTP 409   PASS / FAIL  
**Y** el frontend muestra: "El código de lote ya existe"   PASS / FAIL

### CA-09: Validar Longitud del Código
**Dado** que el usuario está completando el formulario de lote  
**Cuando** el usuario escribe más de 120 caracteres en `name`  
**Entonces** la entrada bloquea más caracteres en 120   PASS / FAIL  
**Y** si una solicitud evade la validación del frontend, el backend retorna HTTP 422   PASS / FAIL

### CA-10: Validar Producto Obligatorio
**Dado** que el usuario está completando el formulario de lote  
**Cuando** el usuario envía sin seleccionar `product_id`  
**Entonces** el frontend resalta el campo   PASS / FAIL  
**Y** muestra: "El campo producto es obligatorio"   PASS / FAIL  
**Y** el registro NO se guarda   PASS / FAIL

### CA-11: Error de Conexión a la Base de Datos
**Dado** que el usuario envía un formulario válido  
**Cuando** la base de datos no es accesible  
**Entonces** el frontend muestra: "No es posible establecer conexión con el servidor, intente nuevamente"   PASS / FAIL

### CA-12: Modificación Concurrente
**Dado** que el Usuario A está editando el lote X en la Pestaña 1 y el Usuario B elimina/inactiva el lote X en la Pestaña 2  
**Cuando** el Usuario A hace clic en "Guardar" después de que la acción del Usuario B se completó  
**Entonces** el backend detecta que el estado del registro ha cambiado   PASS / FAIL  
**Y** el frontend muestra: "No es posible realizar esta acción, recargue la página"   PASS / FAIL

### CA-13: Prevención de Doble Envío
**Dado** que el usuario hace clic en "Guardar"  
**Cuando** la solicitud está en progreso  
**Entonces** el botón de envío se deshabilita y se muestra un spinner de carga   PASS / FAIL  
**Y** el usuario no puede disparar un segundo envío   PASS / FAIL

### CA-14: Sanitizar Entrada Maliciosa
**Dado** que el usuario pega contenido HTML, JSON o script en `name`  
**Entonces** la entrada es rechazada o sanitizada (el `name` permite: letras (incluyendo acentos y ñ), dígitos, espacios, guiones, paréntesis y símbolos de grado; no permite HTML, scripts, ni emojis)   PASS / FAIL

### CA-15: Editar sin Cambios
**Dado** que el usuario hace clic en "Editar" en un lote y no realiza modificaciones  
**Cuando** el usuario hace clic en "Guardar"  
**Entonces** los campos `updated_by` y `updated_at` del registro se actualizan   PASS / FAIL

### CA-16: Recortar Entrada
**Dado** que el usuario escribe "  LOTE-A001  " como `name`  
**Cuando** se envía el formulario  
**Entonces** el valor se recorta a "LOTE-A001" antes de la validación y persistencia   PASS / FAIL  
**Y** los espacios internos se conservan   PASS / FAIL

### CA-17: Caracteres Especiales Permitidos
**Dado** que el usuario escribe "Lote N°3 – Medicamentos Controlados (Cadena Frío)" como `name`  
**Entonces** el código es aceptado y guardado   PASS / FAIL

### CA-18: Confirmación Requerida para Eliminar
**Dado** que el usuario hace clic en "Eliminar" en un lote  
**Cuando** aparece el diálogo de confirmación  
**Entonces** la eliminación solo procede si el usuario confirma explícitamente   PASS / FAIL  
**Y** si el usuario cancela, no ocurre nada   PASS / FAIL

### CA-19: Detección de Lote Eliminado/Inactivado
**Dado** que un lote fue eliminado mediante soft delete o inactivado por otro usuario  
**Cuando** el usuario actual intenta realizar cualquier acción sobre él (editar, eliminar, alternar)  
**Entonces** el sistema retorna un error   PASS / FAIL  
**Y** el frontend muestra: "No es posible realizar esta acción, recargue la página"   PASS / FAIL

### CA-20: Campos de Auditoría al Crear
**Dado** que se crea un nuevo lote  
**Cuando** el registro persiste  
**Entonces** `created_by` se asigna al usuario de la sesión autenticada   PASS / FAIL  
**Y** `created_at` se asigna automáticamente por Eloquent al crear el registro   PASS / FAIL  
**Y** `updated_by` es nulo   PASS / FAIL  
**Y** `updated_at` es nulo   PASS / FAIL

### CA-21: Campos de Auditoría al Editar
**Dado** que se edita un lote existente  
**Cuando** el registro se actualiza  
**Entonces** `updated_by` se asigna al usuario de la sesión autenticada   PASS / FAIL  
**Y** `updated_at` se asigna automáticamente por Eloquent al actualizar el registro   PASS / FAIL  
**Y** `created_by` y `created_at` permanecen sin cambios   PASS / FAIL

### CA-22: Crear Lote con Asociación a Producto
**Dado** que el usuario está creando un lote  
**Cuando** selecciona un `product_id` válido de la lista de productos  
**Entonces** el lote se persiste con la FK correcta   PASS / FAIL  
**Y** la tabla muestra el nombre del producto en lugar del UUID   PASS / FAIL

### CA-23: Validar Fecha de Vencimiento Futura
**Dado** que el usuario está completando el formulario de lote  
**Cuando** ingresa una `expiration_date` anterior o igual a la fecha actual  
**Entonces** el frontend y el backend bloquean el envío   PASS / FAIL  
**Y** muestran: "La fecha de vencimiento debe ser posterior a la fecha actual"   PASS / FAIL

### CA-24: Lote recién creado tiene stock derivado 0
**Dado** que el usuario acaba de crear un lote (no ha registrado movimientos de Kardex para él)  
**Cuando** navega a la pantalla de Lotes  
**Entonces** la columna Stock muestra 0   PASS / FAIL  
**Y** la columna Ubicación no muestra ninguna bodega (o muestra el mensaje "Sin existencias")   PASS / FAIL

### CA-25: Stock se asigna via Kardex, no via Lote
**Dado** que el usuario está en la pantalla de Lotes  
**Cuando** el usuario quiere cargar stock inicial a un lote recién creado  
**Entonces** la pantalla de Lotes no expone un input de stock ni de bodega   PASS / FAIL  
**Y** el flujo correcto es ir a la pantalla de Kardex → "Registrar movimiento" → tipo `ENTRY` → seleccionar bodega, lote y cantidad   PASS / FAIL

### CA-26: Un lote puede existir en N bodegas
**Dado** que un lote X tiene existencias registradas en 2 bodegas distintas vía Kardex (movimientos `ENTRY`)  
**Cuando** el usuario navega a la pantalla de Lotes  
**Entonces** la fila del lote X muestra en la columna Ubicación ambas bodegas con la cantidad individual en cada una (badges)   PASS / FAIL  
**Y** la columna Stock muestra la suma de ambas cantidades   PASS / FAIL

### CA-27: Lote sin existencias muestra Ubicación vacía
**Dado** que un lote no tiene existencias (todas fueron retiradas o nunca se le cargaron)  
**Cuando** el usuario navega a la pantalla de Lotes  
**Entonces** la columna Stock muestra 0   PASS / FAIL  
**Y** la columna Ubicación está vacía (o muestra un placeholder como "Sin existencias")   PASS / FAIL

### CA-28: Filtro Derivado "Con stock"
**Dado** lotes en el sistema  
**Cuando** se consulta con filtro "Con stock"  
**Entonces** retorna solo lotes con `stock` derivado > 0   PASS / FAIL

### CA-29: Filtro Derivado "Por vencer (30 días)" Incluye Hoy
**Dado** un lote con `expiration_date` = today y `stock` > 0  
**Cuando** se consulta con filtro "Por vencer (30 días)"  
**Entonces** el lote aparece en los resultados   PASS / FAIL

### CA-30: Filtro Derivado "Vencido" Excluye Hoy
**Dado** un lote con `expiration_date` = today y `stock` > 0  
**Cuando** se consulta con filtro "Vencido"  
**Entonces** el lote NO aparece en los resultados   PASS / FAIL

### CA-31: Filtros Derivados Excluyen Fechas Nulas
**Dado** lotes con `expiration_date` IS NULL  
**Cuando** se consulta con filtro "Por vencer" o "Vencido"  
**Entonces** esos lotes son excluidos   PASS / FAIL

