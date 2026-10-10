# Prueba manual del sistema de roles y permisos por modulo (HU-07).
#
# Levanta el stack y ejecuta este script:
#
#   docker compose up --build
#   pwsh -File back/tests/CurlRequest/CurlPermisos.ps1
#
# El script recorre los cinco roles y comprueba, contra la API real, que cada
# uno puede hacer lo que dice la matriz de permisos y no puede hacer lo demas.
#
# Cada caso imprime PASS o FAIL y al final hay un resumen. Un FAIL no siempre
# es un error: si cambiaste la matriz a proposito en RoleModuleSeeder, hay que
# actualizar las expectativas de este script junto con ella.

# La API aplica throttle:api, 60 peticiones por minuto por defecto. Este script
# hace unas 60 en total, asi que va justo al limite: si se corre dos veces
# seguidas, o junto con otros scripts de esta carpeta, el login puede devolver
# 429. Cuando pasa, los casos se marcan RATE LIMIT y no FAIL, para no
# confundir un limite de peticiones con un fallo de permisos.

$base = "http://localhost:8000/api"
$pass = 0
$fail = 0
$limitado = 0
$fallos = @()

# Sufijo unico por corrida: el nombre de una bodega tiene que ser único en la
# base (StoreBodegaRequest lo valida), asi que dos corridas seguidas no pueden
# crear la misma.
$sufijo = [guid]::NewGuid().ToString("N").Substring(0, 8)

# Cache de tokens: un login por rol en vez de uno por caso. Sin esto el script
# se pasa del limite de throttle a mitad de ejecucion y los fallos que aparecen
# no son de permisos sino de rate limit.
$tokens = @{}

function Get-Token([string]$nombre) {
    if ($tokens.ContainsKey($nombre)) { return $tokens[$nombre] }

    try {
        $r = Invoke-RestMethod -Method POST -Uri "$base/login" `
            -ContentType "application/json" `
            -Headers @{Accept = "application/json" } `
            -Body (@{name = $nombre; password = "password" } | ConvertTo-Json)
    } catch {
        $code = 0
        if ($_.Exception.Response) { $code = [int]$_.Exception.Response.StatusCode }
        if ($code -eq 429) { return "__LIMIT__" }
        throw
    }

    $tokens[$nombre] = $r.token
    return $r.token
}

# Devuelve el codigo de estado y el cuerpo sin lanzar excepcion, para poder
# distinguir un 403 de un 404 o un 500.
function Invoke-Check([string]$metodo, [string]$ruta, $token, $cuerpo = $null) {
    $headers = @{Authorization = "Bearer $token"; Accept = "application/json" }
    $params = @{
        Method = $metodo
        Uri = "$base$ruta"
        Headers = $headers
        ErrorAction = "Stop"
        # Si la peticion se cuelga, se corta antes de 30s y el motivo queda
        # claro, en vez de esperar el timeout por defecto del sistema.
        TimeoutSec = 30
    }
    if ($cuerpo -ne $null) {
        $params.ContentType = "application/json"
        $params.Body = ($cuerpo | ConvertTo-Json)
    }

    try {
        # -UseBasicParsing es obligatorio en Windows PowerShell 5.1: sin el,
        # Invoke-WebRequest intenta parsear la respuesta con el motor de IE y
        # lanza una excepcion de parseo aunque el HTTP haya sido correcto. Esa
        # excepcion no trae .Response, asi que el codigo se perderia y el caso
        # contaria como fallo sin motivo.
        $r = Invoke-WebRequest @params -UseBasicParsing
        return @{ Code = [int]$r.StatusCode; Body = $r.Content }
    } catch {
        $code = 0
        $body = $null

        if ($_.Exception.Response) {
            $code = [int]$_.Exception.Response.StatusCode

            # ErrorDetails.Message suele venir vacio en PS 5.1. Para diagnosticar
            # un 403 o un 404 hace falta el cuerpo de verdad, y ahi esta la
            # diferencia entre "no tiene permiso" y "no existe".
            try {
                $stream = $_.Exception.Response.GetResponseStream()
                $reader = New-Object System.IO.StreamReader($stream)
                $body = $reader.ReadToEnd()
            } catch {
                $body = $null
            }
        }

        return @{ Code = $code; Body = $body }
    }
}

function Check-Devuelve([string]$rol, [string]$metodo, [string]$ruta, [int]$esperado, $cuerpo = $null) {
    $token = Get-Token $rol

    if ($token -eq "__LIMIT__") {
        $script:limitado++
        Write-Host ("  Límite {0,-16} {1,-6} {2,-28} -> 429, caso omitido" -f $rol, $metodo, $ruta) -ForegroundColor Yellow
        return
    }

    $r = Invoke-Check $metodo $ruta $token $cuerpo

    if ($r.Code -eq 429) {
        $script:limitado++
        Write-Host ("  Límite {0,-16} {1,-6} {2,-28} -> 429, caso omitido" -f $rol, $metodo, $ruta) -ForegroundColor Yellow
        return
    }

    if ($r.Code -eq $esperado) {
        $script:pass++
        Write-Host ("  PASS  {0,-18} {1,-6} {2,-28} -> {3}" -f $rol, $metodo, $ruta, $r.Code) -ForegroundColor Green
    } else {
        $script:fail++
        $script:fallos += "$rol $metodo $ruta (esperado $esperado, recibido $($r.Code))"
        Write-Host ("  FAIL  {0,-18} {1,-6} {2,-28} -> {3} (se esperaba {4})" -f $rol, $metodo, $ruta, $r.Code, $esperado) -ForegroundColor Red
        if ($r.Body) {
            Write-Host ("        cuerpo: " + ($r.Body -replace '\s+', ' ')) -ForegroundColor DarkGray
        }
    }
}

Write-Host ""
Write-Host "=== Sistema de roles y permisos por modulo (HU-07) ===" -ForegroundColor Cyan
Write-Host "Objetivo: $base"
Write-Host ""

# ------------------------------------------------------------------
Write-Host "1. admin: acceso total" -ForegroundColor Yellow
# ------------------------------------------------------------------
Check-Devuelve "admin" "GET"    "/bodegas" 200
Check-Devuelve "admin" "GET"    "/groups" 200
Check-Devuelve "admin" "GET"    "/products" 200
Check-Devuelve "admin" "GET"    "/lots" 200
Check-Devuelve "admin" "GET"    "/sensors" 200
Check-Devuelve "admin" "GET"    "/reportes/inventario" 200
Check-Devuelve "admin" "POST"   "/bodegas" 201 @{ name = "Bodega Admin $sufijo" }
Write-Host ""

# ------------------------------------------------------------------
Write-Host "2. regente: opera inventario, consulta sensores y reportes" -ForegroundColor Yellow
# ------------------------------------------------------------------
Check-Devuelve "regente" "GET"  "/bodegas" 200
Check-Devuelve "regente" "GET"  "/lotes" 200
Check-Devuelve "regente" "GET"  "/sensors" 200
Check-Devuelve "regente" "GET"  "/reportes/inventario" 200
Check-Devuelve "regente" "POST" "/bodegas" 201 @{ name = "Bodega Regente $sufijo" }
# El regente administra tambien grupos: la matriz le da CRUD en los cuatro
# modulos de inventario. Lo que no administra son sensores ni reportes.
Check-Devuelve "regente" "POST" "/groups" 201 @{ name = "Grupo Regente $sufijo" }
Check-Devuelve "regente" "POST" "/sensors" 403 @{}
# form-data es GET, asi que exige can_read, que el regente si tiene.
Check-Devuelve "regente" "GET"  "/sensors/form-data" 200
Write-Host ""

# ------------------------------------------------------------------
Write-Host "3. auxiliar: consulta, opera lotes, no borra" -ForegroundColor Yellow
# ------------------------------------------------------------------
Check-Devuelve "auxiliar" "GET"  "/bodegas" 200
Check-Devuelve "auxiliar" "GET"  "/groups" 200
Check-Devuelve "auxiliar" "GET"  "/products" 200
Check-Devuelve "auxiliar" "GET"  "/lotes" 200
Check-Devuelve "auxiliar" "GET"  "/reportes/inventario" 200
Check-Devuelve "auxiliar" "POST" "/bodegas" 403 @{ name = "Bodega Auxiliar $sufijo" }
Check-Devuelve "auxiliar" "POST" "/products" 403 @{}
# Sensores y lecturas: la URL de lecturas dice "bodegas" pero el permiso es
# de sensores. Si esto devolviera 200, seria un error de agrupacion.
Check-Devuelve "auxiliar" "GET"  "/sensors" 403
Check-Devuelve "auxiliar" "GET"  "/bodegas/00000000-0000-0000-0000-000000000000/readings" 403
Check-Devuelve "auxiliar" "GET"  "/bodegas/00000000-0000-0000-0000-000000000000/alerts" 403
Write-Host ""

# ------------------------------------------------------------------
Write-Host "4. medico: consulta inventario, no da de alta nada" -ForegroundColor Yellow
# ------------------------------------------------------------------
Check-Devuelve "medico" "GET"  "/bodegas" 200
Check-Devuelve "medico" "GET"  "/products" 200
Check-Devuelve "medico" "GET"  "/lots" 200
Check-Devuelve "medico" "GET"  "/reportes/inventario" 200
Check-Devuelve "medico" "GET"  "/groups" 403
Check-Devuelve "medico" "GET"  "/sensors" 403
Check-Devuelve "medico" "POST" "/bodegas" 403 @{ name = "Bodega Medico $sufijo" }
Write-Host ""

# ------------------------------------------------------------------
Write-Host "5. auditor: solo lectura en los seis modulos" -ForegroundColor Yellow
# ------------------------------------------------------------------
Check-Devuelve "auditor" "GET"  "/bodegas" 200
Check-Devuelve "auditor" "GET"  "/groups" 200
Check-Devuelve "auditor" "GET"  "/products" 200
Check-Devuelve "auditor" "GET"  "/lots" 200
Check-Devuelve "auditor" "GET"  "/sensors" 200
Check-Devuelve "auditor" "GET"  "/reportes/inventario" 200
Check-Devuelve "auditor" "POST" "/bodegas" 403 @{ name = "Bodega Auditor $sufijo" }
Check-Devuelve "auditor" "POST" "/groups" 403 @{ name = "Grupo Auditor $sufijo" }
Check-Devuelve "auditor" "POST" "/sensors" 403 @{}
Write-Host ""

# ------------------------------------------------------------------
Write-Host "6. Sin token: 401 en todas las rutas" -ForegroundColor Yellow
# ------------------------------------------------------------------
foreach ($ruta in @("/bodegas", "/groups", "/products", "/lots", "/sensors", "/reportes/inventario")) {
    $r = Invoke-Check "GET" $ruta "sin-token"
    if ($r.Code -eq 401) {
        $pass++
        Write-Host ("  PASS  {0,-18} {1,-6} {2,-28} -> {3}" -f "sin token", "GET", $ruta, $r.Code) -ForegroundColor Green
    } else {
        $fail++
        $fallos += "sin token GET $ruta (esperado 401, recibido $($r.Code))"
        Write-Host ("  FAIL  {0,-18} {1,-6} {2,-28} -> {3} (se esperaba 401)" -f "sin token", "GET", $ruta, $r.Code) -ForegroundColor Red
    }
}
Write-Host ""

# ------------------------------------------------------------------
Write-Host "7. Orden de middlewares: permiso antes que modelo" -ForegroundColor Yellow
# ------------------------------------------------------------------
# Con un id que no existe, un usuario sin permiso debe recibir 403 y no 404.
# Un 403 delata que el permiso se evalua antes de buscar el registro; un 404
# permitiria deducir quais bodegas existen.
$tokenAuditor = Get-Token "auditor"
$inexistente = "/bodegas/00000000-0000-0000-0000-000000000000"
$r = Invoke-Check "DELETE" $inexistente $tokenAuditor

if ($r.Code -eq 403) {
    $pass++
    Write-Host ("  PASS  {0,-18} {1,-6} {2,-28} -> {3}" -f "auditor", "DELETE", $inexistente, $r.Code) -ForegroundColor Green
} else {
    $fail++
    $fallos += "auditor DELETE $inexistente (esperado 403, recibido $($r.Code)) - el binding corre antes del permiso"
    Write-Host ("  FAIL  {0,-18} {1,-6} {2,-28} -> {3} (se esperaba 403)" -f "auditor", "DELETE", $inexistente, $r.Code) -ForegroundColor Red
    Write-Host "        Si devuelve 404, EnsureModule quedo despues de SubstituteBindings en Kernel." -ForegroundColor DarkYellow
}

# El mismo id, pero con permiso: ahi si debe dar 404, que confirma que el 403
# anterior viene del permiso y no de un fallo de la peticion.
$tokenAdmin = Get-Token "admin"
$r = Invoke-Check "DELETE" $inexistente $tokenAdmin

if ($r.Code -eq 404) {
    $pass++
    Write-Host ("  PASS  {0,-18} {1,-6} {2,-28} -> {3}" -f "admin", "DELETE", $inexistente, $r.Code) -ForegroundColor Green
} else {
    $fail++
    $fallos += "admin DELETE $inexistente (esperado 404, recibido $($r.Code))"
    Write-Host ("  FAIL  {0,-18} {1,-6} {2,-28} -> {3} (se esperaba 404)" -f "admin", "DELETE", $inexistente, $r.Code) -ForegroundColor Red
}
Write-Host ""

# ------------------------------------------------------------------
Write-Host "8. El login sigue devolviendo el rol como texto" -ForegroundColor Yellow
# ------------------------------------------------------------------
# El frontend usa UserResource.role para decidir que pantallas mostrar. Si
# esto devuelve un objeto en vez de un string, el AuthContext se rompe.
$login = Invoke-RestMethod -Method POST -Uri "$base/login" `
    -ContentType "application/json" `
    -Headers @{Accept = "application/json" } `
    -Body (@{name = "regente"; password = "password" } | ConvertTo-Json)

if ($login.user.role -is [string] -and $login.user.role -eq "regente_farmacia") {
    $pass++
    Write-Host ("  PASS  {0,-18} role = '{1}' (string)" -f "regente", $login.user.role) -ForegroundColor Green
} else {
    $fail++
    $fallos += "login.user.role no es el string esperado (recibido: $($login.user.role))"
    Write-Host ("  FAIL  role = '{0}' (tipo: {1})" -f $login.user.role, $login.user.role.GetType().Name) -ForegroundColor Red
    Write-Host "        UserResource debe exponer `$this->role?->name, no el modelo Role." -ForegroundColor DarkYellow
}
Write-Host ""

# ------------------------------------------------------------------
Write-Host "=== Resumen ===" -ForegroundColor Cyan
Write-Host ("PASS: {0}    FAIL: {1}    Omitidos por limite de peticiones: {2}" -f $pass, $fail, $limitado)
if ($fail -gt 0) {
    Write-Host ""
    Write-Host "Casos fallidos:" -ForegroundColor Red
    foreach ($f in $fallos) { Write-Host "  - $f" -ForegroundColor Red }
    exit 1
}
if ($limitado -gt 0) {
    Write-Host ""
    Write-Host "Hay casos sin verificar por throttle:api (60/min). Esperá un minuto y volvé a correrlo." -ForegroundColor Yellow
    exit 2
}

Write-Host "Todos los casos de permisos se comportaron como define la matriz." -ForegroundColor Green
exit 0