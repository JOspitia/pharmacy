# HU-01 – Gestión de Productos

## 1. Título y Descripción

**Título:** HU-01 – Gestión de Productos

**Descripción:** Permitir al usuario gestionar productos farmacéuticos mediante un CRUD completo (crear, listar, editar, eliminar con soft delete). La pantalla contiene un formulario de registro en la parte superior y una tabla de productos en la parte inferior. No incluye activación/inactivación; el único mecanismo de retiro es soft delete, bloqueado cuando el producto tiene stock activo en lotes.

## 2. Modelo de Datos

Tabla: `products`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por el sistema (lado del servidor/BD) |
| name | varchar(120) | UNIQUE, NOT NULL, unicidad insensible a mayúsculas/minúsculas |
| cum | varchar(60) | UNIQUE, NOT NULL, unicidad insensible a mayúsculas/minúsculas |
| barcode | varchar(60) | UNIQUE, permite nulo, opcional |
| invima_registration | varchar(60) | UNIQUE, NOT NULL, unicidad insensible a mayúsculas/minúsculas |
| group_id | uuid | FK → groups (id), NOT NULL, onDelete RESTRICT |
| unit_id | uuid | FK → units (id), NOT NULL, onDelete RESTRICT |
| created_by | uuid | FK → users (id), NOT NULL, onDelete RESTRICT |
| updated_by | uuid | FK → users (id), permite nulo, onDelete SET NULL |
| created_at | timestamp | NOT NULL, asignado automáticamente por Eloquent |
| updated_at | timestamp | permite nulo, asignado automáticamente por Eloquent |
| deleted_at | timestamp | permite nulo (SoftDeletes) |

Tabla adicional creada en esta HU: `units`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por el sistema |
| name | varchar(60) | UNIQUE, NOT NULL |
| description | text | permite nulo |
| created_at | timestamp | NOT NULL, asignado automáticamente por Eloquent |
| updated_at | timestamp | permite nulo, asignado automáticamente por Eloquent |

La tabla `units` es una tabla de referencia poblada exclusivamente por seeders. No tiene CRUD de interfaz de usuario ni historia de usuario independiente; se documenta y entrega dentro del alcance de HU-01 como dependencia de productos.

Todos los nombres de columna están en inglés (compatibles con Eloquent: `created_by` para belongsTo, `created_at`/`updated_at` para timestamps, `deleted_at` para SoftDeletes). Las relaciones FK de `products` se definen de forma explícita en la siguiente tabla:

| FK | onDelete |
|---|---|
| `group_id` → groups (id) | RESTRICT |
| `unit_id` → units (id) | RESTRICT |
| `created_by` → users (id) | RESTRICT |
| `updated_by` → users (id) | SET NULL |

Sigue la política global de onDelete documentada en AI_USAGE.md.

Tabla de referencia: `groups`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK |
| name | varchar | NOT NULL |
| status | boolean | DEFAULT true |

## 3. Bloqueos y Dependencias

- Debe existir la HU-03 – Gestión de Bodegas (la tabla `bodegas` debe estar disponible).
- Debe existir la HU-05 – Gestión de Lotes (la lógica de stock por lote se valida al eliminar un producto).
- Debe existir la HU-06 – Gestión de Grupos (la tabla `groups` alimenta el select de grupos).
- Debe existir la tabla `users` (estructura estándar de Laravel 9).
- La tabla `units` se crea mediante migración dentro de esta HU y se pobla mediante seeder.
- Esta HU es dependiente de HU-03, HU-05 y HU-06; sin ellas no puede completarse.

## 4. Variables y Configuración

- Sin restricciones de rol o permisos — todos los usuarios pueden realizar todas las acciones.
- Sin feature toggles ni comportamiento específico por tenant.
- Sin comportamiento específico por entorno; los seeders de `units` se ejecutan en todos los entornos (dev, staging, production).
- Productos ilimitados (en el futuro una tabla de configuración global podría agregar límites, fuera de alcance por ahora).
- El backend retorna datos paginados con metadatos (current_page, per_page, total, last_page); el tamaño de página por defecto es 15.
- No se maneja paginación del lado del cliente para productos.

## 5. Suposiciones

1. Las tablas `bodegas`, `groups` y `users` existen y están operativas desde sus respectivas HUs.
2. La tabla `units` se crea con una migración dentro de esta HU y se puebla con un seeder.
3. El diseño UI/UX del formulario de producto incluye exactamente los campos: `name`, `cum`, `barcode`, `invima_registration`, `group_id` (select) y `unit_id` (select).
4. El UUID `id` es generado automáticamente por el sistema (lado del servidor/BD), no se proporciona externamente.
5. Los campos `created_at` y `updated_at` se asignan automáticamente por Eloquent.
6. La unicidad de `cum`, `barcode` (si se proporciona) e `invima_registration` se valida tanto en el frontend como en el backend (insensible a mayúsculas/minúsculas).
7. El campo `created_by` se obtiene del usuario de la sesión autenticada y no puede elegirse manualmente en el formulario.
8. Los seeders de `units` se ejecutan en cualquier entorno (dev, staging, production).
9. La tabla `groups` tiene una columna `status` (boolean, default true) donde true = activo y false = inactivo. El select de grupos solo muestra registros con `status = true` y `deleted_at IS NULL`.
10. La verificación de "stock activo en lotes" (bloqueo de eliminación) requiere que la tabla `lots` exista. Si HU-05 no está implementada, esta validación se omite (no-op) hasta que la tabla esté disponible. La consulta es: SELECT COUNT(*) FROM lots WHERE product_id = ? AND stock > 0 AND status = true AND deleted_at IS NULL.

## 6. Fuera de Alcance (explícitamente excluido)

- Gestión de stock de productos → pertenece a HU-05 (Lotes).
- Fechas de vencimiento y rangos de vencimiento → pertenecen a HU-05 (Lotes).
- Búsqueda y filtrado avanzado de productos → pertenece a HU-02.
- Asignación de productos a bodegas → se realiza a través de lotes, no desde la gestión de productos.
- CRUD de unidades de medida → la tabla `units` es de referencia con seeder, sin interfaz de usuario.
- Notificaciones por correo electrónico — solo confirmación visual en el frontend.
- Importación/exportación masiva de productos.
- Gestión de precios o costos de productos.
- Tabla de configuración global para límites del sistema (HU futura, no esta).
- Gestión de roles y permisos.

## 7. Camino Feliz Principal (paso a paso)

### Listar y Crear
1. El usuario navega a la pantalla "Productos".
2. El sistema muestra un formulario de registro en la parte superior con los campos: `name` (texto, alfanumérico, máximo 120 caracteres), `cum` (texto, máximo 60 caracteres), `barcode` (texto opcional, máximo 60 caracteres), `invima_registration` (texto, máximo 60 caracteres), `group_id` (select que muestra solo grupos activos) y `unit_id` (select que muestra todas las unidades).
3. Debajo del formulario, una tabla lista los productos existentes con las columnas: Nombre, CUM, Código Barras, Invima, Grupo, Unidad, Acciones (Editar, Eliminar). No se incluye switch de activar/inactivar.
4. El usuario completa el formulario y hace clic en "Guardar".
5. El frontend valida: `name`, `cum` e `invima_registration` son obligatorios, no vacíos después de trim, y respetan sus longitudes máximas; `barcode` es opcional; `group_id` y `unit_id` están seleccionados.
6. El backend valida: unicidad de `name`, `cum`, `barcode` (solo si se envía) e `invima_registration` (insensible a mayúsculas/minúsculas), elimina espacios al inicio y al final de todos los campos de texto, convierte `barcode` vacío o solo espacios a `null`. Si es válido, crea el registro con `created_by` de la sesión, `created_at` = hora del servidor.
7. El sistema retorna respuesta de éxito. La tabla se refresca mostrando el nuevo registro. El formulario se reinicia.

### Editar
1. El usuario hace clic en "Editar" en una fila de producto.
2. El formulario superior se precarga con los datos actuales del producto: `name`, `cum`, `barcode`, `invima_registration`, `group_id` y `unit_id`.
3. El usuario modifica los campos y hace clic en "Guardar".
4. Aplican las mismas reglas de validación. El backend actualiza `updated_by` y `updated_at`.
5. La tabla paginada se refresca mostrando el registro actualizado.

### Editar sin cambios
1. El usuario hace clic en "Editar", no realiza cambios y hace clic en "Guardar".
2. El sistema actualiza `updated_by` y `updated_at` (no hay bloqueo de "sin cambios detectados").

### Eliminar (soft delete)
1. El usuario hace clic en "Eliminar" en una fila de producto.
2. El sistema muestra un diálogo de confirmación.
3. Si el usuario confirma Y el producto NO está referenciado en ningún lote con stock > 0 → se realiza soft delete (`deleted_at` poblado). El registro desaparece de la tabla.
4. Si el producto está referenciado en algún lote con stock > 0 → el sistema bloquea con el mensaje: "No se puede eliminar, el producto tiene stock activo en uno o más lotes".

### Estado vacío
- Cuando no existen productos, la tabla muestra: "No hay productos registrados".

### Estado de carga
- Mientras cualquier operación esté en progreso, mostrar un spinner de carga y deshabilitar el botón de envío para prevenir envíos duplicados.

### Paginación
- El backend retorna los productos paginados con metadatos (total, per_page, current_page, last_page). El frontend muestra los controles de paginación correspondientes.

## 8. Caminos Tristes (errores esperados)

| # | Escenario | Comportamiento del Sistema |
|---|---|---|
| SP-01 | El usuario envía con `name` vacío o solo espacios | El frontend resalta el campo como obligatorio, muestra el mensaje: "El campo nombre es obligatorio". El backend también valida. |
| SP-02 | El usuario envía con `cum` vacío o solo espacios | El frontend resalta el campo como obligatorio, muestra el mensaje: "El campo CUM es obligatorio". El backend también valida. |
| SP-03 | El usuario envía con `invima_registration` vacío o solo espacios | El frontend resalta el campo como obligatorio, muestra el mensaje: "El campo registro Invima es obligatorio". El backend también valida. |
| SP-04 | El usuario envía con `barcode` vacío | Permitido; el campo es opcional y se almacena como `null`. |
| SP-05 | `name` supera 120 caracteres | La entrada se bloquea a 120 caracteres en el frontend. El backend retorna error de validación 422 si de alguna forma se excede. |
| SP-06 | `cum` ya existe (insensible a mayúsculas/minúsculas) | El backend retorna HTTP 409 Conflict. El frontend muestra error visual: "El CUM ya existe". |
| SP-07 | `barcode` ya existe (solo si se ingresó un valor) | El backend retorna HTTP 409 Conflict. El frontend muestra error visual: "El código de barras ya existe". |
| SP-08 | `invima_registration` ya existe (insensible a mayúsculas/minúsculas) | El backend retorna HTTP 409 Conflict. El frontend muestra error visual: "El registro Invima ya existe". |
| SP-09 | `group_id` no seleccionado | El frontend resalta el campo y muestra: "Debe seleccionar un grupo". El backend también valida. |
| SP-10 | `unit_id` no seleccionado | El frontend resalta el campo y muestra: "Debe seleccionar una unidad de medida". El backend también valida. |
| SP-11 | Error de conexión a la base de datos al guardar | El frontend muestra: "No es posible establecer conexión con el servidor, intente nuevamente". |
| SP-12 | Otro usuario modificó o eliminó el producto mientras el usuario actual lo edita | El backend detecta que el registro ya no existe o cambió de estado. Retorna error. El frontend muestra: "No es posible realizar esta acción, recargue la página". |
| SP-13 | El usuario intenta eliminar un producto con lotes que tienen stock > 0 | El sistema bloquea con el mensaje: "No se puede eliminar, el producto tiene stock activo en uno o más lotes". |
| SP-14 | El usuario intenta eliminar un producto ya eliminado | Igual que SP-12: "No es posible realizar esta acción, recargue la página". |
| SP-15 | El usuario hace doble clic en "Guardar" | El botón de envío se deshabilita mientras la solicitud está en progreso (se muestra spinner de carga). |
| SP-16 | `name` ya existe (insensible a mayúsculas/minúsculas) | El backend retorna HTTP 409 Conflict. El frontend muestra error visual: "El nombre del producto ya existe". |
| SP-17 | Timeout de red durante la solicitud | El frontend muestra: "La solicitud excedió el tiempo de espera. Verifique su conexión e intente nuevamente." Timeout: 30 segundos. |
| SP-18 | `barcode` supera 60 caracteres | La entrada se bloquea a 60 caracteres en el frontend. El backend retorna 422 si se excede. |
| SP-19 | Sesión expirada durante operación | La API retorna 401. El frontend redirige al login con mensaje: "Su sesión ha expirado. Inicie sesión nuevamente." |

## 9. Casos Límite y Casos Estúpidos

### Casos Límite

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| CL-01 | `barcode` es nulo/vacío | Se muestra como celda vacía en la tabla (sin texto de marcador). |
| CL-02 | Trim en todos los campos de texto | El frontend y el backend recortan solo los espacios al inicio y al final (los espacios internos se conservan). La validación se ejecuta después del trim. |
| CL-03 | Caracteres especiales en `name` (tildes, ñ, guiones, paréntesis, números) | Permitidos (ej. "Paracetamol 500mg – Solución Oral (Niños)"). |
| CL-04 | Unicidad insensible a mayúsculas/minúsculas en `cum`, `barcode` e `invima_registration` | Cualquier duplicado, aunque difiera en mayúsculas, retorna 409 del backend. |
| CL-05 | Dos usuarios crean un producto con el mismo `cum` simultáneamente | Ambos pasan la validación del frontend. El primero tiene éxito. El segundo recibe 409 del backend: "El CUM ya existe". |
| CL-06 | El usuario abre la edición del mismo producto en varias pestañas y guarda cambios diferentes | El primer guardado tiene éxito y actualiza la BD. Los guardados posteriores reciben error: "No es posible realizar esta acción, recargue la página". |
| CL-07 | Pegar JSON, HTML o scripts en cualquier campo de texto | name: rechazado si contiene HTML/scripts. cum: rechazado si contiene HTML/scripts. barcode: rechazado si contiene HTML/scripts. description: sanitizado (HTML escapado). |
| CL-08 | Solo emojis en campos de texto | El frontend restringe la entrada; los emojis no son aceptados como valor único. |
| CL-09 | `barcode` compuesto solo por espacios | Después del trim se trata como vacío → se almacena como `null`. Permitido. |
| CL-10 | No existen productos | La tabla muestra: "No hay productos registrados". |
| CL-11 | El grupo asignado fue inactivado o eliminado después de vincularlo al producto | El select de grupos solo muestra grupos activos; si ya está asignado, se muestra el valor actual pero no se puede reasignar al mismo grupo inactivo. |

### Casos Estúpidos

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| CE-01 | Enviar `name` compuesto únicamente por espacios | Después del trim se trata como vacío → mismo comportamiento que SP-01. |
| CE-02 | Escribir `<script>alert('xss')</script>` en `name` | Rechazado o sanitizado; solo se aceptan caracteres alfanuméricos permitidos. |
| CE-03 | Enviar un `group_id` o `unit_id` inexistente manipulando la petición | El backend valida que el ID exista y retorna 422 si no es válido. |
| CE-04 | Interceptar la respuesta del backend y reenviar el mismo payload de creación varias veces | Cada envío se valida por unicidad; los envíos posteriores reciben 409. |
| CE-05 | Hacer clic repetido en "Eliminar" y confirmar varias veces | Solo la primera solicitud procesa el soft delete; las siguientes reciben SP-12. |
| CE-06 | Pegar un valor de `cum` o `invima_registration` con 60 espacios al inicio y 60 caracteres válidos | Después del trim el valor excede 60 caracteres → backend retorna 422. |
| CE-07 | Modificar el campo `created_by` desde una herramienta de desarrollo al enviar el formulario | El backend ignora cualquier `created_by` enviado y lo toma de la sesión autenticada. |
| CE-08 | Navegar directamente a la página 9999 cuando solo hay 3 páginas | El backend retorna lista vacía o última página válida según paginador de Laravel. |

## 10. Criterios de Aceptación

### CA-01: Crear Producto (Camino Feliz)
**Dado** que el usuario está en la pantalla de Productos  
**Cuando** el usuario completa `name`, `cum`, `invima_registration`, selecciona un `group_id` activo y un `unit_id` válidos, deja `barcode` vacío y hace clic en "Guardar"  
**Entonces** el producto se crea con `created_at`=hora actual del servidor, `created_by`=usuario autenticado y `barcode`=null  PASS / FAIL  
**Y** se muestra la respuesta de éxito  PASS / FAIL  
**Y** la tabla inferior se refresca mostrando el nuevo producto  PASS / FAIL  
**Y** el formulario se reinicia a vacío  PASS / FAIL

### CA-02: Listar Productos con Paginación
**Dado** que existen más de 15 productos en el sistema  
**Cuando** el usuario navega a la pantalla de Productos  
**Entonces** la tabla muestra la primera página con 15 productos  PASS / FAIL  
**Y** se muestran los controles de paginación con metadatos (total, per_page, last_page)  PASS / FAIL

### CA-03: Lista Vacía
**Dado** que no existen productos en el sistema  
**Cuando** el usuario navega a la pantalla de Productos  
**Entonces** la tabla muestra: "No hay productos registrados"  PASS / FAIL

### CA-04: Editar Producto
**Dado** que existe un producto  
**Cuando** el usuario hace clic en "Editar" en la fila de ese producto  
**Entonces** el formulario superior se precarga con los datos actuales del producto  PASS / FAIL  
**Y** el usuario puede modificar y guardar, lo que actualiza el registro con `updated_by` y `updated_at`  PASS / FAIL  
**Y** la tabla se refresca mostrando los datos actualizados  PASS / FAIL

### CA-05: Eliminar Producto sin Stock Activo
**Dado** que existe un producto y NO tiene lotes con stock > 0  
**Cuando** el usuario hace clic en "Eliminar" y confirma el diálogo  
**Entonces** el producto se elimina mediante soft delete (`deleted_at` poblado)  PASS / FAIL  
**Y** el registro desaparece de la tabla  PASS / FAIL

### CA-06: Bloquear Eliminación con Stock Activo
**Dado** que existe un producto y TIENE al menos un lote con stock > 0  
**Cuando** el usuario hace clic en "Eliminar"  
**Entonces** el sistema bloquea la acción  PASS / FAIL  
**Y** muestra: "No se puede eliminar, el producto tiene stock activo en uno o más lotes"  PASS / FAIL

### CA-07: Validar Nombre Obligatorio
**Dado** que el usuario está completando el formulario de producto  
**Cuando** el usuario envía con `name` vacío o solo espacios (después de trim)  
**Entonces** el frontend resalta el campo  PASS / FAIL  
**Y** muestra: "El campo nombre es obligatorio"  PASS / FAIL  
**Y** el registro NO se guarda  PASS / FAIL

### CA-08: Validar CUM Obligatorio
**Dado** que el usuario está completando el formulario de producto  
**Cuando** el usuario envía con `cum` vacío o solo espacios  
**Entonces** el frontend resalta el campo  PASS / FAIL  
**Y** muestra: "El campo CUM es obligatorio"  PASS / FAIL  
**Y** el registro NO se guarda  PASS / FAIL

### CA-09: Validar Registro Invima Obligatorio
**Dado** que el usuario está completando el formulario de producto  
**Cuando** el usuario envía con `invima_registration` vacío o solo espacios  
**Entonces** el frontend resalta el campo  PASS / FAIL  
**Y** muestra: "El campo registro Invima es obligatorio"  PASS / FAIL  
**Y** el registro NO se guarda  PASS / FAIL

### CA-10: Validar Grupo Obligatorio
**Dado** que el usuario está completando el formulario de producto  
**Cuando** el usuario envía sin seleccionar `group_id`  
**Entonces** el frontend resalta el campo  PASS / FAIL  
**Y** muestra: "Debe seleccionar un grupo"  PASS / FAIL  
**Y** el registro NO se guarda  PASS / FAIL

### CA-11: Validar Unidad de Medida Obligatoria
**Dado** que el usuario está completando el formulario de producto  
**Cuando** el usuario envía sin seleccionar `unit_id`  
**Entonces** el frontend resalta el campo  PASS / FAIL  
**Y** muestra: "Debe seleccionar una unidad de medida"  PASS / FAIL  
**Y** el registro NO se guarda  PASS / FAIL

### CA-12: Validar Barcode Opcional
**Dado** que el usuario está completando el formulario de producto  
**Cuando** el usuario envía con `barcode` vacío o solo espacios  
**Entonces** el registro se crea exitosamente  PASS / FAIL  
**Y** `barcode` se almacena como `null`  PASS / FAIL

### CA-13: Validar CUM Duplicado
**Dado** que ya existe un producto con `cum` "ABC123"  
**Cuando** el usuario intenta crear o editar un producto con `cum` "abc123" (o "ABC123")  
**Entonces** el backend retorna HTTP 409  PASS / FAIL  
**Y** el frontend muestra: "El CUM ya existe"  PASS / FAIL

### CA-14: Validar Barcode Duplicado
**Dado** que ya existe un producto con `barcode` "7701234567890"  
**Cuando** el usuario intenta crear o editar un producto con `barcode` "7701234567890"  
**Entonces** el backend retorna HTTP 409  PASS / FAIL  
**Y** el frontend muestra: "El código de barras ya existe"  PASS / FAIL

### CA-15: Validar Invima Duplicado
**Dado** que ya existe un producto con `invima_registration` "INVIMA-2024-0001"  
**Cuando** el usuario intenta crear o editar un producto con `invima_registration` "invima-2024-0001"  
**Entonces** el backend retorna HTTP 409  PASS / FAIL  
**Y** el frontend muestra: "El registro Invima ya existe"  PASS / FAIL

### CA-16: Validar Longitud del Nombre
**Dado** que el usuario está completando el formulario de producto  
**Cuando** el usuario escribe más de 120 caracteres en `name`  
**Entonces** la entrada bloquea más caracteres en 120  PASS / FAIL  
**Y** si una solicitud evade la validación del frontend, el backend retorna HTTP 422  PASS / FAIL

### CA-17: Validar Longitud del CUM
**Dado** que el usuario está completando el formulario de producto  
**Cuando** el usuario escribe más de 60 caracteres en `cum`  
**Entonces** la entrada bloquea más caracteres en 60  PASS / FAIL  
**Y** si una solicitud evade la validación del frontend, el backend retorna HTTP 422  PASS / FAIL

### CA-18: Error de Conexión a la Base de Datos
**Dado** que el usuario envía un formulario válido  
**Cuando** la base de datos no es accesible  
**Entonces** el frontend muestra: "No es posible establecer conexión con el servidor, intente nuevamente"  PASS / FAIL

### CA-19: Modificación Concurrente
**Dado** que el Usuario A está editando el producto X en la Pestaña 1 y el Usuario B elimina el producto X en la Pestaña 2 (Usuario A y Usuario B pueden ser el mismo usuario en diferentes pestañas, o usuarios diferentes; el comportamiento es idéntico en ambos casos)  
**Cuando** el Usuario A hace clic en "Guardar" después de que la acción del Usuario B se completó  
**Entonces** el backend detecta que el estado del registro ha cambiado  PASS / FAIL  
**Y** el frontend muestra: "No es posible realizar esta acción, recargue la página"  PASS / FAIL

### CA-20: Prevención de Doble Envío
**Dado** que el usuario hace clic en "Guardar"  
**Cuando** la solicitud está en progreso  
**Entonces** el botón de envío se deshabilita y se muestra un spinner de carga  PASS / FAIL  
**Y** el usuario no puede disparar un segundo envío  PASS / FAIL

### CA-21: Sanitizar Entrada Maliciosa
**Dado** que el usuario pega contenido HTML, JSON o script en `name`, `cum`, `barcode` o `invima_registration`  
**Entonces** `name`, `cum`, `barcode` e `invima_registration` son rechazados si contienen HTML o scripts  PASS / FAIL  
**Y** `description` es sanitizado (HTML escapado)  PASS / FAIL

### CA-22: Editar sin Cambios
**Dado** que el usuario hace clic en "Editar" en un producto y no realiza modificaciones  
**Cuando** el usuario hace clic en "Guardar"  
**Entonces** los campos `updated_by` y `updated_at` del registro se actualizan  PASS / FAIL

### CA-23: Recortar Entrada
**Dado** que el usuario escribe "  Paracetamol 500mg  " como `name`  
**Cuando** se envía el formulario  
**Entonces** el valor se recorta a "Paracetamol 500mg" antes de la validación y persistencia  PASS / FAIL  
**Y** los espacios internos se conservan  PASS / FAIL

### CA-24: Caracteres Especiales Permitidos
**Dado** que el usuario escribe "Ibuprofeno 400mg – Suspensión Oral (Pediátrica)" como `name`  
**Entonces** el nombre es aceptado y guardado  PASS / FAIL

### CA-25: Confirmación Requerida para Eliminar
**Dado** que el usuario hace clic en "Eliminar" en un producto  
**Cuando** aparece el diálogo de confirmación  
**Entonces** la eliminación solo procede si el usuario confirma explícitamente  PASS / FAIL  
**Y** si el usuario cancela, no ocurre nada  PASS / FAIL

### CA-26: Detección de Producto Eliminado
**Dado** que un producto fue eliminado mediante soft delete por otro usuario  
**Cuando** el usuario actual intenta realizar cualquier acción sobre él (editar o eliminar)  
**Entonces** el sistema retorna un error  PASS / FAIL  
**Y** el frontend muestra: "No es posible realizar esta acción, recargue la página"  PASS / FAIL

### CA-27: Campos de Auditoría al Crear
**Dado** que se crea un nuevo producto  
**Cuando** el registro persiste  
**Entonces** `created_by` se asigna al usuario de la sesión autenticada  PASS / FAIL  
**Y** `created_at` se asigna automáticamente por Eloquent  PASS / FAIL  
**Y** `updated_by` es nulo  PASS / FAIL  
**Y** `updated_at` es nulo  PASS / FAIL

### CA-28: Campos de Auditoría al Editar
**Dado** que se edita un producto existente  
**Cuando** el registro se actualiza  
**Entonces** `updated_by` se asigna al usuario de la sesión autenticada  PASS / FAIL  
**Y** `updated_at` se asigna automáticamente por Eloquent  PASS / FAIL  
**Y** `created_by` y `created_at` permanecen sin cambios  PASS / FAIL

### CA-29: Tabla Units Creada por Migración
**Dado** que se ejecutan las migraciones de esta HU  
**Cuando** se verifica el esquema de la base de datos  
**Entonces** existe la tabla `units` con las columnas `id`, `name`, `description`, `created_at` y `updated_at`  PASS / FAIL

### CA-30: Seeder de Units Puebla Datos
**Dado** que se ejecutan los seeders en cualquier entorno  
**Cuando** se consulta la tabla `units`  
**Entonces** existen al menos las siguientes unidades base: Unidad, Tableta, Cápsula, Mililitro, Gramo, Frasco, Ampolla, Sobre  PASS / FAIL

### CA-31: Select de Grupos Muestra Solo Activos
**Dado** que existen grupos activos e inactivos en el sistema  
**Cuando** el usuario abre el select de `group_id` en el formulario de producto  
**Entonces** solo se muestran los grupos con `status=true`  PASS / FAIL

### CA-32: Barcode Nulo cuando No se Proporciona
**Dado** que el usuario crea un producto sin completar `barcode`  
**Cuando** se consulta el registro en la base de datos  
**Entonces** el campo `barcode` es `null`  PASS / FAIL

### CA-33: Validar Nombre Duplicado
**Dado** que ya existe un producto con `name` "Paracetamol 500mg"  
**Cuando** el usuario intenta crear o editar un producto con `name` "paracetamol 500mg" (o "PARACETAMOL 500MG")  
**Entonces** el backend retorna HTTP 409  PASS / FAIL  
**Y** el frontend muestra: "El nombre del producto ya existe"  PASS / FAIL

### CA-34: Validar Longitud del Código de Barras
**Dado** que el usuario está completando el formulario de producto  
**Cuando** el usuario escribe más de 60 caracteres en `barcode`  
**Entonces** la entrada bloquea más caracteres en 60  PASS / FAIL  
**Y** si una solicitud evade la validación del frontend, el backend retorna HTTP 422  PASS / FAIL
