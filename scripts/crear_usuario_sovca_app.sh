#!/usr/bin/env bash
# =============================================================================
# Crea/actualiza el rol sovca_app en el PostgreSQL LOCAL con los permisos
# que usa la aplicación (equivalente al usuario del servidor productivo).
#
# Uso:
#   SOVCA_APP_PASS='ClaveSeguraLocal' bash scripts/crear_usuario_sovca_app.sh
#
# Si el superusuario local no es postgres/postgres:
#   PGPASSWORD_ADMIN='otraClave' PGADMIN='postgres' \
#     SOVCA_APP_PASS='ClaveSeguraLocal' bash scripts/crear_usuario_sovca_app.sh
#
# Aplica sobre las bases sovca y sovca_demo (si existen).
# NO usar este script en producción con una clave débil.
#
# Después de ejecutarlo, en config/db.local.php:
#   define('SOVCA_DB_USER', 'sovca_app');
#   define('SOVCA_DB_PASS', 'la-misma-clave');
# =============================================================================
set -euo pipefail

APP_USER="sovca_app"
APP_PASS="${SOVCA_APP_PASS:-sovca_app}"
PGHOST="${PGHOST:-127.0.0.1}"
PGPORT="${PGPORT:-5432}"
PGADMIN="${PGADMIN:-postgres}"
PGPASSWORD_ADMIN="${PGPASSWORD_ADMIN:-}"

if [ -z "$APP_PASS" ]; then
    echo "Falta la clave. Usá: SOVCA_APP_PASS='...' bash $0" >&2
    exit 1
fi

# Cómo conectarse como superusuario:
#   · con PGPASSWORD_ADMIN (o ~/.pgpass) → TCP a PGHOST:PGPORT
#   · sin ella                            → socket local como usuario postgres
#     (peer auth; funciona con `sudo -u postgres psql` sin contraseña)
if [ -n "$PGPASSWORD_ADMIN" ]; then
    export PGPASSWORD="$PGPASSWORD_ADMIN"
    PSQL=(psql -h "$PGHOST" -p "$PGPORT" -U "$PGADMIN" -v ON_ERROR_STOP=1)
else
    PSQL=(sudo -u postgres psql -v ON_ERROR_STOP=1)
fi

echo "· Verificando rol $APP_USER..."
ROLE_EXISTS="$("${PSQL[@]}" -d postgres -Atc "SELECT 1 FROM pg_roles WHERE rolname = '$APP_USER'")"

if [ "$ROLE_EXISTS" = "1" ]; then
    echo "  ya existe: se actualiza la clave y los atributos"
    "${PSQL[@]}" -d postgres -v app_pass="$APP_PASS" <<'SQL'
ALTER ROLE sovca_app WITH LOGIN PASSWORD :'app_pass'
  NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
SQL
else
    echo "  no existe: se crea"
    "${PSQL[@]}" -d postgres -v app_pass="$APP_PASS" <<'SQL'
CREATE ROLE sovca_app WITH LOGIN PASSWORD :'app_pass'
  NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
SQL
fi

for DB in sovca sovca_demo; do
    EXISTS="$("${PSQL[@]}" -d postgres -Atc "SELECT 1 FROM pg_database WHERE datname = '$DB'")"
    if [ "$EXISTS" != "1" ]; then
        echo "· $DB no existe: se omite"
        continue
    fi

    echo "· Aplicando permisos en $DB"
    "${PSQL[@]}" -d "$DB" -c "GRANT CONNECT, TEMPORARY ON DATABASE \"$DB\" TO \"$APP_USER\";"
    "${PSQL[@]}" -d "$DB" <<SQL
GRANT USAGE ON SCHEMA public TO "$APP_USER";
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO "$APP_USER";
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO "$APP_USER";
GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO "$APP_USER";
ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO "$APP_USER";
ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT USAGE, SELECT ON SEQUENCES TO "$APP_USER";
ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT EXECUTE ON FUNCTIONS TO "$APP_USER";
SQL

    # Los REPORTES usan ADOdb y leen las vistas; si en producción sovca_app
    # necesitara crear objetos, descomentá esta línea para igualarlo:
    # "${PSQL[@]}" -d "$DB" -c "GRANT CREATE ON SCHEMA public TO \"$APP_USER\";"
done

echo
echo "Listo. Rol $APP_USER creado/actualizado."
echo "Configurá config/db.local.php con SOVCA_DB_USER='$APP_USER' y la misma clave."
