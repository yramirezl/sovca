-- =============================================================================
-- Aforo de forraje verde por potrero
--
-- `aforo` = kilogramos de forraje verde disponibles por hectárea (kg/ha).
-- El forraje total del potrero se obtiene multiplicando por las hectáreas.
--
-- Con ese dato, la rotación calcula cuántos días puede pastorear un grupo:
--     consumo diario = peso total del lote × 10 %
--     días de ocupación = forraje disponible / consumo diario
--
-- Idempotente: se puede ejecutar varias veces.
-- =============================================================================

ALTER TABLE mpotreros
    ADD COLUMN IF NOT EXISTS aforo numeric(10,2);

COMMENT ON COLUMN mpotreros.aforo IS
    'Forraje verde disponible en kg por hectárea (aforo).';

-- Comprobación:
--   SELECT id, nombre, hectareas, aforo, (COALESCE(aforo,0) * COALESCE(hectareas,0)) AS forraje_total
--     FROM mpotreros WHERE status = 1 ORDER BY id;
