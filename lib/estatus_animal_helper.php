<?php
/**
 * ESTATUS DEL ANIMAL (manimales.estatus_animal) — REGLA ÚNICA
 * ─────────────────────────────────────────────────────────────────────
 *   1  ACTIVO           → valor GENÉRICO: aplica a hembras y machos
 *   2  DONADOR ACTIVO   → SOLO machos (donante de semen / inseminación)
 *   3  DONADOR          → SOLO machos
 *   4  DONADORA ACTIVO  → SOLO hembras (donante de embriones)
 *   5  DONADORA         → SOLO hembras
 *
 *  Catálogo en base de datos: `mestatusanimales`
 *  (sexo: 0 = ambos, 1 = hembra, 2 = macho).
 *  Migración: scripts/migracion_estatus_animal.sql
 *
 *  Estas funciones son la FUENTE ÚNICA de la regla y se usan desde:
 *    - controllers/manimales_controller.php        (ficha del animal)
 *    - controllers/mservicios_controller.php       (monta / inseminación / transferencia)
 *    - controllers/mnacimientos_controller.php     (madre / donadora / padre)
 *    - views/animales/frm_registrar.php|frm_editar.php (select de Estatus)
 *
 *  Si la regla cambia se cambia AQUÍ (y en el catálogo de la BD).
 *  Las funciones "lista" / "sql" NO tocan la base de datos: sirven aunque la
 *  migración todavía no se haya aplicado. El catálogo con los NOMBRES sí lee
 *  la tabla y, si no existe, usa los nombres base de este archivo.
 * ─────────────────────────────────────────────────────────────────────
 */

if (!function_exists('bovs_estatus_animal_base')) {
    /** Nombres y sexo de cada estatus (respaldo sin base de datos). */
    function bovs_estatus_animal_base()
    {
        return array(
            1 => array('nombre' => 'ACTIVO',           'sexo' => 0),
            2 => array('nombre' => 'DONADOR ACTIVO',   'sexo' => 2),
            3 => array('nombre' => 'DONADOR',          'sexo' => 2),
            4 => array('nombre' => 'DONADORA ACTIVO',  'sexo' => 1),
            5 => array('nombre' => 'DONADORA',         'sexo' => 1),
        );
    }
}

if (!function_exists('bovs_estatus_animal_lista')) {
    /**
     * Estatus válidos para un sexo (todos los del rebaño).
     *   1 (hembra) → 1, 4, 5      2 (macho) → 1, 2, 3
     *   0 (cualquiera / sin dato)  → 1, 2, 3, 4, 5
     */
    function bovs_estatus_animal_lista($sexo)
    {
        $sexo = intval($sexo);

        if ($sexo === 1) {
            return array(1, 4, 5);
        }
        if ($sexo === 2) {
            return array(1, 2, 3);
        }

        return array(1, 2, 3, 4, 5);
    }
}

if (!function_exists('bovs_estatus_animal_donante_lista')) {
    /** Estatus de DONANTE para un sexo: hembra 4/5 · macho 2/3. */
    function bovs_estatus_animal_donante_lista($sexo)
    {
        return (intval($sexo) === 1) ? array(4, 5) : array(2, 3);
    }
}

if (!function_exists('bovs_estatus_animal_sql')) {
    /** Fragmento SQL "alias.estatus_animal in (…)" para un sexo. */
    function bovs_estatus_animal_sql($sexo, $alias = '')
    {
        $prefijo = ($alias !== '') ? $alias . '.' : '';
        return $prefijo . 'estatus_animal in (' . implode(',', bovs_estatus_animal_lista($sexo)) . ')';
    }
}

if (!function_exists('bovs_estatus_animal_donante_sql')) {
    /** Fragmento SQL con los estatus de donante para un sexo. */
    function bovs_estatus_animal_donante_sql($sexo, $alias = '')
    {
        $prefijo = ($alias !== '') ? $alias . '.' : '';
        return $prefijo . 'estatus_animal in (' . implode(',', bovs_estatus_animal_donante_lista($sexo)) . ')';
    }
}

if (!function_exists('bovs_estatus_animal_valido')) {
    /** ¿El estatus corresponde al sexo indicado? */
    function bovs_estatus_animal_valido($estatus, $sexo)
    {
        return in_array(intval($estatus), bovs_estatus_animal_lista($sexo), true);
    }
}

if (!function_exists('bovs_estatus_animal_sexo')) {
    /** Sexo al que pertenece un estatus (0 = ambos). */
    function bovs_estatus_animal_sexo($estatus)
    {
        $base = bovs_estatus_animal_base();
        $id = intval($estatus);

        return isset($base[$id]) ? intval($base[$id]['sexo']) : 0;
    }
}

if (!function_exists('_ea_q')) {
    /** Ejecuta SQL contra la conexión de Active Record ([] si falla). */
    function _ea_q($sql)
    {
        if (!class_exists('ActiveRecord\\ConnectionManager')) {
            return array();
        }

        try {
            $conn = ActiveRecord\ConnectionManager::get_connection();
            if (!$conn) {
                return array();
            }
            $sth = $conn->query($sql);
            if (!$sth || !method_exists($sth, 'fetchAll')) {
                return array();
            }
            $rows = $sth->fetchAll(PDO::FETCH_ASSOC);

            return $rows ? $rows : array();
        } catch (Exception $e) {
            return array();
        }
    }
}

if (!function_exists('bovs_estatus_animal_catalogo')) {
    /**
     * Catálogo completo (id, nombre, sexo) listo para armar selects.
     * Lee `mestatusanimales` y, si la tabla no existe o está vacía, usa el
     * respaldo de bovs_estatus_animal_base().
     *
     * @param int  $sexo 0 = todos | 1 = hembras | 2 = machos
     *                   (siempre incluye el valor 1 = ACTIVO, que es genérico)
     * @param bool $solo_donantes true = solo los estatus de donante del sexo
     */
    function bovs_estatus_animal_catalogo($sexo = 0, $solo_donantes = false)
    {
        static $cache = null;

        if ($cache === null) {
            $cache = bovs_estatus_animal_base();

            $filas = _ea_q('SELECT id, nombre, sexo, status, orden FROM mestatusanimales ORDER BY orden, id');
            foreach ($filas as $fila) {
                $id = intval($fila['id']);
                $cache[$id] = array(
                    'nombre' => (string) $fila['nombre'],
                    'sexo'   => intval($fila['sexo']),
                    'status' => isset($fila['status']) ? intval($fila['status']) : 1,
                    'orden'  => isset($fila['orden']) ? intval($fila['orden']) : $id,
                );
            }
        }

        $sexo = intval($sexo);
        $salida = array();

        foreach ($cache as $id => $info) {
            if (isset($info['status']) && intval($info['status']) !== 1) {
                continue;
            }

            $sexo_fila = intval($info['sexo']);

            /* El valor genérico (sexo 0) siempre entra */
            if (($sexo_fila !== 0) && ($sexo !== 0) && ($sexo_fila !== $sexo)) {
                continue;
            }

            if ($solo_donantes && !in_array(intval($id), bovs_estatus_animal_donante_lista($sexo), true)) {
                continue;
            }

            $salida[] = array(
                'id'     => intval($id),
                'nombre' => $info['nombre'],
                'sexo'   => $sexo_fila,
            );
        }

        return $salida;
    }
}

if (!function_exists('bovs_estatus_animal_descripcion')) {
    /** Texto para mensajes: "1 ACTIVO, 4 DONADORA ACTIVO, 5 DONADORA". */
    function bovs_estatus_animal_descripcion($sexo)
    {
        $partes = array();

        foreach (bovs_estatus_animal_catalogo($sexo) as $fila) {
            $partes[] = $fila['id'] . ' ' . $fila['nombre'];
        }

        return implode(', ', $partes);
    }
}

if (!function_exists('bovs_estatus_animal_donante_descripcion')) {
    /** Texto de los estatus de DONANTE: hembra "4 DONADORA ACTIVO, 5 DONADORA". */
    function bovs_estatus_animal_donante_descripcion($sexo)
    {
        $partes = array();

        foreach (bovs_estatus_animal_catalogo($sexo, true) as $fila) {
            $partes[] = $fila['id'] . ' ' . $fila['nombre'];
        }

        return implode(', ', $partes);
    }
}

if (!function_exists('bovs_estatus_animal_nombre')) {    /** Nombre del estatus (catálogo, con respaldo). */
    function bovs_estatus_animal_nombre($estatus)
    {
        $base = bovs_estatus_animal_base();
        $id = intval($estatus);

        foreach (bovs_estatus_animal_catalogo(0) as $fila) {
            if ($fila['id'] === $id) {
                return $fila['nombre'];
            }
        }

        return isset($base[$id]) ? $base[$id]['nombre'] : 'No Definido';
    }
}

if (!function_exists('bovs_estatus_animal_opciones_html')) {
    /**
     * Opciones <option> para un select, filtradas por sexo.
     * Se usa en los formularios de la ficha del animal.
     */
    function bovs_estatus_animal_opciones_html($sexo, $seleccionado = 0)
    {
        $html = '';
        $seleccionado = intval($seleccionado);

        foreach (bovs_estatus_animal_catalogo($sexo) as $fila) {
            $html .= '<option value="' . $fila['id'] . '"'
                  . (($fila['id'] === $seleccionado) ? ' selected' : '')
                  . '>' . htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8') . '</option>';
        }

        return $html;
    }
}
