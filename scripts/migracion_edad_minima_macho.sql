-- =============================================================================
-- Edad mínima del MACHO para registrar un servicio
--
-- Hasta ahora el sistema exigía 12 meses a la hembra pero no controlaba la
-- edad del macho: se podía elegir un becerro de 6 meses como "toro de monta"
-- al registrar un servicio por MONTA.
--
-- El valor se guarda como un período más (`mperiodos`), igual que PSP / PPS /
-- PGB, así el productor lo ajusta desde Parámetros → Períodos:
--
--     Nombre del Periodo : EDAD MINIMA DEL MACHO PARA SERVICIO (DIAS)
--     Días de Gestación  : 365        (≈ 12 meses)
--
-- Resolución (igual que los otros períodos):
--     1) fila del fundo para su tipo de ganadería,
--     2) fila global (mfundos_id = 0) del mismo tipo de ganadería,
--     3) 365 días por defecto (valor de esta migración).
--
-- Idempotente: se puede ejecutar varias veces.
-- =============================================================================

INSERT INTO mperiodos (nombre, dias_gestacion, mfundos_id, codper_bov,
                       mtipoganaderias_id, status, created)
SELECT 'EDAD MINIMA DEL MACHO PARA SERVICIO (DIAS)', 365, 0, 'EMM', t.id, 1, now()
  FROM (VALUES (1), (2)) AS t(id)
 WHERE NOT EXISTS (
       SELECT 1
         FROM mperiodos p
        WHERE p.codper_bov = 'EMM'
          AND p.mfundos_id = 0
          AND p.mtipoganaderias_id = t.id
 );

-- Comprobación:
--   SELECT id, nombre, codper_bov, dias_gestacion, mfundos_id, mtipoganaderias_id
--     FROM mperiodos WHERE codper_bov = 'EMM' ORDER BY mtipoganaderias_id, mfundos_id;
