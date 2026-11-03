-- =============================================================================
-- Notificación de PALPACIÓN (recordatorio para el productor)
--
-- La vista `v_palpacion_alertas` alimenta `malertas` (tipo 2) desde
-- alertas/controlador_alertas.php, que es lo que se ve en
-- Sistema → Notificaciones y lo que se envía por correo.
--
-- Tenía tres defectos:
--
--   1. `motivo` solo se llenaba cuando el servicio estaba en el estado
--      fisiológico 2 (Proxima a Palpacion); en cualquier otro caso devolvía
--      NULL y `malertas.evento` es NOT NULL → el INSERT completo fallaba y
--      NO se generaba ninguna notificación de palpación.
--   2. No filtraba por ciclo abierto: una vez palpada la hembra (o cerrado el
--      ciclo) el servicio seguía generando recordatorios.
--   3. El cruce con `manimales` no usaba `mfundos_id`: un código repetido en
--      otro fundo podía asociarse al animal equivocado.
--
-- Ahora:
--   · solo servicios ABIERTOS (estatus_ciclo = 1) y sin resultado de palpación
--     (estado 4 Aborto y 5 Vacía ya tienen resultado),
--   · ventana de 7 días antes de la fecha probable de palpación,
--   · `motivo` siempre con texto e incluye la fecha, para que la notificación
--     diga CUÁNDO y A QUÉ ANIMAL (el código y el nombre ya van en la fila).
--
-- Idempotente: se puede ejecutar varias veces.
-- =============================================================================

CREATE OR REPLACE VIEW v_palpacion_alertas AS
 SELECT b.mfundos_id,
        a.codigo_rgb_id_animal_hembra AS cod_animal,
        b.nombre,
        b.nro_arete,
        a.f_probable_palpacion,
        ('PALPACION: ' || to_char(a.f_probable_palpacion, 'DD/MM/YYYY'))::text AS motivo,
        date_part('month', age(date(a.f_probable_palpacion)::timestamp with time zone, now())) AS meses,
        date_part('day', age(date(a.f_probable_palpacion)::timestamp with time zone, now())) + 1 AS dias
   FROM mregservicios a
   JOIN manimales b
     ON b.mfundos_id = a.mfundos_id
    AND b.codigo_rgb_id_animal = a.codigo_rgb_id_animal_hembra
    AND b.mestatus_id = 1
    AND b.status = 1
  WHERE a.status = 1
    AND a.estatus_ciclo = 1
    AND a.f_probable_palpacion IS NOT NULL
    AND COALESCE(a.medofisiologicos_id, 0) NOT IN (4, 5)
    AND a.f_probable_palpacion::date BETWEEN CURRENT_DATE AND CURRENT_DATE + 7
  ORDER BY b.mfundos_id, a.f_probable_palpacion;

-- Comprobación:
--   SELECT * FROM v_palpacion_alertas ORDER BY f_probable_palpacion LIMIT 20;
