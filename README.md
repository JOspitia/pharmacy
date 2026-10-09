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
- **SQL Server Express**: Base de datos relacional.

---

## Requisitos Previos

### Para el Frontend
- **Node.js** v18+ (versión recomendada por Vite).
- **npm** v9+.

### Para el Backend
- **PHP 8.0.2+** con extensiones: `pdo_sqlsrv`, `sqlsrv`, `mbstring`, `openssl`, `tokenizer`.
- **Composer** (gestor de dependencias de PHP).
- **SQL Server Express** (o cualquier edición de SQL Server compatible con el driver `sqlsrv`).

---

## Instalación y Ejecución

### Backend (Laravel)

1. **Clonar el repositorio e ingresar al backend**
   ```bash
   git clone https://github.com/JOspitia/prueba-tecnica-johan-ospitia.git
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
   ```
   Edita el archivo `.env` con tus credenciales de base de datos:
   ```env
   DB_CONNECTION=sqlsrv
   DB_HOST=localhost\SQLEXPRESS
   DB_PORT=1433
   DB_DATABASE=PruebaTecnicaDB
   DB_USERNAME=prueba_owner
   DB_PASSWORD=123456
   ```

4. **Generar la Application Key**
   ```bash
   php artisan key:generate
   ```

5. **Ejecutar migraciones y seeders**
   ```bash
   php artisan migrate
   php artisan db:seed --class=AdminUserSeeder
   php artisan db:seed --class=UnitSeeder
   ```
   > Los seeders crean las unidades de medida base y el usuario administrador de prueba.

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

## Documentación Adicional

| Archivo | Descripción |
|---------|-------------|
| [`documentacion/API.md`](./documentacion/API.md) | Documentación completa de todos los endpoints REST |
| [`documentacion/AI_USAGE.md`](./documentacion/AI_USAGE.md) | Metodología de uso de IA y decisiones de arquitectura |
| [`documentacion/HU-03-Gestion-Bodegas.md`](./documentacion/HU-03-Gestion-Bodegas.md) | Historia de usuario: Bodegas |
| [`documentacion/HU-04-Monitoreo-Temperatura-Humedad.md`](./documentacion/HU-04-Monitoreo-Temperatura-Humedad.md) | Historia de usuario: Sensores y monitoreo |
| [`documentacion/HU-01-Gestion-Productos.md`](./documentacion/HU-01-Gestion-Productos.md) | Historia de usuario: Productos |
| [`documentacion/HU-05-Parametrizacion-Lotes.md`](./documentacion/HU-05-Parametrizacion-Lotes.md) | Historia de usuario: Lotes e inventario |
