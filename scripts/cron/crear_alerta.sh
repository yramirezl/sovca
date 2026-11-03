#!/bin/bash
# =============================================================================
# SOVCA · Genera la tabla `malertas` (alertas del día)
# -----------------------------------------------------------------------------
# Lo llama el cron los lunes, miércoles y viernes a las 06:00.
# Debe correr SIEMPRE antes de enviar_alerta.sh.
#
# El script crea el directorio de logs por sí mismo: si /opt/logs no existe,
# la redirección del crontab (`>> /opt/logs/alerta.log`) falla y el comando
# no se ejecuta. Eso fue exactamente lo que pasó entre el 24 y el 29/10/2026.
# =============================================================================
set -uo pipefail

LOG=/opt/logs/alerta.log
APP=/var/www/html/sovca/alertas
PHP=/usr/bin/php

mkdir -p "$(dirname "$LOG")" || exit 1

{
  echo "===== $(date '+%F %T') crear_alerta: inicio ====="
} >> "$LOG"

if [ ! -f "$APP/controlador_alertas.php" ]; then
  echo "ERROR: no existe $APP/controlador_alertas.php" >> "$LOG"
  exit 1
fi

"$PHP" -f "$APP/controlador_alertas.php" >> "$LOG" 2>&1
ESTADO=$?

echo "===== $(date '+%F %T') crear_alerta: fin (salida $ESTADO) =====" >> "$LOG"
exit "$ESTADO"
