-- =====================================================================
-- Normalización de los contadores de correlativos por fundo
-- =====================================================================
-- PROBLEMA
--   Las funciones reservar_rango_animal(), reservar_rango_ternero() y
--   reservar_rango_servicio() entregan el número que sigue según la tabla
--   <modulo>xfundos_contador.ultimo_numero_asignado, SIN mirar qué códigos
--   existen realmente. Si el contador quedó por debajo de los códigos ya
--   usados (cargas manuales, importaciones, restauraciones o registros
--   hechos desde la web con otro criterio), el próximo registro recibe un
--   código repetido.
--
--   Consecuencias reales:
--     · mregnacimientos.codigo_ternero tiene un índice ÚNICO GLOBAL
--       ("Código del Ternero"): el parto falla SIEMPRE y el productor solo
--       ve "No se pudo registrar el nacimiento. Verifica los datos."
--       (2026-11-01: fundo 2 con contador 15 y MA-02-016 ya guardado).
--     · codigo_servicio no tiene índice único: el duplicado entra en
--       silencio y después aparecen dos servicios con el mismo código.
--     · manimales: el choque lo detecta la validación de código único y el
--       animal queda sin subir.
--
-- QUÉ HACE ESTE SCRIPT
--   1. Informa el desfase antes de tocar nada.
--   2. Sube el contador de cada fundo al mayor número de código ya usado
--      (nunca lo baja: si va adelante se respeta, esos números ya se
--      consumieron).
--   3. Crea la fila del contador para fundos con códigos pero sin fila.
--   4. Informa el resultado y los códigos duplicados que ya existan.
--
--   Solo se consideran códigos con el formato final <PREFIJO>-<fundo>-<n>;
--   los provisionales …-PEND-… se ignoran (el servidor siempre los regenera).
--
--   Es idempotente: correrlo dos veces no cambia nada la segunda vez.
--
--   El código de sync_controller.php ya reintenta con el número siguiente
--   cuando encuentra un código ocupado, así que un desfase NO rompe la
--   sincronización; este script deja los contadores sanos y evita gastar
--   esos reintentos.
--
-- Uso:
--   psql -U postgres -d <base> -f scripts/migracion_correlativos_contador.sql
-- =====================================================================

\set ON_ERROR_STOP on

BEGIN;

\echo '=== 1. Desfase ANTES (contador < último código usado) — animales ==='
SELECT c.mfundos_id                        AS fundo,
       c.ultimo_numero_asignado            AS contador,
       t.maximo                            AS ultimo_codigo_usado,
       t.maximo - c.ultimo_numero_asignado AS diferencia
  FROM manimalxfundos_contador c
  JOIN (SELECT mfundos_id,
               MAX(SUBSTRING(codigo_rgb_id_animal FROM '([0-9]+)$')::int) AS maximo
          FROM manimales
         WHERE codigo_rgb_id_animal ~ '^SOVCA-[0-9]+-[0-9]+$'
         GROUP BY mfundos_id) t ON t.mfundos_id = c.mfundos_id
 WHERE c.ultimo_numero_asignado < t.maximo
 ORDER BY c.mfundos_id;

\echo '=== 2. Desfase ANTES — nacimientos (terneros) ==='
SELECT c.mfundos_id                        AS fundo,
       c.ultimo_numero_asignado            AS contador,
       t.maximo                            AS ultimo_codigo_usado,
       t.maximo - c.ultimo_numero_asignado AS diferencia
  FROM mnacimientoxfundos_contador c
  JOIN (SELECT mfundos_id,
               MAX(SUBSTRING(codigo_ternero FROM '([0-9]+)$')::int) AS maximo
          FROM mregnacimientos
         WHERE codigo_ternero ~ '^MA-[0-9]+-[0-9]+$'
         GROUP BY mfundos_id) t ON t.mfundos_id = c.mfundos_id
 WHERE c.ultimo_numero_asignado < t.maximo
 ORDER BY c.mfundos_id;

\echo '=== 3. Desfase ANTES — servicios ==='
SELECT c.mfundos_id                        AS fundo,
       c.ultimo_numero_asignado            AS contador,
       t.maximo                            AS ultimo_codigo_usado,
       t.maximo - c.ultimo_numero_asignado AS diferencia
  FROM mserviciosxfundos_contador c
  JOIN (SELECT mfundos_id,
               MAX(SUBSTRING(codigo_servicio FROM '([0-9]+)$')::int) AS maximo
          FROM mregservicios
         WHERE codigo_servicio ~ '^RSA-[0-9]+-[0-9]+$'
         GROUP BY mfundos_id) t ON t.mfundos_id = c.mfundos_id
 WHERE c.ultimo_numero_asignado < t.maximo
 ORDER BY c.mfundos_id;

-- ---------------------------------------------------------------------
-- 4. Subir los contadores al mayor código usado (nunca bajarlos)
-- ---------------------------------------------------------------------
WITH maximo AS (
    SELECT mfundos_id,
           MAX(SUBSTRING(codigo_rgb_id_animal FROM '([0-9]+)$')::int) AS maximo
      FROM manimales
     WHERE codigo_rgb_id_animal ~ '^SOVCA-[0-9]+-[0-9]+$'
     GROUP BY mfundos_id
)
UPDATE manimalxfundos_contador c
   SET ultimo_numero_asignado = m.maximo, updated = NOW()
  FROM maximo m
 WHERE c.mfundos_id = m.mfundos_id
   AND c.ultimo_numero_asignado < m.maximo;

WITH maximo AS (
    SELECT mfundos_id,
           MAX(SUBSTRING(codigo_ternero FROM '([0-9]+)$')::int) AS maximo
      FROM mregnacimientos
     WHERE codigo_ternero ~ '^MA-[0-9]+-[0-9]+$'
     GROUP BY mfundos_id
)
UPDATE mnacimientoxfundos_contador c
   SET ultimo_numero_asignado = m.maximo, updated = NOW()
  FROM maximo m
 WHERE c.mfundos_id = m.mfundos_id
   AND c.ultimo_numero_asignado < m.maximo;

WITH maximo AS (
    SELECT mfundos_id,
           MAX(SUBSTRING(codigo_servicio FROM '([0-9]+)$')::int) AS maximo
      FROM mregservicios
     WHERE codigo_servicio ~ '^RSA-[0-9]+-[0-9]+$'
     GROUP BY mfundos_id
)
UPDATE mserviciosxfundos_contador c
   SET ultimo_numero_asignado = m.maximo, updated = NOW()
  FROM maximo m
 WHERE c.mfundos_id = m.mfundos_id
   AND c.ultimo_numero_asignado < m.maximo;

-- ---------------------------------------------------------------------
-- 5. Crear la fila del contador para fundos con códigos y sin fila
-- ---------------------------------------------------------------------
INSERT INTO manimalxfundos_contador (mfundos_id, ultimo_numero_asignado, updated)
SELECT a.mfundos_id,
       MAX(SUBSTRING(a.codigo_rgb_id_animal FROM '([0-9]+)$')::int),
       NOW()
  FROM manimales a
 WHERE a.codigo_rgb_id_animal ~ '^SOVCA-[0-9]+-[0-9]+$'
   AND NOT EXISTS (SELECT 1 FROM manimalxfundos_contador c WHERE c.mfundos_id = a.mfundos_id)
 GROUP BY a.mfundos_id
    ON CONFLICT (mfundos_id) DO NOTHING;

INSERT INTO mnacimientoxfundos_contador (mfundos_id, ultimo_numero_asignado, updated)
SELECT n.mfundos_id,
       MAX(SUBSTRING(n.codigo_ternero FROM '([0-9]+)$')::int),
       NOW()
  FROM mregnacimientos n
 WHERE n.codigo_ternero ~ '^MA-[0-9]+-[0-9]+$'
   AND NOT EXISTS (SELECT 1 FROM mnacimientoxfundos_contador c WHERE c.mfundos_id = n.mfundos_id)
 GROUP BY n.mfundos_id
    ON CONFLICT (mfundos_id) DO NOTHING;

INSERT INTO mserviciosxfundos_contador (mfundos_id, ultimo_numero_asignado, updated)
SELECT s.mfundos_id,
       MAX(SUBSTRING(s.codigo_servicio FROM '([0-9]+)$')::int),
       NOW()
  FROM mregservicios s
 WHERE s.codigo_servicio ~ '^RSA-[0-9]+-[0-9]+$'
   AND NOT EXISTS (SELECT 1 FROM mserviciosxfundos_contador c WHERE c.mfundos_id = s.mfundos_id)
 GROUP BY s.mfundos_id
    ON CONFLICT (mfundos_id) DO NOTHING;

\echo '=== 6. Estado DESPUÉS (los tres deben salir vacíos) ==='
SELECT 'animales' AS modulo, c.mfundos_id, c.ultimo_numero_asignado AS contador, t.maximo
  FROM manimalxfundos_contador c
  JOIN (SELECT mfundos_id, MAX(SUBSTRING(codigo_rgb_id_animal FROM '([0-9]+)$')::int) AS maximo
          FROM manimales WHERE codigo_rgb_id_animal ~ '^SOVCA-[0-9]+-[0-9]+$' GROUP BY mfundos_id) t
    ON t.mfundos_id = c.mfundos_id
 WHERE c.ultimo_numero_asignado < t.maximo
 UNION ALL
SELECT 'nacimientos', c.mfundos_id, c.ultimo_numero_asignado, t.maximo
  FROM mnacimientoxfundos_contador c
  JOIN (SELECT mfundos_id, MAX(SUBSTRING(codigo_ternero FROM '([0-9]+)$')::int) AS maximo
          FROM mregnacimientos WHERE codigo_ternero ~ '^MA-[0-9]+-[0-9]+$' GROUP BY mfundos_id) t
    ON t.mfundos_id = c.mfundos_id
 WHERE c.ultimo_numero_asignado < t.maximo
 UNION ALL
SELECT 'servicios', c.mfundos_id, c.ultimo_numero_asignado, t.maximo
  FROM mserviciosxfundos_contador c
  JOIN (SELECT mfundos_id, MAX(SUBSTRING(codigo_servicio FROM '([0-9]+)$')::int) AS maximo
          FROM mregservicios WHERE codigo_servicio ~ '^RSA-[0-9]+-[0-9]+$' GROUP BY mfundos_id) t
    ON t.mfundos_id = c.mfundos_id
 WHERE c.ultimo_numero_asignado < t.maximo;

\echo '=== 7. Códigos duplicados que YA existen (informativo) ==='
SELECT 'terneros' AS modulo, mfundos_id, codigo_ternero AS codigo, COUNT(*) AS veces
  FROM mregnacimientos
 WHERE codigo_ternero ~ '^MA-[0-9]+-[0-9]+$'
 GROUP BY mfundos_id, codigo_ternero HAVING COUNT(*) > 1
 UNION ALL
SELECT 'servicios', mfundos_id, codigo_servicio, COUNT(*)
  FROM mregservicios
 WHERE codigo_servicio ~ '^RSA-[0-9]+-[0-9]+$'
 GROUP BY mfundos_id, codigo_servicio HAVING COUNT(*) > 1;

COMMIT;
