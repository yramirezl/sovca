<?php
/**
 * animal_historial_helper.php
 *
 * Determina si un animal tiene historial (registros) en los módulos operativos
 * del sistema. Se usa para impedir la eliminación de un animal que ya tiene
 * historial: en ese caso el animal debe darse de baja por el módulo de
 * Desincorporación.
 *
 * IMPORTANTE: el contexto que invoca este helper debe haber cargado previamente
 * el modelo Manimale (que extiende ActiveRecord\Model). El helper no carga
 * modelos por sí solo.
 *
 * Compatible: PHP 7.4 y 8.3
 */

if (!function_exists('animal_tiene_historial')) {
    /**
     * Verifica si un animal tiene registros en alguno de los módulos.
     *
     * @param int $id_animal  id del animal (manimales.id)
     * @param int $mfundos_id fundo activo
     * @return array{
     *     tiene: bool,
     *     modulos: string[],
     *     detalle: array<int, array{modulo: string, registros: int}>
     * }
     */
    function animal_tiene_historial($id_animal, $mfundos_id)
    {
        $resultado = array('tiene' => false, 'modulos' => array(), 'detalle' => array());

        $id_animal  = (int)$id_animal;
        $mfundos_id = (int)$mfundos_id;

        if ($id_animal <= 0 || $mfundos_id <= 0 || !class_exists('Manimale')) {
            return $resultado;
        }

        // Datos del animal (código RGB y código de ternero)
        $filas = @Manimale::find_by_sql("
            SELECT id,
                   COALESCE(codigo_rgb_id_animal, '') AS codigo_rgb_id_animal,
                   COALESCE(codigo_ternero, '')       AS codigo_ternero
              FROM manimales
             WHERE id = $id_animal AND mfundos_id = $mfundos_id
             LIMIT 1");
        if (!$filas || !isset($filas[0])) {
            return $resultado;
        }

        $codigo  = addslashes(trim((string)$filas[0]->codigo_rgb_id_animal));
        $ternero = addslashes(trim((string)$filas[0]->codigo_ternero));

        // Etiqueta visible => SQL que devuelve una única columna `c` con el conteo.
        $consultas = array();

        if ($codigo !== '') {
            $consultas['Control sanitario'] = "
                SELECT COUNT(*) AS c FROM mcontrolsanitarios
                 WHERE codigo_rgb_id_animal = '$codigo'
                   AND mfundos_id = $mfundos_id AND status = 1";

            $consultas['Control de peso'] = "
                SELECT COUNT(*) AS c FROM mconsecutivopesos
                 WHERE codigo_rgb_id_animal = '$codigo'
                   AND mfundos_id = $mfundos_id AND status = 1";

            $consultas['Producción de leche'] = "
                SELECT COUNT(*) AS c FROM mprolecheras
                 WHERE codigo_rgb_id_animal_hembra = '$codigo'
                   AND mfundos_id = $mfundos_id AND status = 1";

            $consultas['Servicios'] = "
                SELECT COUNT(*) AS c FROM mregservicios
                 WHERE (codigo_rgb_id_animal_hembra = '$codigo'
                     OR codigo_rgb_id_animal_macho  = '$codigo'
                     OR codigo_rgb_id_animal_dona   = '$codigo')
                   AND mfundos_id = $mfundos_id AND status = 1";

            $consultas['Nacimientos (madre / padre / donadora)'] = "
                SELECT COUNT(*) AS c FROM mregnacimientos
                 WHERE (codigo_rgb_id_animal_hembra = '$codigo'
                     OR codigo_rgb_id_animal_macho  = '$codigo'
                     OR codigo_rgb_id_animal_dona   = '$codigo')
                   AND mfundos_id = $mfundos_id AND status = 1";

            $consultas['Desincorporación'] = "
                SELECT COUNT(*) AS c FROM mdesincorporacionanimales
                 WHERE codigo_rgb_id_animal = '$codigo'
                   AND mfundos_id = $mfundos_id AND status = 1";
        }

        // Chequeos por id del animal (no dependen del código RGB)
        $consultas['Control sanitario (por lote)'] = "
            SELECT COUNT(*) AS c FROM mdetacsxlotes
             WHERE manimales_id = $id_animal
               AND mfundos_id = $mfundos_id AND status = 1";

        $consultas['Movimientos de rebaño'] = "
            SELECT COUNT(*) AS c
              FROM mmovimientos_rebano_detalle mrd
        INNER JOIN mmovimientos_rebano mr ON mr.id = mrd.mmovimientos_rebano_id
             WHERE mrd.manimales_id = $id_animal AND mr.status = 1";

        // Vínculos por código de ternero / código del animal (crías fichadas)
        $codigos_ternero = array();
        if ($ternero !== '') {
            $codigos_ternero["'$ternero'"] = true;
        }
        if ($codigo !== '') {
            $codigos_ternero["'$codigo'"] = true;
        }

        if (!empty($codigos_ternero)) {
            $in = implode(',', array_keys($codigos_ternero));

            $consultas['Nacimientos (cría)'] = "
                SELECT COUNT(*) AS c FROM mregnacimientos
                 WHERE codigo_ternero IN ($in)
                   AND mfundos_id = $mfundos_id AND status = 1";

            $consultas['Nacimientos (detalle de cría)'] = "
                SELECT COUNT(*) AS c FROM mternerosxnacimientos
                 WHERE id_ternero IN ($in)
                   AND mfundos_id = $mfundos_id AND status = 1";

            $consultas['Desincorporación (sin ficha)'] = "
                SELECT COUNT(*) AS c FROM mdsinfichas
                 WHERE codigo_ternero IN ($in)
                   AND mfundos_id = $mfundos_id AND status = 1";
        }

        foreach ($consultas as $etiqueta => $sql) {
            $q    = @Manimale::find_by_sql($sql);
            $cant = ($q && isset($q[0]->c)) ? (int)$q[0]->c : 0;

            if ($cant > 0) {
                $resultado['tiene']     = true;
                $resultado['modulos'][] = $etiqueta;
                $resultado['detalle'][] = array('modulo' => $etiqueta, 'registros' => $cant);
            }
        }

        return $resultado;
    }
}


if (!function_exists('animal_historial_mensaje')) {
    /**
     * Construye el HTML del mensaje de bloqueo de eliminación.
     *
     * @param array $historial  salida de animal_tiene_historial()
     * @param string $codigo    código del animal (opcional, para mostrar)
     * @return string
     */
    function animal_historial_mensaje($historial, $codigo = '')
    {
        $modulos = (isset($historial['modulos']) && is_array($historial['modulos']))
            ? $historial['modulos'] : array();

        $lista = '';
        foreach ($modulos as $m) {
            $lista .= '<li>' . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        $ref = $codigo !== ''
            ? 'El animal <strong>' . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') . '</strong>'
            : 'Este animal';

        return '<div class="alert alert-warning alert-dismissable">'
             . '<button class="close" aria-hidden="true" data-dismiss="alert" type="button">&times;</button>'
             . '<h4><i class="icon fa fa-warning"></i> No se puede eliminar</h4>'
             . $ref . ' ya tiene historial en el sistema:'
             . '<ul style="margin:8px 0 0 0; padding-left:18px;">' . $lista . '</ul>'
             . '<p style="margin:8px 0 0 0;">Para dar de baja al animal use el módulo de '
             . '<a href="../desincorporacion/"><strong>Desincorporación</strong></a>.</p>'
             . '</div>';
    }
}
