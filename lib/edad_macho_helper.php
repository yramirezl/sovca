<?php
/**
 * EDAD MÍNIMA DEL MACHO PARA REGISTRAR SERVICIO — REGLA ÚNICA
 * ─────────────────────────────────────────────────────────────────────
 * El sistema ya exigía 12 meses a la hembra, pero no miraba la edad del
 * macho: se podía registrar una monta con un becerro de 6 meses.
 *
 * El valor NO está en el código: se configura como un período más
 * (`mperiodos`, código `EMM`), igual que PSP / PPS / PGB, desde
 * Parámetros → Períodos:
 *
 *     Nombre del Periodo : EDAD MINIMA DEL MACHO PARA SERVICIO (DIAS)
 *     Días de Gestación  : 365      (≈ 12 meses)
 *
 * Resolución: fila del fundo → fila global (mfundos_id = 0) → 365 días.
 *
 * Se usa desde:
 *   - controllers/mservicios_controller.php  (listado de candidatos, búsqueda
 *     del macho y registro del servicio)
 *   - api/catalogos_controller.php           (la PWA recibe el parámetro y
 *     aplica la misma regla sin conexión)
 *
 * Migración: scripts/migracion_edad_minima_macho.sql
 * ─────────────────────────────────────────────────────────────────────
 */

if (!function_exists('_ea_q')) {
    require_once __DIR__ . '/estatus_animal_helper.php';
}

if (!function_exists('bovs_edad_minima_macho_dias')) {
    /**
     * Edad mínima (en días) que debe tener un macho para registrar servicio.
     *
     * @param int $mfundos_id     fundo activo (0 = solo el global)
     * @param int $tipo_ganaderia mtipoganaderias_id (0 = cualquiera)
     * @return int días (365 por defecto)
     */
    function bovs_edad_minima_macho_dias($mfundos_id = 0, $tipo_ganaderia = 0)
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
                   and codper_bov = \'EMM\'
                   and mfundos_id in (0, ' . $mfundos_id . ')
                   ' . $filtro_tipo . '
                 order by case when mfundos_id = ' . $mfundos_id . ' then 0 else 1 end,
                          ' . $preferir_tipo . '
                          id desc
                 limit 1';

        $filas = _ea_q($sql);
        $dias = (!empty($filas) && intval($filas[0]['dias_gestacion']) > 0)
            ? intval($filas[0]['dias_gestacion'])
            : 365;

        $cache[$clave] = $dias;

        return $dias;
    }
}

if (!function_exists('bovs_edad_meses_desde_dias')) {
    /** Convierte días en meses cumplidos (para los mensajes al productor). */
    function bovs_edad_meses_desde_dias($dias)
    {
        $dias = intval($dias);

        return ($dias > 0) ? (int) floor($dias / 30.4375) : 0;
    }
}

if (!function_exists('bovs_error_edad_macho')) {
    /**
     * Motivo por el que este macho NO puede registrar servicio, o '' si está
     * en condiciones. Devuelve texto plano (quien llama lo envuelve en el
     * aviso que corresponda).
     *
     * @param string $codigo        código del macho (codigo_rgb_id_animal)
     * @param int    $mfundos_id    fundo activo
     * @param int    $tipo_ganaderia mtipoganaderias_id
     */
    function bovs_error_edad_macho($codigo, $mfundos_id, $tipo_ganaderia = 0)
    {
        $codigo = trim((string) $codigo);

        if ($codigo === '') {
            return '';
        }

        $filas = _ea_q(
            'select codigo_rgb_id_animal, coalesce(nombre, \'\') as nombre,
                    case when f_nacimiento is null then null
                         else (current_date - f_nacimiento) end as edad_dias
               from manimales
              where mfundos_id = ' . intval($mfundos_id) . '
                and codigo_rgb_id_animal = \'' . str_replace("'", "''", $codigo) . '\'
                and status = 1
              limit 1'
        );

        if (empty($filas)) {
            return '';
        }

        $minimo_dias = bovs_edad_minima_macho_dias($mfundos_id, $tipo_ganaderia);
        $edad_dias = $filas[0]['edad_dias'];

        /* Sin fecha de nacimiento no se puede comprobar la edad. Se deja
           pasar: en los hatos hay toros y donadores históricos sin fecha y
           bloquearlos impediría registrar servicios legítimos (el 29 % de
           los donadores no la tiene). La ficha lo muestra para completarla. */
        if ($edad_dias === null) {
            return '';
        }

        if (intval($edad_dias) >= $minimo_dias) {
            return '';
        }

        $nombre = trim((string) $filas[0]['nombre']);

        return 'El macho <strong>' . htmlspecialchars($codigo . ($nombre !== '' ? ' · ' . $nombre : ''), ENT_QUOTES, 'UTF-8') . '</strong> '
             . 'tiene <strong>' . bovs_edad_meses_desde_dias($edad_dias) . ' meses</strong>. '
             . 'La edad mínima para registrar servicio es de <strong>'
             . bovs_edad_meses_desde_dias($minimo_dias) . ' meses</strong> '
             . '(se ajusta en Parámetros → Períodos).';
    }
}
