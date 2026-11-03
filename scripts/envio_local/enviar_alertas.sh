#!/bin/bash
# =============================================================================
# SOVCA · Envío de alertas desde el equipo local (Plan B)
# -----------------------------------------------------------------------------
# Para qué sirve
#   El servidor de producción no puede enviar correo: mail.ruted.net rechaza su
#   IP pública (550 Message rejected en el saludo, antes de autenticar). Este
#   script hace el envío desde tu equipo, que sí está aceptado por el buzón.
#
# Cómo funciona
#   1. Lee de tu configuración qué host, puerto y base usa PHP.
#   2. Abre un túnel SSH en ESE MISMO puerto local hacia PostgreSQL del
#      servidor, así no hay que tocar config/db.local.php.
#   3. Ejecuta el MISMO controlador del sistema (alertas/controlador_email.php)
#      en local: el correo sale por SMTP desde tu IP.
#   4. El registro de "ya enviado" se escribe en la base de PRODUCCIÓN, así que
#      cuando el servidor recupere el envío no repetirá estas alertas.
#   5. Cierra el túnel al terminar (también si falla o se interrumpe).
#
# Uso
#   ./enviar_alertas.sh --dry-run          # solo lista lo que enviaría
#   ./enviar_alertas.sh --a=correo@x.com   # PRUEBA: manda todo a esa dirección
#   ./enviar_alertas.sh                    # envío real a los productores
#   ./enviar_alertas.sh --sin-tunel        # usa la base configurada, sin SSH
#   SOVCA_DEBUG=1 ./enviar_alertas.sh ...   # traza paso a paso (set -x)
#
# Requisitos
#   · Acceso SSH sin contraseña:   ssh-copy-id root@57.129.187.133
#   · config/db.local.php con usuario y clave de PRODUCCIÓN, y un SOVCA_DB_PORT
#     que esté LIBRE en tu equipo (el túnel escucha ahí).
#         define('SOVCA_DB_PORT', 6432);
#         define('SOVCA_DB_USER', 'sovca_app');
#         define('SOVCA_DB_PASS', 'la-clave-de-produccion');
#   · Clave del buzón en config/mail.php o SOVCA_SMTP_PASS.
#
# Variables: SOVCA_REMOTE (root@57.129.187.133), SOVCA_REMOTE_DB (sovca),
#            SOVCA_REMOTE_PG_PORT (5432 = PostgreSQL directo, 6432 = PgBouncer)
# =============================================================================
set -uo pipefail

if [ "${SOVCA_DEBUG:-0}" = "1" ]; then
  set -x
fi

APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP=$(command -v php || echo /usr/bin/php)

REMOTO="${SOVCA_REMOTE:-root@57.129.187.133}"
DB_REMOTA="${SOVCA_REMOTE_DB:-sovca}"
PG_REMOTO="${SOVCA_REMOTE_PG_PORT:-5432}"

DRY_RUN=0
CON_TUNEL=1
FORZAR=""
for A in "$@"; do
  case "$A" in
    --dry-run)   DRY_RUN=1 ;;
    --sin-tunel) CON_TUNEL=0 ;;
    --a=*)       FORZAR="${A#--a=}" ;;
    -h|--help)   sed -n '2,40p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Opción no reconocida: $A" >&2; exit 2 ;;
  esac
done

msg()  { echo "[$(date '+%F %T')] $*"; }
fail() { echo "[$(date '+%F %T')] ERROR: $*" >&2; exit 1; }

PID_TUNEL=""
cerrar_tunel() {
  if [ -n "$PID_TUNEL" ] && kill -0 "$PID_TUNEL" 2>/dev/null; then
    kill "$PID_TUNEL" 2>/dev/null
    msg "túnel cerrado (pid $PID_TUNEL)"
  fi
}
trap cerrar_tunel EXIT INT TERM

# -----------------------------------------------------------------------------
# 0. Archivos imprescindibles
# -----------------------------------------------------------------------------
[ -f "$APP/alertas/controlador_email.php" ] || fail "no encuentro $APP/alertas/controlador_email.php"
if [ ! -f "$APP/config/db.php" ]; then
  fail "falta $APP/config/db.php (es un archivo versionado).
       Restáuralo con:  git -C $APP restore config/db.php"
fi

# -----------------------------------------------------------------------------
# 1. Qué base va a usar PHP (host, puerto, base, usuario)
# -----------------------------------------------------------------------------
INFO_DB=$("$PHP" -r '
require "'"$APP"'/config/db.php";
echo SOVCA_DB_HOST, "|", SOVCA_DB_PORT, "|", SOVCA_DB_NAME, "|", SOVCA_DB_USER, PHP_EOL;' 2>&1)
if [ $? -ne 0 ]; then
  fail "no se pudo leer config/db.php: $INFO_DB"
fi

DB_HOST_EF=$(echo "$INFO_DB" | cut -d'|' -f1)
DB_PORT_EF=$(echo "$INFO_DB" | cut -d'|' -f2)
DB_NAME_EF=$(echo "$INFO_DB" | cut -d'|' -f3)
DB_USER_EF=$(echo "$INFO_DB" | cut -d'|' -f4)

msg "config: $DB_USER_EF@$DB_HOST_EF:$DB_PORT_EF/$DB_NAME_EF"

# -----------------------------------------------------------------------------
# 2. Túnel SSH en el MISMO puerto que usa PHP
# -----------------------------------------------------------------------------
if [ "$CON_TUNEL" -eq 1 ]; then
  case "$DB_HOST_EF" in
    127.0.0.1|localhost|::1) ;;
    *) fail "el host de config/db.php es '$DB_HOST_EF' y el túnel solo puede
       usarse con 127.0.0.1 o localhost. Ejecuta con --sin-tunel si esa base
       es la correcta." ;;
  esac

  if [ "$DB_NAME_EF" != "$DB_REMOTA" ]; then
    fail "la base configurada es '$DB_NAME_EF' y el túnel apunta a '$DB_REMOTA'.
       Ajusta SOVCA_DB_NAME en config/db.local.php o usa
       SOVCA_REMOTE_DB=$DB_NAME_EF"
  fi

  command -v ssh >/dev/null 2>&1 || fail "no hay cliente ssh instalado"

  ssh -o BatchMode=yes -o ConnectTimeout=8 "$REMOTO" true 2>/dev/null \
    || fail "no hay acceso SSH sin contraseña a $REMOTO.
       Instálala con:  ssh-copy-id $REMOTO
       O abre el túnel a mano y ejecuta con --sin-tunel."

  # Si el puerto ya está ocupado, hay que distinguir un túnel nuestro olvidado
  # de un PostgreSQL local: reutilizar el equivocado mandaría los envíos a la
  # base de datos que no es.
  if (exec 3<>"/dev/tcp/127.0.0.1/$DB_PORT_EF") 2>/dev/null; then
    exec 3>&- 2>/dev/null
    DUENO=$(ss -ltnp 2>/dev/null | awk -v p=":$DB_PORT_EF\$" '$4 ~ p' | head -1)
    if echo "$DUENO" | grep -q '"ssh"'; then
      msg "ya hay un túnel ssh escuchando en $DB_PORT_EF: se reutiliza"
    else
      fail "el puerto local $DB_PORT_EF ya está ocupado por otro proceso:
       $DUENO
       Puede ser tu PostgreSQL/PgBouncer local. Cambia SOVCA_DB_PORT en
       config/db.local.php a un puerto libre (por ejemplo 15432) y reintenta."
    fi
  else
    msg "abriendo túnel $REMOTO:$PG_REMOTO → 127.0.0.1:$DB_PORT_EF"
    ssh -f -N -o ExitOnForwardFailure=yes -o BatchMode=yes \
        -L "127.0.0.1:$DB_PORT_EF:127.0.0.1:$PG_REMOTO" "$REMOTO" \
        || fail "no se pudo abrir el túnel SSH (¿puerto $DB_PORT_EF ocupado en el servidor?)"

    PID_TUNEL=$(ss -ltnp 2>/dev/null | awk -v p=":$DB_PORT_EF\$" '$4 ~ p {print $NF}' \
                | grep -o 'pid=[0-9]*' | head -1 | cut -d= -f2)
    [ -n "$PID_TUNEL" ] || PID_TUNEL=$(pgrep -f "L 127.0.0.1:$DB_PORT_EF:" | head -1)

    LISTO=0
    for _ in $(seq 1 20); do
      if (exec 3<>"/dev/tcp/127.0.0.1/$DB_PORT_EF") 2>/dev/null; then
        exec 3>&- 2>/dev/null
        LISTO=1
        break
      fi
      sleep 0.5
    done
    [ "$LISTO" -eq 1 ] || fail "el túnel no quedó escuchando en el puerto $DB_PORT_EF"
    msg "túnel listo"
  fi
else
  msg "modo --sin-tunel: se usa la base tal como está configurada"
fi

# -----------------------------------------------------------------------------
# 3. Comprobar la conexión antes de enviar nada
# -----------------------------------------------------------------------------
msg "comprobando la conexión..."
SALIDA_DB=$(timeout 30 "$PHP" -r '
require "'"$APP"'/config/db.php";
try {
    $pdo = new PDO("pgsql:host=".SOVCA_DB_HOST.";port=".SOVCA_DB_PORT.";dbname=".SOVCA_DB_NAME.";connect_timeout=8",
                   SOVCA_DB_USER, SOVCA_DB_PASS);
    $n = $pdo->query("select count(*) from malertas where status = 1")->fetchColumn();
    echo "  conexion: OK · alertas activas: ", $n, PHP_EOL;
} catch (Exception $e) {
    echo "  conexion: FALLO -> ", $e->getMessage(), PHP_EOL;
    exit(1);
}' 2>&1)
ESTADO_DB=$?
echo "$SALIDA_DB"

if [ "$ESTADO_DB" -eq 124 ]; then
  fail "la comprobación tardó más de 30 s y se canceló.
       Suele ser que el túnel no llega a PostgreSQL (puerto $PG_REMOTO
       cerrado en el servidor) o que un firewall corta la conexión."
fi
if [ "$ESTADO_DB" -ne 0 ]; then
  fail "no se puede conectar a la base (detalle arriba).
       Revisa SOVCA_DB_USER / SOVCA_DB_PASS en config/db.local.php."
fi

# -----------------------------------------------------------------------------
# 4. Envío con el controlador oficial
# -----------------------------------------------------------------------------
ARGS=()
[ "$DRY_RUN" -eq 1 ] && ARGS+=("--dry-run")

if [ -n "$FORZAR" ]; then
  export SOVCA_FORZAR_DESTINO="$FORZAR"
  msg "MODO PRUEBA: todo se envía a $FORZAR y no se registra el estado"
fi

msg "ejecutando el controlador de alertas ${ARGS[*]:-}"
"$PHP" -f "$APP/alertas/controlador_email.php" -- "${ARGS[@]:-}"
ESTADO=$?

if [ "$ESTADO" -eq 0 ]; then
  msg "terminado sin errores"
else
  msg "terminado con errores (salida $ESTADO). Revisa el detalle arriba."
fi
exit "$ESTADO"
