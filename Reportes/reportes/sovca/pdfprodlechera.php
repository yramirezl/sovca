<?php
/* ============================================================
   BOVS | Reporte de Producción de Leche
   ------------------------------------------------------------
   Compatible con PHP 7.4 y PHP 8.3.

   Estructura del reporte (rev. 2026-10-19):
     1. Resumen general (animales, registros, total y promedio).
     2. Una ficha por ANIMAL (agrupada correctamente por código),
        ordenada por lote y código.
     3. Dentro de cada animal, un bloque por CICLO DE LACTANCIA
        (se separan con los partos de mregnacimientos y el ciclo se
        cierra con la fecha de secado).
     4. La producción de cada ciclo se ordena de la fecha MÁS
        RECIENTE a la más antigua. El acumulado del ciclo se muestra
        una sola vez, en el resumen del ciclo.
     5. Los ciclos se ordenan del más reciente al más antiguo. Si el
        animal no tiene partos registrados, su producción se muestra
        como CICLO DE LACTANCIA N° 1 (parto no registrado).

   Datos: mprolecheras (producción) + manimales (identificación) +
   mregnacimientos (partos / secado para separar los ciclos).
   Se usan solo 2 consultas (sin N+1 por animal).

   Respaldo de la versión anterior: pdfprodlechera_legacy.php
   ============================================================ */

date_default_timezone_set('America/Caracas');

if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime() { return false; }
}
if (!function_exists('set_magic_quotes_runtime')) {
    function set_magic_quotes_runtime($v) { return true; }
}
if (!function_exists('__pdf_txt')) {
    function __pdf_txt($str) {
        if ($str === null || $str === '') return '';
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
        }
        return utf8_decode($str);
    }
}

error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED & ~E_NOTICE);

require_once('../../lib/general/fpdf/fpdf.php');
require_once('../../lib/bd/basedatosAdoIbase.php');
require_once('../../lib/general/cabecera.php');
require_once('../../lib/general/Herramientas.class.php');
include_once('../../lib/utiles.php');
include_once('../../lib/funciones.php');

#[\AllowDynamicProperties]
class pdfreporte extends fpdf
{
    /* Constructor dual: PHP 5.3.3 → 8.3 */
    function __construct()
    {
        $this->pdfreporte();
    }

    var $reg;
    var $p = 0;

    /* Conexión, cabecera y filtros */
    var $bd;
    var $cab;
    var $titulo;
    var $f_desde;
    var $f_hasta;
    var $cod_bovs;
    var $cod_bovs_inicio;
    var $cod_bovs_final;
    var $lote_desde;
    var $lote_hasta;
    var $tiene_filtro_lote;
    var $mfundos_id;
    var $nombre_fundo;
    var $cid_rif_fundo;
    var $arrp_fundo;
    var $lote_nombres;
    var $animales;
    var $resumen;
    var $y2;

    /* Inicio del cuerpo en cada hoja y punto de corte antes del pie */
    var $y_inicio_cuerpo = 40;
    var $limite_y        = 315;

    /*
     * Anchos del detalle de producción (suman 190 mm).
     * La fecha y los títulos van centrados; las columnas de kilos se
     * alinean a la izquierda porque son valores que se suman y comparan.
     */
    var $anchos_detalle = array(40, 50, 50, 50);
    var $alinea_detalle = array('C', 'C', 'C', 'C');

    function pdfreporte()
    {
        $this->fpdf("p", "mm", "legal");
        $this->bd  = new baseClases();
        $this->cab = new Cabecera();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $parametros = base64_decode(getvalue('%$%'));
        $par        = $this->parsear_parametros($parametros);

        $this->f_desde = $this->normalizar_fecha($par['fecha_inicio']);
        $this->f_hasta = $this->normalizar_fecha($par['fecha_final']);

        if ($this->f_desde !== '' && $this->f_hasta !== '' && $this->f_desde > $this->f_hasta) {
            $tmp           = $this->f_desde;
            $this->f_desde = $this->f_hasta;
            $this->f_hasta = $tmp;
        }

        $this->cod_bovs_inicio = trim($par['cod_bovs_inicio']);
        $this->cod_bovs_final  = trim($par['cod_bovs_final']);
        $this->cod_bovs        = trim($par['cod_bovs']);

        /* Rango de lotes: se acepta lote_unico (dashboards antiguos)
           o lote_inicio / lote_final (gestor de reportes actual). */
        $this->lote_desde        = 0;
        $this->lote_hasta        = 0;
        $this->tiene_filtro_lote = false;

        $lote_unico = intval($par['lote_unico']);
        $lote_ini   = intval($par['lote_inicio']);
        $lote_fin   = intval($par['lote_final']);

        if ($lote_unico > 0) {
            $this->lote_desde = $lote_unico;
            $this->lote_hasta = $lote_unico;
            $this->tiene_filtro_lote = true;
        } elseif ($lote_ini > 0 || $lote_fin > 0) {
            $this->lote_desde = $lote_ini > 0 ? $lote_ini : $lote_fin;
            $this->lote_hasta = $lote_fin > 0 ? $lote_fin : $lote_ini;
            if ($this->lote_desde > $this->lote_hasta) {
                $tmp              = $this->lote_desde;
                $this->lote_desde = $this->lote_hasta;
                $this->lote_hasta = $tmp;
            }
            $this->tiene_filtro_lote = true;
        }

        $this->mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

        $this->titulo       = '';
        $this->arrp         = array();
        $this->animales     = array();
        $this->lote_nombres = '';

        $this->resumen = array(
            'animales'        => 0,
            'registros'       => 0,
            'dias'            => 0,
            'ordenos'         => 0,
            'total'           => 0,
            'promedio'        => 0,
            'promedio_dia'    => 0,
            'promedio_ordeno' => 0,
            'primera'         => '',
            'ultima'          => ''
        );

        /* Datos del fundo (encabezado) */
        $this->nombre_fundo  = 'Sin Fundo';
        $this->cid_rif_fundo = '';
        if ($this->mfundos_id > 0) {
            $sql_fundo = "SELECT nombre, cid_rif FROM mfundos WHERE id=" . $this->mfundos_id . ";";
            $this->arrp_fundo = $this->bd->select($sql_fundo);
            if (isset($this->arrp_fundo[0]['nombre'])) {
                $this->nombre_fundo = $this->arrp_fundo[0]['nombre'];
            }
            if (isset($this->arrp_fundo[0]['cid_rif'])) {
                $this->cid_rif_fundo = $this->arrp_fundo[0]['cid_rif'];
            }
        }

        /* Nombre del lote filtrado (se muestra en el resumen general) */
        if ($this->mfundos_id > 0 && $this->tiene_filtro_lote) {
            $sql_lote = "SELECT nombre FROM mlotes
                          WHERE mfundos_id = " . $this->mfundos_id . "
                            AND id BETWEEN " . $this->lote_desde . " AND " . $this->lote_hasta . "
                       ORDER BY nombre;";
            $rs_lote = $this->bd->select($sql_lote);
            $nombres = array();

            if (is_array($rs_lote)) {
                foreach ($rs_lote as $fila_lote) {
                    $nombres[] = $fila_lote['nombre'];
                }
            }

            $this->lote_nombres = count($nombres) > 0
                ? implode(', ', $nombres)
                : ($this->lote_desde == $this->lote_hasta
                    ? 'Lote ' . $this->lote_desde
                    : 'Lotes del ' . $this->lote_desde . ' al ' . $this->lote_hasta);
        }

        if ($this->mfundos_id <= 0) {
            $this->reg = 0;
            return;
        }

        $this->cargar_datos();
        $this->arrp = $this->animales;
        $this->reg  = 0;
    }

    /* ============================================================
       PARSEO DE PARÁMETROS
       ============================================================ */

    function parsear_parametros($parametros)
    {
        $out = array(
            'cod_bovs_inicio' => '',
            'cod_bovs_final'  => '',
            'cod_bovs'        => '',
            'fecha_inicio'    => '',
            'fecha_final'     => '',
            'lote_unico'      => '',
            'lote_inicio'     => '',
            'lote_final'      => ''
        );

        if (!is_string($parametros) || $parametros === '') {
            return $out;
        }

        $valores = explode('&', $parametros);
        foreach ($valores as $cadena) {
            if ($cadena === '' || strpos($cadena, '=') === false) continue;
            $partes = explode('=', $cadena, 2);
            $clave  = trim($partes[0]);
            $valor  = isset($partes[1]) ? urldecode($partes[1]) : '';
            if (array_key_exists($clave, $out)) {
                $out[$clave] = $valor;
            }
        }

        return $out;
    }

    function normalizar_fecha($valor)
    {
        $valor = trim((string)$valor);
        if ($valor === '') return '';

        $formatos = array('Y-m-d', 'd-m-Y', 'd/m/Y');
        foreach ($formatos as $formato) {
            $d = DateTime::createFromFormat($formato, $valor);
            if ($d instanceof DateTime) {
                $errores = DateTime::getLastErrors();
                if (is_array($errores) && ($errores['warning_count'] > 0 || $errores['error_count'] > 0)) {
                    continue;
                }
                return $d->format('Y-m-d');
            }
        }

        return '';
    }

    /**
     * Texto del filtro por rango de códigos. Solo aplica cuando el
     * usuario eligió un rango (más de un animal); en ese caso el
     * resumen general lo muestra como dato de cabecera.
     */
    function texto_rango_animales()
    {
        $cod_ini = $this->cod_bovs_inicio;
        $cod_fin = $this->cod_bovs_final;

        if ($cod_ini === '' && $cod_fin === '') return '';
        if ($cod_ini === '') $cod_ini = $cod_fin;
        if ($cod_fin === '') $cod_fin = $cod_ini;
        if ($cod_ini === $cod_fin) return '';

        return 'Del ' . $cod_ini . ' al ' . $cod_fin;
    }

    /* ============================================================
       CARGA DE DATOS
       ============================================================ */

    /**
     * ¿El reporte se pidió para UN solo animal (por su código)?
     * En ese caso no se filtra por estado del animal: si el usuario
     * pide explícitamente su código, se muestra todo su historial
     * (el botón "Resumen" de /views/prodleche/ lo usa así).
     */
    function es_animal_unico()
    {
        if ($this->cod_bovs !== '') return true;

        if ($this->cod_bovs_inicio !== '' && $this->cod_bovs_final !== '') {
            return $this->cod_bovs_inicio === $this->cod_bovs_final;
        }

        return false;
    }

    function cargar_datos()
    {
        $cond = array();
        $cond[] = 'p.status = 1';
        $cond[] = 'p.mfundos_id = ' . $this->mfundos_id;
        $cond[] = 'a.mfundos_id = ' . $this->mfundos_id;

        /* En reportes generales solo animales vigentes; con un código
           explícito se incluye aunque esté de baja, vendido o migrado. */
        if (!$this->es_animal_unico()) {
            $cond[] = 'a.mestatus_id = 1';
            $cond[] = 'COALESCE(a.estatus_animal, 1) NOT IN (3, 5)';
        }
        if ($this->f_desde !== '' && $this->f_hasta !== '') {
            $cond[] = "p.fecha_produccion BETWEEN CAST('" . $this->f_desde . "' AS DATE)"
                    . " AND CAST('" . $this->f_hasta . "' AS DATE)";
        }

        /* Rango de códigos: se traduce a rango de id de animal
           (mismo criterio de la versión anterior del reporte). */
        $filtro_codigos = $this->sql_filtro_codigos();
        if ($filtro_codigos !== '') {
            $cond[] = $filtro_codigos;
        }

        if ($this->tiene_filtro_lote) {
            $cond[] = 'a.mlotes_id BETWEEN ' . $this->lote_desde . ' AND ' . $this->lote_hasta;
        }

        /*
         * Orden de los animales:
         *  - Si el usuario eligió un rango de códigos, se respeta el orden
         *    del rango (por id de animal), para que el primero sea el
         *    código inicial y el último el código final.
         *  - Si no hay rango de códigos, se agrupa por lote y luego por
         *    animal (orden natural del hato).
         */
        $por_rango_codigos = ($this->cod_bovs_inicio !== '' || $this->cod_bovs_final !== '' || $this->cod_bovs !== '');
        $orden_lote        = $por_rango_codigos ? '' : "COALESCE(l.nombre, ''), ";

        $sql = "SELECT a.id                     AS id_animal,
                       a.codigo_rgb_id_animal AS codigo_animal,
                       a.nombre               AS nombre_animal,
                       a.nro_arete            AS nro_arete,
                       a.mlotes_id            AS id_lote,
                       COALESCE(l.nombre, '') AS nombre_lote,
                       p.fecha_produccion,
                       p.cantidad_leche_m,
                       p.cantidad_leche_t
                  FROM mprolecheras p
            INNER JOIN manimales a
                    ON a.codigo_rgb_id_animal = p.codigo_rgb_id_animal_hembra
                   AND a.mfundos_id = p.mfundos_id
             LEFT JOIN mlotes l ON l.id = a.mlotes_id
                 WHERE " . implode(' AND ', $cond) . "
              ORDER BY " . $orden_lote . "a.id, p.fecha_produccion DESC, p.id DESC;";

        $registros = $this->bd->select($sql);

        if (!is_array($registros) || count($registros) === 0) {
            return;
        }

        /* Agrupa la producción por animal */
        $por_animal = array();
        $codigos    = array();

        foreach ($registros as $row) {
            $codigo = isset($row['codigo_animal']) ? $row['codigo_animal'] : '';
            if ($codigo === '') continue;

            if (!isset($por_animal[$codigo])) {
                $por_animal[$codigo] = array(
                    'id_animal' => intval($row['id_animal']),
                    'codigo'    => $codigo,
                    'nombre'    => isset($row['nombre_animal']) ? $row['nombre_animal'] : '',
                    'nro_arete' => isset($row['nro_arete']) ? $row['nro_arete'] : '',
                    'lote'      => isset($row['nombre_lote']) ? $row['nombre_lote'] : '',
                    'filas'     => array()
                );
                $codigos[$codigo] = $codigo;
            }

            $m = floatval($row['cantidad_leche_m']);
            $t = floatval($row['cantidad_leche_t']);

            $por_animal[$codigo]['filas'][] = array(
                'fecha' => substr($row['fecha_produccion'], 0, 10),
                'm'     => $m,
                't'     => $t,
                'total' => $m + $t
            );
        }

        if (count($por_animal) === 0) {
            return;
        }

        $partos = $this->cargar_partos(array_values($codigos));

        foreach ($por_animal as $codigo => $animal) {
            $ciclos = $this->construir_ciclos(
                $animal['filas'],
                isset($partos[$codigo]) ? $partos[$codigo] : array()
            );

            if (count($ciclos) === 0) {
                continue;
            }

            $registros_animal = 0;
            $dias_animal      = 0;
            $ordenos_animal   = 0;
            $total_animal     = 0;
            $primera_animal   = '';
            $ultima_animal    = '';

            foreach ($ciclos as $c) {
                $registros_animal += $c['registros'];
                $dias_animal      += $c['dias'];
                $ordenos_animal   += $c['ordenos'];
                $total_animal     += $c['total'];
                if ($primera_animal === '' || $c['primera'] < $primera_animal) {
                    $primera_animal = $c['primera'];
                }
                if ($ultima_animal === '' || $c['ultima'] > $ultima_animal) {
                    $ultima_animal = $c['ultima'];
                }
            }

            $animal['ciclos']          = $ciclos;
            $animal['registros']       = $registros_animal;
            $animal['dias']            = $dias_animal;
            $animal['ordenos']         = $ordenos_animal;
            $animal['total']           = $total_animal;
            $animal['promedio']        = $registros_animal > 0 ? $total_animal / $registros_animal : 0;
            $animal['promedio_dia']    = $dias_animal > 0 ? $total_animal / $dias_animal : 0;
            $animal['promedio_ordeno'] = $ordenos_animal > 0
                ? $total_animal / $ordenos_animal
                : $animal['promedio_dia'];
            $animal['primera']         = $primera_animal;
            $animal['ultima']          = $ultima_animal;
            $animal['n_ciclos']        = count($ciclos);

            $this->animales[] = $animal;

            $this->resumen['animales']++;
            $this->resumen['registros'] += $registros_animal;
            $this->resumen['dias']      += $dias_animal;
            $this->resumen['ordenos']   += $ordenos_animal;
            $this->resumen['total']     += $total_animal;
            if ($this->resumen['primera'] === '' || $primera_animal < $this->resumen['primera']) {
                $this->resumen['primera'] = $primera_animal;
            }
            if ($this->resumen['ultima'] === '' || $ultima_animal > $this->resumen['ultima']) {
                $this->resumen['ultima'] = $ultima_animal;
            }
        }

        if ($this->resumen['registros'] > 0) {
            $this->resumen['promedio'] = $this->resumen['total'] / $this->resumen['registros'];
        }
        if ($this->resumen['dias'] > 0) {
            $this->resumen['promedio_dia'] = $this->resumen['total'] / $this->resumen['dias'];
        }
        if ($this->resumen['ordenos'] > 0) {
            $this->resumen['promedio_ordeno'] = $this->resumen['total'] / $this->resumen['ordenos'];
        } else {
            $this->resumen['promedio_ordeno'] = $this->resumen['promedio_dia'];
        }
    }

    function sql_filtro_codigos()
    {
        $cod_ini = $this->cod_bovs_inicio;
        $cod_fin = $this->cod_bovs_final;

        /* Compatibilidad: un solo código */
        if ($cod_ini === '' && $cod_fin === '' && $this->cod_bovs !== '') {
            $cod_ini = $this->cod_bovs;
            $cod_fin = $this->cod_bovs;
        }
        if ($cod_ini === '' && $cod_fin === '') {
            return '';
        }
        if ($cod_ini === '') $cod_ini = $cod_fin;
        if ($cod_fin === '') $cod_fin = $cod_ini;

        $id_desde = $this->id_de_animal($cod_ini);
        $id_hasta = $this->id_de_animal($cod_fin);

        if ($id_desde > 0 && $id_hasta > 0) {
            if ($id_desde > $id_hasta) {
                $tmp      = $id_desde;
                $id_desde = $id_hasta;
                $id_hasta = $tmp;
            }
            return 'a.id BETWEEN ' . $id_desde . ' AND ' . $id_hasta;
        }
        if ($id_desde > 0) return 'a.id = ' . $id_desde;
        if ($id_hasta > 0) return 'a.id = ' . $id_hasta;

        return '';
    }

    function id_de_animal($codigo)
    {
        $codigo = trim((string)$codigo);
        if ($codigo === '') return 0;

        $codigo = str_replace("'", "''", $codigo);
        $sql    = "SELECT id FROM manimales
                    WHERE mfundos_id = " . $this->mfundos_id . "
                      AND codigo_rgb_id_animal = '" . $codigo . "'
                    LIMIT 1;";
        $rs = $this->bd->select($sql);

        return (is_array($rs) && isset($rs[0]['id'])) ? intval($rs[0]['id']) : 0;
    }

    /**
     * Partos (ciclos de lactancia) de los animales del reporte.
     * Se traen TODOS los partos del animal (no solo los del rango de
     * fechas), porque un parto anterior define el ciclo de la
     * producción que sí cae dentro del rango.
     */
    function cargar_partos($codigos)
    {
        $salida = array();
        if (!is_array($codigos) || count($codigos) === 0) {
            return $salida;
        }

        $lista = array();
        foreach ($codigos as $codigo) {
            $lista[] = "'" . str_replace("'", "''", $codigo) . "'";
        }

        $sql = "SELECT codigo_rgb_id_animal_hembra AS codigo_animal,
                       id,
                       f_parto,
                       nro_parto,
                       f_secado
                  FROM mregnacimientos
                 WHERE mfundos_id = " . $this->mfundos_id . "
                   AND status = 1
                   AND f_parto IS NOT NULL
                   AND codigo_rgb_id_animal_hembra IN (" . implode(',', $lista) . ")
              ORDER BY codigo_rgb_id_animal_hembra, f_parto ASC, id ASC;";

        $rs = $this->bd->select($sql);
        if (!is_array($rs)) {
            return $salida;
        }

        foreach ($rs as $row) {
            $codigo = $row['codigo_animal'];
            if (!isset($salida[$codigo])) {
                $salida[$codigo] = array();
            }
            $salida[$codigo][] = array(
                'id'        => intval($row['id']),
                'f_parto'   => substr($row['f_parto'], 0, 10),
                'nro_parto' => isset($row['nro_parto']) ? intval($row['nro_parto']) : 0,
                'f_secado'  => (isset($row['f_secado']) && $row['f_secado'] !== '' && $row['f_secado'] !== null)
                                ? substr($row['f_secado'], 0, 10) : ''
            );
        }

        return $salida;
    }

    /* ============================================================
       CICLOS DE LACTANCIA
       ============================================================ */

    /**
     * Reparte las producciones entre los partos: cada registro
     * pertenece al último parto ocurrido en o antes de su fecha.
     *
     * - Sin partos registrados: toda la producción forma el CICLO 1,
     *   porque se considera que el animal ya tuvo al menos un ciclo de
     *   producción de leche en el fundo (aunque no esté el parto).
     * - Con partos: lo anterior al primer parto forma un ciclo
     *   "anterior" con parto no registrado, de modo que el número de
     *   bloques coincida siempre con "Ciclos de lactancia".
     */
    function construir_ciclos($filas, $partos)
    {
        $total_partos = count($partos);

        /* Sin partos: un único ciclo N° 1 (parto no registrado) */
        if ($total_partos === 0) {
            return array($this->calcular_ciclo(array(
                'sin_ciclo'           => false,
                'parto_no_registrado' => true,
                'nro_parto'           => 1,
                'f_parto'             => '',
                'f_secado'            => '',
                'filas'               => $filas
            )));
        }

        $ciclos = array();

        for ($i = 0; $i < $total_partos; $i++) {
            $ciclos[$i] = array(
                'sin_ciclo'           => false,
                'parto_no_registrado' => false,
                'nro_parto'           => $partos[$i]['nro_parto'],
                'f_parto'             => $partos[$i]['f_parto'],
                'f_secado'            => $partos[$i]['f_secado'],
                'filas'               => array()
            );
        }

        $huerfanas = array();

        foreach ($filas as $fila) {
            $asignado = -1;
            for ($i = 0; $i < $total_partos; $i++) {
                if (strcmp($partos[$i]['f_parto'], $fila['fecha']) <= 0) {
                    $asignado = $i;
                } else {
                    break;
                }
            }

            if ($asignado >= 0) {
                $ciclos[$asignado]['filas'][] = $fila;
            } else {
                $huerfanas[] = $fila;
            }
        }

        $salida = array();

        foreach ($ciclos as $ciclo) {
            if (count($ciclo['filas']) === 0) continue;
            $salida[] = $this->calcular_ciclo($ciclo);
        }

        if (count($huerfanas) > 0) {
            $salida[] = $this->calcular_ciclo(array(
                'sin_ciclo'           => true,
                'parto_no_registrado' => true,
                'nro_parto'           => 0,
                'f_parto'             => '',
                'f_secado'            => '',
                'filas'               => $huerfanas
            ));
        }

        /* Más reciente primero; el ciclo anterior (sin parto) al final */
        usort($salida, function ($a, $b) {
            $fa = $a['f_parto'];
            $fb = $b['f_parto'];
            if ($fa === $fb) return 0;
            if ($fa === '') return 1;
            if ($fb === '') return -1;
            return strcmp($fb, $fa);
        });

        return $salida;
    }

    function calcular_ciclo($ciclo)
    {
        $filas = $ciclo['filas'];

        /* Orden cronológico ascendente para ubicar primera y última */
        usort($filas, function ($a, $b) {
            return strcmp($a['fecha'], $b['fecha']);
        });

        $suma_m  = 0;
        $suma_t  = 0;
        $total   = 0;
        $maximo  = null;
        $minimo  = null;
        $primera = '';
        $ultima  = '';
        $dias    = array();
        $ordenos = 0;

        foreach ($filas as $fila) {
            $suma_m += $fila['m'];
            $suma_t += $fila['t'];
            $total  += $fila['total'];

            /* Ordeños realmente registrados (mañana / tarde con leche) */
            if ($fila['m'] > 0) $ordenos++;
            if ($fila['t'] > 0) $ordenos++;

            if ($maximo === null || $fila['total'] > $maximo) $maximo = $fila['total'];
            if ($minimo === null || $fila['total'] < $minimo) $minimo = $fila['total'];

            if ($primera === '') $primera = $fila['fecha'];
            $ultima = $fila['fecha'];
            $dias[$fila['fecha']] = 1;
        }

        /* Se muestran de la más reciente a la más antigua */
        $filas = array_reverse($filas);

        $registros = count($filas);
        $n_dias    = count($dias);
        $duracion  = 0;
        if ($ciclo['f_parto'] !== '') {
            $fin      = $ciclo['f_secado'] !== '' ? $ciclo['f_secado'] : $ultima;
            $duracion = $this->dias_entre($ciclo['f_parto'], $fin) + 1;
        }

        return array(
            'sin_ciclo'           => $ciclo['sin_ciclo'],
            'parto_no_registrado' => $ciclo['parto_no_registrado'],
            'nro_parto'           => $ciclo['nro_parto'],
            'f_parto'             => $ciclo['f_parto'],
            'f_secado'            => $ciclo['f_secado'],
            'filas'               => $filas,
            'registros'           => $registros,
            'dias'                => $n_dias,
            'ordenos'             => $ordenos,
            'suma_m'              => $suma_m,
            'suma_t'              => $suma_t,
            'total'               => $total,
            'promedio'            => $registros > 0 ? $total / $registros : 0,
            'promedio_dia'        => $n_dias > 0 ? $total / $n_dias : 0,
            'promedio_ordeno'     => $ordenos > 0
                ? $total / $ordenos
                : ($n_dias > 0 ? $total / $n_dias : 0),
            'maximo'              => $maximo === null ? 0 : $maximo,
            'minimo'              => $minimo === null ? 0 : $minimo,
            'primera'             => $primera,
            'ultima'              => $ultima,
            'duracion'            => $duracion
        );
    }

    /* ============================================================
       ENCABEZADO / PIE
       ============================================================ */

    function getEncabezado($x, $y)
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont("ARIAL", "B", 10);
        $this->SetXY($x + 0, $y - 20);
        $this->SetWidths(array(190));
        $this->SetAligns(array('C'));
        $this->SetBorder(1);
        $this->SetJump(12);
        $this->RowM(array(''));

        $this->SetXY($x + 60, $y - 15);
        $this->SetWidths(array(90, 40));
        $this->SetAligns(array('C', 'R'));

        $fecha = date("d/m/Y");

        $this->RowM(array($this->nombre_fundo . '  ' . $this->cid_rif_fundo, 'Fecha: ' . $fecha));

        $this->SetXY($x + 20, $y - 5);
        $this->SetFont("ARIAL", "B", 10);
        $this->SetTextColor(0, 0, 0);
        $this->SetJump(5);
        $this->SetWidths(array(170));
        $this->SetAligns(array('C'));

        $this->RowM(array('REPORTE DE PRODUCCIÓN DE LECHE'));
    }

    function Footer()
    {
        $this->y2 = 257;
        $this->SetFillColor(230, 230, 230);
        $this->SetFont("ARIAL", "B", "8");
    }

    function Pie_Pagina()
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetTextColor(0, 0, 100);
        $this->SetFont("ARIAL", "B", 7);
        $this->SetXY(10, 330);
        $this->SetWidths(array(190));
        $this->SetAligns(array('R'));
        $this->SetBorder(false);
        $this->SetJump(5);
        $this->RowM(array('Sistema SOVCA  -  Página ' . $this->PageNo()));
        $this->SetTextColor(0, 0, 0);
    }

    function Header()
    {
        $this->cab->poner_cabecera($this, $this->titulo, "p", "", "n");
        $y = $this->GetY();
        $x = $this->GetX();

        $this->getEncabezado($x, $y);
        $this->SetWidths(array(60));
        $this->SetAligns(array('L'));
        $this->SetXY(10, 23);
    }

    /* ============================================================
       CUERPO
       ============================================================ */

    function Cuerpo()
    {
        $this->Pie_Pagina();
        $this->SetXY(10, $this->y_inicio_cuerpo);
        $this->SetTextColor(0, 0, 0);

        if (count($this->animales) === 0) {
            $this->SetFont("ARIAL", "B", 9);
            $this->SetWidths(array(190));
            $this->SetAligns(array('C'));
            $this->SetBorder(true);
            $this->SetJump(8);
            $this->RowM(array('No se encontraron registros de producción de leche con los filtros seleccionados.'));
            return;
        }

        /*
         * Con un solo animal el resumen general sobra: la ficha del
         * animal ya muestra esa información. Solo se dibuja cuando el
         * reporte incluye varios animales.
         */
        if (count($this->animales) > 1) {
            $this->bloqueResumenGeneral();
        }

        foreach ($this->animales as $animal) {
            /*
             * No se deja la cabecera de un animal sola al pie de una
             * página: si no cabe también el inicio de su ciclo de
             * lactancia, el animal completo pasa a la página siguiente.
             */
            if (!$this->cabeEnPagina($this->altoAnimal($animal))) {
                $this->nuevaPagina();
            }
            $this->bloqueAnimal($animal);
        }
    }

    function cabeEnPagina($alto)
    {
        return ($this->GetY() + $alto) <= $this->limite_y;
    }

    function nuevaPagina($animal = null)
    {
        $this->AddPage();
        $this->Pie_Pagina();
        $this->SetXY(10, $this->y_inicio_cuerpo);

        if ($animal !== null) {
            $this->barraContinuacion($animal);
        }
    }

    /** Alto mínimo del bloque de un ciclo (barra + resumen + cabecera + filas). */
    function altoCiclo($ciclo)
    {
        $filas = count($ciclo['filas']);
        if ($filas > 3) {
            $filas = 3;
        }

        return 25 + ($filas * 5);
    }

    /** Alto mínimo del bloque del animal (cabecera + inicio de su primer ciclo). */
    function altoAnimal($animal)
    {
        $alto = 30;

        if (isset($animal['ciclos'][0])) {
            $alto += $this->altoCiclo($animal['ciclos'][0]);
        }

        return $alto;
    }

    function bloqueResumenGeneral()
    {
        $pares = array();
        $pares[] = array('Animales con producción', $this->fmt_num($this->resumen['animales'], 0));
        $pares[] = array('Días con producción', $this->fmt_num($this->resumen['dias'], 0));
        $pares[] = array('Producción total (Kg)', $this->fmt_num($this->resumen['total']));
        $pares[] = array('Promedio por día (Kg)', $this->fmt_num($this->resumen['promedio_dia']));
        $pares[] = array('Promedio por ordeño (Kg)', $this->fmt_num($this->resumen['promedio_ordeno']));
        $pares[] = array('Primera producción', $this->fmt_fecha_dmy($this->resumen['primera']));
        $pares[] = array('Última producción', $this->fmt_fecha_dmy($this->resumen['ultima']));

        /* Solo se incluyen los filtros que realmente se aplicaron */
        if ($this->f_desde !== '' && $this->f_hasta !== '') {
            $pares[] = array(
                'Período del reporte',
                $this->fmt_fecha_dmy($this->f_desde) . ' al ' . $this->fmt_fecha_dmy($this->f_hasta)
            );
        }

        if ($this->lote_nombres !== '') {
            $pares[] = array('Lote', $this->lote_nombres);
        }

        $rango_animales = $this->texto_rango_animales();
        if ($rango_animales !== '') {
            $pares[] = array('Animales seleccionados', $rango_animales);
        }

        /*
         * Se elige el número de columnas para que la última fila no quede
         * con una sola celda ocupando todo el ancho.
         */
        $n_pares  = count($pares);
        $columnas = 4;
        if ($n_pares % 4 === 1) {
            $columnas = 3;
        } elseif ($n_pares > 4 && $n_pares % 3 === 0) {
            $columnas = 3;
        }

        $grupos  = array_chunk($pares, $columnas);
        $primera = true;

        foreach ($grupos as $grupo) {
            $fondo = $primera ? array(200, 250, 216) : array(240, 240, 240);
            $this->filaPares($grupo, $fondo, $primera);
            $primera = false;
        }

        $this->Ln(2);
    }

    function bloqueAnimal($animal)
    {
        /* Barra del animal */
        $this->SetFillColor(38, 96, 62);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont("ARIAL", "B", 9);
        $this->SetWidths(array(190));
        $this->SetAligns(array('C'));
        $this->SetBorder(true);
        $this->SetJump(7);
        $this->RowMFillColor(array(
            'ANIMAL: ' . $animal['codigo']
            . ($animal['nombre'] !== '' ? '  -  ' . $animal['nombre'] : '')
        ), true);
        $this->SetTextColor(0, 0, 0);

        $arete = $animal['nro_arete'] !== '' ? $animal['nro_arete'] : '-';
        $lote  = $animal['lote'] !== '' ? $animal['lote'] : 'Sin lote';

        /* Fila 1: identificación (el lote ajusta su ancho al nombre) */
        $this->filaPares(array(
            array('N° de arete', $arete),
            array('Lote', $lote),
            array('Días con producción', $this->fmt_num($animal['dias'], 0)),
            array('Ciclos de lactancia', $this->fmt_num($animal['n_ciclos'], 0))
        ), array(235, 235, 235), false);

        /* Fila 2: totales (el período ajusta su ancho al rango de fechas) */
        $this->filaPares(array(
            array('Producción total (Kg)', $this->fmt_num($animal['total'])),
            array('Promedio por día (Kg)', $this->fmt_num($animal['promedio_dia'])),
            array('Promedio por ordeño (Kg)', $this->fmt_num($animal['promedio_ordeno'])),
            array(
                'Período de producción',
                $this->fmt_fecha_dmy($animal['primera']) . ' al ' . $this->fmt_fecha_dmy($animal['ultima'])
            )
        ), array(235, 235, 235), true);

        foreach ($animal['ciclos'] as $ciclo) {
            /* El ciclo completo (cabecera + primeras filas) debe caber junto. */
            if (!$this->cabeEnPagina($this->altoCiclo($ciclo))) {
                $this->nuevaPagina($animal);
            }
            $this->bloqueCiclo($animal, $ciclo);
        }

        $this->Ln(7);
    }

    function bloqueCiclo($animal, $ciclo)
    {
        $this->barraCiclo($ciclo);
        $this->resumenCiclo($ciclo);
        $this->tablaCabeceraDetalle();
        $this->filasDetalle($animal, $ciclo);
        $this->filaTotalCiclo($ciclo);
        $this->Ln(3);
    }

    function barraCiclo($ciclo)
    {
        if ($ciclo['sin_ciclo']) {
            $texto = 'CICLO DE LACTANCIA ANTERIOR  -  Parto no registrado en el sistema';
        } else {
            $texto = 'CICLO DE LACTANCIA N° ' . ($ciclo['nro_parto'] > 0 ? $ciclo['nro_parto'] : 1);

            if ($ciclo['parto_no_registrado']) {
                $texto .= '  -  Parto no registrado en el sistema';
            } else {
                $texto .= '  -  Parto: ' . $this->fmt_fecha_dmy($ciclo['f_parto']);
                $texto .= '  -  Secado: ' . ($ciclo['f_secado'] !== ''
                            ? $this->fmt_fecha_dmy($ciclo['f_secado'])
                            : 'en ordeño (sin secado)');
                if ($ciclo['duracion'] > 0) {
                    $texto .= '  -  Duración: ' . $this->fmt_num($ciclo['duracion'], 0) . ' días';
                }
            }
        }

        $texto .= '  -  Producción: ' . $this->fmt_fecha_dmy($ciclo['primera'])
                . ' al ' . $this->fmt_fecha_dmy($ciclo['ultima']);

        $this->SetFillColor(180, 214, 189);
        $this->SetTextColor(20, 60, 35);
        $this->SetFont("ARIAL", "B", 8);
        $this->SetWidths(array(190));
        $this->SetAligns(array('C'));
        $this->SetBorder(true);
        $this->SetJump(5);
        $this->RowMFillColor(array($texto), true);
        $this->SetTextColor(0, 0, 0);
    }

    function barraContinuacion($animal)
    {
        $this->SetFillColor(38, 96, 62);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont("ARIAL", "B", 8);
        $this->SetWidths(array(190));
        $this->SetAligns(array('C'));
        $this->SetBorder(true);
        $this->SetJump(6);
        $this->RowMFillColor(array(
            'CONTINÚA ANIMAL: ' . $animal['codigo']
            . ($animal['nombre'] !== '' ? '  -  ' . $animal['nombre'] : '')
        ), true);
        $this->SetTextColor(0, 0, 0);
    }

    function resumenCiclo($ciclo)
    {
        $this->filaPares(array(
            array('Días con producción', $this->fmt_num($ciclo['dias'], 0)),
            array('Acumulado del ciclo (Kg)', $this->fmt_num($ciclo['total'])),
            array('Promedio por día (Kg)', $this->fmt_num($ciclo['promedio_dia'])),
            array('Promedio por ordeño (Kg)', $this->fmt_num($ciclo['promedio_ordeno'])),
            array(
                'Mayor / Menor (Kg)',
                $this->fmt_num($ciclo['maximo']) . '  /  ' . $this->fmt_num($ciclo['minimo'])
            )
        ), array(240, 240, 240), false);
    }

    function tablaCabeceraDetalle()
    {
        $this->SetFillColor(100, 200, 116);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont("ARIAL", "B", 8);
        $this->SetWidths($this->anchos_detalle);
        $this->SetAligns($this->alinea_detalle);
        $this->SetBorder(true);
        $this->SetJump(5);
        $this->RowMFillColor(array(
            'Fecha de producción',
            'Kg (Mañana)',
            'Kg (Tarde)',
            'Kg TOTAL'
        ), true);
    }

    function filasDetalle($animal, $ciclo)
    {
        $maximo   = $ciclo['maximo'];
        $minimo   = $ciclo['minimo'];
        $promedio = $ciclo['promedio_dia'];

        foreach ($ciclo['filas'] as $fila) {
            if ($this->GetY() > $this->limite_y) {
                $this->AddPage();
                $this->Pie_Pagina();
                $this->SetXY(10, $this->y_inicio_cuerpo);
                $this->barraContinuacion($animal);
                $this->barraCiclo($ciclo);
                $this->tablaCabeceraDetalle();
            }

            /*
             * Colores por día:
             *   - Mayor del ciclo: azul fuerte (negrita + fondo celeste).
             *   - Menor del ciclo: rojo fuerte (negrita + fondo rosado).
             *   - Sobre el promedio: azul.
             *   - Bajo el promedio: rojo.
             */
            $color = array(0, 0, 0);
            $fondo = array(255, 255, 255);
            $letra = '';

            if ($maximo != $minimo && abs($fila['total'] - $maximo) < 0.005) {
                $color = array(0, 60, 180);
                $fondo = array(219, 234, 254);
                $letra = 'B';
            } elseif ($maximo != $minimo && abs($fila['total'] - $minimo) < 0.005) {
                $color = array(170, 0, 0);
                $fondo = array(254, 226, 226);
                $letra = 'B';
            } elseif ($fila['total'] > $promedio + 0.005) {
                $color = array(0, 82, 204);
            } elseif ($fila['total'] < $promedio - 0.005) {
                $color = array(190, 30, 30);
            }

            $this->SetFillColor($fondo[0], $fondo[1], $fondo[2]);
            $this->SetTextColor($color[0], $color[1], $color[2]);
            $this->SetFont("ARIAL", $letra, 8);
            $this->SetWidths($this->anchos_detalle);
            $this->SetAligns($this->alinea_detalle);
            $this->SetBorder(true);
            $this->SetJump(5);
            $this->RowMFillColor(array(
                $this->fmt_fecha_dmy($fila['fecha']),
                $this->fmt_num($fila['m']),
                $this->fmt_num($fila['t']),
                $this->fmt_num($fila['total'])
            ), true);
        }

        $this->SetTextColor(0, 0, 0);
    }

    function filaTotalCiclo($ciclo)
    {
        $this->SetFillColor(222, 238, 225);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont("ARIAL", "B", 8);
        $this->SetWidths($this->anchos_detalle);
        $this->SetAligns($this->alinea_detalle);
        $this->SetBorder(true);
        $this->SetJump(5);
        $this->RowMFillColor(array(
            'TOTAL DEL CICLO',
            $this->fmt_num($ciclo['suma_m']),
            $this->fmt_num($ciclo['suma_t']),
            $this->fmt_num($ciclo['total'])
        ), true);
    }

    /* ============================================================
       HELPERS DE FORMATO Y RENDER
       ============================================================ */

    /**
     * Dibuja un bloque etiqueta/valor calculando el ancho de cada celda
     * según su contenido: las celdas con datos cortos quedan angostas y
     * las que tienen textos largos (nombre del lote, rango de códigos,
     * período de producción) se ensanchan para no partirse en dos líneas.
     */
    function filaPares($pares, $fondo, $negrita)
    {
        $pares = array_values($pares);
        if (count($pares) === 0) return;

        $etiquetas = array();
        $valores   = array();

        foreach ($pares as $par) {
            $etiquetas[] = $par[0];
            $valores[]   = $par[1];
        }

        $anchos = $this->calcular_anchos_celdas($pares);

        $this->filaEtiquetas($etiquetas, $fondo, $anchos);
        $this->filaValores($valores, $fondo, $negrita, $anchos);
    }

    /**
     * Anchos (en mm) proporcionales al contenido de cada par.
     *  1. Se mide la etiqueta (7 pt) y el valor (8.5 pt) de cada celda.
     *  2. Si el espacio alcanza, cada celda recibe lo que necesita y el
     *     sobrante se reparte por igual.
     *  3. Si no alcanza, se reduce proporcionalmente.
     * La suma siempre es 190 mm (ancho útil de la página).
     */
    function calcular_anchos_celdas($pares)
    {
        $n = count($pares);
        if ($n === 0) return array();

        $necesarios = array();
        $suma       = 0;

        foreach ($pares as $i => $par) {
            $etiqueta = $this->_pdfNormalizeText((string)$par[0]);
            $valor    = $this->_pdfNormalizeText((string)$par[1]);

            $this->SetFont("ARIAL", "B", 7);
            $w_etiqueta = $this->GetStringWidth($etiqueta);

            $this->SetFont("ARIAL", "B", 8.5);
            $w_valor = $this->GetStringWidth($valor);

            /* Margen interno de la celda + mínimo legible */
            $necesario = max($w_etiqueta, $w_valor) + 9;
            if ($necesario < 22) $necesario = 22;

            $necesarios[$i] = $necesario;
            $suma          += $necesario;
        }

        $anchos = array();
        $acumulado = 0;

        foreach ($necesarios as $i => $necesario) {
            $ancho = round(190 * $necesario / $suma, 2);
            $anchos[$i] = $ancho;
            $acumulado += $ancho;
        }

        /* La última celda absorbe el redondeo para sumar 190 mm exactos */
        $ultima = $n - 1;
        $anchos[$ultima] = round($anchos[$ultima] + (190 - $acumulado), 2);

        return $anchos;
    }

    function filaEtiquetas($etiquetas, $fondo, $anchos = null)
    {
        $etiquetas = array_values($etiquetas);
        $n         = count($etiquetas);
        if ($n === 0) return;

        if (!is_array($anchos) || count($anchos) !== $n) {
            $anchos = array();
            for ($i = 0; $i < $n; $i++) {
                $anchos[] = 190 / $n;
            }
        }

        $alinea = array();
        for ($i = 0; $i < $n; $i++) {
            $alinea[] = 'C';
        }

        $this->SetFillColor($fondo[0], $fondo[1], $fondo[2]);
        $this->SetTextColor(70, 70, 70);
        $this->SetFont("ARIAL", "B", 7);
        $this->SetWidths($anchos);
        $this->SetAligns($alinea);
        $this->SetBorder(true);
        $this->SetJump(4.5);
        $this->RowMFillColor($etiquetas, true);
        $this->SetTextColor(0, 0, 0);
    }

    function filaValores($valores, $fondo, $negrita, $anchos = null)
    {
        $valores = array_values($valores);
        $n       = count($valores);
        if ($n === 0) return;

        if (!is_array($anchos) || count($anchos) !== $n) {
            $anchos = array();
            for ($i = 0; $i < $n; $i++) {
                $anchos[] = 190 / $n;
            }
        }

        $alinea = array();
        for ($i = 0; $i < $n; $i++) {
            $alinea[] = 'C';
        }

        $this->SetFillColor($fondo[0], $fondo[1], $fondo[2]);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont("ARIAL", $negrita ? "B" : "", 8.5);
        $this->SetWidths($anchos);
        $this->SetAligns($alinea);
        $this->SetBorder(true);
        $this->SetJump(5.5);
        $this->RowMFillColor($valores, true);
    }

    function fmt_num($valor, $dec = 2)
    {
        return number_format((float)$valor, intval($dec), ',', '.');
    }

    function fmt_fecha_dmy($fecha)
    {
        $fecha = trim((string)$fecha);
        if ($fecha === '') return '-';

        $d = DateTime::createFromFormat('Y-m-d', substr($fecha, 0, 10));
        if ($d instanceof DateTime) {
            return $d->format('d-m-Y');
        }

        return $fecha;
    }

    function dias_entre($desde, $hasta)
    {
        if ($desde === '' || $hasta === '') return 0;

        $d1 = DateTime::createFromFormat('Y-m-d', substr($desde, 0, 10));
        $d2 = DateTime::createFromFormat('Y-m-d', substr($hasta, 0, 10));
        if (!($d1 instanceof DateTime) || !($d2 instanceof DateTime)) return 0;

        $diff = $d1->diff($d2);

        return intval($diff->days);
    }
}
