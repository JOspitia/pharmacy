# AI_USAGE.md — Sistema Farmacéutico de Gestión de Inventario

## Índice

1. [Herramientas de IA utilizadas](#1-herramientas-de-ia-utilizadas)
2. [Metodología de trabajo con IA](#2-metodología-de-trabajo-con-ia)
3. [Sistema de subagentes especializados](#3-sistema-de-subagentes-especializados)
4. [Decisiones de arquitectura global](#4-decisiones-de-arquitectura-global)
5. [Registro de prompts significativos](#5-registro-de-prompts-significativos)
6. [Decisiones del desarrollador vs. sugerencias de IA](#6-decisiones-del-desarrollador-vs-sugerencias-de-ia)
7. [Historial completo de Historias de Usuario](#7-historial-completo-de-historias-de-usuario)
8. [Lecciones aprendidas](#8-lecciones-aprendidas)
9. [Segunda etapa: estabilización y cierre de requisitos](#9-segunda-etapa-estabilización-y-cierre-de-requisitos)
   - [Qué se aceptó de las sugerencias](#91-qué-se-aceptó-de-las-sugerencias)
   - [Qué se corrigió](#92-qué-se-corrigió)
   - [Verificación previa a aplicar](#93-verificación-previa-a-aplicar)
   - [Correcciones de documentación](#94-correcciones-de-documentación)
10. [Tercera etapa: migración a SQL Server](#10-tercera-etapa-migración-de-mysql-8-a-sql-server-2025)

---

## 1. Herramientas de IA utilizadas

| Herramienta | Rol | Uso |
|---|---|---|
| **OpenCode** (CLI) | Entorno principal | Orquestación de subagentes, edición de archivos, ejecución de comandos |
| **Jarvis** (Agente Principal) | Analista funcional senior | Guía la creación de HUs con metodología de 9 pasos |
| **Sherlock** (Subagente) | Analista de HUs | Detecta huecos, ambigüedades y escenarios faltantes en archivos `.md` |
| **Tolkien** (Subagente) | Escritor de HUs | Crea y actualiza archivos `.md` siguiendo el template estricto de 10 secciones |
| **Brainiac** (Subagente) | Revisor adversarial | Auditoría final de HUs completas antes de aprobación |
| **Engram** (MCP) | Memoria persistente | Guarda decisiones, descubrimientos y convenciones entre sesiones |

---

## 2. Metodología de trabajo con IA

El proceso de creación de cada historia de usuario siguió un método estructurado de **9 pasos** guiado por Jarvis:

| Paso | Nombre | Objetivo |
|---|---|---|
| 1 | Bloqueos y dependencias | Identificar qué debe existir antes de empezar |
| 2 | Variables y configuración | Definir qué cambia por ambiente, rol o contexto |
| 3 | Suposiciones | Explicitar lo que se asume como verdadero |
| 4 | Fuera de alcance | Delimitar explícitamente lo que NO se construye |
| 5 | Camino feliz | Describir el flujo ideal paso a paso |
| 6 | Caminos tristes | Definir errores esperados y su manejo |
| 7 | Casos límite | Valores cero, vacíos, máximos, concurrencia |
| 8 | Casos estúpidos | Lo que ningún usuario "normal" haría pero alguien hará |
| 9 | Criterios de aceptación | Binarios, formato Dado/Cuando/Entonces/Y + PASS/FAIL |

Cada paso se resolvió mediante conversación uno a uno entre el desarrollador y Jarvis. **La IA nunca tomó decisiones de diseño por sí misma**: preguntó, el desarrollador respondió, y la IA documentó.

---

### 2.1 Validación de Código y Control de Calidad

El proceso de desarrollo no se limitó únicamente a la redacción de Historias de Usuario, sino que incluyó un ciclo riguroso de validación continua entre la documentación y el código implementado, utilizando múltiples modelos de IA de forma complementaria:

* **OpenCode (Go Edition):** Se utilizó la versión Go de OpenCode ([https://opencode.ai/docs/es/go](https://opencode.ai/docs/es/go)) como entorno base para la ejecución de validaciones.
* **Agente Jarvis (Auditor de Cumplimiento):** El agente Jarvis fue empleado estratégicamente para cruzar y comparar el código fuente desarrollado contra los Criterios de Aceptación (CAs) documentados en los archivos `.md`. Su función principal fue actuar como un orquestador de validación, encargado de:
    * Verificar el cumplimiento estricto de las reglas de negocio (ej. bloqueos de eliminación, validaciones de stock).
    * Auditar los estándares de calidad del código (nomenclatura, convenciones de Eloquent, tipado en TypeScript).
    * Detectar discrepancias o "novedades" entre lo que dictaba la HU y lo que ejecutaba el código.
* **Modelo Gentle AI:** Para el análisis y sugerencia de correcciones ante errores o excepciones de código, se recurrió adicionalmente al modelo Gentle AI ([https://github.com/Gentleman-Programming/gentle-ai](https://github.com/Gentleman-Programming/gentle-ai)), aportando una segunda capa de revisión técnica especializada.

**Flujo de resolución de novedades:**
1.  **Detección:** El agente orquestador (Jarvis/OpenCode) analiza el código y notifica al usuario si existe una desviación respecto a la HU o un error de compilación/ejecución.
2.  **Análisis:** Se revisa la notificación y se apoya en los modelos para entender la causa raíz (ej. errores de hidratación de componentes, excepciones de SQL).
3.  **Ejecución Manual:** Una vez identificada la acción de mejora, el desarrollador aplica la corrección manual en el código fuente, garantizando el control total sobre la lógica implementada y asegurando que la solución se alinee con la arquitectura global del sistema.

---

## 3. Sistema de subagentes especializados

Para evitar saturación del contexto y mantener la calidad del análisis, se utilizó un sistema de delegación a subagentes especializados:

```
Usuario ←→ Jarvis (orquestador)
              │
              ├── Sherlock: análisis de gaps en HUs existentes
              ├── Tolkien:   escritura/edición de archivos .md
              └── Brainiac:  revisión adversarial final
```

**Reglas de delegación:**
- Más de 3 ediciones a archivos en una tarea → delegar a Tolkien
- Leer un HU > 20 líneas para análisis → delegar a Sherlock
- Revisión adversarial de un HU completo → delegar a Brainiac
- Nunca editar más de 3 archivos inline sin delegar

---

## 4. Decisiones de arquitectura global

### 4.1 Convención de Nombres (Eloquent-Compatible)

**Decisión del desarrollador:** adoptar nombres en inglés para todas las tablas y columnas, manteniendo el documento en español.

**Criterio:** máxima compatibilidad con el ORM Eloquent de Laravel sin necesidad de declarar columnas de timestamps manualmente. Las etiquetas de interfaz y mensajes de error permanecen en español por ser el idioma del usuario final.

| Elemento | Convención | Ejemplo |
|---|---|---|
| Tablas | Plural, snake_case | `products`, `bodegas`, `lots`, `groups`, `sensors` |
| Columnas | snake_case en inglés | `name`, `description`, `expiration_date`, `invima_registration` |
| Timestamps | `created_at`, `updated_at`, `deleted_at` | Manejo automático por Eloquent |
| FK usuario creador | `created_by` | Requiere `belongsTo(User::class, 'created_by')` explícito |
| FK usuario modificador | `updated_by` | Requiere `belongsTo(User::class, 'updated_by')` explícito |

### 4.2 Regla Global de onDelete

**Decisión del desarrollador:** definir una regla consistente de `onDelete` para todas las migraciones, demostrando pensamiento sistémico por encima de decisiones tabla por tabla.

| Tipo de FK | onDelete | Justificación |
|---|---|---|
| **Campos de auditoría** (`created_by`) | `RESTRICT` | No se puede borrar un usuario que creó registros. Rompería la trazabilidad. |
| **Campos de auditoría** (`updated_by`) | `SET NULL` | Si se borra el usuario que modificó, el campo queda nulo sin perder el resto del registro. |
| **Entidades de negocio** (FK entre entidades principales) | `RESTRICT` | Coincide con los bloqueos definidos en las HUs. Ej: no se puede eliminar un producto si tiene lotes con stock > 0. La BD respalda la regla de negocio. |
| **Tablas intermedias/transaccionales** (pivot, lecturas) | `CASCADE` | Si se hace un force delete de una entidad, sus datos dependientes sin valor independiente deben desaparecer para no dejar huérfanos. |

### 4.3 Estados derivados vs. almacenados

**Decisión del desarrollador:** los estados de lote ("Con stock", "Por vencer", "Vencido") y las alertas de temperatura/humedad son **computados al consultar**, no persistidos como columnas.

**Criterio:** evita redundancia de datos, garantiza consistencia (el estado siempre refleja la realidad actual de `stock` y `expiration_date`), y simplifica el modelo. La lógica de cómputo pertenece al backend en las consultas de HU-02.

### 4.4 Separación Producto vs. Lote

**Decisión del desarrollador:** el producto es el catálogo maestro (nombre, CUM, código de barras, Invima, grupo, unidad). Las fechas de vencimiento y el stock pertenecen estrictamente a la entidad Lote. Esta separación refleja la realidad del sector farmacéutico donde un mismo medicamento puede tener múltiples lotes con diferentes fechas de fabricación y vencimiento.

### 4.5 Documento en Español, Código en Inglés

- Los archivos `.md` están redactados en español para legibilidad del equipo y stakeholders.
- Las referencias técnicas (columnas, tablas, FK, constraints) están en inglés.
- Criterios de aceptación usan formato `Dado/Cuando/Entonces/Y` + `PASS / FAIL`.

---

## 5. Registro de prompts significativos

### 5.1 Definición del modelo de lotes y productos

**Contexto:** Al modelar el producto, se debatió si las fechas de vencimiento y el stock debían ir en Producto o en Lote.

**Prompt del desarrollador:** *"La respuesta corta es: La lógica de negocio y las fechas pertenecen estrictamente a la entidad de Lotes, pero la consulta (Query) debe unificar ambas entidades mediante un JOIN o una relación de Eloquent."*

**Criterio aplicado:** El desarrollador investigó las buenas prácticas del dominio farmacéutico y determinó que el producto es el catálogo maestro (datos que no cambian entre lotes) y el lote contiene los datos variables (fecha de vencimiento, stock, bodega). Esto llevó a redefinir HU-05 completamente, pasando de una simple parametrización a la entidad central del inventario.

### 5.2 Convención Eloquent para compatibilidad

**Contexto:** El modelo inicial usaba nombres de columna en español (`nombre`, `descripcion`, `estado`, `usuario_registra`, `fecha_registro`).

**Prompt del desarrollador:** *"Usar la lógica de eloquent para no tener que declarar algo manualment"*

**Criterio aplicado:** El desarrollador evaluó el trade-off entre nombres en español (más legibles para el equipo) vs. compatibilidad con Eloquent (menos código boilerplate). Decidió que la productividad del ORM justificaba el cambio a inglés. Se adoptó `name`, `description`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`.

### 5.3 Estados derivados vs. almacenados

**Contexto:** El requerimiento pedía filtrar productos por estado del lote: "Con stock", "Por vencer (30 días)", "Vencido", "Todos".

**Prompt del desarrollador:** *"se podría manejar de esta forma 'Con stock', 'Por vencer (30 días)', 'Vencido', 'Todos': Definición lógica de los estados..."*

**Criterio aplicado:** El desarrollador definió cada estado como una condición computada en tiempo de consulta, no como una columna. Esto evita inconsistencias cuando el stock cambia o la fecha de vencimiento se alcanza sin necesidad de un proceso batch que actualice estados.

### 5.4 Regla de onDelete global

**Contexto:** Al definir las relaciones entre tablas, se discutió qué `onDelete` usar en cada FK.

**Prompt del desarrollador:** *"Regla General de onDelete para tu Arquitectura: Campos de Auditoría (created_by, updated_by): restrict... Entidades de Negocio: restrict... Tablas Intermedias / Transaccionales: cascade..."*

**Criterio aplicado:** El desarrollador definió una regla arquitectónica global en lugar de decidir tabla por tabla. Esto demuestra pensamiento sistémico y asegura consistencia en todo el proyecto.

### 5.5 Separación de consultas de sensores

**Contexto:** Al definir HU-02 (Búsqueda y Filtrado), se consideró incluir las lecturas de sensores en la misma pantalla de reporte de inventario.

**Prompt del desarrollador:** *"mejor la pestaña 2 implementarla dentro de bodegas para no unificar productos con sensores"*

**Criterio aplicado:** El desarrollador identificó que mezclar productos (inventario) con lecturas de sensores (monitoreo ambiental) en una misma pantalla generaba confusión de dominio. Decidió mantener la consulta de sensores dentro del contexto de Bodegas (HU-04), donde el usuario ya está gestionando esa entidad.

### 5.6 Umbrales de alerta estrictos

**Contexto:** Al definir los umbrales de temperatura (25°C) y humedad (70%), se debatió si el valor exacto disparaba alerta.

**Prompt del desarrollador:** *"es supera entonces mayor que 70%"*

**Criterio aplicado:** El desarrollador definió umbrales estrictos (`>` no `>=`): 25.0°C no alerta, 25.1°C sí. Esto evita falsos positivos en el límite exacto y es más conservador con las alertas.

---

## 6. Decisiones del desarrollador vs. sugerencias de IA

| Decisión | ¿Quién la tomó? | Contexto |
|---|---|---|
| Separar Producto (catálogo) de Lote (inventario) | Desarrollador | Investigación del dominio farmacéutico |
| Adoptar nombres en inglés para Eloquent | Desarrollador | Evaluación de trade-off legibilidad vs. productividad |
| Estados de lote computados, no columnas | Desarrollador | Análisis de consistencia de datos |
| Regla global de onDelete | Desarrollador | Pensamiento arquitectónico sistémico |
| Separar consulta de sensores de reporte de inventario | Desarrollador | Separación de dominios (inventario vs. monitoreo) |
| Umbrales de alerta estrictos (> no >=) | Desarrollador | Criterio de evitar falsos positivos |
| Soft delete en todas las entidades | Consenso | La IA sugirió, el desarrollador confirmó |
| Filtrado backend para productos, frontend para bodegas/grupos/lotes | Desarrollador | Análisis de volumen de datos esperado |
| Page size 30 para reportes | Desarrollador | Experiencia de usuario y rendimiento |
| Exportación a Excel de todas las filas (no solo página actual) | Desarrollador | Requerimiento funcional |
| Sustituir SQL Server por MySQL 8 | Desarrollador | El `LIKE` de MySQL es insensible a mayúsculas por defecto y eso sostiene la búsqueda del reporte |
| El producto sí incluye activar/inactivar | Desarrollador | Se detectó que la HU lo excluía por error y se corrigió el documento |
| El estado solo se modifica por el endpoint de alternancia, nunca desde el formulario | Desarrollador | Evita que un formulario omitiendo el campo deje registros inactivos por accidente |

---

## 7. Historial completo de Historias de Usuario

### Orden de dependencias

```
HU-03 (Bodegas) ─┐
HU-06 (Grupos)  ─┤
                  ├──► HU-01 (Productos) ──┐
                  │                        │
HU-05 (Lotes) ───┘                        ├──► HU-02 (Búsqueda)
                                           │
HU-04 (Sensores) ─────────────────────────┘
```

### Resumen por HU

| HU | Tema | Archivo | Líneas | CAs | Dependencias |
|---|---|---|---|---|---|
| HU-01 | Gestión de Productos | `HU-01-Gestion-Productos.md` | 364 | 32 | HU-03, HU-05, HU-06 |
| HU-02 | Búsqueda y Filtrado | `HU-02-Busqueda-Filtrado.md` | 302 | 26 | HU-01, HU-03, HU-04, HU-05 |
| HU-03 | Gestión de Bodegas | `HU-03-Gestion-Bodegas.md` | 281 | 23 | Users (Laravel 9) |
| HU-04 | Monitoreo Temp/Humedad | `HU-04-Monitoreo-Temperatura-Humedad.md` | 457 | 43 | HU-03 |
| HU-05 | Lotes (Inventario) | `HU-05-Parametrizacion-Lotes.md` | 348 | 29 | HU-01, HU-03 |
| HU-06 | Parametrización Grupos | `HU-06-Parametrizacion-Grupos.md` | 280 | 23 | Users (Laravel 9) |
| **Total** | | | **2.032** | **176** | |

### Tablas por HU

| HU | Tablas creadas |
|---|---|
| HU-01 | `products`, `units` |
| HU-02 | Ninguna (solo consulta) |
| HU-03 | `bodegas` |
| HU-04 | `sensors`, `sensor_warehouse`, `readings` |
| HU-05 | `lots` |
| HU-06 | `groups` |

---

## 8. Lecciones aprendidas

1. **La metodología de 9 pasos funciona:** forzar la definición de suposiciones, exclusiones y casos estúpidos antes de escribir criterios de aceptación produce HUs sin ambigüedades.

2. **Delegar a subagentes especializados mejora la calidad:** Tolkien escribe HUs más consistentes que un agente general; Sherlock encuentra huecos que Jarvis puede pasar por alto en la conversación.

3. **La IA no reemplaza el conocimiento del dominio:** las decisiones clave (separación producto/lote, estados computados, umbrales estrictos) vinieron de la investigación y criterio del desarrollador, no de la IA.

4. **Los nombres importan:** cambiar de español a inglés a mitad del proceso generó fricción. Definir la convención desde el inicio habría ahorrado retrabajo.

5. **Documentar la regla de onDelete como decisión global** en lugar de repetirla en cada HU evitó inconsistencia y demostró pensamiento arquitectónico.

6. **Los subagentes pueden fallar:** tener un plan de contingencia (trabajo inline con advertencia de calidad) permitió continuar sin bloquear el progreso.

7. **Auditoría del README mediante IA:** Validar el README de forma cruzada contra el código fuente (`composer.json`, `package.json`, `.env`) utilizando la IA permitió identificar y corregir discrepancias críticas de stack (como la versión del framework, base de datos en SQL Server y la estructura real de carpetas en el frontend), garantizando que las instrucciones de arranque sean 100% verídicas y alineadas al estado actual del repositorio.

---

## 9. Segunda etapa: estabilización y cierre de requisitos

Una vez construida la funcionalidad, se solicitó a la herramienta una etapa de estabilización con el alcance de cerrar los requisitos del enunciado que faltaban: Docker, pipeline de CI/CD, pruebas automatizadas y corrección de defectos detectados en uso real.

### 9.1 Qué se aceptó de las sugerencias

| Sugerencia | Motivo de aceptación |
|---|---|
| Cambiar la base de SQL Server a **MySQL 8** | El operador `LIKE` de MySQL es insensible a mayúsculas por defecto, que es exactamente lo que exige la búsqueda del reporte de inventario. En PostgreSQL sería sensible y obligaría a usar `ILIKE`. El SQL del proyecto resultó portable. **Retirado después: el argumento era incompleto** (ver §10) |
| Eliminar `refetchRef` del hook `useApi` | Era un contador `useRef` leído en el array de dependencias de un `useEffect`. Mutar un ref no dispara re-render, así que ese efecto nunca volvía a ejecutarse por su causa. Como `refetch()` ya llamaba a `fetchData()` de forma directa, el ref era código muerto |
| Reemplazar el reseteo de página del modal | El `useEffect` que hacía `setPage(1)` se sustituyó por el patrón que React documenta para ajustar estado cuando cambian las props: comparar el valor anterior durante el render |
| Añadir `created_by` explícito a los factories | La columna es `NOT NULL` y el modelo solo la rellena si hay sesión iniciada, cosa que no ocurre en las pruebas |
| Incluir el job de Docker en el pipeline | El enunciado pide el stack obligatorio completo; el job levanta el stack real y verifica que el login devuelva token |

### 9.2 Qué se corrigió

Estas correcciones son las relevantes para evaluar el uso de la herramienta: en todos los casos la propuesta inicial no se aplicó tal cual.

| Propuesta inicial | Por qué se corrigió |
|---|---|
| Se propuso **eliminar el indicador de activar/inactivar** de productos al leer que el documento lo excluía | El desarrollador confirmó que el documento estaba equivocado y que el indicador debía existir. Se corrigió el documento, no el código |
| Se generaron **6 reintentos de descarga** de imágenes ante el límite de Docker Hub | El límite de Docker Hub aplica por IP y los runners de CI las comparten, de modo que el cupo ya está agotado antes de empezar. Reintentar no tenía ninguna posibilidad de éxito: se eliminaron los reintentos y se resolvió usando registros espejo |
| Se propuso una **clave de cifrado `APP_KEY` redactada a mano** | Decodificaba a 38 bytes y Laravel exige exactamente 32 para AES-256-CBC. Todas las peticiones de escritura devolvían error 500. El error no se detectó con pruebas locales porque el entorno de desarrollo ya tenía su propia clave |
| Se excluyó `phpunit.xml` del contexto de Docker por considerarlo documentación | Dejó al contenedor sin posibilidad de ejecutar las pruebas. Un archivo de configuración de pruebas nunca debe quedar fuera de la imagen |
| Se añadió una deshabilitación de regla de ESLint con el identificador equivocado | El identificador correcto era `react-hooks/use-memo`, no `react-hooks/exhaustive-deps`. La deshabilitación no surtía efecto y daba una falsa sensación de estar resuelto |
| Se propuso una **sugerencia de configuración que nunca se leyó** | `CORS_ALLOWED_ORIGINS` no se leía porque el archivo de configuración de CORS no existe, y `SANCTUM_STATEFUL_DOMAINS` no aplicaba porque el middleware correspondiente está deshabilitado. Se eliminaron antes de propagarlas al archivo de ejemplo |
| Se propuso una arquitectura de estados en el hook con una suma mal planteada | El cálculo de posicionesCNNagrupaba todos los resultados en un mismo instante por un error de acumulación. Se detectó al volcar los datos generados antes de aplicarlos |

### 9.3 Verificación previa a aplicar

Un patrón que se repetió en esta etapa fue **no confiar en la propuesta sin ejecutarla**:

- Se reprodujo el fallo de la clave de cifrado eliminando el archivo de entorno local, se confirmó el error, se aplicó la corrección y se restauró el archivo.
- Se reintrodujeron deliberadamente los defectos ya corregidos para comprobar que las pruebas nuevas los detectaban. Una de las pruebas no reaccionó, lo que demostró que era vacía y obligó a reescribir su documentación para que afirmara lo que realmente comprueba.
- Se reconstruyó el entorno completo desde cero, incluida la base de datos, antes de dar por buena la configuración de Docker.
- Se comprobó que los archivos no tuvieran finales de línea ni marcadores de orden de bytes, que producían diffs con cientos de líneas de ruido.

### 9.4 Correcciones de documentación

Al cruzar la documentación contra el código se detectaron afirmaciones que no correspondían a la implementación real. Se corrigieron porque la documentación es parte del entregable:

| Documento | Afirmación que no se sostenía |
|---|---|
| Historias de usuario | Varias tablas y columnas descritas que nunca existieron |
| Historias de usuario | Una regla de estado que contradecía la implementación |
| `README.md` | Mencionaba una librería HTTP que el proyecto no usa |
| `README.md` | Mencionaba un tipo de sensor que no existe en el modelo de datos |
| Archivos de entorno | Variables de configuración que ningún componente leía |

En todos los casos la corrección se hizo en la documentación para que refleje el código, y no al revés.

### 9.5 Lecciones de esta etapa

8. **Probar en el entorno local oculta fallos del entorno ajeno.** El fallo de la clave de cifrado no aparecía en la máquina del desarrollador porque ya tenía su propia clave configurada. Solo-afloró al ejecutar en un entorno limpio.

9. **Un reintento solo sirve si el fallo es transitorio.** El límite de descargas de imágenes de contenedor no lo es: se sixth de formatea indefinidamente. Se gastaron varios minutos en seis intentos sin ninguna posibilidad de éxito, y la solución real era cambiar de registro.

10. **Un valor escrito a mano debe verificarse, no suponerse.** Una clave de cifrado generada mentalmente parecía correcta y fallaba en runtime. Decodificarla y medir su longitud habría detectado el problema en segundos.

11. **Una prueba que nunca falla no sirve de nada.** Al reintroducir los defectos corregidos para comprobar que las pruebas los detectaban, una de ellas pasó igualmente. Esoobligó a revisar su alcance y a documentar con precisión qué comprobaba y qué no.

12. **La documentación también requiere verificación cruzada contra el código.** En este proyecto la documentación mentía en ambos sentidos: describía tablas que nunca existieron y, en el caso del README, afirmaba usar librerías y sensores ausentes.

---

## 10. Tercera etapa: migración de MySQL 8 a SQL Server 2025

El enunciado acepta PostgreSQL 16 o SQL Server y recomienda SQL Server por el bloqueo de filas y las restricciones `CHECK`. El proyecto venía sobre MySQL 8, y se decidió volver al motor recomendado.

### 10.1 El argumento que Justificaba MySQL era Incompleto

La decisión original de usar MySQL 8 estaba justificada con que "el operador `LIKE` es insensible a mayúsculas por defecto, que es lo que exige la búsqueda del reporte". Eso es cierto, pero **no distingue a MySQL de SQL Server**:

| Motor | `LIKE` por defecto | Collation |
|---|---|---|
| MySQL 8 | insensible a mayúsculas | `utf8mb4_0900_ai_ci` |
| SQL Server | insensible a mayúsculas | `SQL_Latin1_General_CP1_CI_AS` |
| PostgreSQL | **sensible** (requiere `ILIKE`) | — |

El único motor que habría obligado a reescribir la búsqueda era PostgreSQL. SQL Server habría servido igual, así que el argumento no justificaba apartarse de la recomendación del enunciado.

### 10.2 Qué hubo que Reescribir

| Elemento | En MySQL | En SQL Server | Motivo |
|---|---|---|---|
| Imagen de base | `mysql:8.0` | `mcr.microsoft.com/mssql/server:2025-latest` | — |
| Puerto | 3306 | 1433 | — |
| Credenciales | usuario `farmacia` + root | solo `sa` | SQL Server no separa superusuario |
| Creación de bases | `MYSQL_DATABASE` automático | servicio `db-init` con `sqlcmd` | No existe `/docker-entrypoint-initdb.d` |
| Driver PHP | `pdo_mysql` | `pdo_sqlsrv` + `sqlsrv` vía PECL | Requiere ODBC Driver 18 |
| `users.role` | `ENUM` | `string(30)` + `CHECK` | T-SQL no tiene ENUM |
| `ON DELETE RESTRICT` | válido | `NO ACTION` | La palabra `RESTRICT` no existe en T-SQL |
| `insertOrIgnore()` | atajo de Laravel | `MERGE` | El atajo solo existe en MySQL y SQLite |
| `dropColumn()` | nativo | requiere `doctrine/dbal` | Laravel 9 lo exige |

### 10.3 Errores que Costaron Tiempo, y su Causa Real

| Error aparente | Causa real |
|---|---|
| `install failed` en `pecl install sqlsrv` | La última versión exige PHP 8.3 y el proyecto corre 8.2. El mensaje real era `requires PHP (version >= 8.3.0)` |
| `Login failed for user 'sa'` en `db-init` | El servicio no tenía `MSSQL_SA_PASSWORD` en su entorno |
| `Base  verificada` con el nombre vacío | `$BD` lo interpola Docker Compose antes de llegar al contenedor; hacía falta `$$BD` |
| `Cannot open database "farmacia"` | SQL Server no crea bases solo, a diferencia de `MYSQL_DATABASE` |
| `Incorrect syntax near the keyword 'restrict'` | `ON DELETE RESTRICT` no es T-SQL |

Cinco fallos encadenados, y en cuatro el mensaje apuntaba a otra causa. **Leer el error literal habría enviado a la solución equivocada en varios casos**: `install failed` no dice "actualizaste PHP a una versión que no pide"; `Login failed` no dice "te falta una variable de entorno".

### 10.4 Lo que Resultó Portable

Prácticamente todo el SQL del proyecto. `CHECK` con `ALTER TABLE ... ADD CONSTRAINT`, `LOWER()`, `whereDate`, `paginate`, los tipos de columna (`uniqueidentifier`, `bit`, `nvarchar`) y la PK compuesta de `role_modules` funcionaron sin cambios. Solo hubo que tocar lo que usa vocabulario específico de motor: `ENUM`, `RESTRICT`, `insertOrIgnore`, el punto de extensión de MySQL y `after()`.
