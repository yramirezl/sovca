#!/usr/bin/env bash
# =============================================================================
# SOVCA | Despliegue a producción
# -----------------------------------------------------------------------------
# Producción es un ESPEJO de origin/main. Este script:
#   1. comprueba el entorno y la autenticación con GitHub;
#   2. respalda los commits locales que hubiera (rama respaldo-auto-*);
#   3. aparta los archivos sueltos que colisionen con el árbol del remoto;
#   4. git fetch + git reset --hard origin/main;
#   5. ajusta permisos de los archivos cambiados;
#   6. valida sintaxis PHP y recarga PHP-FPM;
#   7. verifica la conexión a la base de datos (usuario y NOMBRE de la base);
#   8. pone al día la base de la demostración (sovca_demo) si está instalada;
#   9. informa la versión del frontend offline y la caché del shell.
#
# Uso:
#   ./scripts/desplegar.sh                  # despliega
#   ./scripts/desplegar.sh --dry-run        # muestra lo que haría, sin cambios
#   ./scripts/desplegar.sh --rollback <commit|rama>
#   ./scripts/desplegar.sh --forzar         # continúa aunque falte config/db.local.php
#   ./scripts/desplegar.sh --solo-permisos  # repara permisos sin tocar git (no
#                                           # requiere que haya algo que desplegar)
#
# Reglas del proceso (importantes):
#   · NO se edita código en el servidor. Si hace falta un hotfix, se hace en el
#     repo local, se sube y se vuelve a desplegar.
#   · La configuración del servidor va SOLO en archivos ignorados:
#       config/db.local.php    → credenciales de PostgreSQL
#   · La clave del SMTP va en la variable de entorno SOVCA_SMTP_PASS del pool de
#     PHP-FPM, no en config/mail.php (que está versionado).
# =============================================================================
set -uo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ" || exit 1

RAMA="main"
REMOTO="origin"
FECHA="$(date +%Y%m%d-%H%M%S)"
RESPALDOS="/root/sovca-respaldo-$FECHA"

DRY=0
ROLLBACK=""
FORZAR=0
SOLO_PERMISOS=0
case "${1:-}" in
    --dry-run) DRY=1 ;;
    --rollback) ROLLBACK="${2:-}" ;;
    --forzar|--force) FORZAR=1 ;;
    --solo-permisos|--reparar) SOLO_PERMISOS=1 ;;
    -h|--help) sed -n '2,31p' "${BASH_SOURCE[0]}"; exit 0 ;;
    "") ;;
    *) echo "Opción no reconocida: $1 (usar --help)" >&2; exit 2 ;;
esac

if [ -n "${REQUEST_METHOD:-}" ]; then
    echo "Este script solo se ejecuta por consola." >&2
    exit 1
fi

log()  { printf '\n\033[1m%s\033[0m\n' "$*"; }
ok()   { printf '  \033[32m[ok]\033[0m %s\n' "$*"; }
warn() { printf '  \033[33m[aviso]\033[0m %s\n' "$*"; }
err()  { printf '  \033[31m[error]\033[0m %s\n' "$*" >&2; }
die()  { err "$*"; exit 1; }
run()  { if [ "$DRY" = 1 ]; then printf '  [dry-run] %s\n' "$*"; else eval "$@"; fi; }

# ---------------------------------------------------------------- utilidades
php_fpm_servicio() {
    local u
    u="$(systemctl list-units --type=service --no-legend --plain 2>/dev/null \
         | awk '{print $1}' | grep -E '^php[0-9.]+-fpm\.service$' | sort -V | tail -1)"
    printf '%s' "$u"
}

php_lint() {
    local fallo=0 f
    for f in "$@"; do
        [ -f "$f" ] || continue
        case "$f" in *.php)
            if ! php -l "$f" >/dev/null 2>&1; then
                err "sintaxis: $f"
                php -l "$f" 2>&1 | tail -2
                fallo=1
            fi
            ;;
        esac
    done
    return $fallo
}

recargar_fpm() {
    SERV="$(php_fpm_servicio)"
    if [ -n "$SERV" ]; then
        systemctl reload "$SERV" && ok "$SERV recargado" || warn "no se pudo recargar $SERV"
    else
        warn "no se encontró el servicio php*-fpm; recargalo a mano"
    fi
}

# -------------------------------------------------- usuario del servidor web
# El pool de PHP-FPM no tiene por qué correr como www-data; se toma el `user =`
# del pool y, si no se puede averiguar, se usa www-data. SOVCA_WEB_USER lo fuerza.
usuario_web() {
    local u="${SOVCA_WEB_USER:-}"
    if [ -z "$u" ]; then
        u="$(grep -rhoE '^[[:space:]]*user[[:space:]]*=[[:space:]]*[A-Za-z0-9_-]+' \
             /etc/php/*/fpm/pool.d/*.conf 2>/dev/null \
             | sed -E 's/.*=[[:space:]]*//' | sort -u | head -1)"
    fi
    printf '%s' "${u:-www-data}"
}

USUARIO_WEB="$(usuario_web)"
COMO_WEB=""

_preparar_como_web() {
    COMO_WEB=""
    local gid
    if command -v runuser >/dev/null 2>&1 \
       && runuser -u "$USUARIO_WEB" -- true 2>/dev/null; then
        COMO_WEB="runuser -u $USUARIO_WEB --"
    elif sudo -n -u "$USUARIO_WEB" true 2>/dev/null; then
        COMO_WEB="sudo -n -u $USUARIO_WEB"
    elif command -v setpriv >/dev/null 2>&1; then
        gid="$(id -g "$USUARIO_WEB" 2>/dev/null)"
        if [ -n "$gid" ] \
           && setpriv --reuid="$USUARIO_WEB" --regid="$gid" --clear-groups true 2>/dev/null; then
            COMO_WEB="setpriv --reuid=$USUARIO_WEB --regid=$gid --clear-groups"
        fi
    fi
}

# Rutas del proyecto que el usuario web no puede leer/atravesar. Se ejecuta como
# ese usuario; los errores NO se descartan (un directorio sin permiso de paso
# hacía que el chequeo anterior no detectara nada y dijera "todo legible").
_rutas_ilegibles() {
    local lista="$1" errs="$2"
    # shellcheck disable=SC2086
    $COMO_WEB bash -c "
        cd '$RAIZ' 2>/dev/null || { echo '__RAIZ__'; exit 0; }
        while IFS= read -r f; do
            [ -n \"\$f\" ] || continue
            if [ -d \"\$f\" ]; then
                [ -x \"\$f\" ] || echo \"\$f\"
            else
                [ -e \"\$f\" ] || { echo \"\$f\"; continue; }
                [ -r \"\$f\" ] || echo \"\$f\"
            fi
        done < '$lista'
    " 2>"$errs"
}

CORREGIDOS_PERMISOS=0

# Carpetas donde la aplicación ESCRIBE: sus archivos deben quedar del usuario
# web (660/770), no de root; si no, las fotos nuevas y las subidas fallan.
CARPETAS_ESCRIBIBLES="file dist/img/fotos_animales"

_es_carpeta_escribible() {
    local f="$1" d
    for d in $CARPETAS_ESCRIBIBLES; do
        case "$f" in "$d"/*) return 0 ;; esac
    done
    return 1
}

# Comprueba (y corrige) que el servidor web pueda leer el código. Devuelve 1 si
# no se pudo verificar, para que el despliegue no termine diciendo "todo bien".
verificar_permisos() {
    local lista errs ilegibles f intento archivos_criticos
    CORREGIDOS_PERMISOS=0
    _preparar_como_web
    if [ -z "$COMO_WEB" ]; then
        err "no se pudo ejecutar como '$USUARIO_WEB' (corré el script con sudo)"
        return 1
    fi
    ok "el servidor web corre como: $USUARIO_WEB"

    lista="$(mktemp /tmp/sovca-arbol.XXXXXX)"
    errs="$(mktemp /tmp/sovca-perm.XXXXXX)"
    chmod 644 "$lista" 2>/dev/null || true
    {
        # quotePath=false: sin esto git entrecomilla y escapa las rutas con
        # acentos y cualquier comprobación (o chown) falla sobre un nombre que
        # no existe.
        git -c core.quotePath=false ls-files
        find . -path ./.git -prune -o -type d -print 2>/dev/null
        # Las fotos nuevas y las subidas no están versionadas: también se miran.
        # shellcheck disable=SC2086
        find $CARPETAS_ESCRIBIBLES -type f 2>/dev/null
    } | sed 's|^\./||' > "$lista" 2>/dev/null
    if [ ! -s "$lista" ]; then
        err "no se pudo listar el código de $RAIZ"
        rm -f "$lista" "$errs"
        return 1
    fi

    for intento in 1 2; do
        ilegibles="$(_rutas_ilegibles "$lista" "$errs")"
        [ -n "$ilegibles" ] || break
        warn "rutas que el servidor web NO puede leer (intento $intento):"
        printf '%s\n' "$ilegibles" | head -20 | sed 's/^/      /'
        if printf '%s\n' "$ilegibles" | grep -qx '__RAIZ__'; then
            chown root:"$USUARIO_WEB" "$RAIZ" 2>/dev/null || true
            chmod 750 "$RAIZ" 2>/dev/null || true
            CORREGIDOS_PERMISOS=$((CORREGIDOS_PERMISOS + 1))
            continue
        fi
        while IFS= read -r f; do
            [ -n "$f" ] || continue
            [ "$f" = "__RAIZ__" ] && continue
            if [ -d "$f" ]; then
                if _es_carpeta_escribible "$f"; then
                    chown "$USUARIO_WEB":"$USUARIO_WEB" "$f" 2>/dev/null || true
                    chmod 770 "$f" 2>/dev/null || true
                else
                    chown root:"$USUARIO_WEB" "$f" 2>/dev/null || true
                    chmod 750 "$f" 2>/dev/null || true
                fi
            elif [ -e "$f" ]; then
                if _es_carpeta_escribible "$f"; then
                    chown "$USUARIO_WEB":"$USUARIO_WEB" "$f" 2>/dev/null || true
                    chmod 660 "$f" 2>/dev/null || true
                else
                    chown root:"$USUARIO_WEB" "$f" 2>/dev/null || true
                    case "$f" in
                        *.sh) chmod 750 "$f" 2>/dev/null || true ;;
                        *)    chmod 640 "$f" 2>/dev/null || true ;;
                    esac
                fi
            fi
            CORREGIDOS_PERMISOS=$((CORREGIDOS_PERMISOS + 1))
        done <<< "$ilegibles"
    done

    # Prueba funcional: lo que carga el login. Es el caso que rompió el sitio
    # el 2026-11-01 (config/demo.php ilegible → 500 en /views/login).
    archivos_criticos="$(printf '%s\n' \
        config/demo.php config/db.php config/db.local.php \
        lib/demo_helper.php lib/captcha_helper.php lib/mantenimiento_helper.php \
        views/layouts/constantes.php | tr '\n' ' ')"
    # shellcheck disable=SC2086
    ilegibles="$($COMO_WEB php -r '
        foreach (explode(" ", $argv[1]) as $f) {
            $f = trim($f);
            if ($f === "") { continue; }
            if (file_exists($f) && !is_readable($f)) { echo $f, PHP_EOL; }
        }' "$archivos_criticos" 2>"$errs")"
    if [ $? -ne 0 ]; then
        warn "no se pudo ejecutar la prueba del login con PHP como $USUARIO_WEB"
        sed 's/^/      /' "$errs" 2>/dev/null | head -3
    fi
    if [ -n "$ilegibles" ]; then
        while IFS= read -r f; do
            [ -n "$f" ] || continue
            chown root:"$USUARIO_WEB" "$f" 2>/dev/null || true
            chmod 640 "$f" 2>/dev/null || true
            CORREGIDOS_PERMISOS=$((CORREGIDOS_PERMISOS + 1))
        done <<< "$ilegibles"
        warn "archivos del login corregidos: $(printf '%s' "$ilegibles" | tr '\n' ' ')"
    fi

    rm -f "$lista" "$errs"

    if [ "$CORREGIDOS_PERMISOS" -gt 0 ]; then
        warn "$CORREGIDOS_PERMISOS ruta(s) ajustadas (código root:$USUARIO_WEB 640/750; $CARPETAS_ESCRIBIBLES $USUARIO_WEB 660/770)"
        return 0
    fi
    ok "todo el código es legible por $USUARIO_WEB"
    return 0
}

# --------------------------------------- permisos sueltos (sin tocar git)
# Un archivo puede quedar ilegible por una edición manual FUERA de un
# despliegue; en ese caso el despliegue normal hace "ya está al día" y sale sin
# revisar nada. Esta opción repara ese caso (y el propio script la usa para
# reaplicarse cuando cambia en el mismo despliegue).
if [ "$SOLO_PERMISOS" = 1 ]; then
    log "Reparando permisos del código (sin tocar git)"
    verificar_permisos || die "no se pudo verificar la legibilidad del código"
    if [ "$CORREGIDOS_PERMISOS" -gt 0 ]; then
        recargar_fpm
    else
        ok "no hizo falta recargar PHP-FPM"
    fi
    exit 0
fi

# ------------------------------------------------------------ 0) comprobaciones
log "Comprobando el entorno"
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "no es un repositorio git"
ok "repositorio: $RAIZ"

ACTUAL="$(git rev-parse --abbrev-ref HEAD)"
[ "$ACTUAL" = "$RAMA" ] || die "estás en la rama '$ACTUAL'; el despliegue usa '$RAMA'"
ok "rama: $ACTUAL"

git remote get-url "$REMOTO" >/dev/null 2>&1 || die "no existe el remoto '$REMOTO'"
ok "remoto: $(git remote get-url "$REMOTO")"

# Producción usa 640/750 (root:www-data) y el repo guarda 755/644. Sin esto git
# marca cada archivo como modificado por el cambio de permiso y el despliegue
# siguiente parece "sucio" aunque el contenido sea idéntico.
if [ "$(git config --bool core.fileMode 2>/dev/null || echo true)" != "false" ]; then
    run "git config core.fileMode false"
fi
ok "core.fileMode = $(git config --bool core.fileMode 2>/dev/null || echo true)"

[ -f config/db.local.php ] || warn "falta config/db.local.php (se usarán los valores por defecto de config/db.php)"

# config/db.php está versionado, así que un reset lo devuelve a la versión del
# repo. Si en el servidor se había editado a mano (nombre de base, host…) ese
# cambio se pierde: todo lo del servidor debe vivir en config/db.local.php.
if [ -n "$(git status --porcelain -- config/db.php 2>/dev/null)" ]; then
    if grep -q "SOVCA_DB_NAME" config/db.local.php 2>/dev/null; then
        warn "config/db.php está modificado en el servidor; se descarta (db.local.php define SOVCA_DB_NAME)"
    else
        warn "config/db.php está modificado en el servidor y db.local.php NO define SOVCA_DB_NAME"
        warn "tras el despliegue se usará el nombre de base del repositorio; si no es el de producción,"
        warn "agregá  define('SOVCA_DB_NAME', '<base>');  en config/db.local.php y volvé a correr el script"
    fi
fi

# ------------------------------------------------------------------ rollback
if [ -n "$ROLLBACK" ]; then
    log "Volviendo a $ROLLBACK"
    git rev-parse --verify "$ROLLBACK^{commit}" >/dev/null 2>&1 || die "no existe '$ROLLBACK'"
    run "git reset --hard '$ROLLBACK'"
    SERV="$(php_fpm_servicio)"
    [ -n "$SERV" ] && run "systemctl reload '$SERV'" && ok "recargado $SERV"
    git log --oneline -1
    exit 0
fi

# La base de producción NUNCA puede depender del valor versionado de
# config/db.php: cada despliegue reemplaza ese archivo. Si el repositorio trae
# otro valor por defecto, el sistema queda apuntando a una base inexistente.
# (Pasó el 2026-11-01: se subió 'sovca_server' y el servidor quedó sin conexión.)
BASE_EXPLICITA="$(php -r 'if (is_file("config/db.local.php")) { require "config/db.local.php"; }
    echo defined("SOVCA_DB_NAME") ? SOVCA_DB_NAME : "";' 2>/dev/null)"
if [ -z "$BASE_EXPLICITA" ]; then
    err "config/db.local.php no define SOVCA_DB_NAME (o el archivo no existe)"
    err "config/db.php está versionado y el despliegue lo reemplaza: si su valor por"
    err "defecto cambia en el repositorio, el servidor queda apuntando a otra base."
    err "Agregá la base real en config/db.local.php:"
    err "    define('SOVCA_DB_NAME', 'sovca');"
    if [ "$FORZAR" = 1 ] || [ "$DRY" = 1 ]; then
        warn "se continúa: la base dependerá de config/db.php del repositorio"
    else
        die "despliegue detenido para no romper la conexión (usá --forzar para ignorarlo)"
    fi
else
    ok "base de datos declarada en db.local.php: $BASE_EXPLICITA"
fi


# ------------------------------------------------------------- 1) conexión git
log "Contactando con $REMOTO"
if ! git fetch --prune "$REMOTO" 2>/tmp/sovca-fetch.log; then
    err "falló git fetch:"
    sed 's/^/      /' /tmp/sovca-fetch.log
    die "revisá la autenticación (token en HTTPS o clave SSH) y reintentá"
fi
ok "fetch correcto"
OBJETIVO="$REMOTO/$RAMA"
git rev-parse --verify "$OBJETIVO" >/dev/null 2>&1 || die "no existe la rama $OBJETIVO"

ANTES="$(git rev-parse HEAD)"
ANTES_CORTO="$(git rev-parse --short HEAD)"
ok "actual:  $ANTES_CORTO"
ok "destino: $(git rev-parse --short "$OBJETIVO")"

if [ "$ANTES" = "$(git rev-parse "$OBJETIVO")" ]; then
    ok "ya está al día; no hay nada que desplegar"
    if [ "$DRY" = 1 ]; then
        warn "[dry-run] se omitiría la comprobación de permisos del código"
        exit 0
    fi
    # Aun sin commits nuevos hay que revisar los permisos: el login se cayó el
    # 2026-11-01 por un archivo que quedó ilegible fuera de un despliegue, y
    # antes el script salía acá sin comprobar nada.
    log "Comprobando los permisos del código de todos modos"
    verificar_permisos || die "no se pudo verificar la legibilidad del código"
    if [ "$CORREGIDOS_PERMISOS" -gt 0 ]; then
        recargar_fpm
    fi
    exit 0
fi

# ------------------------------------------- 2) respaldo de commits locales
LOCALES="$(git rev-list --count "$OBJETIVO..HEAD" 2>/dev/null || echo 0)"
if [ "$LOCALES" -gt 0 ]; then
    RAMA_RESPALDO="respaldo-auto-$FECHA"
    warn "hay $LOCALES commit(s) locales que se van a descartar"
    warn "quedan guardados en la rama $RAMA_RESPALDO"
    run "git branch '$RAMA_RESPALDO' HEAD"
fi

# ------------------------------------------- 3) cambios y archivos sueltos
CAMBIOS="$(git status --porcelain --untracked-files=no | awk '{print $2}')"
if [ -n "$CAMBIOS" ]; then
    mkdir -p "$RESPALDOS"
    git diff > "$RESPALDOS/cambios-versionados.diff" 2>/dev/null || true
    warn "archivos versionados modificados en el servidor:"
    printf '%s\n' "$CAMBIOS" | sed 's/^/      /'
    warn "diff guardado en $RESPALDOS/cambios-versionados.diff"
    warn "un reset los descarta; si son configuración, van en un archivo .local"
fi

git ls-tree -r --name-only "$OBJETIVO" > /tmp/sovca-arbol.txt
SUELTOS="$(git status --porcelain --untracked-files=all | awk '$1=="??"{sub(/^\?\? +/,""); print}')"
COLISION=0
if [ -n "$SUELTOS" ]; then
    while IFS= read -r archivo; do
        [ -n "$archivo" ] || continue
        if grep -qxF "$archivo" /tmp/sovca-arbol.txt; then
            COLISION=1
            warn "archivo suelto que choca con el remoto: $archivo"
            run "mkdir -p '$RESPALDOS/$(dirname "$archivo")'"
            run "mv '$archivo' '$RESPALDOS/$archivo'"
        fi
    done <<< "$SUELTOS"
    [ "$COLISION" = 1 ] && warn "los archivos movidos quedaron en $RESPALDOS"
fi

# ------------------------------------------------------ 4) alinear con el remoto
log "Alineando con $OBJETIVO"
if [ "$DRY" = 1 ]; then
    printf '  [dry-run] git reset --hard %s\n' "$OBJETIVO"
else
    LOG_RESET="$(mktemp)"
    if ! git reset --hard "$OBJETIVO" 2>"$LOG_RESET"; then
        if grep -qi "not uptodate\|Could not reset index" "$LOG_RESET"; then
            # El índice del servidor quedó inconsistente (entradas que no
            # coinciden con el árbol de trabajo). Se respalda y se reconstruye:
            # el reset vuelve a escribir todo el árbol desde el remoto.
            warn "índice de git inconsistente: $(head -1 "$LOG_RESET")"
            mkdir -p "$RESPALDOS"
            if [ -f .git/index ]; then
                cp -a .git/index "$RESPALDOS/git-index.bak"
            fi
            rm -f .git/index
            ok "índice reiniciado (respaldo en $RESPALDOS/git-index.bak)"
            if ! git reset --hard "$OBJETIVO" 2>"$LOG_RESET"; then
                sed 's/^/      /' "$LOG_RESET"
                die "el reset sigue fallando después de reiniciar el índice"
            fi
        else
            sed 's/^/      /' "$LOG_RESET"
            die "git reset --hard falló"
        fi
    fi
    rm -f "$LOG_RESET"
    ok "HEAD: $(git rev-parse --short HEAD)"
fi

if [ "$DRY" = 1 ]; then
    log "Fin del dry-run (no se cambió nada)"
    exit 0
fi

# ------------------------------------------------------------- 5) permisos
log "Ajustando permisos de los archivos cambiados"
CAMBIADOS="$(git diff --name-only "$ANTES" HEAD)"
if [ -n "$CAMBIADOS" ]; then
    while IFS= read -r f; do
        if [ -f "$f" ]; then
            chown root:www-data "$f" 2>/dev/null || true
            # Los scripts de consola tienen que seguir siendo ejecutables.
            case "$f" in
                *.sh) chmod 750 "$f" 2>/dev/null || true ;;
                *)    chmod 640 "$f" 2>/dev/null || true ;;
            esac
        fi
    done <<< "$CAMBIADOS"
    ok "$(printf '%s\n' "$CAMBIADOS" | wc -l) archivo(s) con root:www-data 640"
else
    ok "sin archivos que ajustar"
fi

# ------------------------------------- 5b) legibilidad para el servidor web
# El paso anterior solo toca lo que cambió en ESTE despliegue, y git reescribe
# los archivos que toca (el resto puede seguir ilegible de antes). Además hay
# que comprobar los DIRECTORIOS: sin permiso de paso, ni el archivo más
# legible se puede abrir. Verificado el 2026-11-01 con config/demo.php → 500.
log "Comprobando que el servidor web pueda leer el código"
if ! verificar_permisos; then
    err "no se pudo verificar la legibilidad del código con $USUARIO_WEB"
    err "revisalo a mano antes de dar el despliegue por terminado"
    FALLO_PERMISOS=1
else
    FALLO_PERMISOS=0
fi

# El script se actualiza CON el despliegue: la corrida que trae esta corrección
# usaría todavía la versión anterior. Si cambió, se reaplica con la nueva.
if printf '%s\n' "$CAMBIADOS" | grep -qx 'scripts/desplegar.sh'; then
    warn "scripts/desplegar.sh cambió en este despliegue; reaplicando la comprobación con la versión nueva"
    if bash scripts/desplegar.sh --solo-permisos; then
        FALLO_PERMISOS=0
    else
        FALLO_PERMISOS=1
    fi
fi

# --------------------------------------------------------- 6) sintaxis PHP
log "Validando sintaxis"
FALLO_LINT=0
if [ -n "$CAMBIADOS" ]; then
    # shellcheck disable=SC2086
    php_lint $CAMBIADOS || FALLO_LINT=1
fi
if [ "$FALLO_LINT" = 1 ]; then
    err "hay archivos con errores de sintaxis; se vuelve a $ANTES_CORTO"
    git reset --hard "$ANTES" >/dev/null
    die "despliegue revertido"
fi
ok "sintaxis correcta"

# ------------------------------------------------------------ 7) PHP-FPM
log "Recargando PHP-FPM"
recargar_fpm

# --------------------------------------------------------- 8) verificación
log "Verificando la conexión a la base de datos"
CONFLICTO_BD=0
BASE_BD=""
SALIDA="$(cd models && php -r 'require "Musuario.php";
    $r = ActiveRecord\ConnectionManager::get_connection()->query("select current_user as u, current_database() as d")->fetch();
    echo $r["u"] . "|" . $r["d"];' 2>&1)"
BASE_ESPERADA="$(php -r 'require "config/db.php"; echo SOVCA_DB_NAME;' 2>/dev/null)"
if printf '%s' "$SALIDA" | grep -qE '^[a-zA-Z0-9_]+\|[a-zA-Z0-9_]+$'; then
    USUARIO_BD="${SALIDA%%|*}"
    BASE_BD="${SALIDA##*|}"
    ok "conectado como: $USUARIO_BD"
    if [ -n "$BASE_ESPERADA" ] && [ "$BASE_BD" != "$BASE_ESPERADA" ]; then
        CONFLICTO_BD=1
        err "la base conectada ($BASE_BD) no es SOVCA_DB_NAME ($BASE_ESPERADA)"
    else
        ok "base de datos: $BASE_BD"
    fi
else
    CONFLICTO_BD=1
    err "no se pudo verificar la conexión:"
    printf '%s\n' "$SALIDA" | tail -5 | sed 's/^/      /'
fi

# ------------------------------------------- 9) base de la demostración
log "Sincronizando la base de la demostración"
DEMO_ACTIVO="$(php -r 'require "config/demo.php";
    echo (defined("SOVCA_DEMO_ACTIVO") && SOVCA_DEMO_ACTIVO) ? "1" : "0";' 2>/dev/null)"
if [ "$DEMO_ACTIVO" != "1" ]; then
    ok "la demostración está desactivada en config/demo.php; se omite"
elif [ ! -f scripts/demo_actualizar_esquema.php ]; then
    warn "no está scripts/demo_actualizar_esquema.php; se omite la demo"
else
    LOG_DEMO="/tmp/sovca-demo-sync.log"
    if php scripts/demo_actualizar_esquema.php --verificar >"$LOG_DEMO" 2>&1; then
        ok "sovca_demo ya tiene el mismo esquema que la base real"
    else
        warn "sovca_demo está desfasada; se aplican los cambios"
        if php scripts/demo_actualizar_esquema.php --aplicar >>"$LOG_DEMO" 2>&1; then
            ok "sovca_demo actualizada"
            tail -4 "$LOG_DEMO" | sed 's/^/      /'
        else
            warn "no se pudo actualizar sovca_demo (detalle en $LOG_DEMO)"
            tail -6 "$LOG_DEMO" | sed 's/^/      /'
            warn "esto no afecta al sistema de los productores; se puede reintentar a mano"
        fi
    fi
fi

# ------------------------------------------------- 10) versión del frontend
log "Frontend offline"
VERSION_APP="$(php -r '$d = json_decode(@file_get_contents("app_offline/versiones.json"), true);
    echo isset($d["actual"]) ? $d["actual"] : "?";' 2>/dev/null)"
CACHE_SHELL="$(grep -o "VERSION_CACHE *= *'[^']*'" app_offline/service-worker.js 2>/dev/null | head -1 | sed "s/.*'\([^']*\)'/\1/")"
ok "versión publicada: V${VERSION_APP:-?} · caché del shell: ${CACHE_SHELL:-?}"
warn "los equipos con la app abierta se actualizan desde el aviso de versión (botón 'Actualizar ahora')"

if [ "$CONFLICTO_BD" = 1 ]; then
    log "CONFLICTO DE CONFIGURACIÓN DE LA BASE DE DATOS"
    printf '  base conectada : %s\n' "${BASE_BD:-desconocida}"
    printf '  SOVCA_DB_NAME   : %s\n' "${BASE_ESPERADA:-sin definir}"
    err "el código quedó desplegado, pero la conexión no es la esperada"
    err "1) escribí la base real en el archivo NO versionado:"
    err "     echo \"define('SOVCA_DB_NAME', '<base_real>');\" >> $RAIZ/config/db.local.php"
    err "2) comprobá que config/db.php del repositorio conserve el valor por defecto"
    err "   (no editar ese archivo en el servidor: un despliegue lo reemplaza)"
    err "3) recargá PHP-FPM:  sudo systemctl reload ${SERV:-php-fpm}"
    err "4) volvé a correr:  sudo ./scripts/desplegar.sh"
    [ -d "$RESPALDOS" ] && warn "respaldos en: $RESPALDOS"
    die "despliegue incompleto: corregí la configuración y reintentá"
fi

if [ "${FALLO_PERMISOS:-0}" = 1 ]; then
    log "PERMISOS DEL CÓDIGO SIN VERIFICAR"
    err "el código quedó desplegado, pero no se pudo comprobar que '$USUARIO_WEB' lo lea"
    err "1) reparalo con:  sudo $RAIZ/scripts/desplegar.sh --solo-permisos"
    err "2) o comprobá a mano:  sudo -u $USUARIO_WEB test -r $RAIZ/views/login.php"
    die "despliegue incompleto: permisos sin verificar"
fi

log "Despliegue terminado"
git log --oneline -1
[ -d "$RESPALDOS" ] && warn "respaldos en: $RESPALDOS"
exit 0
