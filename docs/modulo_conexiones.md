# Módulo de Conexiones

**Última revisión:** 2026-10-18

## 1. Qué hacía antes (y por qué no servía)

`mrastreosessiones` guardaba **una sola fila por usuario**: el login hacía un
UPSERT (si existía la fila la actualizaba, si no la insertaba). La vista
`vmdataconexiones` leía de ahí, así que el módulo solo podía mostrar **la última
conexión de cada usuario** y era imposible medir frecuencia de uso.

Además:

- `pais` guardaba `"País, Ciudad"` en un solo campo de texto (varchar 100).
- La geolocalización llamaba a `geoplugin.net` con `file_get_contents` **sin
  timeout, sin caché y sin respaldo**; si el servicio tardaba o fallaba, el
  login se degradaba.
- La IP se tomaba de `$_SERVER['REMOTE_ADDR']` sin considerar proxies.

## 2. Arquitectura actual

| Pieza | Archivo | Rol |
|---|---|---|
| Tabla del historial | `conexiones_historial` | Acumulativa: **una fila por inicio de sesión** |
| Caché de geolocalización | `geoip_cache` | Evita consultar el servicio externo en cada login |
| Migración | `scripts/migracion_conexiones_historial.sql` | Crea ambas tablas + importa lo que había en `mrastreosessiones` |
| Helper | `lib/geoip_helper.php` | IP real, geolocalización, dispositivo y registro |
| Endpoint historial | `data_json/data_mconexiones.php` | Una fila por acceso, ordenado por fecha DESC |
| Endpoint resumen | `data_json/data_mconexiones_resumen.php` | Una fila por usuario con métricas de frecuencia |
| Vista | `views/conexiones/index.php` | KPIs + dos pestañas (resumen / historial) |

### 2.1 Tabla `conexiones_historial`

```
id, musuarios_id, username, nombre, ip, pais, ciudad, region,
user_agent, origen, resultado, fecha (timestamptz)
```

- `username` y `nombre` son **copias** del momento del acceso: el historial
  sobrevive si el usuario se renombra o se elimina.
- `resultado`: `ok` (ingreso válido) o `fallo` (reservado para intentos
  rechazados, todavía no se registran).
- `origen`: `web`, `pwa`, `api`, `reset`.
- Índices: `(musuarios_id, fecha DESC)`, `(fecha DESC)`, `(ip)`.

### 2.2 Helper `lib/geoip_helper.php`

| Función | Para qué |
|---|---|
| `bovs_ip_cliente()` | IP real; usa `X-Forwarded-For` solo si la conexión directa es privada |
| `bovs_ip_privada($ip)` | Detecta loopback, 10/8, 172.16/12, 192.168/16, 169.254/16, ULA y link-local IPv6 |
| `bovs_geo_localizar($ip)` | Devuelve `pais`, `ciudad`, `region`, `fuente` |
| `bovs_geo_etiqueta($pais, $ciudad)` | Arma `"País, Ciudad"` evitando duplicados |
| `bovs_cx_dispositivo($ua)` | Etiqueta corta tipo `Android · Chrome` |
| `bovs_conexion_registrar($uid, $origen)` | Inserta en el historial + actualiza el rastreo legado |

Reglas de la geolocalización:

1. IP privada → `pais = 'Red local'`, sin consulta externa.
2. **Caché** en `geoip_cache`: un acierto dura 30 días; un fallo se reintenta
   a las 24 h. Así solo la primera conexión desde una IP nueva paga latencia.
3. Proveedor primario: `ip-api.com` (`country`, `city`, `regionName`, `lang=es`).
4. Respaldo: `geoplugin.net` (el que se usaba antes).
5. Timeout de 2 s por proveedor, `curl` si está disponible, si no
   `file_get_contents` con contexto de stream.
6. Si nada responde, se guarda `NULL` y **el login continúa**.

Todo el helper está envuelto en `try/catch` y nunca interrumpe el inicio de
sesión. Compatible PHP 7.4 / 8.3 (sin `??`, `?->`, `match`, `str_contains`).

### 2.3 Integración con el login

En `controllers/musuarios_controller.php` se reemplazaron **dos** bloques de
rastreo (uno en la ruta de selector multi-fundo y otro en la ruta normal) por:

```php
bovs_conexion_registrar($uid, 'web');
```

`bovs_conexion_registrar()` además **mantiene el UPSERT legado** en
`mrastreosessiones`, porque de esa tabla dependen `vmdataconexiones` y
`alertas/controlador_email_inactividad.php`.

Se eliminó el cálculo de IP/país al inicio del controlador: geolocalizar en
todas las acciones era innecesario y añadía latencia.

## 3. Métricas de la vista

| Métrica | Cálculo |
|---|---|
| **Conexiones** | `count(*)` de accesos en el periodo |
| **Días activos** | `count(DISTINCT día)` — días distintos con al menos un acceso |
| **Frecuencia** | `(último día − primer día) / (días activos − 1)` = cada cuántos días entra |
| **Días sin entrar** | hoy − último acceso |
| **Estado** | `Activo` ≤1 d · `Reciente` ≤7 d · `Ocasional` ≤30 d · `Inactivo` >30 d · `Sin actividad` |

El periodo es seleccionable: 7 / 30 / 90 / 365 días o **todo el historial**.
El resumen incluye también a los usuarios **sin accesos** en el periodo
(`total = 0`, estado `Sin actividad`), para detectar cuentas que no se usan.

## 4. Vista `views/conexiones/index.php`

- Encabezado con selector de periodo y botón «Actualizar».
- 4 KPIs: usuarios con actividad, conexiones, promedio por usuario, última conexión.
- Dos pestañas:
  - **Resumen por usuario**: Usuario, Última conexión, Ubicación + IP,
    Conexiones, Días activos, Frecuencia, Estado.
  - **Historial completo**: Fecha y hora, Usuario, Ubicación, IP,
    Dispositivo, Origen.
- Ambas tablas usan DataTables con `scrollX` (el sistema se usa en móvil/PWA).
- **Detalle por usuario**: al hacer clic en el nombre de un usuario del resumen
  se abre un modal con **todo su historial** (sin filtro de periodo), ordenado
  del acceso más reciente al más antiguo, con fecha/hora, ubicación (país,
  ciudad, región), IP, dispositivo y origen. Encima de la tabla se muestran sus
  métricas globales: conexiones totales, días activos, frecuencia, días sin
  entrar, primera y última conexión, ubicaciones e IPs distintas, más el chip
  de estado. Se pide con
  `data_mconexiones.php?usuario=<id>&dias=0`.
- Solo accesible para roles 1 (ADMIN) y 4 (SUPER USUARIO).

## 5. Limitaciones conocidas

- La geolocalización depende de servicios **externos gratuitos por HTTP**.
  Con VPN o IP de operador móvil, la ciudad puede ser la del nodo del operador.
- Las IP privadas siempre quedan como `Red local`.
- El campo `resultado = 'fallo'` existe pero **no se registra todavía**; para
  auditar intentos fallidos habría que llamar al helper también en la rama de
  credenciales incorrectas del login.
- `vmdataconexiones` y `models/Vmdataconexione.php` ya no los usa este módulo;
  se conservan por si algo más los consume.
- El historial no se purga automáticamente. Si crece demasiado, conviene un
  job de limpieza (p. ej. conservar 24 meses).

## 6. Cómo probarlo

```bash
# 1) Migración (idempotente)
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -d sovca_qas \
  -v ON_ERROR_STOP=1 -f scripts/migracion_conexiones_historial.sql

# 2) Endpoints (requieren sesión de rol 1 o 4)
curl -b cookies.txt "http://127.0.0.1/sovca/data_json/data_mconexiones_resumen.php?dias=30"
curl -b cookies.txt "http://127.0.0.1/sovca/data_json/data_mconexiones.php?dias=30"
# Historial completo de una cuenta (detalle del resumen)
curl -b cookies.txt "http://127.0.0.1/sovca/data_json/data_mconexiones.php?usuario=77&dias=0"

# 3) Verificar que un login real inserta una fila
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -d sovca_qas \
  -c "SELECT username, ip, pais, ciudad, origen, fecha
        FROM conexiones_historial ORDER BY fecha DESC LIMIT 5"
```

Validaciones usadas al implementar: `php -l` en los 5 archivos PHP,
`node --check` sobre el JS embebido, login real automatizado (captcha incluido)
y verificación de que se inserta 1 fila por acceso con país/ciudad/dispositivo.
