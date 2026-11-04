-- =============================================================================
-- CÓDIGO DE LOS PERÍODOS REPRODUCTIVOS (mperiodos.codper_bov)
--
-- La migración desde BOVS agregó la columna `codper_bov` con valor por
-- defecto '0' pero no copió el código de cada período. Los controladores
-- buscan los períodos por código:
--
--     PGB  Período de gestación        (fecha probable de parto)
--     PSP  Período servicio/palpación  (fecha probable de palpación, PP)
--     PPS  Período parto/servicio      (celo / días no productivos)
--     PPL  Período de producción de leche
--     PBL  Período de lactancia        (destete)
--     PSD  Período de secado           (210 ovino · 120 caprino)
--     EMM  Edad mínima del macho para servicio (días)
--     EHM  Edad mínima de la hembra para servicio (días)
--
-- Sin este dato, los formularios de Servicio y Control mostraban vacíos
-- "Periodo de Palpación", "Periodo de Gestación" y "Fecha Prob. Palpación".
--
-- Idempotente: solo completa filas con codper_bov nulo, vacío o '0'.
-- Uso:
--     psql -h 127.0.0.1 -U postgres -d sovca -f scripts/migracion_periodos_codper_bov.sql
-- =============================================================================

BEGIN;

UPDATE public.mperiodos
   SET codper_bov = 'PGB'
 WHERE (codper_bov IS NULL OR btrim(codper_bov) IN ('', '0'))
   AND upper(nombre) LIKE 'PERIODO DE GESTACION%';

UPDATE public.mperiodos
   SET codper_bov = 'PSP'
 WHERE (codper_bov IS NULL OR btrim(codper_bov) IN ('', '0'))
   AND (upper(nombre) LIKE 'PERIODO SERVICIO/PALPACION%'
        OR upper(nombre) LIKE 'PERIODO SERVICIO / PALPACION%');

UPDATE public.mperiodos
   SET codper_bov = 'PPS'
 WHERE (codper_bov IS NULL OR btrim(codper_bov) IN ('', '0'))
   AND (upper(nombre) LIKE 'PERIODO PARTO/SERVICIO%'
        OR upper(nombre) LIKE 'PERIODO PARTO / SERVICIO%');

UPDATE public.mperiodos
   SET codper_bov = 'PPL'
 WHERE (codper_bov IS NULL OR btrim(codper_bov) IN ('', '0'))
   AND (upper(nombre) LIKE 'PERIODO DE PRODUCCION DE LECHE%'
        OR upper(nombre) LIKE 'PERIODO DE PRODUCION DE LECHE%');

UPDATE public.mperiodos
   SET codper_bov = 'PBL'
 WHERE (codper_bov IS NULL OR btrim(codper_bov) IN ('', '0'))
   AND upper(nombre) LIKE 'PERIODO DE LACTANCIA%';

UPDATE public.mperiodos
   SET codper_bov = 'PSD'
 WHERE (codper_bov IS NULL OR btrim(codper_bov) IN ('', '0'))
   AND upper(nombre) LIKE 'PERIODO DE SECADO%';

COMMIT;

-- Comprobación:
--   SELECT id, codper_bov, nombre, dias_gestacion, mfundos_id, mtipoganaderias_id
--     FROM mperiodos ORDER BY mtipoganaderias_id, mfundos_id, codper_bov;
--   No debe quedar ningún período reproductivo con codper_bov = '0'.
