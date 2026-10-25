-- =============================================================================
-- Desincorporaciones: permitir volver a dar de baja a un animal cuya baja
-- anterior fue ANULADA.
--
-- Algunas instalaciones conservan un índice ÚNICO histórico sobre
-- mdesincorporacionanimales(codigo_rgb_id_animal) que no filtra por status
-- (en producción aparece con el nombre "uq_animales_por_fundo"). Con él, la
-- segunda baja del mismo animal fallaba con:
--
--   duplicate key value violates unique constraint "uq_animales_por_fundo"
--   DETAIL: Key (codigo_rgb_id_animal)=(BOVS-02-026) already exists.
--
-- El índice correcto es único por fundo y SOLO entre las bajas activas.
-- Este script es idempotente: se puede ejecutar varias veces sin riesgo.
-- =============================================================================

BEGIN;

DO $$
DECLARE
    r record;
BEGIN
    /* 1) Restricciones UNIQUE de una sola columna (codigo_rgb_id_animal) */
    FOR r IN
        SELECT c.conname
          FROM pg_constraint c
          JOIN pg_class t ON t.oid = c.conrelid
          JOIN pg_namespace n ON n.oid = t.relnamespace
         WHERE t.relname = 'mdesincorporacionanimales'
           AND n.nspname = current_schema()
           AND c.contype = 'u'
           AND pg_get_constraintdef(c.oid) LIKE '%codigo_rgb_id_animal%'
           AND pg_get_constraintdef(c.oid) NOT LIKE '%mfundos_id%'
           AND pg_get_constraintdef(c.oid) NOT LIKE '%status%'
    LOOP
        EXECUTE format('ALTER TABLE mdesincorporacionanimales DROP CONSTRAINT %I', r.conname);
        RAISE NOTICE 'Restricción única global eliminada: %', r.conname;
    END LOOP;
END $$;

DO $$
DECLARE
    r record;
BEGIN
    /* 2) Índices únicos globales que quedaron sin restricción asociada */
    FOR r IN
        SELECT i.indexname, i.indexdef
          FROM pg_indexes i
         WHERE i.schemaname = current_schema()
           AND i.tablename = 'mdesincorporacionanimales'
           AND i.indexdef LIKE 'CREATE UNIQUE INDEX%'
           AND i.indexdef LIKE '%(codigo_rgb_id_animal)%'
           AND i.indexdef NOT LIKE '%mfundos_id%'
           AND i.indexdef NOT LIKE '%WHERE%'
    LOOP
        EXECUTE format('DROP INDEX IF EXISTS %I', r.indexname);
        RAISE NOTICE 'Índice único global eliminado: %', r.indexname;
    END LOOP;
END $$;

/* 3) Índice correcto: único por fundo entre las bajas ACTIVAS.
      Se recrea para asegurar que sea UNIQUE (versiones anteriores lo crearon
      sin UNIQUE y "CREATE UNIQUE INDEX IF NOT EXISTS" no lo corrige). */
DROP INDEX IF EXISTS mdesincorporacionanimales_fundo_codigo_idx;

CREATE UNIQUE INDEX mdesincorporacionanimales_fundo_codigo_idx
    ON mdesincorporacionanimales (mfundos_id, codigo_rgb_id_animal)
    WHERE status = 1;

COMMIT;

-- Comprobación posterior:
--   SELECT indexname, indexdef FROM pg_indexes
--    WHERE tablename = 'mdesincorporacionanimales' ORDER BY indexname;
