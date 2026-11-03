-- =============================================================================
-- SOVCA · Nacimientos con el padre guardado como número de arete / texto libre
-- -----------------------------------------------------------------------------
-- PROBLEMA
--   El alta y la edición de nacimientos solo comprobaban que el campo del
--   padre NO estuviera vacío. Si el operador tecleaba el número de arete y la
--   búsqueda heredada fallaba (toro dado de baja o fuera de servicio), el
--   texto tecleado se guardaba tal cual en
--   `mregnacimientos.codigo_rgb_id_animal_macho`.
--
--   En el fundo 063 hay 13 partos con el valor «2005», que no es un código:
--   es el NRO DE ARETE del toro SOVCA-063-078. En el listado aparecía como si
--   fuera el código del padre y al editar el registro el padre salía vacío,
--   porque ningún animal tiene ese código.
--   Además hay 2 partos con texto libre («Brahma rojo», «Holstein rojo
--   carora»), que tampoco corresponden a ningún animal del fundo.
--
-- QUÉ HACE ESTE SCRIPT (no borra ningún parto, solo corrige el padre)
--   1. Si el valor guardado es el nro de arete de UN SOLO macho activo del
--      mismo fundo, se reemplaza por el código real de ese animal.
--   2. Si el valor no es ni código ni arete de ningún macho del fundo, se
--      deja el padre VACÍO: estaba inventado, y así el listado no muestra un
--      «código fantasma».
--
--   Los nacimientos siguen intactos: fecha, madre, crías, peso, etc.
--
-- Aplicar:
--   psql -d sovca -f scripts/migracion_nacimientos_macho_codigo.sql
-- =============================================================================

BEGIN;

-- -----------------------------------------------------------------------------
-- Informe PREVIO: qué se va a corregir
-- -----------------------------------------------------------------------------
SELECT 'ANTES · valor que es un nro de arete (se convertirá al código)' AS paso,
       n.mfundos_id,
       n.codigo_rgb_id_animal_macho AS valor_actual,
       (SELECT m.codigo_rgb_id_animal
          FROM manimales m
         WHERE m.mfundos_id = n.mfundos_id
           AND m.sexo = 2
           AND m.status = 1
           AND btrim(coalesce(m.nro_arete, '')) <> ''
           AND upper(btrim(m.nro_arete)) = upper(btrim(n.codigo_rgb_id_animal_macho))
         ORDER BY m.id
         LIMIT 1) AS codigo_nuevo,
       count(*) AS partos
  FROM mregnacimientos n
 WHERE n.status = 1
   AND btrim(coalesce(n.codigo_rgb_id_animal_macho, '')) <> ''
   AND NOT EXISTS (SELECT 1
                     FROM manimales m
                    WHERE m.mfundos_id = n.mfundos_id
                      AND upper(btrim(m.codigo_rgb_id_animal)) = upper(btrim(n.codigo_rgb_id_animal_macho)))
   AND (SELECT count(*)
          FROM manimales m
         WHERE m.mfundos_id = n.mfundos_id
           AND m.sexo = 2
           AND m.status = 1
           AND btrim(coalesce(m.nro_arete, '')) <> ''
           AND upper(btrim(m.nro_arete)) = upper(btrim(n.codigo_rgb_id_animal_macho))) = 1
 GROUP BY n.mfundos_id, n.codigo_rgb_id_animal_macho
 ORDER BY n.mfundos_id, n.codigo_rgb_id_animal_macho;

SELECT 'ANTES · valor sin correspondencia (se dejará el padre vacío)' AS paso,
       n.mfundos_id,
       n.codigo_rgb_id_animal_macho AS valor_actual,
       count(*) AS partos,
       string_agg(n.id::text, ', ' ORDER BY n.id) AS ids
  FROM mregnacimientos n
 WHERE n.status = 1
   AND btrim(coalesce(n.codigo_rgb_id_animal_macho, '')) <> ''
   AND NOT EXISTS (SELECT 1
                     FROM manimales m
                    WHERE m.mfundos_id = n.mfundos_id
                      AND upper(btrim(m.codigo_rgb_id_animal)) = upper(btrim(n.codigo_rgb_id_animal_macho)))
   AND NOT EXISTS (SELECT 1
                     FROM manimales m
                    WHERE m.mfundos_id = n.mfundos_id
                      AND m.sexo = 2
                      AND m.status = 1
                      AND btrim(coalesce(m.nro_arete, '')) <> ''
                      AND upper(btrim(m.nro_arete)) = upper(btrim(n.codigo_rgb_id_animal_macho)))
 GROUP BY n.mfundos_id, n.codigo_rgb_id_animal_macho
 ORDER BY n.mfundos_id, n.codigo_rgb_id_animal_macho;

-- -----------------------------------------------------------------------------
-- 1) Nro de arete inequívoco -> código real del macho
-- -----------------------------------------------------------------------------
WITH candidatos AS (
    SELECT n.id AS nac_id,
           (SELECT m.codigo_rgb_id_animal
              FROM manimales m
             WHERE m.mfundos_id = n.mfundos_id
               AND m.sexo = 2
               AND m.status = 1
               AND btrim(coalesce(m.nro_arete, '')) <> ''
               AND upper(btrim(m.nro_arete)) = upper(btrim(n.codigo_rgb_id_animal_macho))
             ORDER BY m.id
             LIMIT 1) AS codigo_real,
           (SELECT count(*)
              FROM manimales m
             WHERE m.mfundos_id = n.mfundos_id
               AND m.sexo = 2
               AND m.status = 1
               AND btrim(coalesce(m.nro_arete, '')) <> ''
               AND upper(btrim(m.nro_arete)) = upper(btrim(n.codigo_rgb_id_animal_macho))) AS coincidencias
      FROM mregnacimientos n
     WHERE n.status = 1
       AND btrim(coalesce(n.codigo_rgb_id_animal_macho, '')) <> ''
       AND NOT EXISTS (SELECT 1
                         FROM manimales m
                        WHERE m.mfundos_id = n.mfundos_id
                          AND upper(btrim(m.codigo_rgb_id_animal)) = upper(btrim(n.codigo_rgb_id_animal_macho)))
)
UPDATE mregnacimientos n
   SET codigo_rgb_id_animal_macho = c.codigo_real
  FROM candidatos c
 WHERE n.id = c.nac_id
   AND c.coincidencias = 1
   AND c.codigo_real IS NOT NULL
   AND c.codigo_real <> '';

-- -----------------------------------------------------------------------------
-- 2) Valores que no son código ni arete -> el parto queda SIN padre
--    (el dato estaba inventado; no se pierde nada real)
-- -----------------------------------------------------------------------------
UPDATE mregnacimientos n
   SET codigo_rgb_id_animal_macho = ''
 WHERE n.status = 1
   AND btrim(coalesce(n.codigo_rgb_id_animal_macho, '')) <> ''
   AND NOT EXISTS (SELECT 1
                     FROM manimales m
                    WHERE m.mfundos_id = n.mfundos_id
                      AND upper(btrim(m.codigo_rgb_id_animal)) = upper(btrim(n.codigo_rgb_id_animal_macho)))
   AND NOT EXISTS (SELECT 1
                     FROM manimales m
                    WHERE m.mfundos_id = n.mfundos_id
                      AND m.sexo = 2
                      AND m.status = 1
                      AND btrim(coalesce(m.nro_arete, '')) <> ''
                      AND upper(btrim(m.nro_arete)) = upper(btrim(n.codigo_rgb_id_animal_macho)));

-- -----------------------------------------------------------------------------
-- Informe POSTERIOR: deben quedar 0 filas
-- -----------------------------------------------------------------------------
SELECT 'DESPUES · padres que siguen sin corresponder a un animal' AS paso,
       count(*) AS partos
  FROM mregnacimientos n
 WHERE n.status = 1
   AND btrim(coalesce(n.codigo_rgb_id_animal_macho, '')) <> ''
   AND NOT EXISTS (SELECT 1
                     FROM manimales m
                    WHERE m.mfundos_id = n.mfundos_id
                      AND upper(btrim(m.codigo_rgb_id_animal)) = upper(btrim(n.codigo_rgb_id_animal_macho)));

COMMIT;
