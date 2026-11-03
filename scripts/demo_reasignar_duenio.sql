-- =============================================================================
-- SOVCA | Reasigna al usuario de la aplicación los objetos de sovca_demo
-- -----------------------------------------------------------------------------
-- Cuando la demo se instaló con otro rol (normalmente `postgres`), el
-- sincronizador de esquema no puede crear columnas, índices ni vistas:
--
--   must be owner of table manimales
--   permission denied for schema public
--
-- `REASSIGN OWNED BY postgres TO ...` NO sirve: postgres también es dueño de
-- objetos del sistema y PostgreSQL lo rechaza. Este script cambia el dueño
-- objeto por objeto, solo dentro del esquema `public` de la base actual.
--
-- Uso (el nombre del rol es el de SOVCA_DB_USER, en producción `sovca_app`):
--
--   cd /var/www/html/sovca
--   sudo -u postgres psql -d sovca_demo -v duenio=sovca_app \
--        < scripts/demo_reasignar_duenio.sql
--
-- Se pasa por stdin (<) y no con -f porque los archivos del repositorio
-- quedan 640 root:www-data y el usuario postgres no puede abrirlos.
--
-- Es idempotente: si ya está todo en orden no modifica nada.
-- =============================================================================

\set ON_ERROR_STOP on

-- El rol tiene que existir antes de intentar cambiar dueños.
SELECT count(*) = 0 AS falta FROM pg_roles WHERE rolname = :'duenio' \gset
\if :falta
\echo 'ERROR: el rol indicado en -v duenio=... no existe.'
\q
\endif

\echo '· Base:' :DBNAME '→ dueño destino:' :duenio

-- 1) La base. En PostgreSQL 15+ el esquema `public` pertenece a
--    `pg_database_owner`: si el dueño de la base es el usuario de la app,
--    ese usuario puede crear objetos sin tocar permisos del esquema.
SELECT format('ALTER DATABASE %I OWNER TO %I', current_database(), :'duenio')
\gexec

-- 2) Tablas, particiones, vistas, vistas materializadas, secuencias y
--    tablas foráneas del esquema public.
--    Las secuencias ligadas a una columna (SERIAL / IDENTITY) no se tocan:
--    PostgreSQL no deja cambiarles el dueño, pero siguen a su tabla cuando
--    se hace ALTER TABLE ... OWNER TO.
SELECT format('ALTER %s %s OWNER TO %I',
              CASE c.relkind
                   WHEN 'v' THEN 'VIEW'
                   WHEN 'm' THEN 'MATERIALIZED VIEW'
                   WHEN 'S' THEN 'SEQUENCE'
                   WHEN 'f' THEN 'FOREIGN TABLE'
                   ELSE 'TABLE'
              END,
              c.oid::regclass,
              :'duenio')
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relkind IN ('r', 'p', 'v', 'm', 'S', 'f')
  AND pg_get_userbyid(c.relowner) <> :'duenio'
  AND NOT (c.relkind = 'S' AND EXISTS (
        SELECT 1 FROM pg_depend d
        WHERE d.objid = c.oid AND d.deptype IN ('a', 'i')))
ORDER BY 1
\gexec

-- 3) Funciones y procedimientos.
SELECT format('ALTER %s %s OWNER TO %I',
              CASE p.prokind WHEN 'p' THEN 'PROCEDURE' ELSE 'FUNCTION' END,
              p.oid::regprocedure,
              :'duenio')
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND pg_get_userbyid(p.proowner) <> :'duenio'
ORDER BY 1
\gexec

-- 4) Comprobación final.
SELECT '· objetos de public con otro dueño: ' || count(*) AS pendientes
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relkind IN ('r', 'p', 'v', 'm', 'S', 'f')
  AND pg_get_userbyid(c.relowner) <> :'duenio';

\echo 'Listo. Ahora: sudo php scripts/demo_actualizar_esquema.php --aplicar'
