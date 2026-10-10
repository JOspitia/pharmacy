# HU-07 – Sistema de Roles y Permisos

## 1. Título y Descripción

**Título:** HU-07 – Sistema de Roles y Permisos

**Descripción:** Reemplazar el sistema de roles basado en ENUM por un sistema de permisos granular, y unificar en una sola fuente de verdad los dos mecanismos de autorización que tenía el proyecto: los permisos CRUD por módulo y las capacidades de negocio (aprobar traslados, autorizar control especial, dispensar, etc.).

El sistema se apoya en cuatro tablas: `roles` (catálogo de roles), `modules` (catálogo de módulos), `permissions` (catálogo de permisos) y `role_permissions` (qué permisos tiene cada rol). Cada usuario tiene un `role_id` que referencia a `roles`.

El punto central del diseño es que **los permisos son filas y no columnas**. Un permiso es un dato con nombre, no un booleano en un esquema. Eso permite que existan permisos que no son CRUD — como `autorizar-control-especial` — y que agregar un permiso sea un `INSERT` en vez de una migración.

El middleware `EnsureModule` cubre las rutas CRUD: recibe el módulo y deduce el permiso del verbo HTTP. Las capacidades se consultan con Gates, que resuelven contra la misma tabla.

**Nota sobre admin:** el administrador no tiene bypass en el Gate. Su única vía de acceso son los permisos que la matriz le asigna. Esto no es una restricción arbitraria: es lo que exigen RN-05 (autorización de control especial exclusiva del regente) y RN-08 (aprobación de traslados exclusiva del regente). Si admin tuviera acceso por defecto, esas dos reglas se podrían evadir consultando el Gate desde el código de negocio.

## 2. Modelo de Datos

### Tabla: `roles`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | bigint | PK, autoincrement |
| name | varchar(50) | UNIQUE, NOT NULL |
| description | text | permite nulo |
| created_at | timestamp | NOT NULL, asignado por Eloquent |
| updated_at | timestamp | permite nulo, asignado por Eloquent |

**Sin softdeletes.** No hay CRUD de roles: se administran por seeder. Un rol que deja de usarse simplemente no se asigna a nadie.

### Tabla: `modules`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | bigint | PK, autoincrement |
| name | varchar(50) | UNIQUE, NOT NULL |
| description | text | permite nulo |
| created_at | timestamp | NOT NULL, asignado por Eloquent |
| updated_at | timestamp | permite nulo, asignado por Eloquent |

**Sin softdeletes**, por la misma razón que `roles`.

**Los nombres van en inglés** por la convención del proyecto (`AI_USAGE.md` §4.1): tablas y columnas en inglés, documentación y textos de interfaz en español. Cada nombre coincide con la tabla que representa el módulo.

`warehouses` es la excepción: su tabla se llama `bodegas` desde el desarrollo original y renombrarla excede el alcance de esta HU. El módulo es `warehouses` y la ruta sigue siendo `/api/bodegas`. Preferimos un identificador consistente antes que copiar la nomenclatura mixta que ya arrastra el proyecto.

### Tabla: `permissions`

| Columna | Tipo | Restricciones |
|---|---|---|
| id | bigint | PK, autoincrement |
| name | varchar(80) | UNIQUE, NOT NULL |
| module_id | bigint | FK → modules (id), **permite nulo**, onDelete CASCADE |
| description | text | permite nulo |
| created_at | timestamp | NOT NULL, asignado por Eloquent |
| updated_at | timestamp | permite nulo, asignado por Eloquent |

**Sin softdeletes**, por la misma razón que los otros catálogos.

**`name` guarda la clave completa del permiso**, no solo el verbo. Hay dos formas de permiso:

| Forma | `name` | `module_id` |
|---|---|---|
| CRUD | `warehouses.can_read` | `warehouses` |
| Capacidad | `autorizar-control-especial` | `NULL` |

**Por qué el nombre completo y no un par (name, module_id):** con `module_id` nullable, una restricción UNIQUE sobre `(name, module_id)` no protege las capacidades, porque en SQL Server dos filas con `module_id = NULL` se consideran distintas entre sí y el UNIQUE las deja pasar duplicadas. Con un solo `name` UNIQUE no hay ambigüedad ni caso raro.

`module_id` nullable no es decorativo: es lo que distingue un permiso de módulo de una capacidad global, y lo que permite listar "qué puede hacer este rol dentro de bodegas".

### Tabla: `role_permissions` (pivot)

| Columna | Tipo | Restricciones |
|---|---|---|
| role_id | bigint | FK → roles (id), NOT NULL, onDelete CASCADE |
| permission_id | bigint | FK → permissions (id), NOT NULL, onDelete CASCADE |

**PK compuesta:** (role_id, permission_id)

**Sin timestamps ni softdeletes.** Es tabla intermedia pura: sus filas no tienen valor propio, expresan la relación.

**`onDelete CASCADE`** según la regla global de `AI_USAGE.md` §4.2 para tablas transaccionales: si se elimina un rol, un módulo o un permiso, sus asignaciones desaparecen solas en lugar de quedar apuntando a algo que ya no existe.

### Relación `Module::permissions()`

```php
// app/Models/Module.php
public function permissions(): HasMany
{
    return $this->hasMany(Permission::class);
}
```

`HasMany` y no `belongsToMany`. Un módulo tiene muchos permisos; la foreign key `permissions.module_id` apunta hacia `modules`. La dirección de la relación es módulo → permiso, no permiso → módulo. Usar `belongsToMany` genera SQL incorrecto (`WHERE role_permissions.module_id = ?` en vez de `WHERE permissions.module_id = ?`).

### Modificación: tabla `users`

| Columna | Tipo | Restricciones |
|---|---|---|
| role_id | bigint | FK → roles (id), NOT NULL, onDelete NO ACTION |

**La columna `role` (ENUM) se elimina.** `users` no tiene softdeletes: es excepción al patrón del proyecto.

### Tabla eliminada: `role_modules`

La tabla `role_modules` con las columnas `can_read`, `can_create`, `can_update` y `can_delete` **se elimina**. El motivo está en §11 Anexo G.

## 3. Bloqueos y Dependencias

- No tiene dependencias de otras HUs.
- Requiere que la tabla `users` exista y que la migración `2026_10_09_000004_add_role_to_users` haya creado el ENUM que esta HU elimina.
- **Integración:** los Gates de `AuthServiceProvider` dejan de usar el array constante `CAPACIDADES` y pasan a resolverse contra la tabla `permissions`.
- No bloquea ninguna HU existente.

## 4. Variables y Configuración

- Los 5 roles se migran a la tabla `roles` con los mismos `name`.
- Los 7 módulos se crean por seeder, con nombre en inglés y descripción en español.
- Los permisos CRUD (4 por módulo) y las capacidades globales se crean por seeder.
- La matriz de asignaciones rol → permiso se crea por seeder.
- El middleware `EnsureModule` se registra en el Kernel con el alias `module`.
- El alias `role` (EnsureRole) **se elimina**: todas las rutas que lo usaban migran a `module`.

## 5. Suposiciones

1. **Los permisos CRUD no son columnas sino filas.** Un permiso es un dato con nombre. La razón: el proyecto ya tiene permisos que no son CRUD —`aprobar-traslado`, `autorizar-control-especial`, `dispensar`, entre otros— y esos no se pueden expresar como un booleano con nombre de verbo. Meterlos en columnas habría significado agregar una migración y un deploy por cada capacidad nueva.

2. **Un permiso nuevo se agrega con un `INSERT`, no con una migración.** Es la consecuencia directa del punto anterior.

3. **El `name` del permiso es la clave completa** (`warehouses.can_read`), no un par con `module_id`. Ver §2.

4. **`module_id = NULL` significa capacidad global.** Un permiso sin módulo no depende de una ruta ni de un verbo: se consulta con `Gate::allows('autorizar-control-especial')`.

5. **El middleware `EnsureModule` solo resuelve permisos CRUD.** Deduce el verbo, arma el nombre `{modulo}.{sufijo}` y consulta. No resuelve capacidades.

6. **El mapeo verbo → sufijo de permiso** (`GET` → `can_read`, `POST` → `can_create`, `PUT`/`PATCH` → `can_update`, `DELETE` → `can_delete`) vive en `EnsureModule::SUFIJO_POR_VERBO`. No está en el modelo User: la correspondencia es protocolo de transporte HTTP, no lógica de dominio.

7. **El bypass de admin NO existe.** `Gate::before` no hace que admin pase todo. Admin tiene exactamente los permisos que la matriz le asigna. Esto es intencional: RN-05 y RN-08 exigen que admin no pueda autorizar control especial ni aprobar traslados. Sin bypass, esa separación se cumple sin recurrir a código de negocio.

8. **`hasPermission()` está memoizado por instancia de usuario.** En una mismarequest con 30 verificaciones de permiso, se ejecuta 1 consulta SQL en la primera evaluación y 0 en las 29 siguientes. Esto corrige el problema de N+1 en permisos.

9. **La matriz se aplica con `sync()` y no con `syncWithoutDetaching()`.** El primero reemplaza toda la relación para ese rol; el segundo solo agrega. Con `syncWithoutDetaching`, cambiar un permiso de admin (ej.: quitarle escritura a un módulo) no tenía efecto en una base ya sembrada: los permisos viejos quedaban asignados. `sync()` hace que la base sea idempotente con el seeder.

10. **La migración de `role` ENUM a `role_id` FK es irreversible.**

11. **Los módulos que no tienen ningún permiso asignado quedan inaccesibles.**

12. **Las rutas de lecturas (`bodegas/{id}/readings`, `bodegas/{id}/alerts`) pertenecen al módulo `sensors`**, no a `warehouses`. Si pertenecieran a warehouses, un rol con lectura de bodegas pero sin acceso a sensores podría leer alertas ambientales.

13. **`units/select` queda fuera de los grupos de módulo.** Es un catálogo de referencia que alimenta los formularios de productos y de lotes; protegerlo exigiría inventar un módulo que no existe.

## 6. Fuera de Alcance (explícitamente excluido)

- CRUD de administración de roles, módulos o permisos desde la interfaz.
- Endpoints de la API para consultar o modificar roles, módulos o permisos.
- Asignación de permisos por usuario individual (se asigna por rol).
- Implementar las reglas de negocio que consumen las capacidades (por ejemplo, la verificación de RN-05 al dispensar un producto con `requires_authorization`). Esta HU hace que la capacidad exista y sea consultable; quién la dispara en cada flujo es de cada HU.
- Auditoría de cambios de permisos.
- Permisos a nivel de registro (ownership).
- Softdeletes en catálogos.
- Renombrar la tabla `bodegas` a `warehouses`.

## 7. Camino Feliz Principal (paso a paso)

### Inicialización del sistema de permisos

1. Se ejecutan las migraciones: crean `roles`, `modules`, `permissions`, `role_permissions`, transforman `users` y eliminan `role_modules`.
2. Se ejecutan los seeders en orden: `RoleSeeder` → `ModuleSeeder` → `PermissionSeeder` → `RolePermissionSeeder` → `UserSeeder` → `UnitSeeder`.
3. `PermissionSeeder` inserta 4 permisos por módulo (`{modulo}.can_read`, `.can_create`, `.can_update`, `.can_delete`) y las capacidades globales.
4. `RolePermissionSeeder` asigna a cada rol los permisos del Anexo A. Usa `sync()` para que la base quede idempotente con el seeder.

### Verificación de un permiso CRUD por verbo HTTP

1. La ruta vive dentro de `Route::middleware('module:warehouses')->group(...)`.
2. El usuario hace una petición.
3. `EnsureModule` lee el verbo y obtiene el sufijo: `GET` → `can_read`.
4. Arma el nombre completo: `warehouses.can_read`.
5. Consulta si el rol del usuario tiene ese permiso en `role_permissions`.
6. Si lo tiene, continúa al controller. Si no, responde 403.

### Verificación de una capacidad de negocio

1. El código de negocio evalúa `Gate::allows('autorizar-control-especial')`.
2. `Gate::before` intercepta la capacidad. Si la capacidad existe en la base, pregunta a `User::hasPermission()`; si no existe, retorna `null` (no `false`) para que pueda correr otro policy si lo hay.
3. `hasPermission()` consulta `role_permissions` y retorna `true` o `false`.
4. Admin **no** pasa por defecto: si su matriz no incluye la capacidad, el Gate responde `false`.

### Resolución de las capacidades sin consulta en el arranque

1. `AuthServiceProvider::boot()` registra un único `Gate::before`.
2. Cuando se consulta una capacidad, el callback pregunta a `User::hasPermission($nombre)`.
3. La consulta a `permissions` ocurre en ese momento (perezoso), no al arrancar.
4. `hasPermission()` memoiza el resultado la primera vez que se llama por usuario, por request.

La alternativa de recorrer `Permission::pluck('name')` en `boot()` y definir un Gate por permiso **no funciona**: `boot()` corre en cada arranque, incluidos los comandos artisan y el `package:discover` que dispara `composer dump-autoload` durante el build de la imagen, donde no hay base de datos.

### Migración de rutas existentes

```php
Route::middleware('module:warehouses')->group(function () {
    Route::apiResource('bodegas', BodegaController::class);
    Route::patch('bodegas/{bodega}/toggle-status', [BodegaController::class, 'toggleStatus']);
});
```

Aplica a `groups`, `bodegas`, `products`, `lots`, `sensors`, lecturas y `reports`.

## 8. Caminos Tristes (errores esperados)

| # | Escenario | Comportamiento del Sistema |
|---|---|---|
| SP-01 | Usuario sin sesión accede a ruta protegida | Sanctum responde 401 antes de que corra `EnsureModule`. |
| SP-02 | Usuario sin el permiso requerido | 403 con `module` y `required_permission` en el cuerpo. |
| SP-03 | El módulo no existe en el catálogo | 403. Sin módulo no hay permisos que resolver. |
| SP-04 | Rol existe pero no tiene permisos asignados | 403 en todo. Ausencia de fila significa ausencia de permiso. |
| SP-05 | Se evalúa una capacidad que no existe en `permissions` | `null` (no `false`). Permite que otro policy de la cadena procese la pregunta. Si ningún policy la maneja, Sanctum deniega por defecto. |
| SP-06 | La base no responde | 500 con mensaje genérico. |
| SP-07 | Verbo HTTP sin sufijo de permiso asociado (OPTIONS, TRACE) | 403. No se concede nada por no haberlo pensado. |

## 9. Casos Límite y Casos Estúpidos

### Casos Límite

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| CL-01 | Módulo sin ningún permiso en el catálogo | 403 para todos. Nadie entra. |
| CL-02 | Rol con permisos de un módulo y nada de otro | Entra al primero y recibe 403 en el segundo. |
| CL-03 | Se elimina un módulo | `permissions` con ese `module_id` caen por CASCADE, y con ellas sus asignaciones. |
| CL-04 | Se elimina un permiso | Sus asignaciones caen por CASCADE. El rol queda sin ese permiso. |
| CL-05 | Se renombra un módulo | Quedan huérfanos los permisos `{nombre_viejo}.can_read`. Es una operación de datos, no de esquema. |
| CL-06 | Un rol tiene `can_delete` pero no `can_read` | Permitido. Es una matriz que el seeder controla, no una regla del esquema. |
| CL-07 | Muchos usuarios con el mismo rol | Es lo normal: la asignación es rol → permiso. |
| CL-08 | Admin con `gestionar-catalogos` pero sin escritura en `groups`/`products` | La capacidad y la matriz discrepan. Para evitarlo, la matriz de admin incluye escritura en esos módulos. |

### Casos Estúpidos

| # | Caso | Comportamiento del Sistema |
|---|---|---|
| CE-01 | Cambiar el verbo HTTP de la petición | El permiso se deduce del verbo real. |
| CE-02 | Crear un permiso con nombre duplicado | UNIQUE lo rechaza. |
| CE-03 | Asignar a un usuario un rol inexistente | La FK con NO ACTION lo impide. |
| CE-04 | Inventar el nombre de un módulo en una ruta (`module:warehouses2`) | 403. El middleware arma `warehouses2.can_read`, que no existe. |
| CE-05 | Preguntar por una capacidad escrita mal | `null`. Permite que otro policy procese; si ninguno la maneja, Sanctum deniega. |

## 10. Criterios de Aceptación

### CA-01: Tabla Roles Creada
**Dado** que se ejecutan las migraciones de esta HU  
**Cuando** se verifica el esquema  
**Entonces** existe `roles` con `id` (bigint PK), `name` (varchar(50), UNIQUE), `description` (nullable), `created_at`, `updated_at`  PASS / FAIL  
**Y** no tiene `deleted_at`  PASS / FAIL

### CA-02: Tabla Modules Creada
**Dado** que se ejecutan las migraciones  
**Cuando** se verifica el esquema  
**Entonces** existe `modules` con `id` (bigint PK), `name` (varchar(50), UNIQUE), `description` (nullable), `created_at`, `updated_at`  PASS / FAIL  
**Y** no tiene `deleted_at`  PASS / FAIL

### CA-03: Tabla Permissions Creada
**Dado** que se ejecutan las migraciones  
**Cuando** se verifica el esquema  
**Entonces** existe `permissions` con `id` (bigint PK), `name` (varchar(80), UNIQUE), `module_id` (FK → modules, **nullable**, CASCADE), `description` (nullable), `created_at`, `updated_at`  PASS / FAIL  
**Y** `module_id` acepta NULL  PASS / FAIL

### CA-04: Tabla Role_Permissions Creada
**Dado** que se ejecutan las migraciones  
**Cuando** se verifica el esquema  
**Entonces** existe `role_permissions` con `role_id` (FK, CASCADE) y `permission_id` (FK, CASCADE), PK compuesta  PASS / FAIL  
**Y** no tiene `created_at`, `updated_at` ni `deleted_at`  PASS / FAIL

### CA-05: Role_Modules Eliminada
**Dado** que se ejecutan las migraciones  
**Cuando** se verifica el esquema  
**Entonces** la tabla `role_modules` ya no existe  PASS / FAIL

### CA-06: Users.role_id Reemplaza Users.role
**Dado** que existen usuarios con valores ENUM en `role`  
**Cuando** se ejecuta la migración  
**Entonces** la columna `role` se elimina  PASS / FAIL  
**Y** existe `role_id` (bigint, FK → roles, NOT NULL)  PASS / FAIL  
**Y** cada usuario conserva el rol equivalente al ENUM que tenía  PASS / FAIL

### CA-07: Seeder Crea 5 Roles
**Dado** que se ejecutan los seeders  
**Cuando** se consulta `roles`  
**Entonces** existen exactamente 5: `admin`, `regente_farmacia`, `auxiliar_farmacia`, `medico`, `auditor`  PASS / FAIL

### CA-08: Seeder Crea 7 Módulos Con Nombre En Inglés
**Dado** que se ejecutan los seeders  
**Cuando** se consulta `modules`  
**Entonces** existen exactamente 7: `warehouses`, `groups`, `products`, `lots`, `sensors`, `reports`, `users`  PASS / FAIL  
**Y** ninguno tiene un nombre en español  PASS / FAIL

### CA-09: Permisos CRUD Son Filas
**Dado** que se ejecutan los seeders  
**Cuando** se consultan los permisos con `module_id` no nulo  
**Entonces** existen 4 por cada uno de los 7 módulos: `{modulo}.can_read`, `.can_create`, `.can_update`, `.can_delete`  PASS / FAIL  
**Y** el total es 28  PASS / FAIL

### CA-10: Capacidades Son Permisos Globales
**Dado** que se ejecutan los seeders  
**Cuando** se consultan los permisos con `module_id` nulo  
**Entonces** existen las capacidades del Anexo B, entre ellas `autorizar-control-especial`  PASS / FAIL  
**Y** ninguna contiene un punto en el nombre  PASS / FAIL

### CA-11: Agregar un Permiso No Requiere Migración
**Dado** que el sistema está migrado  
**Cuando** se inserta un permiso nuevo y se asigna a un rol  
**Entonces** el permiso queda operativo sin ejecutar migraciones ni modificar código  PASS / FAIL

### CA-12: Seeder Asigna la Matriz
**Dado** que se ejecutan los seeders  
**Cuando** se consulta `role_permissions`  
**Entonces** las asignaciones corresponden al Anexo A  PASS / FAIL

### CA-13: El Provider No Consulta la Base al Arrancar
**Dado** que se inspecciona `AuthServiceProvider`  
**Cuando** se busca una consulta a la base dentro de `boot()`  
**Entonces** no existe: la resolución es perezosa vía `Gate::before`  PASS / FAIL  
**Y** el array constante `CAPACIDADES` no existe en el código  PASS / FAIL  
**Y** `Gate::before` no contiene bypass para admin  PASS / FAIL

### CA-14: Gate de Capacidad Resuelve Por Permiso
**Dado** que el permiso `autorizar-control-especial` está asignado a `regente_farmacia`  
**Cuando** un regente evalúa `Gate::allows('autorizar-control-especial')`  
**Entonces** devuelve `true`  PASS / FAIL  
**Y** un auxiliar evalúa `false`  PASS / FAIL

### CA-15: Capacidad Desconocida Retorna Null
**Dado** que se consulta una capacidad que no existe en `permissions`  
**Cuando** se evalúa el Gate  
**Entonces** retorna `null` (no `false`) y no lanza excepción  PASS / FAIL

### CA-16: Middleware GET Exige can_read
**Dado** que un rol no tiene `warehouses.can_read`  
**Cuando** se hace GET a una ruta con `module:warehouses`  
**Entonces** responde 403  PASS / FAIL

### CA-17: Middleware POST Exige can_create
**Dado** que un rol no tiene `warehouses.can_create`  
**Cuando** se hace POST  
**Entonces** responde 403  PASS / FAIL

### CA-18: Middleware PUT/PATCH Exige can_update
**Dado** que un rol no tiene `warehouses.can_update`  
**Cuando** se hace PUT o PATCH  
**Entonces** responde 403  PASS / FAIL

### CA-19: Middleware DELETE Exige can_delete
**Dado** que un rol no tiene `warehouses.can_delete`  
**Cuando** se hace DELETE  
**Entonces** responde 403  PASS / FAIL

### CA-20: Lectura Sin Escritura En El Mismo Grupo
**Dado** que un rol tiene `warehouses.can_read` pero no `warehouses.can_create`  
**Cuando** se hace POST a una ruta del mismo grupo  
**Entonces** responde 403  PASS / FAIL  
**Y** el GET a ese mismo grupo responde 200  PASS / FAIL

### CA-21: Sin Token Responde 401
**Dado** que no hay usuario autenticado  
**Cuando** se accede a una ruta con `auth:sanctum` y `module:warehouses`  
**Entonces** Sanctum responde 401 antes de que corra `EnsureModule`  PASS / FAIL

### CA-22: Permiso Se Comprueba Antes De Resolver El Modelo
**Dado** que un usuario sin permiso pide DELETE sobre un id inexistente  
**Cuando** se evalúa la petición  
**Entonces** responde 403 y no 404  PASS / FAIL  
**Y** con permiso responde 404  PASS / FAIL

### CA-23: Relación Module → Permissions
**Dado** que existe el modelo `Module`  
**Cuando** se consulta `$modulo->permissions`  
**Entonces** retorna la colección de permisos (no lanza SQL incorrecto)  PASS / FAIL

### CA-24: User Tiene Método hasPermission
**Dado** que existe `User::hasPermission(string $nombre)`  
**Cuando** se llama con el nombre de un permiso asignado y de uno no asignado  
**Entonces** devuelve `true` y `false` respectivamente  PASS / FAIL

### CA-25: Middleware Registrado En Kernel
**Dado** que se verifica `app/Http/Kernel.php`  
**Cuando** se busca el alias `module`  
**Entonces** está registrado como `EnsureModule::class`  PASS / FAIL  
**Y** el alias `role` (EnsureRole) no existe  PASS / FAIL

### CA-26: Rutas De Formulario Siguen Existiendo
**Dado** que se verifica `routes/api.php`  
**Cuando** se consultan `/api/units/select`, `/api/groups/select`, `/api/products/form-data`, `/api/lots/form-data` y `/api/sensors/form-data`  
**Entonces** todas existen y responden 200 para un admin  PASS / FAIL

### CA-27: Descripciones En Español
**Dado** que se consultan `roles`, `modules` y `permissions`  
**Cuando** se verifica `description`  
**Entonces** cada registro tiene una descripción no vacía, en español  PASS / FAIL

### CA-28: Seeders En El Orden Correcto
**Dado** que se verifica `DatabaseSeeder`  
**Cuando** se inspecciona `run()`  
**Entonces** el orden es `RoleSeeder`, `ModuleSeeder`, `PermissionSeeder`, `RolePermissionSeeder`, `UserSeeder`, `UnitSeeder`  PASS / FAIL

## 11. Anexos

### Anexo A: Matriz de Permisos CRUD

`C` = can_create, `R` = can_read, `U` = can_update, `D` = can_delete. `—` = sin permiso.

```
ROL                 | WAREHOUSES | GROUPS | PRODUCTS | LOTS | SENSORS | REPORTS | USERS
--------------------|------------|--------|----------|------|---------|---------|------
admin               | CRUD       | CRUD   | CRUD     | R    | R       | R       | CRUD
regente_farmacia    | CRUD       | CRUD   | CRUD     | CRUD | R       | R       | —
auxiliar_farmacia   | R          | R      | R        | CRU  | —       | R       | —
medico              | R          | —      | R        | R    | —       | R       | —
auditor             | R          | R      | R        | R    | R       | R       | —
```

**Decisión de diseño tras auditoría:** admin tiene escritura en administrativos (warehouses, groups, products) y solo lectura en el flujo clínico (lots, sensors, reports). Esto no es arbitrario: RN-05 y RN-08 exigen que el stock (lotes) no se pueda mover sin un regente. Si admin tuviera escritura en stock, esas reglas serían evadibles consultando el Gate desde el código de negocio.

La matriz es fiel al Anexo de la HU original en `PENDIENTE_auditoria.md`. La capacidad `gestionar-catalogos` queda coherente con ella: admin escribe grupos y productos, y tiene la capacidad que lo declara.

### Anexo B: Capacidades (Permisos Globales)

Se crean con `module_id = NULL`. Traducen la sección 3 del enunciado.

| Capacidad | Roles | Origen |
|---|---|---|
| `dispensar` | auxiliar_farmacia, regente_farmacia | enunciado |
| `consultar-inventario` | auxiliar_farmacia, regente_farmacia, medico, auditor | enunciado |
| `crear-traslado` | auxiliar_farmacia, regente_farmacia | enunciado |
| `recibir-traslado` | auxiliar_farmacia, regente_farmacia | enunciado |
| `aprobar-traslado` | regente_farmacia | RN-08 |
| `autorizar-control-especial` | regente_farmacia | RN-05 |
| `ajustar-inventario` | regente_farmacia | enunciado |
| `crear-prescripcion` | medico | enunciado |
| `consultar-pacientes` | medico | enunciado |
| `consultar-pacientes-enmascarados` | auditor | RN-10 |
| `gestionar-usuarios` | admin | enunciado |
| `gestionar-catalogos` | admin | enunciado |

**Sin bypass.** `Gate::before` no hace que admin pase todas las capacidades. Las capacidades exclusivas del regente (`aprobar-traslado`, `autorizar-control-especial`) están asignadas solo al regente, no a admin. Esto es lo que permite que RN-05 y RN-08 se cumplan sin hardcodear nombres de rol en el código de negocio.

### Anexo C: Registro de Migraciones

| Orden | Migración | Acción |
|---|---|---|
| 1 | `2026_10_09_000005_create_roles_table.php` | Crear `roles` |
| 2 | `2026_10_09_000006_create_modules_table.php` | Crear `modules` |
| 3 | `2026_10_09_000007_create_role_modules_table.php` | Crear `role_modules` (intermedia, se elimina después) |
| 4 | `2026_10_09_000008_migrate_users_role_to_role_id.php` | ENUM → FK |
| 5 | `2026_10_09_000009_create_permissions_table.php` | Crear `permissions` |
| 6 | `2026_10_09_000010_create_role_permissions_table.php` | Crear `role_permissions` |
| 7 | `2026_10_09_000011_drop_role_modules_table.php` | Eliminar `role_modules` |

La 007 y la 011 conviven: 007 documenta el modelo que se descartó y 011 lo retira. Preferible a reescribir 007, que alteraría migraciones ya ejecutadas.

### Anexo D: Registro de Seeders

| Orden | Seeder | Acción |
|---|---|---|
| 1 | `RoleSeeder` | 5 roles |
| 2 | `ModuleSeeder` | 7 módulos con nombre en inglés |
| 3 | `PermissionSeeder` | 28 permisos CRUD + 12 capacidades |
| 4 | `RolePermissionSeeder` | Matriz del Anexo A + capacidades del Anexo B |

`RolePermissionSeeder` usa `sync()` para garantizar idempotencia: la base queda exactamente como el seeder la declara, incluyendo la quita de permisos.

`PermissionSeeder` no declara constantes propias; usa `RolePermissionSeeder::ACCIONES` para crear el catálogo. Una sola definición de los sufijos CRUD.

### Anexo E: Cambios en Archivos Existentes

| Archivo | Cambio |
|---|---|
| `app/Models/User.php` | `role()` relación, `hasPermission()` memoizado, `nombresPermiso()` para exportar al frontend |
| `app/Models/Role.php` | Relación `permissions()` (belongsToMany) |
| `app/Models/Module.php` | Relación `permissions()` (**HasMany**, no belongsToMany) |
| `app/Models/Permission.php` | `catalogo()`, `olvidarCatalogo()`, `crudName()` |
| `app/Http/Middleware/EnsureModule.php` | `SUFIJO_POR_VERBO` (antes en User), `$user->can()` en vez de consultar el modelo directamente |
| `app/Providers/AuthServiceProvider.php` | `Gate::before` sin bypass, resolución perezosa contra `hasPermission()` |
| `app/Http/Kernel.php` | Alias `module` → `EnsureModule::class`; alias `role` eliminado |
| `routes/api.php` | Se mantiene la agrupación por módulo, con nombres en inglés |
| `app/Http/Resources/UserResource.php` | `'role' => $this->role?->name` (futuro: agregar `'permissions' => $this->nombresPermiso()` para el frontend) |
| `database/factories/UserFactory.php` | `conRol()` usa `role_id` |
| `database/seeders/DatabaseSeeder.php` | Nuevo orden de seeders |
| `database/seeders/UserSeeder.php` | `role_id` |

### Anexo F: Archivos Eliminados

| Archivo | Motivo |
|---|---|
| `app/Models/RoleModule.php` | Reemplazado por `Permission` y `role_permissions` |
| `database/seeders/RoleModuleSeeder.php` | Reemplazado por `PermissionSeeder` + `RolePermissionSeeder` |
| `app/Models/User.php` — `hasModulePermission()` | Código muerto tras auditoría: era redundante con `hasPermission()` |
| `app/Models/User.php` — `isAdmin()` | Código muerto: el bypass de admin se eliminó |
| `app/Models/User.php` — `hasAnyRole()` | Código muerto: no se usa en ningún lugar |
| `app/Models/User.php` — `PERMISOS_POR_VERBO` | Movido a `EnsureModule::SUFIJO_POR_VERBO` (capa de transporte, no dominio) |

### Anexo G: Por Qué Se Descartó `role_modules`

El primer diseño de esta HU usaba `role_modules(role_id, module_id, can_read, can_create, can_update, can_delete)`. Se descartó por dos motivos:

1. **No modelaba los permisos que el proyecto ya tenía.** El enunciado define capacidades como `autorizar-control-especial` y `aprobar-traslado` que no son crear, leer, editar ni borrar. Con cuatro columnas no había dónde ponerlas: o se añadía una columna por capacidad —migración y deploy por cada una— o se quedaban en el array `CAPACIDADES` de `AuthServiceProvider`, que es exactamente el problema que esta HU vino a eliminar con los roles.

2. **Las capacidades no pertenecen a un módulo ni a un verbo HTTP.** Se piden con `Gate::allows(...)`, no por ruta. Encajarlas en `role_modules` habría obligado a inventar un módulo artificial para ellas.

Con `permissions` como tabla, un permiso es una fila con nombre. Agregar uno es un `INSERT`, y una capacidad es simplemente un permiso sin `module_id`.

### Anexo H: Contrato con el Frontend

`UserResource` expone `role` como string (el `name` del rol). Ese contrato se mantiene.

Cuando el frontend necesite botones condicionales o protección de rutas sin quemar nombres de rol, conviene extender el recurso:

```php
// app/Http/Resources/UserResource.php
return [
    'id'    => $this->id,
    'name'  => $this->name,
    'role'  => $this->role?->name,
    'permissions' => $this->nombresPermiso(),  // array de strings
];
```

Esto requiere coordinación con el equipo frontend: cambia la respuesta de `/api/user` (es un cambio rompe). Documentar en el changelog de la API antes de mergear.

### Anexo I: Pruebas

| Archivo | Cubre |
|---|---|
| `tests/Feature/EnsureModuleTest.php` | El middleware aislado, con rutas definidas en el test |
| `tests/Feature/ApiRoutePermissionsTest.php` | Las rutas reales de `api.php`, admin escribe donde corresponde y no en stock |
| `tests/Feature/PermissionMatrixTest.php` | El catálogo y la matriz; verifica que `sync()` quite permisos y que la matriz sea idempotente |
| `tests/Feature/PermissionCapabilitiesTest.php` | Las capacidades por Gate y el alta de permisos sin migración |
| `tests/Feature/UserRoleTest.php` | Admin solo tiene lo que la matriz le asigna, sin bypass |

`EnsureModuleTest` usa rutas propias para aislar el middleware. `ApiRoutePermissionsTest` existe porque una prueba aislada no detecta un módulo mal agrupado en `api.php`: el middleware seguiría funcionando y el test no lo vería.

### Anexo J: Decisiones de Auditoría Post-Implementación

Durante la auditoría de la HU implementada se encontraron y corrigieron los siguientes puntos:

| # | Hallazgo | Corrección |
|---|---|---|
| 1 | `Module::permissions()` usaba `belongsToMany`, generando SQL incorrecto (`WHERE role_permissions.module_id = ?` en vez de `WHERE permissions.module_id = ?`) | Cambiado a `HasMany` |
| 2 | `syncWithoutDetaching` en `RolePermissionSeeder` nunca quitaba permisos: al cambiar admin a solo lectura, los permisos de escritura seguían en la base (la suite no lo detectaba por `RefreshDatabase`) | Reescrito para acumular el conjunto completo por rol y usar `sync()` al final |
| 3 | Admin con bypass en `Gate::before` podía evadir RN-05 y RN-08 | Bypass eliminado; la matriz le da solo lo que necesita |
| 4 | `SUFIJO_POR_VERBO` vivía en `User` (dominio) cuando es protocolo de transporte HTTP | Movido a `EnsureModule` |
| 5 | `hasModulePermission()`, `crudPermissions()`, `isAdmin()`, `hasAnyRole()` eran código muerto en `User` | Eliminados |
| 6 | `gestionar-catalogos` quedaba huérfana: admin tenía la capacidad declarada pero solo lectura en groups/products | La matriz de admin ahora incluye escritura en warehouses, groups y products |
| 7 | Constantes `ACCIONES`, `LECTURA`, `LECTURA_Y_ESCRITURA` duplicadas en `PermissionSeeder` y `RolePermissionSeeder` | Consolidada la definición en `RolePermissionSeeder`; `PermissionSeeder` la referencia |
