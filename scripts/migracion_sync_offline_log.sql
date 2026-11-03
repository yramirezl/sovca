-- =====================================================================
-- scripts/migracion_sync_offline_log.sql
-- ---------------------------------------------------------------------
-- RESUMEN DE SINCRONIZACIONES DEL MODO OFFLINE
--
-- Hasta ahora el servidor solo sabía CUÁNDO sincronizó cada equipo
-- (usuario_dispositivo_offline.ultima_sincronizacion) y nada más: si una
-- tanda de cambios era rechazada, el único rastro quedaba en el equipo
-- del productor (outbox.ultimo_error) o en el log del servidor
-- ([SYNC PUSH]), invisible para el administrador.
--
-- Esta tabla guarda UN RENGLÓN POR PETICIÓN DE SINCRONIZACIÓN
-- (pull / push / subir_foto) con:
--   · el dispositivo, usuario y fundo que la hizo,
--   · cuántos registros viajaron, cuántos entraron, cuántos chocaron y
--     cuántos fueron rechazados,
--   · el detalle de cada rechazo (módulo + registro + motivo) en
--     `detalle` (jsonb), que es lo que permite ver DÓNDE falló.
--
-- Se registran SIEMPRE los push (son las operaciones del productor) y los
-- pull cuando traen datos o cuando fallan; los pull vacíos se omiten para
-- no llenar la tabla de ruido.
--
-- Idempotente: se puede correr varias veces sin efectos secundarios.
-- =====================================================================

BEGIN;

CREATE TABLE IF NOT EXISTS public.sync_offline_log (
    id                 bigserial PRIMARY KEY,
    dispositivo_id     uuid,
    nombre_dispositivo character varying(120),
    musuarios_id       integer,
    mfundos_id         integer,
    modo_offline       character varying(20),
    -- pull | push | subir_foto
    accion             character varying(15) NOT NULL,
    -- animales, servicios, nacimientos, control_sanitario, fotos...
    modulo             character varying(40),
    -- ok | parcial | error
    resultado          character varying(10) NOT NULL DEFAULT 'ok',
    total              integer NOT NULL DEFAULT 0,
    exitos             integer NOT NULL DEFAULT 0,
    conflictos         integer NOT NULL DEFAULT 0,
    errores            integer NOT NULL DEFAULT 0,
    duracion_ms        integer NOT NULL DEFAULT 0,
    ip                 character varying(45),
    mensaje            text,
    detalle            jsonb,
    created            timestamp without time zone NOT NULL DEFAULT now()
);

COMMENT ON TABLE public.sync_offline_log IS
    'Una fila por petición de sincronización del modo offline (pull/push/foto). Alimenta el resumen de sincronizaciones de Dispositivos Conectados.';
COMMENT ON COLUMN public.sync_offline_log.detalle IS
    'Lista JSON de fallos: [{ tipo, modulo, referencia, uuid_local, mensaje, codigo }]';
COMMENT ON COLUMN public.sync_offline_log.resultado IS
    'ok = todo entró · parcial = hubo conflictos o registros rechazados · error = la petición entera falló (403/422/500)';

-- Consultas de la pantalla: por equipo (más reciente primero) y por fundo.
CREATE INDEX IF NOT EXISTS sync_offline_log_dispositivo_idx
    ON public.sync_offline_log (dispositivo_id, created DESC);

CREATE INDEX IF NOT EXISTS sync_offline_log_fundo_idx
    ON public.sync_offline_log (mfundos_id, created DESC);

-- Índice parcial: la pantalla filtra "solo con fallos" y busca el último
-- fallo de cada equipo sin recorrer los push exitosos.
CREATE INDEX IF NOT EXISTS sync_offline_log_fallos_idx
    ON public.sync_offline_log (created DESC)
    WHERE resultado <> 'ok';

COMMIT;

-- ---------------------------------------------------------------------
-- PURGA (opcional, ejecutar por cron o a mano una vez al mes)
-- ---------------------------------------------------------------------
-- DELETE FROM public.sync_offline_log WHERE created < now() - interval '180 days';

-- ---------------------------------------------------------------------------
-- PERMISOS
-- El script se ejecuta con el dueño del esquema (postgres). El rol de la
-- aplicación (SOVCA_DB_USER, normalmente sovca_app) necesita leer y escribir
-- la tabla para poder registrar cada sincronización. Si en el servidor la
-- tabla se crea con otro dueño, ejecutar también:
--
--   GRANT SELECT, INSERT, UPDATE, DELETE ON public.sync_offline_log TO sovca_app;
--   GRANT USAGE, SELECT ON SEQUENCE public.sync_offline_log_id_seq TO sovca_app;
-- ---------------------------------------------------------------------------
