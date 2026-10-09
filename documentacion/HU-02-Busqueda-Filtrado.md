# HU-02 – Búsqueda y Filtrado de Productos

## 1. Título y Descripción

**Título:** HU-02 – Búsqueda y Filtrado de Productos

**Descripción:** Permitir al usuario consultar y filtrar el inventario de productos farmacéuticos a través de la pantalla "Reporte de Inventario". Esta historia es de solo lectura (READ-ONLY): no permite crear, editar ni eliminar registros. El sistema combina información de lotes, productos, bodegas, grupos y unidades, aplicando filtros enviados como parámetros de consulta y devolviendo resultados paginados. Además, ofrece una alerta visual de expiración y la posibilidad de exportar los resultados filtrados a Excel.

## 2. Modelo de Datos

No se crean nuevas tablas. Esta HU consulta las siguientes tablas existentes mediante JOINs, partiendo de `lots`:

| Tabla | Columnas relevantes | Rol en la consulta |
|---|---|---|
| `lots` | `id`, `code`, `stock`, `expiration_date`, `product_id`, `bodega_id`, `status`, `deleted_at` | Tabla principal; aquí viven el stock y la fecha de vencimiento del lote. |
| `products` | `id`, `cum`, `name`, `barcode`, `invima`, `group_id`, `unit_id`, `status`, `deleted_at` | Datos del producto (CUM, nombre, código de barras, registro Invima). |
| `bodegas` | `id`, `name`, `status`, `deleted_at` | Nombre de la bodega donde se ubica el lote. |
| `groups` | `id`, `name`, `status`, `deleted_at` | Clasificación/grupo del producto. |
| `units` | `id`, `name`, `status`, `deleted_at` | Unidad de medida del producto. |

**Notas de modelo:**
- Solo se incluyen registros activos: `status = true` y `deleted_at IS NULL` en `bodegas`, `groups`, `products`, `units` y `lots`.
- El campo `lots.expiration_date` determina el estado del lote y la alerta de expiración.
- Los campos `cum`, `name`, `barcode`, `invima` pertenecen a la tabla `products`.
- La consulta inicia desde `lots` y realiza INNER JOIN con `products`, `bodegas`, `groups` y `units`.

## 3. Bloqueos y Dependencias

- Debe existir la tabla `users` (estructura estándar de Laravel 9).
- Debe existir y estar poblada la tabla `products` (HU-01).
- Debe existir y estar poblada la tabla `bodegas` (HU-03).
- Debe existir y estar poblada la tabla `lots` con columnas `stock` y `expiration_date` (HU-05).
- Debe existir y estar poblada la tabla `groups` (HU-06).
- Debe existir y estar poblada la tabla `units` (creada en HU-01).
- La autenticación mediante bearer token debe estar implementada a nivel de aplicación; no es parte de esta HU.
- Esta HU es prerrequisito para cualquier reporte o exportación de inventario.

## 4. Variables y Configuración

- `page_size`: 30 resultados por página (valor por defecto).
- Filtrado y paginación se realizan en el backend, dado el volumen esperado de lotes.
- Sin restricciones de rol, permisos o tenants.
- Sin feature toggles ni comportamiento específico por entorno.
- Framework UI: Ant Design para tablas; el ordenamiento de columnas se envía al backend como query params (`sort_by`, `sort_dir`). Al hacer clic en un encabezado de columna, se envía una nueva consulta al servidor con el orden solicitado.
- Ordenamiento por defecto: nombre del producto de A a Z (`products.name ASC`).
- Debounce en filtros de texto: ~300 ms para `cum` y `nombre producto`.
- Exportación Excel: nombre de archivo `reporte_inventario_{fecha}.xlsx`.
- Todas las comparaciones de fecha usan solo la parte DATE (sin hora). Un lote con `expiration_date = today` (misma fecha del día de consulta) se clasifica como 'Por vencer' (amarillo), NO como 'Vencido' (rojo).
- Los estados de lote en el select NO son mutuamente excluyentes a nivel de datos — un lote puede tener stock > 0 Y estar vencido. Sin embargo, el select los trata como categorías de filtro independientes: 'Con stock' filtra solo por stock > 0 (sin importar fecha); 'Por vencer' filtra por fecha sin importar stock (siempre que stock > 0); 'Vencido' filtra lotes vencidos con stock > 0. Las categorías no se combinan entre sí porque el select solo permite una opción a la vez.

## 5. Suposiciones

1. Todas las tablas de origen existen y están pobladas.
2. El usuario ya está autenticado (bearer token gestionado a nivel de aplicación).
3. Los filtros se envían como query params al backend, que retorna datos paginados más metadatos (`total`, `page`, `per_page`, `last_page`).
4. Los estados de lote "Con stock", "Por vencer", "Vencido" y "Todos" se computan en el backend al momento de consultar; no son columnas almacenadas.
5. La alerta de expiración se computa en base a `lots.expiration_date`:
   - Verde: `expiration_date > today + 30 days`.
   - Amarilla: `expiration_date >= today AND expiration_date <= today + 30 days`.
   - Roja: `expiration_date < today`.
   - Gris: `expiration_date IS NULL` (texto "Sin fecha").
6. El componente `Table` de Ant Design activa el ordenamiento por columnas, pero la ordenación se ejecuta en el backend mediante los query params `sort_by` y `sort_dir`.
7. El ordenamiento por defecto es el nombre del producto ascendente (A-Z).
8. La búsqueda de texto utiliza `%ILIKE%` para coincidencias parciales e insensibles a mayúsculas/minúsculas.
9. El backend recibe `sort_by` (nombre de columna) y `sort_dir` (asc/desc) como query params y aplica ORDER BY en la consulta SQL.
10. Si un lote tiene `expiration_date IS NULL`: la columna 'Alerta Exp.' muestra indicador gris/neutro con texto 'Sin fecha'. Los filtros 'Por vencer' y 'Vencido' excluyen estos lotes. El filtro 'Rango de vencimiento' los excluye. Solo aparecen con los filtros 'Con stock' o 'Todos'.
11. El select de Lote muestra todos los códigos de lote activos (`status = true`, `deleted_at IS NULL`) ordenados alfabéticamente. Si hay más de 100 lotes, se implementa búsqueda con autocomplete en lugar de select estándar.

## 6. Fuera de Alcance (explícitamente excluido)

- Lectura de sensores y alertas asociadas (pertenece a HU-04).
- Operaciones CRUD sobre productos, lotes, bodegas, grupos o unidades; esta HU es solo lectura.
- Dashboards, gráficos o visualización de datos.
- Notificaciones automáticas de vencimiento (el listener de alertas de HU-04 maneja las de sensores).
- Exportación a PDF; solo se permite exportación a Excel.
- Actualizaciones en tiempo real; los resultados son estáticos hasta nueva consulta.

## 7. Camino Feliz Principal (paso a paso)

### Cargar pantalla "Reporte de Inventario"

1. El usuario navega a la pantalla "Reporte de Inventario".
2. El sistema muestra el formulario de filtros en la parte superior y la tabla de resultados en la parte inferior.
3. El sistema ejecuta la consulta inicial sin filtros aplicados.
4. El backend retorna todos los registros activos paginados a 30 por página, ordenados por `products.name ASC`.
5. El frontend renderiza la tabla con las columnas: CUM, Producto, Grupo, Código Barras, Invima, Stock, Unidad, Lote, Bodega, Fecha Exp., Alerta Exp.
6. La columna "Alerta Exp." muestra el indicador visual correspondiente (verde/amarillo/rojo/gris) según la fecha de vencimiento.
7. La paginación muestra el texto "Mostrando X-Y de Z resultados" junto con los controles de navegación.

### Consulta inicial sin filtros

1. El sistema ejecuta la consulta inicial sin filtros aplicados.
2. El backend retorna todos los registros activos paginados a 30 por página, ordenados por `products.name ASC`.
3. La tabla se renderiza con los resultados y metadatos de paginación.

### Aplicar filtros

1. El usuario completa uno o más filtros del formulario:
   - **CUM**: texto libre, búsqueda parcial `%ILIKE%` (búsqueda automática con debounce de 300ms).
   - **Nombre producto**: texto libre, búsqueda parcial `%ILIKE%` (búsqueda automática con debounce de 300ms).
   - **Bodega**: select; filtra lotes ubicados en esa bodega (requiere clic en Buscar).
   - **Grupo**: select; filtra productos pertenecientes a ese grupo (requiere clic en Buscar).
   - **Lote**: select; filtra por código de lote específico (requiere clic en Buscar).
   - **Estado lote**: select con opciones "Con stock", "Por vencer (<30 días)", "Vencido", "Todos" (requiere clic en Buscar).
   - **Rango vencimiento**: date range `desde`/`hasta`; filtra lotes cuya `expiration_date` esté entre ambas fechas (requiere clic en Buscar).
2. Botón "Buscar": aplica filtros de selects y date range. Los filtros de texto se aplican automáticamente.
3. El sistema envía los filtros como query params al backend.
4. El backend aplica todos los filtros con lógica AND y retorna los resultados paginados.
5. La tabla se actualiza, manteniendo el orden por defecto A-Z por nombre de producto.

### Ordenar columnas

1. El usuario hace clic en cualquier encabezado de columna.
2. Al hacer clic en cualquier encabezado de columna, se recarga la consulta con el nuevo ordenamiento (parámetros `sort_by` y `sort_dir` enviados al backend).
3. Hacer clic nuevamente invierte el orden.

### Exportar a Excel

1. El usuario aplica los filtros deseados y hace clic en "Exportar Excel".
2. El sistema deshabilita el botón y muestra un spinner mientras genera el archivo.
3. El backend ejecuta la misma consulta con los filtros actuales, pero exporta **todas** las filas coincidentes (no solo la página visible).
4. El sistema genera un archivo `.xlsx` con nombre `reporte_inventario_{fecha}.xlsx` y lo descarga en el navegador.
5. Una vez finalizada la descarga, el botón vuelve a habilitarse y el spinner desaparece.

### Limpiar filtros

1. El usuario hace clic en "Limpiar filtros".
2. El sistema resetea todos los campos del formulario a sus valores iniciales.
3. El sistema vuelve a ejecutar la consulta sin filtros y muestra todos los registros paginados.

## 8. Caminos Tristes (errores esperados)

| # | Escenario | Comportamiento del Sistema |
|---|---|---|
| SP-01 | Filtros no retornan resultados | Muestra el mensaje: "No se encontraron resultados para los filtros aplicados". |
| SP-02 | Rango de fechas inválido (`desde` > `hasta`) | Muestra el mensaje: "El rango de fechas no es válido" y no ejecuta la consulta. |
| SP-03 | Error de conexión a la base de datos | Muestra el mensaje: "No es posible establecer conexión con el servidor, intente nuevamente". |
| SP-04 | Timeout de consulta | Muestra el mensaje: "La consulta está tomando más tiempo del esperado. Refine los filtros e intente de nuevo". |
| SP-05 | Exportar Excel sin resultados | Muestra el mensaje: "No hay datos para exportar" y no genera el archivo. |
| SP-06 | Error al generar Excel | Muestra el mensaje: "No se pudo generar el archivo. Intente nuevamente". |
| SP-07 | Página solicitada fuera de rango | Retorna la última página disponible con los resultados correspondientes. |
| SP-08 | Debounce: múltiples cambios rápidos de filtro | Cancela la request anterior y ejecuta solo la última consulta. |

## 9. Casos Límite y Casos Estúpidos

### Casos Límite

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| EC-01 | Sin filtros aplicados (consulta inicial) | Retorna todos los registros paginados con orden A-Z por nombre de producto. |
| EC-02 | Todos los filtros combinados simultáneamente | Aplica lógica AND entre todos los filtros y retorna solo los registros que cumplan todas las condiciones. |
| EC-03 | Combinación contradictoria: "Con stock" + solo lotes vencidos | Retorna vacío y muestra el mensaje SP-02. |
| EC-04 | Exactamente 30 resultados | Muestra una sola página; los controles de paginación permiten navegar a una única página. |
| EC-05 | 31 resultados | Muestra dos páginas: 30 registros en la primera y 1 en la segunda. |
| EC-06 | Rango de fechas: misma fecha inicio y fin | Retorna los lotes cuya `expiration_date` es exactamente ese día. |
| EC-07 | Rango de fechas muy amplio (ej. 10 años) | Se permite; la paginación divide el resultado y lo presenta página por página. |
| EC-08 | Input de texto solo espacios | El frontend aplica `trim`; si queda vacío, ignora ese filtro como si no se aplicara. |
| EC-09 | Selects vacíos (no existen bodegas/grupos/lotes) | Los select se muestran vacíos o deshabilitados, pero la consulta sin filtros sigue siendo posible. Si el select de Lote tiene más de 100 opciones, se reemplaza por campo de autocomplete con búsqueda. |
| EC-10 | Selects: solo registros activos | Los select muestran únicamente registros con `status = true` y `deleted_at IS NULL`. |
| EC-11 | Exportar Excel con más de 10.000 filas | Se ejecuta con timeout de 120 segundos, mostrando spinner y manteniendo el botón deshabilitado hasta finalizar. |
| EC-12 | Doble clic en "Exportar Excel" | El botón permanece deshabilitado durante la generación; solo se produce una descarga. |
| EC-13 | Texto de búsqueda con caracteres especiales (`%`, `_`, `\`) | El backend escapa correctamente los caracteres en la consulta SQL para evitar comportamientos inesperados. |
| EC-14 | Usuario escribe muy rápido en filtros de texto | El debounce de ~300 ms cancela la request anterior y ejecuta solo la última consulta. |

### Casos Estúpidos

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| CE-01 | El usuario envía texto de búsqueda con código SQL (`' OR 1=1 --`) | El backend utiliza consultas parametrizadas; el input se trata como texto literal y no altera la consulta. |
| CE-02 | El usuario modifica manualmente el query param `page=-1` o `page=abc` | El backend valida y corrige a la primera página, o retorna un error 422 según implementación del framework. |
| CE-03 | El usuario fuerza un `per_page` muy grande (ej. 100000) en la URL | El backend ignora el valor o lo limita al máximo permitido (por defecto 30). |
| CE-04 | El usuario solicita exportar Excel mientras otra exportación está en progreso | El segundo clic es ignorado porque el botón está deshabilitado; solo se descarga un archivo. |
| CE-05 | El usuario envía fechas en formato inválido (ej. `2025-13-45`) | El backend valida el formato de fecha y retorna HTTP 422 con mensaje de rango inválido. |

## 10. Criterios de Aceptación

### CA-01: Consulta sin filtros
**Dado** que el usuario está en la pantalla "Reporte de Inventario"  
**Cuando** el sistema carga la consulta inicial sin filtros aplicados  
**Entonces** el backend retorna todos los registros activos paginados a 30 por página  PASS / FAIL  
**Y** los resultados están ordenados por `products.name` ascendente (A-Z)  PASS / FAIL

### CA-02: Filtrar por CUM (coincidencia parcial)
**Dado** que existen productos con diferentes valores de `cum`  
**Cuando** el usuario escribe un fragmento de CUM en el filtro (búsqueda automática con debounce de 300ms)  
**Entonces** la tabla muestra solo los lotes cuyo producto tiene `cum` que coincida parcial e insensiblemente con el texto ingresado  PASS / FAIL

### CA-03: Filtrar por nombre de producto (coincidencia parcial)
**Dado** que existen productos con diferentes nombres  
**Cuando** el usuario escribe un fragmento del nombre en el filtro (búsqueda automática con debounce de 300ms)  
**Entonces** la tabla muestra solo los lotes cuyo `products.name` coincida parcial e insensiblemente con el texto ingresado  PASS / FAIL

### CA-04: Filtrar por bodega
**Dado** que existen lotes en diferentes bodegas  
**Cuando** el usuario selecciona una bodega del filtro y hace clic en "Buscar"  
**Entonces** la tabla muestra solo los lotes cuyo `bodega_id` corresponde a la bodega seleccionada  PASS / FAIL

### CA-05: Filtrar por grupo
**Dado** que existen productos en diferentes grupos  
**Cuando** el usuario selecciona un grupo del filtro y hace clic en "Buscar"  
**Entonces** la tabla muestra solo los lotes cuyo producto pertenece al grupo seleccionado  PASS / FAIL

### CA-06: Filtrar por lote
**Dado** que existen lotes con diferentes códigos  
**Cuando** el usuario selecciona un código de lote del filtro y hace clic en "Buscar"  
**Entonces** la tabla muestra solo los lotes cuyo `lots.code` coincide exactamente con el seleccionado  PASS / FAIL

### CA-07: Filtrar por estado "Con stock"
**Dado** que existen lotes con `stock > 0` y lotes con `stock = 0`  
**Cuando** el usuario selecciona "Con stock" en el filtro de estado y hace clic en "Buscar"  
**Entonces** la tabla muestra solo los lotes cuyo `lots.stock` es mayor a cero, incluyendo lotes vencidos  PASS / FAIL  
**Y** los lotes con `expiration_date IS NULL` no son excluidos por este filtro  PASS / FAIL

### CA-08: Filtrar por estado "Por vencer"
**Dado** que existen lotes con fechas de vencimiento dentro y fuera de los próximos 30 días  
**Cuando** el usuario selecciona "Por vencer (<30 días)" y hace clic en "Buscar"  
**Entonces** la tabla muestra solo los lotes cuya `expiration_date` está entre `today` y `today + 30 days` inclusive en ambos extremos  PASS / FAIL  
**Y** los lotes con `expiration_date IS NULL` son excluidos de los filtros "Por vencer" y "Vencido"  PASS / FAIL

### CA-09: Filtrar por estado "Vencido"
**Dado** que existen lotes vencidos y no vencidos  
**Cuando** el usuario selecciona "Vencido" y hace clic en "Buscar"  
**Entonces** la tabla muestra solo los lotes cuya `expiration_date` es anterior a `today` (solo fechas anteriores a hoy, no incluye today)  PASS / FAIL  
**Y** los lotes con `expiration_date IS NULL` son excluidos de los filtros "Por vencer" y "Vencido"  PASS / FAIL

### CA-10: Filtrar por estado "Todos"
**Dado** que existen lotes con diferentes estados de vencimiento y stock  
**Cuando** el usuario selecciona "Todos" y hace clic en "Buscar"  
**Entonces** la tabla no aplica ningún filtro por estado de lote  PASS / FAIL

### CA-11: Filtrar por rango de fechas de vencimiento
**Dado** que existen lotes con diferentes fechas de vencimiento  
**Cuando** el usuario selecciona un rango `desde`/`hasta` válido y hace clic en "Buscar"  
**Entonces** la tabla muestra solo los lotes cuya `expiration_date` cae dentro del rango seleccionado  PASS / FAIL

### CA-12: Filtros combinados (lógica AND)
**Dado** que el usuario aplica múltiples filtros simultáneamente (CUM, bodega, grupo, estado)  
**Cuando** hace clic en "Buscar"  
**Entonces** la tabla muestra solo los lotes que cumplan **todas** las condiciones aplicadas  PASS / FAIL

### CA-13: Sin resultados encontrados
**Dado** que el usuario aplica filtros que no coinciden con ningún registro  
**Cuando** el backend retorna una lista vacía  
**Entonces** la tabla muestra el mensaje: "No se encontraron resultados para los filtros aplicados"  PASS / FAIL

### CA-14: Rango de fechas inválido
**Dado** que el usuario selecciona una fecha `desde` posterior a la fecha `hasta`  
**Cuando** intenta ejecutar la búsqueda  
**Entonces** el sistema muestra el mensaje: "El rango de fechas no es válido"  PASS / FAIL  
**Y** no se ejecuta la consulta  PASS / FAIL

### CA-15: Columnas de la tabla según especificación
**Dado** que la tabla de resultados se renderiza  
**Entonces** las columnas visibles son: CUM, Producto, Grupo, Código Barras, Invima, Stock, Unidad, Lote, Bodega, Fecha Exp., Alerta Exp.  PASS / FAIL

### CA-16: Indicadores visuales de Alerta de Expiración
**Dado** que la tabla muestra lotes con diferentes fechas de vencimiento  
**Entonces** los lotes con `expiration_date > today + 30 days` muestran indicador verde  PASS / FAIL  
**Y** los lotes con `expiration_date >= today AND expiration_date <= today + 30` muestran indicador amarillo  PASS / FAIL  
**Y** los lotes con `expiration_date < today` muestran indicador rojo  PASS / FAIL  
**Y** los lotes con `expiration_date IS NULL` muestran indicador gris con texto "Sin fecha"  PASS / FAIL  
**Y** el indicador es un badge circular de color: verde (sin alerta), amarillo (por vencer), rojo (vencido), gris (sin fecha)  PASS / FAIL

### CA-17: Ordenamiento por defecto por nombre de producto
**Dado** que el usuario accede a la pantalla  
**Cuando** se cargan los resultados  
**Entonces** el ordenamiento por defecto es `products.name` ascendente (A-Z)  PASS / FAIL

### CA-18: Metadatos de paginación
**Dado** que el backend retorna resultados paginados  
**Entonces** la respuesta incluye `total`, `page`, `per_page` y `last_page`  PASS / FAIL  
**Y** la interfaz muestra "Mostrando X-Y de Z resultados"  PASS / FAIL

### CA-19: Navegación de paginación
**Dado** que existen más de 30 registros  
**Cuando** el usuario navega a la página 2  
**Entonces** la tabla muestra los siguientes 30 registros correspondientes  PASS / FAIL  
**Y** el contador de resultados se actualiza  PASS / FAIL

### CA-20: Limpiar filtros
**Dado** que el usuario ha aplicado filtros en el formulario  
**Cuando** hace clic en "Limpiar filtros"  
**Entonces** todos los campos del formulario vuelven a sus valores iniciales  PASS / FAIL  
**Y** el sistema recarga la tabla sin filtros aplicados  PASS / FAIL

### CA-21: Error de conexión a la base de datos
**Dado** que ocurre un error de conexión mientras se ejecuta la consulta  
**Cuando** el backend no puede acceder a la base de datos  
**Entonces** el frontend muestra: "No es posible establecer conexión con el servidor, intente nuevamente"  PASS / FAIL

### CA-22: Exportar Excel con filtros aplicados
**Dado** que el usuario ha aplicado filtros y hay resultados  
**Cuando** hace clic en "Exportar Excel"  
**Entonces** el sistema genera un archivo `.xlsx` con **todas** las filas que coincidan con los filtros  PASS / FAIL  
**Y** el archivo Excel respeta el ordenamiento activo al momento de exportar (mismos `sort_by` y `sort_dir` de la consulta actual); si no hay ordenamiento activo, usa el orden por defecto: `products.name ASC`  PASS / FAIL  
**Y** el archivo se descarga con nombre `reporte_inventario_YYYY-MM-DD.xlsx` (ej: `reporte_inventario_2025-07-02.xlsx`)  PASS / FAIL

### CA-23: Exportar Excel deshabilitado durante la generación
**Dado** que el usuario hace clic en "Exportar Excel"  
**Cuando** el archivo está siendo generado  
**Entonces** el botón se muestra deshabilitado  PASS / FAIL  
**Y** se muestra un spinner de carga  PASS / FAIL

### CA-24: Exportar Excel sin resultados
**Dado** que los filtros aplicados no retornan registros  
**Cuando** el usuario hace clic en "Exportar Excel"  
**Entonces** el sistema muestra: "No hay datos para exportar"  PASS / FAIL  
**Y** no se genera ni descarga ningún archivo  PASS / FAIL

### CA-25: Debounce en filtros de texto
**Dado** que el usuario escribe rápidamente en un campo de texto (CUM o Nombre producto)  
**Cuando** ocurren múltiples cambios dentro de 300 ms  
**Entonces** la request anterior se cancela  PASS / FAIL  
**Y** solo se ejecuta la consulta correspondiente al último valor ingresado  PASS / FAIL

### CA-26: Selects muestran solo entidades activas
**Dado** que existen bodegas, grupos y lotes activos e inactivos  
**Cuando** el usuario abre los select de Bodega, Grupo o Lote  
**Entonces** solo se muestran las opciones con `status = true` y `deleted_at IS NULL`  PASS / FAIL
