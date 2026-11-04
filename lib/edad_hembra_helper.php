<?php
/**
 * EDAD MÍNIMA DE LA HEMBRA PARA REGISTRAR SERVICIO — REGLA ÚNICA
 * ─────────────────────────────────────────────────────────────────────
 * En ovinos y caprinos la pubertad se alcanza alrededor de los 7 meses:
 * el mínimo para recibir servicio son 210 DÍAS, no los 12 meses que se
 * usaban con la regla bovina heredada de BOVS. Una cordera/cabrita de
 * 10 meses ya es apta.
 *
 * El valor NO está en el código: se configura como un período más
 * (`mperiodos`, código `EHM`), igual que PSP / PPS / PGB / EMM, desde
 * Parámetros → Períodos:
 *
 *     Nombre del Periodo : EDAD MINIMA DE LA HEMBRA PARA SERVICIO (DIAS)
 *     Días de Gestación  : 210      (≈ 7 meses)
 *
 * Resolución: fila del fundo → fila global (mfundos_id = 0) → 210 días.
 *
 * Se usa desde:
 *   - controllers/mservicios_controller.php  (listado de candidatas,
 *     búsqueda de la hembra/donadora y registro del servicio)
 *   - api/catalogos_controller.php           (la PWA recibe el parámetro)
 *   - api/sync_controller.php                (validación al sincronizar)
 *
 * Migración: scripts/migracion_edad_minima_hembra.sql
 * ─────────────────────────────────────────────────────────────────────
 */

if (!function_exists('_ea_q')) {
    require_once __DIR__ . '/estatus_animal_helper.php';
}

if (!function_exists('bovs_edad_meses_desde_dias')) {
    /** Convierte días en meses cumplidos (para los mensajes al productor). */
    function bovs_edad_meses_desde_dias($dias)
    {
        $dias = intval($dias);

        return ($dias > 0) ? (int) floor($dias / 30.4375) : 0;
    }
}

if (!function_exists('bovs_edad_minima_hembra_dias')) {
    /**
     * Edad mínima (en días) que debe tener una hembra para registrar servicio.
     *
     * @param int $mfundos_id       fundo activo (0 = solo el global)
     * @param int $tipo_ganaderia   mtipoganaderias_id (0 = cualquiera)
     * @return int días (210 por defecto)
     */
    function bovs_edad_minima_hembra_dias($mfundos_id = 0, $tipo_ganaderia = 0)
    {
        static $cache = array();

        $mfundos_id = intval($mfundos_id);
        $tipo_ganaderia = intval($tipo_ganaderia);
        $clave = $mfundos_id . '|' . $tipo_ganaderia;

        if (isset($cache[$clave])) {
            return $cache[$clave];
        }

        $filtro_tipo = ($tipo_ganaderia > 0)
            ? 'and (mtipoganaderias_id = ' . $tipo_ganaderia . ' or mtipoganaderias_id = 0)'
            : '';

        $preferir_tipo = ($tipo_ganaderia > 0)
            ? 'case when mtipoganaderias_id = ' . $tipo_ganaderia . ' then 0 else 1 end,'
            : '';

        $sql = 'select dias_gestacion
                  from mperiodos
                 where status > 0
                   and codper_bov = \'EHM\'
                   and mfundos_id in (0, ' . $mfundos_id . ')
                   ' . $filtro_tipo . '
                 order by case when mfundos_id = ' . $mfundos_id . ' then 0 else 1 end,
                          ' . $preferir_tipo . '
                          id desc
                 limit 1';

        $filas = _ea_q($sql);
        $dias = (!empty($filas) && intval($filas[0]['dias_gestacion']) > 0)
            ? intval($filas[0]['dias_gestacion'])
            : 210;

        $cache[$clave] = $dias;

        return $dias;
    }
}

if (!function_exists('bovs_error_edad_hembra')) {
    /**
     * Motivo por el que esta hembra NO puede registrar servicio por edad,
     * o '' si cumple. Devuelve HTML (quien llama lo envuelve en el aviso).
     *
     * Sin fecha de nacimiento no se puede comprobar la edad y NO se bloquea
     * (igual criterio que el macho): la ficha del animal permite completarla.
     *
     * @param string $codigo         código de la hembra (codigo_rgb_id_animal)
     * @param int    $mfundos_id     fundo activo
     * @param int    $tipo_ganaderia mtipoganaderias_id
     */
    function bovs_error_edad_hembra($codigo, $mfundos_id, $tipo_ganaderia = 0)
    {
        $codigo = trim((string) $codigo);

        if ($codigo === '') {
            return '';
        }

        $filas = _ea_q(
            'select codigo_rgb_id_animal, coalesce(nombre, \'\') as nombre,
                    case when f_nacimiento is null then null
                         else (current_date - f_nacimiento::date) end as edad_dias
               from manimales
              where mfundos_id = ' . intval($mfundos_id) . '
                and codigo_rgb_id_animal = \'' . str_replace("'", "''", $codigo) . '\'
                and status = 1
              limit 1'
        );

        if (empty($filas)) {
            return '';
        }

        $minimo_dias = bovs_edad_minima_hembra_dias($mfundos_id, $tipo_ganaderia);
        $edad_dias = $filas[0]['edad_dias'];

        if ($edad_dias === null) {
            return '';
        }

        if (intval($edad_dias) >= $minimo_dias) {
            return '';
        }

        $nombre = trim((string) $filas[0]['nombre']);
        $minimo_meses = max(1, (int) round($minimo_dias / 30.4375));

        return 'La hembra <strong>' . htmlspecialchars($codigo . ($nombre !== '' ? ' · ' . $nombre : ''), ENT_QUOTES, 'UTF-8') . '</strong> '
             . 'tiene <strong>' . bovs_edad_meses_desde_dias($edad_dias) . ' meses</strong>. '
             . 'La edad mínima para registrar servicio es de <strong>'
             . $minimo_dias . ' días (≈' . $minimo_meses . ' meses)</strong> '
             . '(se ajusta en Parámetros → Períodos).';
    }
}
