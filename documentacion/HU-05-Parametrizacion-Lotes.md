# HU-05 – Gestión de Lotes (Inventario)

## 1. Título y Descripción

**Título:** HU-05 – Gestión de Lotes (Inventario)

**Descripción:** Permitir al usuario gestionar lotes como la entidad central de inventario con trazabilidad. Un lote pertenece a un producto y a una bodega, tiene stock físico, fecha de vencimiento y código único. La pantalla contiene un formulario en la parte superior y una tabla de lotes en la parte inferior, siguiendo el mismo patrón CRUD de HU-03.

## 2. Modelo de Datos

Tabla: `lots`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por el sistema (lado del servidor/BD) |
| code | varchar(60) | UNIQUE, NOT NULL, unicidad insensible a mayúsculas/minúsculas |
| product_id | uuid | FK → products (id), NOT NULL, onDelete RESTRICT |
| warehouse_id | uuid | FK → bodegas (id), NOT NULL, onDelete RESTRICT |
| stock | integer | NOT NULL, valor por defecto 0, mínimo 0 |
| expiration_date | date | NOT NULL, debe ser una fecha futura |
| status | boolean | NOT NULL, valor por defecto true (true=Activo, false=Inactivo) |
| created_by | uuid | FK → users (id), NOT NULL, onDelete RESTRICT |
| updated_by | uuid | FK → users (id), permite nulo, onDelete SET NULL |
| created_at | timestamp | NOT NULL, asignado automáticamente por Eloquent |
| updated_at | timestamp | permite nulo, asignado automáticamente por Eloquent |
| deleted_at | timestamp | permite nulo (SoftDeletes) |

Todos los nombres de columna están en inglés (compatibles con Eloquent: `created_by` para belongsTo, `created_at`/`updated_at` para timestamps, `deleted_at` para SoftDeletes).

| FK | onDelete |
|---|---|
| `product_id` → products (id) | RESTRICT |
| `warehouse_id` → bodegas (id) | RESTRICT |
| `created_by` → users (id) | RESTRICT |
| `updated_by` → users (id) | SET NULL |

Sigue la política global de onDelete documentada en AI_USAGE.md.

## 3. Bloqueos y Dependencias

- Debe existir la tabla `users` (estructura estándar de Laravel 9).
- Debe existir la tabla `products` (HU-01 – Productos) — el campo `product_id` es FK hacia ella.
- Debe existir la tabla `bodegas` (HU-03 – Bodegas) — el campo `warehouse_id` es FK hacia ella.
- Debe estar definido el diseño UI/UX del formulario (campos: `code`, `product_id`, `warehouse_id`, `stock`, `expiration_date`).
- La validación "no eliminar si stock > 0" depende de la tabla `lots`. Si el endpoint de verificación no está disponible, la validación se omite (no-op).
- Esta HU es un prerrequisito para HU-02 (Búsqueda de Productos) y para futuras HU de ventas/despacho, ya que provee los estados derivados de stock y vencimiento.

## 4. Variables y Configuración

| Variable | Tipo | Entornos | Valor por defecto | Descripción |
|---|---|---|---|---|
| LOT_CODE_MAX_LENGTH | integer | todos | 60 | Longitud máxima permitida para el campo `code`. |
| STOCK_MIN_VALUE | integer | todos | 0 | Valor mínimo permitido para el campo `stock`. |
| EXPIRATION_FUTURE_DAYS | integer | todos | 1 | Diferencia mínima de días entre `expiration_date` y la fecha actual (debe ser > hoy). |
| DERIVED_STATE_FILTER | string | todos | "Todos" | Filtro derivado para listados: "Todos", "Con stock", "Por vencer (30 días)", "Vencido". No se almacena en BD. |
| LOTS_TABLE_PAGE_SIZE | integer | todos | 10 | Cantidad de filas visibles por página en la tabla del frontend (paginación del lado del cliente). |

### Estados Derivados de Lote

| Estado | Regla de negocio | Uso |
|---|---|---|
| Con stock | `stock > 0` (sin importar `expiration_date`) | Filtro en HU-02 |
| Por vencer (30 días) | `stock > 0` AND `expiration_date` BETWEEN today AND today + 30 (inclusive ambos extremos) | Filtro en HU-02 |
| Vencido | `stock > 0` AND `expiration_date < today` (estricto, no incluye today) | Filtro en HU-02 |
| Todos | Sin restricciones de stock ni fecha | Filtro en HU-02 |

Estos estados son COMPUTADOS en tiempo de consulta por el backend de HU-02. No son columnas en la tabla `lots`. Las comparaciones de fecha usan solo la parte DATE (sin hora).

- Sin restricciones de rol o permisos — todos los usuarios pueden realizar todas las acciones.
- Sin feature toggles ni comportamiento específico por tenant.
- Sin comportamiento específico por entorno; los seeders pueden ejecutarse en todos los entornos.
- Lotes ilimitados (en el futuro una tabla de configuración global podría agregar límites, fuera de alcance por ahora).
- El backend retorna todos los lotes activos (se esperan pocos registros); el frontend maneja filtrado, búsqueda y paginación del lado del cliente.

## 5. Suposiciones

1. La tabla `users` existe con la estructura estándar de Laravel 9 (`id`, `name`, `email`, `password`, `timestamps`).
2. La tabla `products` (HU-01) existe y contiene al menos un producto antes de crear un lote.
3. La tabla `bodegas` (HU-03) existe y contiene al menos una bodega antes de crear un lote.
4. El diseño UI del formulario de lote incluye exactamente los campos solicitados: `code` (texto), `product_id` (select de productos), `warehouse_id` (select de bodegas), `stock` (entero) y `expiration_date` (selector de fecha).
5. Los seeders pueden ejecutarse en cualquier entorno (dev, staging, production).
6. No existen restricciones de infraestructura por ahora; si se requiere, limitar a 100 lotes mediante configuración global (fuera de alcance).
7. `created_at` se asigna automáticamente por Eloquent al crear el registro.
8. El UUID `id` es generado automáticamente por el sistema (lado del servidor/BD), no se proporciona externamente.
9. La unicidad de `code` se valida tanto en el frontend (antes del envío) como en el backend (antes de la persistencia), y es insensible a mayúsculas/minúsculas.
10. `created_by` se obtiene del usuario de la sesión autenticada y no puede elegirse manualmente en el formulario.
11. La `expiration_date` debe ser una fecha válida posterior a la fecha actual; las fechas pasadas o iguales a hoy se rechazan.
12. El `stock` por defecto es 0 y el usuario puede establecer un stock inicial en la creación; nunca puede ser negativo.
13. El select de producto muestra solo productos no eliminados (deleted_at IS NULL).

## 6. Fuera de Alcance (explícitamente excluido)

- Asignación de lotes a productos desde la pantalla de productos (pertenece a HU-01 Productos).
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
2. El sistema muestra un formulario de registro en la parte superior con los campos: `code` (entrada de texto, máximo 60 caracteres), `product_id` (select de productos activos), `warehouse_id` (select de bodegas activas), `stock` (entrada numérica, mínimo 0) y `expiration_date` (selector de fecha).
3. Debajo del formulario, una tabla lista todos los lotes existentes con las columnas: Código, Producto, Bodega, Stock, Vencimiento, Estado, Acciones. Cada fila tiene botones de acción: Editar, Switch Activar/Inactivar, Eliminar.
4. El usuario completa el formulario y hace clic en "Guardar".
5. El frontend valida: `code` es obligatorio, no vacío después de trim, máximo 60 caracteres (entrada bloqueada a 60); `product_id` y `warehouse_id` son obligatorios; `stock` es obligatorio y mayor o igual a 0; `expiration_date` es obligatoria y posterior a la fecha actual.
6. El backend valida: unicidad de `code` (insensible a mayúsculas/minúsculas), elimina espacios al inicio y al final, existencia de `product_id` y `warehouse_id`, `stock` >= 0, `expiration_date` futura. Si es válido, crea el registro con `created_by` de la sesión, `created_at` = hora del servidor, `status` = true.
7. El sistema retorna respuesta de éxito. La tabla se refresca mostrando el nuevo registro. El formulario se reinicia.

### Editar
1. El usuario hace clic en "Editar" en una fila de lote.
2. El formulario superior se precarga con los valores actuales: `code`, `product_id`, `warehouse_id`, `stock`, `expiration_date` y `status`.
3. El usuario modifica los campos y hace clic en "Guardar".
4. Aplican las mismas reglas de validación. El backend actualiza `updated_by` y `updated_at`.
5. La tabla se refresca mostrando el registro actualizado.

### Editar sin cambios
1. El usuario hace clic en "Editar", no realiza cambios y hace clic en "Guardar".
2. El sistema actualiza `updated_by` y `updated_at` (no hay bloqueo de "sin cambios detectados").

### Activar/Inactivar
1. El usuario alterna el switch en una fila de lote.
2. Si el lote tiene `stock` > 0 → el sistema bloquea la acción con el mensaje: "No se puede inactivar, el lote tiene stock activo".
3. Si el lote tiene `stock` = 0 → se alterna y guarda el `status`. La tabla se refresca.

### Eliminar (soft delete)
1. El usuario hace clic en "Eliminar" en una fila de lote.
2. El sistema muestra un diálogo de confirmación.
3. Si confirma Y el lote tiene `stock` = 0 → soft delete (`deleted_at` poblado, `status` establecido a inactivo). El registro desaparece de la tabla.
4. Si el lote tiene `stock` > 0 → el sistema bloquea con el mensaje: "No se puede eliminar, el lote tiene stock activo".

### Estado vacío
- Cuando no existen lotes, la tabla muestra: "No hay lotes registrados".

### Estado de carga
- Mientras cualquier operación esté en progreso, mostrar un spinner de carga y deshabilitar el botón de envío para prevenir envíos duplicados.

## 8. Caminos Tristes (errores esperados)

| # | Escenario | Comportamiento del Sistema |
|---|---|---|
| SP-01 | El usuario envía con `code` vacío o solo espacios | El frontend resalta el campo como obligatorio, muestra el mensaje: "El campo código es obligatorio". El backend también valida. |
| SP-02 | `code` excede 60 caracteres | La entrada se bloquea a 60 caracteres en el frontend. El backend retorna error de validación 422 si de alguna forma se excede. |
| SP-03 | `code` ya existe (insensible a mayúsculas/minúsculas) | El backend retorna HTTP 409 Conflict. El frontend muestra error visual: "El código de lote ya existe". |
| SP-04 | Error de conexión a la base de datos al guardar | El frontend muestra: "No es posible establecer conexión con el servidor, intente nuevamente". |
| SP-05 | El usuario edita y borra `code` | Mismo comportamiento que SP-01. |
| SP-06 | El usuario edita `code` a uno que ya existe | Mismo comportamiento que SP-03. |
| SP-07 | Otro usuario eliminó/inactivó el lote mientras el usuario actual lo edita | El backend detecta que el registro ya no existe o cambió de estado. Retorna error. El frontend muestra: "No es posible realizar esta acción, recargue la página". |
| SP-08 | El usuario intenta eliminar un lote ya eliminado | Igual que SP-07: "No es posible realizar esta acción, recargue la página". |
| SP-09 | El usuario intenta inactivar un lote ya inactivado | Igual que SP-07: "No es posible realizar esta acción, recargue la página". |
| SP-10 | El usuario intenta inactivar un lote que tiene stock > 0 | El sistema bloquea: "No se puede inactivar, el lote tiene stock activo". |
| SP-11 | El usuario intenta eliminar un lote que tiene stock > 0 | El sistema bloquea: "No se puede eliminar, el lote tiene stock activo". |
| SP-12 | El usuario no selecciona `product_id` o `warehouse_id` | El frontend resalta el campo como obligatorio, muestra: "El campo [producto/bodega] es obligatorio". El backend retorna 422 si se evade. |
| SP-13 | `expiration_date` es anterior o igual a la fecha actual | El frontend y el backend bloquean con el mensaje: "La fecha de vencimiento debe ser posterior a la fecha actual". |
| SP-14 | `stock` es negativo | El frontend bloquea con `min=0`. El backend retorna 422 si se evade. |

## 9. Casos Límite y Casos Estúpidos

### Casos Límite

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| CL-01 | `stock` = 0 | Permitido. El lote existe pero no tiene unidades. Puede eliminarse o inactivarse. |
| CL-02 | `code` con espacios al inicio/final (ej. "  LOTE-A001  ") | El frontend y el backend recortan solo los espacios al inicio y al final (los espacios internos se conservan). La validación se ejecuta después del trim. |
| CL-03 | `code` con caracteres acentuados, ñ, guiones, números, paréntesis | Permitido (ej. "Lote N°3 – Medicamentos Controlados"). |
| CL-04 | Códigos que difieren solo por mayúsculas/minúsculas (ej. "LOTE-A001" vs "lote-a001") | Tratados como duplicados (insensible a mayúsculas). El backend retorna 409. |
| CL-05 | Dos usuarios crean un lote con el mismo `code` simultáneamente | Ambos pasan la validación del frontend. El primero tiene éxito. El segundo recibe 409 del backend: "El código de lote ya existe". |
| CL-06 | El usuario abre la edición del mismo lote en varias pestañas y guarda cambios diferentes | El primer guardado tiene éxito y actualiza la BD. Los guardados posteriores reciben error: "No es posible realizar esta acción, recargue la página". |
| CL-07 | No existen lotes | La tabla muestra: "No hay lotes registrados". |
| CL-08 | `expiration_date` = exactamente hoy | Rechazado. Debe ser estrictamente posterior a la fecha actual (>= no es válido). |
| CL-09 | Múltiples lotes para el mismo producto en distintas bodegas | Permitido. Cada combinación producto+bodega+código es un lote independiente. |
| CL-10 | Mismo producto + misma bodega + distinto `code` | Permitido. El sistema permite múltiples lotes del mismo producto en la misma bodega siempre que el `code` sea único. |
| CL-11 | Mismo producto + misma bodega + mismo `code` | Rechazado por la validación de unicidad global de `code`. |
| CL-12 | `stock` = 0 y el usuario intenta eliminar | El soft delete procede normalmente. |
| CL-13 | Lote inactivo con `stock` > 0 | Posible si el stock fue cargado antes de inactivar? No: la inactivación está bloqueada si `stock` > 0. Si el stock se carga después de inactivo, queda inactivo con stock (estado inconsistente a evitar; la UI/UX no permite agregar stock a un lote inactivo). |
| CL-14 | Producto o bodega seleccionados son inactivos/eliminaron mientras el usuario completa el formulario | El backend valida que existan y estén activos; si no, retorna 422. |

### Casos Estúpidos

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| CE-01 | El usuario pega JSON, HTML o scripts en `code` | Se rechaza/sanitiza. El `code` permite: letras (incluyendo acentos y ñ), dígitos, espacios, guiones, paréntesis y símbolos de grado. No permite HTML, scripts, ni emojis. |
| CE-02 | El usuario escribe solo emojis como `code` | El frontend restringe la entrada; los emojis no son aceptados. |
| CE-03 | El usuario envía `code` compuesto solo por espacios | Después del trim se trata como vacío → mismo comportamiento que SP-01. |
| CE-04 | Doble clic rápido en "Guardar" | El botón de envío se deshabilita mientras la solicitud está en progreso (se muestra spinner de carga). |
| CE-05 | El usuario manipula el frontend para enviar `stock` como número decimal o string no numérico | El frontend restringe a enteros; el backend valida tipo y retorna 422 si se evade. |
| CE-06 | El usuario envía `expiration_date` en formato inválido (ej. "2025-13-45") | El frontend y el backend rechazan el formato; se muestra error de validación. |
| CE-07 | El usuario envía un `product_id` o `warehouse_id` que no existe en la BD | El backend valida la FK y retorna 422. |
| CE-08 | El usuario intenta enviar `created_by`, `updated_by` o `status` manualmente | El backend ignora o sobrescribe estos valores; no pueden ser manipulados por el cliente. |

## 10. Criterios de Aceptación

### CA-01: Crear Lote (Camino Feliz)
**Dado** que el usuario está en la pantalla de Lotes  
**Cuando** el usuario completa un `code` válido y único (1–60 caracteres, recortado), selecciona `product_id` y `warehouse_id` válidos, ingresa `stock` >= 0 y una `expiration_date` futura, luego hace clic en "Guardar"  
**Entonces** el lote se crea con `status=true`, `created_at`=hora actual del servidor, `created_by`=usuario autenticado   PASS / FAIL  
**Y** se muestra la respuesta de éxito   PASS / FAIL  
**Y** la tabla inferior se refresca mostrando el nuevo lote   PASS / FAIL  
**Y** el formulario se reinicia a vacío   PASS / FAIL

### CA-02: Listar Lotes (Camino Feliz)
**Dado** que existen lotes en el sistema  
**Cuando** el usuario navega a la pantalla de Lotes  
**Entonces** la tabla muestra todos los lotes con las columnas: Código, Producto, Bodega, Stock, Vencimiento, Estado, Acciones   PASS / FAIL  
**Y** cada fila muestra los botones de acción: Editar, Switch Activar/Inactivar, Eliminar   PASS / FAIL

### CA-03: Lista Vacía
**Dado** que no existen lotes en el sistema  
**Cuando** el usuario navega a la pantalla de Lotes  
**Entonces** la tabla muestra: "No hay lotes registrados"   PASS / FAIL

### CA-04: Editar Lote (Camino Feliz)
**Dado** que existe un lote  
**Cuando** el usuario hace clic en "Editar" en la fila de ese lote  
**Entonces** el formulario superior se precarga con los valores actuales: `code`, `product_id`, `warehouse_id`, `stock`, `expiration_date`   PASS / FAIL  
**Y** el usuario puede modificar y guardar, lo que actualiza el registro con `updated_by` y `updated_at`   PASS / FAIL  
**Y** la tabla se refresca mostrando los datos actualizados   PASS / FAIL

### CA-05: Activar/Inactivar Lote sin Stock
**Dado** que existe un lote y su `stock` es igual a 0  
**Cuando** el usuario alterna el switch Activar/Inactivar  
**Entonces** el campo `status` se alterna y persiste   PASS / FAIL  
**Y** la tabla se refresca   PASS / FAIL

### CA-06: Bloquear Inactivación con Stock Activo
**Dado** que existe un lote y su `stock` es mayor a 0  
**Cuando** el usuario intenta alternar el switch para inactivar  
**Entonces** el sistema bloquea la acción   PASS / FAIL  
**Y** muestra: "No se puede inactivar, el lote tiene stock activo"   PASS / FAIL

### CA-07: Eliminar Lote sin Stock
**Dado** que existe un lote y su `stock` es igual a 0  
**Cuando** el usuario hace clic en "Eliminar" y confirma el diálogo  
**Entonces** el lote se elimina mediante soft delete (`deleted_at` poblado, `status` establecido a false)   PASS / FAIL  
**Y** el registro desaparece de la tabla   PASS / FAIL

### CA-08: Bloquear Eliminación con Stock Activo
**Dado** que existe un lote y su `stock` es mayor a 0  
**Cuando** el usuario hace clic en "Eliminar"  
**Entonces** el sistema bloquea la acción   PASS / FAIL  
**Y** muestra: "No se puede eliminar, el lote tiene stock activo"   PASS / FAIL

### CA-09: Validar Código Obligatorio
**Dado** que el usuario está completando el formulario de lote  
**Cuando** el usuario envía con `code` vacío o solo espacios (después de trim)  
**Entonces** el frontend resalta el campo   PASS / FAIL  
**Y** muestra: "El campo código es obligatorio"   PASS / FAIL  
**Y** el registro NO se guarda   PASS / FAIL

### CA-10: Validar Código Duplicado
**Dado** que ya existe un lote llamado "LOTE-A001"  
**Cuando** el usuario intenta crear o editar un lote con `code` "lote-a001" (o "Lote-A001")  
**Entonces** el backend retorna HTTP 409   PASS / FAIL  
**Y** el frontend muestra: "El código de lote ya existe"   PASS / FAIL

### CA-11: Validar Longitud del Código
**Dado** que el usuario está completando el formulario de lote  
**Cuando** el usuario escribe más de 60 caracteres en `code`  
**Entonces** la entrada bloquea más caracteres en 60   PASS / FAIL  
**Y** si una solicitud evade la validación del frontend, el backend retorna HTTP 422   PASS / FAIL

### CA-12: Validar Producto y Bodega Obligatorios
**Dado** que el usuario está completando el formulario de lote  
**Cuando** el usuario envía sin seleccionar `product_id` o `warehouse_id`  
**Entonces** el frontend resalta el campo   PASS / FAIL  
**Y** muestra: "El campo [producto/bodega] es obligatorio"   PASS / FAIL  
**Y** el registro NO se guarda   PASS / FAIL

### CA-13: Error de Conexión a la Base de Datos
**Dado** que el usuario envía un formulario válido  
**Cuando** la base de datos no es accesible  
**Entonces** el frontend muestra: "No es posible establecer conexión con el servidor, intente nuevamente"   PASS / FAIL

### CA-14: Modificación Concurrente
**Dado** que el Usuario A está editando el lote X en la Pestaña 1 y el Usuario B elimina/inactiva el lote X en la Pestaña 2  
**Cuando** el Usuario A hace clic en "Guardar" después de que la acción del Usuario B se completó  
**Entonces** el backend detecta que el estado del registro ha cambiado   PASS / FAIL  
**Y** el frontend muestra: "No es posible realizar esta acción, recargue la página"   PASS / FAIL

### CA-15: Prevención de Doble Envío
**Dado** que el usuario hace clic en "Guardar"  
**Cuando** la solicitud está en progreso  
**Entonces** el botón de envío se deshabilita y se muestra un spinner de carga   PASS / FAIL  
**Y** el usuario no puede disparar un segundo envío   PASS / FAIL

### CA-16: Sanitizar Entrada Maliciosa
**Dado** que el usuario pega contenido HTML, JSON o script en `code`  
**Entonces** la entrada es rechazada o sanitizada (el `code` permite: letras (incluyendo acentos y ñ), dígitos, espacios, guiones, paréntesis y símbolos de grado; no permite HTML, scripts, ni emojis)   PASS / FAIL

### CA-17: Editar sin Cambios
**Dado** que el usuario hace clic en "Editar" en un lote y no realiza modificaciones  
**Cuando** el usuario hace clic en "Guardar"  
**Entonces** los campos `updated_by` y `updated_at` del registro se actualizan   PASS / FAIL

### CA-18: Recortar Entrada
**Dado** que el usuario escribe "  LOTE-A001  " como `code`  
**Cuando** se envía el formulario  
**Entonces** el valor se recorta a "LOTE-A001" antes de la validación y persistencia   PASS / FAIL  
**Y** los espacios internos se conservan   PASS / FAIL

### CA-19: Caracteres Especiales Permitidos
**Dado** que el usuario escribe "Lote N°3 – Medicamentos Controlados (Cadena Frío)" como `code`  
**Entonces** el código es aceptado y guardado   PASS / FAIL

### CA-20: Confirmación Requerida para Eliminar
**Dado** que el usuario hace clic en "Eliminar" en un lote  
**Cuando** aparece el diálogo de confirmación  
**Entonces** la eliminación solo procede si el usuario confirma explícitamente   PASS / FAIL  
**Y** si el usuario cancela, no ocurre nada   PASS / FAIL

### CA-21: Detección de Lote Eliminado/Inactivado
**Dado** que un lote fue eliminado mediante soft delete o inactivado por otro usuario  
**Cuando** el usuario actual intenta realizar cualquier acción sobre él (editar, eliminar, alternar)  
**Entonces** el sistema retorna un error   PASS / FAIL  
**Y** el frontend muestra: "No es posible realizar esta acción, recargue la página"   PASS / FAIL

### CA-22: Campos de Auditoría al Crear
**Dado** que se crea un nuevo lote  
**Cuando** el registro persiste  
**Entonces** `created_by` se asigna al usuario de la sesión autenticada   PASS / FAIL  
**Y** `created_at` se asigna automáticamente por Eloquent al crear el registro   PASS / FAIL  
**Y** `updated_by` es nulo   PASS / FAIL  
**Y** `updated_at` es nulo   PASS / FAIL

### CA-23: Campos de Auditoría al Editar
**Dado** que se edita un lote existente  
**Cuando** el registro se actualiza  
**Entonces** `updated_by` se asigna al usuario de la sesión autenticada   PASS / FAIL  
**Y** `updated_at` se asigna automáticamente por Eloquent al actualizar el registro   PASS / FAIL  
**Y** `created_by` y `created_at` permanecen sin cambios   PASS / FAIL

### CA-24: Crear Lote con Asociación a Producto y Bodega
**Dado** que el usuario está creando un lote  
**Cuando** selecciona un `product_id` válido de la lista de productos y un `warehouse_id` válido de la lista de bodegas  
**Entonces** el lote se persiste con las FK correctas   PASS / FAIL  
**Y** la tabla muestra el nombre del producto y el nombre de la bodega en lugar de los UUID   PASS / FAIL

### CA-25: Validar Fecha de Vencimiento Futura
**Dado** que el usuario está completando el formulario de lote  
**Cuando** ingresa una `expiration_date` anterior o igual a la fecha actual  
**Entonces** el frontend y el backend bloquean el envío   PASS / FAIL  
**Y** muestran: "La fecha de vencimiento debe ser posterior a la fecha actual"   PASS / FAIL

### CA-26: Validar Stock No Negativo
**Dado** que el usuario está completando el formulario de lote  
**Cuando** ingresa un `stock` negativo o deja el campo vacío  
**Entonces** el frontend bloquea con `min=0` y mensaje de obligatoriedad   PASS / FAIL  
**Y** si una solicitud evade la validación, el backend retorna HTTP 422   PASS / FAIL

### CA-27: Bloquear Eliminación cuando Stock > 0
**Dado** que existe un lote con `stock` mayor a 0  
**Cuando** el usuario hace clic en "Eliminar" y confirma  
**Entonces** el sistema NO realiza el soft delete   PASS / FAIL  
**Y** muestra: "No se puede eliminar, el lote tiene stock activo"   PASS / FAIL

### CA-28: Bloquear Inactivación cuando Stock > 0
**Dado** que existe un lote con `stock` mayor a 0  
**Cuando** el usuario alterna el switch para inactivar  
**Entonces** el sistema NO cambia el `status`   PASS / FAIL  
**Y** muestra: "No se puede inactivar, el lote tiene stock activo"   PASS / FAIL

### CA-29: Listar Lotes con Producto, Bodega, Stock y Vencimiento
**Dado** que existen lotes en el sistema  
**Cuando** el usuario navega a la pantalla de Lotes  
**Entonces** la tabla muestra en cada fila: el `code` del lote, el nombre del producto, el nombre de la bodega, el `stock`, la `expiration_date` y el `status`   PASS / FAIL

### CA-30: Filtro Derivado "Con stock"
**Dado** lotes en el sistema  
**Cuando** se consulta con filtro "Con stock"  
**Entonces** retorna solo lotes con `stock` > 0   PASS / FAIL

### CA-31: Filtro Derivado "Por vencer (30 días)" Incluye Hoy
**Dado** un lote con `expiration_date` = today  
**Cuando** se consulta con filtro "Por vencer (30 días)"  
**Entonces** el lote aparece en los resultados   PASS / FAIL

### CA-32: Filtro Derivado "Vencido" Excluye Hoy
**Dado** un lote con `expiration_date` = today  
**Cuando** se consulta con filtro "Vencido"  
**Entonces** el lote NO aparece en los resultados   PASS / FAIL

### CA-33: Filtros Derivados Excluyen Fechas Nulas
**Dado** lotes con `expiration_date` IS NULL  
**Cuando** se consulta con filtro "Por vencer" o "Vencido"  
**Entonces** esos lotes son excluidos   PASS / FAIL
