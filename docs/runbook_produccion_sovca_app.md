# Runbook · desplegar SOVCA en producción con `sovca_app`

Destino: `/var/www/html/sovca`, rama `main`, commit de referencia `27a68ba`.
Base `sovca`, rol `sovca_app`, PgBouncer en `6432`.

> **Regla:** la conexión (rol + PgBouncer + `config/db.local.php`) se deja lista
> **antes** del `git pull`. El pull reemplaza `config/conexion.php`, que hoy
> lleva las credenciales escritas a mano.

Variables que se usan una y otra vez:

```bash
APP=/var/www/html/sovca
CLAVE_APP='la-clave-real-de-sovca_app'
PHP_SVC=$(systemctl list-units --type=service --plain --no-legend \
          | grep -oE 'php8?\.?4-fpm' | head -1)
echo "PHP-FPM: $PHP_SVC"

# Git como el usuario de Apache, sin que importe de quién sea el repositorio.
# El -c evita el error "detected dubious ownership".
GIT="sudo -u www-data git -c safe.directory=$APP -C $APP"
```

---

## Paso 0 · Situación actual (sin tocar nada)

```bash
cd $APP

# Rama y estado del repo
$GIT branch --show-current
$GIT status --short

# Credenciales con las que funciona HOY (¡anotarlas!)
grep -n "pgsql://" config/conexion.php alertas/conexion.php \
     Reportes/lib/bd/basedatosAdoIbase.php

# Bases existentes: ¿sigue habiendo una sovca_qas?
sudo -u postgres psql -Atc "SELECT datname FROM pg_database ORDER BY 1;"
sudo -u postgres psql -Atc "SELECT rolname FROM pg_roles WHERE rolname LIKE '%app%';"

# Respaldos
sudo tar czf /root/sovca-pre-$(date +%F-%H%M).tgz -C /var/www/html sovca/config
```

Si `git status` muestra cambios en archivos versionados (típico:
`config/conexion.php` editado a mano en el servidor), **no los descartes
todavía**: sus credenciales son las que van a `config/db.local.php` en el paso 2.

### Si aparece «detected dubious ownership»

```
fatal: detected dubious ownership in repository at '/var/www/html/sovca'
```

Significa que el repositorio pertenece a otro usuario (root, `yramirez`…) y no a
`www-data`. La variable `$GIT` de arriba ya lo resuelve sin tocar nada.

Para dejarlo arreglado de forma permanente hay dos caminos:

```bash
# a) Guardar la excepción para www-data (rápido, no cambia dueños)
sudo -u www-data git config --global --add safe.directory "$APP"
#    si falla por el HOME de www-data:
sudo -u www-data HOME=/var/www git config --global --add safe.directory "$APP"
sudo -u www-data git config --global --get-all safe.directory

# b) Homogeneizar el dueño del árbol (lo correcto si el pull se hace como www-data)
sudo chown -R www-data:www-data "$APP"
```

La opción **b** es la recomendada: si el despliegue se hace con `www-data`, todo
el árbol debe ser suyo, así los archivos nuevos no quedan con otro dueño y la
aplicación puede escribir en `file/`. Si preferís desplegar con vuestro usuario
por SSH, entonces elegid la **a** y haced siempre `git -c safe.directory=…`.

---

## Paso 1 · Bajar la referencia y crear el rol

```bash
cd $APP
$GIT fetch origin

# El script se ejecuta desde fuera del árbol para no depender del pull
$GIT show origin/main:scripts/crear_usuario_sovca_app.sh > /tmp/crear_usuario.sh
SOVCA_APP_PASS="$CLAVE_APP" bash /tmp/crear_usuario.sh
```

Debe terminar con `Rol sovca_app creado/actualizado` y `GRANT` en la base
`sovca` (y `sovca_demo` si existe).

Comprobación:

```bash
sudo -u postgres psql -Atc \
  "SELECT rolname||' login='||rolcanlogin||' super='||rolsuper
     FROM pg_roles WHERE rolname='sovca_app';"
# esperado: sovca_app login=true super=false
```

---

## Paso 2 · `config/db.local.php` (antes del pull)

```bash
cd $APP
$GIT show origin/main:config/db.local.example.php > /tmp/db.local.php
sudo -u www-data cp /tmp/db.local.php config/db.local.php
sudo -u www-data nano config/db.local.php
```

Contenido final:

```php
define('SOVCA_DB_HOST', '127.0.0.1');
define('SOVCA_DB_PORT', '6432');       // PgBouncer
define('SOVCA_DB_NAME', 'sovca');
define('SOVCA_DB_USER', 'sovca_app');
define('SOVCA_DB_PASS', 'la-clave-real-de-sovca_app');
```

```bash
sudo chown www-data:www-data config/db.local.php
sudo chmod 640 config/db.local.php
```

> `config/db.local.php` está en `.gitignore`. Existe para que el pull nunca
> pise las credenciales del servidor.

---

## Paso 3 · PgBouncer

```bash
sudo bash /tmp/pgbouncer_preparar_usuario.sh                        # simulación
sudo bash /tmp/pgbouncer_preparar_usuario.sh --aplicar
```

El script: escribe la línea de `sovca_app` en `userlist.txt` con el secreto real
de `pg_authid`, agrega `sovca = host=127.0.0.1 port=5432` a `[databases]` si
falta, respalda ambos archivos y recarga el servicio.

Si se detiene por **incompatibilidad md5/SCRAM**:

```bash
sudo sed -i 's/^[[:space:]]*auth_type[[:space:]]*=.*/auth_type = scram-sha-256/' \
     /etc/pgbouncer/pgbouncer.ini
sudo systemctl reload pgbouncer
sudo bash /tmp/pgbouncer_preparar_usuario.sh --aplicar
```

Si el script no se puede bajar (no existe `/tmp/pgbouncer_preparar_usuario.sh`):

```bash
$GIT show origin/main:scripts/pgbouncer_preparar_usuario.sh > /tmp/pgbouncer_preparar_usuario.sh
```

### Prueba obligatoria por el pooler

```bash
PGPASSWORD="$CLAVE_APP" psql -h 127.0.0.1 -p 6432 -U sovca_app -d sovca \
    -c "SELECT current_database(), current_user;"

PGPASSWORD="$CLAVE_APP" psql -h 127.0.0.1 -p 6432 -U sovca_app -d sovca \
    -c "SELECT count(*) FROM manimales;"
```

Las dos deben responder. Si la segunda da `permission denied`, repetir el paso 1.

Revisar también `pg_hba.conf` (PgBouncer se conecta a PostgreSQL como este rol):

```bash
sudo grep -E "^host" /etc/postgresql/*/main/pg_hba.conf | grep -E "127.0.0.1|::1"
sudo systemctl status pgbouncer --no-pager | head -12
```

---

## Paso 4 · Pull del código

Con el sistema todavía en mantenimiento:

```bash
php $APP/scripts/mantenimiento.php --on --motivo="Despliegue SOVCA" --minutos=60
```

```bash
cd $APP
$GIT pull origin main
$GIT log --oneline -1
```

Debe quedar en `27a68ba` (o el último de `main`).

Si `git pull` se queja de cambios locales en `config/`:

```bash
$GIT stash push -m "local prod $(date +%F)" -- config
$GIT pull origin main
# el respaldo de credenciales ya está en config/db.local.php
```

(`dubious ownership` ya quedó resuelto con la variable `$GIT` en el paso 0.)

---

## Paso 5 · DDL (siempre como `postgres`)

`sovca_app` **no es dueño** de las tablas, así que los scripts que cambian
estructura se lanzan ignorando `db.local.php`:

```bash
cd $APP
export SOVCA_DB_IGNORAR_LOCAL=1 SOVCA_DB_USER=postgres

php scripts/actualizar_planes_fundos.php                 # simulación
php scripts/actualizar_planes_fundos.php --aplicar

php scripts/agregar_cierre_remoto_sesiones.php           # simulación
php scripts/agregar_cierre_remoto_sesiones.php --aplicar

php scripts/optimizar_indices_reportes.php               # simulación
php scripts/optimizar_indices_reportes.php --aplicar --analizar

php scripts/corregir_fk_servicio_nacimiento.php          # simulación
php scripts/corregir_fk_servicio_nacimiento.php --aplicar

php scripts/actualizar_vista_animales_activos.php --dry-run
php scripts/actualizar_vista_animales_activos.php

unset SOVCA_DB_IGNORAR_LOCAL SOVCA_DB_USER
```

Todos son idempotentes y simulados por defecto. Leer la salida de cada uno
antes de pasar al siguiente.

---

## Paso 6 · Servicios y caché

```bash
sudo systemctl reload "$PHP_SVC"
sudo systemctl reload pgbouncer
```

Si `$PHP_SVC` salió vacío:

```bash
systemctl list-units --type=service | grep -i php
```

---

## Paso 7 · Verificación

```bash
cd $APP

# Conexión real de la aplicación (rol por el pooler)
sudo -u www-data php scripts/diagnostico_conexiones.php

# Permisos de escritura con el rol de la aplicación
PGPASSWORD="$CLAVE_APP" psql -h 127.0.0.1 -p 6432 -U sovca_app -d sovca \
  -c "BEGIN; INSERT INTO mlotes (nombre, mfundos_id, status) VALUES ('zz_permiso', 1, 1); ROLLBACK;"

# Cifras coherentes
sudo -u postgres psql -d sovca -Atc \
  "SELECT count(*) FROM v_list_animales_activos;"
```

En el navegador:

1. `https://TU-DOMINIO/sovca/views/login.php` → 200 sin avisos de conexión.
2. `https://TU-DOMINIO/sovca/verificar_despliegue.php?k=sovca-verif-2026` →
   todos los controles en verde (`&reset=1` limpia OPcache antes de medir).
3. Animales: listado y alta con y sin lote.
4. Nacimientos: alta sin arete y sin servicio gestante.
5. Reportes: un PDF de animales activos y uno de producción.
6. Planes (usuario rol 4): crear/editar y ver el cupo en la ficha de usuario.
7. Conexiones: cerrar una sesión → cuenta atrás de 60 s.
8. Desde el móvil: el login ofrece «Instalar app».

```bash
php $APP/scripts/mantenimiento.php --off --nota="Despliegue completado"
```

---

## Rollback

```bash
cd $APP

# Código
$GIT reset --hard 4b772d2

# Configuración (credenciales del servidor)
sudo tar xzf /root/sovca-pre-AAAA-MM-DD-HHMM.tgz -C /var/www/html

# PgBouncer
sudo cp /etc/pgbouncer/pgbouncer.ini.bak-AAAAMMDD-HHMMSS /etc/pgbouncer/pgbouncer.ini
sudo cp /etc/pgbouncer/userlist.txt.bak-AAAAMMDD-HHMMSS /etc/pgbouncer/userlist.txt
sudo systemctl reload pgbouncer
sudo systemctl reload "$PHP_SVC"
```

Las migraciones son aditivas (columnas, índices, una vista): el código viejo
las ignora. `actualizar_planes_fundos.php` es el único que toca datos
existentes (`mplanes`), y solo con `--forzar`.

---

## Problemas típicos

| Síntoma | Causa | Salida |
|---|---|---|
| `password authentication failed for user "sovca_app"` | Línea del userlist con hash incorrecto o `auth_type` md5 con secreto SCRAM | Paso 3 |
| `no such database: sovca` | PgBouncer sin ruta para la base | Paso 3 (`[databases]`) |
| `permission denied for table …` | Faltan GRANT | Repetir paso 1 |
| `must be owner of table …` | DDL con `sovca_app` | Paso 5 (usar `postgres`) |
| Página en blanco / 500 tras el pull | `config/db.local.php` ausente o con valores de ejemplo | Paso 2 y `php -l config/db.php` |
| Sigue mostrando la versión vieja | OPcache | `sudo systemctl reload "$PHP_SVC"` |
| Correos sin enviar | Falta la clave del buzón | `config/mail.php` o `SOVCA_SMTP_PASS` |
