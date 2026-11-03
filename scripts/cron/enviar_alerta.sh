#!/bin/bash
# =============================================================================
# SOVCA · Envía los correos de alertas pendientes
# -----------------------------------------------------------------------------
# Lo llama el cron los lunes, miércoles y viernes a las 08:00, después de
# crear_alerta.sh.
#
# Modo revisión (no envía ni registra nada):
#     /var/www/html/sovca/scripts/cron/enviar_alerta.sh --dry-run
# Mostrar el HTML del primer correo del modo revisión:
#     /var/www/html/sovca/scripts/cron/enviar_alerta.sh --dry-run --html
# =============================================================================
set -uo pipefail

LOG=/opt/logs/correo.log
APP=/var/www/html/sovca/alertas
PHP=/usr/bin/php

mkdir -p "$(dirname "$LOG")" || exit 1

# OJO: los argumentos van DESPUÉS de `--`. Sin ese separador PHP interpreta
# --dry-run como una opción suya, imprime su ayuda y termina con salida 1
# (pasó el 29/10/2026: el modo revisión nunca llegó al controlador).
ARGS=()
for A in "$@"; do
  case "$A" in
    --dry-run|--html) ARGS+=("$A") ;;
  esac
done

{
  echo "===== $(date '+%F %T') enviar_alerta: inicio ${ARGS[*]:-} ====="
} >> "$LOG"

if [ ! -f "$APP/controlador_email.php" ]; then
  echo "ERROR: no existe $APP/controlador_email.php" >> "$LOG"
  exit 1
fi

"$PHP" -f "$APP/controlador_email.php" -- "${ARGS[@]:-}" >> "$LOG" 2>&1
ESTADO=$?

echo "===== $(date '+%F %T') enviar_alerta: fin (salida $ESTADO) =====" >> "$LOG"
exit "$ESTADO"
