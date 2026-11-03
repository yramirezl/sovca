# Sincronización offline: errores, esperas y reintentos

Este documento explica por qué un registro del modo offline puede quedar
"en error" y qué hace la app desde la versión **1.00061** para que eso no
se repita indefinidamente.

## El caso que lo motivó

Un equipo de campo mostraba en el panel de administración *"Resumen de
sync"* el mismo rechazo cada 1–3 minutos, durante días:

```
movimiento_rebano · SOVCA-02-PEND-1247B0
Ninguno de los animales seleccionados está activo en este fundo.
```

El movimiento nunca iba a subir y el equipo seguía intentándolo.

## Causas

### 1. El registro apuntaba a un animal que todavía no existía en el servidor

Cuando se crea un animal sin conexión, la app le asigna un código
provisional (`SOVCA-0<fundo>-PEND-xxxxxx`). Si antes de que ese alta suba
se registra un movimiento, un control o un pesaje sobre él, el registro
viaja con el código provisional.

El orden de envío ya prioriza `animales` sobre el resto de los módulos,
pero eso **no garantiza** que el alta haya subido: si el alta falla por
cualquier motivo (datos incompletos, conflicto), los registros que
dependen de ella se envían igual y el servidor los rechaza.

### 2. El mapa de códigos renombrados se perdía en cada descarga

Al subir un animal, el servidor devuelve `codigo_anterior` (el
provisional) y el código definitivo. La app guarda ese `codigo_anterior`
en la copia local del animal para poder corregir después los registros
que aún llevan el código viejo.

El problema: en la descarga (pull) la app reemplazaba la copia local con
la fila del servidor, que **no** incluye `codigo_anterior`. Al perderlo,
el mapa provisional → definitivo quedaba vacío y los registros viejos
volvían a ser rechazados en el siguiente envío.

### 3. Los rechazos se reintentaban sin límite

El contador de intentos existía pero nadie lo usaba para decidir: cada
2 minutos (y en cada evento `online`) se reenviaba lo mismo. El resultado
era batería, datos y un historial lleno de fallos idénticos.

### 4. El servidor movía *parte* del rebaño y no lo decía

`validar_animales_movimiento_rebano()` devolvía sólo los animales
válidos y el movimiento se creaba con esos. Si de 10 animales 3 no eran
válidos, el movimiento subía "bien" con 7 y el operario creía que se
había movido todo el lote.

## Qué cambió

### Espera al animal (cliente)

Antes de enviar cualquier registro, `sync.js` revisa los animales que
menciona (campos de `CONFIG.CAMPOS_REFERENCIA_ANIMAL` y `datos.animales`).
Si alguno todavía no está sincronizado —o su código es `-PEND-`— el
registro **no se envía**:

- queda `estado_sync = 'pendiente'` (no se marca en rojo),
- el outbox guarda `espera_animal` con el código y un mensaje claro,
- se envía solo, en la primera sincronización posterior a la subida del
  animal.

Si el código provisional ya no tiene animal en el equipo (se revirtió su
alta), esperar no sirve: el registro pasa a *"necesita tu revisión"* con
ese motivo.

### `codigo_anterior` sobrevive a la descarga (cliente)

En `traerCambios()`, para el módulo `animales`, se conserva
`codigo_anterior` y `codigo_provisional` de la copia local al aplicar la
fila del servidor. El mapa de renombres ya no se pierde.

### Retroceso progresivo y bloqueo (cliente)

| Intento | Espera antes del siguiente |
| ------: | -------------------------- |
| 1       | 1 minuto                   |
| 2       | 2 minutos                  |
| 3       | 4 minutos                  |
| 4       | 8 minutos                  |
| 5       | 16 minutos                 |
| 6       | 30 minutos                 |

Al llegar a `CONFIG.MAX_INTENTOS_SYNC` (6) el registro se marca
`bloqueado`: deja de reintentarse solo y aparece en la burbuja de
sincronización bajo *"necesita tu revisión"*, con el motivo y un botón
**Reintentar ahora** (reinicia contadores) — o se revierte desde su
pantalla.

Una sincronización manual (tocar **Sincronizar**) salta la espera entre
intentos, pero no desbloquea los registros bloqueados: esos requieren una
decisión explícita.

Una falla de la petición completa (sin señal, 500, portal cautivo) **no**
cuenta como rechazo: no aplica retroceso ni bloquea nada.

### El movimiento es completo o no se guarda (servidor)

`validar_animales_movimiento_rebano()` ahora rechaza el movimiento
completo si algún animal no está disponible y nombra los códigos:

- *"No se encontraron en el servidor: A, B y 2 más."* + pista sobre
  `-PEND-`,
- *"Están dados de baja (muerto, vendido, extraviado o migrado): …"*,
- *"No están en el origen elegido: …"*.

Con esto nunca se crea un movimiento parcial.

### Visibilidad

- El ícono de sincronización del encabezado muestra en rojo
  **"N para revisar"** cuando hay registros bloqueados.
- La burbuja lista cada registro bloqueado con su motivo e intentos.
- En *Dispositivos → Resumen de sync* el evento de `push` informa
  **"0 de 1 guardado(s) · 1 rechazado(s)"** en lugar de
  "1 enviado · 1 rechazado".

## Qué hacer con un registro bloqueado en campo

1. Abrir la burbuja de sincronización (ícono del encabezado).
2. Leer el motivo de cada registro.
3. Corregir el dato en su pestaña (por ejemplo, volver a cargar la lista
   de animales para que aparezcan los que subieron).
4. Tocar **Reintentar ahora**.
5. Si el registro ya no corresponde, usar la opción de revertir/borrar de
   la tarjeta del registro.

## Verificación

Pruebas que quedan en el repositorio para que esto no vuelva a romperse:

```bash
# Motor de la app (sin navegador): 20 comprobaciones
node app_offline/test/verificar_espera_y_reintentos.js

# Flujo alta provisional → control → movimiento: 9 comprobaciones
php api/test/validar_alta_offline_flujo.php

# El movimiento es atómico: 6 comprobaciones
php api/test/validar_movimiento_atomico.php
```

Y las comprobaciones de sintaxis:

```bash
node --check app_offline/js/sync.js
node --check app_offline/js/app.js
node --check app_offline/service-worker.js
php -l api/sync_controller.php
```

## Archivos tocados

| Archivo | Cambio |
| ------- | ------ |
| `app_offline/js/sync.js` | espera al animal, `codigo_anterior` en pull, retroceso y bloqueo, conteo de errores por cambio real |
| `app_offline/js/config.js` | `MAX_INTENTOS_SYNC` |
| `app_offline/js/app.js` | chip "N para revisar", lista de bloqueados, botón Reintentar |
| `app_offline/css/estilos.css` | estilos de la lista de bloqueados |
| `api/sync_controller.php` | rechazo atómico del movimiento y mensajes con códigos |
| `views/dispositivos/sincronizaciones.php` | "N de M guardado(s)" |
| `app_offline/test/verificar_espera_y_reintentos.js` | prueba del motor de sincronización |
| `api/test/validar_movimiento_atomico.php` | prueba del rechazo atómico |
| `app_offline/versiones.json` | 1.00061 |
| `app_offline/service-worker.js` | `sovca-shell-v108` |
