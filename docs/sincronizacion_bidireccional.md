# Sincronización bidireccional de la PWA — análisis y validación

**Versión analizada:** app offline 4.33 · `bovs-shell-v81`
**Base de pruebas:** `sovca_qas` (fundo 2, usuario `USUARIODEMO`)
**Fecha:** 2026-10-18

---

## 1. Resumen ejecutivo

La sincronización de la PWA **funciona en las dos direcciones**, pero la auditoría
encontró **tres fallos reales** que provocaban pérdida silenciosa de cambios en la
dirección *nube → dispositivo*. Los tres quedaron corregidos y verificados con
pruebas automatizadas contra el API real:

| # | Hallazgo | Impacto | Estado |
|---|----------|---------|--------|
| 1 | `updated` no era fiable (NULL o con precisión de segundos) | Una edición hecha en la web **no llegaba nunca** al dispositivo que ya tenía el registro | Corregido (trigger + saneo) |
| 2 | El cursor del pull era solo la marca de tiempo | Dos filas con la misma marca: la última podía quedarse fuera para siempre | Corregido (cursor `marca\|id`) |
| 3 | El pull sobrescribía registros con cambios locales pendientes | El operario veía desaparecer su dato hasta que subía | Corregido (no se pisan los pendientes) |

Además se implementó el **aviso de versión nueva**: cuando en el servidor hay una
versión o una corrección distinta de la que está corriendo el equipo, la app lo
detecta sola, muestra las novedades y pide actualizar. **Nunca recarga por su
cuenta**: la recarga siempre nace de un toque en "Actualizar ahora".

**Resultado de las validaciones:** 25/25 comprobaciones del flujo bidireccional y
10/10 de los casos límite del cursor.

---

## 2. Arquitectura del flujo

```mermaid
flowchart LR
  subgraph PWA["Dispositivo (PWA offline)"]
    UI[Formularios] -->|guardar| IDB[(IndexedDB por fundo)]
    IDB --> OUT[outbox<br/>pendientes de subir]
    IDB --> CONF[conflictos]
    CUR[sync_meta<br/>cursor marca|id]
  end

  subgraph API["Servidor (api/sync_controller.php)"]
    PUSH[push<br/>crear / actualizar]
    PULL[pull<br/>cambios desde cursor]
    VER[AUTH + vínculo de dispositivo<br/>+ validación de fundo]
  end

  subgraph DB["PostgreSQL"]
    T[(9 tablas sincronizables<br/>trigger de marca monótona)]
  end

  OUT -->|POST push version_base| VER --> PUSH --> T
  CUR -->|POST pull desde=marca|id| VER --> PULL --> T
  PULL -->|registros nuevos| IDB
  PUSH -->|id, version, campos resueltos| IDB
```

**Módulos sincronizados:** `animales`, `nacimientos`, `servicios`,
`control_sanitario`, `control_sanitario_lotes`, `produccion_leche`, `pesos`,
`desincorporacion`, `movimiento_rebano`.

### Dirección dispositivo → nube (push)

1. Cada guardado escribe en IndexedDB y encola en `outbox` con `uuid_local` y
   `version_base`.
2. `Sync.enviarPendientes()` agrupa por módulo (animales → servicios →
   nacimientos primero, para respetar dependencias) y envía lotes de hasta 200.
3. El servidor resuelve cada cambio por separado:
   - `creado` / `actualizado`: devuelve `id`, `version`, `updated` y los campos que
     el servidor normaliza (códigos provisionales, categoría, etc.).
   - `conflicto`: la versión del servidor ya no coincide con `version_base`.
   - `sin_cambios`: reenvío idempotente (sin `version_base`), no duplica.
   - `error`: el registro se queda en `outbox` con el motivo, sin bloquear al resto.
4. Si un alta genera un **código definitivo**, el servidor lo devuelve en
   `campos_resueltos` y la app reescribe las referencias locales
   (`remapearCodigosDeAnimal`), incluidas las del `outbox`.

### Dirección nube → dispositivo (pull)

1. Cada módulo tiene su cursor en `sync_meta` (`ultima_sync_<modulo>`), por fundo.
2. `pull` devuelve hasta 500 filas con `hay_mas` / `siguiente_desde`.
3. El cliente guarda el cursor y repite hasta `hay_mas = false`.
4. Los registros con cambios locales pendientes **no se sobrescriben**: se
   resuelven por el camino del push (conflicto si el servidor cambió).

---

## 3. Hallazgos y correcciones

### 3.1. `updated` no era fiable → ediciones que nunca llegaban

**Síntoma medido:** crear un pesaje y editarlo en el mismo segundo dejaba
`COALESCE(updated, created)` **por detrás** del cursor ya guardado por el
dispositivo. El pull devolvía **0 filas**: la edición no llegaba nunca.

**Causas:**
- `php-activerecord` solo gestiona `created_at` / `updated_at`; las columnas
  `created` / `updated` **no** las rellena.
- Cuando el API las escribía, usaba `date('Y-m-d H:i:s')` (**segundos**), mientras
  que el `DEFAULT` de la base pone `created` con **microsegundos** → la marca podía
  retroceder.

**Alcance:** casi todas las filas de las 9 tablas tenían la marca desfasada
(**9.716 filas** saneadas: `mprolecheras` 4.532, `manimales` 2.962,
`mregnacimientos` 627, `mdesincorporacionanimales` 559, `mconsecutivopesos` 529,
`mregservicios` 320, `mcontrolsanitarios` 167, `mcontrolsanitarioxlotes` 20).

**Corrección:** `scripts/migracion_sync_cursor_updated.sql` (idempotente) crea un
trigger por tabla que:
- al **insertar** deja `updated = GREATEST(updated, created, now())`;
- al **actualizar** mueve `updated` siempre hacia adelante, como mínimo
  **1 microsegundo** por encima de la marca anterior;
- **sanea** las filas existentes con `updated` NULL o anterior a `created`.

Así la edición número *n* siempre tiene una marca mayor que la *n-1*, venga de la
web, de otro dispositivo o de un script.

### 3.2. Cursor solo por marca → empates

Un push en lote escribe varias filas en el mismo instante: todas comparten marca.
Con `marca > cursor` la última del lote podía quedar fuera.

**Corrección:** el cursor pasa a ser **`marca|id`** (`2026-10-18 11:47:48.123456|553`).
La consulta compara la tupla completa:

```sql
WHERE mfundos_id = ?
  AND (COALESCE(updated, created) > ?
       OR (COALESCE(updated, created) = ? AND id > ?))
ORDER BY COALESCE(updated, created) ASC, id ASC
```

Se sigue aceptando el formato viejo (solo timestamp) para los equipos que ya
tienen un cursor guardado; el cliente no necesita cambios porque guarda
`siguiente_desde` tal cual lo recibe.

### 3.3. El pull pisaba los cambios locales pendientes

Al sincronizar, cualquier fila traída del servidor reemplazaba la copia local
aunque el registro estuviera en `outbox`. El operario veía desaparecer su edición
hasta que subía.

**Corrección:** `Sync.traerCambios()` omite del guardado los `uuid_local` que están
en `outbox`. El dato local se conserva y el push posterior resuelve:
- si el servidor no cambió → se aplica;
- si el servidor cambió → `conflicto` y la persona elige en pantalla.

---

## 4. Aviso de versión nueva y actualización

**Problema:** el equipo podía quedarse semanas ejecutando una versión vieja porque
nadie le avisaba (el badge solo informaba el número).

**Comportamiento implementado:**

1. Al abrir se guarda la versión y el hash técnico que está ejecutando el equipo.
2. `verificarNuevaVersion()` consulta `version.php` (sin caché) **cada 5 minutos**,
   al volver a la app (`visibilitychange`) y al recuperar señal.
3. Si la versión **o** el hash técnico difieren, aparece el recuadro
   `#aviso-actualizacion` con:
   - título ("Hay una versión nueva de la app (v4.34)" o "…una corrección nueva");
   - versión que usa el equipo vs. la del servidor;
   - hasta 2 novedades;
   - **"Actualizar ahora"** y **"Después"**.
4. El badge de versión del encabezado se marca en ámbar y **reabre el aviso** al
   tocarlo (por si se pospuso).
5. "Actualizar ahora":
   - si hay formularios o modales abiertos, pide confirmación;
   - si hay registros pendientes, **primero sincroniza** (botón
     "Sincronizar y actualizar"), informa si quedan conflictos y recién entonces
     pide al service worker la versión nueva y recarga;
   - si el relevo del service worker no llega en 8 s, recarga igual (los archivos
     se revalidan al abrir).
6. **Nunca hay recarga automática** por este motivo: la decisión es de la persona.

---

## 5. Cómo reproducir las validaciones

Requisitos: base `sovca_qas` con la migración aplicada y un dispositivo vinculado en
modo gestión para el usuario/fundo de prueba.

```bash
# 1. Migración de marcas (idempotente)
psql -h 127.0.0.1 -U postgres -d sovca_qas -f scripts/migracion_sync_cursor_updated.sql

# 2. Flujo bidireccional completo (21 comprobaciones)
php api/test/validar_sync_bidireccional.php

# 3. Casos límite del cursor (11 comprobaciones)
php api/test/validar_sync_limites.php

# 4. Reglas de negocio y anulación (10 + 19 comprobaciones)
php api/test/validar_reglas_negocio.php
php api/test/validar_anulacion.php

# 5. Reversión de movimientos de rebaño (18 comprobaciones)
php api/test/validar_reversion.php
```

Ambos scripts crean su propio token y sus propios registros, y los **borran** al
terminar (incluso si fallan). Devuelven código de salida 0 cuando todo pasa.

### Casos cubiertos

| Bloque | Qué verifica |
|--------|--------------|
| Preparación | Token de 64 caracteres y dispositivo vinculado en gestión |
| Pull | HTTP 200, arreglo de registros, cursor compuesto |
| Push alta | `creado`, `id`, `version = 1` |
| Ida y vuelta | El pull devuelve el registro recién subido con sus datos |
| Push edición | `actualizado` y `version + 1` |
| Conflicto | `version_base` vieja → `conflicto`, sin pisar el dato del servidor |
| Idempotencia | Reenvío sin `version_base` → `sin_cambios` |
| Edición por cursor | La fila reeditada viaja en el siguiente pull con el último valor |
| Seguridad | Fundo sin acceso → 4xx |
| Modo consulta | Push → 403 `modo_consulta_no_escribe`; pull permitido |
| Lote simultáneo | 3 altas con la misma marca llegan todas y no se repiten |
| Ediciones repetidas | La marca avanza y llega el último valor |
| Coherencia global | Ninguna fila con `updated` NULL o anterior a `created` |
| Anulación | `eliminado: true` deja `status = 0`, sube la versión y es idempotente |
| Anulación con versión vieja | Devuelve `conflicto` y no toca el registro |
| Anulación de un servicio con parto | Se rechaza (igual que la web) |

---

## 5.1. Anulación (borrado lógico) desde el dispositivo

La web elimina con `status = 0`. La PWA hace lo mismo para no divergir:

1. En la tarjeta del registro (leche, pesaje, control sanitario o servicio) se toca
   **Eliminar** (icono de papelera en celular).
2. Si el registro **nunca llegó al servidor** (sin `id`), se borra del equipo junto con
   su fila del outbox: no hay nada que avisar.
3. Si **ya está sincronizado**, se encola `{ eliminado: true, version_base }`. El
   servidor (`anular_registro()`) marca `status = 0`, sube `version` y `updated`, y
   responde `eliminado`. El equipo borra entonces su copia local.
4. El pull siguiente **borra** los registros que llegan con `status = 0` en esos
   módulos, así que una baja hecha en la web también desaparece del celular.
5. Si el registro cambió en el servidor mientras tanto, el resultado es `conflicto`:
   la pantalla de conflictos ofrece **Eliminar igual** o **Conservar el del servidor**.

Módulos anulables: `produccion_leche`, `pesos`, `control_sanitario`, `servicios`.
Bajas, movimientos y nacimientos no se eliminan desde el campo (tienen su flujo).
Un servicio con `parto = 1` no se anula, igual que en la web.

## 5.2. Respaldo y restauración local

Desde el chip de estado de sincronización (**Respaldo del equipo**):

- **Descargar respaldo** genera `bovs-respaldo-f<fundo>-AAAAMMDD-HHMM.json` con los
  datos de campo, el outbox, los conflictos y los rangos de códigos del fundo.
- **Restaurar respaldo** reemplaza los almacenes del fundo activo **solo si el
  `fundo_id` del archivo coincide**; antes pide confirmación.
- No incluye la sesión ni la credencial sin conexión: esas son del equipo.

## 5.3. Reversión de un movimiento de rebaño

Un movimiento no se borra: se revierte. La PWA replica el `case
"revertir_movimiento"` de `movreb_controller.php`.

- **Movimiento todavía no enviado** (sin `id`): se deshace en el equipo — los
  animales vuelven a su lote/potrero de origen, se quita del outbox y se borra el
  registro local. No queda rastro en el servidor.
- **Movimiento ya sincronizado**: la tarjeta muestra un panel **Motivo de la
  reversión** (mínimo 10 caracteres, igual que la web) y se encola
  `{ revertir: true, motivo_reversion, version_base }`. El servidor:
  1. controla la versión (si cambió, devuelve `conflicto`);
  2. devuelve cada animal del detalle a `lote_anterior_id` / `potrero_anterior_id`;
  3. marca el movimiento con `revertido = TRUE`, `fecha_reversion`,
     `motivo_reversion`, `user_reversion` y sube `version`.
- El resultado `revertido` es **idempotente**: reenviar la petición no infla la
  versión ni vuelve a mover animales.
- El pull siguiente trae el movimiento con `revertido = true`, así que la tarjeta
  queda atenuada, muestra el motivo y ya no ofrece el botón **Revertir**.

## 5.4. Pantalla de conflictos

Cuando un registro cambió en el servidor y también en el equipo, la tarjeta de
conflicto muestra ahora **campo por campo** qué valor tiene cada versión (con
etiquetas legibles y fechas formateadas), más la fecha de la edición del
servidor. Solo se comparan los campos que el equipo llegó a enviar, y las
fechas se equiparan (una marca con `00:00:00` es la misma fecha que sin hora),
para no listar diferencias falsas.


---

## 6. Recomendaciones operativas

- **Aplicar la migración en cada base** (QAS y producción). Es idempotente.
- **No desactivar** los triggers `trg_*_marca_sync`: son los que garantizan que una
  edición siempre avance la marca.
- Si se importan datos con `updated` histórico, hacerlo con
  `ALTER TABLE … DISABLE TRIGGER …` y volver a habilitarlo, o dejar que el trigger
  lo normalice (el importador no lo necesita).
- Al desplegar una versión nueva, **subir `actual` en `versiones.json` y
  `VERSION_CACHE` en `service-worker.js`**: el aviso se apoya en esos dos valores.
  Si solo cambia un archivo, el `hash_tecnico` igual dispara el aviso de corrección.
- Revisar los conflictos pendientes desde la PWA antes de actualizar en bloque.
