#!/usr/bin/env bash
set -e

API="http://127.0.0.1:8000/bovs/api"   # ajustá si tu API vive en otro host/puerto
USUARIO="DEMO"
PASSWORD="tu_password_de_prueba"

UUID_A="11111111-1111-4111-8111-111111111111"
UUID_B="22222222-2222-4222-8222-222222222222"

echo "=== 1. Login ==="
RESP=$(curl -s -X POST "$API/auth_controller.php" \
  -H "Content-Type: application/json" \
  -d "{\"action\":\"login\",\"usuario\":\"$USUARIO\",\"password\":\"$PASSWORD\",\"dispositivo\":\"curl-test\",\"dispositivo_id\":\"$UUID_A\"}")

echo "$RESP" | head -c 400; echo
TOKEN=$(echo "$RESP" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
[ -z "$TOKEN" ] && { echo "FALLÓ: no se obtuvo token"; exit 1; }
echo "Token: ${TOKEN:0:16}..."

echo
echo "=== 2. Vincular dispositivo A ==="
curl -s -X POST "$API/auth_controller.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"action\":\"vincular_dispositivo\",\"dispositivo_id\":\"$UUID_A\",\"nombre_dispositivo\":\"PC de prueba A\"}"
echo

echo
echo "=== 3. Estado de A (debería ser vinculado=true) ==="
curl -s -X POST "$API/auth_controller.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"action\":\"estado_dispositivo\",\"dispositivo_id\":\"$UUID_A\"}"
echo

echo
echo "=== 4. Intentar vincular dispositivo B (debería fallar 409) ==="
curl -s -X POST "$API/auth_controller.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"action\":\"vincular_dispositivo\",\"dispositivo_id\":\"$UUID_B\",\"nombre_dispositivo\":\"PC de prueba B\"}"
echo

echo
echo "=== 5. Listar dispositivos ==="
curl -s -X POST "$API/auth_controller.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"action\":\"listar_dispositivos\"}"
echo

echo
echo "=== 6. Desvincular dispositivo A (el propio) ==="
curl -s -X POST "$API/auth_controller.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"action\":\"desvincular_dispositivo\",\"dispositivo_id\":\"$UUID_A\",\"motivo\":\"prueba\"}"
echo

echo
echo "=== 7. Vincular dispositivo B (ahora sí debe funcionar) ==="
curl -s -X POST "$API/auth_controller.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"action\":\"vincular_dispositivo\",\"dispositivo_id\":\"$UUID_B\",\"nombre_dispositivo\":\"PC de prueba B\"}"
echo

echo
echo "=== 8. Estado de A (ahora debería ser vinculado=false / otro_dispositivo) ==="
curl -s -X POST "$API/auth_controller.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"action\":\"estado_dispositivo\",\"dispositivo_id\":\"$UUID_A\"}"
echo

echo
echo "=== 9. Revincular A (debe fallar porque B está activo) ==="
curl -s -X POST "$API/auth_controller.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"action\":\"vincular_dispositivo\",\"dispositivo_id\":\"$UUID_A\",\"nombre_dispositivo\":\"PC de prueba A\"}"
echo

echo
echo "=== FIN ==="