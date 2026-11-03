# Pruebas del modo offline

Dos suites cubren el recorrido completo del productor sin conexión:
**facturas** (contrato con el servidor) y **motor** (el cliente real).

> ⚠ Las dos escriben en la base configurada y borran lo que crean, pero
> **usan la base de trabajo**: no las ejecutes apuntando a producción.

## 1. Contrato completo — `api/test/validar_offline_completo.php`

Reproduce, contra el API real, el trabajo de un productor que registra todo
sin conexión y después sincroniza:

| Fase | Qué prueba |
|------|------------|
| 1 | Alta de 4 animales (2 hembras, 2 machos) con código provisional `SOVCA-0<fundo>-PEND-xxxxxx` |
| 2 | El servidor rechaza registros que apuntan a un código provisional (no deja huérfanos) |
| 3 | Servicios con hembra y macho recién creados; código `RSA` lo asigna el servidor |
| 4 | Palpación (edición) y **conflicto de versión** si el equipo venía atrasado |
| 5 | Pesajes, incluido el duplicado del mismo día |
| 6 | Control sanitario individual con productos aplicados |
| 7 | Producción de leche |
| 8 | Control sanitario por lote (detalle de animales y productos) |
| 9 | Movimiento de rebaño (actualiza `manimales.mlotes_id`) |
| 10 | Nacimiento con **dos crías** y cierre del ciclo reproductivo |
| 11 | Fichaje del ternero (nacimiento → ficha de animal) |
| 12 | Desincorporación (venta) y su idempotencia |
| 13 | Idempotencia del alta (reenviar no duplica) |
| 14 | **Pull de los 9 módulos y comparación campo a campo** |

```bash
php api/test/validar_offline_completo.php
```

Resultado de referencia (2026-11-01, fundo 2): **70 comprobaciones OK** y
**137/137 campos = 100 %** de consistencia offline ↔ web.

## 2. Motor del cliente — `api/test/validar_motor_offline.php`

Carga `app_offline/js/db.js`, `dispositivo.js`, `api.js`, `config.js` y
`sync.js` **reales** en Node, sobre una IndexedDB en memoria
(`fake-indexeddb`), y sincroniza contra el API real. Comprueba lo que el
contrato no puede ver:

- `Db.tomarSiguienteCodigo` genera el código provisional;
- el outbox se arma como en cada módulo;
- `Sync.enviarPendientes` sube y **remapea** la copia local y los pendientes
  al recibir el código definitivo;
- tras «recargar la app», el mapa se reconstruye desde `codigo_anterior`.

Requisito (fuera del repo, no se agrega a `composer.json` ni a `package.json`):

```bash
mkdir -p /tmp/bovs-node && (cd /tmp/bovs-node && npm i fake-indexeddb)
```

```bash
php api/test/validar_motor_offline.php
# o con otra ruta de módulos:
SOVCA_NODE_PATH=/ruta/node_modules php api/test/validar_motor_offline.php
```

Resultado de referencia (2026-11-01): **18 comprobaciones OK**.

## 3. Correlativos: `scripts/migracion_correlativos_contador.sql`

Los contadores por fundo (`manimalxfundos_contador`,
`mnacimientoxfundos_contador`, `mserviciosxfundos_contador`) pueden quedar por
debajo de los códigos ya usados (cargas manuales, importaciones,
restauraciones). Consecuencia real el 2026-11-01: `codigo_ternero` tiene un
índice **único global**, el contador del fundo estaba en 15 y `MA-02-016` ya
existía → **todo nacimiento offline fallaba** con «No se pudo registrar el
nacimiento. Verifica los datos.»

`api/sync_controller.php` ahora reintenta con el número siguiente cuando
encuentra un código ocupado, así que el desfase ya no rompe nada. Este script
deja además los contadores sanos (idempotente, nunca los baja):

```bash
psql -U postgres -d <base> -f scripts/migracion_correlativos_contador.sql
```

## 4. Prueba rápida existente

`api/test/validar_alta_offline_flujo.php` (9 comprobaciones) sigue vigente para
el caso puntual «alta offline → control sanitario → movimiento».
