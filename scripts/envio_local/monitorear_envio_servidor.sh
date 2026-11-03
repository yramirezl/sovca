#!/bin/bash
# =============================================================================
# SOVCA · ¿Ya puede el SERVIDOR enviar correo?
# -----------------------------------------------------------------------------
# Ejecuta la prueba en el servidor de producción por SSH y devuelve el
# resultado. La prueba NO envía ningún mensaje: habla SMTP hasta RCPT TO y
# corta con RSET, sin llegar a DATA. No molesta a ningún destinatario.
#
# Uso
#   ./monitorear_envio_servidor.sh                  # una comprobación
#   ./monitorear_envio_servidor.sh --esperar=15     # reintenta cada 15 min
#                                                   # hasta que funcione
#   ./monitorear_envio_servidor.sh --a=otro@x.com   # dirección de control
#   ./monitorear_envio_servidor.sh --verboso        # muestra todos los pasos
#
# Códigos de salida: 0 = el servidor ya puede enviar · 1 = sigue bloqueado
#                    2 = no se pudo ejecutar en el servidor (SSH)
# =============================================================================
set -uo pipefail

REMOTO="${SOVCA_REMOTE:-root@57.129.187.133}"
RUTA="${SOVCA_RUTA:-/var/www/html/sovca}"
CONTROL="backupglobalsovcabovs@gmail.com"
ESPERAR=0
VERBOSO=0

for A in "$@"; do
  case "$A" in
    --esperar=*) ESPERAR="${A#--esperar=}" ;;
    --a=*)       CONTROL="${A#--a=}" ;;
    --verboso)   VERBOSO=1 ;;
    -h|--help)   sed -n '2,20p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Opción no reconocida: $A" >&2; exit 2 ;;
  esac
done

msg() { echo "[$(date '+%F %T')] $*"; }

probar() {
  local argumentos="--to=$CONTROL"
  [ "$VERBOSO" -eq 1 ] || true

  ssh -o BatchMode=yes -o ConnectTimeout=10 "$REMOTO" \
      "php '$RUTA/scripts/probar_envio_correo.php' '$argumentos'" 2>&1
  return $?
}

if [ "$ESPERAR" -eq 0 ]; then
  probar
  ESTADO=$?
  case "$ESTADO" in
    0) msg "el servidor YA puede enviar correo. Se puede retirar el Plan B." ;;
    1) msg "el servidor sigue sin poder enviar. Se mantiene el envío local." ;;
    *) msg "no se pudo ejecutar la prueba en $REMOTO (revisa el acceso SSH)." ;;
  esac
  exit "$ESTADO"
fi

# Bucle de vigilancia: para cuando el servidor pueda enviar.
msg "vigilando cada $ESPERAR minuto(s) hasta que el servidor pueda enviar"
INTENTO=0
while true; do
  INTENTO=$((INTENTO + 1))
  msg "intento $INTENTO"
  SALIDA=$(probar)
  ESTADO=$?
  echo "$SALIDA"

  if [ "$ESTADO" -eq 0 ]; then
    msg "LISTO: el servidor ya puede enviar correo (intento $INTENTO)."
    msg "Retira el Plan B: el cron del servidor se encarga desde ahora."
    exit 0
  fi
  if [ "$ESTADO" -ne 1 ]; then
    msg "error de ejecución remota (salida $ESTADO). Se detiene la vigilancia."
    exit 2
  fi

  msg "sigue bloqueado; próximo intento en $ESPERAR minuto(s)"
  sleep $((ESPERAR * 60))
done
