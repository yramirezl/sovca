# Correlativos sin huecos (código provisional → definitivo al sincronizar)

## Cómo funcionaba antes (con reserva de bloques)

1. El dispositivo, al detectar conexión, pedía un bloque de números
   (`POST api/rangos_controller.php { action: "reservar", modulo: "animales", cantidad: 20 }`).
2. Guardaba el bloque en IndexedDB (`rangos`) y generaba códigos **definitivos**
   offline: `SOVCA-017-0458`, `SOVCA-017-0459`…
3. Si sobraban números del bloque, se perdían → **huecos** en el correlativo del fundo
   (por eso un fundo con 87 animales podía tener el contador en 4144).

## Cómo funciona ahora (sin reserva)

1. El dispositivo **ya no pide bloques** (`CANTIDAD_A_RESERVAR: 0`) y el servidor
   **rechaza** cualquier reserva (`422`), así que también los equipos con la versión
   anterior dejan de abrir huecos.
2. Al registrar sin conexión se usa un **código provisional**, con el mismo formato
   del real: `SOVCA-0<fundo>-PEND-<aleatorio>` (animales) y `MA-0<fundo>-PEND-<aleatorio>`
   (nacimientos). La app lo marca como *provisional* en la chapeta.
3. Al sincronizar, el servidor detecta el `PEND`, pide **un** número a la función
   atómica `reservar_rango_animal(fundo, 1)` y guarda el código definitivo.
   Ya lo hacía `api/lib/Correlativos.php`; ahora es el único camino.
4. La sincronización devuelve el código nuevo y el cliente **remapea** las
   referencias del mismo lote (pesos, servicios, nacimientos, movimientos de rebaño,
   control sanitario) al código definitivo — la lista está en
   `app_offline/js/config.js → CAMPOS_REFERENCIA` y lo aplica `sync.js`.
5. El remapeo tiene tres refuerzos para que ninguna referencia quede apuntando al
   código provisional:
   - `enviarPendientes()` **vuelve a leer el outbox de cada módulo** justo antes de
     enviarlo. Antes usaba una foto tomada al inicio de la corrida, así que un
     control sanitario o un movimiento creados antes del alta del animal viajaban
     con el `PEND` y el servidor los rechazaba («Ninguno de los animales
     seleccionados está activo en este fundo»).
   - El animal guarda su `codigo_anterior`; con eso `mapaCodigosRenombrados()`
     reconstruye el mapa después de recargar la app y repara registros viejos.
   - Al preparar el envío se completan desde la copia local los campos que
     identifican al registro (animal, lote, fecha) si el payload los perdió.
6. El servidor **rechaza** (`SyncModulos.php → campos_animal` y
   `rechazar_codigos_provisionales()`) cualquier control, pesaje, servicio,
   nacimiento o baja que apunte a un código con `-PEND-`, con un mensaje que pide
   sincronizar primero. Antes ese registro se guardaba huérfano.

## Qué se gana y qué se pierde

| | Antes | Ahora |
|---|---|---|
| Huecos en el correlativo | Sí (bloques sin usar) | **No** |
| Código en el campo, sin señal | Definitivo | Provisional, se reemplaza al sincronizar |
| Si el equipo nunca sincroniza | Ya tenía código “real” | Queda provisional (igual que hoy si se agota el rango) |
| Duplicados entre equipos | Evitados por bloque | Evitados por la función atómica del servidor |

## Volver al esquema anterior

```bash
# servidor
echo "<?php return ['reservar_rangos' => true];" > config/correlativos.php
# o: SetEnv SOVCA_RESERVAR_RANGOS 1
```

```js
// app_offline/js/config.js
CANTIDAD_A_RESERVAR: 20,
```

Los bloques que un equipo ya tenga guardados se siguen usando hasta agotarse
(no generan huecos nuevos).

## Verificación

```bash
# debe responder 422 con el aviso de que la reserva está deshabilitada
curl -s -X POST -H 'Content-Type: application/json' \
  -d '{"action":"reservar","modulo":"animales","cantidad":20}' \
  http://TU-HOST/api/rangos_controller.php
```

Después de sincronizar un animal creado offline:

```sql
SELECT codigo_rgb_id_animal FROM manimales ORDER BY id DESC LIMIT 5;  -- sin 'PEND'
```
