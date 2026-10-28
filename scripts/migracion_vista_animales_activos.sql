-- ===========================================================================
--  scripts/migracion_vista_animales_activos.sql
-- ---------------------------------------------------------------------------
--  Deja la vista v_list_animales_activos con el MISMO criterio de «animal
--  activo» que usa el resto del sistema (lib/animales_conteo_helper.php):
--
--      status = 1 AND mestatus_id = 1 AND estatus_animal IN (1, 2, 4)
--
--  Antes la vista filtraba con `estatus_animal <> 3`, lo que dejaba pasar a
--  DONADORA (5) —animales que no están activos— y no mostraba su descripción
--  en la columna estatus_animal (solo tenía ACTIVO y DONADOR ACTIVO).
--
--  Aplicar:
--      PGPASSWORD=... psql -h 127.0.0.1 -U postgres -d sovca \
--          -f scripts/migracion_vista_animales_activos.sql
--
--  Es idempotente (CREATE OR REPLACE VIEW).
-- ===========================================================================

CREATE OR REPLACE VIEW v_list_animales_activos AS
 SELECT a.id AS id_animal,
    a.codigo_rgb_id_animal AS codigo_animal,
    a.nombre AS nombre_animal,
    a.sexo,
        CASE
            WHEN a.sexo = 1 THEN 'HEMBRA'::text
            WHEN a.sexo = 2 THEN 'MACHO'::text
            ELSE NULL::text
        END AS sexo_animal,
        CASE
            WHEN a.estatus_animal = 1 THEN 'ACTIVO'::text
            WHEN a.estatus_animal = 2 THEN 'DONADOR ACTIVO'::text
            WHEN a.estatus_animal = 3 THEN 'DONADOR'::text
            WHEN a.estatus_animal = 4 THEN 'DONADORA ACTIVO'::text
            WHEN a.estatus_animal = 5 THEN 'DONADORA'::text
            ELSE NULL::text
        END AS estatus_animal,
        CASE
            WHEN a.imagen::text <> ''::text THEN a.imagen
            ELSE 'bovinos.png'::character varying
        END AS imagen,
    a.mestatus_id,
    a.nro_arete AS nro_chapeta,
    ( SELECT x.nombre
           FROM mcategorias x
          WHERE x.id = a.categoria_ingreso_id) AS categoria_ingreso,
    ( SELECT y.nombre
           FROM mcategorias y
          WHERE y.id = a.categoria_futura_id) AS categoria_futura,
    ( SELECT z.nombre
           FROM maptitudes z
          WHERE z.id = a.aptitud) AS aptitud,
    m.nombre AS raza,
    a.f_nacimiento,
    a.peso_nacimiento,
    a.mfundos_id,
    c.nombre AS nombre_lote,
    c.id AS lote,
    date_part('year'::text, age(now(), date(a.f_nacimiento)::timestamp with time zone)) AS anios,
    date_part('month'::text, age(now(), date(a.f_nacimiento)::timestamp with time zone)) AS meses,
    date_part('day'::text, age(now(), date(a.f_nacimiento)::timestamp with time zone)) AS dias
   FROM manimales a
     LEFT JOIN mrazas m ON m.id = a.mrazas_id
     LEFT JOIN mlotes c ON c.id = a.mlotes_id,
    manimalxfundos b,
    mfundos e
  WHERE a.mestatus_id = 1 AND a.status = 1 AND (a.estatus_animal = ANY (ARRAY[1, 2, 4]))
    AND a.mfundos_id = b.mfundos_id AND a.id = b.manimales_id AND b.mfundos_id = e.id;
