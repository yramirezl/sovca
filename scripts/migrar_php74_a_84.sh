#!/usr/bin/env bash
# =============================================================================
#  migrar_php74_a_84.sh
#  Migración segura de PHP 7.4 → PHP 8.4 (Ubuntu/Debian + Apache mod_php o FPM)
#
#  Principios:
#    1. NO desinstala PHP 7.4: lo deja instalado para poder volver en segundos.
#    2. Instala 8.4 EN PARALELO y solo entonces analiza el código.
#    3. Si el análisis encuentra bloqueantes, NO cambia Apache.
#    4. Si el smoke test falla, hace rollback automático.
#    5. No toca la base de datos (el backup de BD es opcional y aparte).
#
#  Uso:
#    sudo bash migrar_php74_a_84.sh --dry-run        # solo analiza, no cambia nada
#    sudo bash migrar_php74_a_84.sh                  # migración completa
#    sudo bash migrar_php74_a_84.sh --force          # ignora los bloqueantes
#    sudo bash migrar_php74_a_84.sh --rollback       # vuelve a PHP 7.4
#
#  Opciones:
#    --dry-run              Solo diagnóstico y análisis (no instala ni cambia nada)
#    --force                Continúa aunque el análisis detecte bloqueantes
#    --ignorar=ruta1,ruta2  Rutas/patrones a excluir del análisis (ej: lib/PHPExcel)
#    --no-switch            Instala y analiza, pero NO cambia Apache
#    --sapi=mod_php|fpm     Fuerza el SAPI (por defecto se autodetecta)
#    --cli=8.4|7.4          Qué versión deja el comando `php` (por defecto 8.4)
#    --rollback             Deshace el cambio de Apache y vuelve a 7.4
#    --log=ARCHIVO          Guarda todo el log (por defecto /var/log/migracion_php84.log)
# =============================================================================
set -uo pipefail

ORIGEN="${ORIGEN:-7.4}"
DESTINO="8.4"
DRY_RUN=0
FORCE=0
NO_SWITCH=0
SAPI=""
CLI_DESTINO="8.4"
IGNORAR=""
LOG="/var/log/migracion_php84.log"
ACCION="migrar"

for arg in "$@"; do
  case "$arg" in
    --dry-run)        DRY_RUN=1 ;;
    --force)          FORCE=1 ;;
    --no-switch)      NO_SWITCH=1 ;;
    --rollback)       ACCION="rollback" ;;
    --ignorar=*)      IGNORAR="${arg#*=}" ;;
    --sapi=*)         SAPI="${arg#*=}" ;;
    --cli=*)          CLI_DESTINO="${arg#*=}" ;;
    --log=*)          LOG="${arg#*=}" ;;
    -h|--help)        sed -n '2,30p' "$0"; exit 0 ;;
    *) echo "Opción desconocida: $arg (usá --help)"; exit 2 ;;
  esac
done

# ---------------------------------------------------------------- utilidades --
ESC=$'\033'; RESET="${ESC}[0m"; ROJO="${ESC}[31m"; VERDE="${ESC}[32m"; AMBAR="${ESC}[33m"; AZUL="${ESC}[36m"
BLOQUEANTES=0; AVISOS=0; FALLOS_SMOKE=0; SWITCHED=0

log()   { printf '%s\n' "$*" | tee -a "$LOG" ; }
paso()  { log ""; log "${AZUL}══ $* ══${RESET}" ; }
ok()    { log "  ${VERDE}✓${RESET} $*" ; }
aviso() { AVISOS=$((AVISOS+1)); log "  ${AMBAR}⚠${RESET} $*" ; }
error() { BLOQUEANTES=$((BLOQUEANTES+1)); log "  ${ROJO}✗${RESET} $*" ; }
morir() { log ""; log "${ROJO}✗ $*${RESET}"; log "  Log completo: $LOG"; exit 1; }
correr() { if [ "$DRY_RUN" = 1 ]; then log "  [dry-run] $*"; else "$@"; fi; }

requiere_root() { [ "$(id -u)" = 0 ] || morir "Ejecutá con sudo (la migración toca paquetes y Apache)."; }

# ------------------------------------------------------------------ 0. inicio --
requiere_root
: > "$LOG"
log "=============================================================================="
log " Migración PHP ${ORIGEN} → PHP ${DESTINO}   ·   $(date '+%Y-%m-%d %H:%M:%S')"
log " Host: $(hostname)   ·   OS: $(. /etc/os-release 2>/dev/null; echo "${PRETTY_NAME:-desconocido}")"
log " Modo: $([ "$DRY_RUN" = 1 ] && echo DRY-RUN || echo REAL)  ·  Acción: $ACCION"
log "=============================================================================="

# --------------------------------------------------------------- 1. rollback --
rollback() {
  paso "ROLLBACK a PHP ${ORIGEN}"
  if [ -f /etc/apache2/mods-enabled/php${ORIGEN}.load ]; then
    ok "php${ORIGEN} ya está activo en Apache"
  else
    correr a2dismod -f "php${DESTINO}" 2>/dev/null || true
    correr a2enmod "php${ORIGEN}" || morir "No se pudo reactivar php${ORIGEN}"
  fi
  correr rm -f "/etc/php/${DESTINO}/apache2/conf.d/99-migracion-desde-${ORIGEN}.ini"
  correr apache2ctl configtest
  correr systemctl restart apache2
  correr systemctl restart "php${DESTINO}-fpm" 2>/dev/null || true
  correr update-alternatives --set php "/usr/bin/php${ORIGEN}" 2>/dev/null || true

  log ""
  log "  Apache: $(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/ 2>/dev/null)"
  log "  ${VERDE}Rollback terminado: el sitio vuelve a PHP ${ORIGEN}.${RESET}"
  log "  PHP ${DESTINO} sigue instalado; se puede reintentar cuando quieras."
  exit 0
}

if [ "$ACCION" = "rollback" ]; then rollback; fi

# ------------------------------------------------------------- 2. diagnóstico --
paso "1/8 · Diagnóstico del servidor"

PHP74_CLI=""
for c in php${ORIGEN} /usr/bin/php${ORIGEN} php; do
  if command -v "$c" >/dev/null 2>&1 && "$c" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null | grep -qx "$ORIGEN"; then
    PHP74_CLI=$(command -v "$c"); break
  fi
done
[ -n "$PHP74_CLI" ] || morir "No encontré el binario de PHP ${ORIGEN}."
ok "CLI PHP ${ORIGEN}: $PHP74_CLI ($("$PHP74_CLI" -v | head -1))"

APACHE_ACTIVO=0
if systemctl is-active --quiet apache2 2>/dev/null; then APACHE_ACTIVO=1; ok "Apache2 activo"; else aviso "Apache2 no está activo (¿usás nginx?)"; fi

# ¿Cómo está desplegado PHP 7.4?
if [ -z "$SAPI" ]; then
  if ls /etc/apache2/mods-enabled/ 2>/dev/null | grep -q "php${ORIGEN}.load"; then SAPI="mod_php"
  elif dpkg -l "php${ORIGEN}-fpm" 2>/dev/null | grep -q '^ii'; then SAPI="fpm"
  else SAPI="mod_php"; fi
fi
ok "SAPI detectado: $SAPI"

DOCROOT=$(awk '/DocumentRoot/ {print $2; exit}' /etc/apache2/sites-enabled/*.conf 2>/dev/null)
[ -n "${DOCROOT:-}" ] || DOCROOT="/var/www/html"
ok "DocumentRoot: $DOCROOT"

# Raíz real de la aplicación: si el index.php está en public/, www/, web/…,
# sube al directorio que contiene app/, core/, vendor/, .env o composer.json.
raiz_app() {
  local d="$1" p
  case "$(basename "$d")" in
    public|www|web|htdocs|html|dist) d=$(dirname "$d") ;;
  esac
  p=$(dirname "$d")
  if [ ! -e "$d/composer.json" ] && [ ! -e "$d/vendor" ] && [ ! -e "$d/app" ] && [ ! -e "$d/core" ] && [ ! -e "$d/.env" ]; then
    if [ -e "$p/composer.json" ] || [ -e "$p/vendor" ] || [ -e "$p/app" ] || [ -e "$p/core" ] || [ -e "$p/.env" ]; then
      d="$p"
    fi
  fi
  printf '%s\n' "$d"
}

# Candidatos: cada index.php encontrado (con la raíz corregida)
mapfile -t CANDIDATOS < <(find "$DOCROOT" -maxdepth 4 -name 'index.php' -not -path '*/vendor/*' -not -path '*/node_modules/*' 2>/dev/null | while IFS= read -r f; do raiz_app "$(dirname "$f")"; done | sort -u)

# Sólo es una aplicación raíz la que tiene views/, app/, core/, vendor/, composer.json o .env
APPS=()
for d in ${CANDIDATOS[@]+"${CANDIDATOS[@]}"}; do
  if [ -e "$d/views" ] || [ -e "$d/app" ] || [ -e "$d/core" ] || [ -e "$d/vendor" ] || [ -e "$d/composer.json" ] || [ -e "$d/.env" ]; then
    ancestro=0
    for a in ${APPS[@]+"${APPS[@]}"}; do
      case "$d" in "$a"/*) ancestro=1 ;; esac
    done
    [ "$ancestro" = 1 ] || APPS+=("$d")
  fi
done
if [ "${#APPS[@]}" -eq 0 ] && [ "${#CANDIDATOS[@]}" -gt 0 ]; then
  APPS=("${CANDIDATOS[@]}")
  aviso "No pude identificar la raíz de las apps; se analiza el directorio del index.php"
fi

if [ "${#APPS[@]}" -eq 0 ]; then
  aviso "No detecté aplicaciones con index.php bajo $DOCROOT (revisá el DocumentRoot)"
else
  ok "Aplicaciones detectadas (${#APPS[@]}):"
  for a in "${APPS[@]}"; do log "      · $a"; done
fi

EXT74=$(dpkg -l 'php7.4-*' 2>/dev/null | awk '/^ii/ {print $2}' | sed 's/^php7\.4-//' | grep -vE '^(common|cli|dev|dbg)$' | sort -u | tr '\n' ' ')
ok "Extensiones 7.4 instaladas: ${EXT74:-ninguna}"

LIBRE=$(df -Pk /var /usr | awk 'NR>1 {print $4}' | sort -n | head -1)
if [ "${LIBRE:-0}" -lt 524288 ]; then aviso "Quedan $((LIBRE/1024)) MB libres; recomiendo 500 MB+"; else ok "Espacio libre: $((LIBRE/1024)) MB"; fi

# --------------------------------------------------------------- 3. respaldo --
paso "2/8 · Respaldo de la configuración actual"
FECHA=$(date +%Y%m%d_%H%M%S)
BK="/root/migracion_php_${FECHA}"
mkdir -p "$BK"
correr cp -a "/etc/php/${ORIGEN}" "$BK/php${ORIGEN}" 2>/dev/null || true
correr cp -a /etc/apache2/mods-enabled "$BK/mods-enabled" 2>/dev/null || true
correr cp -a /etc/apache2/sites-enabled "$BK/sites-enabled" 2>/dev/null || true
dpkg -l 'php*' 2>/dev/null > "$BK/paquetes_php.txt" || true
"$PHP74_CLI" -m > "$BK/extensiones_74.txt" 2>/dev/null || true
"$PHP74_CLI" -i > "$BK/phpinfo_74.txt" 2>/dev/null || true
for a in "${APPS[@]}"; do
  n=$(basename "$a")
  if [ "$DRY_RUN" = 0 ]; then
    tar -czf "$BK/app_${n}.tar.gz" -C "$(dirname "$a")" "$n" 2>/dev/null || aviso "No pude comprimir $a"
  else
    log "  [dry-run] se respaldaría $a"
  fi
done
ok "Respaldo en $BK ($(du -sh "$BK" | cut -f1))"

# ------------------------------------------------------- 4. instalar PHP 8.4 --
paso "3/8 · Instalación de PHP ${DESTINO} (en paralelo, sin tocar ${ORIGEN})"

if /usr/bin/php${DESTINO} -v >/dev/null 2>&1; then
  ok "PHP ${DESTINO} ya está instalado: $(/usr/bin/php${DESTINO} -v | head -1)"
else
  if ! apt-cache policy "php${DESTINO}-cli" 2>/dev/null | grep -qE '(Candidato|Candidate):'; then
    log "  Configurando el PPA de PHP (ondrej)…"
    correr apt-get install -y --no-install-recommends software-properties-common ca-certificates apt-transport-https
    correr add-apt-repository -y ppa:ondrej/php
  fi
  correr apt-get update -qq
fi

PAQUETES=("php${DESTINO}-cli" "php${DESTINO}-common" "php${DESTINO}-opcache" "php${DESTINO}-readline")
if [ "$SAPI" = "mod_php" ]; then PAQUETES+=("libapache2-mod-php${DESTINO}"); else PAQUETES+=("php${DESTINO}-fpm"); fi
for e in $EXT74; do
  case "$e" in
    dev|dbg|common|cli|opcache|readline|fpm) continue ;;
    pgsql|mysql|sqlite3|mbstring|xml|curl|gd|zip|intl|bcmath|soap|ldap|imap|snmp|xsl|gmp|tidy|xmlrpc|bz2|enchant|pspell|sqlite) PAQUETES+=("php${DESTINO}-${e}") ;;
  esac
done
# normalizaciones de nombre entre 7.4 y 8.4
PAQUETES=("${PAQUETES[@]/php${DESTINO}-xmlrpc/php${DESTINO}-xml}")
mapfile -t PAQUETES < <(printf '%s\n' "${PAQUETES[@]}" | sort -u)

log "  Paquetes: ${PAQUETES[*]}"
correr apt-get install -y --no-install-recommends "${PAQUETES[@]}"

if [ "$DRY_RUN" = 1 ]; then
  PHP84=/usr/bin/php${DESTINO}; [ -x "$PHP84" ] || PHP84=""
else
  PHP84=/usr/bin/php${DESTINO}
  [ -x "$PHP84" ] || morir "La instalación de PHP ${DESTINO} no dejó /usr/bin/php${DESTINO}."
  ok "Instalado: $("$PHP84" -v | head -1)"
fi

# --------------------------------------------------- 5. migrar configuración --
paso "4/8 · Migrando la configuración php.ini de ${ORIGEN} → ${DESTINO}"

case "$SAPI" in
  fpm)     INI74="/etc/php/${ORIGEN}/fpm/php.ini";     INI84_DIR="/etc/php/${DESTINO}/fpm/conf.d" ;;
  *)       INI74="/etc/php/${ORIGEN}/apache2/php.ini"; INI84_DIR="/etc/php/${DESTINO}/apache2/conf.d" ;;
esac
DROPIN="${INI84_DIR}/99-migracion-desde-${ORIGEN}.ini"

DIRECTIVAS="memory_limit upload_max_filesize post_max_size max_execution_time max_input_time max_input_vars max_file_uploads post_max_size date.timezone display_errors display_startup_errors log_errors error_log error_reporting default_charset short_open_tag precision serialize_precision zlib.output_compression session.gc_maxlifetime session.cookie_lifetime session.cookie_path session.cookie_httponly session.cookie_secure session.use_strict_mode session.use_only_cookies session.save_handler opcache.enable opcache.memory_consumption opcache.interned_strings_buffer opcache.max_accelerated_files opcache.revalidate_freq opcache.validate_timestamps opcache.save_comments opcache.enable_cli"

if [ -f "$INI74" ]; then
  contenido="# Generado por migrar_php74_a_84.sh el $(date '+%Y-%m-%d %H:%M:%S')"
  contenido+=$'\n# Valores heredados de PHP '"${ORIGEN}"$'\n'
  n=0
  for d in $DIRECTIVAS; do
    v=$(grep -E "^[[:space:]]*${d}[[:space:]]*=" "$INI74" | tail -1 | sed -E "s/^[[:space:]]*[^=]+=[[:space:]]*//" | sed 's/[[:space:]]*$//')
    [ -n "$v" ] || continue
    contenido+="${d} = ${v}"$'\n'; n=$((n+1))
  done
  if [ "$DRY_RUN" = 1 ]; then
    log "  [dry-run] se escribirían $n directivas en ${DROPIN}"
  else
    mkdir -p "$(dirname "$DROPIN")"
    printf '%s' "$contenido" > "$DROPIN"
    ok "$n directivas heredadas en $DROPIN"
  fi
else
  aviso "No encontré $INI74; se usan los valores por defecto de PHP ${DESTINO}"
fi

# ------------------------------------------------------------- 6. análisis --
paso "5/8 · Análisis del código (sintaxis + funciones eliminadas en PHP 8)"

if [ -z "${PHP84:-}" ]; then
  aviso "Sin binario ${DESTINO} todavía: el análisis se hará después de instalar"
else
  IGN_ARGS=()
  if [ -n "$IGNORAR" ]; then
    IFS=',' read -ra _ig <<< "$IGNORAR"
    for p in "${_ig[@]}"; do IGN_ARGS+=(-not -path "*${p}*"); done
    ok "Excluyendo: $IGNORAR"
  fi

  TMPL=$(mktemp); TMPG=$(mktemp)
  for app in "${APPS[@]}"; do
    log "  ▸ $app"
    mapfile -t FILES < <(find "$app" -name '*.php' -not -path '*/vendor/*' -not -path '*/node_modules/*' -not -path '*/.git/*' ${IGN_ARGS[@]+"${IGN_ARGS[@]}"} 2>/dev/null)
    log "      ${#FILES[@]} archivos PHP"
    # ⚠️ php -l devuelve 255 cuando hay parse error y xargs ABORTA con ese código:
    # por eso se envuelve en sh -c "... || true" (si no, solo se detecta el primero).
    LINT_CMD="\"$PHP84\" -n -d error_reporting=E_ALL -l \"\$1\" 2>&1 || true"
    if [ "${#FILES[@]}" -gt 0 ]; then
      printf '%s\0' "${FILES[@]}" | xargs -0 -r -P 4 -n 1 /bin/sh -c "$LINT_CMD" _ > "$TMPL" 2>&1 || true
    fi
    grep -E "Parse error" "$TMPL" | sed 's/^/        /' | head -20 >> "$LOG" || true
    # "Errors parsing" aparece exactamente una vez por archivo (más fiable que contar "Parse error")
    n_parse=$(grep -c "Errors parsing" "$TMPL" 2>/dev/null); n_parse=${n_parse:-0}
    [ "$n_parse" -eq 0 ] && ok "sin errores de sintaxis" || error "$n_parse archivo(s) no compilan en PHP ${DESTINO}"

    # Funciones eliminadas en PHP 8 (con PHP 7.4 todavía funcionan)
    PATRON='(^|[^a-zA-Z_>:])(each|create_function|get_magic_quotes_gpc|get_magic_quotes_runtime|set_magic_quotes_runtime|money_format|hebrevc|convert_cyr_string|ezmlm_hash|fgetss|image2wbmp|ldap_sort|read_exif_data|gmp_random)[[:space:]]*\('
    if [ "${#FILES[@]}" -gt 0 ]; then
      grep -rnE "$PATRON" --include='*.php' "${FILES[@]}" 2>/dev/null | grep -v "foreach\|function " > "$TMPG" || true
    fi
    n_fn=$(wc -l < "$TMPG")
    if [ "$n_fn" -gt 0 ]; then
      error "$n_fn llamada(s) a funciones eliminadas en PHP 8:"
      head -8 "$TMPG" | sed "s|$app/||" | sed 's/^/        /' >> "$LOG"
    else ok "sin funciones eliminadas"; fi

    # Avisos (no rompen, pero conviene revisar)
    grep -rnE '\$HTTP_RAW_POST_DATA|\$php_errormsg|FILTER_SANITIZE_STRING' --include='*.php' ${FILES[@]+"${FILES[@]}"} 2>/dev/null | head -3 | sed 's/^/        ⚠ /' >> "$LOG" || true
    n_utf=$(grep -rlE 'utf8_(en|de)code[[:space:]]*\(|strftime[[:space:]]*\(' --include='*.php' ${FILES[@]+"${FILES[@]}"} 2>/dev/null | wc -l)
    [ "$n_utf" -gt 0 ] && aviso "$n_utf archivo(s) usan utf8_encode/decode o strftime (obsoletas desde 8.1/8.2, siguen funcionando)"

    # Constructores estilo PHP 4 (en PHP 8 ya NO se ejecutan: el objeto queda sin inicializar)
    if [ "${#FILES[@]}" -gt 0 ]; then
      printf '%s\0' "${FILES[@]}" | xargs -0 -r awk '
        function emitir(   k) { for (k in clase) if (k in fn) print archivo ": " k }
        FNR == 1 { if (archivo != "") emitir(); archivo = FILENAME; delete clase; delete fn }
        match($0, /^[[:space:]]*class[[:space:]]+[A-Za-z_][A-Za-z0-9_]*/) {
          s = substr($0, RSTART, RLENGTH); sub(/^[[:space:]]*class[[:space:]]+/, "", s); clase[s] = 1
        }
        match($0, /function[[:space:]]+[A-Za-z_][A-Za-z0-9_]*[[:space:]]*\(/) {
          s = substr($0, RSTART, RLENGTH); sub(/^function[[:space:]]+/, "", s); sub(/[[:space:]]*\($/, "", s); fn[s] = 1
        }
        END { emitir() }
      ' > "$TMPG" 2>/dev/null || true
      n_p4=$(wc -l < "$TMPG")
      if [ "$n_p4" -gt 0 ]; then
        aviso "$n_p4 constructor(es) estilo PHP 4 (revisar que además tengan __construct)"
        head -4 "$TMPG" | sed "s|$app/||; s|^|        · |" >> "$LOG"
        [ "$n_p4" -gt 4 ] && log "        · … y $((n_p4-4)) más (ver $LOG)"
      fi
    fi
  done
  rm -f "$TMPL" "$TMPG"
fi

# --------------------------------------------------------------- 7. switch --
paso "6/8 · Cambio de Apache a PHP ${DESTINO}"

PODES_CAMBIAR=1
[ "$DRY_RUN" = 1 ] && PODES_CAMBIAR=0
[ "$NO_SWITCH" = 1 ] && PODES_CAMBIAR=0
if [ "$BLOQUEANTES" -gt 0 ] && [ "$FORCE" = 0 ]; then PODES_CAMBIAR=0; fi

if [ "$PODES_CAMBIAR" = 0 ]; then
  if [ "$BLOQUEANTES" -gt 0 ] && [ "$FORCE" = 0 ]; then
    log ""
    log "  ${AMBAR}Hay ${BLOQUEANTES} bloqueante(s). NO se cambia Apache.${RESET}"
    log "  PHP ${DESTINO} quedó instalado y PHP ${ORIGEN} sigue sirviendo el sitio."
    log ""
    log "  Corregí lo listado y volvé a ejecutar, o forzá con:  --force"
    log "  Si el reporte incluye librerías legacy que no usás:   --ignorar=ruta/a/libreria"
    log "  Log: $LOG"
    exit 3
  fi
  log "  [dry-run / --no-switch] no se modifica Apache"
else
  if [ "$SAPI" = "mod_php" ]; then
    a2dismod -f "php${ORIGEN}" 2>/dev/null || true
    a2enmod "php${DESTINO}" || morir "No se pudo activar php${DESTINO} en Apache"
  else
    systemctl enable --now "php${DESTINO}-fpm" || aviso "No se pudo activar php${DESTINO}-fpm"
  fi
  apache2ctl configtest || morir "Configuración de Apache inválida (no se reinició nada)"
  systemctl restart apache2 || morir "Apache no reinició"
  sleep 2
  SWITCHED=1
  ok "Apache reiniciado con PHP ${DESTINO}"
fi

# ----------------------------------------------------------- 8. smoke test --
paso "7/8 · Verificación funcional"

if [ "$SWITCHED" = 1 ]; then
  LOGAPACHE="/var/log/apache2/error.log"
  LINEAS_ANTES=0
  [ -f "$LOGAPACHE" ] && LINEAS_ANTES=$(wc -l < "$LOGAPACHE")

  for app in "${APPS[@]}"; do
    rel="${app#$DOCROOT}"; rel="${rel#/}"
    url="http://127.0.0.1/${rel}/"
    code=$(curl -s -o /dev/null -m 20 -w '%{http_code}' "$url" 2>/dev/null || echo "000")
    if [ "$code" -ge 500 ] || [ "$code" = "000" ]; then
      error "HTTP $code en $url"
      FALLOS_SMOKE=$((FALLOS_SMOKE+1))
    else
      ok "HTTP $code en $url"
    fi
  done

  if [ -f "$LOGAPACHE" ]; then
    FATALES=$(tail -n +$((LINEAS_ANTES+1)) "$LOGAPACHE" 2>/dev/null | grep -ci "PHP Fatal error" || true)
    if [ "${FATALES:-0}" -gt 0 ]; then
      error "$FATALES error(es) fatal(es) nuevos en $LOGAPACHE"
      tail -n +$((LINEAS_ANTES+1)) "$LOGAPACHE" | grep -i "PHP Fatal error" | head -5 | sed 's/^/        /' >> "$LOG"
      FALLOS_SMOKE=$((FALLOS_SMOKE+FATALES))
    else
      ok "sin errores fatales nuevos en el log"
    fi
  fi

  if [ "$FALLOS_SMOKE" -gt 0 ]; then
    log ""
    log "  ${ROJO}Falló la verificación → ROLLBACK AUTOMÁTICO${RESET}"
    rollback
  fi
fi

# --------------------------------------------------------- 9. CLI + cierre --
paso "8/8 · CLI y resumen"

if [ "$CLI_DESTINO" = "8.4" ] && [ "$DRY_RUN" = 0 ] && [ -x "/usr/bin/php${DESTINO}" ]; then
  update-alternatives --set php "/usr/bin/php${DESTINO}" 2>/dev/null && ok "Comando 'php' → ${DESTINO}" || aviso "No se pudo fijar la alternativa del CLI"
elif [ "$CLI_DESTINO" = "7.4" ] && [ "$DRY_RUN" = 0 ]; then
  update-alternatives --set php "/usr/bin/php${ORIGEN}" 2>/dev/null && ok "Comando 'php' se deja en ${ORIGEN}" || true
fi

log ""
log "=============================================================================="
log " RESUMEN"
log "=============================================================================="
log "  PHP ${DESTINO} instalado : $(/usr/bin/php${DESTINO} -v 2>/dev/null | head -1 || echo 'no instalado')"
log "  Apache                  : $([ "$SWITCHED" = 1 ] && echo "sirviendo PHP ${DESTINO}" || echo "sin cambios (sigue en ${ORIGEN})")"
if [ -x "/usr/bin/php${DESTINO}" ]; then
  log "  Extensiones ${DESTINO}     : $(/usr/bin/php${DESTINO} -m 2>/dev/null | grep -vE '^\[|^$|^(core|date|filter|hash|json|libxml|openssl|pcntl|pcre|random|Reflection|session|sodium|SPL|standard|zlib)$' | grep -v 'Zend OPcache' | sort -u | tr '\n' ' ')"
fi
log "  Bloqueantes             : $BLOQUEANTES"
log "  Avisos                  : $AVISOS"
log "  Respaldo                : $BK"
log "  Log                     : $LOG"
log ""
log "  Rollback (1 comando):   sudo bash $0 --rollback"
log "  Reintentar análisis:    sudo bash $0 --dry-run"
log "=============================================================================="

[ "$BLOQUEANTES" -gt 0 ] && exit 3 || exit 0
