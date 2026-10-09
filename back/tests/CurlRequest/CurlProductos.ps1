# Asegurar codificación UTF-8 en PowerShell
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

# CRUL request traer datos iniciales del formulario grupos y unidades

# 1. Autenticarse para obtener el Token
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 2. Consultar el listado completo (index)
Write-Host "`n--- DATOS INICIALES DEL FORMULARIO DE PRODUCTOS ---" -ForegroundColor Green
$listado = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/products/form-data" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$listado | ConvertTo-Json -Depth 5

# CURL request traer listado de datos de Productos

# 1. Autenticarse para obtener el Token
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 2. Consultar el listado completo (index)
Write-Host "`n--- LISTADO DE PRODUCTOS PAGINADOS ---" -ForegroundColor Green
$listado = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/products" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$listado | ConvertTo-Json -Depth 5


# CURL request crear un nuevo registro de producto

# 1. Autenticarse para obtener el Token (si ya expiró o necesitas refrescarlo)
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 2. Generar el body en JSON para el nuevo producto
$grupoIdValido = "3ABCABD1-327D-442E-B9C2-213E1C7588E5"
$unidadIdValida = "07590B53-77A9-4D60-B8BE-94D2D5B1C45B"
$createBody = [System.Text.Encoding]::UTF8.GetBytes((@{
    name                = "Acetaminofén 1000mg – Tableta"
    cum                 = "19927641-2"
    barcode             = "7701234567891"
    invima_registration = "INVIMA 2020M-0019855"
    group_id            = $grupoIdValido
    unit_id             = $unidadIdValida
    description         = "Analgésico y antipirético de uso común."
} | ConvertTo-Json))

# 3. Hacer la petición POST para ingresar los datos
Write-Host "`n--- CREAR NUEVO PRODUCTO ---" -ForegroundColor Green
try {
    $nuevoProducto = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/products" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" } -ContentType "application/json; charset=utf-8" -Body $createBody
    $nuevoProducto | ConvertTo-Json -Depth 5
} catch {
    Write-Error $_.Exception.Message
    $_.Exception.Response | ConvertTo-Json -Depth 3
}


# CURL traer información de un dato de producto (show)

# 1. Asignar el ID UUID del producto que quieres consultar (ajustar con uno existente en tu BD)
$productoId = "DC8C9CB4-612A-42AC-A4BE-810D0214EB92"

# 2. Autenticarse para obtener el token
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 3. Consultar el producto específico usando el UUID
Write-Host "`n--- MOSTRAR DETALLE DE PRODUCTO ($productoId) ---" -ForegroundColor Green
try {
    $show = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/products/$productoId" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
    $show | ConvertTo-Json -Depth 5
} catch {
    Write-Error $_.Exception.Message
}


# CURL inactivar/activar un producto (toggle-status)

# Asigna aquí el UUID del producto que quieres cambiar de estado
$productoId = "85dd6260-ec36-435f-80bc-b4e1c3e7167a"

# Autenticarse
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

Write-Host "--- CAMBIAR ESTADO DE PRODUCTO ($productoId) ---"
$toggle = Invoke-RestMethod -Method PATCH -Uri "http://localhost:8000/api/products/$productoId/toggle-status" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$toggle | ConvertTo-Json -Depth 5


# CURL eliminar un producto (soft delete)

# Asigna aquí el UUID del producto que quieres eliminar
$productoId = "85dd6260-ec36-435f-80bc-b4e1c3e7167a"

# Autenticarse
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

Write-Host "--- ELIMINAR PRODUCTO ($productoId) ---"
$delete = Invoke-RestMethod -Method DELETE -Uri "http://localhost:8000/api/products/$productoId" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$delete | ConvertTo-Json -Depth 5