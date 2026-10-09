# Asegurar codificación UTF-8 en PowerShell
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

# CRUL request traer datos iniciales del formulario grupos y unidades

# 1. Autenticarse para obtener el Token
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 2. Consultar el listado completo (index)
Write-Host "`n--- DATOS INICIALES DEL FORMULARIO DE SENSORES ---" -ForegroundColor Green
$listado = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/sensors/form-data" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$listado | ConvertTo-Json -Depth 5

# CURL request traer listado de datos de Sensores

# 1. Autenticarse para obtener el Token
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 2. Consultar el listado completo (index)
Write-Host "`n--- LISTADO DE SENSORES PAGINADOS ---" -ForegroundColor Green
$listado = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/sensors" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$listado | ConvertTo-Json -Depth 5


# CURL request crear un nuevo registro de sensor

# 1. Autenticarse para obtener el Token (si ya expiró o necesitas refrescarlo)
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 2. Generar el body en JSON para el nuevo sensor
$warehouseIdValido = "D092C02E-D87B-495F-A996-149384D7CAEA"
$createBody = [System.Text.Encoding]::UTF8.GetBytes((@{
            name         = "Sensor de Humedad modelo X1"
            warehouse_id = $warehouseIdValido
            description  = "Sensor de humedad marca Testo modelo X1, rango de medición de 0% a 100% humedad relativa, precisión ±0.5%HR, comunicación inalámbrica Bluetooth."
        } | ConvertTo-Json))

# 3. Hacer la petición POST para ingresar los datos
Write-Host "`n--- CREAR NUEVO SENSOR ---" -ForegroundColor Green
try {
    $nuevoSensor = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/sensors" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" } -ContentType "application/json; charset=utf-8" -Body $createBody
    $nuevoSensor | ConvertTo-Json -Depth 5
}
catch {
    Write-Error $_.Exception.Message
    $_.Exception.Response | ConvertTo-Json -Depth 3
}


# CURL traer información de un dato de sensor (show)

# 1. Asignar el ID UUID del sensor que quieres consultar (ajustar con uno existente en tu BD)
$sensorId = "A3F9694D-A9D6-46B7-ADCE-8F64BA703432"

# 2. Autenticarse para obtener el token
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

# 3. Consultar el sensor específico usando el UUID
Write-Host "`n--- MOSTRAR DETALLE DE SENSOR ($sensorId) ---" -ForegroundColor Green
try {
    $show = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/sensors/$sensorId" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
    $show | ConvertTo-Json -Depth 5
}
catch {
    Write-Error $_.Exception.Message
}

# PUT para modificar el sensor
$sensorId = "A3F9694D-A9D6-46B7-ADCE-8F64BA703432"

$updateBody = @{
    name         = "Sensor de Temperatura modelo X2"
    warehouse_id = "D092C02E-D87B-495F-A996-149384D7CAEA"
    description  = "Sensor de temperatura marca Testo modelo X1, rango de medición de -20°C a 50°C, precisión ±0.5°C, comunicación inalámbrica Bluetooth."
} | ConvertTo-Json

Write-Host "`n--- ACTUALIZAR SENSOR ---" -ForegroundColor Green
try {
    $updateSensor = Invoke-RestMethod -Method PUT -Uri "http://localhost:8000/api/sensors/$sensorId" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" } -ContentType "application/json; charset=utf-8" -Body $updateBody
    $updateSensor | ConvertTo-Json -Depth 5
}
catch {
    Write-Error $_.Exception.Message
    $_.Exception.Response | ConvertTo-Json -Depth 3
}


# CURL inactivar/activar un sensor (toggle-status)

# Asigna aquí el UUID del sensor que quieres cambiar de estado
$sensorId = "A3F9694D-A9D6-46B7-ADCE-8F64BA703432"

# Autenticarse
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

Write-Host "--- CAMBIAR ESTADO DE SENSOR ($sensorId) ---"
$toggle = Invoke-RestMethod -Method PATCH -Uri "http://localhost:8000/api/sensors/$sensorId/toggle-status" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$toggle | ConvertTo-Json -Depth 5


# CURL eliminar un sensor (soft delete)

# Asigna aquí el UUID del sensor que quieres eliminar
$sensorId = "A3F9694D-A9D6-46B7-ADCE-8F64BA703432"

# Autenticarse
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

Write-Host "--- ELIMINAR SENSOR ($sensorId) ---"
$delete = Invoke-RestMethod -Method DELETE -Uri "http://localhost:8000/api/sensors/$sensorId" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$delete | ConvertTo-Json -Depth 5


# CURL obtener lecturas de una bodega

# Asigna aquí el UUID de la bodega que quieres consultar
$bodegaIdValido = "D092C02E-D87B-495F-A996-149384D7CAEA"

# Autenticarse
$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

Write-Host "`n--- OBTENER LECTURAS DE UNA BODEGA ($bodegaIdValido) ---" -ForegroundColor Green
$show = Invoke-RestMethod  -Method GET -Uri "http://localhost:8000/api/bodegas/$bodegaIdValido/readings" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$show | ConvertTo-Json -Depth 5


# CURL obtener solo las lecturas con alertas
$bodegaIdValido = "D092C02E-D87B-495F-A996-149384D7CAEA"
$alertOnly = 1
$timestamp = (Get-Date).AddMinutes(-1).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")

$response = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $response.token

Write-Host "`n--- CONSULTANDO ALERTAS EN UN RANGO DE TIEMPO ---" -ForegroundColor Green
$show = Invoke-RestMethod  -Method GET -Uri "http://localhost:8000/api/bodegas/$bodegaIdValido/alerts?alert_only=$alertOnly&created_after=$timestamp" -Headers @{Authorization = "Bearer $token"; Accept = "application/json" }
$show | ConvertTo-Json -Depth 5
