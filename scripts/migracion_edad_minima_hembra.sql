-- =============================================================================
-- EDAD MÍNIMA DE LA HEMBRA PARA REGISTRAR SERVICIO
--
-- El sistema arrastraba la regla bovina de exigir 12 meses a la hembra.
-- En ovinos y caprinos la pubertad llega alrededor de los 7 meses: el
-- mínimo debe ser 210 DÍAS. Con la regla anterior se rechazaban hembras
-- de 10 meses que ya están aptas (ver imágenes del módulo de Servicios).
--
-- El valor se guarda como un período más (`mperiodos`, código `EHM`),
-- igual que PSP / PPS / PGB / EMM, para ajustarlo desde
-- Parámetros → Períodos:
--
--     Nombre del Periodo : EDAD MINIMA DE LA HEMBRA PARA SERVICIO (DIAS)
--     Días de Gestación  : 210        (≈ 7 meses)
--
-- Resolución en lib/edad_hembra_helper.php:
--     1) fila del fundo para su tipo de ganadería,
--     2) fila global (mfundos_id = 0) del mismo tipo,
--     3) 210 días por defecto.
--
-- Idempotente: se puede ejecutar varias veces.
-- Uso:
--     psql -h 127.0.0.1 -U postgres -d sovca -f scripts/migracion_edad_minima_hembra.sql
-- =============================================================================

INSERT INTO mperiodos (nombre, dias_gestacion, mfundos_id, codper_bov,
                       mtipoganaderias_id, status, created)
SELECT 'EDAD MINIMA DE LA HEMBRA PARA SERVICIO (DIAS)', 210, 0, 'EHM', t.id, 1, now()
  FROM (VALUES (1), (2)) AS t(id)
 WHERE NOT EXISTS (
       SELECT 1
         FROM mperiodos p
        WHERE p.codper_bov = 'EHM'
          AND p.mfundos_id = 0
          AND p.mtipoganaderias_id = t.id
 );

-- Comprobación:
--   SELECT id, nombre, codper_bov, dias_gestacion, mfundos_id, mtipoganaderias_id
--     FROM mperiodos WHERE codper_bov = 'EHM' ORDER BY mtipoganaderias_id;
