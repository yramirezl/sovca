# Reinstalar SOVCA desde cero en producción

Sustituye al despliegue incremental (`runbook_produccion_sovca_app.md`) para
esta puesta en marcha: SOVCA todavía no está en explotación, así que en vez de
traer los cambios con `git pull` se reemplaza el directorio por un clon limpio.

**La base de datos no se toca.** Sigue siendo `sovca` en el mismo PostgreSQL;
solo cambia el usuario con el que se conecta la aplicación (`sovca_app`).

## Qué trae el clon y qué no

| Pieza | ¿En el repositorio? | Acción |
|---|---|---|
| Código, `php-activerecord`, `vendor`, `plugins`, `bootstrap` | Sí | Nada |
| `lib/fpdf` (reportes PDF) | **Sí, desde el commit `07cf6c4`** | Nada |
| `config/db.php`, `config/mail.php`, `config/db.local.example.php` | Sí | Nada |
| `config/db.local.php` (credenciales del servidor) | No (`.gitignore`) | Crear en el paso 6 |
| `file/` (fotos subidas, correos, estado de mantenimiento) | No | **Restaurar** del respaldo |
| `dist/img/fotos_animales/` (fotos de animales) | No | **Restaurar** del respaldo |

---

## Paso 1 · Respaldo

```bash
FECHA=$(date +%F-%H%M)
APP=/var/www/html/sovca
BK=/root/sovca-$FECHA
mkdir -p "$BK"

# Lo que git NO trae
tar czf "$BK/no-versionado.tgz" -C "$APP" \
    file dist/img/fotos_animales config/db.local.php 2>/dev/null || true

# Respaldo completo (por si hay que volver atrás)
tar czf "$BK/sitio-completo.tgz" -C /var/www/html sovca

# Base de datos (no se modifica, pero mejor tenerla)
sudo -u postgres pg_dump -Fc sovca > "$BK/sovca.dump"

ls -lh "$BK"
echo "Respaldo en $BK"
```

Comprobar que el tar de lo no versionado tiene contenido real:

```bash
tar tzf "$BK/no-versionado.tgz" | grep -vE "index\.php|\.gitignore|/$" | head
```

---

## Paso 2 · Clonar en paralelo (el sitio sigue arriba)

```bash
cd /var/www/html
git clone https://github.com/yramirezl/sovca.git sovca.nuevo

cd sovca.nuevo
git log --oneline -1        # 07cf6c4 o posterior
ls lib/fpdf | head -3       # changelog.htm composer.json doc
```

Si el repositorio pide credenciales, el servidor debe tenerlas guardadas (las
mismas que ya usa para los `git pull` de BOVS).

---

## Paso 3 · Restaurar lo que no está en git

```bash
tar xzf "$BK/no-versionado.tgz" -C /var/www/html/sovca.nuevo
ls /var/www/html/sovca.nuevo/file
ls /var/www/html/sovca.nuevo/dist/img/fotos_animales | head -3
```

---

## Paso 4 · Rol `sovca_app` (igual que `bovs_app`)

```bash
cd /var/www/html/sovca.nuevo

# Variables del paso 1 (si abriste otra terminal, redefinilas)
BK=$(ls -d /root/sovca-* | tail -1)
echo "Respaldo: $BK"

# Clave en hexadecimal: sin '/', '+', '=' ni comillas, para que se pueda pegar
# en psql, en config/db.local.php y en cualquier script sin escaparla.
CLAVE_APP=$(openssl rand -hex 24)
printf '%s\n' "$CLAVE_APP" > "$BK/sovca_app.txt"
chmod 600 "$BK/sovca_app.txt"
echo "Clave de sovca_app: $CLAVE_APP"

SOVCA_APP_PASS="$CLAVE_APP" bash scripts/crear_usuario_sovca_app.sh
```

El script se conecta como superusuario por el **socket local**
(`sudo -u postgres psql`, autenticación `peer`) y no necesita contraseña. Si en
el servidor preferís TCP, pasadle la clave:

```bash
PGPASSWORD_ADMIN='la-clave-de-postgres' SOVCA_APP_PASS="$CLAVE_APP" \
    bash scripts/crear_usuario_sovca_app.sh
```

> Si aparece `password authentication failed for user "postgres"`, el script
> está intentando TCP sin la clave correcta: o se le pasa `PGPASSWORD_ADMIN`,
> o se ejecuta como root para que use el socket local.

Salida esperada:

```
· Verificando rol sovca_app...
  no existe: se crea
· Aplicando permisos en sovca
GRANT
GRANT
...
Listo. Rol sovca_app creado/actualizado.
```

Si en lugar de eso dice `· sovca no existe: se omite`, el script está
apuntando a otro PostgreSQL: revisá `PGHOST`, `PGPORT` y `PGADMIN`.

El script hace lo mismo que se hizo con `bovs_app`:

- crea el rol `sovca_app` con `LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE`;
- `GRANT CONNECT, TEMPORARY` sobre `sovca` y `sovca_demo`;
- `USAGE` del esquema `public`;
- `SELECT/INSERT/UPDATE/DELETE` sobre **todas** las tablas existentes;
- `USAGE, SELECT` sobre todas las secuencias;
- `EXECUTE` sobre las funciones;
- `ALTER DEFAULT PRIVILEGES` para las tablas, secuencias y funciones futuras.

Verificación directa al `5432` (sin PgBouncer todavía):

```bash
PGPASSWORD="$CLAVE_APP" psql -h 127.0.0.1 -p 5432 -U sovca_app -d sovca \
    -c "SELECT current_user, count(*) AS animales FROM manimales;"
```

Esperado: `sovca_app | 216` (o el total real de animales).

Si responde `password authentication failed`, la clave no llegó al script:
comprobá que `SOVCA_APP_PASS` no esté vacía y volvé a ejecutarlo (es
idempotente, actualiza la clave del rol).

Si responde `permission denied for table manimales`, ejecutá de nuevo
`scripts/crear_usuario_sovca_app.sh`: ese error significa que el rol se creó
pero los `GRANT` no se aplicaron.

---

## Paso 5 · PgBouncer

```bash
cd /var/www/html/sovca.nuevo
bash scripts/pgbouncer_preparar_usuario.sh              # simulación
bash scripts/pgbouncer_preparar_usuario.sh --aplicar
```

Deja `userlist.txt` con el secreto real del rol, agrega `sovca` a
`[databases]` si falta, respalda ambos archivos y recarga el servicio.

Si se para por **md5/SCRAM**:

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

---

## Paso 6 · `config/db.local.php`

```bash
cd /var/www/html/sovca.nuevo
cp config/db.local.example.php config/db.local.php
nano config/db.local.php
```

```php
define('SOVCA_DB_HOST', '127.0.0.1');
define('SOVCA_DB_PORT', '6432');       // PgBouncer
define('SOVCA_DB_NAME', 'sovca');
define('SOVCA_DB_USER', 'sovca_app');
define('SOVCA_DB_PASS', 'la-clave-generada-en-el-paso-4');
```

Comprobación con el propio código, antes de publicar nada:

```bash
php scripts/diagnostico_conexiones.php
```

Debe mostrar `sovca · sovca_app` y un veredicto saludable.

---

## Paso 7 · Permisos

```bash
APP=/var/www/html/sovca.nuevo

# Código: dueño root, lectura para Apache
chown -R root:www-data "$APP"
find "$APP" -type d -exec chmod 775 {} \;
find "$APP" -type f -exec chmod 664 {} \;

# Donde la aplicación escribe
chown -R www-data:www-data "$APP/file" "$APP/dist/img/fotos_animales"

# Credenciales: legibles por Apache, no por el resto
chown root:www-data "$APP/config/db.local.php"
chmod 640 "$APP/config/db.local.php"
```

---

## Paso 8 · Intercambio (segundos de corte)

```bash
cd /var/www/html
mv sovca sovca.viejo
mv sovca.nuevo sovca
ls -ld sovca sovca.viejo
```

---

## Paso 9 · Migraciones (como `postgres`)

`sovca_app` no es dueño de las tablas, así que los DDL se ejecutan ignorando
`db.local.php`:

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

Todos son simulados por defecto. Leer la salida de cada uno antes de seguir.

---

## Paso 10 · Servicios y verificación

```bash
systemctl reload php8.4-fpm     # o el servicio que devuelva: systemctl list-units | grep php
systemctl reload pgbouncer

sudo -u www-data php /var/www/html/sovca/scripts/diagnostico_conexiones.php
```

En el navegador:

1. Login → 200, sin errores de conexión.
2. `verificar_despliegue.php?k=sovca-verif-2026` → todo en verde.
3. Animales: listado, alta con lote y sin lote.
4. Nacimientos: alta sin arete y sin servicio gestante.
5. Reportes: un PDF de animales activos (usa `lib/fpdf`).
6. Planes (rol 4): crear/editar y ver el cupo en la ficha del usuario.
7. Conexiones: cierre remoto de una sesión con cuenta atrás.
8. Desde el móvil: el login ofrece «Instalar app».

---

## Paso 11 · Limpieza

Cuando todo esté verificado:

```bash
# Guardar una semana y luego borrar
mv /var/www/html/sovca.viejo /root/sovca.viejo-$(date +%F)
```

---

## Rollback

Mientras `sovca.viejo` exista:

```bash
cd /var/www/html
mv sovca sovca.fallido
mv sovca.viejo sovca
systemctl reload php8.4-fpm
```

La base no se modificó, así que el código anterior funciona igual. Las
migraciones son aditivas: el código viejo las ignora.

Rollback solo de la conexión, sin tocar código: en `config/db.local.php`
volver a `5432` / `postgres` / la clave anterior.

---

## Diferencias con `bovs_app`

Ninguna funcional: el script es el mismo con otro nombre de rol y de base. Si
mañana hay que repetirlo para otra instalación, basta cambiar `APP_USER`,
`APP_PASS` y la lista de bases dentro del script.
