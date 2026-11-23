#!/bin/bash
# =============================================================================
# SOVCA · Secuencia de correos automáticos a los usuarios de cuentas demo
# -----------------------------------------------------------------------------
# Envía, día por día, los correos que acompañan la demostración:
#
#   Día 0   bienvenida con las bondades del sistema
#   Día 7   seguimiento: con datos pregunta por la experiencia y empuja la
#           compra; sin datos ofrece ayuda para cargar registros
#   Día 14  aviso: queda 1 día de prueba (con/sin datos)
#   Día 22  rescate después del vencimiento (con/sin datos)
#   Día 30  último recordatorio (con/sin datos)
#
# No repite etapas: cada correo se envía una vez por cuenta (tabla
# demo_correos_auto) y a quien ya tiene cuenta oficial no se le escribe.
#
# Cron sugerido (todos los días a las 09:00):
#     0 9 * * * /opt/backups/script/sovca/demo_correos_auto.sh >> /opt/logs/demo_correos.log 2>&1
#
# Variables opcionales:
#     SOVCA_PHP            binario de php   (por defecto: /usr/bin/php)
#     SOVCA_APP_DIR        raíz del sistema (por defecto: /var/www/html/sovca)
#     SOVCA_DEMO_MAIL_LOG  archivo de log   (por defecto: /opt/logs/demo_correos.log)
#
# Modo revisión (no envía ni registra nada):
#     /var/www/html/sovca/scripts/cron/demo_correos_auto.sh --dry-run
#
# OJO: igual que el resto de tareas de SOVCA, el script crea /opt/logs por sí
# mismo: si el directorio no existe, la redirección del crontab falla ANTES de
# ejecutar el comando y la tarea no corre.
# =============================================================================
set -uo pipefail

LOG="${SOVCA_DEMO_MAIL_LOG:-/opt/logs/demo_correos.log}"
APP="${SOVCA_APP_DIR:-/var/www/html/sovca}"
PHP="${SOVCA_PHP:-/usr/bin/php}"
ARGS=()

for ARG in "$@"; do
  case "$ARG" in
    --dry-run|--verbose)
      ARGS+=("$ARG")
      ;;
    --limite=*|--etapa=*|--usuario=*)
      ARGS+=("$ARG")
      ;;
    --ayuda|--help|-h)
      echo "Uso: $0 [--dry-run] [--verbose] [--limite=N] [--etapa=CLAVE] [--usuario=USUARIO]"
      echo "  Envía los correos de la secuencia demo. --dry-run no envía nada."
      exit 0
      ;;
    *)
      echo "Opción no reconocida: $ARG (use --ayuda)" >&2
      exit 2
      ;;
  esac
done

mkdir -p "$(dirname "$LOG")" 2>/dev/null || {
  echo "No se pudo crear $(dirname "$LOG")" >&2
  exit 1
}

log() { echo "[$(date '+%F %T')] $*" >> "$LOG"; }

if [ ! -x "$PHP" ]; then
  log "ERROR: no se encontró php ejecutable en '$PHP'"
  exit 1
fi

SCRIPT="$APP/scripts/demo_correos_auto.php"
if [ ! -f "$SCRIPT" ]; then
  log "ERROR: no existe $SCRIPT"
  exit 1
fi

log "===== inicio secuencia demo ====="

"$PHP" "$SCRIPT" "${ARGS[@]}" 2>&1 | while IFS= read -r LINEA; do
  [ -n "$LINEA" ] && log "$LINEA"
done
ESTADO=${PIPESTATUS[0]}

if [ "$ESTADO" -ne 0 ]; then
  log "ERROR: demo_correos_auto.php terminó con salida $ESTADO"
fi

log "===== fin secuencia demo ====="
exit 0
