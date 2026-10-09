# Prueba Técnica — Gestión de Almacenes con Monitoreo IoT

Sistema de gestión de almacenes (bodegas), productos farmacéuticos, lotes e inventario, complementado con monitoreo de condiciones ambientales mediante sensores IoT (temperatura, humedad y CO2).

---

## Tecnologías Utilizadas

### Frontend
- **React 19** + **TypeScript**: Biblioteca de UI con tipado estático.
- **Vite**: Entorno de desarrollo rápido y empaquetador.
- **React Router DOM v7**: Enrutamiento declarativo con rutas anidadas.
- **Ant Design**: Biblioteca de componentes de UI.
- **Axios**: Cliente HTTP para las llamadas a la API.

### Backend
- **Laravel 9**: Framework PHP para la API REST.
- **PHP 8.0.2+**: Lenguaje de programación del servidor.
- **Laravel Sanctum 3**: Autenticación basada en tokens.
- **MySQL 8**: Base de datos relacional (la imagen oficial en Docker).

---

## Inicio rápido con Docker (recomendado)

Levanta frontend, backend y base de datos sin instalar PHP ni Node en el equipo:

```bash
docker compose up --build
```

| Servicio | URL |
|---|---|
| Frontend | http://localhost:5173 |
| Backend (API) | http://localhost:8000 |
| MySQL | `localhost:3306` |

La primera vez tarda varios minutos porque compila las imágenes. Las
migraciones y los seeders se ejecutan solos al arrancar el backend.

Para detener y borrar también el volumen de la base:

```bash
docker compose down -v
```

### Variables de entorno

El backend dentro de Docker usa `back/.env.docker`, que está versionado porque
solo contiene una clave de desarrollo. No es un archivo de secretos.

---

## Requisitos Previos

La instalación manual (sin Docker) requiere:

### Para el Frontend
- **Node.js** v18+ (versión recomendada por Vite).
- **npm** v9+.

### Para el Backend
- **PHP 8.0.2+** con extensiones: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`.
- **Composer** (gestor de dependencias de PHP).
- **MySQL 8** (local o el contenedor del compose).

---

## Instalación y Ejecución (sin Docker)

### Backend (Laravel)

1. **Clonar el repositorio e ingresar al backend**
   ```bash
   git clone https://github.com/JOspitia/pharmacy.git
   cd back
   ```

2. **Instalar dependencias**
   ```bash
   composer install
   ```

3. **Configurar el entorno**
   ```bash
   # En Windows (PowerShell o CMD):
   copy .env.example .env
   # En Linux/macOS:
   cp .env.example .env
   ```
   El archivo ya viene con los valores de la base de MySQL y una clave de
   desarrollo, así que funciona sin editarlo. Si usas otra base de datos, cambia
   las variables `DB_*`.

   > `back/.env.example` y `back/.env.docker` son el mismo archivo con una única
   > diferencia: `DB_HOST`. Aquí apunta a `127.0.0.1` (MySQL local) y en el
   > `.env.docker` apunta a `db` (el servicio del compose).

4. **Generar la Application Key**
   ```bash
   php artisan key:generate
   ```

5. **Preparar la Base de Datos**

   Las migraciones de Laravel son la fuente de verdad del esquema:
   ```bash
   php artisan migrate --seed
   ```
   Los seeders crean el usuario administrador de prueba (`admin` / `password`) y las
   7 unidades de medida base.

   > **Sobre `db/scripts.sql`:** ese archivo es un volcado generado desde SQL Server
   > durante el desarrollo original y está **obsoleto**. Contiene rutas de Windows
   > y sintaxis específica de ese motor, por lo que no funciona con MySQL ni dentro
   > de Docker. Se conserva únicamente como registro histórico.

6. **Iniciar el servidor**
   ```bash
   php artisan serve
   ```
   El backend quedará disponible en `http://localhost:8000`.

---

### Frontend (React + Vite)

1. **Abrir una nueva terminal** y navegar al directorio del frontend:
   ```bash
   cd frontend
   ```

2. **Instalar dependencias**
   ```bash
   npm install
   ```

3. **Configurar las variables de entorno**
   Crea un archivo `.env` en la raíz del directorio `frontend/`:
   ```env
   VITE_API_URL=http://localhost:8000/api
   ```

4. **Iniciar el servidor de desarrollo**
   ```bash
   npm run dev
   ```
   El frontend estará disponible en `http://localhost:5173`.

---

## Autenticación

### Credenciales de Prueba

El sistema autentica por **nombre de usuario**.

| Campo    | Valor      |
|----------|------------|
| Usuario  | `admin`    |
| Password | `password` |

> Estas credenciales son creadas por `AdminUserSeeder`. Si no corres el seeder, deberás crear el usuario manualmente.

### Inicio de Sesión
1. Navega a `http://localhost:5173/login`.
2. Ingresa las credenciales de prueba.
3. Haz clic en **"Iniciar sesión"**.

---

## Estructura del Proyecto

```
prueba-tecnica/
├── back/                          # Backend Laravel
│   ├── app/
│   │   ├── Http/Controllers/      # Controladores de la API REST
│   │   ├── Models/                # Modelos Eloquent (soft delete activado)
│   │   └── Services/              # Lógica de negocio separada de los controladores
│   ├── database/
│   │   ├── migrations/            # Migraciones de la base de datos
│   │   └── seeders/               # Datos iniciales (usuarios, unidades)
│   └── routes/api.php             # Definición de rutas API
│
├── frontend/                      # Frontend React + TypeScript
│   └── src/
│       ├── components/            # Componentes reutilizables (AppLayout, modales, etc.)
│       ├── context/               # Contexto de autenticación (AuthContext)
│       ├── hooks/                 # Hooks personalizados (useApi, useFormErrors)
│       ├── lib/                   # Instancia Axios y módulos de llamadas por entidad
│       │   ├── api.ts             # Configuración base de Axios + interceptores
│       │   ├── warehouses.ts      # Llamadas API de Bodegas
│       │   ├── groups.ts          # Llamadas API de Grupos
│       │   ├── products.ts        # Llamadas API de Productos
│       │   ├── lots.ts            # Llamadas API de Lotes
│       │   ├── sensors.ts         # Llamadas API de Sensores
│       │   └── reports.ts         # Llamadas API de Reportes
│       ├── pages/                 # Páginas de la aplicación
│       │   ├── Login.tsx
│       │   ├── Home.tsx
│       │   ├── Warehouse/         # Módulo de Bodegas
│       │   ├── Groups/            # Módulo de Grupos
│       │   ├── Products/          # Módulo de Productos
│       │   ├── Lots/              # Módulo de Lotes
│       │   └── Reports/           # Módulo de Reportes de inventario
│       └── routes/AppRouter.tsx   # Router principal con rutas privadas/públicas
│
└── documentacion/                 # Documentación técnica del proyecto
    ├── API.md                     # Documentación completa de endpoints
    ├── AI_USAGE.md                # Metodología de uso de IA en el desarrollo
    ├── HU-01-Gestion-Productos.md
    ├── HU-02-Busqueda-Filtrado.md
    ├── HU-03-Gestion-Bodegas.md
    ├── HU-04-Monitoreo-Temperatura-Humedad.md
    ├── HU-05-Parametrizacion-Lotes.md
    └── HU-06-Parametrizacion-Grupos.md
```

---

## Rutas del Frontend

| Ruta                  | Módulo                      | Acceso  |
|-----------------------|-----------------------------|---------|
| `/login`              | Inicio de sesión            | Público |
| `/Home`               | Dashboard principal         | Privado |
| `/bodegas`            | Listado de bodegas          | Privado |
| `/bodegas/new`        | Crear bodega                | Privado |
| `/bodegas/:id/edit`   | Editar bodega               | Privado |
| `/groups`             | Listado de grupos           | Privado |
| `/groups/new`         | Crear grupo                 | Privado |
| `/groups/:id/edit`    | Editar grupo                | Privado |
| `/products`           | Listado de productos        | Privado |
| `/products/new`       | Crear producto              | Privado |
| `/products/:id/edit`  | Editar producto             | Privado |
| `/lots`               | Listado de lotes            | Privado |
| `/lots/new`           | Crear lote                  | Privado |
| `/lots/:id/edit`      | Editar lote                 | Privado |
| `/reports/inventory`  | Reporte de inventario       | Privado |

---

## Seguridad

### Autenticación con Sanctum
Todas las rutas protegidas requieren un token Bearer válido en el header:
```
Authorization: Bearer {token}
Accept: application/json
```

### CORS
El backend está configurado para aceptar peticiones desde `http://localhost:5173`. Si cambias el puerto del frontend, actualiza la variable `SANCTUM_STATEFUL_DOMAINS` y la configuración CORS en el `.env` del backend.

---

## Resumen de Endpoints de la API

La API sigue una estructura RESTful con base URL `http://localhost:8000/api`.

| Método   | Ruta                              | Descripción                        |
|----------|-----------------------------------|------------------------------------|
| `POST`   | `/api/login`                      | Iniciar sesión (devuelve token)    |
| `POST`   | `/api/logout`                     | Cerrar sesión (invalida token)     |
| `GET`    | `/api/bodegas`                    | Listar bodegas                     |
| `POST`   | `/api/bodegas`                    | Crear bodega                       |
| `PUT`    | `/api/bodegas/{id}`               | Actualizar bodega                  |
| `DELETE` | `/api/bodegas/{id}`               | Eliminar bodega (soft delete)      |
| `PATCH`  | `/api/bodegas/{id}/toggle-status` | Activar/inactivar bodega           |
| `GET`    | `/api/groups`                     | Listar grupos                      |
| `POST`   | `/api/groups`                     | Crear grupo                        |
| `GET`    | `/api/products`                   | Listar productos (paginado)        |
| `POST`   | `/api/products`                   | Crear producto                     |
| `GET`    | `/api/lots`                       | Listar lotes (paginado)            |
| `POST`   | `/api/lots`                       | Crear lote                         |
| `GET`    | `/api/sensors`                    | Listar sensores                    |
| `POST`   | `/api/sensors`                    | Crear sensor                       |

> Para la documentación completa de todos los endpoints, cuerpos de petición, validaciones y ejemplos de respuesta, consulta [`documentacion/API.md`](./documentacion/API.md).

---

## Pruebas Manuales

Una vez iniciados ambos entornos, puedes validar las siguientes funcionalidades:

1. **Autenticación**: Iniciar sesión con `admin` / `password` y verificar la redirección al dashboard.
2. **Bodegas**: CRUD completo, búsqueda por nombre y cambio de estado activo/inactivo.
3. **Grupos**: CRUD completo y cambio de estado.
4. **Productos**: CRUD con paginación, filtros y búsqueda desde el backend.
5. **Lotes**: CRUD con estados derivados: *Con stock*, *Por vencer (≤30 días)*, *Vencido*.
6. **Sensores**: CRUD, asignación a bodegas, cambio de estado y visualización en modal.
7. **Reportes**: Reporte de inventario exportable a Excel.

---

## Supuestos Tomados

Decisiones que no estaban definidas en el enunciado y que se tomaron por criterio propio.

| # | Supuesto | Motivo |
|---|---|---|
| 1 | **La base de datos es MySQL 8**, no SQL Server. | El desarrollo original usó SQL Server, pero en MySQL el operador `LIKE` es insensible a mayúsculas por defecto, que es lo que exige la búsqueda del reporte (HU-02). El SQL del proyecto es portable: `LOWER(...)`, `whereDate` y `paginate` funcionan igual en ambos motores. |
| 2 | **El backend se sirve con `php artisan serve`**, no con nginx + php-fpm. | Evita depender de un servidor web adicional y de su configuración para una evaluación. Para producción lo indicado sería nginx o Apache con PHP-FPM. |
| 3 | **El frontend se sirve con el servidor de desarrollo de Vite**, no con los archivos compilados de `dist/`. | Permite evaluarlo sin paso de build. Para producción se ejecuta `npm run build` y se sirve el contenido de `dist/`. |
| 4 | **`back/.env.docker` está versionado.** | Solo contiene una clave de desarrollo y credenciales de la base local del compose. No hay secretos reales. |
| 5 | **La columna `code` del lote se llama `name` en la base de datos.** | Los HUs la denominan `code`, pero la interfaz la muestra como "Código". Se documenta la discrepancia en `HU-05`. |
| 6 | **Un lote ya vencido se puede editar** siempre que no se cambie su fecha de vencimiento. | `LotResource` define "Vencido" como un estado legítimo del dominio. Bloquear la edición completa haría esos registros inmodificables. |
| 7 | **Producto y bodega no se pueden inactivar si tienen stock activo**, pero sí bloquear el guardado del lote. | Coherente con la regla de cascada usada en los demás módulos (grupo → producto, bodega → lote). |

---

## Funcionalidad No Entregada

Por priorización de tiempo dentro del plazo de 72 horas, quedó fuera:

| Funcionalidad | Estado | Motivo |
|---|---|---|
| **Traslados entre bodegas** | No implementado | Es la funcionalidad más grande no entregada: requiere tabla de movimientos, dos endpoints (solicitar y confirmar traslado), validación de stock en origen y destino, y su pantalla. Se priorizó un núcleo de inventarios por lote que funcionara correctamente y estuviera documentado. |
| **Dispensación de medicamentos** | Parcial | El control de stock por lote está completo (crear, editar stock, estados derivados, alertas de vencimiento). No hay registro de dispensing ni decremento automático de stock por venta o entrega. |
| **Suite de tests automatizados** | Mínima | Solo se conservan los stubs de ejemplo de Laravel. No se escribieron pruebas unitarias ni de feature. Es la carencia más relevante del entregable. |

`db/scripts.sql` es un volcado obsoleto de SQL Server del desarrollo original. Está
documentado arriba y no debe usarse.

---

## Documentación Adicional

| Archivo | Descripción |
|---------|-------------|
| [`documentacion/API.md`](./documentacion/API.md) | Documentación completa de todos los endpoints REST |
| [`documentacion/AI_USAGE.md`](./documentacion/AI_USAGE.md) | Metodología de uso de IA y decisiones de arquitectura |
| [`documentacion/HU-03-Gestion-Bodegas.md`](./documentacion/HU-03-Gestion-Bodegas.md) | Historia de usuario: Bodegas |
| [`documentacion/HU-04-Monitoreo-Temperatura-Humedad.md`](./documentacion/HU-04-Monitoreo-Temperatura-Humedad.md) | Historia de usuario: Sensores y monitoreo |
| [`documentacion/HU-01-Gestion-Productos.md`](./documentacion/HU-01-Gestion-Productos.md) | Historia de usuario: Productos |
| [`documentacion/HU-05-Parametrizacion-Lotes.md`](./documentacion/HU-05-Parametrizacion-Lotes.md) | Historia de usuario: Lotes e inventario |
