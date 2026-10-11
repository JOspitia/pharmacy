#!/bin/sh
# Test rapido: verifica que /api/inventories devuelve objetos anidados
# para lot, product y bodega (bug del frontend: veia "—" en esas columnas).
set -e

LOGIN=$(curl -s -X POST http://localhost:8000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"name":"regente","password":"password"}')

TOKEN=$(printf '%s' "$LOGIN" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')

if [ -z "$TOKEN" ]; then
  echo "ERROR: no se pudo obtener token"
  echo "Login response: $LOGIN"
  exit 1
fi

echo "=== Primera fila de /api/inventories ==="
curl -s -H "Authorization: Bearer $TOKEN" \
  'http://localhost:8000/api/inventories?per_page=1' | head -c 1800
echo
