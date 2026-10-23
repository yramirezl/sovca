# Migración de fecha de secado / destete

Recalcula `f_secado` en `mregnacimientos` y `mregservicios` con la regla única
implementada en `lib/fecha_secado_helper.php`.

## Por qué es necesaria

Los datos históricos se generaron con tres lógicas distintas (una por módulo) y
con períodos que después cambiaron. En `sovca_qas` el diagnóstico dio:

| Módulo | Revisados | Ya correctos | Cambian | Se llenan | No aplica |
|---|---|---|---|---|---|
| Nacimientos | 932 | 0 | 845 | 0 | 3 (sin período) + 84 sin regla |
| Servicios (gestantes) | 296 | 31 | 1 | 207 | 5 + 52 sin regla |

Las diferencias en nacimientos son de −2 días (329), +1 día (306), +28 días (164,
fundo 63 cuyo PBL cambió de 182 a 210) y casos puntuales. En servicios los 207
vacíos son los que hoy no alimentan las alertas de secado.

## Regla aplicada

- **Nacimientos**: `f_parto + PBL` (propósito 1 = Lactancia/destete) o
  `f_parto + PPL` (propósito 2 = Producción de leche/secado). Período del fundo
  con fallback global (`mfundos_id = 0`). Solo si `produccion_leche = 1`.
- **Servicios**: solo gestantes (`medofisiologicos_id = 1`) con último parto y
  aptitud **LECHE (2)** o **DOBLE PROPÓSITO (3)**:
  - `servicio − parto < 180` → `servicio + 210`
  - en otro caso → `parto + PPL`, solo si esa fecha es posterior al servicio.
- **Nunca borra** una fecha existente cuando la regla ya no aplica (por ejemplo,
  los 5 servicios de aptitud carne): solo los informa como omitidos.

## Archivos

| Archivo | Uso |
|---|---|
| `scripts/migracion_fecha_secado.php` | Ejecutor recomendado (reutiliza el helper real de la app) |
| `scripts/migracion_fecha_secado.sql` | Versión SQL equivalente (para DBA / psql) |
| `scripts/migracion_fecha_secado_verificar.sql` | Verificación de solo lectura tras aplicar |
| `scripts/migracion_fecha_secado_revert.sql` | Reversión por lote |

## Procedimiento en producción

### 1. Respaldo

```bash
pg_dump -h 127.0.0.1 -U postgres -d bovs_prod \
        -t mregnacimientos -t mregservicios \
        -f /ruta/backup_secado_$(date +%Y%m%d_%H%M).sql
```

### 2. Simulación (no escribe nada)

```bash
cd /var/www/html/bovs
php scripts/migracion_fecha_secado.php
```

Revisar que los contadores tengan sentido. Para acotar:

```bash
php scripts/migracion_fecha_secado.php --modulo=nacimientos --fundo=63
php scripts/migracion_fecha_secado.php --modulo=servicios --fundo=63
```

### 3. Aplicar

```bash
php scripts/migracion_fecha_secado.php --aplicar
```

Al terminar imprime el **código de lote** (por ejemplo `20261020_192728`).
Guardarlo. Cada ejecución escribe en `migracion_fecha_secado_log` el valor
anterior de cada fila.

La actualización también hace `updated = NOW()` y `version = version + 1` para
que la PWA reciba el cambio en el siguiente `pull`. Si no se desea:

```bash
php scripts/migracion_fecha_secado.php --aplicar --sin-sync
```

### 4. Verificar

```bash
psql -h 127.0.0.1 -U postgres -d bovs_prod -P pager=off \
     -f scripts/migracion_fecha_secado_verificar.sql
```

Debe dar `siguen_distintos = 0` y `siguen_vacios = 0`. Volver a ejecutar el PHP
sin `--aplicar` debe mostrar `Fechas cambiadas = 0` (el script es idempotente).

### 5. Revertir (si hizo falta)

```bash
psql -h 127.0.0.1 -U postgres -d bovs_prod -P pager=off \
     -v lote="20261020_192728" \
     -f scripts/migracion_fecha_secado_revert.sql
```

## Alternativa SQL

```bash
psql -h 127.0.0.1 -U postgres -d bovs_prod -P pager=off \
     -v fundo=0 -f scripts/migracion_fecha_secado.sql
```

`fundo=0` procesa todos los fundos. La reversión es la misma (el lote se llama
`SQL_AAAAMMDD_HH24MISS`).

## Opciones del ejecutor PHP

| Opción | Efecto |
|---|---|
| `--modulo=nacimientos\|servicios\|todos` | Acota el módulo |
| `--fundo=ID` | Acota a un fundo |
| `--lote=N` | Filas por transacción (500 por defecto) |
| `--solo-vacios` | Solo llena fechas NULL, no toca las existentes |
| `--sin-sync` | No modifica `updated` / `version` |
| `--aplicar` | Escribe en la base (sin esto es simulación) |

## Recomendaciones

- Ejecutar en una ventana de baja actividad: aunque usa transacciones cortas,
  actualiza ~1 000 filas.
- Si el negocio prefiere **no** tocar fechas históricas y solo completar las
  faltantes, usar `--solo-vacios` (en QAS eso serían 207 servicios y 0
  nacimientos).
- Los 5 servicios de aptitud carne con fecha de secado se dejan intactos por
  decisión de diseño; si se quisieran limpiar, debe hacerse con un `UPDATE`
  explícito y autorizado.
