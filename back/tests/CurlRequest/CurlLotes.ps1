# Asegurar codificación UTF-8 en PowerShell
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

# CRUL request traer datos iniciales del formulario lotes y unidades

# 1. Autenticarse para obtener el Token
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 2. Consultar el listado completo (index)
Write-Host "`n--- DATOS INICIALES DEL FORMULARIO DE LOTES ---" -ForegroundColor Green
$listado = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/lots/form-data" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$listado | ConvertTo-Json -Depth 5

# CURL request traer listado de datos de LOTES

# 1. Autenticarse para obtener el Token
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 2. Consultar el listado completo (index)
Write-Host "`n--- LISTADO DE LOTES PAGINADOS ---" -ForegroundColor Green
$listado = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/lots" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$listado | ConvertTo-Json -Depth 5


# CURL request crear un nuevo registro de lote

# 1. Autenticarse para obtener el Token (si ya expiró o necesitas refrescarlo)
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 2. Generar el body en JSON para el nuevo lote
$productIdValido = "85DD6260-EC36-435F-80BC-B4E1C3E7167A"
$bodegaIdValido = "D092C02E-D87B-495F-A996-149384D7CAEA"
$createBody = [System.Text.Encoding]::UTF8.GetBytes((@{
    name            = "Lote 002"
    product_id      = $productIdValido
    warehouse_id    = $bodegaIdValido
    stock           = 100
    expiration_date = "2026-08-15"
    description     = "Lote de prueba para los productos"
} | ConvertTo-Json))

# 3. Hacer la petición POST para ingresar los datos
Write-Host "`n--- CREAR NUEVO LOTE ---" -ForegroundColor Green
try {
    $nuevoLote = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/lots" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" } -ContentType "application/json; charset=utf-8" -Body $createBody
    $nuevoLote | ConvertTo-Json -Depth 5
} catch {
    Write-Error $_.Exception.Message
    $_.Exception.Response | ConvertTo-Json -Depth 3
}

# PUT para modificar el lote (requiere TODOS los campos)
$loteId = "BFD6C779-FF7E-40E0-9474-73EB15A6DF68"

$updateBody = @{
    name            = "Lote 002"
    product_id      = "85DD6260-EC36-435F-80BC-B4E1C3E7167A"
    warehouse_id    = "D092C02E-D87B-495F-A996-149384D7CAEA"
    stock           = 0
    expiration_date = "2026-08-15"
} | ConvertTo-Json

Write-Host "`n--- ACTUALIZAR LOTE ---" -ForegroundColor Green
try {
    $updateLote = Invoke-RestMethod -Method PUT -Uri "http://localhost:8000/api/lots/$loteId" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" } -ContentType "application/json; charset=utf-8" -Body $updateBody
    $updateLote | ConvertTo-Json -Depth 5
} catch {
    Write-Error $_.Exception.Message
    $_.Exception.Response | ConvertTo-Json -Depth 3
}

# CURL traer información de un dato de lote (show)

# 1. Asignar el ID UUID del lote que quieres consultar (ajustar con uno existente en tu BD)
$loteId = "BFD6C779-FF7E-40E0-9474-73EB15A6DF68"

# 2. Autenticarse para obtener el token
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 3. Consultar el lote específico usando el UUID
Write-Host "`n--- MOSTRAR DETALLE DE LOTE ($loteId) ---" -ForegroundColor Green
try {
    $show = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/lots/$loteId" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
    $show | ConvertTo-Json -Depth 5
} catch {
    Write-Error $_.Exception.Message
}


# CURL inactivar/activar un lote (toggle-status)

# Asigna aquí el UUID del lote que quieres cambiar de estado
$loteId = "BFD6C779-FF7E-40E0-9474-73EB15A6DF68"

# Autenticarse
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

Write-Host "--- CAMBIAR ESTADO DE LOTE ($loteId) ---"
$toggle = Invoke-RestMethod -Method PATCH -Uri "http://localhost:8000/api/lots/$loteId/toggle-status" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$toggle | ConvertTo-Json -Depth 5


# CURL eliminar un lote (soft delete)

# Asigna aquí el UUID del lote que quieres eliminar
$loteId = "BFD6C779-FF7E-40E0-9474-73EB15A6DF68"

# Autenticarse
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

Write-Host "--- ELIMINAR LOTE ($loteId) ---"
$delete = Invoke-RestMethod -Method DELETE -Uri "http://localhost:8000/api/lots/$loteId" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$delete | ConvertTo-Json -Depth 5