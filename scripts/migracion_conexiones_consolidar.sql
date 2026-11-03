-- =====================================================================
-- Consolidación del historial de conexiones duplicado
-- =====================================================================
-- PROBLEMA
--   El login podía ejecutarse más de una vez con la MISMA sesión PHP
--   (reenvío del formulario, flujo multifundo, reingreso sin cerrar). Cada
--   ejecución insertaba otra fila en conexiones_historial con el mismo
--   session_id_hash → la pantalla «Conexiones» mostraba varias «En línea»
--   de la misma conexión, con duraciones de 0 s (2026-11-01).
--
--   El código ya NO las crea (lib/geoip_helper.php reutiliza la conexión
--   abierta y acumula la duración). Este script limpia las que quedaron.
--
-- QUÉ HACE
--   1. Informa los grupos duplicados.
--   2. Por cada (usuario, session_id_hash) con más de una fila:
--        · conserva la fila MÁS ANTIGUA (el inicio real de la conexión);
--        · le pone la última actividad del grupo y la duración acumulada;
--        · la marca cerrada solo si TODAS las filas del grupo lo estaban,
--          con el motivo del último cierre (logout/timeout);
--        · borra las demás.
--   3. Informa el resultado.
--
--   Solo actúa sobre filas con session_id_hash. Dos filas con el mismo
--   hash son, por definición, la misma sesión de PHP, así que unirlas no
--   inventa información. Las filas anteriores a la migración (hash NULL)
--   se dejan intactas.
--
--   Es idempotente: correrlo dos veces no cambia nada la segunda vez.
--
-- Uso:
--   psql -U postgres -d <base> -f scripts/migracion_conexiones_consolidar.sql
-- =====================================================================

\set ON_ERROR_STOP on

BEGIN;

\echo '=== 1. Conexiones duplicadas (misma sesión, varias filas) ==='
SELECT musuarios_id,
       left(session_id_hash, 12) || '…' AS sesion,
       count(*)                          AS filas,
       min(id)                           AS id_a_conservar,
       to_char(min(fecha) AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI') AS inicio,
       to_char(max(COALESCE(ultima_actividad, fecha)) AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI') AS ultima_actividad
  FROM conexiones_historial
 WHERE session_id_hash IS NOT NULL
   AND resultado = 'ok'
 GROUP BY musuarios_id, session_id_hash
HAVING count(*) > 1
 ORDER BY musuarios_id, min(fecha);

-- ---------------------------------------------------------------------
-- 2. Grupos a consolidar
-- ---------------------------------------------------------------------
CREATE TEMP TABLE _cx_grupos ON COMMIT DROP AS
SELECT h.musuarios_id,
       h.session_id_hash,
       min(h.id)                                        AS principal,
       max(COALESCE(h.ultima_actividad, h.fecha))       AS ultima,
       max(h.fecha_fin)                                 AS fin,
       bool_and(h.cerrada)                              AS todas_cerradas,
       (array_agg(h.cerrada_por
                  ORDER BY COALESCE(h.fecha_fin, h.ultima_actividad, h.fecha) DESC))[1] AS motivo
  FROM conexiones_historial h
 WHERE h.session_id_hash IS NOT NULL
   AND h.resultado = 'ok'
 GROUP BY h.musuarios_id, h.session_id_hash
HAVING count(*) > 1;

-- ---------------------------------------------------------------------
-- 3. La fila que se conserva queda con el total acumulado
-- ---------------------------------------------------------------------
UPDATE conexiones_historial h
   SET ultima_actividad = g.ultima,
       fecha_fin        = CASE WHEN g.todas_cerradas
                               THEN COALESCE(g.fin, g.ultima) END,
       cerrada          = g.todas_cerradas,
       cerrada_por      = CASE WHEN g.todas_cerradas THEN g.motivo END,
       duracion_seg     = GREATEST(0, EXTRACT(EPOCH FROM (
                            CASE WHEN g.todas_cerradas
                                 THEN COALESCE(g.fin, g.ultima)
                                 ELSE g.ultima END
                            - h.fecha))::int)
  FROM _cx_grupos g
 WHERE h.id = g.principal;

\echo ''
\echo '=== 2. Filas principales actualizadas ==='

-- ---------------------------------------------------------------------
-- 4. Se borran las filas sobrantes del grupo
-- ---------------------------------------------------------------------
DELETE FROM conexiones_historial d
 USING _cx_grupos g
 WHERE d.musuarios_id = g.musuarios_id
   AND d.session_id_hash = g.session_id_hash
   AND d.id <> g.principal;

\echo ''
\echo '=== 3. Estado DESPUÉS (no debe quedar ningún duplicado) ==='
SELECT musuarios_id,
       left(session_id_hash, 12) || '…' AS sesion,
       count(*)                          AS filas
  FROM conexiones_historial
 WHERE session_id_hash IS NOT NULL
   AND resultado = 'ok'
 GROUP BY musuarios_id, session_id_hash
HAVING count(*) > 1;

\echo ''
\echo '=== 4. Cómo quedaron las conexiones consolidadas ==='
SELECT id,
       musuarios_id,
       to_char(fecha AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI') AS inicio,
       to_char(ultima_actividad AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI') AS ultima_actividad,
       duracion_seg,
       cerrada,
       COALESCE(cerrada_por, '—') AS motivo
  FROM conexiones_historial
 WHERE id IN (SELECT principal FROM _cx_grupos)
 ORDER BY id DESC;

COMMIT;
