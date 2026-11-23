#!/bin/bash
# =============================================================================
# SOVCA · Avisos por inactividad de fundos + resumen a los administradores
# -----------------------------------------------------------------------------
# Envía un correo al PRODUCTOR dueño del fundo cuando NINGÚN usuario del
# fundo (productor y ayudantes) se ha conectado en los últimos 7 días.
# Al terminar cada corrida real manda un resumen a los administradores
# activos (roles 1 y 4) con lo que se envió.
#
# El proceso es alertas/controlador_email_inactividad.php:
#   · agrupa por productor (un correo aunque tenga varios fundos),
#   · no repite el aviso al mismo fundo dentro del mismo período,
#   · deja registro en malertas_inactividad_estado.
#
# Cron sugerido (todos los días a las 06:00):
#     0 6 * * * /opt/backups/script/sovca/enviar_inactividad.sh >> /opt/logs/inactividad.log 2>&1
#
# Variables opcionales:
#     SOVCA_PHP              binario de php   (por defecto: /usr/bin/php)
#     SOVCA_APP_DIR          raíz del sistema (por defecto: /var/www/html/sovca)
#     SOVCA_INACTIVIDAD_LOG  archivo de log   (por defecto: /opt/logs/inactividad.log)
#
# Modo revisión (no envía ni registra nada):
#     /var/www/html/sovca/scripts/cron/enviar_inactividad.sh --dry-run
#
# OJO: igual que el resto de tareas de SOVCA, el script crea /opt/logs por sí
# mismo: si el directorio no existe, la redirección del crontab falla ANTES de
# ejecutar el comando y la tarea no corre.
# =============================================================================
set -uo pipefail

LOG="${SOVCA_INACTIVIDAD_LOG:-/opt/logs/inactividad.log}"
APP="${SOVCA_APP_DIR:-/var/www/html/sovca}"
PHP="${SOVCA_PHP:-/usr/bin/php}"
ARGS=()

for ARG in "$@"; do
  case "$ARG" in
    --dry-run|--html|--forzar|--sin-resumen)
      ARGS+=("$ARG")
      ;;
    --dias=*|--limite=*|--resumen=*)
      ARGS+=("$ARG")
      ;;
    --ayuda|--help|-h)
      echo "Uso: $0 [--dry-run] [--html] [--forzar] [--sin-resumen]"
      echo "        [--dias=N] [--limite=N] [--resumen=correo]"
      echo "  Avisa a los productores con fundos inactivos y resume a los"
      echo "  administradores. --dry-run no envía ni registra nada."
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

DIR="$APP/alertas"
if [ ! -f "$DIR/controlador_email_inactividad.php" ]; then
  log "ERROR: no existe $DIR/controlador_email_inactividad.php"
  exit 1
fi

log "===== inicio avisos de inactividad ====="

cd "$DIR" || {
  log "ERROR: no se pudo entrar a $DIR"
  exit 1
}

"$PHP" controlador_email_inactividad.php "${ARGS[@]}" 2>&1 | while IFS= read -r LINEA; do
  [ -n "$LINEA" ] && log "$LINEA"
done
ESTADO=${PIPESTATUS[0]}

if [ "$ESTADO" -ne 0 ]; then
  log "ERROR: controlador_email_inactividad.php terminó con salida $ESTADO"
fi

log "===== fin avisos de inactividad ====="
# Se propaga la salida del proceso para que un fallo quede visible.
exit "$ESTADO"
