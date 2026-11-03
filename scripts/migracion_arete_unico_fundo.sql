-- =============================================================================
-- SOVCA · Índice único del N° de arete por fundo
-- -----------------------------------------------------------------------------
-- REGLA: un animal ACTIVO (status = 1) de un fundo no puede repetir el N° de
-- arete de otro animal activo del mismo fundo. El arete se compara en su forma
-- canónica (mayúsculas y sin espacios de sobra).
--
-- La validación de la aplicación vive en lib/arete_helper.php y se aplica en la
-- web, la importación, la migración entre fundos y la sincronización offline.
-- Este índice es la última red de seguridad ante dos escrituras simultáneas.
--
-- Antes de crearlo, compruebe que no haya duplicados:
--
--   SELECT mfundos_id, upper(btrim(nro_arete)), count(*)
--     FROM manimales
--    WHERE status = 1
--      AND nro_arete IS NOT NULL
--      AND length(btrim(nro_arete)) > 0
--    GROUP BY 1, 2
--   HAVING count(*) > 1;
--
-- Si aparecen, se resuelven con:
--   php scripts/auditar_aretes_duplicados.php
--   php scripts/normalizar_aretes.php --aplicar
--
-- Idempotente: se puede ejecutar varias veces.
--
-- Aplicar como dueño de la tabla (normalmente postgres):
--   psql -d sovca -f scripts/migracion_arete_unico_fundo.sql
-- =============================================================================

-- Índice único parcial: solo animales vigentes con arete.
CREATE UNIQUE INDEX IF NOT EXISTS manimales_fundo_arete_normalizado_unico_idx
    ON public.manimales USING btree (mfundos_id, upper(btrim((nro_arete)::text)))
    WHERE ((status = 1) AND (nro_arete IS NOT NULL) AND (length(btrim((nro_arete)::text)) > 0));

COMMENT ON INDEX public.manimales_fundo_arete_normalizado_unico_idx IS
    'Un arete no puede repetirse entre animales activos del mismo fundo (comparación canónica).';

-- Verificación:
--   SELECT indexdef FROM pg_indexes
--    WHERE tablename = 'manimales'
--      AND indexname = 'manimales_fundo_arete_normalizado_unico_idx';
