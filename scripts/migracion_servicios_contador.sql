-- =====================================================================
-- Normalización del contador de códigos de servicio por fundo
-- =====================================================================
-- PROBLEMA
--   La función reservar_rango_servicio() entrega el número que sigue
--   según mserviciosxfundos_contador.ultimo_numero_asignado, sin mirar
--   qué códigos existen realmente en mregservicios. Si el contador quedó
--   por debajo de los códigos ya usados (por cargas manuales, importacio-
--   nes, restauraciones o porque un fundo empezó a usar servicios después
--   de que la tabla existiera), el próximo servicio recibe un código
--   REPETIDO (ej. fundo 76: contador 0 con 4 servicios ya guardados ->
--   el siguiente intenta RSA-076-01 otra vez).
--
--   codigo_servicio no tiene índice único, así que el duplicado entra en
--   silencio y después aparecen dos servicios con el mismo código.
--
-- QUÉ HACE ESTE SCRIPT
--   1. Informa el desfase antes de tocar nada.
--   2. Sube el contador de cada fundo al mayor número de código ya usado.
--      Nunca lo baja (si el contador va adelante, se respeta: esos
--      números ya se consumieron y no deben reutilizarse).
--   3. Crea la fila del contador para fundos que tienen servicios pero
--      no tienen fila.
--   4. Informa el resultado.
--
--   Solo se consideran códigos con el formato final RSA-<fundo>-<numero>;
--   los provisionales RSA-PEND-... se ignoran (nunca deberían haberse
--   guardado: el servidor siempre regenera el código al crear).
--
-- Es idempotente: correrlo dos veces no cambia nada la segunda vez.
-- =====================================================================

BEGIN;

\echo '=== 1. Desfase ANTES (contador < último código usado) ==='
SELECT c.mfundos_id                        AS fundo,
       c.ultimo_numero_asignado            AS contador,
       t.maximo                            AS ultimo_codigo_usado,
       t.maximo - c.ultimo_numero_asignado AS diferencia
  FROM mserviciosxfundos_contador c
  JOIN (SELECT mfundos_id,
               MAX(SUBSTRING(codigo_servicio FROM '([0-9]+)$')::int) AS maximo
          FROM mregservicios
         WHERE codigo_servicio ~ '^RSA-[0-9]+-[0-9]+$'
         GROUP BY mfundos_id) t
    ON t.mfundos_id = c.mfundos_id
 WHERE c.ultimo_numero_asignado < t.maximo
 ORDER BY c.mfundos_id;

\echo '=== 2. Fundos con servicios pero SIN fila de contador ==='
SELECT s.mfundos_id AS fundo,
       COUNT(*)     AS servicios,
       MAX(SUBSTRING(s.codigo_servicio FROM '([0-9]+)$')::int) AS ultimo_codigo_usado
  FROM mregservicios s
 WHERE s.codigo_servicio ~ '^RSA-[0-9]+-[0-9]+$'
   AND NOT EXISTS (SELECT 1 FROM mserviciosxfundos_contador c WHERE c.mfundos_id = s.mfundos_id)
 GROUP BY s.mfundos_id
 ORDER BY s.mfundos_id;

-- ---------------------------------------------------------------------
-- 3. Subir el contador al mayor código ya usado (nunca bajarlo)
-- ---------------------------------------------------------------------
WITH maximo AS (
    SELECT mfundos_id,
           MAX(SUBSTRING(codigo_servicio FROM '([0-9]+)$')::int) AS maximo
      FROM mregservicios
     WHERE codigo_servicio ~ '^RSA-[0-9]+-[0-9]+$'
     GROUP BY mfundos_id
)
UPDATE mserviciosxfundos_contador c
   SET ultimo_numero_asignado = m.maximo,
       updated                = NOW()
  FROM maximo m
 WHERE c.mfundos_id = m.mfundos_id
   AND c.ultimo_numero_asignado < m.maximo;

-- ---------------------------------------------------------------------
-- 4. Crear la fila del contador para fundos que no la tienen
-- ---------------------------------------------------------------------
INSERT INTO mserviciosxfundos_contador (mfundos_id, ultimo_numero_asignado, updated)
SELECT s.mfundos_id,
       MAX(SUBSTRING(s.codigo_servicio FROM '([0-9]+)$')::int),
       NOW()
  FROM mregservicios s
 WHERE s.codigo_servicio ~ '^RSA-[0-9]+-[0-9]+$'
   AND NOT EXISTS (SELECT 1 FROM mserviciosxfundos_contador c WHERE c.mfundos_id = s.mfundos_id)
 GROUP BY s.mfundos_id
    ON CONFLICT (mfundos_id) DO NOTHING;

\echo '=== 5. Estado DESPUÉS (debe salir vacío) ==='
SELECT c.mfundos_id AS fundo, c.ultimo_numero_asignado AS contador, t.maximo AS ultimo_codigo_usado
  FROM mserviciosxfundos_contador c
  JOIN (SELECT mfundos_id,
               MAX(SUBSTRING(codigo_servicio FROM '([0-9]+)$')::int) AS maximo
          FROM mregservicios
         WHERE codigo_servicio ~ '^RSA-[0-9]+-[0-9]+$'
         GROUP BY mfundos_id) t
    ON t.mfundos_id = c.mfundos_id
 WHERE c.ultimo_numero_asignado < t.maximo
 ORDER BY c.mfundos_id;

\echo '=== 6. Códigos de servicio duplicados (informativo) ==='
SELECT mfundos_id, codigo_servicio, COUNT(*) AS veces, MIN(id) AS id_menor, MAX(id) AS id_mayor
  FROM mregservicios
 WHERE codigo_servicio ~ '^RSA-[0-9]+-[0-9]+$'
 GROUP BY mfundos_id, codigo_servicio
HAVING COUNT(*) > 1
 ORDER BY mfundos_id, codigo_servicio;

COMMIT;
