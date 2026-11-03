# Port de mejoras de BOVS a SOVCA (2026-11-02)

Este documento resume los cambios que se trajeron desde BOVS (commits del
28-10-2026 al 02-11-2026) y que dejan a SOVCA a la par en funcionalidad,
conservando su enfoque de **ganadería ovina y caprina**.

> BOVS no se modificó: todo se leyó desde `/var/www/html/bovs` y se aplicó en
> `/var/www/html/sovca` y sus bases `sovca` y `sovca_demo`.

## 1. Base de datos

Migraciones aplicadas en **`sovca`** y **`sovca_demo`** (todas idempotentes):

| Script | Qué deja |
|---|---|
| `scripts/migracion_sync_offline_log.sql` | Tabla `sync_offline_log` (una fila por pull/push/foto) e índices. |
| `scripts/migracion_alerta_palpacion.sql` | Vista `v_palpacion_alertas` corregida (motivo siempre informado, solo ciclos abiertos, 7 días). |
| `scripts/migracion_vmregnacimientos_tipo_servicio.sql` / `migracion_vmregnacimientos_sin_duplicados.sql` | Vista `vmregnacimientos` con LEFT JOIN y `LATERAL … LIMIT 1`. Conserva las columnas propias de SOVCA (`lactancia`, `raza`). |
| `scripts/migracion_edad_minima_macho.sql` | Período `EMM` (365 días) para ovinos y caprinos. |
| `scripts/migracion_correlativos_contador.sql` | Contadores de animales, nacimientos y servicios alineados con los códigos ya usados. |
| `scripts/migracion_servicios_contador.sql` | Mismo ajuste para `codigo_servicio`. |
| `scripts/migracion_conexiones_consolidar.sql` | Une las filas duplicadas de una misma sesión en `conexiones_historial`. |
| `scripts/migracion_nacimientos_macho_codigo.sql` | Corrige padres guardados como N° de arete o texto libre. |
| `scripts/migracion_categorias_normalizar.sql` | Cadenas de edades sin cruces ni nombres repetidos (no borra filas). |
| `scripts/migracion_arete_unico_fundo.sql` | Índice único `manimales_fundo_arete_normalizado_unico_idx`. |

Además se normalizaron los aretes existentes con
`php scripts/normalizar_aretes.php --aplicar` (2 cambios en `sovca`).

> En otro servidor, los DDL se ejecutan como dueño del esquema (`postgres`) y
> después se conceden permisos al rol de la aplicación (`sovca_app`). El
> permiso de `sync_offline_log` está documentado dentro de su migración.

## 2. Reglas de negocio

- **N° de arete único por fundo** (`lib/arete_helper.php` + `js/arete-formato.js`):
  formato canónico (mayúsculas, símbolos a guion, máximo 15), comparación sin
  distinguir guiones/espacios y validación en web, importación, migración entre
  fundos, ficha de la cría y API.
- **Categorías por cadena de edades** (`lib/categoria_rangos_helper.php` +
  `js/categoria-rangos.js`): la categoría futura empieza donde termina la de
  ingreso, con la misma especie, sexo y ganadería; el productor puede corregir
  las suyas.
- **Edad mínima del macho** (`lib/edad_macho_helper.php`, período `EMM`):
  no se puede elegir un cordero de 6 meses como carnero de monta.
- **Padre del nacimiento** (`lib/macho_resolver_helper.php`): convierte el N° de
  arete al código real y avisa cuando no corresponde a un macho del fundo.
- **Nacimientos sin servicio gestante**: se puede registrar el parto indicando
  el tipo de servicio; la fecha probable de parto y el secado se calculan solos.
- **Servicios**: el listado de hembras muestra solo las disponibles y se avisa
  la fecha probable de palpación.
- **Cuota de fundos contratada**: el plan fija el tope y la cuenta indica cuántos
  fundos contrató (`mf_normalizar_cupo_fundos`).

## 3. Web

- Nuevo **panel de Inicio** (`views/inicio/blank.php` + `css/bovs-inicio.css`):
  hero con imágenes de ovinos o caprinos, saludo, accesos rápidos por rol y
  avisos. El administrador abre el submenú **Estadísticas** (`views/estadisticas/`).
- **Panel lateral reutilizable** (`js/bovs-drawer.js` + `css/bovs-drawer.css`):
  perfil de usuario y ficha del animal sin salir del listado.
- **Consulta en panel lateral** y **ficha a casi pantalla completa**.
- Módulo **Dispositivos → Sincronizaciones**: resumen por equipo, fallos y
  detalle del último rechazo (alimentado por `sync_offline_log`).
- **Conexiones**: una fila por conexión, duración acumulada y estados con hora.
- **Correo**: configuración SMTP por entorno, HELO configurable, diagnóstico
  (`scripts/diagnostico_correo.php`) y prueba sin enviar
  (`scripts/probar_envio_correo.php`).
- **Demo**: un solo acceso desde el login; la pantalla permite crear la cuenta
  o ingresar con una existente.
- **Modo offline**: el login muestra un único acceso al módulo; el asistente de
  activación se abre con `?activar=1`.

## 4. Modo offline (PWA)

- Versión **V1.00046** (`app_offline/versiones.json`) y caché
  **`sovca-shell-v4`**. Se adopta el esquema V1.nnnnn para no confundir la app
  de campo con la versión web.
- Nueva **portada** `app_offline/inicio.html` (+ `css/inicio.css`,
  `js/inicio.js`, `js/acceso-modo-offline.js`) con atajo `/sovca/offline`.
- **Inicio** con accesos a todos los módulos y lista de pendientes/avisos.
- **Aretes** normalizados también en el equipo y buscador con o sin guiones.
- **Nacimientos**: reglas de gestación del servidor, fecha probable de parto y
  secado automático.
- **Reintentos con espera progresiva** (1, 2, 4, 8, 16 y 30 min), bloqueo tras
  6 rechazos, chip «N para revisar» y descarga de registros atascados.
- Los registros sobre un animal nuevo **esperan a que suba el alta** y luego se
  envían solos.

## 5. Infraestructura y scripts

- `scripts/desplegar.sh` (despliegue idempotente, permisos y verificación).
- `scripts/cron/` (respaldo, alertas, purga de `sync_offline_log`) y
  `scripts/envio_local/` (envío alternativo por túnel).
- `scripts/demo_actualizar_esquema.php`, `scripts/demo_reasignar_duenio.sql`.
- `scripts/normalizar_aretes.php`, `scripts/auditar_aretes_duplicados.php`,
  `scripts/corregir_tipo_servicio_nacimientos.php`,
  `scripts/normalizar_planes_fundos.php`.
- Se retiró `lib/PHPExcel` (9,2 MB) y `excel.php` / `controllers/ctrl_excel.php`.
  La importación de contactos usa **PhpSpreadsheet** (`vendor/autoload.php`).

## 6. Variables de entorno nuevas

Todas son opcionales; si faltan se usa el valor de `config/mail.php` o el
comportamiento histórico:

```
SOVCA_SMTP_HOST, SOVCA_SMTP_PORT, SOVCA_SMTP_SECURE, SOVCA_SMTP_USER,
SOVCA_SMTP_PASS, SOVCA_SMTP_HELO, SOVCA_MAIL_FROM, SOVCA_MAIL_SENDER,
SOVCA_MAIL_REPLY_TO, SOVCA_MAIL_FROM_NAME, SOVCA_MAIL_DOMAIN,
SOVCA_BACKUP_EMAIL, SOVCA_BACKUP_BASE, SOVCA_BACKUP_DIR,
SOVCA_BACKUP_RETENCION, SOVCA_SYNC_LOG_BASE, SOVCA_SYNC_LOG_RETENCION
```

La clave de la firma digital y la URL pública pueden vivir en
`lib/firma.local.php` (plantilla en `lib/firma.local.example.php`).

## 7. Verificación realizada

- `php -l` en todos los PHP modificados: **0 errores**.
- `node --check` en todo el JavaScript modificado y en el JS embebido de los
  formularios renderizados: **0 errores**.
- `bash -n` en los scripts de shell: **0 errores**.
- Renderizado con sesión simulada de: inicio, animales, nacimientos, categorías,
  servicios, usuarios, conexiones, alertas, estadísticas, dispositivos,
  producción de leche y dashboard.
- Endpoints probados: `data_mcategorias`, `data_mnacimientos`,
  `data_mconexiones`, `resumen_sync`, `search_nro_arete`, `validar_rangos`,
  `listar_candidatos_servicio`, `buscar_servicios_gestantes`.
- `api/test/validar_conexiones.php`: **14/14**.
- `app_offline/test/verificar_espera_y_reintentos.js`: **20/20**.
- `scripts/probar_envio_correo.php`: conexión, EHLO, autenticación y
  MAIL FROM/RCPT TO correctos (sin enviar mensaje).
- Atajo `/sovca/offline` → 302 a `app_offline/inicio.html?inicio=1` y
  `/sovca/.git/config` → 404.

## 8. Rollback

- Código: `git reset --hard respaldo-pre-port-20261102` (rama creada antes del
  port).
- Base de datos: restaurar los dumps
  `/tmp/sovca_pre_port_20261102.dump` y
  `/tmp/sovca_demo_pre_port_20261102.dump` con `pg_restore`.

> Después del port, `versiones.json` y `service-worker.js` deben subir juntos
> para que los equipos con caché vieja descarguen la PWA nueva.
