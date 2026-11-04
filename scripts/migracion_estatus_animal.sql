-- =============================================================================
-- Catálogo de ESTATUS DEL ANIMAL: columna `sexo`
--
-- La ficha del animal filtra el combo "Estatus" según el sexo:
--
--     hembra (1) → 1 ACTIVO · 4 DONADORA ACTIVO · 5 DONADORA
--     macho  (2) → 1 ACTIVO · 2 DONADOR ACTIVO  · 3 DONADOR
--     ambos  (0) → 0 = genérico (ACTIVO)
--
-- El catálogo se sembró sin la columna `sexo`, así que todas las filas
-- quedaron en 0 ("ambos") y las hembras veían también DONADOR / DONADOR
-- ACTIVO. Esta migración corrige los datos existentes.
--
-- Idempotente: se puede ejecutar varias veces.
-- Uso:
--     psql -h 127.0.0.1 -U postgres -d sovca -f scripts/migracion_estatus_animal.sql
-- =============================================================================

INSERT INTO public.mestatusanimales (id, nombre, sexo)
SELECT * FROM (VALUES (1,'ACTIVO',0),(2,'DONADOR ACTIVO',2),(3,'DONADOR',2),
                      (4,'DONADORA ACTIVO',1),(5,'DONADORA',1)) AS v(id,nombre,sexo)
ON CONFLICT (id) DO NOTHING;

UPDATE public.mestatusanimales m
   SET sexo = v.sexo
  FROM (VALUES (1,0),(2,2),(3,2),(4,1),(5,1)) AS v(id,sexo)
 WHERE m.id = v.id
   AND m.sexo IS DISTINCT FROM v.sexo;

-- Comprobación:
--   SELECT id, nombre, sexo, status FROM mestatusanimales ORDER BY id;
--   Esperado: 1|ACTIVO|0 · 2|DONADOR ACTIVO|2 · 3|DONADOR|2
--             4|DONADORA ACTIVO|1 · 5|DONADORA|1
