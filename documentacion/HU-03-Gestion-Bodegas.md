# HU-03 – Gestión de Bodegas

## 1. Título y Descripción

**Título:** HU-03 – Gestión de Bodegas

**Descripción:** Permitir al usuario gestionar bodegas farmacéuticas mediante un CRUD completo (crear, listar, editar, activar/inactivar, eliminar con soft delete). La pantalla contiene un formulario en la parte superior y una tabla de bodegas en la parte inferior.

## 2. Modelo de Datos

Tabla: `bodegas`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por el sistema (lado del servidor/BD) |
| name | varchar(60) | UNIQUE, NOT NULL, unicidad insensible a mayúsculas/minúsculas |
| description | text | permite nulo, máximo 300 caracteres |
| status | boolean | NOT NULL, valor por defecto true (true=Activo, false=Inactivo) |
| created_by | uuid | FK → users (id), NOT NULL, onDelete definido |
| updated_by | uuid | FK → users (id), permite nulo |
| created_at | timestamp | NOT NULL, asignado automáticamente por Eloquent |
| updated_at | timestamp | permite nulo, asignado automáticamente por Eloquent |
| deleted_at | timestamp | permite nulo (SoftDeletes) |

Todos los nombres de columna están en inglés (compatibles con Eloquent: `created_by` para belongsTo, `created_at`/`updated_at` para timestamps, `deleted_at` para SoftDeletes).

| FK | onDelete |
|---|---|
| `created_by` → users (id) | RESTRICT |
| `updated_by` → users (id) | SET NULL |

Esta regla sigue la política global de onDelete documentada en AI_USAGE.md.

## 3. Bloqueos y Dependencias

- Debe existir la tabla `users` (estructura estándar de Laravel 9).
- Debe estar definido el diseño UI/UX del formulario (campos: `name`, `description`).
- Esta HU es independiente; NO depende de HU-04, HU-05 ni HU-06.
- Esta HU es un prerrequisito para HU-01 (Productos) — debe existir una bodega antes de que se puedan asignar productos.
- La regla de bloqueo "no se puede inactivar/eliminar si tiene productos activos" requiere que la tabla `lots` exista. Si HU-05 aún no está implementada, esta validación se omite (no-op) hasta que la tabla `lots` esté disponible.

## 4. Variables y Configuración

- Sin restricciones de rol o permisos — todos los usuarios pueden realizar todas las acciones.
- Sin feature toggles ni comportamiento específico por tenant.
- Sin comportamiento específico por entorno; los seeders pueden ejecutarse en todos los entornos.
- Bodegas ilimitadas (en el futuro una tabla de configuración global podría agregar límites, fuera de alcance por ahora).
- El backend retorna todas las bodegas (se esperan pocos registros); el frontend maneja filtrado, búsqueda y paginación del lado del cliente.

## 5. Suposiciones

1. La tabla `users` existe con la estructura estándar de Laravel 9 (`id`, `name`, `email`, `password`, `timestamps`).
2. El diseño UI del formulario de bodega incluye exactamente los campos solicitados: `name` y `description`.
3. Los seeders pueden ejecutarse en cualquier entorno (dev, staging, production).
4. No existen restricciones de infraestructura por ahora; si se requiere, limitar a 100 bodegas mediante configuración global (fuera de alcance).
5. `created_at` se asigna automáticamente por Eloquent al crear el registro.
6. El UUID `id` es generado automáticamente por el sistema (lado del servidor/BD), no se proporciona externamente.
7. La unicidad de `name` se valida tanto en el frontend (antes del envío) como en el backend (antes de la persistencia).
8. `created_by` se obtiene del usuario de la sesión autenticada y no puede elegirse manualmente en el formulario.
9. La existencia de "productos activos asignados" a una bodega se determina mediante: existe al menos un registro en `lots` con `warehouse_id` = id de la bodega, `status` = true y `deleted_at` IS NULL.
10. El usuario debe estar autenticado para acceder a la pantalla de Bodegas. El acceso no autenticado redirige al login (protegido por middleware de autenticación de Laravel).

## 6. Fuera de Alcance (explícitamente excluido)

- Gestión de sensores (pertenece a HU-04).
- Asignación de productos a bodegas (pertenece a HU-01 Productos).
- Dashboard/reportes de stock por bodega (pertenece a HU-01/HU-02).
- Notificaciones por correo electrónico — solo confirmación visual en el frontend.
- Migración de datos legados.
- Tabla de configuración global para límites del sistema (HU futura, no esta).
- Gestión de roles y permisos.
- Filtrado y paginación del lado del backend (solo frontend para esta HU).
- Restauración de bodegas eliminadas (soft-deleted) — no hay operación de restore en esta HU.

## 7. Camino Feliz Principal (paso a paso)

### Listar y Crear
1. El usuario navega a la pantalla "Bodegas".
2. El sistema muestra un formulario de registro en la parte superior con los campos: `name` (entrada de texto, permite: letras (incluyendo acentos y ñ), dígitos, espacios, guiones, paréntesis y símbolos de grado. No permite HTML, scripts, ni emojis.) y `description` (área de texto, máximo 300 caracteres).
3. Debajo del formulario, una tabla lista todas las bodegas existientes con las columnas: Nombre, Descripción, Estado, Fecha Registro. Cada fila tiene botones de acción: Editar, Switch Activar/Inactivar, Eliminar.
4. El usuario completa el formulario y hace clic en "Guardar".
5. El frontend valida: `name` es obligatorio, no vacío después de trim, máximo 60 caracteres (entrada bloqueada a 60). `description` es opcional, máximo 300 caracteres.
6. El backend valida: unicidad de `name` (insensible a mayúsculas/minúsculas), elimina espacios al inicio y al final. Si es válido, crea el registro con `created_by` de la sesión, `created_at` = hora del servidor, `status` = true.
7. El sistema retorna respuesta de éxito. La tabla se refresca mostrando el nuevo registro. El formulario se reinicia.

### Editar
1. El usuario hace clic en "Editar" en una fila de bodega.
2. El formulario superior se precarga con el `name` y `description` de esa bodega.
3. El usuario modifica los campos y hace clic en "Guardar".
4. Aplican las mismas reglas de validación. El backend actualiza `updated_by` y `updated_at`.
5. La tabla se refresca mostrando el registro actualizado.

### Editar sin cambios
1. El usuario hace clic en "Editar", no realiza cambios y hace clic en "Guardar".
2. El sistema actualiza `updated_by` y `updated_at` (no hay bloqueo de "sin cambios detectados").

### Activar/Inactivar
1. El usuario alterna el switch en una fila de bodega.
2. Si la bodega tiene productos activos asignados → el sistema bloquea la acción con el mensaje: "No se puede inactivar, tiene productos activos".
3. Si no tiene productos activos → se alterna y guarda el `status`. La tabla se refresca.

### Eliminar (soft delete)
1. El usuario hace clic en "Eliminar" en una fila de bodega.
2. El sistema muestra un diálogo de confirmación.
3. Si confirma Y la bodega no tiene productos activos → soft delete (`deleted_at` poblado, `status` establecido a inactivo). El registro desaparece de la tabla.
4. Si la bodega tiene productos activos → el sistema bloquea con el mensaje: "No se puede eliminar, tiene productos activos".

### Estado vacío
- Cuando no existen bodegas, la tabla muestra: "No hay bodegas registradas".

### Estado de carga
- Mientras cualquier operación esté en progreso, mostrar un spinner de carga y deshabilitar el botón de envío para prevenir envíos duplicados.

## 8. Caminos Tristes (errores esperados)

| # | Escenario | Comportamiento del Sistema |
|---|---|---|
| SP-01 | El usuario envía con `name` vacío o solo espacios | El frontend resalta el campo como obligatorio, muestra el mensaje: "El campo nombre es obligatorio". El backend también valida. |
| SP-02 | `name` excede 60 caracteres | La entrada se bloquea a 60 caracteres en el frontend. El backend retorna error de validación 422 si de alguna forma se excede. |
| SP-03 | `name` ya existe (insensible a mayúsculas/minúsculas) | El backend retorna HTTP 409 Conflict. El frontend muestra error visual: "El nombre de bodega ya existe". |
| SP-04 | Error de conexión a la base de datos al guardar | El frontend muestra: "No es posible establecer conexión con el servidor, intente nuevamente". |
| SP-05 | El usuario edita y borra `name` | Mismo comportamiento que SP-01. |
| SP-06 | El usuario edita `name` a uno que ya existe | Mismo comportamiento que SP-03. |
| SP-07 | Otro usuario eliminó/inactivó la bodega mientras el usuario actual la edita | El backend detecta que el registro ya no existe o cambió de estado. Retorna error. El frontend muestra: "No es posible realizar esta acción, recargue la página". |
| SP-08 | El usuario intenta eliminar una bodega ya eliminada | Igual que SP-07: "No es posible realizar esta acción, recargue la página". |
| SP-09 | El usuario intenta inactivar una bodega ya inactivada | Igual que SP-07: "No es posible realizar esta acción, recargue la página". |
| SP-10 | El usuario intenta inactivar una bodega que tiene productos activos | El sistema bloquea: "No se puede inactivar, tiene productos activos". Esta validación es condicional: solo se ejecuta si la tabla `lots` existe. |
| SP-11 | El usuario intenta eliminar una bodega que tiene productos activos | El sistema bloquea: "No se puede eliminar, tiene productos activos". Esta validación es condicional: solo se ejecuta si la tabla `lots` existe. |
| SP-12 | `description` excede 300 caracteres | La entrada se bloquea a 300 caracteres en el frontend. El backend retorna error de validación 422 si se excede. |

## 9. Casos Límite y Casos Estúpidos

### Casos Límite

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| CL-01 | `description` es nulo/vacío | Se muestra como celda vacía en la tabla (sin texto de marcador). |
| CL-02 | Nombre con espacios al inicio/final (ej. "  Bodega Norte  ") | El frontend y el backend recortan solo los espacios al inicio y al final (los espacios internos se conservan). La validación se ejecuta después del trim. |
| CL-03 | Nombre con caracteres acentuados, ñ, guiones, números, paréntesis | Permitido (ej. "Bodega N°3 – Medicamentos Controlados"). |
| CL-04 | Nombres que difieren solo por mayúsculas/minúsculas (ej. "Central" vs "CENTRAL") | Tratados como duplicados (insensible a mayúsculas). El backend retorna 409. |
| CL-05 | Dos usuarios crean una bodega con el mismo nombre simultáneamente | Ambos pasan la validación del frontend. El primero tiene éxito. El segundo recibe 409 del backend: "El nombre de bodega ya existe". |
| CL-06 | El usuario abre la edición de la misma bodega en varias pestañas y guarda cambios diferentes | El primer guardado tiene éxito y actualiza la BD. Los guardados posteriores reciben error: "No es posible realizar esta acción, recargue la página". |
| CL-07 | No existen bodegas | La tabla muestra: "No hay bodegas registradas". |

### Casos Estúpidos

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| CE-01 | El usuario pega JSON, HTML o scripts en `name` o `description` | Se rechaza/sanitiza. Se permite el conjunto de caracteres definido para `name`; HTML y scripts son rechazados. Para `description`: se almacena como texto plano; el backend escapa/sanitiza HTML para prevenir XSS. |
| CE-02 | El usuario escribe solo emojis como `name` | El frontend restringe a entrada alfanumérica; los emojis no son aceptados. |
| CE-03 | El usuario envía `name` compuesto solo por espacios | Después del trim se trata como vacío → mismo comportamiento que SP-01. |
| CE-04 | Doble clic rápido en "Guardar" | El botón de envío se deshabilita mientras la solicitud está en progreso (se muestra spinner de carga). |

## 10. Criterios de Aceptación

### CA-01: Crear Bodega (Camino Feliz)
**Dado** que el usuario está en la pantalla de Bodegas  
**Cuando** el usuario completa un `name` válido y único (1–60 caracteres, alfanumérico, recortado) y una `description` opcional (0–300 caracteres), luego hace clic en "Guardar"  
**Entonces** la bodega se crea con `status=true`, `created_at`=hora actual del servidor, `created_by`=usuario autenticado   PASS / FAIL  
**Y** se muestra la respuesta de éxito   PASS / FAIL  
**Y** la tabla inferior se refresca mostrando la nueva bodega   PASS / FAIL  
**Y** el formulario se reinicia a vacío   PASS / FAIL

### CA-02: Listar Bodegas (Camino Feliz)
**Dado** que existen bodegas en el sistema  
**Cuando** el usuario navega a la pantalla de Bodegas  
**Entonces** la tabla muestra todas las bodegas no eliminadas (excluyendo registros con soft-delete) con las columnas: Nombre, Descripción, Estado, Fecha Registro   PASS / FAIL  
**Y** cada fila muestra los botones de acción: Editar, Switch Activar/Inactivar, Eliminar   PASS / FAIL

### CA-03: Lista Vacía
**Dado** que no existen bodegas en el sistema  
**Cuando** el usuario navega a la pantalla de Bodegas  
**Entonces** la tabla muestra: "No hay bodegas registradas"   PASS / FAIL

### CA-04: Editar Bodega (Camino Feliz)
**Dado** que existe una bodega  
**Cuando** el usuario hace clic en "Editar" en la fila de esa bodega  
**Entonces** el formulario superior se precarga con el `name` y `description` actuales de la bodega   PASS / FAIL  
**Y** el usuario puede modificar y guardar, lo que actualiza el registro con `updated_by` y `updated_at`   PASS / FAIL  
**Y** la tabla se refresca mostrando los datos actualizados   PASS / FAIL

### CA-05: Activar/Inactivar Bodega sin Productos
**Dado** que existe una bodega y NO tiene productos activos asignados  
**Cuando** el usuario alterna el switch Activar/Inactivar  
**Entonces** el campo `status` se alterna y persiste   PASS / FAIL  
**Y** la tabla se refresca   PASS / FAIL

### CA-06: Bloquear Inactivación con Productos Activos
**Dado** que existe una bodega y TIENE productos activos asignados  
**Cuando** el usuario intenta alternar el switch para inactivar  
**Entonces** el sistema bloquea la acción   PASS / FAIL  
**Y** muestra: "No se puede inactivar, tiene productos activos"   PASS / FAIL

### CA-07: Eliminar Bodega sin Productos
**Dado** que existe una bodega y NO tiene productos activos asignados  
**Cuando** el usuario hace clic en "Eliminar" y confirma el diálogo  
**Entonces** la bodega se elimina mediante soft delete (`deleted_at` poblado, `status` establecido a false)   PASS / FAIL  
**Y** el registro desaparece de la tabla   PASS / FAIL

### CA-08: Bloquear Eliminación con Productos Activos
**Dado** que existe una bodega y TIENE productos activos asignados  
**Cuando** el usuario hace clic en "Eliminar"  
**Entonces** el sistema bloquea la acción   PASS / FAIL  
**Y** muestra: "No se puede eliminar, tiene productos activos"   PASS / FAIL

### CA-09: Validar Nombre Obligatorio
**Dado** que el usuario está completando el formulario de bodega  
**Cuando** el usuario envía con `name` vacío o solo espacios (después de trim)  
**Entonces** el frontend resalta el campo   PASS / FAIL  
**Y** muestra: "El campo nombre es obligatorio"   PASS / FAIL  
**Y** el registro NO se guarda   PASS / FAIL

### CA-10: Validar Nombre Duplicado
**Dado** que ya existe una bodega llamada "Central"  
**Cuando** el usuario intenta crear o editar una bodega con `name` "central" (o "CENTRAL")  
**Entonces** el backend retorna HTTP 409   PASS / FAIL  
**Y** el frontend muestra: "El nombre de bodega ya existe"   PASS / FAIL

### CA-11: Validar Longitud del Nombre
**Dado** que el usuario está completando el formulario de bodega  
**Cuando** el usuario escribe más de 60 caracteres en `name`  
**Entonces** la entrada bloquea más caracteres en 60   PASS / FAIL  
**Y** si una solicitud evade la validación del frontend, el backend retorna HTTP 422   PASS / FAIL

### CA-12: Validar Longitud de la Descripción
**Dado** que el usuario está completando el formulario de bodega  
**Cuando** el usuario escribe más de 300 caracteres en `description`  
**Entonces** la entrada bloquea más caracteres en 300   PASS / FAIL  
**Y** si una solicitud evade la validación del frontend, el backend retorna HTTP 422   PASS / FAIL

### CA-13: Error de Conexión a la Base de Datos
**Dado** que el usuario envía un formulario válido  
**Cuando** la base de datos no es accesible  
**Entonces** el frontend muestra: "No es posible establecer conexión con el servidor, intente nuevamente"   PASS / FAIL

### CA-14: Modificación Concurrente
**Dado** que el Usuario A está editando la bodega X en la Pestaña 1 y el Usuario B elimina/inactiva la bodega X en la Pestaña 2  
**Cuando** el Usuario A hace clic en "Guardar" después de que la acción del Usuario B se completó  
**Entonces** el backend detecta que el estado del registro ha cambiado   PASS / FAIL  
**Y** el frontend muestra: "No es posible realizar esta acción, recargue la página"   PASS / FAIL

### CA-15: Prevención de Doble Envío
**Dado** que el usuario hace clic en "Guardar"  
**Cuando** la solicitud está en progreso  
**Entonces** el botón de envío se deshabilita y se muestra un spinner de carga   PASS / FAIL  
**Y** el usuario no puede disparar un segundo envío   PASS / FAIL

### CA-16: Sanitizar Entrada Maliciosa
**Dado** que el usuario pega contenido HTML, JSON o script en `name` o `description`  
**Entonces** la entrada es rechazada o sanitizada (se aceptan caracteres del conjunto definido para `name`; para `description` se escapan/sanitizan tags HTML)   PASS / FAIL

### CA-17: Editar sin Cambios
**Dado** que el usuario hace clic en "Editar" en una bodega y no realiza modificaciones  
**Cuando** el usuario hace clic en "Guardar"  
**Entonces** los campos `updated_by` y `updated_at` del registro se actualizan   PASS / FAIL

### CA-18: Recortar Entrada
**Dado** que el usuario escribe "  Bodega Norte  " como `name`  
**Cuando** se envía el formulario  
**Entonces** el valor se recorta a "Bodega Norte" antes de la validación y persistencia   PASS / FAIL  
**Y** los espacios internos se conservan   PASS / FAIL

### CA-19: Caracteres Especiales Permitidos
**Dado** que el usuario escribe "Bodega N°3 – Medicamentos Controlados (Cadena Frío)" como `name`  
**Entonces** el nombre es aceptado y guardado   PASS / FAIL

### CA-20: Confirmación Requerida para Eliminar
**Dado** que el usuario hace clic en "Eliminar" en una bodega  
**Cuando** aparece el diálogo de confirmación  
**Entonces** la eliminación solo procede si el usuario confirma explícitamente   PASS / FAIL  
**Y** si el usuario cancela, no ocurre nada   PASS / FAIL

### CA-21: Detección de Bodega Eliminada/Inactivada
**Dado** que una bodega fue eliminada mediante soft delete o inactivada por otro usuario  
**Cuando** el usuario actual intenta realizar cualquier acción sobre ella (editar, eliminar, alternar)  
**Entonces** el sistema retorna un error   PASS / FAIL  
**Y** el frontend muestra: "No es posible realizar esta acción, recargue la página"   PASS / FAIL

### CA-22: Campos de Auditoría al Crear
**Dado** que se crea una nueva bodega  
**Cuando** el registro persiste  
**Entonces** `created_by` se asigna al usuario de la sesión autenticada   PASS / FAIL  
**Y** `created_at` se asigna automáticamente por Eloquent al crear el registro   PASS / FAIL  
**Y** `updated_by` es nulo   PASS / FAIL  
**Y** `updated_at` es nulo   PASS / FAIL

### CA-23: Campos de Auditoría al Editar
**Dado** que se edita una bodega existente  
**Cuando** el registro se actualiza  
**Entonces** `updated_by` se asigna al usuario de la sesión autenticada   PASS / FAIL  
**Y** `updated_at` se asigna automáticamente por Eloquent al actualizar el registro   PASS / FAIL  
**Y** `created_by` y `created_at` permanecen sin cambios   PASS / FAIL
