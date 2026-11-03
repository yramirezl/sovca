#!/bin/bash
# =============================================================================
# SOVCA · Respaldo diario de la base de datos
# -----------------------------------------------------------------------------
# Cron sugerido (todos los días):
#     0 1 * * * /var/www/html/sovca/scripts/cron/backup.sh
#
# Deja el dump comprimido en /opt/backups/databases/<base>_AAAA-MM-DD.dump y
# además una copia en /opt/backups/databases/sovca/respaldo_db_sovca_DD-MM-YYYY.tar.gz
# (formato y ruta que espera alertas/controlador_email_backup.php para enviarlo
# por correo). Borra los archivos con más de $RETENCION_DIAS días.
#
# Variables opcionales:
#     SOVCA_BACKUP_BASE      base a respaldar (por defecto: bovs)
#     SOVCA_BACKUP_DIR       carpeta destino (por defecto: /opt/backups/databases)
#     SOVCA_BACKUP_RETENCION días a conservar (por defecto: 14)
#
# El script crea el directorio de logs por sí mismo: si /opt/logs no existe,
# la redirección del crontab falla y el comando no se ejecuta.
# =============================================================================
set -uo pipefail

LOG=/opt/logs/backup.log
BASE="${SOVCA_BACKUP_BASE:-sovca}"
DEST="${SOVCA_BACKUP_DIR:-/opt/backups/databases}"
RETENCION_DIAS="${SOVCA_BACKUP_RETENCION:-14}"
PG_DUMP=/usr/bin/pg_dump

mkdir -p "$(dirname "$LOG")" "$DEST" || exit 1

log() { echo "[$(date '+%F %T')] $*" >> "$LOG"; }

log "inicio respaldo de la base '$BASE' → $DEST"

FECHA=$(date +%F)
ARCHIVO="$DEST/${BASE}_${FECHA}.dump"

if [ ! -x "$PG_DUMP" ] && ! command -v pg_dump >/dev/null 2>&1; then
  log "ERROR: pg_dump no está disponible"
  exit 1
fi

# OJO: se redirige con `>` para que el archivo lo cree ROOT. Con `-f` lo crea
# el usuario `postgres` y /opt/backups/databases es root:root 755, así que
# pg_dump falla con "could not open output file ... Permission denied"
# (pasó el 29/10/2026: cinco días sin ningún respaldo).
if sudo -n -u postgres pg_dump -Fc -d "$BASE" > "$ARCHIVO" 2>> "$LOG"; then
  if [ ! -s "$ARCHIVO" ]; then
    log "ERROR: el dump salió vacío"
    rm -f "$ARCHIVO"
    exit 1
  fi
  TAMANO=$(du -h "$ARCHIVO" 2>/dev/null | cut -f1)
  log "OK $ARCHIVO (${TAMANO:-?})"

  # ---------------------------------------------------------------------------
  # Copia en el formato/ruta que usa el correo de respaldo
  # (alertas/controlador_email_backup.php):
  #     /opt/backups/databases/sovca/respaldo_db_sovca_DD-MM-YYYY.tar.gz
  # Se guarda el .dump dentro del tar.gz; al extraerlo se restaura con:
  #     pg_restore -d <base> sovca_AAAA-MM-DD.dump
  # ---------------------------------------------------------------------------
  DIR_MAIL="$DEST/sovca"
  mkdir -p "$DIR_MAIL"
  ARCHIVO_MAIL="$DIR_MAIL/respaldo_db_sovca_$(date +%d-%m-%Y).tar.gz"
  if tar -czf "$ARCHIVO_MAIL" -C "$DEST" "$(basename "$ARCHIVO")" 2>> "$LOG"; then
    log "OK $ARCHIVO_MAIL ($(du -h "$ARCHIVO_MAIL" 2>/dev/null | cut -f1)) para envío por correo"
  else
    log "ERROR: no se pudo crear $ARCHIVO_MAIL"
  fi

  # Rotación
  log "rotación: borrando ${BASE}_*.dump con más de ${RETENCION_DIAS} días"
  find "$DEST" -maxdepth 1 -type f -name "${BASE}_*.dump" -mtime "+${RETENCION_DIAS}" -print -delete >> "$LOG" 2>&1
  log "rotación: borrando respaldo_db_*.tar.gz con más de ${RETENCION_DIAS} días"
  find "$DIR_MAIL" -maxdepth 1 -type f -name 'respaldo_db_*.tar.gz' -mtime "+${RETENCION_DIAS}" -print -delete >> "$LOG" 2>&1
  exit 0
fi

log "ERROR: pg_dump falló; se descarta el archivo parcial"
rm -f "$ARCHIVO"
exit 1
