# Despliegue en producción · SOVCA (2026-10-28)

Cubre las fases 1-7 de paridad con BOVS, la configuración única de PostgreSQL,
el rol `sovca_app` y el aviso de instalación de la app.

> **Regla de oro:** primero se deja lista la conexión (rol + `config/db.local.php`
> + PgBouncer) y **después** se trae el código. Al revés se queda sin base.

---

## 0. Antes de empezar

```bash
cd /var/www/html/sovca

# ¿Con qué credenciales está funcionando HOY? (se van a reemplazar)
grep -n "pgsql://" config/conexion.php alertas/conexion.php \
     Reportes/lib/bd/basedatosAdoIbase.php

# ¿Existe la base de QA en producción? (los reportes apuntan a ella hoy)
sudo -u postgres psql -Atc "SELECT datname FROM pg_database ORDER BY 1;"

# Estado del pooler y de PHP
sudo systemctl is-active postgresql pgbouncer php8.4-fpm
pgbouncer -V

# Respaldo del código y de la carpeta de configuración
tar czf /root/sovca-antes-$(date +%F).tgz \
    -C /var/www/html sovca/config sovca/alertas/conexion.php
```

Anotá la **base** y el **usuario** que usa hoy la aplicación: serán los valores
de `config/db.local.php` en el paso 2 si algo no cuadra.

Ventana recomendada: fuera de horario, con el modo mantenimiento a mano:

```bash
php scripts/mantenimiento.php --on --motivo="Despliegue SOVCA" --minutos=60
```

---

## 1. Rol de la aplicación en PostgreSQL

```bash
cd /var/www/html/sovca

# Se baja el código solo de los scripts (el resto del pull se hace en el paso 4)
git fetch origin
git checkout origin/main -- scripts/crear_usuario_sovca_app.sh

SOVCA_APP_PASS='la-clave-real' bash scripts/crear_usuario_sovca_app.sh
```

Comprobación:

```bash
sudo -u postgres psql -Atc \
  "SELECT rolname, rolcanlogin, rolsuper FROM pg_roles WHERE rolname='sovca_app';"
```

Debe salir `sovca_app|t|f`.

> El rol **no** es dueño de las tablas: puede leer y escribir datos, pero no
> hacer DDL. Para eso se usa `postgres` en el paso 5.

---

## 2. PgBouncer

Esta es la parte que hay que dejar bien para que no falle. El script lee el
`auth_type`, saca el secreto real del rol desde PostgreSQL y escribe la línea
correcta en `userlist.txt`:

```bash
# Simulación (no toca nada)
sudo bash scripts/pgbouncer_preparar_usuario.sh

# Aplicar
sudo bash scripts/pgbouncer_preparar_usuario.sh --aplicar
```

Qué hace exactamente:

| Paso | Detalle |
|---|---|
| `userlist.txt` | Escribe `"sovca_app" "SCRAM-SHA-256$…"` copiando el secreto de `pg_authid`. Respeta el `auth_file` que declare el ini. |
| `[databases]` | Si la base no está ruteada y no hay comodín `*`, agrega `sovca = host=127.0.0.1 port=5432`. |
| Respaldos | `userlist.txt.bak-AAAAMMDD-HHMMSS` y `pgbouncer.ini.bak-…`. |
| Recarga | `systemctl reload pgbouncer`. |
| Verificación | `psql -p 6432 -U sovca_app -d sovca -c 'select 1'`. |

**Si el script se detiene por incompatibilidad de cifrado** significa que
`pgbouncer.ini` tiene `auth_type = md5` y PostgreSQL guarda SCRAM. La salida
explica las dos salidas; la recomendada es:

```bash
sudo sed -i 's/^[[:space:]]*auth_type[[:space:]]*=.*/auth_type = scram-sha-256/' \
     /etc/pgbouncer/pgbouncer.ini
sudo systemctl reload pgbouncer
```

Prueba manual, sin la aplicación de por medio:

```bash
PGPASSWORD='la-clave-real' psql -h 127.0.0.1 -p 6432 -U sovca_app -d sovca \
    -c "SELECT current_database(), current_user;"
PGPASSWORD='la-clave-real' psql -h 127.0.0.1 -p 6432 -U sovca_app -d sovca \
    -c "SELECT count(*) FROM manimales;"
```

Si la primera funciona y la segunda da `permission denied`, faltan permisos →
repetir el paso 1 (el script los concede a todas las tablas y secuencias).

### Capacidad

SOVCA comparte PostgreSQL con BOVS. Con `pool_mode = transaction` y
`max_connections = 100` en PostgreSQL, conviene revisar:

```bash
sudo grep -E "pool_mode|max_client_conn|default_pool_size|max_db_connections" \
     /etc/pgbouncer/pgbouncer.ini
php /var/www/html/bovs/scripts/diagnostico_conexiones.php
```

Orientativo: `default_pool_size` 20 y `max_db_connections` 30 por base dejan
margen para las dos aplicaciones.

---

## 3. Configuración de la conexión en el servidor

**Antes del `git pull`**, porque `config/conexion.php` se reemplaza:

```bash
cd /var/www/html/sovca
sudo -u www-data cp config/db.local.example.php config/db.local.php
sudo -u www-data nano config/db.local.php
```

```php
define('SOVCA_DB_HOST', '127.0.0.1');
define('SOVCA_DB_PORT', '6432');      // PgBouncer
define('SOVCA_DB_NAME', 'sovca');
define('SOVCA_DB_USER', 'sovca_app');
define('SOVCA_DB_PASS', 'la-clave-real');
```

```bash
sudo chown www-data:www-data config/db.local.php
sudo chmod 640 config/db.local.php
```

`config/db.local.php` está en `.gitignore`: un `git pull` nunca lo pisa.

---

## 4. Código

```bash
cd /var/www/html/sovca
sudo -u www-data git pull origin main
sudo -u www-data php -l config/conexion.php && sudo -u www-data php -l config/db.php
```

Si el sistema estaba en mantenimiento, aún no lo quites: primero los datos.

---

## 5. Base de datos (DDL, siempre como `postgres`)

```bash
cd /var/www/html/sovca
export SOVCA_DB_IGNORAR_LOCAL=1 SOVCA_DB_USER=postgres SOVCA_DB_PASS='…'
```

```bash
# 5.1 Planes: columna max_fundos, cupos y plan PLATINO +
php scripts/actualizar_planes_fundos.php                 # mirar la salida
php scripts/actualizar_planes_fundos.php --aplicar

# 5.2 Cierre remoto de sesiones
php scripts/agregar_cierre_remoto_sesiones.php
php scripts/agregar_cierre_remoto_sesiones.php --aplicar

# 5.3 Índices de reportes
php scripts/optimizar_indices_reportes.php
php scripts/optimizar_indices_reportes.php --aplicar --analizar

# 5.4 Vínculo del servicio en nacimientos
php scripts/corregir_fk_servicio_nacimiento.php
php scripts/corregir_fk_servicio_nacimiento.php --aplicar

# 5.5 Vista de animales activos (criterio único)
php scripts/actualizar_vista_animales_activos.php --dry-run
php scripts/actualizar_vista_animales_activos.php
```

```bash
unset SOVCA_DB_IGNORAR_LOCAL SOVCA_DB_USER SOVCA_DB_PASS
```

> Todos los scripts son simulados por defecto y piden `--aplicar`. Ejecutalos
> de a uno y leé la salida antes de continuar.

---

## 6. Caché y servicios

```bash
sudo systemctl reload php8.4-fpm     # limpia OPcache
sudo systemctl reload pgbouncer
```

Si el despliegue usó el modo mantenimiento, apagalo:

```bash
php scripts/mantenimiento.php --off --nota="Despliegue completado"
```

---

## 7. Verificación

```bash
cd /var/www/html/sovca

# Conexión por el pooler con el rol de la aplicación
php scripts/diagnostico_conexiones.php

# Comprobación integral (token del archivo)
#   https://TU-DOMINIO/sovca/verificar_despliegue.php?k=sovca-verif-2026
#   Con &reset=1 limpia OPcache antes de medir.

# Reportes: una sola conexión por request
sudo -u www-data php -r '
require "/var/www/html/sovca/Reportes/lib/bd/basedatosAdoIbase.php";
$b = new basedatosAdoIbase();
$rs = $b->select("SELECT count(*) AS n FROM manimales");
print_r($rs->FetchRow());
print_r(basedatosAdoIbase::estadisticas());
'
```

En el navegador:

1. Login → 200 y sin avisos de conexión.
2. **Animales**: listado y alta (con y sin lote).
3. **Nacimientos**: alta sin arete y sin servicio gestante.
4. **Reportes**: PDF de animales activos y de producción.
5. **Planes** (usuario rol 4): crear/editar y ver el cupo en la ficha de usuario.
6. **Conexiones**: botón de cierre de sesión → aparece la cuenta atrás.
7. **Inicio en el móvil**: debe ofrecer «Instalar app».

Comparación de cifras (la vista y la aplicación deben coincidir):

```sql
SELECT count(*) FROM v_list_animales_activos WHERE mfundos_id = 5;
```

---

## 8. Rollback

```bash
cd /var/www/html/sovca

# Código
sudo -u www-data git reset --hard 4b772d2     # último commit estable

# Configuración (si el respaldo del paso 0 tiene config/conexion.php)
tar xzf /root/sovca-antes-AAAA-MM-DD.tgz -C /var/www/html

# PgBouncer
sudo cp /etc/pgbouncer/pgbouncer.ini.bak-AAAAMMDD-HHMMSS /etc/pgbouncer/pgbouncer.ini
sudo cp /etc/pgbouncer/userlist.txt.bak-AAAAMMDD-HHMMSS /etc/pgbouncer/userlist.txt
sudo systemctl reload pgbouncer

sudo systemctl reload php8.4-fpm
```

Las migraciones aplicadas son **aditivas** (columnas nuevas, índices, una
vista). Volver al código anterior no requiere deshacerlas: el código viejo las
ignora. `actualizar_planes_fundos.php` es el único que modifica datos
existentes (`mplanes`), y solo con `--forzar`.

---

## 9. Avisos para los usuarios

- **Las cifras del hato bajan.** Ahora cuentan solo los animales activos: no
  entran vendidos, muertos, descartados, extraviados ni donadores sin
  habilitar. Es el mismo criterio en el menú, el listado, la ficha del fundo y
  el cupo del plan.
- **El administrador puede cerrar sesiones** desde *Conexiones*; el usuario ve
  un aviso con 60 segundos para guardar.
- **La app se puede instalar** en el teléfono desde el login (Android e iOS).
