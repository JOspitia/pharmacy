# CURL request traer listado de datos

# 1. Autenticarse para obtener el Token
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 2. Consultar el listado completo (index)
Write-Host "--- LISTADO DE BODEGAS ---"
$listado = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/bodegas" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$listado | ConvertTo-Json -Depth 5


# CURL request crear un nuevo registro

# 1. Autenticarse para obtener el Token (si ya expiró o necesitas refrescarlo)
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 2. Generar el body en JSON para la nueva bodega
$body = @{
    name        = "Bodega Principal Sur"
    description = "Bodega destinada al almacenamiento de medicamentos generales de la zona sur."
} | ConvertTo-Json

# 3. Hacer la petición POST para ingresar los datos
Write-Host "--- CREAR NUEVA BODEGA ---"
$nuevaBodega = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/bodegas" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" } -ContentType "application/json" -Body $body
$nuevaBodega | ConvertTo-Json -Depth 5


# CURL traer información de un dato

# 1. Asignar el ID UUID de la bodega que quieres consultar
$bodegaId = "3558B3CE-E11A-43B4-A0AD-C4AA753789C2"

# 2. Autenticarse para obtener el token
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 3. Consultar la bodega específica usando el UUID
Write-Host "--- MOSTRAR BODEGA ($bodegaId) ---"
$show = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/bodegas/$bodegaId" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$show | ConvertTo-Json -Depth 5


#CURL inactivar una bodega

# Asigna aquí el UUID de la bodega que quieres cambiar de estado
$bodegaId = "310e89bd-b872-487e-8981-ddcf2d65c95e"

# Autenticarse
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

Write-Host "--- CAMBIAR ESTADO DE BODEGA ($bodegaId) ---"
$toggle = Invoke-RestMethod -Method PATCH -Uri "http://localhost:8000/api/bodegas/$bodegaId/toggle-status" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$toggle | ConvertTo-Json -Depth 5


#CURL eliminar una bodega sofdelet

# Asigna aquí el UUID de la bodega que quieres eliminar
$bodegaId = "310e89bd-b872-487e-8981-ddcf2d65c95e"

# Autenticarse
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

Write-Host "--- ELIMINAR BODEGA ($bodegaId) ---"
$delete = Invoke-RestMethod -Method DELETE -Uri "http://localhost:8000/api/bodegas/$bodegaId" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$delete | ConvertTo-Json -Depth 5