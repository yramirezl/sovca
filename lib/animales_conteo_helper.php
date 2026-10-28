<?php
/**
 * Criterio único de "animal activo" del sistema.
 * ─────────────────────────────────────────────────────────────
 * Cuenta para el plan cuando:
 *   · no fue eliminado        → manimales.status = 1
 *   · sigue vivo en el hato   → manimales.mestatus_id = 1 (ACTIVO)
 *   · es ACTIVO (1), DONADOR ACTIVO (2) o DONADORA ACTIVO (4)
 *
 * No cuentan:
 *   · DONADOR (3) y DONADORA (5) sin habilitar
 *   · DESCARTE (2), MUERTO (3), VENDIDO (4), EXTRAVIADO (5) y
 *     MIGRADO A OTRO FUNDO (7) en mestatus_id
 *   · nacimientos sin fichar (todavía no existen en manimales)
 *
 * Se usa para el cupo del plan, el panel de fundos, el menú lateral y el
 * tablero, de modo que todos muestren el mismo número.
 */

if (!function_exists('bovs_animal_activo_sql')) {
    /**
     * Condición SQL de animal activo para un alias de la tabla manimales.
     *
     * @param string $alias Alias de manimales ('' para consultas sin alias)
     * @return string
     */
    function bovs_animal_activo_sql($alias = 'a')
    {
        $p = ($alias === '' || $alias === null) ? '' : $alias . '.';
        return "{$p}status = 1 AND {$p}mestatus_id = 1 AND {$p}estatus_animal IN (1, 2, 4)";
    }
}

if (!function_exists('bovs_contar_animales_activos')) {
    /**
     * Cuenta los animales activos de un fundo.
     *
     * @param int      $mfundos_id
     * @param int|null $tipo_ganaderia 1 ovina, 2 caprina (null = todas)
     * @param mixed    $conexion       Conexión PDO/ActiveRecord opcional
     * @return int
     */
    function bovs_contar_animales_activos($mfundos_id, $tipo_ganaderia = null, $conexion = null)
    {
        $fid = (int) $mfundos_id;
        if ($fid <= 0) {
            return 0;
        }

        $sql = "SELECT COUNT(*) AS total FROM manimales a
                 WHERE a.mfundos_id = {$fid} AND " . bovs_animal_activo_sql('a');

        if ($tipo_ganaderia !== null && (int) $tipo_ganaderia > 0) {
            $sql .= " AND a.mtipoganaderias_id = " . (int) $tipo_ganaderia;
        }

        try {
            if ($conexion === null && class_exists('ActiveRecord\\ConnectionManager')) {
                $conexion = ActiveRecord\ConnectionManager::get_connection();
            }
            if (!$conexion) {
                return 0;
            }

            $stmt = $conexion->query($sql);
            if (!$stmt) {
                return 0;
            }
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            return $fila ? (int) $fila['total'] : 0;
        } catch (Throwable $e) {
            return 0;
        }
    }
}
