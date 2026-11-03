#!/bin/bash
# =============================================================================
# SOVCA · Envía por correo el respaldo de la base de datos del día
# -----------------------------------------------------------------------------
# Cron sugerido (todos los días, después de backup.sh):
#     0 2 * * * /opt/backups/script/sovca/enviar_backup_db.sh
#
# Usa el controlador oficial del sistema (alertas/controlador_email_backup.php),
# el mismo que se usa a mano, para no tener dos implementaciones del mismo
# correo. Ese controlador valida que el archivo exista y devuelve código de
# salida distinto de cero si el envío falla.
#
# Variables opcionales:
#     SOVCA_BACKUP_EMAIL   destinatario (por defecto: el del controlador)
#     SOVCA_BACKUP_FECHA   fecha del respaldo DD-MM-AAAA (por defecto: hoy)
#
# Este script crea su propio directorio de logs: si /opt/logs no existe, la
# redirección del crontab falla ANTES de ejecutar el comando y el envío nunca
# ocurre (pasó entre el 24/10 y el 29/10/2026).
# =============================================================================
set -uo pipefail

LOG=/opt/logs/enviar_backup_db.log
APP=/var/www/html/sovca
PHP=/usr/bin/php
CONTROLADOR="$APP/alertas/controlador_email_backup.php"

mkdir -p "$(dirname "$LOG")" || exit 1
: >> "$LOG"

log() { echo "[$(date '+%F %T')] $*" >> "$LOG"; }

log "inicio"

if [ ! -f "$CONTROLADOR" ]; then
  log "ERROR: no existe $CONTROLADOR"
  exit 1
fi

ARGS=()
if [ -n "${SOVCA_BACKUP_FECHA:-}" ]; then
  ARGS+=("--fecha=${SOVCA_BACKUP_FECHA}")
fi

if [ -z "${SOVCA_BACKUP_EMAIL:-}" ]; then
  "$PHP" -f "$CONTROLADOR" "${ARGS[@]}" >> "$LOG" 2>&1
  ESTADO=$?
  log "fin (salida $ESTADO)"
  exit "$ESTADO"
fi

# Varios destinatarios separados por coma: se envía uno por uno.
FALLO=0
IFS=',' read -ra DESTINOS <<< "${SOVCA_BACKUP_EMAIL}"
for D in "${DESTINOS[@]}"; do
  D_TMP=$(echo "$D" | xargs)
  [ -z "$D_TMP" ] && continue
  "$PHP" -f "$CONTROLADOR" -- "--to=$D_TMP" "${ARGS[@]}" >> "$LOG" 2>&1
  ESTADO=$?
  log "destinatario $D_TMP → salida $ESTADO"
  [ "$ESTADO" -ne 0 ] && FALLO=1
done

log "fin (fallo=$FALLO)"
exit "$FALLO"

