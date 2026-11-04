# Servicios: reglas de edad y recordatorio de palpación

Documenta las reglas que se aplican **al registrar un servicio** (web y app
offline) y cómo se genera el recordatorio de palpación.

---

## 1. Hembra

| Regla | Valor | Dónde |
|---|---|---|
| Sexo | Hembra (`sexo = 1`) | `mservicios_controller` · `numeroServicioParaHembra()` (PWA) |
| Estatus | `mestatus_id = 1` (ACTIVO) | ídem |
| Edad mínima | **210 días** (≈ 7 meses) con fecha de nacimiento registrada | ídem |
| Ciclo abierto | **No puede tener un servicio abierto** | ídem |

Un ciclo deja de estar abierto cuando el control sanitario lo cierra con
**Aborto (4)** o **Vacía (5)**: recién ahí la hembra vuelve a estar disponible.

La edad mínima **ya no son 12 meses**: en ovinos y caprinos la pubertad llega
cerca de los 7 meses, así que el mínimo son **210 días**. El valor se guarda
como un período más (`mperiodos`, código `EHM`) y se ajusta desde
**Parámetros → Períodos**:

```
Nombre del Periodo : EDAD MINIMA DE LA HEMBRA PARA SERVICIO (DIAS)
Días de Gestación  : 210        (≈ 7 meses)
```

Resolución (`lib/edad_hembra_helper.php`): fila del fundo → fila global
(`mfundos_id = 0`) → 210 días. Se aplica en el listado de candidatas, en
`get_buscar_rgb_h` / `get_buscar_rgb_h_dona`, en `add` (última barrera) y en
la sincronización offline.

**Antes** la app offline la listaba igual y el rechazo aparecía al guardar
(*«La hembra ya tiene un servicio activo. Registra el resultado de palpación
antes de crear otro.»*), después de cargar todo el formulario. Ahora:

- el combo **solo ofrece hembras disponibles** (`poblarSelectAnimalesServicios()`
  → `hembraTieneServicioAbierto()`), y
- la regla se sigue validando al guardar (`numeroServicioParaHembra()`), por si
  el registro llegó por otra vía.

La web ya funcionaba así: `listar_candidatos_servicio` devuelve `disponible = 0`
y `servicio-selector.js` no las ofrece.

---

## 2. Macho — edad mínima parametrizable

Antes no se miraba la edad: se podía registrar una monta con un becerro de
6 meses.

El mínimo se guarda como **un período más** (`mperiodos`, código `EMM`), igual
que PSP / PPS / PGB, así que se ajusta desde **Parámetros → Períodos**:

```
Nombre del Periodo : EDAD MINIMA DEL MACHO PARA SERVICIO (DIAS)
Días de Gestación  : 365        (≈ 12 meses)
```

Resolución del valor (`lib/edad_macho_helper.php`):

1. fila del fundo para su tipo de ganadería,
2. fila global (`mfundos_id = 0`) del mismo tipo,
3. **365 días** si no hay ninguna fila.

Se aplica en:

- **Web** `listar_candidatos_servicio` (el macho joven aparece como *Muy joven*
  y no se puede elegir), `get_buscar_rgb_m` (código escrito a mano) y `add`
  (última barrera antes de guardar).
- **PWA** `poblarSelectAnimalesServicios()` (filtra la lista) y
  `validarNuevoServicioOffline()` (mensaje con los meses reales del animal).
  El valor llega por el catálogo `parametros_servicios.edad_minima_macho_dias`.

Mensaje al productor:

> El macho **SOVCA-02-0100 · Marcial** tiene **6 meses**. La edad mínima para
> registrar servicio es de **12 meses** (se ajusta en Parámetros → Períodos).

**Sin fecha de nacimiento la edad no se puede comprobar y el macho NO se
bloquea** (se ofrece igual). Es deliberado: en el hato hay toros y donadores
históricos sin fecha —hoy el 29 % de los donadores activos— y bloquearlos
impediría registrar servicios legítimos. La regla solo actúa cuando el dato
existe.

---

## 3. Fecha probable de palpación (FPP)

Se calcula como `f_servicio + días del período PSP` del fundo. El formulario
recalcula la fecha al cambiar la fecha de servicio y también cuando termina
de cargar el período (antes podía quedar vacía si el AJAX llegaba después).
El control del servicio usa el mismo cálculo para `Fecha Prob. de Palpación`
y para `Fecha.PP` (período PGB).

Los períodos se identifican por `mperiodos.codper_bov`:

| Código | Período | Uso |
|---|---|---|
| `PSP` | Servicio/palpación | `PP` · fecha probable de palpación |
| `PGB` | Gestación | `PG` · fecha probable de parto |
| `PPS` | Parto/servicio | celo · días no productivos |
| `PPL` | Producción de leche | secado normal (parto + PPL) |
| `PSD` | Secado | ajuste por servicio (210 ovino · 120 caprino) |
| `PBL` | Lactancia | destete (120 días en ovinos y caprinos) |
| `EMM` / `EHM` | Edad mínima macho/hembra | validación de servicios |

Si una instalación aún tiene `codper_bov = '0'`, ejecutar
`scripts/migracion_periodos_codper_bov.sql` (idempotente). Los endpoints de
palpación/gestación además buscan el período por nombre como respaldo.

---

## 4. Recordatorio de palpación en Notificaciones

`v_palpacion_alertas` alimenta `malertas` (tipo 2) desde
`alertas/controlador_alertas.php` (cron `scripts/cron/crear_alerta.sh`, lunes,
miércoles y viernes 06:00). Eso es lo que aparece en la campana del encabezado,
en **Notificaciones Activas** y en los correos.

La vista tenía tres defectos que la dejaban muda:

1. `motivo` solo se llenaba con el estado fisiológico *2 Proxima a Palpacion*;
   en el resto devolvía `NULL` y como `malertas.evento` es **NOT NULL**, el
   INSERT completo fallaba → **cero notificaciones de palpación**.
2. No filtraba por ciclo abierto: seguía recordando servicios ya palpados.
3. El cruce con `manimales` no usaba `mfundos_id`.

Corregido en `scripts/migracion_alerta_palpacion.sql`:

- solo servicios **abiertos** (`estatus_ciclo = 1`) sin resultado de palpación
  (excluye Aborto 4 y Vacía 5),
- ventana de **7 días** antes de la FPP,
- `motivo` siempre con texto y con la fecha:
  `PALPACION: 03/11/2026` → la notificación dice **cuándo** y, junto al código
  y el nombre del animal, **a cuál**.

---

## 5. Puesta en producción

```bash
psql -d sovca -f scripts/migracion_periodos_codper_bov.sql
psql -d sovca -f scripts/migracion_edad_minima_hembra.sql
psql -d sovca -f scripts/migracion_secado_caprino_120.sql
psql -d sovca -f scripts/migracion_edad_minima_macho.sql
psql -d sovca -f scripts/migracion_alerta_palpacion.sql
php alertas/controlador_alertas.php      # regenera malertas (o esperar al cron)
```

Ambas migraciones son idempotentes. En la demo el esquema se sincroniza con
`php scripts/demo_actualizar_esquema.php --aplicar`.
