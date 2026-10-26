-- ============================================================================
-- migracion_conexiones_duracion.sql — DURACIÓN DE LAS SESIONES (SOVCA)
-- ----------------------------------------------------------------------------
-- Añade a conexiones_historial las columnas que usa lib/conexiones_helper.php
-- para medir cuánto tiempo estuvo conectado cada usuario (latido mientras
-- navega y cierre al salir).
--
-- Idempotente: se puede ejecutar varias veces sin error.
--   psql -h 127.0.0.1 -U postgres -d sovca -f scripts/migracion_conexiones_duracion.sql
-- ============================================================================

ALTER TABLE conexiones_historial
    ADD COLUMN IF NOT EXISTS session_id_hash   character varying(64),
    ADD COLUMN IF NOT EXISTS ultima_actividad  timestamp with time zone,
    ADD COLUMN IF NOT EXISTS ultima_ruta       character varying(255),
    ADD COLUMN IF NOT EXISTS fecha_fin         timestamp with time zone,
    ADD COLUMN IF NOT EXISTS duracion_seg      integer,
    ADD COLUMN IF NOT EXISTS cerrada           boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS cerrada_por       character varying(20) DEFAULT '-';

-- Búsqueda del latido: la sesión se localiza por su hash.
CREATE INDEX IF NOT EXISTS idx_cx_historial_hash
    ON conexiones_historial (session_id_hash);

-- Historial por usuario/fecha (listado del módulo Conexiones).
CREATE INDEX IF NOT EXISTS idx_cx_historial_usuario_fecha
    ON conexiones_historial (musuarios_id, fecha DESC);
