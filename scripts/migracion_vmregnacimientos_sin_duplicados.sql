-- =============================================================================
-- SOVCA · vmregnacimientos sin filas duplicadas
-- -----------------------------------------------------------------------------
-- PROBLEMA
--   La vista unía a `manimales` con un LEFT JOIN simple:
--
--     LEFT JOIN manimales b
--            ON a.mfundos_id = b.mfundos_id
--           AND b.mestatus_id = 1
--           AND b.codigo_rgb_id_animal = a.codigo_rgb_id_animal_hembra
--
--   Si en el hato existen dos filas con el mismo código de la madre (una
--   activa y otra dada de baja, algo habitual tras una migración o una
--   corrección), el nacimiento sale DOS veces. Combinado con el join a las
--   crías que hace el reporte, un parto podía aparecer hasta 3 o 4 veces.
--
--   Además, las subconsultas de nombre_h y nombre_m devuelven más de una fila
--   cuando hay códigos repetidos, y PostgreSQL aborta con
--   "more than one row returned by a subquery".
--
-- SOLUCIÓN
--   LEFT JOIN LATERAL ... ORDER BY c.id LIMIT 1 en los tres casos: la vista
--   devuelve exactamente una fila por nacimiento, siempre.
--
-- Aplicar:
--   psql -d sovca -f scripts/migracion_vmregnacimientos_sin_duplicados.sql
-- =============================================================================

CREATE OR REPLACE VIEW public.vmregnacimientos AS
 SELECT a.id,
    a.codigo_ternero,
    a.id_ternero,
    a.codigo_rgb_id_animal_macho,
    a.codigo_rgb_id_animal_hembra,
    a.codigo_rgb_id_animal_dona,
    a.f_parto,
    a.peso_nacimiento,
    a.sexo,
    a.nro_parto,
    a.estatus_nacido,
    a.produccion_leche,
    a.proposito,
    a.observaciones,
    a.mfundos_id,
    a.f_secado,
    a.aptitud,
    a.mtiposervicios_id,
    a.mestatus_id,
    a.status AS estatus_regnac,
        CASE
            WHEN a.fichado = true THEN 'FICHADO'::text
            ELSE 'Sin Fichar'::text
        END AS fichado,
    madre.status AS estatus_animal,
    madre.nro_arete,
    nombre_h.nombre AS nombre_h,
    nombre_m.nombre AS nombre_m,
    /* Columnas propias de SOVCA (no están en la vista de BOVS): se conservan
       para que las pantallas y reportes de SOVCA sigan funcionando. */
    a.lactancia,
    a.mrazas_id AS raza
   FROM public.mregnacimientos a
     LEFT JOIN LATERAL (
          SELECT c.status, c.nro_arete
            FROM public.manimales c
           WHERE c.mfundos_id = a.mfundos_id
             AND c.mestatus_id = 1
             AND c.codigo_rgb_id_animal = a.codigo_rgb_id_animal_hembra
           ORDER BY c.id
           LIMIT 1
     ) madre ON true
     LEFT JOIN LATERAL (
          SELECT c.nombre
            FROM public.manimales c
           WHERE c.codigo_rgb_id_animal = a.codigo_rgb_id_animal_hembra
           ORDER BY c.id
           LIMIT 1
     ) nombre_h ON true
     LEFT JOIN LATERAL (
          SELECT c.nombre
            FROM public.manimales c
           WHERE c.codigo_rgb_id_animal = a.codigo_rgb_id_animal_macho
           ORDER BY c.id
           LIMIT 1
     ) nombre_m ON true;

COMMENT ON VIEW public.vmregnacimientos IS
'Una fila por nacimiento. Usa LATERAL ... LIMIT 1 para la madre y los nombres: con JOIN simple la vista multiplicaba filas cuando había animales con el mismo código.';
