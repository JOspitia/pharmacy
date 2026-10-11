# Permisos del sistema — Roles y Matriz de Acceso

> Generado: HU-07 (Auth), HU-08 (Kardex + Inventarios), HU-09 (Traslados entre bodegas)
> Fuente: `back/database/seeders/RolePermissionSeeder.php`

---

## Usuarios del sistema

Todos comparten la misma contraseña: **`password`**

| Usuario   | Rol               | Notas |
|-----------|-------------------|-------|
| `admin`   | admin             | Gestiona usuarios y catálogos |
| `regente` | regente_farmacia  | Opera el día a día |
| `auxiliar`| auxiliar_farmacia  | Carga y consulta stock |
| `medico`  | medico            | Consulta inventario y recetas |
| `auditor` | auditor           | Solo lectura, auditoría |

---

## CRUD por módulo

| Módulo       | admin | regente | auxiliar | médico | auditor |
|--------------|-------|---------|----------|--------|---------|
| **Bodegas**  | C R U D | C R U D | R | R | R |
| **Grupos**   | C R U D | C R U D | R | — | R |
| **Productos**| C R U D | C R U D | R | R | R |
| **Lotes**    | R | C R U D | R U | R | R |
| **Kardex**   | R C | R C | R | R | R |
| **Traslados**| R | C R U D | R C U | R | R |
| **Sensores** | R | R | — | — | R |
| **Reportes** | R | R | R | R | R |
| **Usuarios** | C R U D | — | — | — | — |

> **C** = can_create · **R** = can_read · **U** = can_update · **D** = can_delete
>
> Constantes del seeder: `ACCIONES = [read,create,update,delete]` ·
> `LECTURA = [read]` · `LECTURA_Y_ESCRITURA = [read,create,update]`

### Notas

- **Kardex**: `can_update` y `can_delete` NO existen para ningún rol (RN-06: Kardex inmutable).
- **Admin en Lotes**: solo lectura. No crea ni modifica lotes; eso es responsabilidad del regente.
- **Auxiliar en Lotes**: `LECTURA_Y_ESCRITURA` = puede crear, leer y corregir lotes, pero NO eliminar (el historial no se borra desde la interfaz).
- **Auxiliar en Bodegas**: solo `LECTURA`. No puede crear, actualizar ni eliminar bodegas.
- **Auxiliar en Kardex**: solo `LECTURA`. Puede ver movimientos pero no crear nuevos.
- **Médico en Grupos**: no tiene acceso. Un médico no necesita ver grupos de productos.
- **Traslados (HU-09)**: el slug del módulo en el seeder es `transfers`.
  - **Admin, médico y auditor**: solo `LECTURA`. Ninguno opera traslados.
  - **Regente**: `ACCIONES` completas — es el único rol que recorre el ciclo completo (solicita, aprueba, despacha, recibe, anula).
  - **Auxiliar**: `['can_read', 'can_create', 'can_update']`. Sin `can_delete` porque el historial de traslados no se borra desde la interfaz. No tiene `anular-traslado`: anular es una decisión autoritativa y le corresponde al regente, igual que aprobar y despachar.
- **Segregación de duties (RN-08)**: quien solicita un traslado **no** puede aprobarlo, aunque tenga el permiso `aprobar-traslado`. Con los usuarios de demo (un único regente), esto significa que un traslado creado por `regente` **no puede ser aprobado por nadie**: hay que crearlo con `auxiliar` y aprobarlo con `regente`.

---

## Capacidades globales

| Capacidad                     | admin | regente | auxiliar | médico | auditor |
|-------------------------------|-------|---------|----------|--------|---------|
| gestionar-usuarios             | ✅    | —       | —        | —      | —       |
| gestionar-catalogos            | ✅    | —       | —        | —      | —       |
| dispensar                      | —     | ✅      | ✅       | —      | —       |
| consultar-inventario           | —     | ✅      | ✅       | ✅      | ✅      |
| crear-prescripcion             | —     | —       | —        | ✅      | —       |
| consultar-pacientes            | —     | —       | —        | ✅      | —       |
| consultar-pacientes-enmascarados | —   | —       | —        | —      | ✅      |
| consultar-traslados            | ✅    | ✅      | ✅       | ✅      | ✅      |
| crear-traslado                 | —     | ✅      | ✅       | —      | —       |
| aprobar-traslado               | —     | ✅      | —        | —      | —       |
| despachar-traslado             | —     | ✅      | —        | —      | —       |
| recibir-traslado               | —     | ✅      | ✅       | —      | —       |
| anular-traslado                | —     | ✅      | —        | —      | —       |
| resolver-discrepancia          | —     | ✅      | —        | —      | —       |
| autorizar-control-especial     | —     | ✅      | —        | —      | —       |
| ajustar-inventario             | —     | ✅      | —        | —      | —       |

---

## Reglas de negocio que definen la matriz

- **RN-06**: Kardex es inmutable → `can_update` y `can_delete` no existen en el módulo `kardex` para ningún rol.
- **RN-05 / RN-08**: El stock vive en el Kardex, no en el lote → el auxiliar crea movimientos, no lotes directamente.
- **RN-08 (HU-09)**: El aprobador de un traslado debe ser distinto de quien lo solicitó. Es una separación de funciones: quien pide el movimiento no es quien lo autoriza. El permiso `aprobar-traslado` por sí solo no alcanza — el backend compara `requested_by` con el usuario autenticado y rechaza con 422.
- **HU-08 §7**: Admin y regente consultan e ingresan movimientos de Kardex.
- Un admin sin `gestionar-catalogos` contradictorio → admin tiene escritura en groups y products.
- Auditor: solo lectura en todo, como corresponde a un rol de auditoría.

---

## Cómo editar esta matriz

Editar `back/database/seeders/RolePermissionSeeder.php`:

- `ACCIONES = ['can_read', 'can_create', 'can_update', 'can_delete']` — todos los permisos
- `LECTURA = ['can_read']` — solo consulta
- `LECTURA_Y_ESCRITURA = ['can_read', 'can_create', 'can_update']` — sin borrado

Luego correr `docker compose exec backend php artisan db:seed --class=RolePermissionSeeder`.
