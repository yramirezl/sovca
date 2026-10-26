<?php

/**
 * lib/fecha_secado_helper.php
 *
 * Regla ÚNICA de fecha de secado / destete, compartida por:
 *   - controllers/mservicios_controller.php   (control del servicio: palpación/gestación)
 *   - controllers/mnacimientos_controller.php (registro/edición de nacimiento)
 *   - api/sync_controller.php                 (sincronización offline)
 *
 * Regla de negocio (documento "Ajuste de cálculo para fecha de secado"):
 *
 *   1. El cálculo de SECADO solo aplica a hembras de aptitud LECHE (2)
 *      o DOBLE PROPÓSITO (3). Con aptitud CARNE (1) no se programa secado.
 *   2. Si la hembra está gestante (el control del servicio así lo indica),
 *      existe fecha de último parto y el servicio se registró ANTES de
 *      180 días desde ese parto, la fecha de secado se programa a los
 *      210 días de la FECHA DE SERVICIO. Es el "ajuste" que evita que la
 *      lactancia se solape con el último tercio de la gestación.
 *   3. En el resto de los casos (servicio a los 180 días o más desde el
 *      parto) se respeta el ciclo normal de lactancia: FECHA DE PARTO +
 *      período de producción de leche (PPL) configurado en el fundo. Es
 *      la misma regla que ya usaba el módulo de nacimientos y la que
 *      alimenta las alertas de secado.
 *
 * El módulo de nacimientos, además, calcula el DESTETE con el período
 * básico de lactancia (PBL) cuando el propósito registrado es "Lactancia"
 * (proposito = 1); con propósito "Producción de Leche" (proposito = 2)
 * calcula el SECADO con PPL.
 *
 * Los períodos se leen con prioridad: fundo (mfundos_id = X) y, si no
 * existe, el global (mfundos_id = 0).
 */

if (!defined('SOVCA_SECADO_DIAS_AJUSTE')) {
    /** Días desde el servicio para el cierre anticipado de lactancia (regla de la imagen). */
    define('SOVCA_SECADO_DIAS_AJUSTE', 210);
}
if (!defined('SOVCA_SECADO_DIAS_MINIMOS_PARTO_SERVICIO')) {
    /** Días mínimos entre el último parto y el servicio para considerar el ajuste. */
    define('SOVCA_SECADO_DIAS_MINIMOS_PARTO_SERVICIO', 180);
}

if (!function_exists('bovs_fecha_a_objeto')) {
    /**
     * Normaliza fechas heterogéneas (DateTime, 'd-m-Y', 'Y-m-d', 'Y-m-d H:i:s',
     * 'd/m/Y') a un DateTime a medianoche. Devuelve null si no es válida.
     *
     * @param DateTimeInterface|string|null $fecha
     * @return DateTime|null
     */
    function bovs_fecha_a_objeto($fecha)
    {
        if ($fecha === null) {
            return null;
        }
        if ($fecha instanceof DateTimeInterface) {
            return new DateTime($fecha->format('Y-m-d'));
        }

        $texto = trim((string)$fecha);
        if ($texto === '') {
            return null;
        }

        foreach (array('!d-m-Y', '!Y-m-d', '!Y-m-d H:i:s', '!Y-m-d H:i:s.u', '!d/m/Y') as $formato) {
            $objeto = DateTime::createFromFormat($formato, $texto);
            $errores = DateTime::getLastErrors();
            if ($objeto && (!$errores || ($errores['warning_count'] === 0 && $errores['error_count'] === 0))) {
                $objeto->setTime(0, 0, 0);
                return $objeto;
            }
        }

        // Sin respaldo de strtotime: normaliza fechas imposibles (31-02) en
        // silencio y haría que un dato inválido produjera un secado erróneo.
        return null;
    }
}

if (!function_exists('bovs_periodo_reproductivo_dias')) {
    /**
     * Días configurados para un período reproductivo (PBL, PPL, PGB, ...).
     * Prioridad: período del fundo; si no existe, el global (mfundos_id = 0).
     *
     * @param string $codigo           Código del período (PBL, PPL...).
     * @param int    $mfundos_id       Fundo de la sesión.
     * @param int    $tipo_ganaderia   Tipo de ganadería del fundo.
     * @return int   Días configurados o 0 si no hay período válido.
     */
    function bovs_periodo_reproductivo_dias($codigo, $mfundos_id, $tipo_ganaderia)
    {
        $codigo = strtoupper(trim((string)$codigo));
        if ($codigo === '') {
            return 0;
        }

        $conexion = ActiveRecord\ConnectionManager::get_connection();
        $consulta = $conexion->query(
            "SELECT dias_gestacion
               FROM mperiodos
              WHERE mfundos_id IN (?, 0)
                AND mtipoganaderias_id = ?
                AND codper_bov = ?
                AND status = 1
           ORDER BY CASE WHEN mfundos_id = ? THEN 0 ELSE 1 END, id DESC
              LIMIT 1",
            array((int)$mfundos_id, (int)$tipo_ganaderia, $codigo, (int)$mfundos_id)
        );

        $fila = $consulta ? $consulta->fetch(PDO::FETCH_ASSOC) : false;
        if (!$fila || !isset($fila['dias_gestacion'])) {
            return 0;
        }

        return max(0, (int)$fila['dias_gestacion']);
    }
}

if (!function_exists('bovs_calcular_secado_servicio')) {
    /**
     * Calcula la fecha de secado para el CONTROL DE UN SERVICIO.
     *
     * @param DateTimeInterface|string|null $fecha_servicio      Fecha del servicio.
     * @param DateTimeInterface|string|null $fecha_ultimo_parto  Fecha del último parto.
     * @param int                           $aptitud             Aptitud de la hembra (1 carne, 2 leche, 3 doble propósito).
     * @param int                           $mfundos_id          Fundo de la sesión.
     * @param int                           $tipo_ganaderia      Tipo de ganadería del fundo.
     *
     * @return array{
     *   aplica: bool, tipo: string, fecha: DateTime|null, regla: string,
     *   dias_desde_parto: int|null, dias_periodo: int, mensaje: string
     * }
     */
    function bovs_calcular_secado_servicio($fecha_servicio, $fecha_ultimo_parto, $aptitud, $mfundos_id, $tipo_ganaderia)
    {
        $resultado = array(
            'aplica'          => false,
            'tipo'            => '',
            'fecha'           => null,
            'regla'           => 'SIN_APLICAR',
            'dias_desde_parto' => null,
            'dias_periodo'    => 0,
            'mensaje'         => ''
        );

        // (1) Solo leche o doble propósito (imagen: "aptitud leche o doble propósito").
        if (!in_array((int)$aptitud, array(2, 3), true)) {
            $resultado['mensaje'] = 'La hembra no es de aptitud leche ni doble propósito: no se programa secado.';
            return $resultado;
        }
        $resultado['aplica'] = true;
        $resultado['tipo'] = 'SECADO';

        $servicio = bovs_fecha_a_objeto($fecha_servicio);
        if (!$servicio) {
            $resultado['regla'] = 'SIN_FECHA_SERVICIO';
            $resultado['mensaje'] = 'No hay una fecha de servicio válida para calcular el secado.';
            return $resultado;
        }

        $parto = bovs_fecha_a_objeto($fecha_ultimo_parto);
        if (!$parto) {
            $resultado['regla'] = 'SIN_ULTIMO_PARTO';
            $resultado['mensaje'] = 'No hay fecha de último parto: la fecha de secado se debe registrar manualmente.';
            return $resultado;
        }

        $dias_desde_parto = (int)floor(($servicio->getTimestamp() - $parto->getTimestamp()) / 86400);
        $resultado['dias_desde_parto'] = $dias_desde_parto;

        if ($dias_desde_parto < 0) {
            $resultado['regla'] = 'FECHAS_INCONSISTENTES';
            $resultado['mensaje'] = 'La fecha de servicio es anterior al último parto: revisa las fechas.';
            return $resultado;
        }

        // (2) Ajuste de la imagen: servicio antes de 180 días post-parto.
        if ($dias_desde_parto < SOVCA_SECADO_DIAS_MINIMOS_PARTO_SERVICIO) {
            $fecha = clone $servicio;
            $fecha->modify('+' . SOVCA_SECADO_DIAS_AJUSTE . ' days');
            $resultado['fecha'] = $fecha;
            $resultado['regla'] = 'AJUSTE_' . SOVCA_SECADO_DIAS_AJUSTE;
            $resultado['dias_periodo'] = SOVCA_SECADO_DIAS_AJUSTE;
            $resultado['mensaje'] = 'Servicio antes de los 180 días post-parto: secado a los '
                . SOVCA_SECADO_DIAS_AJUSTE . ' días del servicio.';
            return $resultado;
        }

        // (3) Ciclo normal de lactancia: parto + PPL del fundo.
        $dias_ppl = bovs_periodo_reproductivo_dias('PPL', $mfundos_id, $tipo_ganaderia);
        $resultado['dias_periodo'] = $dias_ppl;

        if ($dias_ppl <= 0) {
            $resultado['regla'] = 'SIN_PERIODO_PPL';
            $resultado['mensaje'] = 'El fundo no tiene configurado el período de producción de leche (PPL).';
            return $resultado;
        }

        $fecha = clone $parto;
        $fecha->modify('+' . $dias_ppl . ' days');

        // Si el ciclo de lactancia ya había terminado antes del servicio, no
        // tiene sentido programar un secado nuevo (servicios muy tardíos).
        if ($fecha <= $servicio) {
            $resultado['regla'] = 'LACTANCIA_CERRADA';
            $resultado['mensaje'] = 'El período de lactancia del último parto terminó antes del servicio: no hay secado que programar.';
            return $resultado;
        }

        $resultado['fecha'] = $fecha;
        $resultado['regla'] = 'PERIODO_PPL';
        $resultado['mensaje'] = 'Servicio con 180 días o más post-parto: secado a los '
            . $dias_ppl . ' días del parto (período del fundo).';

        return $resultado;
    }
}

if (!function_exists('bovs_calcular_secado_parto')) {
    /**
     * Calcula la fecha de secado / destete a partir de un PARTO (nacimientos).
     *
     * proposito = 1 (Lactancia)            → PBL (destete del ternero)
     * proposito = 2 (Producción de leche)  → PPL (secado de la madre)
     *
     * @param DateTimeInterface|string|null $fecha_parto
     * @param int                           $proposito
     * @param int                           $mfundos_id
     * @param int                           $tipo_ganaderia
     *
     * @return array{ok: bool, fecha: DateTime|null, periodo: string, dias: int, mensaje: string}
     */
    function bovs_calcular_secado_parto($fecha_parto, $proposito, $mfundos_id, $tipo_ganaderia)
    {
        $proposito = (int)$proposito;
        $periodo = ($proposito === 1) ? 'PBL' : (($proposito === 2) ? 'PPL' : '');

        if ($periodo === '') {
            return array(
                'ok'      => false,
                'fecha'   => null,
                'periodo' => '',
                'dias'    => 0,
                'mensaje' => 'Selecciona el propósito de lactancia o producción de leche.'
            );
        }

        $dias = bovs_periodo_reproductivo_dias($periodo, $mfundos_id, $tipo_ganaderia);
        if ($dias <= 0) {
            return array(
                'ok'      => false,
                'fecha'   => null,
                'periodo' => $periodo,
                'dias'    => 0,
                'mensaje' => 'No hay un período válido configurado para '
                    . ($periodo === 'PBL' ? 'lactancia/destete' : 'producción de leche/secado')
                    . '. Revisa los períodos del fundo.'
            );
        }

        $fecha = bovs_fecha_a_objeto($fecha_parto);
        if (!$fecha) {
            return array(
                'ok'      => false,
                'fecha'   => null,
                'periodo' => $periodo,
                'dias'    => $dias,
                'mensaje' => 'La fecha del parto no tiene un formato válido.'
            );
        }

        $fecha->modify('+' . $dias . ' days');

        return array(
            'ok'      => true,
            'fecha'   => $fecha,
            'periodo' => $periodo,
            'dias'    => $dias,
            'mensaje' => ''
        );
    }
}
