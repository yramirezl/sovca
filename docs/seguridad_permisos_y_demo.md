# Permisos y endurecimiento de SOVCA y BOVS

Aplica a `/var/www/html/sovca` y `/var/www/html/bovs`. Los dos funcionan igual:
PHP-FPM corre como `www-data` y el código lo despliega `root`.

## Qué necesita permiso de escritura

Solo estas dos carpetas (más `/tmp`, que ya es escribible):

| Carpeta | Qué se escribe |
|---|---|
| `file/` | `mantenimiento/estado.json`, `correo/` (modo prueba), adjuntos de mensajes |
| `dist/img/fotos_animales/` | fotos de animales (alta y edición) |

El resto —código, `config/`, `models/`, `vendor/`, `plugins/`, `lib/`, `docs/`, `scripts/`— **solo se lee**.

## Comandos (idénticos para las dos aplicaciones)

```bash
APP=/var/www/html/sovca        # o /var/www/html/bovs

# 1) Código: dueño root, lectura para Apache por grupo
chown -R root:www-data "$APP"
find "$APP" -type d -exec chmod 750 {} \;
find "$APP" -type f -exec chmod 640 {} \;

# 2) Las dos carpetas donde la aplicación escribe
chown -R www-data:www-data "$APP/file" "$APP/dist/img/fotos_animales"
find "$APP/file" "$APP/dist/img/fotos_animales" -type d -exec chmod 770 {} \;
find "$APP/file" "$APP/dist/img/fotos_animales" -type f -exec chmod 660 {} \;

# 3) El repositorio fuera del alcance de Apache
chown -R root:root "$APP/.git"
chmod 700 "$APP/.git"

# 4) Archivos con credenciales: solo root y Apache
chown root:www-data "$APP/config/db.php" "$APP/config/mail.php"
chmod 640 "$APP/config/db.php" "$APP/config/mail.php"
chmod 640 "$APP/config/conexion.php" "$APP/config/demo.php"
```

Por qué `750`/`640` y no `755`/`644`: con el grupo `www-data` basta para que
Apache lea, y ningún otro usuario del sistema puede ver el código ni las
credenciales. Si preferís lo clásico, `755`/`644` también funciona (Apache lee
por «others»), pero expone más.

**Nunca `777`.** Deja el código y `.git` modificables por cualquiera.

## Verificación

```bash
APP=/var/www/html/sovca

sudo -u www-data test -r "$APP/index.php" && echo "lectura del código: OK"
sudo -u www-data test -w "$APP/file" && echo "file/: escribible"
sudo -u www-data test -w "$APP/dist/img/fotos_animales" && echo "fotos: escribible"
sudo -u www-data test -w "$APP/config" && echo "OJO: config escribible" || echo "config/: protegido"
sudo -u www-data test -r "$APP/.git" && echo "OJO: .git visible" || echo ".git: protegido"
```

Las cuatro últimas deben dar `escribible`, `escribible`, `protegido` y
`protegido`.

## Endurecimiento en Apache

Los permisos no impiden que alguien pida `https://dominio/sovca/config/mail.php`
por HTTP: eso lo resuelve Apache. En el vhost (o en el `.conf` del sitio):

```apache
# Sin listados de directorios
<Directory /var/www/html/sovca>
    Options -Indexes
    AllowOverride All
    Require all granted
</Directory>

# Nada de esto debe servirse por web
<DirectoryMatch "^/var/www/html/(sovca|bovs)/(config|docs|scripts|php-activerecord|vendor|\.git)">
    Require all denied
</DirectoryMatch>

# Extensiones que no son contenido público
<FilesMatch "\.(sql|md|log|bak|ini|sh|sh\$|gitignore)$">
    Require all denied
</FilesMatch>
```

Y **muy importante**, porque `file/` recibe archivos subidos por usuarios: que
Apache no ejecute PHP ahí.

```apache
<Directory /var/www/html/sovca/file>
    Options -Indexes -ExecCGI
    php_admin_flag engine off
    <FilesMatch "\.(php|phtml|phar|cgi|pl|py|sh)$">
        Require all denied
    </FilesMatch>
</Directory>

<Directory /var/www/html/sovca/dist/img/fotos_animales>
    Options -Indexes -ExecCGI
    php_admin_flag engine off
</Directory>
```

Lo mismo para `/var/www/html/bovs/...`.

```bash
apachectl configtest && systemctl reload apache2
```

> Si preferís `.htaccess` en vez del vhost, necesita `AllowOverride All` (o al
> menos `FileInfo Options AuthConfig Limit`). Con `AllowOverride None` se
> ignoran en silencio; con `AllowOverride` parcial pueden provocar **500**.

## Opcional: limitar a qué rutas accede PHP

```apache
php_admin_value[open_basedir] = /var/www/html/sovca:/tmp
```

Evita que un fallo de inclusión lea `/etc/passwd`. Probalo primero: cualquier
script que abra una ruta fuera de esas dos fallará. Una por aplicación
(`.../sovca:/tmp` y `.../bovs:/tmp`), nunca la raíz `/var/www/html` completa.

---

# `lib/firma.php` — la clave NO se comparte

**No, no debe usarse el mismo hash que BOVS.** Cada instalación tiene el suyo y
así está bien:

| Sistema | Secreto |
|---|---|
| BOVS | `ruted_bovs_2026_firma_segura_a7f3e9c1…` |
| SOVCA | `ruted_sovca_2026_firma_segura_85fd75c0…` |

El QR es `id_animal + HMAC(id_animal, secreto)`. Si las dos aplicaciones
compartieran la clave, un QR impreso de un animal de BOVS **validaría en SOVCA**
y abriría la ficha del animal con ese mismo `id` en la otra base: dos hatos
distintos mostrando el animal equivocado.

Eso es justo lo que se corrigió al separar los secretos y al calcular la URL con
`SOVCA_URL_PUBLICA` (el QR de SOVCA apunta a `/sovca/verificar.php`, no a
`/bovs/`).

## Si querés rotar la clave

Se puede sobrescribir sin tocar el archivo versionado:

```php
// config/db.local.php  (no se sube al repositorio)
define('SOVCA_FIRMA_SECRET', 'otra-cadena-larga-y-aleatoria');
```

Los QR ya impresos dejan de validar. Para que sigan funcionando mientras se
reemplazan:

```bash
# Variable de entorno del pool de PHP-FPM o del vhost
SOVCA_FIRMA_SECRET_ANTERIOR='ruted_sovca_2026_firma_segura_85fd75c0…'
```

`firma_verificar()` acepta la clave vieja **solo** si esa variable está definida.
Cuando todos los animales tengan QR nuevo, se quita.

---

# `sovca_demo` — por qué se omitió y qué hacer

El script dijo `· sovca_demo no existe: se omite`. Es correcto: solo concede
permisos sobre bases que existen, y en el servidor la base demo nunca se creó.

Pero `config/demo.php` trae `SOVCA_DEMO_ACTIVO = true`, así que el acceso de
demostración del login está visible y **fallaría** al entrar (la base no existe).

Elegí una de las dos:

## A) No usar la demo en producción (recomendado)

Sin tocar archivos versionados, agregando dos líneas a `config/db.local.php`
(que `demo.php` carga antes de definir sus constantes):

```php
define('SOVCA_DEMO_ACTIVO', false);
```

```bash
cd /var/www/html/sovca
php -r 'chdir("controllers"); require "../config/demo.php";
echo "demo: " . (SOVCA_DEMO_ACTIVO ? "ACTIVO" : "apagado") . "\n";'
```

Con eso desaparece el acceso demo del login y no hace falta la base. Es la
opción recomendada en producción: el modo demo existe para mostrar el sistema a
un interesado, no para el día a día.

Si preferís tocarlo en el archivo versionado, es la misma línea en
`config/demo.php`.

## B) Usarla

El instalador usa `SOVCA_DEMO_USUARIO` y `SOVCA_DEMO_CLAVE` de
`config/demo.php`, que traen `postgres/postgres`. En producción la clave de
`postgres` es otra, así que hay que ajustarla antes:

```bash
cd /var/www/html/sovca

sed -i "s/SOVCA_DEMO_CLAVE', 'postgres'/SOVCA_DEMO_CLAVE', 'LA-CLAVE-DE-POSTGRES'/" config/demo.php
php -l config/demo.php

# Como root. NO uses 'sudo -u postgres php': con los permisos 750 root:www-data
# el usuario del sistema 'postgres' no puede leer el código y PHP responde
# "Could not open input file". Las credenciales de la base las toma de
# config/demo.php, no del usuario del sistema.
php /var/www/html/sovca/scripts/demo_instalar.php

# Ahora sí, permisos para el rol de la aplicación en la base nueva
SOVCA_APP_PASS="$(cat /root/sovca_app.txt)" bash scripts/crear_usuario_sovca_app.sh
```

> `Could not open input file: scripts/demo_instalar.php` significa que el
> comando se lanzó desde otra carpeta. Usá siempre la ruta absoluta o hacé
> `cd /var/www/html/sovca` primero.

El segundo comando es necesario porque los `GRANT` se aplicaron cuando
`sovca_demo` no existía.

Verificación:

```bash
PGPASSWORD="$(cat /root/sovca_app.txt)" psql -w -h 127.0.0.1 -p 6432 -U sovca_app -d sovca_demo \
    -c "SELECT current_database(), count(*) FROM mplanes;"
```

Si además querés que el demo pase por PgBouncer, agregá la base al pooler:

```bash
bash scripts/pgbouncer_preparar_usuario.sh --base=sovca,sovca_demo --aplicar
```
