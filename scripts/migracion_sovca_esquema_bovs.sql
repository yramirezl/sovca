-- ============================================================================
-- migracion_sovca_esquema_bovs.sql
-- ----------------------------------------------------------------------------
-- Adecuación de sovca_qas al esquema que usa el código migrado de BOVS.
-- Generado automáticamente comparando los catálogos de sovca_qas y sovca_qas.
--
-- Sólo AGREGA lo que falta:
--   · extensión pgcrypto (gen_random_uuid)
--   · funciones de rangos/offline y de sincronización
--   · secuencias y tablas nuevas (planes, suscripciones, potreros,
--     movimientos de rebaño, soporte, validación de correos, offline, etc.)
--   · columnas nuevas en las tablas que ya existían
--   · índices, constraints y triggers de sincronización
--   · vistas nuevas
--
-- Es idempotente: puede ejecutarse más de una vez sin efectos secundarios.
-- NO elimina tablas, columnas ni datos existentes.
--
-- Uso:
--   psql -h 127.0.0.1 -U postgres -d sovca_qas -f scripts/migracion_sovca_esquema_bovs.sql
-- ============================================================================

\set ON_ERROR_STOP on
BEGIN;

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;

-- ============================================================================
-- 1) FUNCIONES
-- ============================================================================

CREATE OR REPLACE FUNCTION public.reservar_rango_animal(p_fundo_id integer, p_cantidad integer DEFAULT 1) RETURNS TABLE(desde integer, hasta integer)
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_actual integer;
BEGIN
    IF p_cantidad < 1 THEN
        RAISE EXCEPTION 'La cantidad a reservar debe ser mayor a 0';
    END IF;

    -- Asegura que exista fila de contador para el fundo (por si es un
    -- fundo creado antes de este script, o después sin re-sembrar).
    INSERT INTO public.manimalxfundos_contador (mfundos_id, ultimo_numero_asignado)
    VALUES (p_fundo_id, 0)
    ON CONFLICT (mfundos_id) DO NOTHING;

    SELECT ultimo_numero_asignado INTO v_actual
    FROM public.manimalxfundos_contador
    WHERE mfundos_id = p_fundo_id
    FOR UPDATE;

    UPDATE public.manimalxfundos_contador
       SET ultimo_numero_asignado = v_actual + p_cantidad,
           updated = now()
     WHERE mfundos_id = p_fundo_id;

    RETURN QUERY SELECT v_actual + 1, v_actual + p_cantidad;
END;
$$;


--

CREATE OR REPLACE FUNCTION public.reservar_rango_servicio(p_fundo_id integer, p_cantidad integer DEFAULT 1) RETURNS TABLE(desde integer, hasta integer)
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_actual integer;
BEGIN
    IF p_cantidad < 1 THEN
        RAISE EXCEPTION 'La cantidad a reservar debe ser mayor a 0';
    END IF;

    INSERT INTO public.mserviciosxfundos_contador (mfundos_id, ultimo_numero_asignado)
    VALUES (p_fundo_id, 0)
    ON CONFLICT (mfundos_id) DO NOTHING;

    SELECT ultimo_numero_asignado INTO v_actual
    FROM public.mserviciosxfundos_contador
    WHERE mfundos_id = p_fundo_id
    FOR UPDATE;

    UPDATE public.mserviciosxfundos_contador
       SET ultimo_numero_asignado = v_actual + p_cantidad,
           updated = now()
     WHERE mfundos_id = p_fundo_id;

    RETURN QUERY SELECT v_actual + 1, v_actual + p_cantidad;
END;
$$;


--

CREATE OR REPLACE FUNCTION public.reservar_rango_ternero(p_fundo_id integer, p_cantidad integer DEFAULT 1) RETURNS TABLE(desde integer, hasta integer)
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_actual integer;
BEGIN
    IF p_cantidad < 1 THEN
        RAISE EXCEPTION 'La cantidad a reservar debe ser mayor a 0';
    END IF;

    INSERT INTO public.mnacimientoxfundos_contador (mfundos_id, ultimo_numero_asignado)
    VALUES (p_fundo_id, 0)
    ON CONFLICT (mfundos_id) DO NOTHING;

    SELECT ultimo_numero_asignado INTO v_actual
    FROM public.mnacimientoxfundos_contador
    WHERE mfundos_id = p_fundo_id
    FOR UPDATE;

    UPDATE public.mnacimientoxfundos_contador
       SET ultimo_numero_asignado = v_actual + p_cantidad,
           updated = now()
     WHERE mfundos_id = p_fundo_id;

    RETURN QUERY SELECT v_actual + 1, v_actual + p_cantidad;
END;
$$;


--

CREATE OR REPLACE FUNCTION public.t_insert_b_mvalidacorreos() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
 
BEGIN
    INSERT INTO b_mvalidacorreos (email, codigo_validacion, musuarios_id)
    VALUES (NEW.email, NEW.codigo_validacion, NEW.musuarios_id);
    RETURN NEW;
END;

 
$$;


--

CREATE OR REPLACE FUNCTION public.trg_manimales_marca_sync() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, NEW.created, now()),
                        COALESCE(NEW.created, now())
                    );
                ELSE
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, now()),
                        COALESCE(OLD.updated, OLD.created, now()) + interval '1 microsecond'
                    );
                END IF;
                RETURN NEW;
            END;
            $$;


--

CREATE OR REPLACE FUNCTION public.trg_mconsecutivopesos_marca_sync() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, NEW.created, now()),
                        COALESCE(NEW.created, now())
                    );
                ELSE
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, now()),
                        COALESCE(OLD.updated, OLD.created, now()) + interval '1 microsecond'
                    );
                END IF;
                RETURN NEW;
            END;
            $$;


--

CREATE OR REPLACE FUNCTION public.trg_mcontrolsanitarios_marca_sync() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, NEW.created, now()),
                        COALESCE(NEW.created, now())
                    );
                ELSE
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, now()),
                        COALESCE(OLD.updated, OLD.created, now()) + interval '1 microsecond'
                    );
                END IF;
                RETURN NEW;
            END;
            $$;


--

CREATE OR REPLACE FUNCTION public.trg_mcontrolsanitarioxlotes_marca_sync() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, NEW.created, now()),
                        COALESCE(NEW.created, now())
                    );
                ELSE
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, now()),
                        COALESCE(OLD.updated, OLD.created, now()) + interval '1 microsecond'
                    );
                END IF;
                RETURN NEW;
            END;
            $$;


--

CREATE OR REPLACE FUNCTION public.trg_mdesincorporacionanimales_marca_sync() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, NEW.created, now()),
                        COALESCE(NEW.created, now())
                    );
                ELSE
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, now()),
                        COALESCE(OLD.updated, OLD.created, now()) + interval '1 microsecond'
                    );
                END IF;
                RETURN NEW;
            END;
            $$;


--

CREATE OR REPLACE FUNCTION public.trg_mmovimientos_rebano_marca_sync() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, NEW.created, now()),
                        COALESCE(NEW.created, now())
                    );
                ELSE
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, now()),
                        COALESCE(OLD.updated, OLD.created, now()) + interval '1 microsecond'
                    );
                END IF;
                RETURN NEW;
            END;
            $$;


--

CREATE OR REPLACE FUNCTION public.trg_mprolecheras_marca_sync() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, NEW.created, now()),
                        COALESCE(NEW.created, now())
                    );
                ELSE
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, now()),
                        COALESCE(OLD.updated, OLD.created, now()) + interval '1 microsecond'
                    );
                END IF;
                RETURN NEW;
            END;
            $$;


--

CREATE OR REPLACE FUNCTION public.trg_mregnacimientos_marca_sync() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, NEW.created, now()),
                        COALESCE(NEW.created, now())
                    );
                ELSE
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, now()),
                        COALESCE(OLD.updated, OLD.created, now()) + interval '1 microsecond'
                    );
                END IF;
                RETURN NEW;
            END;
            $$;


--

CREATE OR REPLACE FUNCTION public.trg_mregservicios_marca_sync() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, NEW.created, now()),
                        COALESCE(NEW.created, now())
                    );
                ELSE
                    NEW.updated := GREATEST(
                        COALESCE(NEW.updated, now()),
                        COALESCE(OLD.updated, OLD.created, now()) + interval '1 microsecond'
                    );
                END IF;
                RETURN NEW;
            END;
            $$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--

-- ============================================================================
-- 2) SECUENCIAS NUEVAS
-- ============================================================================

CREATE SEQUENCE IF NOT EXISTS public.api_tokens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.b_mvalidacorreos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.conexiones_historial_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.correccion_ciclos_servicios_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.manimxlotestemps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mcontrolsanitarioxlotes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mdetacrlsanitariolotes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mdetacsxlotes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mdetamovanimxlotes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mdetatemps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mdsinfichas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mmigraciones_animales_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mmovanimxlotes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mmovanimxlotestemps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mmovimientos_rebano_detalle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mmovimientos_rebano_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mmovlotestemps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mplanes_seq_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mplanesxfundo_seq_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mpotreros_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mregserviciostemps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.msuscripciones_seq_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mternerosxnacimientos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.mvalidacorreos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.rangos_reservados_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.soporte_fundos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

CREATE SEQUENCE IF NOT EXISTS public.usuario_dispositivo_offline_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--

-- ============================================================================
-- 3) TABLAS NUEVAS (34)
-- ============================================================================

CREATE TABLE IF NOT EXISTS public.api_tokens (
    id integer NOT NULL,
    musuarios_id integer NOT NULL,
    mfundos_id integer,
    token_hash character varying(64) NOT NULL,
    dispositivo character varying(100),
    created timestamp without time zone DEFAULT now() NOT NULL,
    expira timestamp without time zone NOT NULL,
    status integer DEFAULT 1 NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.b_mvalidacorreos (
    id integer DEFAULT nextval('public.b_mvalidacorreos_id_seq'::regclass) NOT NULL,
    email character varying(100) NOT NULL,
    codigo_validacion integer DEFAULT 111111 NOT NULL,
    musuarios_id integer NOT NULL,
    created timestamp without time zone DEFAULT now() NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.conexiones_historial (
    id integer NOT NULL,
    musuarios_id integer,
    username character varying(60),
    nombre character varying(120),
    ip character varying(45),
    pais character varying(100),
    ciudad character varying(100),
    region character varying(100),
    user_agent text,
    origen character varying(20) DEFAULT 'web'::character varying NOT NULL,
    resultado character varying(10) DEFAULT 'ok'::character varying NOT NULL,
    fecha timestamp with time zone DEFAULT now() NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.correccion_ciclos_servicios_log (
    id integer NOT NULL,
    lote character varying(40) NOT NULL,
    mregservicios_id integer NOT NULL,
    mfundos_id integer,
    codigo_rgb_id_animal_hembra character varying(60),
    ciclo_reproductivo integer,
    nro_servicio smallint,
    f_servicio timestamp without time zone,
    estatus_ciclo_antes smallint,
    estado_servicio_antes smallint,
    medofisiologicos_id integer,
    aplicado_en timestamp without time zone DEFAULT now() NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.geoip_cache (
    ip character varying(45) NOT NULL,
    pais character varying(100),
    ciudad character varying(100),
    region character varying(100),
    ok smallint DEFAULT 0 NOT NULL,
    actualizado timestamp with time zone DEFAULT now() NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.malertas_email_estado (
    mfundos_id integer NOT NULL,
    mtipoalertas_id smallint NOT NULL,
    codigo_animal character varying(20) NOT NULL,
    evento character varying(100) NOT NULL,
    f_estimada date NOT NULL,
    ultimo_envio timestamp without time zone,
    ultima_visualizacion timestamp without time zone,
    recordatorio_parto_enviado boolean DEFAULT false NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.malertas_inactividad_estado (
    mfundos_id integer NOT NULL,
    ultimo_envio timestamp without time zone,
    ultima_conexion timestamp without time zone,
    dias_inactividad smallint,
    correo_enviado character varying(150),
    updated timestamp without time zone DEFAULT now()
);


--

CREATE TABLE IF NOT EXISTS public.manimalxfundos_contador (
    mfundos_id integer NOT NULL,
    ultimo_numero_asignado integer DEFAULT 0 NOT NULL,
    updated timestamp without time zone DEFAULT now()
);


--

CREATE TABLE IF NOT EXISTS public.manimxlotestemps (
    id integer DEFAULT nextval('public.manimxlotestemps_id_seq'::regclass) NOT NULL,
    id_manimal integer NOT NULL,
    id_lotes integer NOT NULL,
    mfundos_id integer NOT NULL,
    id_musuario bigint NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.mcontrolsanitarioxlotes (
    id integer DEFAULT nextval('public.mcontrolsanitarioxlotes_id_seq'::regclass) NOT NULL,
    codigo_mcs_lote character varying(15) NOT NULL,
    mlotes_id integer NOT NULL,
    fecha_control timestamp without time zone DEFAULT now() NOT NULL,
    evento character varying(200) NOT NULL,
    responsable character varying(100) NOT NULL,
    mfundos_id integer NOT NULL,
    updated timestamp without time zone,
    created timestamp without time zone DEFAULT now() NOT NULL,
    status integer DEFAULT 1 NOT NULL,
    user_create bigint,
    user_modify bigint,
    fecha_prox_control timestamp without time zone DEFAULT now(),
    observaciones character varying(300),
    uuid_local uuid DEFAULT gen_random_uuid() NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    origen_dispositivo character varying(64)
);


--

CREATE TABLE IF NOT EXISTS public.mdetacrlsanitariolotes (
    id integer DEFAULT nextval('public.mdetacrlsanitariolotes_id_seq'::regclass) NOT NULL,
    mcontrolsanitarioxlotes_id integer NOT NULL,
    mproductos_id integer NOT NULL,
    dosis character varying(60),
    cantidad integer DEFAULT 0 NOT NULL,
    detalles character varying(300),
    mfundos_id integer NOT NULL,
    status smallint DEFAULT 1 NOT NULL,
    user_create bigint,
    created timestamp without time zone DEFAULT now() NOT NULL,
    user_modify bigint,
    updated timestamp without time zone
);


--

CREATE TABLE IF NOT EXISTS public.mdetacsxlotes (
    id integer DEFAULT nextval('public.mdetacsxlotes_id_seq'::regclass) NOT NULL,
    manimales_id integer NOT NULL,
    mcontrolsanitarioxlotes_id integer NOT NULL,
    codigo_mcs_lote character varying(15) NOT NULL,
    id_lotes integer NOT NULL,
    mfundos_id integer NOT NULL,
    updated timestamp without time zone,
    created timestamp without time zone DEFAULT now() NOT NULL,
    status integer DEFAULT 1 NOT NULL,
    user_create bigint,
    user_modify bigint
);


--

CREATE TABLE IF NOT EXISTS public.mdetamovanimxlotes (
    id integer DEFAULT nextval('public.mdetamovanimxlotes_id_seq'::regclass) NOT NULL,
    id_lote_origen integer NOT NULL,
    id_lote_destino integer NOT NULL,
    mfundos_id integer NOT NULL,
    cantidad_lote_origen integer DEFAULT 0,
    cantidad_exc_lote_origen integer DEFAULT 0,
    status integer DEFAULT 1 NOT NULL,
    fecha_mov timestamp without time zone DEFAULT now() NOT NULL,
    motivo character varying(200) NOT NULL,
    responsable character varying(100) NOT NULL,
    observaciones character varying(300),
    id_musuario integer NOT NULL,
    user_modify integer,
    created timestamp without time zone DEFAULT now() NOT NULL,
    updated timestamp without time zone DEFAULT now() NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.mdetatemps (
    id integer DEFAULT nextval('public.mdetatemps_id_seq'::regclass) NOT NULL,
    mcontrolsanitarioxlotes_id_temp integer NOT NULL,
    mproductos_id integer NOT NULL,
    mlote_id integer NOT NULL,
    dosis character varying(60),
    detalles character varying(300),
    mfundos_id integer NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.mdsinfichas (
    id integer DEFAULT nextval('public.mdsinfichas_id_seq'::regclass) NOT NULL,
    codigo_ternero character varying(20) NOT NULL,
    f_desincorporacion timestamp without time zone,
    tipo_desincorporacion smallint DEFAULT 0 NOT NULL,
    mfundos_id integer NOT NULL,
    causa_descarte character varying(100),
    causa_mortalidad character varying(100),
    observaciones character varying(300),
    updated timestamp without time zone,
    created timestamp without time zone DEFAULT now() NOT NULL,
    status integer DEFAULT 1 NOT NULL,
    user_create bigint,
    user_modify bigint,
    mestatus_anterior smallint
);


--

CREATE TABLE IF NOT EXISTS public.mestatusanimales (
    id smallint NOT NULL,
    nombre character varying(40) NOT NULL,
    sexo smallint DEFAULT 0 NOT NULL,
    status smallint DEFAULT 1 NOT NULL,
    orden smallint DEFAULT 0 NOT NULL,
    creado timestamp without time zone DEFAULT now()
);


--

CREATE TABLE IF NOT EXISTS public.mmigraciones_animales (
    id integer NOT NULL,
    manimal_origen_id integer NOT NULL,
    manimal_destino_id integer NOT NULL,
    mfundos_origen_id integer NOT NULL,
    mfundos_destino_id integer NOT NULL,
    codigo_original character varying(20) NOT NULL,
    codigo_nuevo character varying(20) NOT NULL,
    fecha_migracion timestamp without time zone DEFAULT now() NOT NULL,
    motivo character varying(300),
    observaciones text,
    usuario_id bigint NOT NULL,
    revertida boolean DEFAULT false NOT NULL,
    fecha_reversion timestamp without time zone,
    usuario_reversion bigint,
    motivo_reversion character varying(300),
    estado character varying(20) DEFAULT 'completada'::character varying NOT NULL,
    created timestamp without time zone DEFAULT now(),
    status integer DEFAULT 1 NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.mmovanimxlotes (
    id integer DEFAULT nextval('public.mmovanimxlotes_id_seq'::regclass) NOT NULL,
    id_mdetamovanimxlote integer NOT NULL,
    id_manimal integer NOT NULL,
    mfundos_id integer NOT NULL,
    id_musuario bigint NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.mmovanimxlotestemps (
    id integer DEFAULT nextval('public.mmovanimxlotestemps_id_seq'::regclass) NOT NULL,
    id_manimal integer NOT NULL,
    id_lotes integer NOT NULL,
    mfundos_id integer NOT NULL,
    id_musuario bigint NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.mmovimientos_rebano (
    id integer NOT NULL,
    mfundos_id integer NOT NULL,
    tipo_movimiento character varying(10) NOT NULL,
    mlotes_origen_id integer,
    mpotreros_origen_id integer,
    mlotes_destino_id integer,
    mpotreros_destino_id integer,
    fecha_movimiento timestamp without time zone DEFAULT now() NOT NULL,
    motivo character varying(150) NOT NULL,
    observaciones character varying(500),
    total_animales integer DEFAULT 0 NOT NULL,
    user_create bigint,
    created timestamp without time zone DEFAULT now() NOT NULL,
    revertido boolean DEFAULT false NOT NULL,
    fecha_reversion timestamp without time zone,
    motivo_reversion character varying(300),
    user_reversion bigint,
    movimiento_reversion_id integer,
    status integer DEFAULT 1 NOT NULL,
    uuid_local uuid DEFAULT gen_random_uuid() NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    updated timestamp without time zone,
    origen_dispositivo character varying(64)
);


--

CREATE TABLE IF NOT EXISTS public.mmovimientos_rebano_detalle (
    id integer NOT NULL,
    mmovimientos_rebano_id integer NOT NULL,
    manimales_id integer NOT NULL,
    codigo_rgb_id_animal character varying(20) NOT NULL,
    lote_anterior_id integer,
    lote_nuevo_id integer,
    potrero_anterior_id integer,
    potrero_nuevo_id integer,
    categoria_anterior_id integer,
    categoria_nueva_id integer,
    created timestamp without time zone DEFAULT now() NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.mmovlotestemps (
    id integer DEFAULT nextval('public.mmovlotestemps_id_seq'::regclass) NOT NULL,
    id_manimal integer NOT NULL,
    id_lotes integer NOT NULL,
    mfundos_id integer NOT NULL,
    id_musuario bigint NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.mnacimientoxfundos_contador (
    mfundos_id integer NOT NULL,
    ultimo_numero_asignado integer DEFAULT 0 NOT NULL,
    updated timestamp without time zone DEFAULT now()
);


--

CREATE TABLE IF NOT EXISTS public.mplanes (
    id integer DEFAULT nextval('public.mplanes_seq_id_seq'::regclass) NOT NULL,
    nombre character varying(100) NOT NULL,
    max_reg integer DEFAULT 0 NOT NULL,
    status integer DEFAULT 1 NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.mplanesxfundo (
    id smallint DEFAULT nextval('public.mplanesxfundo_seq_id_seq'::regclass) NOT NULL,
    musuarios_id integer NOT NULL,
    msuscripciones_id smallint,
    mfundos_id integer,
    mplanes_id integer,
    fecha_activa_suscripcion timestamp without time zone DEFAULT now() NOT NULL,
    fecha_caduca_suscripcion timestamp without time zone DEFAULT now() NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.mpotreros (
    id integer NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion character varying(500),
    hectareas numeric(10,2),
    capacidad_maxima integer,
    tipo_pasto character varying(100),
    mfundos_id integer NOT NULL,
    status integer DEFAULT 1 NOT NULL,
    user_create bigint,
    user_modify bigint,
    created timestamp without time zone DEFAULT now() NOT NULL,
    updated timestamp without time zone
);


--

CREATE TABLE IF NOT EXISTS public.mregserviciostemps (
    id integer DEFAULT nextval('public.mregserviciostemps_id_seq'::regclass) NOT NULL,
    mservicios_id integer NOT NULL,
    pario integer NOT NULL,
    medofisiologicos_id integer DEFAULT 3 NOT NULL,
    nro_servicio smallint DEFAULT 0 NOT NULL,
    ciclo_reproductivo integer DEFAULT 1 NOT NULL,
    estatus_ciclo integer DEFAULT 1 NOT NULL,
    estado_servicio integer DEFAULT 1 NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.mserviciosxfundos_contador (
    mfundos_id integer NOT NULL,
    ultimo_numero_asignado integer DEFAULT 0 NOT NULL,
    updated timestamp without time zone DEFAULT now()
);


--

CREATE TABLE IF NOT EXISTS public.msuscripciones (
    id smallint DEFAULT nextval('public.msuscripciones_seq_id_seq'::regclass) NOT NULL,
    descripcion character varying(100) NOT NULL,
    cantidad_dias smallint DEFAULT 0 NOT NULL,
    status smallint DEFAULT 1 NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.mternerosxnacimientos (
    id integer NOT NULL,
    mregnacimientos_id integer NOT NULL,
    mfundos_id integer NOT NULL,
    nro_ternero smallint DEFAULT 1 NOT NULL,
    id_ternero character varying,
    peso_nacimiento character varying DEFAULT ''::character varying NOT NULL,
    sexo smallint DEFAULT 0 NOT NULL,
    estatus_nacido smallint DEFAULT 1 NOT NULL,
    aptitud integer DEFAULT 0 NOT NULL,
    created timestamp without time zone DEFAULT now() NOT NULL,
    updated timestamp without time zone,
    status integer DEFAULT 1 NOT NULL,
    user_create bigint,
    user_modify bigint
);


--

CREATE TABLE IF NOT EXISTS public.mvalidacorreos (
    id integer DEFAULT nextval('public.mvalidacorreos_id_seq'::regclass) NOT NULL,
    email character varying(100) NOT NULL,
    codigo_validacion integer DEFAULT 111111 NOT NULL,
    estatus_codigo boolean DEFAULT true NOT NULL,
    activacion_codigo boolean DEFAULT false NOT NULL,
    nro_intentos smallint DEFAULT 0 NOT NULL,
    created timestamp without time zone DEFAULT now() NOT NULL,
    musuarios_id integer NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.rangos_reservados_log (
    id integer NOT NULL,
    musuarios_id integer NOT NULL,
    mfundos_id integer NOT NULL,
    modulo character varying(30) NOT NULL,
    dispositivo character varying(100),
    desde_numero integer NOT NULL,
    hasta_numero integer NOT NULL,
    created timestamp without time zone DEFAULT now() NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.soporte_fundos (
    id integer NOT NULL,
    musuarios_id integer NOT NULL,
    mfundos_id integer NOT NULL,
    fecha_inicio timestamp without time zone DEFAULT now() NOT NULL,
    fecha_fin timestamp without time zone,
    duracion_seg integer,
    motivo character varying(200) DEFAULT ''::character varying,
    ip character varying(45) DEFAULT ''::character varying,
    user_agent character varying(255) DEFAULT ''::character varying,
    status smallint DEFAULT 1 NOT NULL
);


--

CREATE TABLE IF NOT EXISTS public.usuario_dispositivo_offline (
    id integer NOT NULL,
    musuarios_id integer NOT NULL,
    mfundos_id integer NOT NULL,
    dispositivo_id uuid NOT NULL,
    nombre_dispositivo character varying(120),
    ultima_sincronizacion timestamp without time zone,
    activado_en timestamp without time zone DEFAULT now() NOT NULL,
    desvinculado_en timestamp without time zone,
    desvinculado_por bigint,
    motivo_desvinculacion character varying(200),
    status integer DEFAULT 1 NOT NULL,
    user_create bigint,
    user_modify bigint,
    created timestamp without time zone DEFAULT now() NOT NULL,
    updated timestamp without time zone,
    modo_offline character varying(20) DEFAULT 'gestion'::character varying NOT NULL,
    CONSTRAINT usuario_dispositivo_offline_modo_check CHECK (((modo_offline)::text = ANY ((ARRAY['gestion'::character varying, 'consulta'::character varying])::text[])))
);


--

-- ============================================================================
-- 3.1) PROPIEDAD DE SECUENCIAS
-- ============================================================================

ALTER SEQUENCE public.api_tokens_id_seq OWNED BY public.api_tokens.id;


--

ALTER SEQUENCE public.conexiones_historial_id_seq OWNED BY public.conexiones_historial.id;


--

ALTER SEQUENCE public.correccion_ciclos_servicios_log_id_seq OWNED BY public.correccion_ciclos_servicios_log.id;


--

ALTER SEQUENCE public.mmigraciones_animales_id_seq OWNED BY public.mmigraciones_animales.id;


--

ALTER SEQUENCE public.mmovimientos_rebano_detalle_id_seq OWNED BY public.mmovimientos_rebano_detalle.id;


--

ALTER SEQUENCE public.mmovimientos_rebano_id_seq OWNED BY public.mmovimientos_rebano.id;


--

ALTER SEQUENCE public.mpotreros_id_seq OWNED BY public.mpotreros.id;


--

ALTER SEQUENCE public.mternerosxnacimientos_id_seq OWNED BY public.mternerosxnacimientos.id;


--

ALTER SEQUENCE public.rangos_reservados_log_id_seq OWNED BY public.rangos_reservados_log.id;


--

ALTER SEQUENCE public.soporte_fundos_id_seq OWNED BY public.soporte_fundos.id;


--

ALTER SEQUENCE public.usuario_dispositivo_offline_id_seq OWNED BY public.usuario_dispositivo_offline.id;


--

-- ============================================================================
-- 4) DEFAULT DE SECUENCIAS EN TABLAS NUEVAS
-- ============================================================================

ALTER TABLE ONLY public.api_tokens ALTER COLUMN id SET DEFAULT nextval('public.api_tokens_id_seq'::regclass);


--

ALTER TABLE ONLY public.conexiones_historial ALTER COLUMN id SET DEFAULT nextval('public.conexiones_historial_id_seq'::regclass);


--

ALTER TABLE ONLY public.correccion_ciclos_servicios_log ALTER COLUMN id SET DEFAULT nextval('public.correccion_ciclos_servicios_log_id_seq'::regclass);


--

ALTER TABLE ONLY public.mmigraciones_animales ALTER COLUMN id SET DEFAULT nextval('public.mmigraciones_animales_id_seq'::regclass);


--

ALTER TABLE ONLY public.mmovimientos_rebano ALTER COLUMN id SET DEFAULT nextval('public.mmovimientos_rebano_id_seq'::regclass);


--

ALTER TABLE ONLY public.mmovimientos_rebano_detalle ALTER COLUMN id SET DEFAULT nextval('public.mmovimientos_rebano_detalle_id_seq'::regclass);


--

ALTER TABLE ONLY public.mpotreros ALTER COLUMN id SET DEFAULT nextval('public.mpotreros_id_seq'::regclass);


--

ALTER TABLE ONLY public.mternerosxnacimientos ALTER COLUMN id SET DEFAULT nextval('public.mternerosxnacimientos_id_seq'::regclass);


--

ALTER TABLE ONLY public.rangos_reservados_log ALTER COLUMN id SET DEFAULT nextval('public.rangos_reservados_log_id_seq'::regclass);


--

ALTER TABLE ONLY public.soporte_fundos ALTER COLUMN id SET DEFAULT nextval('public.soporte_fundos_id_seq'::regclass);


--

ALTER TABLE ONLY public.usuario_dispositivo_offline ALTER COLUMN id SET DEFAULT nextval('public.usuario_dispositivo_offline_id_seq'::regclass);


--

-- ============================================================================
-- 5) LLAVES, CONSTRAINTS E ÍNDICES DE TABLAS NUEVAS
-- ============================================================================

ALTER TABLE ONLY public.api_tokens DROP CONSTRAINT IF EXISTS api_tokens_pkey;
ALTER TABLE ONLY public.api_tokens
    ADD CONSTRAINT api_tokens_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.b_mvalidacorreos DROP CONSTRAINT IF EXISTS b_mvalidacorreos_pkey;
ALTER TABLE ONLY public.b_mvalidacorreos
    ADD CONSTRAINT b_mvalidacorreos_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.conexiones_historial DROP CONSTRAINT IF EXISTS conexiones_historial_pkey;
ALTER TABLE ONLY public.conexiones_historial
    ADD CONSTRAINT conexiones_historial_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.correccion_ciclos_servicios_log DROP CONSTRAINT IF EXISTS correccion_ciclos_servicios_log_pkey;
ALTER TABLE ONLY public.correccion_ciclos_servicios_log
    ADD CONSTRAINT correccion_ciclos_servicios_log_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.geoip_cache DROP CONSTRAINT IF EXISTS geoip_cache_pkey;
ALTER TABLE ONLY public.geoip_cache
    ADD CONSTRAINT geoip_cache_pkey PRIMARY KEY (ip);


--

ALTER TABLE ONLY public.malertas_email_estado DROP CONSTRAINT IF EXISTS malertas_email_estado_pk;
ALTER TABLE ONLY public.malertas_email_estado
    ADD CONSTRAINT malertas_email_estado_pk PRIMARY KEY (mfundos_id, mtipoalertas_id, codigo_animal, evento, f_estimada);


--

ALTER TABLE ONLY public.malertas_inactividad_estado DROP CONSTRAINT IF EXISTS malertas_inactividad_estado_pk;
ALTER TABLE ONLY public.malertas_inactividad_estado
    ADD CONSTRAINT malertas_inactividad_estado_pk PRIMARY KEY (mfundos_id);


--

ALTER TABLE ONLY public.manimalxfundos_contador DROP CONSTRAINT IF EXISTS manimalxfundos_contador_pkey;
ALTER TABLE ONLY public.manimalxfundos_contador
    ADD CONSTRAINT manimalxfundos_contador_pkey PRIMARY KEY (mfundos_id);


--

ALTER TABLE ONLY public.manimxlotestemps DROP CONSTRAINT IF EXISTS manimxlotestemps_pkey;
ALTER TABLE ONLY public.manimxlotestemps
    ADD CONSTRAINT manimxlotestemps_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mcontrolsanitarioxlotes DROP CONSTRAINT IF EXISTS mcontrolsanitarioxlotes_pkey;
ALTER TABLE ONLY public.mcontrolsanitarioxlotes
    ADD CONSTRAINT mcontrolsanitarioxlotes_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mdetacrlsanitariolotes DROP CONSTRAINT IF EXISTS mdetacrlsanitariolotes_pkey;
ALTER TABLE ONLY public.mdetacrlsanitariolotes
    ADD CONSTRAINT mdetacrlsanitariolotes_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mdetacsxlotes DROP CONSTRAINT IF EXISTS mdetacsxlotes_pkey;
ALTER TABLE ONLY public.mdetacsxlotes
    ADD CONSTRAINT mdetacsxlotes_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mdetamovanimxlotes DROP CONSTRAINT IF EXISTS mdetamovanimxlotes_pkey;
ALTER TABLE ONLY public.mdetamovanimxlotes
    ADD CONSTRAINT mdetamovanimxlotes_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mdetatemps DROP CONSTRAINT IF EXISTS mdetatemps_pkey;
ALTER TABLE ONLY public.mdetatemps
    ADD CONSTRAINT mdetatemps_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mdsinfichas DROP CONSTRAINT IF EXISTS mdsinfichas_pkey;
ALTER TABLE ONLY public.mdsinfichas
    ADD CONSTRAINT mdsinfichas_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mestatusanimales DROP CONSTRAINT IF EXISTS mestatusanimales_pkey;
ALTER TABLE ONLY public.mestatusanimales
    ADD CONSTRAINT mestatusanimales_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mmigraciones_animales DROP CONSTRAINT IF EXISTS mmigraciones_animales_pkey;
ALTER TABLE ONLY public.mmigraciones_animales
    ADD CONSTRAINT mmigraciones_animales_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mmovanimxlotes DROP CONSTRAINT IF EXISTS mmovanimxlotes_pkey;
ALTER TABLE ONLY public.mmovanimxlotes
    ADD CONSTRAINT mmovanimxlotes_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mmovanimxlotestemps DROP CONSTRAINT IF EXISTS mmovanimxlotestemps_pkey;
ALTER TABLE ONLY public.mmovanimxlotestemps
    ADD CONSTRAINT mmovanimxlotestemps_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mmovimientos_rebano_detalle DROP CONSTRAINT IF EXISTS mmovimientos_rebano_detalle_pkey;
ALTER TABLE ONLY public.mmovimientos_rebano_detalle
    ADD CONSTRAINT mmovimientos_rebano_detalle_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mmovimientos_rebano DROP CONSTRAINT IF EXISTS mmovimientos_rebano_pkey;
ALTER TABLE ONLY public.mmovimientos_rebano
    ADD CONSTRAINT mmovimientos_rebano_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mmovlotestemps DROP CONSTRAINT IF EXISTS mmovlotestemps_pkey;
ALTER TABLE ONLY public.mmovlotestemps
    ADD CONSTRAINT mmovlotestemps_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mplanes DROP CONSTRAINT IF EXISTS mmplanes_pkey;
ALTER TABLE ONLY public.mplanes
    ADD CONSTRAINT mmplanes_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mnacimientoxfundos_contador DROP CONSTRAINT IF EXISTS mnacimientoxfundos_contador_pkey;
ALTER TABLE ONLY public.mnacimientoxfundos_contador
    ADD CONSTRAINT mnacimientoxfundos_contador_pkey PRIMARY KEY (mfundos_id);


--

ALTER TABLE ONLY public.mplanesxfundo DROP CONSTRAINT IF EXISTS mplanesxfundo_pkey;
ALTER TABLE ONLY public.mplanesxfundo
    ADD CONSTRAINT mplanesxfundo_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mpotreros DROP CONSTRAINT IF EXISTS mpotreros_pkey;
ALTER TABLE ONLY public.mpotreros
    ADD CONSTRAINT mpotreros_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mregserviciostemps DROP CONSTRAINT IF EXISTS mregserviciostemps_pkey;
ALTER TABLE ONLY public.mregserviciostemps
    ADD CONSTRAINT mregserviciostemps_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mserviciosxfundos_contador DROP CONSTRAINT IF EXISTS mserviciosxfundos_contador_pkey;
ALTER TABLE ONLY public.mserviciosxfundos_contador
    ADD CONSTRAINT mserviciosxfundos_contador_pkey PRIMARY KEY (mfundos_id);


--

ALTER TABLE ONLY public.msuscripciones DROP CONSTRAINT IF EXISTS msuscripciones_pkey;
ALTER TABLE ONLY public.msuscripciones
    ADD CONSTRAINT msuscripciones_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mternerosxnacimientos DROP CONSTRAINT IF EXISTS mternerosxnacimientos_evento_ordinal_uq;
ALTER TABLE ONLY public.mternerosxnacimientos
    ADD CONSTRAINT mternerosxnacimientos_evento_ordinal_uq UNIQUE (mregnacimientos_id, nro_ternero);


--

ALTER TABLE ONLY public.mternerosxnacimientos DROP CONSTRAINT IF EXISTS mternerosxnacimientos_pkey;
ALTER TABLE ONLY public.mternerosxnacimientos
    ADD CONSTRAINT mternerosxnacimientos_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mvalidacorreos DROP CONSTRAINT IF EXISTS mvalidacorreos_pkey;
ALTER TABLE ONLY public.mvalidacorreos
    ADD CONSTRAINT mvalidacorreos_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.rangos_reservados_log DROP CONSTRAINT IF EXISTS rangos_reservados_log_pkey;
ALTER TABLE ONLY public.rangos_reservados_log
    ADD CONSTRAINT rangos_reservados_log_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.soporte_fundos DROP CONSTRAINT IF EXISTS soporte_fundos_pkey;
ALTER TABLE ONLY public.soporte_fundos
    ADD CONSTRAINT soporte_fundos_pkey PRIMARY KEY (id);


--

ALTER TABLE ONLY public.mdetacsxlotes DROP CONSTRAINT IF EXISTS uk_mdetacsxlotes;
ALTER TABLE ONLY public.mdetacsxlotes
    ADD CONSTRAINT uk_mdetacsxlotes UNIQUE (mcontrolsanitarioxlotes_id, manimales_id);


--

ALTER TABLE ONLY public.usuario_dispositivo_offline DROP CONSTRAINT IF EXISTS usuario_dispositivo_offline_pkey;
ALTER TABLE ONLY public.usuario_dispositivo_offline
    ADD CONSTRAINT usuario_dispositivo_offline_pkey PRIMARY KEY (id);


--

CREATE UNIQUE INDEX IF NOT EXISTS api_tokens_token_hash_idx ON public.api_tokens USING btree (token_hash);


--

CREATE INDEX IF NOT EXISTS api_tokens_usuario_idx ON public.api_tokens USING btree (musuarios_id, status);


--

CREATE INDEX IF NOT EXISTS correccion_ciclos_servicios_log_lote_idx ON public.correccion_ciclos_servicios_log USING btree (lote);


--

CREATE INDEX IF NOT EXISTS idx_conex_hist_fecha ON public.conexiones_historial USING btree (fecha DESC);


--

CREATE INDEX IF NOT EXISTS idx_conex_hist_ip ON public.conexiones_historial USING btree (ip);


--

CREATE INDEX IF NOT EXISTS idx_conex_hist_usuario_fecha ON public.conexiones_historial USING btree (musuarios_id, fecha DESC);


--

CREATE INDEX IF NOT EXISTS malertas_inactividad_ultimo_envio_idx ON public.malertas_inactividad_estado USING btree (ultimo_envio);


--

CREATE INDEX IF NOT EXISTS mcontrolsanitarioxlotes_fundo_fecha_idx ON public.mcontrolsanitarioxlotes USING btree (mfundos_id, fecha_control, mlotes_id) WHERE (status = 1);


--

CREATE INDEX IF NOT EXISTS mcontrolsanitarioxlotes_fundo_updated_idx ON public.mcontrolsanitarioxlotes USING btree (mfundos_id, updated);


--

CREATE UNIQUE INDEX IF NOT EXISTS mcontrolsanitarioxlotes_uuid_local_idx ON public.mcontrolsanitarioxlotes USING btree (uuid_local);


--

CREATE INDEX IF NOT EXISTS mdetacrlsanitariolotes_control_idx ON public.mdetacrlsanitariolotes USING btree (mcontrolsanitarioxlotes_id) WHERE (status > 0);


--

CREATE INDEX IF NOT EXISTS mdetacsxlotes_manimales_idx ON public.mdetacsxlotes USING btree (manimales_id) WHERE (status = 1);


--

CREATE INDEX IF NOT EXISTS mmig_animal_destino_idx ON public.mmigraciones_animales USING btree (manimal_destino_id);


--

CREATE INDEX IF NOT EXISTS mmig_animal_origen_idx ON public.mmigraciones_animales USING btree (manimal_origen_id);


--

CREATE INDEX IF NOT EXISTS mmig_fecha_idx ON public.mmigraciones_animales USING btree (fecha_migracion DESC);


--

CREATE INDEX IF NOT EXISTS mmig_fundos_idx ON public.mmigraciones_animales USING btree (mfundos_origen_id, mfundos_destino_id);


--

CREATE INDEX IF NOT EXISTS mmovimientos_rebano_detalle_mov_idx ON public.mmovimientos_rebano_detalle USING btree (mmovimientos_rebano_id);


--

CREATE INDEX IF NOT EXISTS mmovimientos_rebano_fundo_updated_idx ON public.mmovimientos_rebano USING btree (mfundos_id, updated);


--

CREATE UNIQUE INDEX IF NOT EXISTS mmovimientos_rebano_uuid_local_idx ON public.mmovimientos_rebano USING btree (uuid_local);


--

CREATE INDEX IF NOT EXISTS mternerosxnacimientos_fundo_evento_idx ON public.mternerosxnacimientos USING btree (mfundos_id, mregnacimientos_id, status);


--

CREATE UNIQUE INDEX IF NOT EXISTS mternerosxnacimientos_fundo_id_ternero_uq ON public.mternerosxnacimientos USING btree (mfundos_id, id_ternero) WHERE ((status = 1) AND (id_ternero IS NOT NULL) AND (btrim((id_ternero)::text) <> ''::text));


--

CREATE INDEX IF NOT EXISTS mternerosxnacimientos_regnacimiento_idx ON public.mternerosxnacimientos USING btree (mregnacimientos_id) WHERE (status = 1);


--

CREATE INDEX IF NOT EXISTS rangos_reservados_log_fundo_idx ON public.rangos_reservados_log USING btree (mfundos_id, created);


--

CREATE INDEX IF NOT EXISTS soporte_fundos_fundo_idx ON public.soporte_fundos USING btree (mfundos_id, fecha_inicio DESC);


--

CREATE INDEX IF NOT EXISTS soporte_fundos_usuario_idx ON public.soporte_fundos USING btree (musuarios_id, fecha_inicio DESC);


--

CREATE INDEX IF NOT EXISTS usuario_dispositivo_offline_disp_usuario_idx ON public.usuario_dispositivo_offline USING btree (dispositivo_id, musuarios_id, status);


--

CREATE INDEX IF NOT EXISTS usuario_dispositivo_offline_dispositivo_idx ON public.usuario_dispositivo_offline USING btree (dispositivo_id);


--

CREATE INDEX IF NOT EXISTS usuario_dispositivo_offline_fundo_idx ON public.usuario_dispositivo_offline USING btree (mfundos_id, status);


--

CREATE INDEX IF NOT EXISTS usuario_dispositivo_offline_modo_idx ON public.usuario_dispositivo_offline USING btree (musuarios_id, mfundos_id, modo_offline, status);


--

CREATE UNIQUE INDEX IF NOT EXISTS usuario_dispositivo_offline_unico_activo_gestion_idx ON public.usuario_dispositivo_offline USING btree (musuarios_id, mfundos_id) WHERE ((status = 1) AND ((modo_offline)::text = 'gestion'::text));


--

-- ============================================================================
-- 6) COLUMNAS NUEVAS EN TABLAS EXISTENTES
-- ============================================================================




ALTER TABLE public.manimales ADD COLUMN IF NOT EXISTS uuid_local uuid;
UPDATE public.manimales SET uuid_local = gen_random_uuid() WHERE uuid_local IS NULL;
ALTER TABLE public.manimales ALTER COLUMN uuid_local SET DEFAULT gen_random_uuid();
ALTER TABLE public.manimales ALTER COLUMN uuid_local SET NOT NULL;
ALTER TABLE public.manimales ADD COLUMN IF NOT EXISTS version integer DEFAULT 1 NOT NULL;
ALTER TABLE public.manimales ADD COLUMN IF NOT EXISTS origen_dispositivo character varying(64);
ALTER TABLE public.manimales ADD COLUMN IF NOT EXISTS mpotreros_id integer;
ALTER TABLE public.manimales ADD COLUMN IF NOT EXISTS animal_origen_id integer;
ALTER TABLE public.manimales ADD COLUMN IF NOT EXISTS codigo_origen character varying(20);
ALTER TABLE public.manimales ADD COLUMN IF NOT EXISTS mfundos_origen_id integer;
ALTER TABLE public.manimales ADD COLUMN IF NOT EXISTS fecha_migracion timestamp without time zone;
ALTER TABLE public.manimales ADD COLUMN IF NOT EXISTS mmigracion_id integer;

ALTER TABLE public.manimalxfundos ADD COLUMN IF NOT EXISTS uuid_local text;
UPDATE public.manimalxfundos SET uuid_local = gen_random_uuid()::text WHERE uuid_local IS NULL;
ALTER TABLE public.manimalxfundos ALTER COLUMN uuid_local SET DEFAULT gen_random_uuid()::text;
ALTER TABLE public.manimalxfundos ALTER COLUMN uuid_local SET NOT NULL;
ALTER TABLE public.manimalxfundos ADD COLUMN IF NOT EXISTS version integer DEFAULT 1 NOT NULL;
ALTER TABLE public.manimalxfundos ADD COLUMN IF NOT EXISTS origen_dispositivo text;







ALTER TABLE public.mconsecutivopesos ADD COLUMN IF NOT EXISTS uuid_local uuid;
UPDATE public.mconsecutivopesos SET uuid_local = gen_random_uuid() WHERE uuid_local IS NULL;
ALTER TABLE public.mconsecutivopesos ALTER COLUMN uuid_local SET DEFAULT gen_random_uuid();
ALTER TABLE public.mconsecutivopesos ALTER COLUMN uuid_local SET NOT NULL;
ALTER TABLE public.mconsecutivopesos ADD COLUMN IF NOT EXISTS version integer DEFAULT 1 NOT NULL;
ALTER TABLE public.mconsecutivopesos ADD COLUMN IF NOT EXISTS origen_dispositivo character varying(64);


ALTER TABLE public.mcontrolsanitarios ADD COLUMN IF NOT EXISTS uuid_local uuid;
UPDATE public.mcontrolsanitarios SET uuid_local = gen_random_uuid() WHERE uuid_local IS NULL;
ALTER TABLE public.mcontrolsanitarios ALTER COLUMN uuid_local SET DEFAULT gen_random_uuid();
ALTER TABLE public.mcontrolsanitarios ALTER COLUMN uuid_local SET NOT NULL;
ALTER TABLE public.mcontrolsanitarios ADD COLUMN IF NOT EXISTS version integer DEFAULT 1 NOT NULL;
ALTER TABLE public.mcontrolsanitarios ADD COLUMN IF NOT EXISTS origen_dispositivo character varying(64);

ALTER TABLE public.mdesincorporacionanimales ADD COLUMN IF NOT EXISTS uuid_local uuid;
UPDATE public.mdesincorporacionanimales SET uuid_local = gen_random_uuid() WHERE uuid_local IS NULL;
ALTER TABLE public.mdesincorporacionanimales ALTER COLUMN uuid_local SET DEFAULT gen_random_uuid();
ALTER TABLE public.mdesincorporacionanimales ALTER COLUMN uuid_local SET NOT NULL;
ALTER TABLE public.mdesincorporacionanimales ADD COLUMN IF NOT EXISTS version integer DEFAULT 1 NOT NULL;
ALTER TABLE public.mdesincorporacionanimales ADD COLUMN IF NOT EXISTS origen_dispositivo character varying(64);
ALTER TABLE public.mdesincorporacionanimales ADD COLUMN IF NOT EXISTS mestatus_anterior smallint;






ALTER TABLE public.mfundos ADD COLUMN IF NOT EXISTS mplanes_id integer;


ALTER TABLE public.mlotes ADD COLUMN IF NOT EXISTS uuid_local uuid;
UPDATE public.mlotes SET uuid_local = gen_random_uuid() WHERE uuid_local IS NULL;
ALTER TABLE public.mlotes ALTER COLUMN uuid_local SET DEFAULT gen_random_uuid();
ALTER TABLE public.mlotes ALTER COLUMN uuid_local SET NOT NULL;
ALTER TABLE public.mlotes ADD COLUMN IF NOT EXISTS version integer DEFAULT 1 NOT NULL;
ALTER TABLE public.mlotes ADD COLUMN IF NOT EXISTS origen_dispositivo character varying(64);







ALTER TABLE public.mperiodos ADD COLUMN IF NOT EXISTS codper_bov character varying(10) DEFAULT '0';



ALTER TABLE public.mprolecheras ADD COLUMN IF NOT EXISTS uuid_local uuid;
UPDATE public.mprolecheras SET uuid_local = gen_random_uuid() WHERE uuid_local IS NULL;
ALTER TABLE public.mprolecheras ALTER COLUMN uuid_local SET DEFAULT gen_random_uuid();
ALTER TABLE public.mprolecheras ALTER COLUMN uuid_local SET NOT NULL;
ALTER TABLE public.mprolecheras ADD COLUMN IF NOT EXISTS version integer DEFAULT 1 NOT NULL;
ALTER TABLE public.mprolecheras ADD COLUMN IF NOT EXISTS origen_dispositivo character varying(64);




ALTER TABLE public.mregnacimientos ADD COLUMN IF NOT EXISTS estatus_animal smallint DEFAULT 1 NOT NULL;
ALTER TABLE public.mregnacimientos ADD COLUMN IF NOT EXISTS mestatus_id smallint DEFAULT 1 NOT NULL;
ALTER TABLE public.mregnacimientos ADD COLUMN IF NOT EXISTS uuid_local uuid;
UPDATE public.mregnacimientos SET uuid_local = gen_random_uuid() WHERE uuid_local IS NULL;
ALTER TABLE public.mregnacimientos ALTER COLUMN uuid_local SET DEFAULT gen_random_uuid();
ALTER TABLE public.mregnacimientos ALTER COLUMN uuid_local SET NOT NULL;
ALTER TABLE public.mregnacimientos ADD COLUMN IF NOT EXISTS version integer DEFAULT 1 NOT NULL;
ALTER TABLE public.mregnacimientos ADD COLUMN IF NOT EXISTS origen_dispositivo character varying(64);
ALTER TABLE public.mregnacimientos ADD COLUMN IF NOT EXISTS mregservicios_id integer;

ALTER TABLE public.mregservicios ADD COLUMN IF NOT EXISTS ciclo_reproductivo integer DEFAULT 1 NOT NULL;
ALTER TABLE public.mregservicios ADD COLUMN IF NOT EXISTS estatus_ciclo integer DEFAULT 1 NOT NULL;
ALTER TABLE public.mregservicios ADD COLUMN IF NOT EXISTS estado_servicio integer DEFAULT 1 NOT NULL;
ALTER TABLE public.mregservicios ADD COLUMN IF NOT EXISTS uuid_local uuid;
UPDATE public.mregservicios SET uuid_local = gen_random_uuid() WHERE uuid_local IS NULL;
ALTER TABLE public.mregservicios ALTER COLUMN uuid_local SET DEFAULT gen_random_uuid();
ALTER TABLE public.mregservicios ALTER COLUMN uuid_local SET NOT NULL;
ALTER TABLE public.mregservicios ADD COLUMN IF NOT EXISTS version integer DEFAULT 1 NOT NULL;
ALTER TABLE public.mregservicios ADD COLUMN IF NOT EXISTS origen_dispositivo character varying(64);










ALTER TABLE public.musuarios ADD COLUMN IF NOT EXISTS mplanes_id smallint DEFAULT 0 NOT NULL;
ALTER TABLE public.musuarios ADD COLUMN IF NOT EXISTS fecha_activacion timestamp without time zone DEFAULT now() NOT NULL;
ALTER TABLE public.musuarios ADD COLUMN IF NOT EXISTS es_multifundo boolean DEFAULT false NOT NULL;
ALTER TABLE public.musuarios ADD COLUMN IF NOT EXISTS max_fundos integer DEFAULT 1 NOT NULL;
ALTER TABLE public.musuarios ADD COLUMN IF NOT EXISTS ultimo_fundo_id integer;
ALTER TABLE public.musuarios ADD COLUMN IF NOT EXISTS acceso_offline boolean DEFAULT false NOT NULL;
ALTER TABLE public.musuarios ADD COLUMN IF NOT EXISTS password_reset_expires timestamp without time zone;
ALTER TABLE public.musuarios ADD COLUMN IF NOT EXISTS password_reset_solicitado timestamp without time zone;
ALTER TABLE public.musuarios ADD COLUMN IF NOT EXISTS password_reset_codigo character varying(64);
ALTER TABLE public.musuarios ADD COLUMN IF NOT EXISTS password_reset_intentos smallint DEFAULT 0 NOT NULL;

-- ============================================================================
-- 6.1) AJUSTES DE LONGITUD
-- ----------------------------------------------------------------------------
-- En SOVCA el código de cría se declaró varchar(10), pero el formato
-- real (SOVCA-02-018) ya ocupa 11 caracteres. Se iguala a BOVS (20).
-- ============================================================================

-- v_list_terneros_activos, vmregnacimientos y vmregnacimientos_old
-- dependen de mregnacimientos.codigo_ternero: se retiran antes del ALTER
-- y se vuelven a crear (las dos últimas, al final del script).
DROP VIEW IF EXISTS public.v_list_terneros_activos;
DROP VIEW IF EXISTS public.vmregnacimientos;
DROP VIEW IF EXISTS public.vmregnacimientos_old;

DO $$
BEGIN
    -- Sólo se cambia el tipo si todavía es más corto: así la segunda
    -- ejecución no choca con las vistas que ya usan la columna.
    IF EXISTS (SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'public' AND table_name = 'manimales'
                  AND column_name = 'codigo_ternero'
                  AND (character_maximum_length IS NULL OR character_maximum_length < 20)) THEN
        ALTER TABLE public.manimales ALTER COLUMN codigo_ternero TYPE character varying(20);
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'public' AND table_name = 'mregnacimientos'
                  AND column_name = 'codigo_ternero'
                  AND (character_maximum_length IS NULL OR character_maximum_length < 20)) THEN
        ALTER TABLE public.mregnacimientos ALTER COLUMN codigo_ternero TYPE character varying(20);
    END IF;
END $$;

CREATE VIEW public.v_list_terneros_activos AS
 SELECT mfundos_id,
    ( SELECT z.nombre
           FROM maptitudes z
          WHERE z.id = a.aptitud) AS aptitud,
    codigo_ternero,
    sexo,
    id_ternero AS nro_chapeta,
    codigo_rgb_id_animal_hembra,
    codigo_rgb_id_animal_macho,
    f_parto AS fecha_nacimiento,
        CASE
            WHEN sexo = 1 AND mtipoganaderias_id = 1 THEN 'Cordera'::text
            WHEN sexo = 1 AND mtipoganaderias_id = 2 THEN 'Cabrita'::text
            WHEN sexo = 2 AND mtipoganaderias_id = 2 THEN 'Cabrito'::text
            WHEN sexo = 2 AND mtipoganaderias_id = 1 THEN 'Cordero'::text
            ELSE NULL::text
        END AS ternero,
    date_part('year'::text, age(now(), date(f_parto)::timestamp with time zone)) AS anios,
    date_part('month'::text, age(now(), date(f_parto)::timestamp with time zone)) AS meses,
    date_part('day'::text, age(now(), date(f_parto)::timestamp with time zone)) AS dias
   FROM mregnacimientos a
  WHERE fichado = false AND status = 1 AND estatus_nacido = 1 AND f_parto IS NOT NULL
  ORDER BY codigo_ternero;

-- ============================================================================
-- 7) ÍNDICES ÚNICOS DE uuid_local (sincronización offline)
-- ============================================================================

CREATE UNIQUE INDEX IF NOT EXISTS idx_manimales_uuid_local ON public.manimales (uuid_local);
CREATE UNIQUE INDEX IF NOT EXISTS idx_manimalxfundos_uuid_local ON public.manimalxfundos (uuid_local);
CREATE UNIQUE INDEX IF NOT EXISTS idx_mconsecutivopesos_uuid_local ON public.mconsecutivopesos (uuid_local);
CREATE UNIQUE INDEX IF NOT EXISTS idx_mcontrolsanitarios_uuid_local ON public.mcontrolsanitarios (uuid_local);
CREATE UNIQUE INDEX IF NOT EXISTS idx_mdesincorporacionanimales_uuid_local ON public.mdesincorporacionanimales (uuid_local);
CREATE UNIQUE INDEX IF NOT EXISTS idx_mlotes_uuid_local ON public.mlotes (uuid_local);
CREATE UNIQUE INDEX IF NOT EXISTS idx_mprolecheras_uuid_local ON public.mprolecheras (uuid_local);
CREATE UNIQUE INDEX IF NOT EXISTS idx_mregnacimientos_uuid_local ON public.mregnacimientos (uuid_local);
CREATE UNIQUE INDEX IF NOT EXISTS idx_mregservicios_uuid_local ON public.mregservicios (uuid_local);

-- ============================================================================
-- 8) TRIGGERS DE SINCRONIZACIÓN
-- ============================================================================

DROP TRIGGER IF EXISTS t_insert_b_mvalidacorreos ON public.mvalidacorreos;
CREATE TRIGGER t_insert_b_mvalidacorreos AFTER INSERT ON public.mvalidacorreos FOR EACH ROW EXECUTE FUNCTION public.t_insert_b_mvalidacorreos();


--

DROP TRIGGER IF EXISTS trg_manimales_marca_sync ON public.manimales;
CREATE TRIGGER trg_manimales_marca_sync BEFORE INSERT OR UPDATE ON public.manimales FOR EACH ROW EXECUTE FUNCTION public.trg_manimales_marca_sync();


--

DROP TRIGGER IF EXISTS trg_mconsecutivopesos_marca_sync ON public.mconsecutivopesos;
CREATE TRIGGER trg_mconsecutivopesos_marca_sync BEFORE INSERT OR UPDATE ON public.mconsecutivopesos FOR EACH ROW EXECUTE FUNCTION public.trg_mconsecutivopesos_marca_sync();


--

DROP TRIGGER IF EXISTS trg_mcontrolsanitarios_marca_sync ON public.mcontrolsanitarios;
CREATE TRIGGER trg_mcontrolsanitarios_marca_sync BEFORE INSERT OR UPDATE ON public.mcontrolsanitarios FOR EACH ROW EXECUTE FUNCTION public.trg_mcontrolsanitarios_marca_sync();


--

DROP TRIGGER IF EXISTS trg_mcontrolsanitarioxlotes_marca_sync ON public.mcontrolsanitarioxlotes;
CREATE TRIGGER trg_mcontrolsanitarioxlotes_marca_sync BEFORE INSERT OR UPDATE ON public.mcontrolsanitarioxlotes FOR EACH ROW EXECUTE FUNCTION public.trg_mcontrolsanitarioxlotes_marca_sync();


--

DROP TRIGGER IF EXISTS trg_mdesincorporacionanimales_marca_sync ON public.mdesincorporacionanimales;
CREATE TRIGGER trg_mdesincorporacionanimales_marca_sync BEFORE INSERT OR UPDATE ON public.mdesincorporacionanimales FOR EACH ROW EXECUTE FUNCTION public.trg_mdesincorporacionanimales_marca_sync();


--

DROP TRIGGER IF EXISTS trg_mmovimientos_rebano_marca_sync ON public.mmovimientos_rebano;
CREATE TRIGGER trg_mmovimientos_rebano_marca_sync BEFORE INSERT OR UPDATE ON public.mmovimientos_rebano FOR EACH ROW EXECUTE FUNCTION public.trg_mmovimientos_rebano_marca_sync();


--

DROP TRIGGER IF EXISTS trg_mprolecheras_marca_sync ON public.mprolecheras;
CREATE TRIGGER trg_mprolecheras_marca_sync BEFORE INSERT OR UPDATE ON public.mprolecheras FOR EACH ROW EXECUTE FUNCTION public.trg_mprolecheras_marca_sync();


--

DROP TRIGGER IF EXISTS trg_mregnacimientos_marca_sync ON public.mregnacimientos;
CREATE TRIGGER trg_mregnacimientos_marca_sync BEFORE INSERT OR UPDATE ON public.mregnacimientos FOR EACH ROW EXECUTE FUNCTION public.trg_mregnacimientos_marca_sync();


--

DROP TRIGGER IF EXISTS trg_mregservicios_marca_sync ON public.mregservicios;
CREATE TRIGGER trg_mregservicios_marca_sync BEFORE INSERT OR UPDATE ON public.mregservicios FOR EACH ROW EXECUTE FUNCTION public.trg_mregservicios_marca_sync();


--

-- ============================================================================
-- 9) VISTAS NUEVAS
-- ============================================================================

DROP VIEW IF EXISTS public.mdataconexiones;
CREATE VIEW public.mdataconexiones AS
 SELECT a.username,
    a.name,
    b.fecha,
    b.hora,
    b.ip,
    b.logueado
   FROM (public.musuarios a
     LEFT JOIN public.mrastreosessiones b ON ((a.id = b.id_musuario)))
  WHERE ((a.status = 1) AND (a.mroles_id <> 4))
  ORDER BY a.username, b.fecha;


--

DROP VIEW IF EXISTS public.v_consecutivo_peso;
CREATE VIEW public.v_consecutivo_peso AS
 SELECT a.id AS id_consecutivo_peso,
    a.codigo_rgb_id_animal,
    a.fecha_pesaje,
    a.peso,
    b.id AS id_animal,
    b.nombre,
    b.sexo,
    b.nro_arete,
    b.mfundos_id,
    b.mlotes_id AS id_lote,
        CASE
            WHEN (b.sexo = 1) THEN 'HEMBRA'::text
            WHEN (b.sexo = 2) THEN 'MACHO'::text
            ELSE NULL::text
        END AS sexo_animal
   FROM public.mconsecutivopesos a,
    public.manimales b
  WHERE ((b.mestatus_id = 1) AND (b.estatus_animal <> ALL (ARRAY[3, 5])) AND (a.status = 1) AND (a.mfundos_id = b.mfundos_id) AND ((a.codigo_rgb_id_animal)::text = (b.codigo_rgb_id_animal)::text))
  ORDER BY a.codigo_rgb_id_animal, a.fecha_pesaje;


--

DROP VIEW IF EXISTS public.v_conteo_animales_productor;
CREATE VIEW public.v_conteo_animales_productor AS
 SELECT f.user_propietario AS usuario_id,
    count(m.id) AS total_animales_activos,
    count(DISTINCT COALESCE(m.animal_origen_id, m.id)) AS total_animales_unicos
   FROM (public.manimales m
     JOIN public.mfundos f ON ((f.id = m.mfundos_id)))
  WHERE ((m.status = 1) AND (m.mestatus_id = 1) AND (m.estatus_animal = ANY (ARRAY[1, 2])))
  GROUP BY f.user_propietario;


--

DROP VIEW IF EXISTS public.vanimalesctlsantlotes;
CREATE VIEW public.vanimalesctlsantlotes AS
 SELECT b.nombre,
    a.id,
    b.id AS id_manimales,
    b.nro_arete,
    b.mfundos_id,
    ( SELECT x.nombre
           FROM public.mrazas x
          WHERE (x.id = b.mrazas_id)) AS raza,
    age(b.f_nacimiento) AS edad
   FROM public.mlotes a,
    public.manimales b
  WHERE ((a.id = b.mlotes_id) AND (a.status > 0) AND (b.mestatus_id = 1))
  ORDER BY b.nombre;


--

DROP VIEW IF EXISTS public.vcontrolsanitariosxlotes;
CREATE VIEW public.vcontrolsanitariosxlotes AS
 SELECT a.id,
    a.codigo_mcs_lote,
    a.fecha_control,
    a.fecha_prox_control,
    a.evento,
    a.responsable,
    a.observaciones,
    a.mfundos_id,
    a.mlotes_id,
    ( SELECT x.nombre
           FROM public.mlotes x
          WHERE (x.id = a.mlotes_id)) AS nombre_lote
   FROM public.mcontrolsanitarioxlotes a,
    public.mdetacsxlotes b
  WHERE ((a.status = 1) AND (a.status = 1) AND (b.mcontrolsanitarioxlotes_id = a.id));


--

DROP VIEW IF EXISTS public.vcontrolservicios;
CREATE VIEW public.vcontrolservicios AS
 SELECT a.codigo_rgb_id_animal_hembra,
    a.codigo_servicio,
    b.nro_arete,
    a.mfundos_id,
    a.medofisiologicos_id,
    a.ciclo_reproductivo,
    a.estatus_ciclo,
    a.estado_servicio,
    a.nro_servicio,
        CASE
            WHEN (a.estado_servicio = 1) THEN 'Servicio Activo'::text
            WHEN (a.estado_servicio = 2) THEN 'Servicio Cerrado'::text
            ELSE NULL::text
        END AS servicio,
    a.parto,
        CASE
            WHEN (a.parto = 0) THEN 'Sin Parir'::text
            WHEN (a.parto = 1) THEN 'Ya Pario'::text
            ELSE NULL::text
        END AS condicion_parto,
    a.fecha_parto,
    a.id
   FROM public.mregservicios a,
    public.manimales b
  WHERE (((b.codigo_rgb_id_animal)::text = (a.codigo_rgb_id_animal_hembra)::text) AND (a.status = 1) AND (b.status = 1) AND (b.mestatus_id = 1) AND (a.mfundos_id = b.mfundos_id))
  ORDER BY a.codigo_rgb_id_animal_hembra, a.codigo_servicio;


--

DROP VIEW IF EXISTS public.vdetamovimientoxlotes;
CREATE VIEW public.vdetamovimientoxlotes AS
 SELECT b.nombre,
    a.id_mdetamovanimxlote,
    b.id AS id_manimales,
    b.nro_arete,
    b.mfundos_id,
    ( SELECT x.nombre
           FROM public.mrazas x
          WHERE (x.id = b.mrazas_id)) AS raza,
    age(b.f_nacimiento) AS edad
   FROM public.mmovanimxlotes a,
    public.manimales b
  WHERE ((b.id = a.id_manimal) AND (b.mestatus_id = 1) AND (b.status > 0))
  ORDER BY b.nombre;


--

DROP VIEW IF EXISTS public.vgraficanacimientos;
CREATE VIEW public.vgraficanacimientos AS
 SELECT count(id) AS cantidad,
        CASE
            WHEN (sexo = 1) THEN 'Hembras'::text
            WHEN (sexo = 2) THEN 'Machos'::text
            ELSE NULL::text
        END AS sexox,
    mfundos_id,
    mtipoganaderias_id
   FROM public.mregnacimientos
  WHERE ((status = 1) AND (f_parto > (CURRENT_DATE - 180)) AND (sexo = ANY (ARRAY[1, 2])))
  GROUP BY mfundos_id, mtipoganaderias_id, sexo;


--

DROP VIEW IF EXISTS public.vlistarusuarios;
CREATE VIEW public.vlistarusuarios AS
 SELECT a.id,
    a.mroles_id,
    a.username,
    a.name,
    a.email,
    a.telefono,
    a.created AS creado,
    a.status,
        CASE
            WHEN (a.status = 1) THEN 'ACTIVO'::text
            WHEN (a.status = 0) THEN '-INACTIVO-'::text
            ELSE NULL::text
        END AS estatus,
    a.fecha_activacion,
    b.nombre AS descripcion,
    c.mfundos_id,
    a.mtipousuarios_id,
    d.nombre AS nombre_tipo_user,
        CASE
            WHEN ((k.mtipoganaderias_id > 0) AND (a.mroles_id = ANY (ARRAY[2, 3]))) THEN k.mtipoganaderias_id
            WHEN (a.mroles_id = ANY (ARRAY[1, 4])) THEN 0
            ELSE '-1'::integer
        END AS mtipoganaderias_id,
    x.max_reg AS maximo_registros,
    x.id AS mplanes_id
   FROM (((((public.musuarios a
     LEFT JOIN public.mroles b ON ((a.mroles_id = b.id)))
     LEFT JOIN public.mfundosxusuarios c ON ((a.id = c.musuarios_id)))
     LEFT JOIN public.mtipousuarios d ON ((a.mtipousuarios_id = d.id)))
     LEFT JOIN public.mfundos k ON ((c.mfundos_id = k.id)))
     LEFT JOIN public.mplanes x ON ((a.mplanes_id = x.id)))
  ORDER BY a.username;


--

DROP VIEW IF EXISTS public.vloteconsecutivopesos;
CREATE VIEW public.vloteconsecutivopesos AS
 SELECT DISTINCT c.nombre,
    c.id,
    b.mfundos_id
   FROM public.mconsecutivopesos a,
    public.manimales b,
    public.mlotes c
  WHERE ((a.mfundos_id = b.mfundos_id) AND (b.mlotes_id = c.id) AND (b.mestatus_id = 1) AND ((a.codigo_rgb_id_animal)::text = (b.codigo_rgb_id_animal)::text))
  ORDER BY c.id, c.nombre;


--

DROP VIEW IF EXISTS public.vmdetacrlstemps;
CREATE VIEW public.vmdetacrlstemps AS
 SELECT a.id AS id_control,
    a.mcontrolsanitarioxlotes_id_temp,
    a.mproductos_id,
    a.mlote_id,
    a.mfundos_id,
    c.nombre AS nombre_producto,
    a.dosis,
    a.detalles
   FROM public.mdetatemps a,
    public.mproductos c
  WHERE (a.mproductos_id = c.id);


--

DROP VIEW IF EXISTS public.vmdetacsxlotes;
CREATE VIEW public.vmdetacsxlotes AS
 SELECT a.mcontrolsanitarioxlotes_id,
    b.nombre,
    b.id AS id_manimales,
    b.nro_arete,
    b.mfundos_id,
    a.id_lotes,
    ( SELECT x.nombre
           FROM public.mrazas x
          WHERE (x.id = b.mrazas_id)) AS raza,
    age(b.f_nacimiento) AS edad
   FROM public.mdetacsxlotes a,
    public.manimales b
  WHERE ((a.manimales_id = b.id) AND (b.mfundos_id = a.mfundos_id) AND (a.status > 0) AND (b.status > 0) AND (b.mestatus_id = 1))
  ORDER BY b.nombre;


--

DROP VIEW IF EXISTS public.vmfichaxanimales;
CREATE VIEW public.vmfichaxanimales AS
 SELECT a.id AS id_animal,
    a.codigo_rgb_id_animal,
    a.nombre,
        CASE
            WHEN (a.sexo = 1) THEN 'HEMBRA'::text
            WHEN (a.sexo = 2) THEN 'MACHO'::text
            ELSE NULL::text
        END AS sexo,
    a.sexo AS id_sexo,
    a.mestatus_id,
    a.nro_arete,
        CASE
            WHEN (a.aptitud = 1) THEN 'CARNE'::text
            WHEN (a.aptitud = 2) THEN 'LECHE'::text
            WHEN (a.aptitud = 3) THEN 'DOBLE PROPOSITO'::text
            ELSE NULL::text
        END AS aptitud,
    b.nombre AS raza,
        CASE
            WHEN (a.servicio = 1) THEN 'MONTA'::text
            WHEN (a.servicio = 2) THEN 'INSEMINACION ARTIFICIAL'::text
            WHEN (a.servicio = 3) THEN 'DOBLE PROPOSITO'::text
            ELSE 'NO REGISTRADO'::text
        END AS servicio,
    a.f_nacimiento,
    a.peso_nacimiento,
    a.f_ingreso,
    a.rgd_madre,
    a.nombre_madre,
    a.rgd_padre,
    a.nombre_padre,
    a.procedencia,
    a.observaciones,
    a.mfundos_id,
    a.mlotes_id,
    a.rena,
    a.alza,
    a.estatus_animal,
    a.mtipoganaderias_id,
    a.codigo_ternero,
    a.nac_fundo,
    c.nombre AS lote
   FROM ((public.manimales a
     LEFT JOIN public.mrazas b ON ((a.mrazas_id = b.id)))
     LEFT JOIN public.mlotes c ON ((a.mlotes_id = c.id)));


--

DROP VIEW IF EXISTS public.vmovimientoxlotes;
CREATE VIEW public.vmovimientoxlotes AS
 SELECT b.nombre,
    a.id,
    b.id AS id_manimales,
    b.nro_arete,
    b.mfundos_id,
    ( SELECT x.nombre
           FROM public.mrazas x
          WHERE (x.id = b.mrazas_id)) AS raza,
    age(b.f_nacimiento) AS edad
   FROM public.mlotes a,
    public.manimales b
  WHERE ((a.id = b.mlotes_id) AND (a.status > 0) AND (b.mestatus_id = 1))
  ORDER BY b.nombre;


--

DROP VIEW IF EXISTS public.vmproductosxlotes;
CREATE VIEW public.vmproductosxlotes AS
 SELECT a.mcontrolsanitarioxlotes_id,
    a.mproductos_id,
    a.mfundos_id,
    c.nombre AS nombre_producto,
    a.dosis,
    a.detalles
   FROM public.mdetacrlsanitariolotes a,
    public.mproductos c
  WHERE (a.mproductos_id = c.id);


--

-- ============================================================================
-- 10) VISTAS EXISTENTES QUE CAMBIAN DE CONTRATO
-- ============================================================================

DROP VIEW IF EXISTS public.vusuarios;
CREATE VIEW public.vusuarios AS
 SELECT a.id,
    a.cid,
    a.mroles_id,
    a.username,
    a.password,
    a.name,
    a.email,
    a.telefono,
    a.imagen,
    a.created,
    a.mtipousuarios_id,
    b.nombre AS descripcion,
    a.act_clave,
    a.act_email,
    c.mfundos_id,
    d.nombre AS nombre_tipo_user,
        CASE
            WHEN ((k.mtipoganaderias_id > 0) AND (a.mroles_id = ANY (ARRAY[2, 3]))) THEN k.mtipoganaderias_id
            WHEN (a.mroles_id = ANY (ARRAY[1, 4])) THEN 0
            ELSE '-1'::integer
        END AS mtipoganaderias_id,
    x.max_reg AS maximo_registros,
    x.id AS mplanes_id
   FROM (((((public.musuarios a
     LEFT JOIN public.mroles b ON ((a.mroles_id = b.id)))
     LEFT JOIN public.mfundosxusuarios c ON ((a.id = c.musuarios_id)))
     LEFT JOIN public.mtipousuarios d ON ((a.mtipousuarios_id = d.id)))
     LEFT JOIN public.mfundos k ON ((c.mfundos_id = k.id)))
     LEFT JOIN public.mplanes x ON ((a.mplanes_id = x.id)))
  WHERE (a.status = 1)
  ORDER BY a.id;


--

DROP VIEW IF EXISTS public.vregservicios;
CREATE VIEW public.vregservicios AS
 SELECT a.id,
    a.codigo_servicio,
    a.codigo_rgb_id_animal_macho,
    a.codigo_rgb_id_animal_hembra,
    a.codigo_rgb_id_animal_dona,
    a.f_servicio,
    a.f_ultimo_parto,
    a.mservicios_id,
    a.aptitud,
    a.mrazas_id AS raza,
    a.f_probable_palpacion,
    a.f_palpacion,
    a.parto,
    a.fecha_parto,
    a.f_probable_parto,
    a.medofisiologicos_id,
    ( SELECT k.nombre
           FROM public.mestadofisiologicos k
          WHERE (k.id = a.medofisiologicos_id)) AS estado_fisiologico,
    ( SELECT m.aptitud
           FROM public.manimales m
          WHERE ((m.codigo_rgb_id_animal)::text = (a.codigo_rgb_id_animal_hembra)::text)) AS aptitud_h,
    a.status AS estatus_servicio,
    a.dnp,
    a.nro_servicio,
    a.nro_terneros,
    a.mfundos_id,
    a.observaciones,
    b.status AS estatus_animal,
    b.nro_arete,
    a.estatus_ciclo,
    a.ciclo_reproductivo,
    a.estado_servicio,
    b.aptitud AS aptitud_hembra,
    ( SELECT c.nombre
           FROM public.manimales c
          WHERE ((a.codigo_rgb_id_animal_hembra)::text = (c.codigo_rgb_id_animal)::text)) AS nombre_h,
    ( SELECT c.nombre
           FROM public.manimales c
          WHERE ((a.codigo_rgb_id_animal_macho)::text = (c.codigo_rgb_id_animal)::text)) AS nombre_m
   FROM public.mregservicios a,
    public.manimales b
  WHERE ((a.status = 1) AND (b.status = 1) AND (b.mestatus_id = 1) AND (a.mfundos_id = b.mfundos_id) AND ((b.codigo_rgb_id_animal)::text = (a.codigo_rgb_id_animal_hembra)::text));


--

DROP VIEW IF EXISTS public.vdesincorporaciones;
CREATE VIEW public.vdesincorporaciones AS
 SELECT a.codigo_rgb_id_animal,
    a.nombre AS nombre_animal,
    a.status,
    a.mfundos_id,
    a.nro_arete,
    b.id AS id_desincorporacion,
    b.tipo_desincorporacion,
    b.f_desincorporacion,
    b.causa_descarte,
    b.causa_mortalidad,
    b.observaciones,
    b.status AS estatus_registro,
    c.id AS id_estatus,
    c.nombre AS estatus_animal
   FROM public.manimales a,
    public.mdesincorporacionanimales b,
    public.mestatusganados c
  WHERE ((a.status = 1) AND (a.mestatus_id <> 1) AND ((a.codigo_rgb_id_animal)::text = (b.codigo_rgb_id_animal)::text) AND (a.mfundos_id = b.mfundos_id) AND (b.tipo_desincorporacion = c.id));


--

DROP VIEW IF EXISTS public.vconsecutivopesos;
CREATE VIEW public.vconsecutivopesos AS
 SELECT a.id,
    a.codigo_rgb_id_animal,
    a.fecha_pesaje,
    a.peso,
    a.mfundos_id,
    a.status AS estatus_consecutivopeso,
    b.status AS estatus_animal,
    b.nombre AS nombre_animal,
    b.nro_arete
   FROM public.mconsecutivopesos a,
    public.manimales b
  WHERE ((a.mfundos_id = b.mfundos_id) AND (a.mfundos_id = b.mfundos_id) AND (b.mestatus_id = 1) AND ((b.codigo_rgb_id_animal)::text = (a.codigo_rgb_id_animal)::text) AND (a.status > 0) AND (b.status > 0));


--

DROP VIEW IF EXISTS public.vmregnacimientos;
CREATE VIEW public.vmregnacimientos AS
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
    a.lactancia,
    a.mrazas_id AS raza
   FROM mregnacimientos a,
    manimales b,
    mtiposervicios e
  WHERE a.mtiposervicios_id = e.id AND a.mfundos_id = b.mfundos_id
    AND b.mestatus_id = 1
    AND b.codigo_rgb_id_animal::text = a.codigo_rgb_id_animal_hembra::text;

CREATE VIEW public.vmregnacimientos_old AS
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
    a.lactancia,
    a.observaciones,
    a.mfundos_id,
    a.f_secado,
    a.aptitud,
    a.mrazas_id AS raza,
    a.mtiposervicios_id,
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
          WHERE a.codigo_rgb_id_animal_macho::text = c.codigo_rgb_id_animal::text) AS nombre_m
   FROM mregnacimientos a,
    manimales b,
    mtiposervicios e
  WHERE a.mtiposervicios_id = e.id AND a.mfundos_id = b.mfundos_id
    AND b.codigo_rgb_id_animal::text = a.codigo_rgb_id_animal_hembra::text;

-- ============================================================================
-- 11) DATOS SEMILLA DE CATÁLOGOS NUEVOS
-- ============================================================================
INSERT INTO public.mestatusanimales (id, nombre)
SELECT * FROM (VALUES (1,'ACTIVO'),(2,'DONADOR ACTIVO'),(3,'DONADOR'),
                      (4,'DONADORA ACTIVO'),(5,'DONADORA')) AS v(id,nombre)
ON CONFLICT (id) DO NOTHING;

INSERT INTO public.msuscripciones (id, descripcion, cantidad_dias)
SELECT * FROM (VALUES (1,'Mensual',30),(2,'Trimestral',90),
                      (3,'Semestral',180),(4,'Anual',365)) AS v(id,descripcion,cantidad_dias)
ON CONFLICT (id) DO NOTHING;

INSERT INTO public.mplanes (id, nombre, max_reg, status)
SELECT * FROM (VALUES (1,'BRONCE',20,1),(2,'PLATA',50,1),(3,'ORO',100,1),
                      (4,'PLATINO',150,1),(5,'DIAMANTE',5000,1)) AS v(id,nombre,max_reg,status)
ON CONFLICT (id) DO NOTHING;

-- Ajusta las secuencias de los catálogos sembrados (sólo si existen:
-- mestatusanimales puede usar IDENTITY en vez de una secuencia nombrada).
DO $$
DECLARE
    r record;
BEGIN
    FOR r IN
        SELECT * FROM (VALUES
            ('public.msuscripciones', 'public.msuscripciones_seq_id_seq'),
            ('public.mplanes',        'public.mplanes_seq_id_seq'),
            ('public.mestatusanimales', pg_get_serial_sequence('public.mestatusanimales', 'id'))
        ) AS v(tabla, seq)
    LOOP
        IF r.seq IS NOT NULL THEN
            EXECUTE format(
                'SELECT setval(%L, GREATEST((SELECT COALESCE(MAX(id),0) FROM %s), 1), true)',
                r.seq, r.tabla);
        END IF;
    END LOOP;
END $$;

-- ============================================================================
-- 12) PLANES DE LAS CUENTAS EXISTENTES
-- ----------------------------------------------------------------------------
-- La versión anterior de SOVCA no manejaba planes. Sin un plan asignado,
-- maximo_registros queda en 0 y la app bloquea el registro de animales.
-- Se asigna DIAMANTE a lo ya existente para no bloquear la operación;
-- el administrador puede ajustarlo después desde el módulo de planes.
-- ============================================================================
UPDATE public.mfundos SET mplanes_id = 5 WHERE mplanes_id IS NULL;
UPDATE public.musuarios SET mplanes_id = 5
 WHERE mroles_id = 2 AND COALESCE(mplanes_id, 0) = 0;

COMMIT;
