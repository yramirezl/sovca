<?php
/**
 * arete_helper.php — REGLA ÚNICA del N° de arete / microchip
 * ─────────────────────────────────────────────────────────────────────────
 * Un animal ACTIVO de un fundo no puede repetir el N° de arete de otro
 * animal activo del mismo fundo. Antes esa regla vivía copiada en cada
 * módulo y cada copia comparaba distinto:
 *
 *   - la web comparaba `nro_arete = 'X'` (sensible a mayúsculas y a los
 *     espacios internos: "322  JR" pasaba como distinto de "322 JR");
 *   - la edición y la validación del formulario usaban un `NOT IN
 *     (SELECT nro_arete ...)` que excluía por VALOR de arete, así que al
 *     editar nunca detectaban el duplicado;
 *   - la API y la app offline comparaban con UPPER(TRIM()).
 *
 * Aquí queda una sola forma de normalizar y una sola consulta:
 *
 *   bovs_arete_normalizar()        "322  jr " → "322 JR"
 *   bovs_arete_existente()         el animal activo que ya usa ese arete
 *   bovs_arete_mensaje()           texto listo para mostrar al usuario
 *   bovs_arete_duplicados()        auditoría: aretes repetidos en el fundo
 *   bovs_arete_base_compartida()   auditoría: "332" vs "332 JR"
 *
 * Criterios (los mismos en web, API y offline):
 *   - Mayúsculas y sin espacios de sobra; el acento NO se quita (un arete
 *     con Ñ es distinto de uno con N).
 *   - Solo se compara contra animales con `status = 1`: un animal dado de
 *     baja libera su arete, igual que hacía la web original.
 *   - El arete vacío nunca bloquea (hay animales sin arete).
 *
 * Compatible PHP 7.4 / 8.3. No carga modelos: usa la conexión de
 * php-activerecord, igual que lib/estatus_animal_helper.php.
 */

if (!function_exists('bovs_arete_formatear')) {
    /**
     * FORMATO CANÓNICO del N° de arete:
     *   - todo en MAYÚSCULAS (los acentos se convierten a su letra: Ñ→N, Á→A);
     *   - cualquier símbolo o espacio se reemplaza por un guion;
     *   - nunca dos guiones seguidos;
     *   - sin guion al inicio (al final SÍ se permite: es el ajuste de duplicados);
     *   - máximo 15 caracteres.
     *
     * El productor elige la combinación (9321A, A9321, 322-JR…); el sistema
     * solo la deja en esta forma.
     *
     *   ' 322  jr '     → '322-JR'
     *   'SN 2-1'        → 'SN-2-1'
     *   'NRO. PIERNA: 22/25' → 'NRO-PIERNA-22-2' (recortado a 15)
     */
    function bovs_arete_formatear($valor, $maximo = 15)
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }

        $acentos = array(
            'á' => 'A', 'à' => 'A', 'ä' => 'A', 'â' => 'A', 'ã' => 'A',
            'é' => 'E', 'è' => 'E', 'ë' => 'E', 'ê' => 'E',
            'í' => 'I', 'ì' => 'I', 'ï' => 'I', 'î' => 'I',
            'ó' => 'O', 'ò' => 'O', 'ö' => 'O', 'ô' => 'O', 'õ' => 'O',
            'ú' => 'U', 'ù' => 'U', 'ü' => 'U', 'û' => 'U',
            'ñ' => 'N', 'ç' => 'C',
        );
        $texto = strtr($texto, $acentos);
        $texto = function_exists('mb_strtoupper')
            ? mb_strtoupper($texto, 'UTF-8')
            : strtoupper($texto);

        $texto = preg_replace('/[^0-9A-Z]+/', '-', $texto);
        $texto = trim($texto);
        $texto = ltrim($texto, '-');

        if ($maximo > 0) {
            $largo = function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
            if ($largo > $maximo) {
                $texto = function_exists('mb_substr')
                    ? mb_substr($texto, 0, $maximo, 'UTF-8')
                    : substr($texto, 0, $maximo);
                $texto = rtrim($texto, '-');
            }
        }

        return $texto;
    }
}

if (!function_exists('bovs_arete_variante')) {
    /**
     * Variante de un arete para resolver un duplicado, conservando el formato
     * y el máximo de caracteres. La primera variante es un guion final
     * ('322JR' → '322JR-'), tal como se acordó con el productor; si el arete
     * ya termina en guion se pasa a '-2', '-3'…
     *
     * @param string $base arete ya formateado
     * @param int    $n    1 = primer duplicado, 2 = segundo, …
     */
    function bovs_arete_variante($base, $n, $maximo = 15)
    {
        $base = bovs_arete_formatear($base, $maximo);
        $n    = (int) $n;
        if ($n <= 0) {
            return $base;
        }

        $sufijo    = ($n === 1) ? '-' : '-' . $n;
        $limpiar   = rtrim($base, '-');
        $disponible = $maximo - strlen($sufijo);
        if ($disponible < 1) {
            return substr($sufijo, 0, $maximo);
        }

        return rtrim(substr($limpiar, 0, $disponible), '-') . $sufijo;
    }
}

if (!function_exists('bovs_arete_sql_canonico')) {
    /**
     * Expresión SQL con el MISMO formato que bovs_arete_formatear(), para
     * comparar contra los valores guardados (incluidos los que todavía no se
     * migraron). No trunca a 15: después de scripts/normalizar_aretes.php el
     * dato ya tiene ese largo.
     */
    function bovs_arete_sql_canonico($columna = 'nro_arete')
    {
        $col = preg_match('/^[a-z0-9_\.]+$/i', $columna) ? $columna : 'nro_arete';

        return "ltrim(regexp_replace(translate(upper(btrim(coalesce(" . $col . ", ''))), "
            . "'ÁÀÄÂÃÉÈËÊÍÌÏÎÓÒÖÔÕÚÙÜÛÑÇáàäâãéèëêíìïîóòöôõúùüûñç', "
            . "'AAAAAEEEEIIIIOOOOOUUUUNCaaaaaeeeeiiiiooooouuuunc'), "
            . "'[^0-9A-Z]+', '-', 'g'), '-')";
    }
}

if (!function_exists('bovs_arete_normalizar')) {
    /** Forma canónica del arete (alias histórico de bovs_arete_formatear). */
    function bovs_arete_normalizar($valor)
    {
        return bovs_arete_formatear($valor);
    }
}

if (!function_exists('bovs_arete_numero_inicial')) {
    /**
     * Bloque numérico con el que empieza el arete: "332 JR" → "332",
     * "0332" → "332", "CARA PELA" → "". Sirve solo para AUDITAR aretes
     * que comparten número ("332" y "332 JR" no son duplicados exactos,
     * pero sí el mismo número físico repetido en dos animales).
     */
    function bovs_arete_numero_inicial($valor)
    {
        $normalizado = bovs_arete_normalizar($valor);
        if ($normalizado === '' || !preg_match('/^0*([0-9]+)/u', $normalizado, $m)) {
            return '';
        }

        return ltrim($m[1], '0');
    }
}

if (!function_exists('_arete_q')) {
    /** Ejecuta SQL contra la conexión de Active Record ([] si falla). */
    function _arete_q($sql, $valores = array())
    {
        if (!class_exists('ActiveRecord\\ConnectionManager')) {
            return array();
        }

        try {
            $conn = ActiveRecord\ConnectionManager::get_connection();
            if (!$conn) {
                return array();
            }
            $sth = $conn->query($sql, $valores);
            if (!$sth || !method_exists($sth, 'fetchAll')) {
                return array();
            }
            $filas = $sth->fetchAll(PDO::FETCH_ASSOC);

            return $filas ? $filas : array();
        } catch (Exception $e) {
            return array();
        }
    }
}

if (!function_exists('bovs_arete_existente')) {
    /**
     * Animal ACTIVO del fundo que ya tiene ese N° de arete.
     *
     * @param int   $fundoId  fundo donde se está guardando
     * @param string $arete   valor tal como lo escribió el usuario
     * @param array $opciones 'excluir_id' => id del propio animal (al editar)
     *                        'excluir_codigo' => código del propio animal
     *                        'incluir_bajas' => true para auditar también status=0
     * @return array|null  ['id','codigo_rgb_id_animal','nombre','nro_arete']
     */
    function bovs_arete_existente($fundoId, $arete, $opciones = array())
    {
        $normalizado = bovs_arete_normalizar($arete);
        $fundoId     = (int) $fundoId;

        if ($normalizado === '' || $fundoId <= 0) {
            return null;
        }

        $condiciones = array(
            'mfundos_id = ?',
            bovs_arete_sql_canonico('nro_arete') . ' = ?',
        );
        $valores = array($fundoId, $normalizado);

        if (empty($opciones['incluir_bajas'])) {
            $condiciones[] = 'status = 1';
        }

        if (!empty($opciones['excluir_id'])) {
            $condiciones[] = 'id <> ?';
            $valores[]     = (int) $opciones['excluir_id'];
        }

        if (!empty($opciones['excluir_codigo'])) {
            $condiciones[] = "btrim(upper(coalesce(codigo_rgb_id_animal, ''))) <> ?";
            $valores[]     = strtoupper(trim((string) $opciones['excluir_codigo']));
        }

        $filas = _arete_q(
            'SELECT id, codigo_rgb_id_animal, nombre, nro_arete
               FROM manimales
              WHERE ' . implode(' AND ', $condiciones) . '
              ORDER BY id
              LIMIT 1',
            $valores
        );

        return $filas ? $filas[0] : null;
    }
}

if (!function_exists('bovs_arete_mensaje')) {
    /** Texto para el usuario a partir de la fila que devuelve bovs_arete_existente(). */
    function bovs_arete_mensaje($existente)
    {
        if (!$existente) {
            return '';
        }

        $etiquetas = array();
        if (!empty($existente['codigo_rgb_id_animal'])) {
            $etiquetas[] = $existente['codigo_rgb_id_animal'];
        }
        if (!empty($existente['nombre'])) {
            $etiquetas[] = $existente['nombre'];
        }

        $animal = $etiquetas ? ' (' . implode(' — ', $etiquetas) . ')' : '';
        $arete  = !empty($existente['nro_arete']) ? $existente['nro_arete'] : '';

        return 'El N° de arete ' . $arete . $animal . ' ya está asignado a otro animal activo de este fundo.';
    }
}

if (!function_exists('bovs_arete_duplicados')) {
    /**
     * Aretes repetidos EXACTOS entre animales activos de un fundo.
     * Si $fundoId es 0 se revisan todos los fundos.
     *
     * @return array<int, array{fundos_id:int, arete:string, total:int, animales:string}>
     */
    function bovs_arete_duplicados($fundoId = 0)
    {
        $sql = "SELECT mfundos_id AS fundos_id,
                       " . bovs_arete_sql_canonico('nro_arete') . " AS arete,
                       count(*) AS total,
                       string_agg(codigo_rgb_id_animal || ' ' || coalesce(nombre, ''), ' | ' ORDER BY id) AS animales
                  FROM manimales
                 WHERE status = 1
                   AND btrim(coalesce(nro_arete, '')) <> ''";
        $valores = array();

        if ((int) $fundoId > 0) {
            $sql .= ' AND mfundos_id = ?';
            $valores[] = (int) $fundoId;
        }

        $sql .= ' GROUP BY 1, 2 HAVING count(*) > 1 ORDER BY 1, 2';

        return _arete_q($sql, $valores);
    }
}

if (!function_exists('bovs_arete_base_compartida')) {
    /**
     * Aretes que comparten el número inicial sin ser idénticos
     * ("332" y "332 JR"). No es un duplicado exacto: son dos chapetas
     * distintas, pero el productor debe revisarlo porque en pantalla y en
     * las búsquedas se confunden.
     *
     * @return array<int, array{numero:string, animales:array<int, string>}>
     */
    function bovs_arete_base_compartida($fundoId)
    {
        $fundoId = (int) $fundoId;
        if ($fundoId <= 0) {
            return array();
        }

        $filas = _arete_q(
            "SELECT id, codigo_rgb_id_animal, nombre, nro_arete
               FROM manimales
              WHERE mfundos_id = ? AND status = 1
                AND btrim(coalesce(nro_arete, '')) <> ''
              ORDER BY id",
            array($fundoId)
        );

        $grupos = array();
        foreach ($filas as $fila) {
            $numero = bovs_arete_numero_inicial($fila['nro_arete']);
            if ($numero === '') {
                continue;
            }
            $grupos[$numero][] = trim($fila['nro_arete']) . ' → ' . trim($fila['codigo_rgb_id_animal'] . ' ' . $fila['nombre']);
        }

        $compartidos = array();
        foreach ($grupos as $numero => $animales) {
            $aretes = array();
            foreach ($animales as $texto) {
                $aretes[] = bovs_arete_normalizar(substr($texto, 0, strpos($texto, ' → ')));
            }            if (count($animales) > 1 && count(array_unique($aretes)) > 1) {
                $compartidos[] = array('numero' => (string) $numero, 'animales' => $animales);
            }
        }

        return $compartidos;
    }
}
