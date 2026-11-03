#!/bin/bash
# =============================================================================
# SOVCA · Purga del registro de sincronizaciones del modo offline
# -----------------------------------------------------------------------------
# Mantiene acotada la tabla `sync_offline_log` (una fila por pull/push de la
# app offline). Sin purga crece todos los días: cada equipo escribe varias
# filas por sincronización.
#
# Cron sugerido (todos los días a las 03:30, fuera del horario de campo):
#     30 3 * * * /opt/backups/script/sovca/purgar_sync_log.sh >> /opt/logs/sync_log.log 2>&1
#
# Variables opcionales:
#     SOVCA_SYNC_LOG_BASE       base de datos      (por defecto: sovca)
#     SOVCA_SYNC_LOG_RETENCION  días a conservar   (por defecto: 180)
#     SOVCA_SYNC_LOG_ARCHIVO    archivo de log     (por defecto: /opt/logs/sync_log.log)
#     SOVCA_PSQL                binario de psql    (por defecto: se busca en PATH)
#
# Modo revisión (solo cuenta lo que borraría, no toca nada):
#     /var/www/html/sovca/scripts/cron/purgar_sync_log.sh --dry-run
#
# OJO: igual que el resto de tareas de SOVCA, el script crea /opt/logs por sí
# mismo. La redirección del crontab se resuelve ANTES de ejecutar el comando:
# si el directorio no existe, la tarea no corre y no queda ni rastro.
# =============================================================================
set -uo pipefail

LOG="${SOVCA_SYNC_LOG_ARCHIVO:-/opt/logs/sync_log.log}"
BASE="${SOVCA_SYNC_LOG_BASE:-sovca}"
RETENCION="${SOVCA_SYNC_LOG_RETENCION:-180}"
TABLA="public.sync_offline_log"
DRY_RUN=0

for ARG in "$@"; do
  case "$ARG" in
    --dry-run) DRY_RUN=1 ;;
    --ayuda|--help|-h)
      echo "Uso: $0 [--dry-run]"
      echo "  Purgar $TABLA con más de \$SOVCA_SYNC_LOG_RETENCION días (def. 180)."
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

# ---------------------------------------------------------------------------
# Validaciones
# ---------------------------------------------------------------------------
if ! [[ "$RETENCION" =~ ^[0-9]+$ ]] || [ "$RETENCION" -lt 1 ]; then
  log "ERROR: SOVCA_SYNC_LOG_RETENCION debe ser un entero mayor que 0 (recibido: '$RETENCION')"
  exit 2
fi

if [ -z "${SOVCA_PSQL:-}" ]; then
  PSQL=$(command -v psql || true)
else
  PSQL="$SOVCA_PSQL"
fi
if [ -z "$PSQL" ] || [ ! -x "$PSQL" ]; then
  log "ERROR: psql no está disponible"
  exit 1
fi

# Se ejecuta como root (crontab de root) contra el usuario postgres, igual que
# backup.sh. Si ya somos postgres o no hay sudo, se intenta directo.
ejecutar_sql() {
  local SQL="$1"
  if [ "$(id -u)" -eq 0 ]; then
    sudo -n -u postgres "$PSQL" -X -q -A -t -v ON_ERROR_STOP=1 -d "$BASE" -c "$SQL"
  else
    "$PSQL" -X -q -A -t -v ON_ERROR_STOP=1 -d "$BASE" -c "$SQL"
  fi
}

log "===== inicio purga de $TABLA (base '$BASE', retención ${RETENCION} d)$([ "$DRY_RUN" -eq 1 ] && echo ' [dry-run]') ====="

# ¿Existe la tabla? Si el despliegue aún no aplicó
# scripts/migracion_sync_offline_log.sql, se avisa y se falla para que el
# cron mande correo en vez de quedarse callado.
EXISTE=$(ejecutar_sql "SELECT to_regclass('$TABLA') IS NOT NULL;")
ESTADO_TABLA=$?
if [ "$ESTADO_TABLA" -ne 0 ]; then
  log "ERROR: no se pudo consultar la base '$BASE' (salida $ESTADO_TABLA)"
  exit 1
fi
if [ "$EXISTE" != "t" ]; then
  log "ERROR: no existe la tabla $TABLA. Aplique scripts/migracion_sync_offline_log.sql"
  exit 1
fi

CORTE="now() - interval '$RETENCION days'"
ANTES=$(ejecutar_sql "SELECT count(*) FROM $TABLA;")
if [ "$?" -ne 0 ]; then
  log "ERROR: no se pudo contar filas de $TABLA"
  exit 1
fi

if [ "$DRY_RUN" -eq 1 ]; then
  BORRAR=$(ejecutar_sql "SELECT count(*) FROM $TABLA WHERE created < $CORTE;")
  if [ "$?" -ne 0 ]; then
    log "ERROR: no se pudo calcular lo que se purgaría"
    exit 1
  fi
  log "dry-run: filas totales $ANTES · se borrarían $BORRAR (anteriores a $RETENCION días)"
  log "===== fin dry-run ====="
  exit 0
fi

# DELETE ... RETURNING + count: deja en el log cuántas filas se borraron de
# verdad, que es lo único que permite detectar que la retención no se aplica.
BORRADAS=$(ejecutar_sql "WITH borrados AS (DELETE FROM $TABLA WHERE created < $CORTE RETURNING 1) SELECT count(*) FROM borrados;")
ESTADO=$?
if [ "$ESTADO" -ne 0 ] || ! [[ "$BORRADAS" =~ ^[0-9]+$ ]]; then
  log "ERROR: falló el borrado (salida $ESTADO, valor '$BORRADAS')"
  exit 1
fi

DESPUES=$(ejecutar_sql "SELECT count(*) FROM $TABLA;")

# VACUUM no puede ir dentro de una transacción: va en su propio -c.
ejecutar_sql "VACUUM (ANALYZE) $TABLA;" >/dev/null 2>&1 || log "AVISO: VACUUM ANALYZE no se pudo ejecutar (autovacuum lo hará igual)"

log "OK: borradas $BORRADAS fila(s) con más de $RETENCION días · antes $ANTES · ahora ${DESPUES:-?}"
log "===== fin purga ====="
exit 0
