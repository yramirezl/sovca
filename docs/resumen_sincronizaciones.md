# Resumen de sincronizaciones del modo offline

Pantalla del módulo **Dispositivos Conectados** que muestra qué hicieron los
equipos offline: cuántas veces sincronizaron, cuántos registros subieron y
**dónde falló** cada envío rechazado.

- Pantalla: `views/dispositivos/sincronizaciones.php` (botón *Resumen de sync*
  en `views/dispositivos/index.php`).
- Acciones del controlador: `resumen_sync` y `detalle_sync` en
  `controllers/mdispositivos_controller.php`.
- Tabla: `sync_offline_log` (`scripts/migracion_sync_offline_log.sql`).
- Registrador: `api/lib/SyncLogger.php`.

## Cómo se llena

`api/sync_controller.php` abre el registrador antes de atender la acción:

```php
require_once __DIR__ . '/lib/SyncLogger.php';
\Api\SyncLogger::iniciar($input, $usuario);
```

y la fila se escribe en el **shutdown** de PHP. Así queda registrado también lo
que corta la respuesta con `ApiResponse::error()` (403 dispositivo
desvinculado, 422 módulo inválido, 403 sin acceso offline, 500 excepción), sin
tocar el flujo normal del API.

| Acción      | Cuándo se registra                                        |
|-------------|-----------------------------------------------------------|
| `push`      | Siempre (son las operaciones del productor).              |
| `pull`      | Cuando trae registros o cuando falla. Los pull vacíos se omiten. |
| `subir_foto`| Al subir la foto de un animal.                            |

Por cada evento se guarda: dispositivo (uuid + nombre), usuario, fundo, modo
(`gestion` / `consulta`), módulo, resultado (`ok` / `parcial` / `error`),
totales (`total`, `exitos`, `conflictos`, `errores`), duración, IP, mensaje
resumen y `detalle` (jsonb) con la lista de fallos:

```json
[{"tipo":"error","modulo":"animales","referencia":"Animales · SOVCA-PEND-001",
  "uuid_local":"…","mensaje":"Falta un dato obligatorio («nro_arete»)…","codigo":""}]
```

`resultado`:

- `ok` — todo entró.
- `parcial` — hubo conflictos o registros rechazados (el lote siguió).
- `error` — la petición entera falló (auth, vínculo, mantenimiento, 500).

## Qué muestra la pantalla

1. **KPIs** del período (7 / 30 / 90 días o todo): sincronizaciones, equipos con
   actividad, sincronizaciones con fallos, registros rechazados y conflictos.
2. **Tabla por equipo**: dispositivo, usuario y fundos, modo, número de
   sincronizaciones, fallos (con el detalle de rechazados/conflictos), y la
   columna *Último fallo · dónde falló* (fecha + módulo + referencia + motivo).
3. **Modal de detalle**: línea de tiempo de las últimas 300 peticiones del
   equipo, con contadores, duración y la lista de registros rechazados.

Filtro opcional *Solo equipos con fallos*.

## Permisos

- Roles 1 y 4 (administrador / super usuario): todos los fundos.
- Rol 2 (productor): solo las filas de su fundo (`mfundos_id` de la sesión).
- Rol 3 (ayudante): sin acceso, igual que el resto del módulo.

## Operación

- La tabla crece con cada push. La purga la hace
  **`scripts/cron/purgar_sync_log.sh`** (cron diario `30 3 * * *`):

  ```bash
  # cuántas filas se borrarían (no toca nada)
  scripts/cron/purgar_sync_log.sh --dry-run

  # purga real (retención por defecto: 180 días)
  SOVCA_SYNC_LOG_RETENCION=180 scripts/cron/purgar_sync_log.sh
  ```

  Escribe en `/opt/logs/sync_log.log` cuántas filas borró. Si la tabla no
  existe, termina con salida 1 para que el cron avise. Instalación y crontab:
  `scripts/cron/README.md`.

- La base de demostración recibe la tabla con
  `php scripts/demo_actualizar_esquema.php --aplicar` o aplicando el propio
  `scripts/migracion_sync_offline_log.sql` sobre `sovca_demo`.

## Pruebas realizadas

- `php -l` de los cinco archivos PHP tocados y `node --check` del JS de la vista.
- Registrador probado con push parcial (con detalle de rechazo y conflicto),
  pull vacío (no registra), 403 de dispositivo desvinculado (queda como
  `error`/`peticion`) y pull sin `dispositivo_id` (queda con uuid null).
- API real con token temporal: `pull` con datos → fila `ok` con 17 registros;
  módulo inválido → fila `error` con código `http_422`.
- Pantalla verificada en navegador con un servidor PHP temporal y una sesión de
  prueba (pantalla completa + modal de detalle).
