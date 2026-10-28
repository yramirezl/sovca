#!/usr/bin/env bash
# =============================================================================
#  scripts/pgbouncer_preparar_usuario.sh
# -----------------------------------------------------------------------------
#  Deja PgBouncer listo para que la aplicación se conecte con su propio rol
#  (por defecto sovca_app), sin tocar PostgreSQL más que lo necesario.
#
#  QUÉ HACE
#    1. Lee `auth_type`, `auth_file` y la sección [databases] de pgbouncer.ini.
#    2. Obtiene el secreto del rol desde PostgreSQL (pg_authid.rolpassword):
#         · SCRAM-SHA-256$…  → se copia tal cual al userlist
#         · md5…             → se copia tal cual al userlist
#    3. Comprueba si la base está ruteada en [databases] (o si hay `*`).
#    4. Con --aplicar: respalda los archivos, escribe la línea del userlist,
#       agrega la base si falta y recarga PgBouncer.
#    5. Verifica la conexión real por el puerto de PgBouncer.
#
#  USO (siempre primero en simulación)
#      cd /var/www/html/sovca
#      sudo bash scripts/pgbouncer_preparar_usuario.sh
#      sudo bash scripts/pgbouncer_preparar_usuario.sh --aplicar
#
#  OPCIONES
#      --usuario=sovca_app     Rol de la aplicación.
#      --base=sovca            Base que debe rutear PgBouncer (repetible con coma:
#                              --base=sovca,sovca_demo).
#      --puerto=6432           Puerto de PgBouncer (para la verificación final).
#      --ini=/etc/pgbouncer/pgbouncer.ini
#      --userlist=/etc/pgbouncer/userlist.txt   (si no se indica, se toma
#                              auth_file del propio pgbouncer.ini)
#      --aplicar               Escribe los cambios (sin esto solo informa).
#
#  REQUISITOS
#      · Ejecutar como root (o con sudo) para escribir en /etc/pgbouncer.
#      · Acceso de superusuario a PostgreSQL por el socket local (el usuario
#        `postgres` del sistema) para leer pg_authid.
#
#  NOTA SOBRE auth_type
#      PostgreSQL 16 guarda las claves como scram-sha-256. Si pgbouncer.ini
#      tiene `auth_type = md5`, PgBouncer NO puede autenticar con un secreto
#      SCRAM: hay que pasar a `auth_type = scram-sha-256` (PgBouncer ≥ 1.14)
#      o volver a fijar la clave del rol con `password_encryption = 'md5'`.
#      Este script lo detecta y se detiene explicando qué hacer.
# =============================================================================
set -euo pipefail

USUARIO="sovca_app"
BASES="sovca"
PUERTO_POOL="6432"
INI="/etc/pgbouncer/pgbouncer.ini"
USERLIST=""
APLICAR=0
PGADMIN="${PGADMIN:-postgres}"
PGPASSWORD_ADMIN="${PGPASSWORD_ADMIN:-}"

# Acceso de superusuario a PostgreSQL:
#   · con PGPASSWORD_ADMIN (o ~/.pgpass) → TCP a 127.0.0.1
#   · sin ella                            → socket local como usuario `postgres`
if [ -n "$PGPASSWORD_ADMIN" ]; then
    export PGPASSWORD="$PGPASSWORD_ADMIN"
    PSQL_ADMIN=(psql -h 127.0.0.1 -U "$PGADMIN" -v ON_ERROR_STOP=1 -At)
else
    PSQL_ADMIN=(sudo -u postgres psql -v ON_ERROR_STOP=1 -At)
fi

for arg in "$@"; do
    case "$arg" in
        --usuario=*)  USUARIO="${arg#*=}" ;;
        --base=*)     BASES="${arg#*=}" ;;
        --puerto=*)   PUERTO_POOL="${arg#*=}" ;;
        --ini=*)      INI="${arg#*=}" ;;
        --userlist=*) USERLIST="${arg#*=}" ;;
        --aplicar)    APLICAR=1 ;;
        -h|--help)
            sed -n '2,45p' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *) echo "Opción desconocida: $arg" >&2; exit 1 ;;
    esac
done

echo
echo "══════════════════════════════════════════════════════════════════════"
echo " PgBouncer · preparar el rol de la aplicación"
echo "══════════════════════════════════════════════════════════════════════"
echo " Rol    : $USUARIO"
echo " Bases  : $BASES"
echo " Modo   : $([ "$APLICAR" -eq 1 ] && echo 'APLICAR' || echo 'SIMULACIÓN')"
echo

if [ ! -f "$INI" ]; then
    echo "ERROR: no existe $INI (¿está instalado PgBouncer en este servidor?)" >&2
    exit 1
fi

leer_ini() {   # $1 = clave
    grep -E "^[[:space:]]*$1[[:space:]]*=" "$INI" 2>/dev/null \
        | tail -1 | cut -d= -f2- | tr -d '[:space:]'
}

AUTH_TYPE="$(leer_ini auth_type)"
[ -n "$AUTH_TYPE" ] || AUTH_TYPE="md5"
if [ -z "$USERLIST" ]; then
    USERLIST="$(leer_ini auth_file)"
fi
[ -n "$USERLIST" ] || USERLIST="/etc/pgbouncer/userlist.txt"

echo "── Configuración detectada ───────────────────────────────────────────"
echo "   auth_type      : $AUTH_TYPE"
echo "   auth_file      : $USERLIST"
echo "   pool_mode      : $(leer_ini pool_mode || true)"
echo "   max_connections: $(leer_ini max_client_conn || true) clientes"

# ── Secreto del rol ────────────────────────────────────────────────────────
echo
echo "── Secreto del rol en PostgreSQL ─────────────────────────────────────"
ROL_PASS="$("${PSQL_ADMIN[@]}" -c "SELECT rolpassword FROM pg_authid WHERE rolname = '$USUARIO'" 2>/dev/null | head -1 || true)"

if [ -z "$ROL_PASS" ]; then
    echo "ERROR: no se pudo leer pg_authid.rolpassword de '$USUARIO'." >&2
    echo "       · ¿Existe el rol?   SELECT rolname FROM pg_roles WHERE rolname='$USUARIO';" >&2
    echo "       · ¿Tiene clave?     ALTER ROLE $USUARIO WITH LOGIN PASSWORD '…';" >&2
    echo "       · Este script debe correr con acceso de superusuario (sudo -u postgres)." >&2
    exit 1
fi

case "$ROL_PASS" in
    SCRAM-SHA-256\$*) TIPO_SECRETO="scram" ;;
    md5*)             TIPO_SECRETO="md5" ;;
    *)                TIPO_SECRETO="desconocido" ;;
esac
echo "   formato        : $TIPO_SECRETO"

if [ "$TIPO_SECRETO" = "desconocido" ]; then
    echo "ERROR: el rol no tiene contraseña cifrada (o es texto plano)." >&2
    echo "       Fijala con: ALTER ROLE $USUARIO WITH LOGIN PASSWORD '…';" >&2
    exit 1
fi

if [ "$AUTH_TYPE" = "md5" ] && [ "$TIPO_SECRETO" = "scram" ]; then
    cat >&2 <<'AVISO'
ERROR: incompatibilidad de cifrado.

  PostgreSQL guarda la clave de este rol como SCRAM-SHA-256, pero
  pgbouncer.ini tiene `auth_type = md5`. PgBouncer no puede autenticar
  clientes con md5 usando un secreto SCRAM.

  Opción recomendada (PgBouncer ≥ 1.14):
      sudo sed -i 's/^[[:space:]]*auth_type[[:space:]]*=.*/auth_type = scram-sha-256/' /etc/pgbouncer/pgbouncer.ini

  Alternativa (solo si PgBouncer es antiguo): volver a fijar la clave del rol
  con cifrado md5 y luego regenerar el userlist:
      sudo -u postgres psql -c "SET password_encryption='md5'; ALTER ROLE sovca_app WITH PASSWORD '…';"

  En ambos casos, volvé a ejecutar este script.
AVISO
    exit 1
fi

LINEA_USUARIO="\"$USUARIO\" \"$ROL_PASS\""

echo
echo "── Línea para $USERLIST ──────────────────────────────────────────────"
echo "   ${LINEA_USUARIO:0:70}…"

# ¿Ya existe la línea del usuario?
YA_EXISTE=0
if [ -f "$USERLIST" ] && grep -qE "^[[:space:]]*\"$USUARIO\"" "$USERLIST"; then
    YA_EXISTE=1
fi
echo "   estado actual  : $([ "$YA_EXISTE" -eq 1 ] && echo 'ya está definido' || echo 'NO está definido')"

# ── Sección [databases] ────────────────────────────────────────────────────
echo
echo "── Sección [databases] de pgbouncer.ini ──────────────────────────────"
BLOQUE_DB="$(awk '/^\[databases\]/{bandera=1;next} /^\[/{bandera=0} bandera' "$INI")"
COMODIN=0
grep -qE '^[[:space:]]*\*[[:space:]]*=' <<<"$BLOQUE_DB" && COMODIN=1

FALTAN=()
IFS=',' read -ra LISTA_BASES <<< "$BASES"
for b in "${LISTA_BASES[@]}"; do
    b="$(echo "$b" | tr -d '[:space:]')"
    [ -n "$b" ] || continue
    if grep -qE "^[[:space:]]*$b[[:space:]]*=" <<<"$BLOQUE_DB"; then
        echo "   $b : ya ruteada"
    elif [ "$COMODIN" -eq 1 ]; then
        echo "   $b : la cubre el comodín (* = host=… port=…)"
    else
        echo "   $b : FALTA"
        FALTAN+=("$b")
    fi
done

# ── Aplicar ────────────────────────────────────────────────────────────────
if [ "$APLICAR" -ne 1 ]; then
    echo
    echo " [SIMULACIÓN] Cambios que se harían:"
    [ "$YA_EXISTE" -eq 1 ] && echo "   · actualizar la línea de $USUARIO en $USERLIST"
    [ "$YA_EXISTE" -eq 0 ] && echo "   · agregar $USUARIO a $USERLIST"
    for b in "${FALTAN[@]:-}"; do
        [ -n "$b" ] && echo "   · agregar a [databases]:  $b = host=127.0.0.1 port=5432"
    done
    [ "$YA_EXISTE" -eq 0 ] || [ "${#FALTAN[@]}" -gt 0 ] || echo "   (nada que cambiar)"
    echo
    echo " Agregá --aplicar para escribir y recargar PgBouncer."
    echo
    exit 0
fi

SELLO="$(date +%Y%m%d-%H%M%S)"
cp -a "$USERLIST" "$USERLIST.bak-$SELLO"
echo
echo "── Aplicando ─────────────────────────────────────────────────────────"
echo "   respaldo: $USERLIST.bak-$SELLO"

{
    grep -vE "^[[:space:]]*\"$USUARIO\"" "$USERLIST" || true
    echo "$LINEA_USUARIO"
} > "$USERLIST.nuevo"
cat "$USERLIST.nuevo" > "$USERLIST"
rm -f "$USERLIST.nuevo"
echo "   [OK] userlist actualizado"

if [ "${#FALTAN[@]}" -gt 0 ]; then
    cp -a "$INI" "$INI.bak-$SELLO"
    echo "   respaldo: $INI.bak-$SELLO"
    for b in "${FALTAN[@]}"; do
        # Se inserta al final de la sección [databases]
        awk -v base="$b" '
            /^\[databases\]/ { print; dentro=1; next }
            /^\[/ && dentro  { if (!hecho) { print base " = host=127.0.0.1 port=5432"; print ""; hecho=1 } dentro=0 }
            { print }
            END { if (dentro && !hecho) print base " = host=127.0.0.1 port=5432" }
        ' "$INI" > "$INI.nuevo"
        cat "$INI.nuevo" > "$INI"
        rm -f "$INI.nuevo"
        echo "   [OK] $b agregada a [databases]"
    done
fi

systemctl reload pgbouncer 2>/dev/null && echo "   [OK] pgbouncer recargado" \
    || echo "   [AVISO] no se pudo recargar pgbouncer (¿está activo?)"

# ── Verificación ───────────────────────────────────────────────────────────
echo
echo "── Verificación por el pooler (puerto $PUERTO_POOL) ───────────────────"
for b in "${LISTA_BASES[@]}"; do
    b="$(echo "$b" | tr -d '[:space:]')"
    [ -n "$b" ] || continue
    if PGPASSWORD="$(printf '%s' "${SOVCA_DB_PASS:-}")" psql -h 127.0.0.1 -p "$PUERTO_POOL" \
            -U "$USUARIO" -d "$b" -Atc "SELECT 'ok ' || current_database() || ' via ' || current_user" 2>/dev/null; then
        :
    else
        echo "   $b : falló la prueba. Revisá SOVCA_DB_PASS o corré:"
        echo "        PGPASSWORD='…' psql -h 127.0.0.1 -p $PUERTO_POOL -U $USUARIO -d $b -c 'select 1'"
    fi
done

echo
echo " Listo. Recordá que la aplicación debe apuntar al puerto del pooler:"
echo "   config/db.local.php →  define('SOVCA_DB_PORT', '$PUERTO_POOL');"
echo
