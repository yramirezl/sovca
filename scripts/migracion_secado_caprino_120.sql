-- =============================================================================
-- SECADO / LACTANCIA EN OVINOS Y CAPRINOS
--
-- El sistema arrastraba valores bovinos (7 meses ≈ 210 días) para el secado.
-- En caprinos la lactancia es más corta: el secado debe trabajarse con
-- 4 MESES (120 DÍAS). Además, el módulo de Nacimientos necesita el período
-- de lactancia (PBL) para calcular la fecha probable de destete, y en las
-- bases migradas desde BOVS esa fila no existía.
--
-- Esta migración:
--   1. Crea el período PSD (PERIODO DE SECADO) con 210 días para ovinos y
--      120 días para caprinos.
--   2. Crea el período PBL (PERIODO DE LACTANCIA / DESTETE) con 120 días.
--   3. Ajusta el PPL global de caprinos a 120 días (4 meses), que es el que
--      usa el control del servicio para el secado normal.
--
-- Es idempotente: se puede ejecutar varias veces.
-- Uso:
--     psql -h 127.0.0.1 -U postgres -d sovca -f scripts/migracion_secado_caprino_120.sql
-- =============================================================================

BEGIN;

-- 1) Días de secado (ajuste por servicio antes de 180 días post-parto)
INSERT INTO public.mperiodos (nombre, dias_gestacion, mfundos_id, codper_bov,
                              mtipoganaderias_id, status, created)
SELECT 'PERIODO DE SECADO (DIAS)', v.dias, 0, 'PSD', v.tipo, 1, now()
  FROM (VALUES (1, 210), (2, 120), (3, 120)) AS v(tipo, dias)
 WHERE NOT EXISTS (
       SELECT 1
         FROM public.mperiodos p
        WHERE p.codper_bov = 'PSD'
          AND p.mfundos_id = 0
          AND p.mtipoganaderias_id = v.tipo
 );

-- 2) Período de lactancia / destete (PBL)
INSERT INTO public.mperiodos (nombre, dias_gestacion, mfundos_id, codper_bov,
                              mtipoganaderias_id, status, created)
SELECT 'PERIODO DE LACTANCIA', 120, 0, 'PBL', v.tipo, 1, now()
  FROM (VALUES (1), (2), (3)) AS v(tipo)
 WHERE NOT EXISTS (
       SELECT 1
         FROM public.mperiodos p
        WHERE p.codper_bov = 'PBL'
          AND p.mfundos_id = 0
          AND p.mtipoganaderias_id = v.tipo
 );

-- 3) PPL y PBL globales de caprinos: 4 meses (120 días). Los períodos
--    propios de cada fundo se respetan; se ajustan desde Parámetros → Períodos.
UPDATE public.mperiodos
   SET dias_gestacion = 120
 WHERE codper_bov IN ('PPL', 'PBL')
   AND mtipoganaderias_id = 2
   AND mfundos_id = 0
   AND dias_gestacion <> 120;

COMMIT;

-- Comprobación:
--   SELECT codper_bov, mtipoganaderias_id, dias_gestacion, mfundos_id
--     FROM mperiodos
--    WHERE codper_bov IN ('PSD', 'PBL', 'PPL')
--    ORDER BY codper_bov, mtipoganaderias_id, mfundos_id;
