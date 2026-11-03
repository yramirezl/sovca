-- ===========================================================================
--  scripts/migracion_vmregnacimientos_tipo_servicio.sql
-- ---------------------------------------------------------------------------
--  La vista `vmregnacimientos` unía con `mtiposervicios` mediante un INNER JOIN
--  implícito:
--
--      FROM mregnacimientos a, manimales b, mtiposervicios e
--      WHERE a.mtiposervicios_id = e.id AND ...
--
--  Consecuencia: todo parto con `mtiposervicios_id = 0` (o con un id que ya no
--  existe en el catálogo) DESAPARECÍA del listado de Nacimientos, de la ficha
--  y de los reportes, aunque el registro estuviera activo. Casos reales:
--  SOVCA-063-0177 (registro 881), 265 y 905.
--
--  Esta migración recrea la vista con LEFT JOIN: el parto se muestra siempre.
--  Las columnas son exactamente las mismas, así que no cambia ninguna consulta.
--  El tipo de servicio sigue disponible en `mtiposervicios_id` y se corrige con
--  scripts/corregir_tipo_servicio_nacimientos.php.
--
--  Idempotente: se puede ejecutar varias veces (CREATE OR REPLACE VIEW).
--
--  Aplicar como el dueño de la vista (normalmente `postgres`), porque
--  `sovca_app` no puede reemplazarla:
--      sudo -u postgres psql -d sovca -f scripts/migracion_vmregnacimientos_tipo_servicio.sql
-- ===========================================================================

BEGIN;

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
    b.status AS estatus_animal,
    b.nro_arete,
    ( SELECT c.nombre
           FROM manimales c
          WHERE a.codigo_rgb_id_animal_hembra::text = c.codigo_rgb_id_animal::text) AS nombre_h,
    ( SELECT c.nombre
           FROM manimales c
          WHERE a.codigo_rgb_id_animal_macho::text = c.codigo_rgb_id_animal::text) AS nombre_m,
    /* Columnas propias de SOVCA: se conservan para no romper sus pantallas. */
    a.lactancia,
    a.mrazas_id AS raza
   FROM mregnacimientos a
   LEFT JOIN manimales b
          ON a.mfundos_id = b.mfundos_id
         AND b.mestatus_id = 1
         AND b.codigo_rgb_id_animal::text = a.codigo_rgb_id_animal_hembra::text
   LEFT JOIN mtiposervicios e
          ON a.mtiposervicios_id = e.id;

COMMENT ON VIEW public.vmregnacimientos IS
  'Nacimientos con datos de la madre. LEFT JOIN a mtiposervicios y manimales: un tipo de servicio en 0 o una madre dada de baja NO ocultan el parto.';

COMMIT;

-- Verificación rápida (debe devolver los partos con tipo 0):
--   SELECT id, codigo_ternero, mtiposervicios_id
--     FROM vmregnacimientos
--    WHERE COALESCE(mtiposervicios_id, 0) = 0;
