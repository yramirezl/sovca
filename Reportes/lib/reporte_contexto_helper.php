<?php
/**
 * SOVCA | Contexto de los reportes (nomenclatura de la salida)
 * ---------------------------------------------------------------------------
 * Arma el "quién/qué" de un reporte a partir de los parámetros que envía el
 * Gestor de Reportes Parametrizables (`%$%` = base64 de la query string) y de
 * la base de datos, para que TODOS los reportes usen la misma nomenclatura:
 *
 *   · individual → REPORTE + nombre y arete del animal + fecha de generado
 *   · por lote   → REPORTE + nombre del lote + fecha de generado
 *   · por rango  → REPORTE + rango (fechas y/o códigos) + fecha de generado
 *
 * Uso desde un wrapper (Reportes/reportes/sovca/*.php):
 *
 *     require_once('../../lib/reporte_contexto_helper.php');
 *     $ctx = bovs_reporte_contexto($mfundos_id);
 *     $obj->SetTitle(bovs_reporte_titulo('REPORTE DE PRODUCCIÓN DE LECHE', $ctx));
 *     $obj->contexto_detalle = $ctx['detalle'];
 *     $name = bovs_reporte_nombre_archivo('Produccion_de_Leche', $ctx);
 *
 * Los PDF solo necesitan imprimir `$this->contexto_detalle` en su encabezado.
 *
 * Compatible con PHP 7.4 y 8.3.
 */

if (!function_exists('bovs_reporte_parametros_raw')) {
    /**
     * Parámetros crudos del gestor (base64). Equivale a getvalue('%$%').
     */
    function bovs_reporte_parametros_raw()
    {
        $claves = array('%$%', '$%', '%24%25');

        foreach (array($_GET, $_POST) as $fuente) {
            foreach ($claves as $clave) {
                if (isset($fuente[$clave]) && $fuente[$clave] !== '') {
                    return (string) $fuente[$clave];
                }
            }
        }

        return '';
    }
}

if (!function_exists('bovs_reporte_filtros')) {
    /**
     * Devuelve los filtros del reporte normalizados (clave => valor).
     *
     * @param string|null $parametros Base64 o query string. null = leer de %$%.
     * @return array
     */
    function bovs_reporte_filtros($parametros = null)
    {
        $out = array(
            'codigo_bovs'     => '',
            'cod_bovs'        => '',
            'cod_bovs_inicio' => '',
            'cod_bovs_final'  => '',
            'codigo_erete'    => '',
            'nro_arete'       => '',
            'fecha_inicio'    => '',
            'fecha_final'     => '',
            'lote_unico'      => '',
            'lote_inicio'     => '',
            'lote_final'      => '',
            'modo'            => ''
        );

        if ($parametros === null) {
            $parametros = bovs_reporte_parametros_raw();
        }

        $parametros = trim((string) $parametros);
        if ($parametros === '') {
            return $out;
        }

        /*
         * El gestor envía base64 de la query. Se reconoce por su alfabeto y su
         * longitud (múltiplo de 4); si se decodifica y contiene "clave=valor"
         * se usa el texto decodificado. Así se aceptan también enlaces
         * directos que ya traen la query sin codificar.
         */
        if (preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $parametros)
            && (strlen($parametros) % 4) === 0) {
            $decodificado = base64_decode($parametros, true);
            if ($decodificado !== false && strpos($decodificado, '=') !== false) {
                $parametros = $decodificado;
            }
        }

        foreach (explode('&', $parametros) as $trozo) {
            if ($trozo === '' || strpos($trozo, '=') === false) {
                continue;
            }

            $partes = explode('=', $trozo, 2);
            $clave  = trim($partes[0]);
            $valor  = isset($partes[1]) ? urldecode($partes[1]) : '';

            if (array_key_exists($clave, $out)) {
                $out[$clave] = trim($valor);
            }
        }

        return $out;
    }
}

if (!function_exists('bovs_reporte_fecha_dmy')) {
    /** Normaliza una fecha a dd-mm-aaaa ('' si no es válida). */
    function bovs_reporte_fecha_dmy($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }

        foreach (array('Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d') as $formato) {
            $d = DateTime::createFromFormat($formato, $valor);
            if ($d instanceof DateTime) {
                return $d->format('d-m-Y');
            }
        }

        $ts = strtotime($valor);

        return ($ts === false) ? '' : date('d-m-Y', $ts);
    }
}

if (!function_exists('bovs_reporte_consulta')) {
    /**
     * SELECT con la conexión de los reportes (ADOdb). Devuelve array de filas.
     * Nunca lanza: si algo falla devuelve array().
     */
    function bovs_reporte_consulta($sql)
    {
        if (!class_exists('basedatosAdoIbase')) {
            return array();
        }

        try {
            $bd = new basedatosAdoIbase();
            $rs = $bd->select($sql);

            if (!$rs) {
                return array();
            }

            if (is_array($rs)) {
                return $rs;
            }

            if (method_exists($rs, 'GetRows')) {
                $filas = $rs->GetRows();
                return is_array($filas) ? $filas : array();
            }

            if ($rs instanceof Traversable) {
                $filas = array();
                foreach ($rs as $fila) {
                    $filas[] = $fila;
                }
                return $filas;
            }
        } catch (Exception $e) {
            return array();
        }

        return array();
    }
}

if (!function_exists('bovs_reporte_sql_texto')) {
    /** Escapa un texto para usarlo en un SQL de los reportes. */
    function bovs_reporte_sql_texto($valor)
    {
        return str_replace("'", "''", (string) $valor);
    }
}

if (!function_exists('bovs_reporte_animal')) {
    /**
     * Busca el animal por código SOVCA (tolerando el relleno de ceros) o por
     * N° de arete. Devuelve ['codigo','nombre','nro_arete'] o null.
     */
    function bovs_reporte_animal($codigo, $mfundos_id, $por_arete = false)
    {
        $codigo = trim((string) $codigo);
        $mfundos_id = intval($mfundos_id);

        if ($codigo === '' || $mfundos_id <= 0) {
            return null;
        }

        if ($por_arete) {
            $condicion = "TRIM(COALESCE(nro_arete, '')) = TRIM('" . bovs_reporte_sql_texto($codigo) . "')";
        } else {
            $candidatos = array($codigo);
            if (function_exists('bovs_codigos_candidatos')) {
                $candidatos = bovs_codigos_candidatos($codigo, $mfundos_id);
            } else {
                $partes = explode('-', $codigo);
                $nro = intval(end($partes));
                if ($nro > 0) {
                    $candidatos[] = 'SOVCA-0' . $mfundos_id . '-0' . $nro;
                }
            }

            $lista = array();
            foreach ($candidatos as $c) {
                $lista[] = "'" . bovs_reporte_sql_texto($c) . "'";
            }
            $condicion = 'codigo_rgb_id_animal IN (' . implode(',', array_unique($lista)) . ')';
        }

        $sql = "SELECT codigo_rgb_id_animal AS codigo,
                       COALESCE(nombre, '')    AS nombre,
                       COALESCE(nro_arete, '') AS nro_arete
                  FROM manimales
                 WHERE mfundos_id = " . $mfundos_id . "
                   AND " . $condicion . "
                 ORDER BY CASE WHEN status = 1 THEN 0 ELSE 1 END, id DESC
                 LIMIT 1;";

        $filas = bovs_reporte_consulta($sql);

        return ($filas && isset($filas[0]['codigo'])) ? $filas[0] : null;
    }
}

if (!function_exists('bovs_reporte_lotes')) {
    /**
     * Nombres de los lotes del rango indicado.
     */
    function bovs_reporte_lotes($desde, $hasta, $mfundos_id)
    {
        $desde = intval($desde);
        $hasta = intval($hasta);
        $mfundos_id = intval($mfundos_id);

        if ($desde <= 0 && $hasta <= 0) {
            return array();
        }
        if ($desde <= 0) { $desde = $hasta; }
        if ($hasta <= 0) { $hasta = $desde; }
        if ($desde > $hasta) { $tmp = $desde; $desde = $hasta; $hasta = $tmp; }

        $sql = "SELECT nombre
                  FROM mlotes
                 WHERE mfundos_id = " . $mfundos_id . "
                   AND id BETWEEN " . $desde . " AND " . $hasta . "
                 ORDER BY nombre;";

        $filas  = bovs_reporte_consulta($sql);
        $nombres = array();

        foreach ($filas as $fila) {
            if (isset($fila['nombre']) && trim($fila['nombre']) !== '') {
                $nombres[] = trim($fila['nombre']);
            }
        }

        return $nombres;
    }
}

if (!function_exists('bovs_reporte_contexto')) {
    /**
     * Contexto del reporte para títulos, nombres de archivo y encabezados.
     *
     * @param int         $mfundos_id  Fundo activo.
     * @param string|null $parametros  Base64/query. null = leer de %$%.
     * @return array
     */
    function bovs_reporte_contexto($mfundos_id = 0, $parametros = null)
    {
        if (intval($mfundos_id) <= 0 && isset($_SESSION['mfundos_id'])) {
            $mfundos_id = intval($_SESSION['mfundos_id']);
        }
        $mfundos_id = intval($mfundos_id);

        $f = bovs_reporte_filtros($parametros);

        $fecha_desde = bovs_reporte_fecha_dmy($f['fecha_inicio']);
        $fecha_hasta = bovs_reporte_fecha_dmy($f['fecha_final']);
        if ($fecha_desde !== '' && $fecha_hasta !== '' && $fecha_desde > $fecha_hasta) {
            $tmp = $fecha_desde; $fecha_desde = $fecha_hasta; $fecha_hasta = $tmp;
        }

        /* ---------------- Animal vs. rango de códigos ---------------- */
        $cod_individual = $f['cod_bovs'] !== '' ? $f['cod_bovs'] : $f['codigo_bovs'];
        $cod_desde      = $f['cod_bovs_inicio'];
        $cod_hasta      = $f['cod_bovs_final'];

        if ($cod_individual === '' && $cod_desde !== '') {
            if ($cod_hasta === '' || $cod_desde === $cod_hasta) {
                $cod_individual = $cod_desde;
            }
        }

        $es_rango_codigos = ($cod_individual === '' && $cod_desde !== '' && $cod_hasta !== ''
                             && $cod_desde !== $cod_hasta);

        /* ---------------- Lote ---------------- */
        $lote_desde = intval($f['lote_unico']) > 0 ? intval($f['lote_unico']) : intval($f['lote_inicio']);
        $lote_hasta = intval($f['lote_unico']) > 0 ? intval($f['lote_unico']) : intval($f['lote_final']);
        $hay_lote   = ($lote_desde > 0 || $lote_hasta > 0);
        $es_rango_lotes = ($hay_lote && $lote_desde > 0 && $lote_hasta > 0 && $lote_desde !== $lote_hasta);

        /* ---------------- Detalle legible ---------------- */
        $partes    = array();
        $tipo      = 'general';
        $sufijo    = '';
        $animal    = null;
        $lotes     = array();

        if ($cod_individual !== '') {
            $tipo   = 'animal';
            $animal = bovs_reporte_animal($cod_individual, $mfundos_id, ($f['codigo_erete'] === '2'));

            if ($animal) {
                $nombre = trim($animal['nombre']);
                $arete  = trim($animal['nro_arete']);

                /* Si el nombre ya trae el arete (p. ej. "GKM LADY 294-19")
                   no se repite en el identificador ni en el archivo. */
                $nombre_trae_arete = ($nombre !== '' && $arete !== ''
                                      && stripos($nombre, $arete) !== false);

                $ident = array();
                if ($nombre !== '') {
                    $ident[] = $nombre;
                }
                if ($arete !== '' && !$nombre_trae_arete) {
                    $ident[] = 'Arete ' . $arete;
                }
                $ident[] = trim($animal['codigo']);
                $partes[] = implode(' · ', $ident);

                $sufijo = $nombre;
                if ($arete !== '' && !$nombre_trae_arete) {
                    $sufijo .= ($sufijo !== '' ? '_' : '') . 'Arete_' . $arete;
                }
                if ($sufijo === '') {
                    $sufijo = trim($animal['codigo']);
                }
            } else {
                $etiqueta = ($f['codigo_erete'] === '2') ? 'Arete ' : 'Código ';
                $partes[] = $etiqueta . $cod_individual;
                $sufijo   = $cod_individual;
            }
        } elseif ($es_rango_codigos) {
            $tipo     = 'rango';
            $partes[] = 'Códigos ' . $cod_desde . ' al ' . $cod_hasta;
            $sufijo   = $cod_desde . '_al_' . $cod_hasta;
        }

        if ($hay_lote) {
            if ($tipo === 'general') {
                $tipo = 'lote';
            }
            $lotes = bovs_reporte_lotes($lote_desde, $lote_hasta, $mfundos_id);

            if (count($lotes) > 0) {
                $partes[] = (count($lotes) === 1 && !$es_rango_lotes)
                    ? 'Lote: ' . $lotes[0]
                    : 'Lotes: ' . implode(', ', $lotes);
                $sufijo .= ($sufijo !== '' ? '_' : '') .
                           (count($lotes) === 1 ? $lotes[0] : implode('-', $lotes));
            } else {
                $partes[] = $es_rango_lotes
                    ? 'Lotes del ' . $lote_desde . ' al ' . $lote_hasta
                    : 'Lote ' . $lote_desde;
                $sufijo .= ($sufijo !== '' ? '_' : '') .
                           ($es_rango_lotes ? $lote_desde . '_al_' . $lote_hasta : $lote_desde);
            }
        }

        if ($fecha_desde !== '' || $fecha_hasta !== '') {
            if ($tipo === 'general') {
                $tipo = 'rango';
            }

            if ($fecha_desde !== '' && $fecha_hasta !== '') {
                $partes[] = 'Del ' . $fecha_desde . ' al ' . $fecha_hasta;
                $sufijo  .= ($sufijo !== '' ? '_' : '') . $fecha_desde . '_al_' . $fecha_hasta;
            } elseif ($fecha_desde !== '') {
                $partes[] = 'Desde el ' . $fecha_desde;
                $sufijo  .= ($sufijo !== '' ? '_' : '') . 'desde_' . $fecha_desde;
            } else {
                $partes[] = 'Hasta el ' . $fecha_hasta;
                $sufijo  .= ($sufijo !== '' ? '_' : '') . 'hasta_' . $fecha_hasta;
            }
        }

        if ($tipo === 'general') {
            $partes[] = 'Todo el hato';
        }

        return array(
            'tipo'           => $tipo,
            'detalle'        => implode(' · ', $partes),
            'sufijo'         => $sufijo,
            'animal'         => $animal,
            'lotes'          => $lotes,
            'fecha_desde'    => $fecha_desde,
            'fecha_hasta'    => $fecha_hasta,
            'fecha_generado' => date('d-m-Y')
        );
    }
}

if (!function_exists('bovs_reporte_titulo')) {
    /**
     * Título/nomenclatura completa: reporte + detalle + fecha de generado.
     */
    function bovs_reporte_titulo($nombre_reporte, $ctx)
    {
        $partes = array(trim((string) $nombre_reporte));

        if (!empty($ctx['detalle'])) {
            $partes[] = $ctx['detalle'];
        }

        if (!empty($ctx['fecha_generado'])) {
            $partes[] = 'Generado el ' . $ctx['fecha_generado'];
        }

        $titulo = implode(' - ', array_filter($partes, 'strlen'));

        /* FPDF escribe el /Title tal cual: se pasa a ISO-8859-1 para que los
           acentos se vean bien en el visor y en el diálogo de impresión. */
        if (class_exists('FPDF') && function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($titulo, 'ISO-8859-1', 'UTF-8');
        }

        return $titulo;
    }
}

if (!function_exists('bovs_reporte_nombre_archivo')) {
    /**
     * Nombre de archivo del PDF con la misma nomenclatura.
     */
    function bovs_reporte_nombre_archivo($nombre_reporte, $ctx)
    {
        $partes = array(trim((string) $nombre_reporte));

        if (!empty($ctx['sufijo'])) {
            $partes[] = $ctx['sufijo'];
        }

        if (!empty($ctx['fecha_generado'])) {
            $partes[] = $ctx['fecha_generado'];
        }

        $nombre = implode('_', $partes);
        $nombre = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $nombre);
        $nombre = preg_replace('/_+/', '_', $nombre);

        return trim($nombre, '_') . '.pdf';
    }
}
