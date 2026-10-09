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
