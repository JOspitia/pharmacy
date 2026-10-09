# HU-04 – Monitoreo de Temperatura y Humedad

## 1. Título y Descripción

**Título:** HU-04 – Monitoreo de Temperatura y Humedad

**Descripción:** Permitir registrar y administrar sensores ambientales asociados a una o más bodegas, generar lecturas automáticas de temperatura y humedad, derivar alertas a partir de umbrales fijos y consultar el historial de lecturas por bodega a través de un endpoint API. El CRUD de sensores se accede desde la pantalla de Bodegas (HU-03) en una sección o pestaña de "Sensores".

## 2. Modelo de Datos

Tabla: `sensors`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por el sistema (lado del servidor/BD) |
| name | varchar(60) | UNIQUE, NOT NULL, unicidad insensible a mayúsculas/minúsculas |
| description | text | permite nulo, máximo 300 caracteres |
| status | boolean | NOT NULL, valor por defecto true (true=Activo, false=Inactivo) |
| created_by | uuid | FK → users (id), NOT NULL, onDelete RESTRICT |
| updated_by | uuid | FK → users (id), permite nulo, onDelete SET NULL |
| created_at | timestamp | NOT NULL, asignado automáticamente por Eloquent |
| updated_at | timestamp | permite nulo, asignado automáticamente por Eloquent |
| deleted_at | timestamp | permite nulo (SoftDeletes) |

Nota: `created_by` → users: `onDelete RESTRICT`; `updated_by` → users: `onDelete SET NULL`. Sigue la política global de onDelete documentada en AI_USAGE.md.

Tabla: `sensor_warehouse`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por el sistema (lado del servidor/BD) |
| sensor_id | uuid | FK → sensors (id), NOT NULL, onDelete CASCADE |
| warehouse_id | uuid | FK → bodegas (id), NOT NULL, onDelete RESTRICT |
| created_at | timestamp | NOT NULL, asignado automáticamente por Eloquent |

Restricción UNIQUE compuesta: (`sensor_id`, `warehouse_id`).

Tabla: `readings`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por el sistema (lado del servidor/BD) |
| sensor_warehouse_id | uuid | FK → sensor_warehouse (id), NOT NULL, onDelete CASCADE |
| temperature | decimal(5,2) | NOT NULL |
| humidity | decimal(5,2) | NOT NULL |
| recorded_at | timestamp | NOT NULL |

Tabla: `alerts`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | uuid | PK, autogenerado por el sistema (lado del servidor/BD) |
| reading_id | uuid | FK → readings (id), NOT NULL, onDelete CASCADE |
| type | varchar(20) | NOT NULL (valores: 'temperature', 'humidity', 'both') |
| message | text | NOT NULL |
| created_at | timestamp | NOT NULL, asignado automáticamente por Eloquent |

Todos los nombres de columna están en inglés (compatibles con Eloquent: `created_by`/`updated_by` para belongsTo, `created_at`/`updated_at` para timestamps, `deleted_at` para SoftDeletes). Las relaciones FK tienen `onDelete` correctamente definido. Las lecturas (`readings`) son inmutables: solo se consultan, nunca se crean, editan ni eliminan manualmente. Las alertas (`alerts`) también son inmutables y derivadas de las lecturas.

## 3. Bloqueos y Dependencias

- Debe existir la tabla `users` (estructura estándar de Laravel 9).
- Debe existir la HU-03 (Gestión de Bodegas) con la tabla `bodegas` disponible.
- Debe estar definido el diseño UI/UX de la pestaña "Sensores" dentro de la pantalla de Bodegas.
- El scheduler de Laravel (`app/Console/Kernel.php`) debe tener programado el comando `readings:generate` cada minuto.
- Esta HU es independiente de HU-01, HU-02, HU-05 y HU-06.

## 4. Variables y Configuración

| Variable | Valor / Tipo | Entornos | Valor por defecto | Descripción |
|---|---|---|---|---|
| Frecuencia de lecturas | 1 minuto | Todos | 1 minuto | Intervalo fijo de ejecución del comando `readings:generate` (hardcoded; en el futuro vendrá de configuración global). |
| Umbral de alerta temperatura | decimal(5,2) | Todos | 25.00 °C | Dispara alerta cuando `temperature > 25.0` (estricto, no >=). |
| Umbral de alerta humedad | decimal(5,2) | Todos | 70.00 % | Dispara alerta cuando `humidity > 70.0` (estricto, no >=). |
| Rango simulación temperatura | decimal(5,2) | Todos | 15.0 – 35.0 °C | Valor aleatorio generado por el comando para cada lectura. |
| Rango simulación humedad | decimal(5,2) | Todos | 30.0 – 90.0 % | Valor aleatorio generado por el comando para cada lectura. |
| `limit` de lecturas por consulta | integer | Todos | 30 | Cantidad máxima de lecturas retornadas por el endpoint. Máximo permitido: 1000. |
| `page_size` de lecturas en consulta UI | integer | Todos | 30 | Cantidad de lecturas mostradas por página en la tabla de consulta de lecturas de sensores. |

- Sin roles ni permisos — todos los usuarios autenticados pueden realizar todas las acciones.
- Sin feature toggles ni comportamiento específico por tenant.
- Sin comportamiento específico por entorno; los valores de simulación y umbrales son los mismos en dev, staging y production.

## 5. Suposiciones

1. La HU-03 (Gestión de Bodegas) existe con la tabla `bodegas` operativa.
2. La tabla `users` existe con la estructura estándar de Laravel 9 (`id`, `name`, `email`, `password`, `timestamps`).
3. El scheduler de Laravel está configurado y ejecutándose en el servidor (cron o worker).
4. El comando `php artisan readings:generate` se ejecuta cada minuto mediante el scheduler.
5. Las lecturas simuladas generan valores decimales aleatorios dentro de los rangos definidos.
6. Los umbrales de alerta (25 °C, 70 %) están hardcoded en el listener; en el futuro una tabla de configuración global los hará configurables.
7. El UUID `id` es autogenerado por el sistema (lado del servidor/BD), no se proporciona externamente.
8. Las lecturas son INMUTABLES: no se pueden crear, editar ni eliminar manualmente. Solo se consultan a través del endpoint.
9. Los sensores inactivos (`status=false`) NO generan lecturas. Los sensores eliminados mediante soft delete NO generan lecturas.
10. El soft-delete de un sensor elimina en cascada sus relaciones con bodegas, lecturas y alertas. Si se requiere preservar el histórico, se debe hacer un force delete de las lecturas antes de eliminar el sensor (fuera del alcance de esta HU).

## 6. Fuera de Alcance (explícitamente excluido)

- Visualización de alertas y reportes → pertenece a HU-02 (Búsqueda y Filtrado).
- Parámetros configurables (frecuencia, umbrales) → futura HU de configuración global.
- Creación, edición o eliminación manual de lecturas → las lecturas son inmutables.
- Notificaciones por correo electrónico o SMS para alertas.
- Dashboard en tiempo real.
- CRUD independiente de asignación sensor-bodega → se gestiona desde el formulario del sensor, no hay pantalla separada.
- Gestión de roles y permisos.

## 7. Camino Feliz Principal (paso a paso)

### A. CRUD de Sensores (dentro de la pantalla de Bodegas)

1. El usuario navega a la pantalla "Bodegas" y accede a la pestaña/sección "Sensores".
2. En la parte superior se muestra un formulario con los campos: `name` (texto, permite: letras incluyendo acentos y ñ, dígitos, espacios, guiones, paréntesis y símbolos de grado; no permite HTML, scripts, ni emojis), `description` (texto, máximo 300 caracteres) y un multi-select de bodegas (opcional para guardar, pero requerido para que el sensor genere lecturas).
3. Debajo del formulario se muestra una tabla con las columnas: Nombre, Descripción, Estado, Bodegas Asociadas, Acciones (Editar, Switch Activar/Inactivar, Eliminar).
4. **Crear:** el usuario completa el formulario, selecciona una o más bodegas y hace clic en "Guardar". El sistema valida la unicidad de `name` (insensible a mayúsculas/minúsculas), guarda el sensor con `created_by` del usuario de la sesión y crea los registros correspondientes en `sensor_warehouse` para cada bodega seleccionada.
5. **Editar:** el usuario hace clic en "Editar" en una fila. El formulario se precarga con `name`, `description` y las bodegas asociadas. El usuario puede agregar o quitar bodegas. El sistema actualiza los registros de `sensor_warehouse` según corresponda. Las lecturas históricas se preservan aunque se quite una bodega.
6. **Activar/Inactivar:** el usuario alterna el switch en una fila. El sistema cambia el campo `status`. Si se inactiva, el sensor deja de generar lecturas; si se reactiva, reanuda la generación.
7. **Eliminar:** el usuario hace clic en "Eliminar". El sistema solicita confirmación y, si se confirma, aplica soft delete (`deleted_at` poblado). El sensor deja de generar lecturas. Al hacer soft-delete de un sensor, los registros en `sensor_warehouse` se eliminan vía CASCADE, y consecuentemente las lecturas (`readings`) asociadas a esos `sensor_warehouse_id` también se eliminan vía CASCADE. Las alertas (`alerts`) asociadas a esas lecturas también se eliminan vía CASCADE.
8. **Validaciones:** `name` es obligatorio, único (case-insensitive), máximo 60 caracteres y se recorta (trim). `description` es opcional, máximo 300 caracteres.
9. **Estado vacío:** cuando no existen sensores, la tabla muestra: "No hay sensores registrados".
10. **Estado de carga:** mientras cualquier operación está en progreso, se muestra un spinner y se deshabilita el botón de envío.

### B. Generación Automática de Lecturas (Comando Artisan)

1. El comando `php artisan readings:generate` se ejecuta cada minuto mediante el scheduler de Laravel.
2. Por cada sensor activo (`status=true`) que tenga al menos una bodega asignada en `sensor_warehouse`, el comando genera una lectura.
3. Para cada registro `sensor_warehouse` del sensor, se crea una lectura con:
   - `temperature`: valor decimal aleatorio entre 15.0 y 35.0 °C.
   - `humidity`: valor decimal aleatorio entre 30.0 y 90.0 %.
   - `recorded_at`: timestamp actual del servidor.
4. Las lecturas se guardan en la tabla `readings`.

### C. Evento y Listener de Alertas

1. Cada vez que se registra una lectura, se despacha el evento `ReadingRecorded`.
2. El evento `ReadingRecorded` se dispara después de guardar cada lectura. El listener evalúa: si `temperature > 25.0` O `humidity > 70.0`, se guarda un registro en una tabla `alerts` con los campos: `id` (uuid), `reading_id` (FK → readings), `type` (enum: 'temperature', 'humidity', 'both'), `message` (text), `created_at` (timestamp). Esto permite que HU-02 consulte y muestre las alertas históricas.
3. HU-02 podrá consultar estas alertas a través del endpoint de lecturas.

### D. Consulta de Lecturas de Sensores (dentro de la pantalla de Bodegas)

1. El usuario navega a la pantalla "Bodegas" y accede a la pestaña/sección "Consulta de Lecturas de Sensores".
2. En la parte superior se muestra un formulario de filtros:
   - Bodega: select precargado con la bodega actual en contexto.
   - Sensor: select con los sensores asignados a esa bodega.
   - Rango de fechas: dos campos `desde` y `hasta` (timestamp de fecha).
3. Debajo del formulario se muestran los botones: "Buscar", "Limpiar filtros", "Exportar Excel".
4. Al hacer clic en "Buscar", el sistema consulta las lecturas aplicando los filtros seleccionados.
5. Los resultados se muestran en una tabla con las columnas: Bodega, Sensor, Temperatura, Humedad, Alerta, Fecha Registro.
6. La columna "Alerta" muestra un indicador con icono/color:
   - Rojo: `temperature > 25.0` O `humidity > 70.0`.
   - Verde: dentro del rango normal.
7. La tabla se ordena por `recorded_at` ascendente (más antigua primero) por defecto.
8. La tabla pagina 30 registros por página.
9. Mientras se cargan los datos se muestra un spinner.
10. Al hacer clic en "Exportar Excel", el sistema genera un archivo `.xlsx` con todas las filas que coinciden con los filtros aplicados (no solo la página actual), nombrado `lecturas_sensores_{fecha}.xlsx`.
11. Durante la generación del Excel, el botón se deshabilita y muestra un spinner.

### E. Endpoint de Consulta de Lecturas

1. **Endpoint:** `GET /api/warehouses/{id}/readings`
2. **Query params:**
   - `sensor_id` (uuid, opcional): filtra las lecturas del sensor en la bodega.
   - `limit` (integer, opcional): cantidad de registros por página. Valor por defecto: 30. Máximo permitido: 1000. Valores inválidos, negativos o mayores a 1000 se ajustan a 30.
   - `date_from` (ISO 8601, opcional): fecha/hora inicial del rango de filtro.
   - `date_to` (ISO 8601, opcional): fecha/hora final del rango de filtro.
   - `page` (integer, opcional): número de página. Valor por defecto: 1.
3. Retorna las lecturas ordenadas por `recorded_at` DESC.
4. **Response:** `{ data: [...], current_page, per_page, total, last_page }`.

## 8. Caminos Tristes (errores esperados)

| # | Escenario | Comportamiento del Sistema |
|---|---|---|
| SP-01 | El usuario envía `name` vacío o solo espacios | El frontend y el backend validan: "El campo nombre es obligatorio". |
| SP-02 | `name` duplicado (insensible a mayúsculas/minúsculas) | El backend retorna HTTP 409 Conflict con el mensaje: "El nombre del sensor ya existe". |
| SP-03 | `name` supera 60 caracteres | El frontend bloquea la entrada a 60 caracteres. Si se evade, el backend retorna HTTP 422. |
| SP-04 | `description` supera 300 caracteres | El frontend bloquea la entrada a 300 caracteres. Si se evade, el backend retorna HTTP 422. |
| SP-05 | Error de conexión a la base de datos al guardar el sensor | El frontend muestra: "No es posible establecer conexión con el servidor, intente nuevamente". |
| SP-06 | Otro usuario modificó o eliminó el sensor mientras el usuario actual lo edita | El backend detecta el cambio de estado. El frontend muestra: "No es posible realizar esta acción, recargue la página". |
| SP-07 | Eliminar un sensor ya eliminado | Mismo comportamiento que SP-06: "No es posible realizar esta acción, recargue la página". |
| SP-08 | Doble envío del formulario | El botón se deshabilita y se muestra un spinner mientras la solicitud está en progreso. |
| SP-09 | Comando `readings:generate` procesa un sensor inactivo o eliminado | No genera lectura para ese sensor y continúa con el siguiente. |
| SP-10 | Comando `readings:generate` procesa un sensor sin bodegas asignadas | No genera lectura para ese sensor y continúa con el siguiente. |
| SP-11 | Error de base de datos al guardar una lectura durante el comando | Se registra el error en el log y el comando continúa con el siguiente sensor. |
| SP-12 | Endpoint: la bodega `{id}` no existe | El backend retorna HTTP 404 con el mensaje: "Bodega no encontrada". |
| SP-13 | Endpoint: `sensor_id` no pertenece a esa bodega | El endpoint retorna un array vacío `[]`. |
| SP-14 | Endpoint: `limit` inválido (< 1 o > 1000) | El sistema aplica el valor por defecto 30. |
| SP-15 | Consulta sin filtros | Retorna todas las lecturas de la bodega, paginadas (30 por página), orden ascendente por fecha |
| SP-16 | Filtros no retornan resultados | "No se encontraron lecturas para los filtros aplicados" |
| SP-17 | Rango de fechas inválido (`desde` > `hasta`) | "El rango de fechas no es válido" |
| SP-18 | Exportar Excel sin resultados | "No hay datos para exportar" |
| SP-19 | Error al generar Excel | "No se pudo generar el archivo. Intente nuevamente" |
| SP-20 | Endpoint: `date_from` > `date_to` | El backend retorna HTTP 422 con el mensaje: "El rango de fechas no es válido". |

## 9. Casos Límite y Casos Estúpidos

### Casos Límite

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| EC-01 | Sensor creado sin bodegas asignadas | Está permitido. No genera lecturas hasta que se le asigne al menos una bodega. |
| EC-02 | Sensor inactivado y luego reactivado | Reanuda la generación de lecturas normalmente. |
| EC-03 | Quitar una bodega en edición | Las lecturas existentes se preservan. No se generan nuevas lecturas para esa bodega. |
| EC-04 | Todas las bodegas quitadas de un sensor | El sensor deja de generar lecturas. |
| EC-05 | Multi-select de bodegas vacío en edición | Está permitido; el sensor queda sin bodegas asignadas. |
| EC-06 | Endpoint consultado sin parámetro `sensor_id` | Retorna lecturas de todos los sensores asociados a la bodega. |
| EC-07 | Temperatura exactamente 25.0 °C | NO dispara alerta (la comparación es estrictamente > 25). |
| EC-08 | Humedad exactamente 70.0 % | NO dispara alerta (la comparación es estrictamente > 70). |
| EC-09 | Mismo sensor asignado dos veces a la misma bodega | La restricción UNIQUE de `sensor_warehouse` lo bloquea; el backend retorna HTTP 422. |
| EC-13 | Sensor no pertenece a la bodega | El select de sensores solo muestra los asignados a esa bodega |
| EC-14 | Sin sensores en la bodega | Select de sensor aparece vacío, mensaje: "No hay sensores asignados a esta bodega" |
| EC-15 | Rango de fechas: misma fecha inicio y fin | Retorna lecturas de ese día exacto (timestamp entre 00:00:00 y 23:59:59) |
| EC-16 | Doble clic en "Exportar Excel" | Botón deshabilitado durante generación |

### Casos Estúpidos

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| EC-10 | El usuario pega JSON, HTML o scripts en `name` o `description` | Se rechaza o sanitiza. `name` permite: letras incluyendo acentos y ñ, dígitos, espacios, guiones, paréntesis y símbolos de grado. No permite HTML, scripts, ni emojis. |
| EC-11 | El usuario escribe solo emojis como `name` | El frontend restringe la entrada; `name` permite: letras incluyendo acentos y ñ, dígitos, espacios, guiones, paréntesis y símbolos de grado. No permite emojis. |
| EC-12 | Tabla de sensores vacía | Se muestra el mensaje: "No hay sensores registrados". |

## 10. Criterios de Aceptación

### CA-01: Crear Sensor con Nombre y Descripción
**Dado** que el usuario está en la pestaña "Sensores" de la pantalla de Bodegas  
**Cuando** completa `name` válido (1–60 caracteres, único, permite: letras incluyendo acentos y ñ, dígitos, espacios, guiones, paréntesis y símbolos de grado; no permite HTML, scripts, ni emojis, recortado) y `description` opcional (0–300 caracteres), luego hace clic en "Guardar"  
**Entonces** el sensor se crea con `status=true`, `created_at`=hora actual del servidor, `created_by`=usuario autenticado   PASS / FAIL  
**Y** se muestra la respuesta de éxito   PASS / FAIL  
**Y** la tabla inferior se refresca mostrando el nuevo sensor   PASS / FAIL  
**Y** el formulario se reinicia a vacío   PASS / FAIL

### CA-02: Crear Sensor con Bodegas Asignadas
**Dado** que el usuario crea un sensor y selecciona una o más bodegas del multi-select  
**Cuando** el registro del sensor persiste  
**Entonces** se crean los registros correspondientes en `sensor_warehouse` para cada bodega seleccionada   PASS / FAIL  
**Y** la combinación (`sensor_id`, `warehouse_id`) respeta la restricción UNIQUE   PASS / FAIL

### CA-03: Crear Sensor sin Bodegas
**Dado** que el usuario completa el formulario del sensor y no selecciona ninguna bodega  
**Cuando** hace clic en "Guardar"  
**Entonces** el sensor se guarda correctamente   PASS / FAIL  
**Y** no se crean registros en `sensor_warehouse`   PASS / FAIL

### CA-04: Listar Sensores Mostrando Bodegas Asociadas
**Dado** que existen sensores en el sistema  
**Cuando** el usuario navega a la pestaña "Sensores"  
**Entonces** la tabla muestra las columnas: Nombre, Descripción, Estado, Bodegas Asociadas   PASS / FAIL  
**Y** cada fila muestra las bodegas vinculadas al sensor   PASS / FAIL

### CA-05: Lista Vacía de Sensores
**Dado** que no existen sensores en el sistema  
**Cuando** el usuario navega a la pestaña "Sensores"  
**Entonces** la tabla muestra: "No hay sensores registrados"   PASS / FAIL

### CA-06: Editar Nombre y Descripción del Sensor
**Dado** que existe un sensor  
**Cuando** el usuario hace clic en "Editar", modifica `name` y/o `description` y guarda  
**Entonces** el registro se actualiza con los nuevos valores   PASS / FAIL  
**Y** `updated_by` y `updated_at` se actualizan   PASS / FAIL

### CA-07: Editar Sensor: Agregar Bodegas
**Dado** que existe un sensor asociado a una o más bodegas  
**Cuando** el usuario agrega bodegas adicionales en la edición y guarda  
**Entonces** se crean los nuevos registros en `sensor_warehouse`   PASS / FAIL  
**Y** las bodegas anteriores permanecen   PASS / FAIL

### CA-08: Editar Sensor: Quitar Bodegas
**Dado** que existe un sensor con lecturas históricas en una bodega  
**Cuando** el usuario quita esa bodega en la edición y guarda  
**Entonces** se elimina el registro correspondiente de `sensor_warehouse`   PASS / FAIL  
**Y** las lecturas existentes en `readings` se preservan   PASS / FAIL  
**Y** no se generan nuevas lecturas para esa bodega   PASS / FAIL

### CA-09: Inactivar Sensor
**Dado** que existe un sensor activo con bodegas asignadas  
**Cuando** el usuario alterna el switch para inactivarlo  
**Entonces** el campo `status` cambia a false   PASS / FAIL  
**Y** el sensor deja de generar lecturas en la siguiente ejecución del comando   PASS / FAIL

### CA-10: Reactivar Sensor
**Dado** que existe un sensor inactivo con bodegas asignadas  
**Cuando** el usuario alterna el switch para reactivarlo  
**Entonces** el campo `status` cambia a true   PASS / FAIL  
**Y** el sensor reanuda la generación de lecturas en la siguiente ejecución del comando   PASS / FAIL

### CA-11: Eliminar Sensor
**Dado** que existe un sensor con bodegas asignadas  
**Cuando** el usuario hace clic en "Eliminar" y confirma  
**Entonces** el sensor se elimina mediante soft delete (`deleted_at` poblado)   PASS / FAIL  
**Y** los registros de `sensor_warehouse` asociados se eliminan en cascada   PASS / FAIL  
**Y** las lecturas (`readings`) y alertas (`alerts`) asociadas a esas relaciones también se eliminan en cascada   PASS / FAIL  
**Y** el sensor deja de generar lecturas   PASS / FAIL

### CA-12: Validar Nombre Obligatorio
**Dado** que el usuario está completando el formulario del sensor  
**Cuando** envía con `name` vacío o solo espacios (después de trim)  
**Entonces** el frontend resalta el campo   PASS / FAIL  
**Y** muestra: "El campo nombre es obligatorio"   PASS / FAIL  
**Y** el registro NO se guarda   PASS / FAIL

### CA-13: Validar Nombre Duplicado
**Dado** que ya existe un sensor llamado "Sensor Central"  
**Cuando** el usuario intenta crear o editar un sensor con `name` "sensor central" (o "SENSOR CENTRAL")  
**Entonces** el backend retorna HTTP 409   PASS / FAIL  
**Y** el frontend muestra: "El nombre del sensor ya existe"   PASS / FAIL

### CA-14: Validar Longitud del Nombre y la Descripción
**Dado** que el usuario está completando el formulario del sensor  
**Cuando** escribe más de 60 caracteres en `name` o más de 300 en `description`  
**Entonces** el frontend bloquea la entrada en los límites   PASS / FAIL  
**Y** si una solicitud evade la validación del frontend, el backend retorna HTTP 422   PASS / FAIL

### CA-15: Comando Genera Lecturas para Sensores Activos con Bodegas
**Dado** que existen sensores activos con al menos una bodega asignada  
**Cuando** se ejecuta `php artisan readings:generate`  
**Entonces** se crea al menos una lectura por cada registro `sensor_warehouse`   PASS / FAIL  
**Y** cada lectura tiene `temperature`, `humidity`, `recorded_at` y `sensor_warehouse_id`   PASS / FAIL

### CA-16: Comando Omite Sensores Inactivos
**Dado** que existe un sensor inactivo (`status=false`) con bodegas asignadas  
**Cuando** se ejecuta `php artisan readings:generate`  
**Entonces** no se generan lecturas para ese sensor   PASS / FAIL

### CA-17: Comando Omite Sensores sin Bodegas
**Dado** que existe un sensor activo sin bodegas asignadas  
**Cuando** se ejecuta `php artisan readings:generate`  
**Entonces** no se generan lecturas para ese sensor   PASS / FAIL

### CA-18: Valores de Lectura Dentro de los Rangos Definidos
**Dado** que el comando genera lecturas para un sensor activo  
**Cuando** se inspeccionan las lecturas guardadas  
**Entonces** `temperature` está entre 15.0 y 35.0 °C   PASS / FAIL  
**Y** `humidity` está entre 30.0 y 90.0 %   PASS / FAIL

### CA-19: Lectura con Timestamp de Registro
**Dado** que se ejecuta `php artisan readings:generate`  
**Cuando** se crea una lectura  
**Entonces** el campo `recorded_at` contiene el timestamp actual del servidor   PASS / FAIL

### CA-20: Evento Despachado al Registrar Lectura
**Dado** que el comando `readings:generate` crea una lectura exitosamente  
**Cuando** la lectura se guarda en la tabla `readings`  
**Entonces** se despacha el evento `ReadingRecorded`   PASS / FAIL

### CA-21: Alerta por Temperatura Mayor a 25 °C
**Dado** que el listener de alertas recibe una lectura con `temperature` > 25.0  
**Cuando** se evalúa la condición de alerta  
**Entonces** la lectura se considera alerta por temperatura   PASS / FAIL

### CA-22: Alerta por Humedad Mayor a 70 %
**Dado** que el listener de alertas recibe una lectura con `humidity` > 70.0  
**Cuando** se evalúa la condición de alerta  
**Entonces** la lectura se considera alerta por humedad   PASS / FAIL

### CA-23: No Alerta con Temperatura Igual a 25.0 °C
**Dado** que el listener recibe una lectura con `temperature` = 25.0  
**Cuando** se evalúa la condición de alerta  
**Entonces** la lectura NO se considera alerta por temperatura   PASS / FAIL

### CA-24: No Alerta con Humedad Igual a 70.0 %
**Dado** que el listener recibe una lectura con `humidity` = 70.0  
**Cuando** se evalúa la condición de alerta  
**Entonces** la lectura NO se considera alerta por humedad   PASS / FAIL

### CA-25: Endpoint Retorna Lecturas de una Bodega
**Dado** que existen lecturas para una bodega  
**Cuando** se consulta `GET /api/warehouses/{id}/readings`  
**Entonces** el endpoint retorna las lecturas ordenadas por `recorded_at` DESC   PASS / FAIL  
**Y** el código de respuesta es HTTP 200   PASS / FAIL

### CA-26: Endpoint Filtra por `sensor_id`
**Dado** que una bodega tiene lecturas de varios sensores  
**Cuando** se consulta `GET /api/warehouses/{id}/readings?sensor_id=X`  
**Entonces** el endpoint retorna solo las lecturas del sensor X para esa bodega   PASS / FAIL

### CA-27: Endpoint Bodega No Encontrada
**Dado** que se consulta `GET /api/warehouses/{id}/readings` con un `id` de bodega inexistente  
**Cuando** el endpoint procesa la solicitud  
**Entonces** retorna HTTP 404   PASS / FAIL  
**Y** el mensaje es: "Bodega no encontrada"   PASS / FAIL

### CA-28: Endpoint Limit Inválido Usa Valor por Defecto
**Dado** que se consulta el endpoint con `limit=0`, `limit=-1` o `limit=1001`  
**Cuando** el endpoint procesa la solicitud  
**Entonces** aplica `limit=30`   PASS / FAIL  
**Y** retorna como máximo 30 lecturas   PASS / FAIL

### CA-29: Restricción de Unicidad Sensor-Bodega
**Dado** que un sensor ya está asignado a una bodega  
**Cuando** se intenta crear un segundo registro `sensor_warehouse` con el mismo `sensor_id` y `warehouse_id`  
**Entonces** el backend rechaza la operación con HTTP 422   PASS / FAIL  
**Y** no se duplica la asignación   PASS / FAIL

### CA-30: Campos de Auditoría al Crear
**Dado** que se crea un nuevo sensor  
**Cuando** el registro persiste  
**Entonces** `created_by` se asigna al usuario de la sesión autenticada   PASS / FAIL  
**Y** `created_at` se asigna automáticamente por Eloquent   PASS / FAIL  
**Y** `updated_by` es nulo   PASS / FAIL  
**Y** `updated_at` es nulo   PASS / FAIL

### CA-31: Campos de Auditoría al Editar
**Dado** que se edita un sensor existente  
**Cuando** el registro se actualiza  
**Entonces** `updated_by` se asigna al usuario de la sesión autenticada   PASS / FAIL  
**Y** `updated_at` se asigna automáticamente por Eloquent   PASS / FAIL  
**Y** `created_by` y `created_at` permanecen sin cambios   PASS / FAIL

### CA-32: Consulta de Lecturas sin Filtros
**Dado** que el usuario está en la pestaña "Consulta de Lecturas de Sensores" de una bodega con lecturas  
**Cuando** hace clic en "Buscar" sin aplicar filtros adicionales  
**Entonces** el sistema retorna todas las lecturas de esa bodega   PASS / FAIL  
**Y** los resultados están ordenados por `recorded_at` ascendente   PASS / FAIL

### CA-33: Filtrar Lecturas por Sensor
**Dado** que el usuario está en la pestaña "Consulta de Lecturas de Sensores" y selecciona un sensor del select  
**Cuando** hace clic en "Buscar"  
**Entonces** el sistema retorna solo las lecturas de ese sensor para la bodega actual   PASS / FAIL

### CA-34: Filtrar Lecturas por Rango de Fechas
**Dado** que el usuario selecciona un rango de fechas `desde` y `hasta` válido  
**Cuando** hace clic en "Buscar"  
**Entonces** el sistema retorna solo las lecturas con `recorded_at` entre ambos timestamps   PASS / FAIL

### CA-35: Mensaje de Sin Resultados para Lecturas
**Dado** que el usuario aplica filtros que no retornan lecturas  
**Cuando** el sistema completa la consulta  
**Entonces** se muestra el mensaje: "No se encontraron lecturas para los filtros aplicados"   PASS / FAIL

### CA-36: Error de Rango de Fechas Inválido para Lecturas
**Dado** que el usuario selecciona una fecha `desde` posterior a la fecha `hasta`  
**Cuando** hace clic en "Buscar"  
**Entonces** el frontend muestra: "El rango de fechas no es válido"   PASS / FAIL  
**Y** no se ejecuta la consulta   PASS / FAIL

### CA-37: Indicador Visual de Alerta
**Dado** que la tabla de lecturas muestra resultados  
**Cuando** una fila tiene `temperature` > 25.0 o `humidity` > 70.0  
**Entonces** la columna Alerta muestra el indicador rojo   PASS / FAIL  
**Y** cuando una fila está dentro del rango normal, la columna Alerta muestra el indicador verde   PASS / FAIL

### CA-38: Ordenamiento de Lecturas por Defecto
**Dado** que el usuario consulta lecturas sin especificar ordenamiento  
**Cuando** se muestran los resultados  
**Entonces** el orden por defecto es `recorded_at` ascendente (más antigua primero)   PASS / FAIL

### CA-39: Paginación de Lecturas
**Dado** que una bodega tiene más de 30 lecturas  
**Cuando** el usuario consulta las lecturas  
**Entonces** el sistema pagina los resultados de 30 en 30   PASS / FAIL  
**Y** se muestra el control de paginación   PASS / FAIL

### CA-40: Exportar Lecturas a Excel
**Dado** que el usuario aplicó filtros y hay resultados  
**Cuando** hace clic en "Exportar Excel"  
**Entonces** el sistema genera y descarga un archivo `.xlsx`   PASS / FAIL  
**Y** el archivo contiene todas las filas que coinciden con los filtros aplicados   PASS / FAIL  
**Y** el nombre del archivo es `lecturas_sensores_{fecha}.xlsx`   PASS / FAIL

### CA-41: Exportar Excel Deshabilitado Durante Generación
**Dado** que el usuario hace clic en "Exportar Excel"  
**Cuando** el archivo se está generando  
**Entonces** el botón se deshabilita   PASS / FAIL  
**Y** se muestra un spinner mientras dura la generación   PASS / FAIL

### CA-42: Exportar Excel sin Resultados
**Dado** que no hay resultados para los filtros aplicados  
**Cuando** el usuario hace clic en "Exportar Excel"  
**Entonces** el sistema muestra: "No hay datos para exportar"   PASS / FAIL  
**Y** no se genera ningún archivo   PASS / FAIL

### CA-43: Select de Sensor Limitado a la Bodega Actual
**Dado** que el usuario está en la pestaña "Consulta de Lecturas de Sensores"  
**Cuando** se carga el select de sensores  
**Entonces** solo se muestran los sensores asignados a la bodega actual   PASS / FAIL  
**Y** si la bodega no tiene sensores, el select aparece vacío y muestra: "No hay sensores asignados a esta bodega"   PASS / FAIL

### CA-44: Endpoint Rechaza Rango de Fechas Inválido
**Dado** que se consulta `GET /api/warehouses/{id}/readings?date_from=2024-12-31&date_to=2024-01-01`  
**Cuando** el endpoint procesa la solicitud  
**Entonces** retorna HTTP 422   PASS / FAIL  
**Y** el mensaje es: "El rango de fechas no es válido"   PASS / FAIL
