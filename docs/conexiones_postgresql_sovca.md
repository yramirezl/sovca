# Conexión a PostgreSQL en SOVCA

Todo el sistema lee las credenciales de **un solo sitio**: `config/db.php`.

## Cómo se resuelve cada valor

1. Las constantes que ya estén definidas (normalmente por `config/db.php`).
2. `config/db.local.php`, si existe (no se sube al repositorio).
3. La variable de entorno (`SOVCA_DB_HOST`, `SOVCA_DB_PORT`, `SOVCA_DB_NAME`,
   `SOVCA_DB_DEMO_NAME`, `SOVCA_DB_USER`, `SOVCA_DB_PASS`).
4. El valor por defecto de desarrollo.

Solo hay dos bases de negocio: **`sovca`** y **`sovca_demo`**. El nombre
`sovca_qas` se resuelve a `sovca` para que ningún módulo quede apuntando a una
base inexistente.

## Qué lee de aquí

| Capa | Archivo | Cómo |
|---|---|---|
| Aplicación (modelos, controladores) | `config/conexion.php` | `sovca_db_dsn_ar()` |
| Alertas por correo | `alertas/conexion.php` | `sovca_db_config()` + conexión compartida |
| Reportes (ADOdb) | `Reportes/lib/bd/basedatosAdoIbase.php` | `sovca_db_config()` + conexión compartida |
| API / PWA | `api/config/bootstrap.php` | vía `config/conexion.php` (sin sesión PHP) |
| Scripts de mantenimiento | `scripts/*.php` | `sovca_db_config()` |

## Configuración del servidor

```bash
cp config/db.local.example.php config/db.local.php
nano config/db.local.php
```

`config/db.local.php` está en `.gitignore`: un `git pull` nunca lo pisa.

En producción conviene que la aplicación use un rol propio y no el
superusuario:

```bash
SOVCA_APP_PASS='la-clave-real' bash scripts/crear_usuario_sovca_app.sh
```

y luego, en `db.local.php`:

```php
define('SOVCA_DB_USER', 'sovca_app');
define('SOVCA_DB_PASS', 'la-clave-real');
```

Con PgBouncer por delante, el puerto suele ser **6432** en vez de 5432.

## Scripts que necesitan ser dueños de las tablas

`sovca_app` puede leer y escribir datos, pero **no** puede hacer DDL (`ALTER
TABLE`, `CREATE INDEX`, `CREATE OR REPLACE VIEW`) porque no es dueño de los
objetos. Para esos scripts, se ignora `db.local.php` en esa ejecución con
`SOVCA_DB_IGNORAR_LOCAL=1`:

```bash
SOVCA_DB_IGNORAR_LOCAL=1 SOVCA_DB_USER=postgres SOVCA_DB_PASS='…' \
    php scripts/optimizar_indices_reportes.php --aplicar --analizar

SOVCA_DB_IGNORAR_LOCAL=1 SOVCA_DB_USER=postgres SOVCA_DB_PASS='…' \
    php scripts/actualizar_planes_fundos.php --aplicar

SOVCA_DB_IGNORAR_LOCAL=1 SOVCA_DB_USER=postgres SOVCA_DB_PASS='…' \
    php scripts/agregar_cierre_remoto_sesiones.php --aplicar

SOVCA_DB_IGNORAR_LOCAL=1 SOVCA_DB_USER=postgres SOVCA_DB_PASS='…' \
    php scripts/corregir_fk_servicio_nacimiento.php --aplicar

SOVCA_DB_IGNORAR_LOCAL=1 SOVCA_DB_USER=postgres SOVCA_DB_PASS='…' \
    php scripts/actualizar_vista_animales_activos.php
```

La alternativa es transferir la propiedad de los objetos al rol de la
aplicación, algo que solo conviene hacer al instalar el servidor:

```sql
ALTER VIEW  public.v_list_animales_activos OWNER TO sovca_app;
-- … y así con cada tabla/índice/secuencia que administre la aplicación
```

## Comprobación rápida

```bash
php scripts/diagnostico_conexiones.php
```

Muestra el servidor, la base, el usuario, las conexiones abiertas y un
veredicto de saturación (útil antes de tocar `max_connections`).
