# Plan de actualización de SOVCA en producción

Puesta en marcha desde cero: se elimina el directorio actual y se baja todo del
repositorio. SOVCA no tiene datos propios en el servidor (las fotos y los
correos viven en la base y en `file/`, que se descartan).

**BOVS no se toca.** `/var/www/html/bovs` queda intacto; solo se comparte
PostgreSQL y PgBouncer, y los cambios que se hacen aquí son aditivos (un rol
nuevo, una base ruteada más y unos permisos sobre `sovca`).

---

## Resumen

| # | Qué |
|---|---|
| 1 | Clonar en paralelo y verificar |
| 2 | Sustituir el directorio viejo |
| 3 | `config/db.php` con las credenciales de `sovca_app` |
| 4 | Verificar `config/mail.php` |
| 5 | Crear `sovca_app` y sus permisos |
| 6 | PgBouncer (userlist + `[databases]`) |
| 7 | Migraciones de base de datos |
| 8 | Permisos de archivos y servicios |
| 9 | Verificación |
| 10 | Limpieza total |

---

## 1 · Clonar en paralelo

```bash
cd /var/www/html

# Si quedó un intento anterior
rm -rf sovca-nuevo

git clone https://github.com/yramirezl/sovca.git sovca-nuevo

cd sovca-nuevo
git log --oneline -1          # 79cfd7f o posterior
ls lib/fpdf | head -3         # changelog.htm composer.json doc
```

Si el `clone` pide credenciales, el servidor necesita las mismas que ya usa
para BOVS (`git config --global credential.helper` o un token en la URL).

Comprobar que vienen las piezas nuevas:

```bash
ls config/db.php config/db.local.example.php config/mail.php
ls scripts/crear_usuario_sovca_app.sh scripts/pgbouncer_preparar_usuario.sh
[ -e config/db.local.php ] && echo "OJO: no debería existir" || echo "OK: sin db.local.php"
```

---

## 2 · Sustituir el directorio

```bash
cd /var/www/html
mv sovca sovca-viejo
mv sovca-nuevo sovca
ls -ld sovca sovca-viejo bovs
```

`bovs` debe seguir ahí, sin cambios. El viejo se borra en el paso 10.

---

## 3 · `config/db.php` con las credenciales

Editá los valores por defecto de estas tres constantes:

```bash
cd /var/www/html/sovca
nano config/db.php
```

```php
if (!defined('SOVCA_DB_PORT')) {
    define('SOVCA_DB_PORT', sovca_db_env('SOVCA_DB_PORT', '6432'));   // PgBouncer
}
if (!defined('SOVCA_DB_USER')) {
    define('SOVCA_DB_USER', sovca_db_env('SOVCA_DB_USER', 'sovca_app'));
}
if (!defined('SOVCA_DB_PASS')) {
    define('SOVCA_DB_PASS', sovca_db_env('SOVCA_DB_PASS', 'LA-CLAVE-DE-SOVCA-APP'));
}
```

`SOVCA_DB_HOST` se queda en `127.0.0.1` y `SOVCA_DB_NAME` en `sovca`.

Comprobar la sintaxis y la conexión:

```bash
php -l config/db.php
php scripts/diagnostico_conexiones.php
```

> El paso 5 crea el rol; si lo ejecutás antes de eso, el diagnóstico fallará por
> credenciales. Es normal. El orden correcto es 5 → 6 → volver aquí a probar.
>
> **Nota de seguridad:** la clave queda escrita en un archivo versionado. Si el
> repositorio se comparte con más gente, mové esa línea a `config/db.local.php`
> (está en `.gitignore`) y dejá el valor por defecto vacío.

---

## 4 · `config/mail.php`

La clave del buzón ya viene escrita en el archivo; no hay que hacer nada. Solo
confirmar que el helper la resuelve:

```bash
cd /var/www/html/sovca/controllers
php -r 'chdir(__DIR__); require "../lib/mailer_helper.php";
$c = bovs_mail_config();
echo "buzon: " . $c["username"] . "\n";
echo "clave: " . ($c["password"] !== "" ? "configurada" : "VACIA") . "\n";'
```

Esperado: `buzon: global@ruted.net` y `clave: configurada`.

Si el servidor define `SOVCA_SMTP_PASS`, esa variable tiene prioridad.

---

## 5 · Crear `sovca_app`

```bash
cd /var/www/html/sovca

CLAVE_APP=$(openssl rand -hex 24)
echo "$CLAVE_APP" > /root/sovca_app.txt
chmod 600 /root/sovca_app.txt
echo "Clave de sovca_app: $CLAVE_APP"

SOVCA_APP_PASS="$CLAVE_APP" bash scripts/crear_usuario_sovca_app.sh
```

El script se conecta como superusuario por el socket local
(`sudo -u postgres psql`, peer) y no pide contraseña. Si prefirieras TCP:

```bash
PGPASSWORD_ADMIN='clave-de-postgres' SOVCA_APP_PASS="$CLAVE_APP" \
    bash scripts/crear_usuario_sovca_app.sh
```

Qué hace, igual que se hizo con `bovs_app`:

- crea el rol con `LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE`;
- `GRANT CONNECT, TEMPORARY` sobre `sovca` (y `sovca_demo` si existe);
- `USAGE` del esquema `public`;
- `SELECT/INSERT/UPDATE/DELETE` sobre todas las tablas;
- `USAGE, SELECT` sobre todas las secuencias y `EXECUTE` sobre las funciones;
- `ALTER DEFAULT PRIVILEGES` para objetos futuros.

Prueba directa al 5432, sin PgBouncer:

```bash
PGPASSWORD="$CLAVE_APP" psql -h 127.0.0.1 -p 5432 -U sovca_app -d sovca \
    -c "SELECT current_user, count(*) FROM manimales;"
```

Esperado: `sovca_app | <total>`.

Ahora sí, poné esa misma clave en `config/db.php` (paso 3) si todavía no lo
hiciste.

---

## 6 · PgBouncer

```bash
cd /var/www/html/sovca
bash scripts/pgbouncer_preparar_usuario.sh              # simulación
bash scripts/pgbouncer_preparar_usuario.sh --aplicar
```

El script solo añade lo de SOVCA: la línea de `sovca_app` en el userlist y
`sovca = host=127.0.0.1 port=5432` en `[databases]`. **No toca la línea de
`bovs` ni la de `bovs_app`**, y el `reload` no corta las conexiones activas de
BOVS.

Si se detiene por `auth_type = md5` con secreto SCRAM:

```bash
sed -i 's/^[[:space:]]*auth_type[[:space:]]*=.*/auth_type = scram-sha-256/' \
     /etc/pgbouncer/pgbouncer.ini
systemctl reload pgbouncer
bash scripts/pgbouncer_preparar_usuario.sh --aplicar
```

Prueba por el pooler:

```bash
PGPASSWORD="$CLAVE_APP" psql -h 127.0.0.1 -p 6432 -U sovca_app -d sovca \
    -c "SELECT current_database(), current_user, count(*) FROM manimales;"
```

Y comprobá que BOVS sigue funcionando:

```bash
PGPASSWORD=$(grep -oP '(?<="bovs_app" ")[^"]*' /etc/pgbouncer/userlist.txt | head -1) \
  psql -h 127.0.0.1 -p 6432 -U bovs_app -d bovs -c "SELECT 1;" 2>&1 | head -2
```

(Si esa prueba no es concluyente porque el userlist guarda el hash SCRAM y no la
clave, basta con abrir BOVS en el navegador.)

---

## 7 · Migraciones de base de datos

`sovca_app` no es dueño de las tablas, así que los DDL se ejecutan con
`postgres` ignorando la configuración de la aplicación:

```bash
cd /var/www/html/sovca
export SOVCA_DB_IGNORAR_LOCAL=1 SOVCA_DB_USER=postgres

php scripts/actualizar_planes_fundos.php;                    php scripts/actualizar_planes_fundos.php --aplicar
php scripts/agregar_cierre_remoto_sesiones.php;              php scripts/agregar_cierre_remoto_sesiones.php --aplicar
php scripts/optimizar_indices_reportes.php;                  php scripts/optimizar_indices_reportes.php --aplicar --analizar
php scripts/corregir_fk_servicio_nacimiento.php;             php scripts/corregir_fk_servicio_nacimiento.php --aplicar
php scripts/actualizar_vista_animales_activos.php --dry-run; php scripts/actualizar_vista_animales_activos.php

unset SOVCA_DB_IGNORAR_LOCAL SOVCA_DB_USER
```

Todos son simulados por defecto: leé la salida de cada uno antes de pasar al
siguiente. Afectan **solo a la base `sovca`**.

---

## 8 · Permisos y servicios

```bash
APP=/var/www/html/sovca

chown -R root:www-data "$APP"
find "$APP" -type d -exec chmod 775 {} \;
find "$APP" -type f -exec chmod 664 {} \;

# Donde la aplicación escribe
mkdir -p "$APP/file/correo"
chown -R www-data:www-data "$APP/file"

systemctl reload php8.4-fpm     # o el que devuelva: systemctl list-units | grep php
systemctl reload pgbouncer
```

`reload` es suave: no corta peticiones en curso de BOVS.

---

## 9 · Verificación

```bash
cd /var/www/html/sovca
php scripts/diagnostico_conexiones.php        # debe decir sovca · sovca_app
php scripts/mantenimiento.php --estado        # NO debe estar en mantenimiento
```

En el navegador:

1. Login → 200, sin avisos de conexión.
2. `verificar_despliegue.php?k=sovca-verif-2026` → todo en verde.
3. Animales: listado, alta con lote y sin lote.
4. Nacimientos: alta sin arete y sin servicio gestante.
5. Reportes: un PDF de animales activos (usa `lib/fpdf`).
6. Planes (usuario rol 4): crear/editar y ver el cupo en la ficha de usuario.
7. Conexiones: cierre remoto de una sesión con cuenta atrás.
8. Móvil: el login ofrece «Instalar app».
9. **BOVS**: entrar y abrir un listado, para confirmar que el pooler sigue bien.

---

## 10 · Limpieza total

Solo cuando el paso 9 esté completo:

```bash
rm -rf /var/www/html/sovca-viejo
rm -f  /root/sovca-*.txt /root/sovca-*.tgz /root/sovca-*.dump
rm -rf /root/sovca-respaldo /root/sovca-20*
ls /var/www/html
```

---

## Si algo falla

| Síntoma | Causa | Salida |
|---|---|---|
| `password authentication failed for user "sovca_app"` | Clave distinta entre `config/db.php` y el rol, o userlist desactualizado | Rehacer pasos 5 y 6 |
| `password authentication failed for user "postgres"` | Un script intentando TCP sin `PGPASSWORD_ADMIN` | Ejecutar como root (usa socket) o pasar la clave |
| `no such database: sovca` | PgBouncer sin ruta para la base | Paso 6 |
| `permission denied for table …` | Faltan GRANT | Repetir paso 5 |
| `must be owner of table …` | DDL con `sovca_app` | Paso 7 |
| 500 tras el clon | `config/db.php` sin la clave correcta | Paso 3 |

**Volver atrás** (mientras `sovca-viejo` exista):

```bash
cd /var/www/html
mv sovca sovca-fallido
mv sovca-viejo sovca
systemctl reload php8.4-fpm
```

La base no se modifica, así que el código anterior funciona igual. Si ya se
aplicaron las migraciones, son aditivas y el código viejo las ignora.
