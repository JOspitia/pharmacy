# Prueba Técnica — Gestión de Almacenes con Monitoreo IoT

![CI](https://github.com/JOspitia/pharmacy/actions/workflows/ci.yml/badge.svg)
![Docker](https://github.com/JOspitia/pharmacy/actions/workflows/docker.yml/badge.svg)

Sistema de gestión farmacéutica: bodegas, productos, lotes, inventario por bodega × producto × lote (Kardex), traslados entre bodegas, prescripciones y dispensación con control especial, monitoreo de condiciones ambientales (temperatura/humedad) mediante sensores IoT, y un sistema de roles + permisos granular extensible a nivel de fila.

---

## Tabla de contenidos

1. [Tecnologías utilizadas](#tecnologías-utilizadas)
2. [Inicio rápido con Docker (recomendado)](#inicio-rápito-con-docker-recomendado)
3. [Requisitos previos](#requisitos-previos)
4. [Primeros pasos después de clonar](#primeros-pasos-después-de-clonar)
5. [Instalación y ejecución sin Docker](#instalación-y-ejecución-sin-docker)
6. [Autenticación y credenciales](#autenticación-y-credenciales)
7. [Estructura del proyecto](#estructura-del-proyecto)
8. [Rutas del frontend](#rutas-del-frontend)
9. [Endpoints de la API](#endpoints-de-la-api)
10. [CI/CD — GitHub Actions](#cicd--github-actions)
11. [Pruebas automáticas](#pruebas-automáticas)
12. [Pruebas manuales](#pruebas-manuales)
13. [Supuestos tomados](#supuestos-tomados)
14. [Funcionalidad no entregada](#funcionalidad-no-entregada)
15. [Documentación adicional](#documentación-adicional)

---

## Tecnologías utilizadas

### Frontend
- **React 19** + **TypeScript**: biblioteca de UI con tipado estático.
- **Vite**: entorno de desarrollo y empaquetador.
- **React Router DOM v7**: enrutamiento declarativo con rutas anidadas.
- **Ant Design**: biblioteca de componentes de UI.
- **Fetch API**: cliente HTTP nativo del navegador.
- **Playwright 1.49+** con `@playwright/test` + Page Object Model: suite E2E en `frontend/test/specs/` (login, permisos, kardex, inventario) que corre dentro del contenedor del frontend. Ver [Pruebas automáticas](#pruebas-automáticas).

### Backend
- **Laravel 9**: framework PHP para la API REST.
- **PHP 8.2**: lenguaje del servidor (pineado en `back/Dockerfile`).
- **Laravel Sanctum 3**: autenticación basada en tokens.
- **Predis / SQL Server driver**: `pdo_sqlsrv` + `sqlsrv` (PECL 5.12.0, pineado para compatibilidad con PHP 8.2).
- **PHPUnit 9** (incluido en Laravel 9): suite de pruebas de backend en `back/tests/` — Feature tests de HU-07/08/09/10, modelos y coverage de HU-03/04/05/06. Ver [Pruebas automáticas](#pruebas-automáticas).

### Base de datos
- **SQL Server 2025** (`mcr.microsoft.com/mssql/server:2025-latest`): corre **dentro de Docker** como servicio `db` del compose. El enunciado acepta PostgreSQL 16 o SQL Server y recomienda este último por bloqueo de filas y restricciones `CHECK`. La versión se alinea con la instancia SQLEXPRESS local del equipo (`17.x`).
  - Las PKs de las tablas transaccionales (inventories, movements, transfers, patients, prescriptions, dispenses, transfer_items, etc.) son **UUID** autogenerados, no `bigint`.
  - Los catálogos (`roles`, `modules`, `permissions`) conservan `bigint`.

### Infraestructura
- **Docker 29** + **Docker Compose v2**: orquestación de los servicios `db`, `backend` y `frontend`.
- **GitHub Actions**: cuatro workflows declarados en `.github/workflows/` (ver [CI/CD](#cicd--github-actions)).
- **Microsoft Container Registry** (`mcr.microsoft.com`): fuente de la imagen oficial de SQL Server.

---

## Inicio rápido con Docker (recomendado)

Levanta frontend, backend y **SQL Server 2025** sin instalar PHP ni Node en el equipo:

```bash
docker compose up --build
```

| Servicio | URL en el host | Puerto interno | Notas |
|---|---|---|---|
| **Frontend** | http://localhost:5173 | 5173 | Vite dev server con HMR |
| **Backend (API)** | http://localhost:8000 | 8000 | `php artisan serve` |
| **SQL Server 2025** | `localhost:1434` | 1433 | Dentro de Docker (servicio `db`) |

> **¿Por qué 1434 y no 1433?** El equipo de desarrollo tiene una instancia SQLEXPRESS local escuchando en 1433. Publicar el contenedor en el mismo puerto causa un conflicto silencioso en Windows: el servicio local gana, y cualquier cliente que se conecte a `localhost:1433` aterriza ahí (no en Docker). Publicar en `1434` evita el choque. Dentro de la red de Docker, el backend habla con `db` por el puerto interno `1433`, que es `DB_PORT` en `back/.env.docker`. Solo cambia cómo se llega desde el host.

Las migraciones, seeders y `migrate --seed` se ejecutan solos al arrancar el backend. La primera vez tarda varios minutos porque compila las imágenes del backend y el frontend.

Para detener y borrar también el volumen de la base:

```bash
docker compose down -v
```

### Servicios del compose

```
┌──────────────────────────────────────────────────────────────┐
│  red pharmacy (bridge)                                        │
│                                                              │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐    │
│  │  db          │    │  backend     │    │  frontend    │    │
│  │  SQL 2025    │◄───│  PHP 8.2     │◄───│  Node 20     │    │
│  │  2025-latest │    │  Laravel 9   │    │  Vite + R19  │    │
│  │  :1433       │    │  :8000       │    │  :5173       │    │
│  └──────────────┘    └──────────────┘    └──────────────┘    │
│         ▲                     ▲                              │
│         │                     │                              │
│    :1434 en host          :8000 en host                       │
│    (volumen db_data)      :5173 en host                       │
└──────────────────────────────────────────────────────────────┘
```

Hay un servicio adicional `db-init` que se ejecuta una sola vez al levantar el stack: crea las bases `farmacia` y `farmacia_testing` con `sqlcmd` (SQL Server no las crea por su cuenta como MySQL). Termina al terminar; no queda corriendo.

### Modo desarrollo con hot reload (sin rebuild en cada cambio)

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

Qué cambia respecto al modo normal:

- Los fuentes de `back/app`, `back/config`, `back/database`, `back/routes`, `back/tests`, `back/resources`, `back/public` y `back/bootstrap` viven en el host y se sincronizan al contenedor. Cualquier edit se ve en el siguiente request sin rebuild.
- `vendor/`, `bootstrap/cache/` y la imagen de PHP base no se montan, así que la instalación de Composer y la imagen son las del build original.
- El `CMD` por defecto se reemplaza por `back/docker-dev-entrypoint.sh`, que limpia caches (`config:clear`, `route:clear`, `view:clear`) y arranca `php artisan serve`.
- El frontend usa `CHOKIDAR_USEPOLLING=true` y `WATCHPACK_POLLING=true` para que Vite detecte los cambios en archivos bind-mounted desde Windows (sin esto, HMR queda sordo).
- `COMPOSER_AUTOLOADER_OPTIMIZE=false` para no tener que correr `composer dump-autoload` cada vez que se agrega una clase.

Para parar el modo dev:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml down
```

Para volver al modo "estándar" (imagen horneada):

```bash
docker compose down
docker compose up -d
```

### Modo producción

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

El override de producción:

- No monta bind mounts: el código queda dentro de la imagen.
- Corre `php artisan migrate --force` + `config:cache` + `route:cache` + `view:cache` antes de arrancar.
- Es la forma correcta de validar la imagen antes de un release.

### Variables de entorno

El backend dentro de Docker usa `back/.env.docker` (versionado). Contiene la clave de la aplicación y las credenciales de la base del compose. **No es un archivo de secretos**; los valores son los del compose local y no se usan en producción.

Variables relevantes:

| Variable | Valor Docker | Valor local sin Docker | Notas |
|---|---|---|---|
| `DB_CONNECTION` | `sqlsrv` | `sqlsrv` | Driver ODBC 18 + pdo_sqlsrv 5.12 |
| `DB_HOST` | `db` | `127.0.0.1` | Nombre del servicio dentro de Docker |
| `DB_PORT` | `1433` | `1434` (puert mapping) | Dentro del contenedor el puerto es 1433 |
| `DB_DATABASE` | `farmacia` | `farmacia` | La de pruebas se llama `farmacia_testing` |
| `DB_USERNAME` | `sa` | `sa` | Único superusuario de SQL Server |
| `DB_PASSWORD` | `YourStrong!Passw0rd` | `YourStrong!Passw0rd` | Debe cumplir complejidad SA |

---

## Requisitos previos

### Solo si vas a usar Docker
- **Docker Desktop 29+** con `compose` integrado.
- Puertos `5173`, `8000` y `1434` libres en el host.

### Instalación manual (sin Docker)

#### Para el frontend
- **Node.js** 20+ (versión recomendada por Vite y pineada en el Dockerfile).
- **npm** 10+.

#### Para el backend
- **PHP 8.2** con extensiones: `pdo_sqlsrv`, `sqlsrv`, `mbstring`, `openssl`, `tokenizer`, `bcmath`, `intl`, `zip`, `gd`.
- **Composer** 2+.
- **SQL Server 2025** (o la imagen Docker del compose). El proyecto se validó sobre **SQL Server 2025** (`17.x`).

---

## Primeros pasos después de clonar

Esta sección es la versión detallada del *Inicio rápido con Docker*: qué clonar, qué esperar, qué verificar y qué hacer cuando algo no anda. Si seguís los pasos en orden, en cinco minutos deberías tener el stack corriendo con login funcional.

### 1. Clonar el repositorio

```bash
git clone https://github.com/JOspitia/pharmacy.git
cd pharmacy
```

### 2. Levantar el stack con Docker

```bash
docker compose up -d --build
```

**Qué esperar:**

- **Primera vez** (`--build`): 5–10 minutos — descarga SQL Server 2025, compila backend (instala msodbcsql18, mssql-tools18, pdo_sqlsrv 5.12.0, extensiones PHP, Composer) y frontend (npm install). En corridas siguientes, ~10–20 segundos.
- El servicio `db` arranca SQL Server y queda **healthy** después de 60–90 segundos (la primera corrida inicializa las bases del sistema).
- El servicio `db-init` corre una vez: crea `farmacia` y `farmacia_testing` con `sqlcmd` y termina. SQL Server no crea bases por sí mismo, como sí hace MySQL con `MYSQL_DATABASE`.
- El `CMD` del backend es un loop que espera al puerto de SQL Server, corre `php artisan migrate --force --seed` y arranca `php artisan serve` cuando termina.

**Seguí los logs para confirmar el avance:**

```bash
docker compose logs -f backend db db-init
```

Vas a ver cosas como:

```
db-1         | SQL Server 2025 ... ready for client connections
db-init-1    | Base farmacia verificada.
db-init-1    | Base farmacia_testing verificada.
backend-1    | Esperando a la base de datos...
backend-1    | Nothing to migrate.
backend-1    | Database seeding completed successfully.
backend-1    | Server running on [http://0.0.0.0:8000]
```

> Si la salida se queda en `Esperando a la base de datos...`, es normal. Esperá 60–90 segundos la primera vez (SQL Server tarda en inicializar).

### 3. Verificar el backend

```bash
# Liveness — endpoint público, sin token
curl http://localhost:8000/api/health
# → {"status":"ok"}

# Readiness — verifica que la DB responde
curl http://localhost:8000/api/ready
# → {"status":"ok","database":"connected"}
```

Si `/api/ready` falla o no devuelve `"database":"connected"`, no pases al login — revisá los logs de `db` y `backend` antes de seguir.

### 4. Hacer login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name":"admin","password":"password"}'
# → {"token":"1|abc..."}
```

Probá un endpoint protegido con el token:

```bash
curl http://localhost:8000/api/user \
  -H "Authorization: Bearer 1|abc..." \
  -H "Accept: application/json"
```

### 5. Abrir el frontend

Navegá a **http://localhost:5173/login** y entrá con `admin` / `password`. Vas a caer en `/Home`.

Repetí el login con los otros usuarios (`regente`, `auxiliar`, `medico`, `auditor`) — todos comparten la contraseña `password` y cada uno aterriza con un conjunto distinto de permisos según el rol.

---

### Comandos recurrentes

Una vez que el stack está corriendo, esta es la cheat sheet:

#### Ver qué está pasando

```bash
docker compose logs -f backend      # logs del backend en vivo
docker compose logs -f db           # logs de SQL Server
docker compose logs -f frontend     # logs de Vite
docker compose ps                   # estado de los servicios
```

#### Resetear todo (incluyendo los datos de la base)

```bash
docker compose down -v              # borra también el volumen db_data
docker compose up -d --build        # levanta de cero: vuelve a migrar y seedear
```

#### Re-correr seeders sin tocar el esquema

```bash
# Re-correr TODOS los seeders (idempotente con sync())
docker compose exec backend php artisan db:seed

# Re-correr solo uno
docker compose exec backend php artisan db:seed --class=RolePermissionSeeder
```

#### Re-migrar desde cero (incluyendo datos)

```bash
docker compose exec backend php artisan migrate:fresh --seed
```

> ⚠️  Esto borra todos los datos de la base `farmacia`. No lo hagas en un entorno donde haya datos reales.

#### Inspeccionar la base con `sqlcmd`

```bash
docker compose exec db /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P 'YourStrong!Passw0rd' -C \
  -Q 'SELECT TOP 5 * FROM bodegas;' -h -1
```

`-C` confía el certificado autofirmado del contenedor. Sin él, el handshake TLS falla con un error poco descriptivo.

> **Desde el host:** si necesitás conectarte desde tu cliente (DBeaver, Azure Data Studio, etc.) usá `localhost,1434` con las credenciales `sa` / `YourStrong!Passw0rd`. El puerto es `1434` (no `1433`) por el conflicto con SQLEXPRESS local.

#### Abrir un shell dentro de un contenedor

```bash
docker compose exec backend sh       # shell del backend
docker compose exec db bash         # shell del SQL Server
```

#### Playwright y PHPUnit (rápido)

```bash
# Levantar el override de dev (para tener HMR y código bind-mount)
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Tests del frontend
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec frontend npm run test:e2e

# Tests del backend
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan test
```

Más detalle en [Pruebas automáticas](#pruebas-automáticas).

---

### Troubleshooting

| Síntoma | Causa probable | Solución |
|---|---|---|
| `docker compose up` queda en `Esperando a la base...` por más de 2 min | SQL Server todavía está inicializando (la primera corrida es la más lenta) | Esperá; revisá `docker compose logs db`. Si querés certeza, corré `docker compose ps` y mirá el `Health` de `db` — debería pasar a `healthy` al rato. |
| `curl http://localhost:8000/api/health` da `Connection refused` | El backend todavía está arrancando | Esperá 5 segundos y reintentá. Si persiste, `docker compose logs backend`. |
| Login devuelve `401` | Las credenciales son las del seeder (`admin` / `password`) | Si hiciste `migrate:fresh` con seeders no se rompió nada. Verificá que el usuario exista: `docker compose exec backend php artisan tinker` → `App\Models\User::count()`. |
| Frontend abre pero no carga datos (`Network Error`) | El backend no está corriendo, o el proxy de Vite apunta a otro lado | Verificá que `backend` esté `healthy` en `docker compose ps`. El proxy usa `VITE_API_TARGET=http://backend:8000`. |
| Las pruebas PHPUnit fallan con "Cannot open database 'farmacia_testing'" | La base de pruebas no existe (probablemente nunca levantaste `db-init`) | `docker compose up -d db-init` (es one-shot) o `docker compose up -d` que también la crea. |
| `ECONNREFUSED 1433` desde el host | Estás usando el puerto equivocado | El puerto publicado es **`1434`**, no `1433`. Usá `localhost,1434` con usuario `sa` y contraseña `YourStrong!Passw0rd`. |
| El contenedor `db` arranca pero `db-init` falla con `Login failed for user 'sa'` | La contraseña no cumple la complejidad de SQL Server | Debe tener **mayúscula, minúscula, dígito y símbolo**. La del compose es `YourStrong!Passw0rd`. |
| Cambié un `.php` del backend y no veo el cambio | Estás en modo estándar (`docker compose up`), no dev | Levantá el override: `docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d`. |
| Cambié un `.tsx` y Vite no recarga | Estás en Windows con bind mounts y `CHOKIDAR_USEPOLLING` no está prendido | El override de dev ya lo prende. Si seguís sin HMR, `docker compose restart frontend`. |

---

## Instalación y ejecución sin Docker

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
   El archivo ya viene con los valores de la base de SQL Server y una clave de
   desarrollo, así que funciona sin editarlo. Si usas otra base de datos, cambia
   las variables `DB_*`.

   > `back/.env.example` y `back/.env.docker` son el mismo archivo con una única
   > diferencia: `DB_HOST`. Aquí apunta a `127.0.0.1` (SQL Server local) y en el
   > `.env.docker` apunta a `db` (el servicio del compose).

   > El usuario es `sa`, que en SQL Server es el único superusuario: no existe
   > una separación entre root y un usuario de aplicación como en MySQL. La
   > contraseña de desarrollo es `YourStrong!Passw0rd` y SQL Server exige que
   > cumpla complejidad (mayúscula, minúscula, dígito y símbolo).

4. **Generar la Application Key**
   ```bash
   php artisan key:generate
   ```

5. **Preparar la base de datos**

   Las migraciones de Laravel son la fuente de verdad del esquema:
   ```bash
   php artisan migrate --seed
   ```
   Los seeders crean:
   - Roles del catálogo (`admin`, `regente_farmacia`, `auxiliar_farmacia`, `medico`, `auditor`).
   - Módulos del catálogo (`warehouses`, `groups`, `products`, `lots`, `sensors`, `reports`, `users`, `kardex`, `transfers`, `patients`, `prescriptions`).
   - Permisos (CRUD por módulo + capacidades globales como `aprobar-traslado`, `autorizar-control-especial`).
   - Matriz `role_permissions` que asigna a cada rol los permisos que le corresponden.
   - Usuarios de prueba para los 5 roles (ver [credenciales](#credenciales-de-prueba)).
   - 7 unidades de medida base (`UnitSeeder`).

   > **Sobre `db/scripts.sql`:** ese archivo es un volcado generado durante el
   > desarrollo original y está **obsoleto**. Contiene rutas de Windows y un
   > esquema que ya no coincide con el actual. Las migraciones de Laravel son la
   > fuente de verdad del esquema. Se conserva únicamente como registro histórico.

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

## Autenticación y credenciales

### Endpoint de login

El sistema autentica por **nombre de usuario** (`name`, no email).

```
POST /api/login
Content-Type: application/json

{ "name": "admin", "password": "password" }

→ 200 { "token": "1|abc123def456ghi789..." }
```

Todas las rutas protegidas requieren un token Bearer válido:

```
Authorization: Bearer {token}
Accept: application/json
```

### Credenciales de prueba

Las credenciales son creadas por `RolePermissionSeeder` + `UserSeeder`. La contraseña para todos los usuarios es **`password`**.

| Usuario | Rol | Notas |
|---|---|---|
| `admin` | admin | Gestiona usuarios y catálogos. Solo lectura en lotes y Kardex. |
| `regente` | regente_farmacia | Opera el día a día. Único rol con `aprobar-traslado` y `autorizar-control-especial`. |
| `auxiliar` | auxiliar_farmacia | Crea y consulta stock. Puede crear traslados y recibirlos, pero no aprobarlos. |
| `medico` | medico | Crea prescripciones y consulta inventario. No dispensa. |
| `auditor` | auditor | Solo lectura. Datos de pacientes enmascarados (RN-10). |

> Si no corres los seeders, los usuarios no existen. Crear el primer usuario manualmente queda fuera del alcance del flujo estándar.

### Inicio de sesión

1. Navega a `http://localhost:5173/login`.
2. Ingresa las credenciales de la tabla.
3. Haz clic en **"Iniciar sesión"**.

---

## Estructura del proyecto

```
prueba-tecnica/
├── back/                              # Backend Laravel 9 / PHP 8.2
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/          # Capa fina: recibe request, llama service
│   │   │   ├── Middleware/            # EnsureModule (mapeo verbo → permiso)
│   │   │   └── Resources/             # Serialización de respuestas JSON
│   │   ├── Models/                    # Eloquent: softdelete salvo catálogos
│   │   ├── Services/                  # Toda la lógica de negocio vive acá
│   │   │   ├── AuthService.php
│   │   │   ├── KardexService.php      # HU-08 — Singleton inyectable
│   │   │   ├── TransferService.php    # HU-09 — máquina de 7 estados
│   │   │   ├── DispenseService.php    # HU-10 — FEFO + idempotencia
│   │   │   ├── PrescriptionService.php
│   │   │   ├── PatientService.php     # incluye bitácora RN-10
│   │   │   └── ...
│   │   └── Providers/
│   │       └── AuthServiceProvider.php # Gate::before con resolución perezosa
│   ├── database/
│   │   ├── migrations/                # Fuente de verdad del esquema
│   │   └── seeders/
│   │       ├── RoleSeeder.php
│   │       ├── ModuleSeeder.php
│   │       ├── PermissionSeeder.php
│   │       ├── RolePermissionSeeder.php
│   │       ├── UserSeeder.php
│   │       └── UnitSeeder.php
│   ├── routes/api.php                 # Definición de rutas por módulo
│   ├── tests/                         # Suite PHPUnit del backend
│   │   ├── bootstrap.php              # Fija sqlsrv + farmacia_testing
│   │   ├── Feature/                   # Pruebas funcionales
│   │   │   ├── AdminNoAutorizaTest.php
│   │   │   ├── ApiRoutePermissionsTest.php
│   │   │   ├── EnsureModuleTest.php
│   │   │   ├── HealthCheckTest.php
│   │   │   ├── KardexTest.php
│   │   │   ├── LotesEnBodegaTest.php
│   │   │   ├── PermissionCapabilitiesTest.php
│   │   │   ├── PermissionMatrixTest.php
│   │   │   ├── UserRoleTest.php
│   │   │   └── Models/                # Bodega, Group, Lot, Product, Sensor
│   │   ├── Unit/                      # Pruebas unitarias
│   │   └── CurlRequest/
│   ├── Dockerfile                     # Imagen PHP 8.2-cli + driver SQL Server
│   └── docker-dev-entrypoint.sh
│
├── frontend/                          # Frontend React 19 + Vite
│   ├── src/
│   │   ├── components/                # Componentes reutilizables
│   │   ├── context/                   # AuthContext
│   │   ├── hooks/                     # useApi, useFormErrors
│   │   ├── lib/                       # Módulos de llamadas por entidad
│   │   │   ├── api.ts                 # Axios + interceptores
│   │   │   ├── warehouses.ts
│   │   │   ├── groups.ts
│   │   │   ├── products.ts
│   │   │   ├── lots.ts
│   │   │   ├── sensors.ts
│   │   │   ├── inventories.ts         # HU-08
│   │   │   ├── movements.ts           # HU-08 — Kardex
│   │   │   ├── transfers.ts           # HU-09
│   │   │   ├── patients.ts            # HU-10
│   │   │   ├── prescriptions.ts       # HU-10
│   │   │   ├── dispenses.ts           # HU-10
│   │   │   └── reports.ts
│   │   ├── pages/
│   │   │   ├── Login.tsx
│   │   │   ├── Home.tsx
│   │   │   ├── Warehouse/             # Módulo de Bodegas
│   │   │   ├── Groups/                # Módulo de Grupos
│   │   │   ├── Products/              # Módulo de Productos
│   │   │   ├── Lots/                  # Módulo de Lotes
│   │   │   ├── Inventories/           # HU-08 — Existencias por bodega
│   │   │   ├── Kardex/                # HU-08 — Movimientos
│   │   │   ├── Transfers/             # HU-09
│   │   │   ├── Patients/              # HU-10
│   │   │   ├── Prescriptions/         # HU-10
│   │   │   ├── Dispenses/             # HU-10
│   │   │   └── Reports/               # Reportes de inventario
│   │   ├── routes/AppRouter.tsx       # Rutas privadas/públicas
│   │   └── test/                      # Suite Playwright E2E
│   │       ├── specs/                 # login, permisos, kardex, inventario
│   │       ├── pages/                 # Page Object Model
│   │       └── fixtures/              # auth.fixture + teardown
│   ├── Dockerfile                     # Node 20-bookworm-slim
│   ├── playwright.config.ts
│   └── package.json
│
├── .github/workflows/                 # CI/CD — ver sección propia
│   ├── ci.yml                         # tests backend + frontend en cada push
│   ├── docker.yml                     # build de imágenes cuando cambia Dockerfile
│   ├── release.yml                    # publica imágenes en ghcr.io con tag
│   └── deploy.yml                     # deploy manual a staging / production
│
├── documentacion/                     # Documentación técnica
│   ├── API.md                         # Contrato completo de la API
│   ├── AI_USAGE.md                    # Metodología de uso de IA
│   ├── HU-01-Gestion-Productos.md
│   ├── HU-02-Busqueda-Filtrado.md
│   ├── HU-03-Gestion-Bodegas.md
│   ├── HU-04-Monitoreo-Temperatura-Humedad.md
│   ├── HU-05-Parametrizacion-Lotes.md
│   ├── HU-06-Parametrizacion-Grupos.md
│   ├── HU-07-Roles-Permisos.md
│   ├── HU-08-Kardex-Inventories.md
│   ├── HU-09-Traslados.md
│   └── HU-10-Dispensacion.md
│
├── db/                                # Volumen histórico de la base
│   ├── init/                          # Scripts SQL de una sola ejecución
│   └── scripts.sql                    # Obsoleto (ver aviso en instalación)
│
├── docker-compose.yml                 # Base: define db, backend, frontend
├── docker-compose.dev.yml             # Override con bind mounts + HMR
├── docker-compose.prod.yml            # Override sin bind mounts + cache
├── PERMISOS.md                        # Matriz de roles legible (HU-07/08)
└── README.md                          # Este archivo
```

---

## Rutas del frontend

| Ruta | Módulo | Acceso | HU |
|---|---|---|---|
| `/login` | Inicio de sesión | Público | — |
| `/Home` | Dashboard principal | Privado | — |
| `/bodegas` | Listado de bodegas | Privado | HU-03 |
| `/bodegas/new` | Crear bodega | Privado | HU-03 |
| `/bodegas/:id/edit` | Editar bodega | Privado | HU-03 |
| `/groups` | Listado de grupos | Privado | HU-06 |
| `/groups/new` | Crear grupo | Privado | HU-06 |
| `/groups/:id/edit` | Editar grupo | Privado | HU-06 |
| `/products` | Listado de productos | Privado | HU-01 |
| `/products/new` | Crear producto | Privado | HU-01 |
| `/products/:id/edit` | Editar producto | Privado | HU-01 |
| `/lots` | Listado de lotes | Privado | HU-05 |
| `/lots/new` | Crear lote | Privado | HU-05 |
| `/lots/:id/edit` | Editar lote | Privado | HU-05 |
| `/inventories` | Existencias por bodega × producto × lote | Privado | HU-08 |
| `/kardex` | Movimientos (ENTRADA, TRASLADO, DISPENSACION…) | Privado | HU-08 |
| `/transfers` | Listado y creación de traslados | Privado | HU-09 |
| `/transfers/:id` | Detalle + flujo aprobar/despachar/recibir | Privado | HU-09 |
| `/patients` | Pacientes (con enmascaramiento para auditor) | Privado | HU-10 |
| `/prescriptions` | Prescripciones médicas | Privado | HU-10 |
| `/dispenses` | Dispensaciones (FEFO + doble autorización) | Privado | HU-10 |
| `/reports/inventory` | Reporte de inventario exportable | Privado | HU-02 |

---

## Endpoints de la API

Base URL: `http://localhost:8000/api`

### Salud del sistema

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| `GET` | `/api/health` | Pública | Liveness — devuelve `{ "status": "ok" }` |
| `GET` | `/api/ready` | Pública | Readiness — verifica que la DB responde |

### Autenticación

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/api/login` | Iniciar sesión (devuelve token Sanctum) |
| `POST` | `/api/logout` | Cerrar sesión (invalida token) |
| `GET` | `/api/user` | Datos del usuario autenticado |

### Recursos por módulo

| Módulo | Endpoints | HU |
|---|---|---|
| **Bodegas** | `GET/POST /api/bodegas`, `PUT/DELETE /api/bodegas/{id}`, `PATCH /api/bodegas/{id}/toggle-status` | HU-03 |
| **Bodegas — sensores** | `GET/POST /api/bodegas/{id}/sensors`, lecturas y alertas | HU-04 |
| **Grupos** | `GET/POST /api/groups`, `PUT/DELETE /api/groups/{id}`, `PATCH /api/groups/{id}/toggle-status` | HU-06 |
| **Productos** | `GET/POST /api/products`, `PUT/DELETE /api/products/{id}` (paginado + filtros) | HU-01/02 |
| **Lotes** | `GET/POST /api/lots`, `PUT/DELETE /api/lots/{id}`, `PATCH /api/lots/{id}/toggle-status` | HU-05 |
| **Sensores** | `GET/POST /api/sensors`, `PUT/DELETE /api/sensors/{id}` | HU-04 |
| **Reportes** | `GET /api/reports/inventory` (exportable a Excel) | HU-02 |
| **Inventarios** | `GET /api/inventories` (FEFO, alertas vencimiento, stock mínimo) | HU-08 |
| **Kardex (movimientos)** | `GET/POST /api/movements` (inmutable: PUT/DELETE = 405) | HU-08 |
| **Traslados** | `GET /api/transfers`, `POST /api/transfers`, `PATCH /api/transfers/{id}/{approve,dispatch,receive,cancel}` | HU-09 |
| **Pacientes** | `GET/POST /api/patients`, `GET /api/patients/{id}` (enmascarado para auditor) | HU-10 |
| **Prescripciones** | `GET/POST /api/prescriptions`, `GET /api/prescriptions/{id}` | HU-10 |
| **Dispensaciones** | `POST /api/dispenses` (con header `Idempotency-Key`), `GET /api/dispenses/prepare/{prescription_id}` | HU-10 |

### CORS

El backend está configurado para aceptar peticiones desde `http://localhost:5173`. Si cambias el puerto del frontend, actualiza la variable `SANCTUM_STATEFUL_DOMAINS` y la configuración CORS en `back/.env`.

> Para la documentación completa de todos los endpoints, cuerpos de petición, validaciones y ejemplos de respuesta, consulta [`documentacion/API.md`](./documentacion/API.md).

---

## CI/CD — GitHub Actions

Hay **cuatro workflows** declarados en `.github/workflows/`. Cada uno cumple un rol distinto; ninguno pisa a otro.

| Workflow | Trigger | Qué hace |
|---|---|---|
| **CI** (`ci.yml`) | `push` y `pull_request` a `main` | Levanta un service container de SQL Server 2022, corre `php artisan test` del backend y `tsc --noEmit + lint + build` del frontend en paralelo. Badge en la parte superior. |
| **Docker** (`docker.yml`) | `push`/`pull_request` a `main` **solo si cambian** `Dockerfile*`, `docker-compose*.yml`, manifests de dependencias o `.dockerignore*` | Construye las imágenes de backend y frontend con BuildKit, sin publicarlas. El filtro por `paths` evita rebuilds cuando cambia código no relacionado. |
| **Release** (`release.yml`) | Tags `v*` (`git tag v1.0.0 && git push origin v1.0.0`) | Construye, smoke-testea el stack completo (levanta + login + DB), publica `ghcr.io/$repo/backend:vX.Y.Z` y `frontend:vX.Y.Z`, y crea el Release de GitHub con notas. |
| **Deploy** (`deploy.yml`) | `workflow_dispatch` (manual) | Disparo manual a staging o production. Tiene tres opciones comentadas (Azure App Service, SSH a VPS, Render). Hasta que se elija hosting, falla intencionalmente con mensaje claro. |

### Convenciones

- Concurrencia cancela corridas anteriores del mismo branch (`concurrency: cancel-in-progress: true` en `ci.yml` y `docker.yml`).
- `release.yml` declara `permissions: { contents: write, packages: write }` — sin esto, `docker push` a `ghcr.io` falla con 403.
- Ningún workflow hace deploy automático a `main`. El deploy es siempre manual hasta que haya hosting configurado.

---

## Pruebas automáticas

Hay dos suites independientes: **PHPUnit** sobre el backend y **Playwright** sobre el frontend. Corren juntas en CI dentro de `.github/workflows/ci.yml` (ver sección anterior); también se pueden correr localmente dentro del override de desarrollo de Docker.

### Backend — PHPUnit (Feature + Unit)

Las pruebas viven en `back/tests/`. Cobertura por archivo:

| Archivo | Cubre |
|---|---|
| `Feature/KardexTest.php` | HU-08 — entradas, FEFO, Kardex inmutable, `lockForUpdate`, CHECK constraint de stock no negativo |
| `Feature/PermissionMatrixTest.php` | HU-07 — catálogo de permisos + matriz de asignaciones; idempotencia del seeder con `sync()` |
| `Feature/PermissionCapabilitiesTest.php` | HU-07 — capacidades globales (`autorizar-control-especial`, `aprobar-traslado`, `dispensar`) por `Gate::allows()` |
| `Feature/ApiRoutePermissionsTest.php` | HU-07 — cada ruta real de `api.php` exige el permiso CRUD que corresponda al verbo HTTP |
| `Feature/EnsureModuleTest.php` | HU-07 — middleware `EnsureModule` aislado con rutas definidas en el test |
| `Feature/AdminNoAutorizaTest.php` | RN-05 / RN-08 — admin NO aprueba traslados ni autoriza control especial, sin bypass en `Gate::before` |
| `Feature/UserRoleTest.php` | HU-07 — modelo `User::hasPermission()` memoizado |
| `Feature/LotesEnBodegaTest.php` | HU-08 — selector de lotes por bodega devuelve los que tienen existencia |
| `Feature/HealthCheckTest.php` | `/api/health` y `/api/ready` (sin auth, JSON esperado) |
| `Feature/Models/BodegaTest.php` | HU-03 — modelo y relaciones |
| `Feature/Models/GroupTest.php` | HU-06 |
| `Feature/Models/LotTest.php` | HU-05 |
| `Feature/Models/ProductTest.php` | HU-01 |
| `Feature/Models/SensorTest.php` | HU-04 |
| `Unit/ExampleTest.php` | Stubs de ejemplo (Laravel) |

> **`Feature/AdminNoAutorizaTest.php`** verifica que admin no tenga `aprobar-traslado` ni `autorizar-control-especial`. Garantiza que RN-05 y RN-08 se cumplen sin hardcodear nombres de rol en código de negocio.

#### Cómo correrlas localmente

Dentro del override de dev (los fuentes viven en el host, `migrate:fresh` corre cada corrida):

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d backend db
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan test
```

Sin Docker (requiere SQL Server local + `farmacia_testing` creada):

```bash
cd back
php artisan test
```

#### Configuración de la base de pruebas

`back/tests/bootstrap.php` fija `DB_CONNECTION=sqlsrv` y `DB_DATABASE=farmacia_testing` antes del arranque de Laravel. Lo hace con `putenv` y `$_ENV`/`$_SERVER` directos porque el cargador de Laravel es **inmutable**: una variable real de entorno gana sobre lo que diga `.env.testing` o `phpunit.xml`. Las pruebas usan `RefreshDatabase`, que ejecuta `migrate:fresh` — por eso **debe** apuntar a una base distinta a `farmacia`: si apuntara a la base de la app, cada corrida borraría datos de trabajo y al usuario `admin`.

La base `farmacia_testing` la crea el job `backend` de `ci.yml` con `sqlcmd`, y el servicio `db-init` del compose en local.

---

### Frontend — Playwright (E2E)

Las pruebas E2E viven en `frontend/test/`. Patrón **Page Object Model**: cada screen tiene su clase en `pages/`, y los specs (`specs/*.spec.ts`) orquestan contra los page objects vía fixture `loggedInPage`.

**Specs (4):**

| Spec | Cubre |
|---|---|
| `specs/login.spec.ts` | Login — formulario real, validación, mensaje de error con credenciales inválidas |
| `specs/permisos.spec.ts` | HU-07 — botones condicionales según rol; auxiliar no ve "Aprobar traslado" |
| `specs/kardex.spec.ts` | HU-08 — pantalla del Kardex carga, muestra tabla, filtra por tipo `ENTRY` |
| `specs/inventario.spec.ts` | HU-08 — pantalla de inventarios carga, ordena por FEFO, muestra alertas |

**Pages (Page Objects):**

| Page | Responsabilidad |
|---|---|
| `pages/LoginPage.ts` | Form de login (vía UI real) |
| `pages/SidebarPage.ts` | Navegación lateral entre módulos; expone `irA(nombre)` y `esperoActivo(nombre)` |
| `pages/KardexPage.ts` | Tabla de movimientos + filtro por tipo |
| `pages/InventoriesPage.ts` | Tabla de existencias + alertas de vencimiento y stock mínimo |

**Fixtures:**

- `fixtures/auth.fixture.ts` — define la fixture `loggedInPage`: hace `POST /api/login` con `page.request`, captura el token, lo inyecta en `sessionStorage` vía `context.addInitScript` y navega a `/Home`. Va por la vía API en lugar del form porque el proxy de Vite (`localhost:5173` → `localhost:8000`) hace que Sanctum vea el request como cross-origin y pierda la sesión por cookies; el token vive en `sessionStorage` y el `AuthContext` lo levanta en cada carga. Esto es exactamente lo que hace la app real en uso.
- `fixtures/global-teardown.ts` — limpieza entre corridas.

#### Cómo correrlas

Con el stack levantado en modo dev:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

```bash
# Headless (CI)
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec frontend npm run test:e2e

# Headed (ver el navegador)
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec frontend npm run test:e2e:headed

# UI mode (debug con inspector)
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec frontend npm run test:e2e:ui
```

Sin Docker:

```bash
cd frontend
npm install
npx playwright install chromium
npm run test:e2e
```

> **Por qué `chromium` se instala aparte:** la imagen `node:20-bookworm-slim` del Dockerfile ya viene con las dependencias de sistema de Chromium instaladas (`libnss3`, `libgbm1`, etc.), pero el binario de Chromium lo gestiona Playwright y debe bajarse con `playwright install` la primera vez. En el contenedor de dev se ejecuta dentro del mismo `exec`.

---

### Estado de la suite en CI

`ci.yml` corre `php artisan test` (Feature + Unit) en el job `backend` y `tsc --noEmit` + `npm run lint` + `npm run build` en el job `frontend`. Los specs de Playwright **no corren hoy en CI** porque el runner necesita un stack completo (db + backend + frontend) levantado en simultáneo; está previsto agregarlos a un workflow dedicado que use `docker compose up -d` antes del `playwright test`. Localmente con el override de dev todo el flujo ya funciona.

---

## Pruebas manuales

Una vez iniciados ambos entornos, podés validar:

1. **Autenticación**: login con `admin` / `password` y verificar redirección al dashboard. Repetir con los otros 4 usuarios.
2. **Bodegas**: CRUD + búsqueda + cambio de estado activo/inactivo.
3. **Grupos**: CRUD + cambio de estado.
4. **Productos**: CRUD con paginación, filtros y búsqueda textual desde el backend.
5. **Lotes**: CRUD con estados derivados (Con stock, Por vencer ≤30 días, Vencido).
6. **Sensores**: CRUD, asignación a bodegas, simulación de lecturas y alertas por rango (25°C / 70% humedad).
7. **Kardex**: registrar entrada de mercancía (regente), verificar `inventory.quantity` actualizado y alerta si el lote está vencido.
8. **Traslados**: ciclo completo `BORRADOR → SOLICITADO → APROBADO → EN_TRANSITO → RECIBIDO` entre dos bodegas; recepción parcial con discrepancia.
9. **Dispensación**: prescripción → prepare (FEFO) → confirmar dispensación; control especial requiere `authorized_by` (regente) distinto de `dispensed_by`.
10. **Permisos**: verificar que un `auxiliar` no puede aprobar traslados, y que un `auditor` ve datos enmascarados de pacientes.
11. **Reportes**: reporte de inventario exportable a Excel.
12. **Salud**: `GET /api/health` y `GET /api/ready` responden JSON esperado.

---

## Supuestos tomados

Decisiones que no estaban definidas en el enunciado y que se tomaron por criterio propio.

| # | Supuesto | Motivo |
|---|---|---|
| 1 | **La base de datos es SQL Server 2025**, que vive **dentro de Docker** como servicio `db` del compose. | El enunciado acepta PostgreSQL 16 o SQL Server y recomienda SQL Server por el bloqueo de filas y las restricciones `CHECK`. Se eligió esa versión para coincidir con la instancia SQLEXPRESS del equipo (17.x). |
| 2 | **El puerto publicado en el host es 1434** (no 1433). | SQLEXPRESS local ocupa 1433 en Windows; publicar ambos servicios en el mismo puerto genera un conflicto silencioso que confunde los errores de conexión. |
| 3 | **El backend se sirve con `php artisan serve`**, no con nginx + php-fpm. | Evita depender de un servidor web adicional. Para producción real, lo indicado sería nginx/Apache + PHP-FPM. |
| 4 | **El frontend se sirve con el servidor de desarrollo de Vite**, no con `dist/`. | Permite evaluarlo sin paso de build. Para producción se ejecuta `npm run build`. |
| 5 | **Las PKs de las tablas transaccionales son UUID**. | Las tablas nuevas (`inventories`, `movements`, `transfers`, `patients`, `prescriptions`, `dispenses`) usan UUID como PK por interoperabilidad con sistemas externos y para que IDs no sean enumerables. Los catálogos (`roles`, `modules`, `permissions`) conservan `bigint`. |
| 6 | **Los permisos son filas, no columnas.** Un permiso es un dato con nombre en `permissions`, no un booleano en el esquema (HU-07). | Hay capacidades como `autorizar-control-especial` que no son CRUD; modelarlas como columnas obligaría a una migración por cada capacidad nueva. |
| 7 | **El admin NO tiene bypass en `Gate::before`.** Pasa solo lo que su matriz le asigna. | RN-05 y RN-08 (autorización de control especial y aprobación de traslados) son exclusivas del regente. Si admin tuviera bypass, esas reglas podrían evadirse consultando el Gate desde código de negocio. |
| 8 | **El stock vive en `inventories`, no en `lots`.** La existencia se identifica por la terna bodega × producto × lote (RN-01, HU-08). | Un mismo lote puede existir en dos bodegas a la vez — es exactamente lo que hace posible un traslado. El Kardex (`movements`) es el log inmutable de cada cambio de stock. |
| 9 | **El Kardex es inmutable.** PUT y DELETE sobre `/api/movements/{id}` devuelven 405 (RN-06). | La trazabilidad exige que el log no pueda modificarse ni borrarse. Las correcciones se hacen con un movimiento AJUSTE_NEGATIVO. |
| 10 | **La dispensación es idempotente por `Idempotency-Key` HTTP.** | RN-09 exige que reintentos del mismo POST no dupliquen el descuento de stock. La garantía está en una constraint UNIQUE sobre `dispenses.idempotency_key`, no en código PHP. |
| 11 | **Los datos de pacientes se enmascaran para auditores** (RN-10, HU-10). | El auditor ve últimos 4 dígitos del documento, y `phone` y `email` ocultos; `first_name` y `last_name` sí son visibles para poder auditar. La bitácora `patient_access_logs` registra **quién y cuándo** consultó, nunca los datos vistos. |
| 12 | **FEFO aplica sobre inventories**, no sobre el catálogo de lotes (HU-10). | Al dispensar, se consulta `Inventory` ordenado por `expiration_date ASC` filtrado por `product_id` y `quantity > 0`. La dispensación puede tomar de varios lotes si uno solo no alcanza. |
| 13 | **`back/.env.docker` está versionado.** | Solo contiene una clave de desarrollo y credenciales del compose local. No hay secretos reales; cualquier valor productivo debe ir por `secrets` de GitHub Actions o variables del hosting. |
| 14 | **Producto y bodega no se pueden inactivar si tienen stock activo**, pero sí bloquear el guardado del lote. | Coherente con la regla de cascada usada en los demás módulos (grupo → producto, bodega → lote). |
| 15 | **Un lote ya vencido se puede editar** mientras no se cambie su fecha de vencimiento. | `LotResource` define "Vencido" como un estado legítimo. Bloquear la edición completa haría esos registros inmodificables. |

---

## Funcionalidad no entregada

**Estado al cierre (octubre 2026):** Kardex, Traslados y Dispensación — las tres funcionalidades grandes originalmente marcadas como prioridad — están **implementadas y documentadas** en HU-08, HU-09 y HU-10. También están entregadas las **suites automatizadas**: PHPUnit cubre HU-07/08/09/10 y los modelos de HU-01/03/04/05/06 (ver [Pruebas automáticas](#pruebas-automáticas)), y Playwright cubre login, permisos y las pantallas de Kardex e Inventarios.

Quedan como trabajo futuro explícito:

| Funcionalidad | Estado | Motivo |
|---|---|---|
| **Interfaz del frontend para Kardex, Traslados y Dispensación** | Parcial | El backend expone los endpoints completos y los `lib/*` del frontend los consumen, pero las pantallas de gestión (formularios de traslado, wizard de dispensación, tabla de Kardex) no llegaron a la versión pulida del resto del frontend. |
| **Dispensación — registro permanente y trazabilidad clínica** | Parcial | La dispensación funcional y FEFO están listos. Falta integración con prescripción electrónica firmada y reportes de dispensación por período/paciente/producto. |
| **Playwright en CI** | Pendiente | Los specs existen y corren localmente con `npm run test:e2e` sobre el stack de Docker, pero no están integrados en `.github/workflows/ci.yml` porque el runner necesita `docker compose up -d` previo al `playwright test`. |
| **Notificaciones por correo** | No implementado | Alertas de vencimiento y stock mínimo se muestran en la UI pero no se envían por email. |
| **Despliegue productivo** | Pendiente | `deploy.yml` está preparado pero ningún proveedor (Azure, Render, SSH) está configurado; los secrets de GitHub faltan. Hasta entonces, `deploy.yml` falla intencionalmente con un mensaje explicando qué falta. |
| **Reportes avanzados** | Mínima | Solo está el reporte de inventario (HU-02). Faltan reportes de Kardex, traslados pendientes, dispensación por paciente. |

> `db/scripts.sql` es un volcado obsoleto de SQL Server del desarrollo original. Está documentado arriba y no debe usarse.

---

## Documentación adicional

| Archivo | Descripción |
|---|---|
| [`documentacion/API.md`](./documentacion/API.md) | Contrato completo de todos los endpoints REST |
| [`documentacion/AI_USAGE.md`](./documentacion/AI_USAGE.md) | Metodología de uso de IA y decisiones de arquitectura |
| [`PERMISOS.md`](./PERMISOS.md) | Matriz legible de roles × módulos × capacidades (generada de HU-07/08) |
| [`documentacion/HU-01-Gestion-Productos.md`](./documentacion/HU-01-Gestion-Productos.md) | Historia de usuario: Productos |
| [`documentacion/HU-02-Busqueda-Filtrado.md`](./documentacion/HU-02-Busqueda-Filtrado.md) | Historia de usuario: Búsqueda y filtrado |
| [`documentacion/HU-03-Gestion-Bodegas.md`](./documentacion/HU-03-Gestion-Bodegas.md) | Historia de usuario: Bodegas |
| [`documentacion/HU-04-Monitoreo-Temperatura-Humedad.md`](./documentacion/HU-04-Monitoreo-Temperatura-Humedad.md) | Historia de usuario: Sensores IoT |
| [`documentacion/HU-05-Parametrizacion-Lotes.md`](./documentacion/HU-05-Parametrizacion-Lotes.md) | Historia de usuario: Lotes |
| [`documentacion/HU-06-Parametrizacion-Grupos.md`](./documentacion/HU-06-Parametrizacion-Grupos.md) | Historia de usuario: Grupos |
| [`documentacion/HU-07-Roles-Permisos.md`](./documentacion/HU-07-Roles-Permisos.md) | Historia de usuario: Permisos granulares |
| [`documentacion/HU-08-Kardex-Inventories.md`](./documentacion/HU-08-Kardex-Inventories.md) | Historia de usuario: Kardex e inventarios |
| [`documentacion/HU-09-Traslados.md`](./documentacion/HU-09-Traslados.md) | Historia de usuario: Traslados entre bodegas |
| [`documentacion/HU-10-Dispensacion.md`](./documentacion/HU-10-Dispensacion.md) | Historia de usuario: Dispensación de medicamentos |
