$loginResponse = Invoke-RestMethod -Method POST -Uri "http://localhost:8000/api/login" -ContentType "application/json" -Headers @{Accept = "application/json" } -Body '{"name":"admin","password":"password"}'
$token = $loginResponse.token
$headers = @{ Authorization = "Bearer $token"; Accept = "application/json" }

# 1. CONSULTA INICIAL SIN FILTROS (paginada, default 30 por página, orden A-Z)

Write-Host "`n--- 1. CONSULTA INICIAL SIN FILTROS ---" -ForegroundColor Green
try {
    $report = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/reportes/inventario" -Headers $headers
    Write-Host "Total: $($report.meta.total) | Página: $($report.meta.current_page) | Por página: $($report.meta.per_page)"
    $report.data | Select-Object -First 5 cum, product_name, stock, lot_code, bodega_name, alerta_color | Format-Table -AutoSize
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}


# 2. FILTRAR POR CUM (búsqueda parcial)

Write-Host "`n--- 2. FILTRAR POR CUM ---" -ForegroundColor Green
try {
    $report = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/reportes/inventario?cum=19927641" -Headers $headers
    Write-Host "Resultados para cum=19927641: $($report.meta.total)"
    $report.data | Select-Object cum, product_name, lot_code | Format-Table -AutoSize
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}


# 3. FILTRAR POR NOMBRE PRODUCTO (búsqueda parcial)

Write-Host "`n--- 3. FILTRAR POR NOMBRE PRODUCTO ---" -ForegroundColor Green
try {
    $report = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/reportes/inventario?name=Acetaminofén" -Headers $headers
    Write-Host "Resultados para name=Acetaminofén: $($report.meta.total)"
    $report.data | Select-Object product_name, lot_code, bodega_name | Format-Table -AutoSize
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}


# 4. FILTRAR POR BODEGA

Write-Host "`n--- 4. FILTRAR POR BODEGA ---" -ForegroundColor Green
try {
    # Reemplaza con un UUID real de tu tabla bodegas
    $bodegaId = "D092C02E-D87B-495F-A996-149384D7CAEA"
    $report = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/reportes/inventario?bodega_id=$bodegaId" -Headers $headers
    Write-Host "Resultados para bodega: $($report.meta.total)"
    $report.data | Select-Object product_name, lot_code, bodega_name | Format-Table -AutoSize
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}


# 5. FILTRAR POR GRUPO

Write-Host "`n--- 5. FILTRAR POR GRUPO ---" -ForegroundColor Green
try {
    # Reemplaza con un UUID real de tu tabla groups
    $groupId = "3ABCABD1-327D-442E-B9C2-213E1C7588E5"
    $report = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/reportes/inventario?group_id=$groupId" -Headers $headers
    Write-Host "Resultados para group_id: $($report.meta.total)"
    $report.data | Select-Object product_name, group_name, lot_code | Format-Table -AutoSize
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}


# 6. FILTRAR POR CÓDIGO DE LOTE

Write-Host "`n--- 6. FILTRAR POR CÓDIGO DE LOTE ---" -ForegroundColor Green
try {
    $report = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/reportes/inventario?lot_code=Lote 001" -Headers $headers
    Write-Host "Resultados para lot_code=Lote 001: $($report.meta.total)"
    $report.data | Select-Object lot_code, product_name, stock, bodega_name | Format-Table -AutoSize
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}


# 7. FILTRAR POR ESTADO "CON STOCK"

Write-Host "`n--- 7. FILTRAR POR ESTADO 'Con stock' ---" -ForegroundColor Green
try {
    $report = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/reportes/inventario?estado=con_stock" -Headers $headers
    Write-Host "Resultados con stock > 0: $($report.meta.total)"
    $report.data | Select-Object lot_code, product_name, stock, expiration_date | Format-Table -AutoSize
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}


# 8. FILTRAR POR ESTADO "POR VENCER"

Write-Host "`n--- 8. FILTRAR POR ESTADO 'Por vencer' ---" -ForegroundColor Green
try {
    $report = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/reportes/inventario?estado=por_vencer" -Headers $headers
    Write-Host "Resultados por vencer: $($report.meta.total)"
    $report.data | Select-Object lot_code, product_name, expiration_date, stock, alerta_color, alerta_exp | Format-Table -AutoSize
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}


# 9. FILTRAR POR ESTADO "VENCIDO"

Write-Host "`n--- 9. FILTRAR POR ESTADO 'Vencido' ---" -ForegroundColor Green
try {
    $report = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/reportes/inventario?estado=vencido" -Headers $headers
    Write-Host "Resultados vencidos: $($report.meta.total)"
    $report.data | Select-Object lot_code, product_name, expiration_date, stock, alerta_color | Format-Table -AutoSize
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}


# 10. FILTRAR POR RANGO DE FECHAS DE VENCIMIENTO

Write-Host "`n--- 10. FILTRAR POR RANGO DE VENCIMIENTO ---" -ForegroundColor Green
try {
    $from = "2026-07-01"
    $to = "2026-12-31"
    $report = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/reportes/inventario?date_from=$from&date_to=$to" -Headers $headers
    Write-Host "Resultados entre $($report.meta.total)"
    $report.data | Select-Object lot_code, expiration_date | Format-Table -AutoSize
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}


# 11. ORDENAMIENTO POR COLUMNA

Write-Host "`n--- 11. ORDENAMIENTO (stock desc) ---" -ForegroundColor Green
try {
    $report = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/reportes/inventario?sort_by=lots.stock&sort_dir=desc&per_page=5" -Headers $headers
    Write-Host "Ordenado por stock DESC:"
    $report.data | Select-Object lot_code, product_name, stock | Format-Table -AutoSize
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}


# 12. FILTROS COMBINADOS (lógica AND)

Write-Host "`n--- 12. FILTROS COMBINADOS (AND) ---" -ForegroundColor Green
try {
    $report = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/reportes/inventario?bodega_id=$bodegaId&estado=con_stock&per_page=5" -Headers $headers
    Write-Host "Resultados combinados: $($report.meta.total)"
    $report.data | Select-Object lot_code, product_name, stock, bodega_name, alerta_color | Format-Table -AutoSize
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}


# 13. ERROR 422: RANGO DE FECHAS INVÁLIDO

Write-Host "`n--- 13. RANGO DE FECHAS INVÁLIDO (debe dar 422) ---" -ForegroundColor Red
try {
    Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/reportes/inventario?date_from=2030-12-31&date_to=2020-01-01" -Headers $headers -ErrorAction Stop
    Write-Host "ERROR: debería haber dado 422" -ForegroundColor Red
}
catch {
    if ($_.Exception.Response.StatusCode.value__ -eq 422) {
        Write-Host "OK: 422 retornado" -ForegroundColor Green
    }
    else {
        Write-Host "Status inesperado: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Yellow
    }
}


# 14. INDICADORES DE ALERTA DE EXPIRACIÓN (colores)

Write-Host "`n--- 14. INDICADORES DE ALERTA DE EXPIRACIÓN ---" -ForegroundColor Green
try {
    $report = Invoke-RestMethod -Method GET -Uri "http://localhost:8000/api/reportes/inventario?per_page=20" -Headers $headers
    Write-Host "Distribución de colores:"
    $report.data | Group-Object alerta_color | ForEach-Object {
        $color = $_.Name
        $count = $_.Count
        Write-Host "  $color : $count" -ForegroundColor $(switch ($color) { 'verde' { 'Green' } 'amarillo' { 'Yellow' } 'rojo' { 'Red' } default { 'Gray' } })
    }
    Write-Host ""
    Write-Host "Muestra de cada color:"
    $report.data | Select-Object lot_code, product_name, expiration_date, alerta_color, alerta_exp -First 10 | Format-Table -AutoSize
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`n=== FIN DEL SCRIPT ===" -ForegroundColor Cyan
