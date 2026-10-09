# Asegurar codificación UTF-8 en PowerShell
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

# CURL request traer listado de datos de Grupos

# 1. Autenticarse para obtener el Token
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 2. Consultar el listado completo (index)
Write-Host "--- LISTADO DE GRUPOS ---"
$listado = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/groups" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$listado | ConvertTo-Json -Depth 5


# CURL request crear un nuevo registro de Grupo

# 1. Autenticarse para obtener el Token (si ya expiró o necesitas refrescarlo)
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 2. Generar el body en JSON para el nuevo grupo
$body = [System.Text.Encoding]::UTF8.GetBytes((@{
    name        = "Analgésicos"
    description = "Medicamentos destinados a aliviar o reducir el dolor."
} | ConvertTo-Json))

# 3. Hacer la petición POST para ingresar los datos
Write-Host "--- CREAR NUEVO GRUPO ---"
$nuevoGrupo = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/groups" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" } -ContentType "application/json; charset=utf-8" -Body $body
$nuevoGrupo | ConvertTo-Json -Depth 5


# CURL traer información de un dato de Grupo (show)

# 1. Asignar el ID UUID del grupo que quieres consultar (ajustar con uno existente en tu BD)
$grupoId = "43064f7a-3e92-4a7c-994e-db8fb60aa0a4"

# 2. Autenticarse para obtener el token
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 3. Consultar el grupo específico usando el UUID
Write-Host "--- MOSTRAR GRUPO ($grupoId) ---"
$show = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/groups/$grupoId" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$show | ConvertTo-Json -Depth 5


# CURL inactivar/activar un grupo (toggle-status)

# Asigna aquí el UUID del grupo que quieres cambiar de estado
$grupoId = "43064f7a-3e92-4a7c-994e-db8fb60aa0a4"

# Autenticarse
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

Write-Host "--- CAMBIAR ESTADO DE GRUPO ($grupoId) ---"
$toggle = Invoke-RestMethod -Method PATCH -Uri "http://localhost:8000/api/groups/$grupoId/toggle-status" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$toggle | ConvertTo-Json -Depth 5


# CURL eliminar un grupo (soft delete)

# Asigna aquí el UUID del grupo que quieres eliminar
$grupoId = "43064f7a-3e92-4a7c-994e-db8fb60aa0a4"

# Autenticarse
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

Write-Host "--- ELIMINAR GRUPO ($grupoId) ---"
$delete = Invoke-RestMethod -Method DELETE -Uri "http://localhost:8000/api/groups/$grupoId" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$delete | ConvertTo-Json -Depth 5