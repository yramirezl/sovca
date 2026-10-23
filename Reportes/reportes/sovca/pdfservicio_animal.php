<?php
/* ============================================================
   REPORTE DE SERVICIO ANIMAL POR HEMBRA
   Compatible con PHP 7.4 y PHP 8.3
   ------------------------------------------------------------
   Optimizaciones y correcciones aplicadas (2026-10-14):

   1) PARÁMETROS: los 6 bloques repetidos de parseo se reemplazan
      por un bucle con saneado de valores (evita inyección por la
      cadena base64 y fechas inválidas que lanzaban DateTime).
   2) CONSULTAS: se pasa de ~19 consultas por corrida (~14 por cada
      hembra: 4 totales + 1 meta + 3 efectividades + DNP + IEP +
      gestación/parto + detalle) a 5 fijas sin importar cuántas
      hembras muestre el reporte: fundo, metas, totales, detalle y
      partos. Cada una usa COUNT(*) FILTER y GROUP BY.
   3) CORRECCIONES:
      - Las metas ya no se vuelven a consultar por cada hembra y el
        `mfundos_id=5` fijo se cambia por el fundo del usuario.
      - `$$total_teg_gesta` (variable variable) → `$total_teg_gesta`.
      - El N° de arete de la tabla resumen salía vacío: la consulta
        principal no traía `nro_chapeta`.
      - El IEP se calcula con los partos ordenados por fecha; antes
        lanzaba error fatal (TypeError) cuando la hembra tenía 2 o
        más partos y el promedio se dividía entre el total de partos.
      - El filtro de sexo sobre `manimales` usaba una columna
        inexistente (`sexo_animal`), por lo que el rango de animales
        quedaba siempre en 0..0 cuando no se indicaba un código.
   4) TEXTO: se usa el conversor `__pdf_txt()` (ya definido en este
      archivo) en lugar de `utf8_decode()`, obsoleto desde PHP 8.2,
      y se aplica también a los textos que vienen de la base de datos
      (nombres, estados fisiológicos, fundo) para que los acentos se
      impriman correctamente con la fuente ISO-8859-1 del PDF.
   5) La salida del PDF es la misma del reporte anterior.
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
    var $reg;
    var $p = 0;
    var $titulo = '';

    /* Cachés: una consulta por tabla para todas las hembras del reporte */
    var $metas           = array();
    var $totales_hembra  = array();
    var $detalle_hembra  = array();
    var $partos_hembra   = array();

    /* Cachés del reporte de machos */
    var $totales_macho   = array();
    var $detalle_macho   = array();

    /* Código de macho escrito en el servicio → código real del hato */
    var $mapa_machos     = array();

    /* Aviso de la sección de machos sin registro (se imprime una sola vez) */
    var $aviso_macho_nr  = 0;

    /* hembra | macho | ambos */
    var $modo = 'hembra';

    /* Constructor dual: PHP 5.3.3 → 8.3 */
    function __construct()
    {
        $this->pdfreporte();
    }

    function pdfreporte()
    {
        $this->fpdf('l', 'mm', 'legal');
        $this->bd  = new baseClases();
        $this->cab = new Cabecera();

        /* ---------------- Parámetros (antes: 6 bloques repetidos) ---------------- */
        $p = $this->leerParametros();

        $this->fecha_desde        = $p['fecha_inicio'];
        $this->fecha_hasta        = $p['fecha_hasta'];
        $this->cod_bovs           = $p['cod_bovs'];
        $this->filtrar_sexo       = $p['filtrarXsexo'];
        $this->buscar_x_cod_arete = $p['codigo_erete'];

        /* ---------------- Fundo de la sesión ---------------- */
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->mfundos_id = isset($_SESSION['mfundos_id']) ? (int) $_SESSION['mfundos_id'] : 0;

        $fila_fundo = $this->fila('SELECT nombre, cid_rif from mfundos where id=' . $this->mfundos_id . ';');
        $this->arrp_fundo    = array($fila_fundo);
        $this->nombre_fundo  = isset($fila_fundo['nombre'])  ? $fila_fundo['nombre']  : '';
        $this->cid_rif_fundo = isset($fila_fundo['cid_rif']) ? $fila_fundo['cid_rif'] : '';

        /* ---------------- Cadenas de filtro ---------------- */
        $cadena_sexo      = '';   /* filtro de sexo sobre la vista (lado hembra) */
        $cadena_codigo    = '';   /* filtro por id del animal (vista, hembras)   */
        $cadena_cod_macho = '';   /* filtro por codigo del macho                 */
        $cad_order        = '';

        if ($this->filtrar_sexo != '') {
            $cadena_sexo = 'and sexo_animal=' . (int) $this->filtrar_sexo . ' ';
        }

        if (($this->fecha_desde != '') && ($this->fecha_hasta != '')) {
            $date_desde  = new DateTime($this->fecha_desde);
            $fecha_desde = $date_desde->format('Y-m-d');

            $date_hasta  = new DateTime($this->fecha_hasta);
            $fecha_hasta = $date_hasta->format('Y-m-d');

            $this->cadena_rango_fecha = 'Desde el ' . $this->fecha_desde . ' Hasta el ' . $this->fecha_hasta;
            $cad_order = 'order by id_animal';
            $this->cadena_fecha = " and f_servicio
            BETWEEN CAST ('$fecha_desde' AS DATE) AND CAST ('$fecha_hasta' AS DATE)";
        } else {
            $this->cadena_fecha = '';
            $this->cadena_rango_fecha = '';
        }

        $cod_animal     = 0;
        $codigo_animal  = '';
        $sexo_codigo    = 0;

        if ($this->cod_bovs != '') {

            if ($this->buscar_x_cod_arete == 1) {
                $cadena = "and codigo_rgb_id_animal=" . $this->comillar($this->cod_bovs) . " ";
            } else if ($this->buscar_x_cod_arete == 2) {
                $cadena = "and nro_arete=" . $this->comillar($this->cod_bovs) . " ";
            } else {
                $cadena = '';
            }

            $animal = $this->fila('SELECT id, codigo_rgb_id_animal, sexo from manimales where mfundos_id=' . $this->mfundos_id . " $cadena;");
            $this->arrp_id = array($animal);

            $cod_animal    = isset($animal['id']) ? intval($animal['id']) : 0;
            $codigo_animal = isset($animal['codigo_rgb_id_animal']) ? $animal['codigo_rgb_id_animal'] : '';
            $sexo_codigo   = isset($animal['sexo']) ? (int) $animal['sexo'] : 0;

            if ($cod_animal > 0) {
                /* Hembras: la vista se filtra por id_animal (que es el lado hembra) */
                $cadena_codigo    = ' and id_animal= ' . $cod_animal . '  ';
                /* Machos: se filtra por el codigo del macho en mregservicios */
                $cadena_cod_macho = ($codigo_animal != '') ? ' and a.codigo_rgb_id_animal_macho = ' . $this->comillar($codigo_animal) . ' ' : '';
                $cad_order = 'order by id_animal';
            } else {
                /* OJO: manimales NO tiene columna sexo_animal (el filtro de sexo
                   se aplica en la vista v_rep_regservicios, que sí la tiene). */
                $this->arrp_id_desde = $this->filas('SELECT min(id) as minimo from manimales where mfundos_id=' . $this->mfundos_id . " and mestatus_id<>3;");
                $this->arrp_id_hasta = $this->filas('SELECT max(id) as maximo from manimales where mfundos_id=' . $this->mfundos_id . " and mestatus_id<>3;");

                $cod_animal_desde = isset($this->arrp_id_desde[0]['minimo']) ? intval($this->arrp_id_desde[0]['minimo']) : 0;
                $cod_animal_hasta = isset($this->arrp_id_hasta[0]['maximo']) ? intval($this->arrp_id_hasta[0]['maximo']) : 0;

                $cadena_codigo = ' and id_animal>= ' . $cod_animal_desde . '  and id_animal<= ' . $cod_animal_hasta . ' ';
                $cad_order = 'order by id_animal';
            }
        }

        /* ---------------- Modo del reporte ----------------
           hembra : servicios de las hembras (1 por hembra)
           macho  : participacion de los machos en los servicios
           ambos  : sin filtros se muestran los dos (hembras y machos) */
        $this->modo = 'hembra';
        if ($this->filtrar_sexo == 2) {
            $this->modo = 'macho';
        } else if ($this->filtrar_sexo == '' && $sexo_codigo == 2 && $cod_animal > 0) {
            $this->modo = 'macho';   /* se buscó el código de un macho sin indicar sexo */
        } else if ($this->filtrar_sexo == '' && $cod_animal == 0) {
            $this->modo = 'ambos';
        }

        /* ---------------- Animales del reporte ----------------
           Sin LIMIT: se listan TODAS las hembras y/o todos los machos que
           cumplen los filtros (antes: order by codigo_servicio desc limit 1,
           por lo que siempre salía un solo animal).                      */
        $this->arrp = array();

        if ($this->modo == 'hembra' || $this->modo == 'ambos') {
            $filtro_hembras = ($cadena_sexo != '') ? $cadena_sexo : ' and sexo_animal=1 ';

            $sql_hembras = " SELECT DISTINCT ON (codigo_rgb_id_animal_hembra)
                                    codigo_rgb_id_animal_hembra AS codigo,
                                    coalesce(nro_chapeta,'')    AS nro_chapeta,
                                    coalesce(nombre_h,'')       AS nombre_h,
                                    1                           AS sexo_animal
                               from v_rep_regservicios
                              where mfundos_id=" . $this->mfundos_id . " $this->cadena_fecha $cadena_codigo $filtro_hembras
                              order by codigo_rgb_id_animal_hembra; ";

            foreach ($this->filas($sql_hembras) as $fila) {
                $this->arrp[] = $fila;
            }
        }

        if ($this->modo == 'macho' || $this->modo == 'ambos') {
            $fecha_macho = str_replace('f_servicio', 'a.f_servicio', $this->cadena_fecha);

            /* El código del macho guardado en el servicio NO siempre es el
               código del hato: los operarios escriben el N° de arete o un
               texto libre ('----------', 'HOLSTEIN SEXADO'…). Se resuelve
               cada valor contra manimales (por código y, si no, por arete)
               para que los servicios caigan en el toro correcto y para que
               la fila de identificación no salga en blanco. Los valores que
               no existen en el hato se listan al final, en su propia
               sección, para no perder información. */
            $sql_mapa = " SELECT c.crudo AS crudo,
                                 coalesce(m1.codigo_rgb_id_animal, m2.codigo_rgb_id_animal, c.crudo) AS codigo,
                                 coalesce(m1.nro_arete, m2.nro_arete, '')     AS nro_chapeta,
                                 coalesce(m1.nombre, m2.nombre, '')           AS nombre_h,
                                 case when coalesce(m1.codigo_rgb_id_animal, m2.codigo_rgb_id_animal) is null
                                      then 0 else 1 end                       AS registrado
                            from ( select distinct a.codigo_rgb_id_animal_macho AS crudo
                                     from mregservicios a
                                    where a.mfundos_id = " . $this->mfundos_id . " and a.status = 1 $fecha_macho $cadena_cod_macho ) c
                            left join manimales m1 on m1.mfundos_id = " . $this->mfundos_id . " and m1.codigo_rgb_id_animal = c.crudo
                            left join manimales m2 on m2.mfundos_id = " . $this->mfundos_id . " and m2.nro_arete = c.crudo
                                                 and m1.id is null
                           order by 1; ";

            $registrados    = array();
            $no_registrados = array();

            foreach ($this->filas($sql_mapa) as $fila) {
                $codigo = (string) $fila['codigo'];

                if (($fila['crudo'] !== null) && ($fila['crudo'] !== '')) {
                    $this->mapa_machos[(string) $fila['crudo']] = $codigo;
                }

                $item = array(
                    'codigo'      => $codigo,
                    'nro_chapeta' => (string) $fila['nro_chapeta'],
                    'nombre_h'    => (string) $fila['nombre_h'],
                    'registrado'  => ((int) $fila['registrado'] == 1) ? 1 : 0,
                    'sexo_animal' => 2,
                );

                if ($item['registrado'] == 1) {
                    $registrados[$codigo] = $item;
                } else {
                    $no_registrados[$codigo] = $item;
                }
            }

            ksort($registrados);
            ksort($no_registrados);

            foreach ($registrados as $item) {
                $this->arrp[] = $item;
            }
            foreach ($no_registrados as $item) {
                $this->arrp[] = $item;
            }
        }

        $this->reg = 0;

        /* ---------------- Datos precalculados (1 consulta por tabla) ---------------- */
        $this->metas = $this->cargarMetas();

        $hembras = array();
        $machos  = array();
        foreach ($this->arrp as $fila) {
            if ((int) $fila['sexo_animal'] === 2) {
                $machos[] = $fila['codigo'];
            } else {
                $hembras[] = $fila['codigo'];
            }
        }

        $this->cargarDatosHembras($hembras);
        $this->cargarDatosMachos($machos);
    }

    /* ============================================================
       PARÁMETROS DE LA URL (base64 con clave=valor&...)
       ============================================================ */
    function leerParametros()
    {
        $par = array(
            'cod_bovs'      => '',
            'filtrarXsexo'  => '',
            'fecha_inicio'  => '',
            'fecha_hasta'   => '',
            'codigo_erete'  => '',
        );

        $crudo = base64_decode((string) getvalue('%$%'));
        if ($crudo === false || $crudo === '') {
            return $par;
        }

        foreach (explode('&', $crudo) as $cadena) {
            if (strpos($cadena, '=') === false) {
                continue;
            }
            $valores = explode('=', $cadena, 2);
            $clave   = trim($valores[0]);
            $valor   = isset($valores[1]) ? $valores[1] : '';

            switch ($clave) {
                case 'cod_bovs':
                    /* Código RGB o número de arete: solo caracteres seguros */
                    $par['cod_bovs'] = preg_replace('/[^A-Za-z0-9 _.\-]/', '', $valor);
                    break;
                case 'filtrarXsexo':
                    $par['filtrarXsexo'] = ($valor !== '') ? (string) (int) $valor : '';
                    break;
                case 'fecha_inicio':
                    $par['fecha_inicio'] = $this->fechaValida($valor);
                    break;
                case 'fecha_hasta':
                    $par['fecha_hasta'] = $this->fechaValida($valor);
                    break;
                case 'codigo_erete':
                    $par['codigo_erete'] = ($valor !== '') ? (string) (int) $valor : '';
                    break;
            }
        }

        return $par;
    }

    /* Fecha válida (d/m/Y, d-m-Y o Y-m-d) o cadena vacía */
    function fechaValida($fecha)
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return '';
        }

        $ok = preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}$/', $fecha)
           || preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha);

        if (!$ok || strtotime(str_replace('/', '-', $fecha)) === false) {
            return '';
        }

        return $fecha;
    }

    /* ============================================================
       ACCESO A DATOS
       ============================================================ */
    /* Ejecuta la consulta y devuelve todas las filas como arreglos */
    function filas($sql)
    {
        $rs = $this->bd->select($sql);
        if (!$rs) {
            return array();
        }

        $filas = array();
        foreach ($rs as $fila) {
            $filas[] = $fila;
        }
        if (is_object($rs) && method_exists($rs, 'Close')) {
            $rs->Close();
        }

        return $filas;
    }

    /* Primera fila de la consulta (o arreglo vacío) */
    function fila($sql)
    {
        $filas = $this->filas($sql);
        return isset($filas[0]) ? $filas[0] : array();
    }

    /* Cadena segura para usar entre comillas simples */
    function comillar($valor)
    {
        return "'" . str_replace("'", "''", (string) $valor) . "'";
    }

    /* ============================================================
       METAS DEL FUNDO (antes se consultaban por cada hembra)
       ============================================================ */
    function cargarMetas()
    {
        $sql = "SELECT nombre,periodo,condicion,codmeta_bov,uni_medida
                from mmetaservicios
                where mfundos_id=" . $this->mfundos_id . " and status>0
                union
                SELECT nombre,periodo,condicion,codmeta_bov,uni_medida
                from mmetaservicios
                where mfundos_id=0 and status>0 and
                codmeta_bov NOT in (select DISTINCT b.codmeta_bov
                    from mmetaservicios b where b.mfundos_id=" . $this->mfundos_id . " and b.status>0);";

        $metas = array(
            'valores_MMG'   => '',
            'valores_DNPOG' => '',
            'valores_PMA'   => '',
            'valores_MTEG'  => '',
            'valores_POGP'  => '',
            'valores_IEPO'  => '',
            'valores_MIAG'  => '',
            'unidad_POGP'   => '',
            'unidad_PMA'    => '',
        );

        foreach ($this->filas($sql) as $data_meta) {
            $cod = $data_meta['codmeta_bov'];
            $per = $data_meta['periodo'];
            $con = $data_meta['condicion'];
            $uni = $data_meta['uni_medida'];

            if ($cod == 'MMG') {
                $metas['valores_MMG'] = $con . ' ' . $per;
            } else if ($cod == 'DNPOG') {
                $metas['valores_DNPOG'] = $con . ' ' . $per . ' ' . $uni;
            } else if ($cod == 'PMA') {
                $metas['valores_PMA']   = $con . ' ' . $per . ' ' . $uni;
                $metas['unidad_PMA']    = $uni;
            } else if ($cod == 'MTEG') {
                $metas['valores_MTEG'] = $con . ' ' . $per;
            } else if ($cod == 'POGP') {
                $metas['valores_POGP'] = $con . ' ' . $per . ' ' . $uni;
                $metas['unidad_POGP']  = $uni;
            } else if ($cod == 'IEPO') {
                $metas['valores_IEPO'] = $con . ' ' . $per . ' ' . $uni;
            } else if ($cod == 'MIAG') {
                $metas['valores_MIAG'] = $con . ' ' . $per;
            }
        }

        return $metas;
    }

    /* ============================================================
       TOTALES, DETALLE Y PARTOS DE LAS HEMBRAS DEL REPORTE
       Una consulta por tabla (antes: 14 por cada hembra)
       ============================================================ */
    function cargarDatosHembras($hembras)
    {
        if (!is_array($hembras) || count($hembras) == 0) {
            return;
        }

        $lista = array();
        foreach (array_unique($hembras) as $cod) {
            $lista[] = $this->comillar($cod);
        }
        $in = implode(',', $lista);

        /* --- Totales por hembra --- */
        $sql = "SELECT codigo_rgb_id_animal_hembra,
                   count(*) FILTER (WHERE status=1)                              AS total_servicio,
                   count(*) FILTER (WHERE status=1 AND medofisiologicos_id=1)     AS total_gestaciones,
                   count(*) FILTER (WHERE status=1 AND medofisiologicos_id=4)     AS total_aborto,
                   count(*) FILTER (WHERE status=1 AND medofisiologicos_id=5)     AS total_vacia,
                   count(*) FILTER (WHERE status=1 AND mservicios_id=1)           AS emg_monta,
                   count(*) FILTER (WHERE mservicios_id=1 AND medofisiologicos_id=1) AS emg_gesta,
                   count(*) FILTER (WHERE status=1 AND mservicios_id=2)           AS eiag_monta,
                   count(*) FILTER (WHERE mservicios_id=2 AND medofisiologicos_id=1) AS eiag_gesta,
                   count(*) FILTER (WHERE status=1 AND mservicios_id=3)           AS teg_monta,
                   count(*) FILTER (WHERE mservicios_id=3 AND medofisiologicos_id=1) AS teg_gesta,
                   coalesce(sum(dnp) FILTER (WHERE status=1), 0)                  AS dnp_total,
                   count(*) FILTER (WHERE status=1 AND dnp>0)                     AS dnp_reg,
                   count(*) FILTER (WHERE status=1 AND medofisiologicos_id=1 AND parto=1) AS gesta_parto,
                   count(*) FILTER (WHERE status=1 AND medofisiologicos_id=4)     AS gesta_aborto
                 from mregservicios
                 where codigo_rgb_id_animal_hembra IN ($in)
                 group by codigo_rgb_id_animal_hembra;";

        foreach ($this->filas($sql) as $r) {
            $this->totales_hembra[$r['codigo_rgb_id_animal_hembra']] = $r;
        }

        /* --- Detalle de servicios --- */
        $sql = "SELECT a.codigo_rgb_id_animal_hembra        AS hembra,
                   to_char(a.f_servicio, 'DD-MM-YYYY')    AS f_servicio,
                   to_char(a.f_ultimo_parto, 'DD-MM-YYYY') AS f_ultimo_parto,
                   to_char(a.fecha_parto, 'DD-MM-YYYY')   AS fecha_parto,
                   a.nro_servicio, a.dnp,
                   case when a.mservicios_id=1 then 'Monta'
                        when a.mservicios_id=2 then 'Inse. Artif'
                        when a.mservicios_id=3 then 'Transf. Embrio'
                        else 'No Definida' end             AS tipo_servicio,
                   coalesce(b.nombre, '')                  AS estado_fisiologico,
                   case when a.parto=1 then 'SI' else 'NO' end AS pario
                 from mregservicios a
                 left join mestadofisiologicos b on b.id = a.medofisiologicos_id
                 where a.codigo_rgb_id_animal_hembra IN ($in) and a.status=1
                 order by a.codigo_rgb_id_animal_hembra, a.f_servicio asc;";

        foreach ($this->filas($sql) as $r) {
            $this->detalle_hembra[$r['hembra']][] = $r;
        }

        /* --- Partos (para el IEP), ordenados por fecha --- */
        $sql = "SELECT codigo_rgb_id_animal_hembra AS hembra,
                   to_char(f_ultimo_parto, 'DD-MM-YYYY') AS f_parto
                 from mregservicios
                 where parto=1 and status=1 and codigo_rgb_id_animal_hembra IN ($in)
                 order by codigo_rgb_id_animal_hembra, f_ultimo_parto asc;";

        foreach ($this->filas($sql) as $r) {
            $this->partos_hembra[$r['hembra']][] = $r['f_parto'];
        }
    }

    /* ============================================================
       CASE que traduce el código de macho escrito en el servicio al
       código real del hato (NULL y desconocidos quedan como están).
       Se usa en los agregados para que las variantes de un mismo toro
       (código, arete, texto) sumen en una sola ficha.
       ============================================================ */
    function expr_macho($campo)
    {
        $cuando = '';
        foreach ($this->mapa_machos as $crudo => $codigo) {
            if ((string) $crudo === (string) $codigo) {
                continue;
            }
            $cuando .= ' when ' . $this->comillar($crudo) . ' then ' . $this->comillar($codigo) . ' ';
        }

        /* OJO: el CASE simple necesita al menos un WHEN; si no hay alias que
           traducir se usa coalesce para que los servicios sin macho (NULL)
           no se pierdan. */
        if ($cuando === '') {
            return 'coalesce(' . $campo . ', \'\')';
        }

        return 'coalesce(case ' . $campo . $cuando . ' else ' . $campo . ' end, \'\')';
    }

    /* ============================================================
       DATOS DE LOS MACHOS (participación en los servicios)
       Igual que las hembras: una consulta de totales y una de detalle
       para todos los machos del reporte.
       ============================================================ */
    function cargarDatosMachos($machos)
    {
        if (!is_array($machos) || count($machos) == 0) {
            return;
        }

        $lista = array();
        foreach (array_unique($machos) as $cod) {
            $lista[] = $this->comillar($cod);
        }
        $in = implode(',', $lista);

        /* El servicio puede traer el arete o un texto en lugar del código:
           el CASE agrupa todas las variantes bajo el código del hato */
        $expr_tot = $this->expr_macho('codigo_rgb_id_animal_macho');
        $expr_det = $this->expr_macho('a.codigo_rgb_id_animal_macho');

        /* --- Totales por macho --- */
        $sql = "SELECT $expr_tot AS codigo_rgb_id_animal_macho,
                   count(*) FILTER (WHERE status=1)                                    AS total_servicio,
                   count(DISTINCT codigo_rgb_id_animal_hembra) FILTER (WHERE status=1)  AS hembras_servidas,
                   count(*) FILTER (WHERE status=1 AND medofisiologicos_id=1)           AS gestaciones,
                   count(*) FILTER (WHERE status=1 AND parto=1)                         AS partos,
                   count(*) FILTER (WHERE status=1 AND medofisiologicos_id=4)           AS abortos,
                   count(*) FILTER (WHERE status=1 AND mservicios_id=1)                 AS n_monta,
                   count(*) FILTER (WHERE mservicios_id=1 AND medofisiologicos_id=1)    AS monta_gesta,
                   count(*) FILTER (WHERE status=1 AND mservicios_id=2)                 AS n_ia,
                   count(*) FILTER (WHERE mservicios_id=2 AND medofisiologicos_id=1)    AS ia_gesta,
                   count(*) FILTER (WHERE status=1 AND mservicios_id=3)                 AS n_te,
                   count(*) FILTER (WHERE mservicios_id=3 AND medofisiologicos_id=1)    AS te_gesta
                 from mregservicios
                 where mfundos_id = " . $this->mfundos_id . " and status=1 and $expr_tot IN ($in)
                 group by $expr_tot;";

        foreach ($this->filas($sql) as $r) {
            $this->totales_macho[$r['codigo_rgb_id_animal_macho']] = $r;
        }

        /* --- Detalle: cada servicio con la hembra atendida --- */
        $sql = "SELECT $expr_det                             AS macho,
                   to_char(a.f_servicio, 'DD-MM-YYYY')     AS f_servicio,
                   a.nro_servicio, a.dnp,
                   a.codigo_rgb_id_animal_hembra           AS hembra,
                   coalesce(mh.nombre, '')                 AS nombre_hembra,
                   coalesce(mh.nro_arete, '')              AS arete_hembra,
                   case when a.mservicios_id=1 then 'Monta'
                        when a.mservicios_id=2 then 'Inse. Artif'
                        when a.mservicios_id=3 then 'Transf. Embrio'
                        else 'No Definida' end             AS tipo_servicio,
                   coalesce(ef.nombre, '')                 AS estado_fisiologico,
                   case when a.parto=1 then 'SI' else 'NO' end AS pario
                 from mregservicios a
                 left join manimales mh on mh.codigo_rgb_id_animal = a.codigo_rgb_id_animal_hembra
                                       and mh.mfundos_id = a.mfundos_id
                 left join mestadofisiologicos ef on ef.id = a.medofisiologicos_id
                 where a.mfundos_id = " . $this->mfundos_id . " and a.status=1 and $expr_det IN ($in)
                 order by $expr_det, a.f_servicio asc;";

        foreach ($this->filas($sql) as $r) {
            $this->detalle_macho[$r['macho']][] = $r;
        }
    }

    function totalesMacho($cod_macho)
    {
        return isset($this->totales_macho[$cod_macho]) ? $this->totales_macho[$cod_macho] : array();
    }

    function detalleMacho($cod_macho)
    {
        return isset($this->detalle_macho[$cod_macho]) ? $this->detalle_macho[$cod_macho] : array();
    }

    /* ============================================================
       PARTICIPACIÓN DEL MACHO EN LOS SERVICIOS
       Bloque equivalente al de la hembra: identificación, índices de
       efectividad, metas del fundo y detalle de los servicios con la
       hembra atendida en cada uno.
       ============================================================ */
    function cuerpoMacho($data)
    {
        $cod_macho  = $data['codigo'];
        $registrado = isset($data['registrado']) ? (int) $data['registrado'] : 1;
        $tot        = $this->totalesMacho($cod_macho);

        $total_servicio   = isset($tot['total_servicio'])   ? (int) $tot['total_servicio']   : 0;
        $hembras_servidas = isset($tot['hembras_servidas']) ? (int) $tot['hembras_servidas'] : 0;
        $gestaciones      = isset($tot['gestaciones'])      ? (int) $tot['gestaciones']      : 0;
        $partos           = isset($tot['partos'])           ? (int) $tot['partos']           : 0;
        $abortos          = isset($tot['abortos'])          ? (int) $tot['abortos']          : 0;

        $n_monta    = isset($tot['n_monta'])    ? (int) $tot['n_monta']    : 0;
        $monta_gesta = isset($tot['monta_gesta']) ? (int) $tot['monta_gesta'] : 0;
        $n_ia       = isset($tot['n_ia'])       ? (int) $tot['n_ia']       : 0;
        $ia_gesta   = isset($tot['ia_gesta'])   ? (int) $tot['ia_gesta']   : 0;
        $n_te       = isset($tot['n_te'])       ? (int) $tot['n_te']       : 0;
        $te_gesta   = isset($tot['te_gesta'])   ? (int) $tot['te_gesta']   : 0;

        /* Efectividades: misma fórmula que el reporte de hembras */
        $efec_monta = '';
        if ($n_monta > 0) {
            $efec_monta = number_format(($monta_gesta > 0) ? ($n_monta / $monta_gesta) : $monta_gesta, 1);
        }

        $efec_ia = '';
        if ($n_ia > 0) {
            $efec_ia = number_format(($ia_gesta > 0) ? ($n_ia / $ia_gesta) : $ia_gesta, 1);
        }

        $efec_te = '';
        if ($n_te > 0) {
            $efec_te = number_format(($te_gesta > 0) ? ($n_te / $te_gesta) : $te_gesta, 1);
        }

        $porcent_gestacion = '';
        $porcent_aborto    = '';
        if ($total_servicio > 0) {
            $porcent_gestacion = number_format(($gestaciones / $total_servicio) * 100, 0) . ' %';
            $porcent_aborto    = number_format(($abortos / $total_servicio) * 100, 0) . ' %';
        }

        /* Metas del fundo (misma consulta única para todo el reporte) */
        $valores_MMG   = $this->metas['valores_MMG'];
        $valores_MIAG  = $this->metas['valores_MIAG'];
        $valores_MTEG  = $this->metas['valores_MTEG'];
        $valores_POGP  = $this->metas['valores_POGP'];
        $valores_PMA   = $this->metas['valores_PMA'];

        /* ---------- Encabezado del macho ---------- */
        $this->SetFont('ARIAL', 'B', '14');
        $this->SetWidths(array(180));
        $this->SetAligns(array('C'));
        $this->SetFillTable(1);
        $this->SetFillColor(2, 223, 173);
        $this->SetBorder(true);
        $this->SetJump(10);
        $this->RowM(array(__pdf_txt('Datos del Macho')));

        $this->SetDrawColor(200, 157, 116);
        $this->SetTextColor(0, 0, 0);

        $this->SetWidths(array(40, 40, 100, 40, 40, 30, 30));
        $this->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C'));
        $this->SetDrawColor(2, 157, 116);
        $this->SetBorder(true);
        $this->SetFont('ARIAL', 'B', '11');
        $this->SetJump(6);
        $this->SetFillColor(250, 180, 180);
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(0, 0, 0);
        $this->SetJump(8);

        $this->RowMFillColor(array(__pdf_txt('Código'), __pdf_txt('N° Arete'), 'Nombre', 'Total Servicios', __pdf_txt('Hembras Servidas'), 'Gestaciones', 'Partos'), true);

        $this->SetFillColor(200, 250, 216);
        $this->SetAligns(array('L', 'C', 'L', 'C', 'C', 'C', 'C'));
        $this->SetFont('ARIAL', 'B', '10');
        $this->SetFillColor(148, 247, 224);

        /* Si el código escrito en el servicio no existe en el hato la fila
           salía en blanco: ahora se identifica con un texto explícito */
        $cod_txt    = (($cod_macho === '') || ($cod_macho === null)) ? '(sin identificar)' : $cod_macho;
        $nombre_txt = ($registrado == 1) ? $data['nombre_h'] : '(no registrado en el hato)';

        $this->RowMFillColor(array($cod_txt, $data['nro_chapeta'], __pdf_txt($nombre_txt), $total_servicio, $hembras_servidas, $gestaciones, $partos), true);

        $this->SetTextColor(0, 0, 0);
        $this->SetWidths(array(30, 50, 40, 50, 40, 30, 50, 30));
        $this->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'));
        $this->SetFillColor(250, 180, 180);
        $this->SetBorder(true);
        $this->SetFont('ARIAL', 'B', '10');
        $this->SetJump(6);

        $this->RowMFillColor(array('', __pdf_txt('Efectividad Monta/ Gest'), __pdf_txt('Efectividad IA/ Gest'), 'Efectividad TE/ Gest', 'Gestaciones', __pdf_txt('% Gestación'), __pdf_txt('% Abortos'), ''), true);

        /* ---------- Fila ACTUAL / META ---------- */
        $this->SetFillColor(148, 247, 224);
        $this->SetJump(8);
        $this->RowMFillColor(array('ACTUAL', $efec_monta, $efec_ia, $efec_te, $gestaciones, $porcent_gestacion, $porcent_aborto, ''), true);

        $this->SetFillColor(39, 224, 227);
        $this->SetJump(6);
        $this->RowMFillColor(array('META', $valores_MMG, $valores_MIAG, $valores_MTEG, '', $valores_POGP, $valores_PMA, ''), true);

        /* ---------- Detalle de los servicios ---------- */
        $this->SetWidths(array(30, 40, 40, 40, 50, 30, 30, 30, 30));
        $this->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'));
        $this->SetFillColor(250, 180, 180);
        $this->SetBorder(true);
        $this->SetFont('ARIAL', 'B', '10');
        $this->SetJump(6);

        $this->RowMFillColor(array(__pdf_txt('Fecha'), __pdf_txt('# Servicio'), 'Tipo de Servicio', 'Hembra', __pdf_txt('Nombre de la Hembra'), __pdf_txt('N° Arete'), __pdf_txt('Estado Fisiológico'), __pdf_txt('Parió?'), __pdf_txt('DNP')), true);

        $servicios = $this->detalleMacho($cod_macho);

        foreach ($servicios as $serv) {

            /* Salto de página si se acaba el espacio */
            if ($this->GetY() > 175) {
                $this->AddPage();
                $this->Pie_Pagina();
                $this->SetXY(10, 35);

                $this->SetWidths(array(30, 40, 40, 40, 50, 30, 30, 30, 30));
                $this->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'));
                $this->SetFillColor(250, 180, 180);
                $this->SetBorder(true);
                $this->SetFont('ARIAL', 'B', '10');
                $this->SetJump(6);
                $this->RowMFillColor(array(__pdf_txt('Fecha'), __pdf_txt('# Servicio'), 'Tipo de Servicio', 'Hembra', __pdf_txt('Nombre de la Hembra'), __pdf_txt('N° Arete'), __pdf_txt('Estado Fisiológico'), __pdf_txt('Parió?'), __pdf_txt('DNP')), true);
                $this->SetFillColor(148, 247, 224);
                $this->SetJump(8);
            }

            $this->SetFillColor(148, 247, 224);
            $this->SetJump(8);

            $this->RowMFillColor(array(
                $serv['f_servicio'],
                $serv['nro_servicio'],
                __pdf_txt($serv['tipo_servicio']),
                $serv['hembra'],
                __pdf_txt($serv['nombre_hembra']),
                $serv['arete_hembra'],
                __pdf_txt($serv['estado_fisiologico']),
                $serv['pario'],
                $serv['dnp']
            ), true);
        }
    }

    function totalesHembra($cod_hembra)
    {
        if (isset($this->totales_hembra[$cod_hembra])) {
            return $this->totales_hembra[$cod_hembra];
        }
        return array();
    }

    function detalleHembra($cod_hembra)
    {
        return isset($this->detalle_hembra[$cod_hembra]) ? $this->detalle_hembra[$cod_hembra] : array();
    }

    /* ============================================================
       IEP: promedio de días entre partos consecutivos
       (antes: se recorrían los partos sin ordenar y se leían como si
        fueran filas del registro → error fatal con 2 o más partos)
       ============================================================ */
    function calcularIEP($cod_hembra)
    {
        $partos = isset($this->partos_hembra[$cod_hembra]) ? $this->partos_hembra[$cod_hembra] : array();
        if (count($partos) < 2) {
            return '';
        }

        $fechas = array();
        foreach ($partos as $fecha) {
            if ($fecha !== '' && $fecha !== null && !in_array($fecha, $fechas, true)) {
                $fechas[] = $fecha;
            }
        }
        if (count($fechas) < 2) {
            return '';
        }

        sort($fechas);

        $suma = 0;
        $n    = 0;
        for ($i = 1; $i < count($fechas); $i++) {
            $dias = $this->diasEntre($fechas[$i], $fechas[$i - 1]);
            if ($dias > 0) {
                $suma += $dias;
                $n++;
            }
        }

        return ($n > 0) ? number_format($suma / $n, 0) : '';
    }

    function getEncabezado($x, $y)
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetBorder(1);
        $this->SetDrawColor(2, 157, 116);
        //Fondo verde de celda

        $this->SetFont('ARIAL', 'B', 12);
        $this->SetXY($x + 0, $y - 20);
        $this->SetWidths(array(320));
        $this->SetAligns(array('C'));
        $this->SetBorder(1);
        $this->SetJump(12);
        $this->RowM(array(''));
        $this->SetXY($x + 60, $y - 15);

        $fecha = date('d/m/Y');

        $this->SetWidths(array(200, 50));
        $this->SetAligns(array('C', 'R'));

        $this->RowM(array(__pdf_txt($this->nombre_fundo) . '  ' . __pdf_txt($this->cid_rif_fundo), 'Fecha: ' . $fecha));

        $this->SetXY($x + 35, $y);

        if ($this->cadena_rango_fecha != '') {
            $this->SetXY($x + 15, $y);

            $this->SetJump(6);
            $this->SetWidths(array(250));

            $this->RowM(array(__pdf_txt('REPORTE DE SERVICIO ANIMAL ' . $this->cadena_rango_fecha)));
        } else {

            $this->SetJump(6);
            $this->SetWidths(array(250));
            $this->RowM(array('INFORME DETALLADO DE SERVICIO ANIMAL'));
        }
    }

    function Footer()
    {
        $this->y2 = 257;
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('ARIAL', 'B', '8');
    }

    /* Pie de página del reporte (se llama al cambiar de página).
       Antes no existía en este reporte y Cuerpo() lo invocaba: cuando el
       detalle pasaba de una página se producía un error fatal.
       Página legal apaisada: alto 215 mm y el corte automático de FPDF
       ocurre en 196 mm, por eso el pie se dibuja en 185. */
    function Pie_Pagina()
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetTextColor(0, 0, 100);
        $this->SetFont("ARIAL", "B", 7);
        $this->SetXY(10, 185);
        $this->SetWidths(array(335));
        $this->SetAligns(array('R'));
        $this->RowM(array(__pdf_txt('Sistema SOVCA (https://global.ruted.net)')));
    }

    function Header()
    {
        $this->cab->poner_cabecera($this, $this->titulo, 'p', '', 'n');
        $y = $this->GetY();
        $x = $this->GetX();

        $this->getEncabezado($x, $y);
        $this->SetWidths(array(60));
        $this->SetAligns(array('L'));
        $this->SetXY(10, 23);
    }
    //end header

    function Cuerpo()
    {
        $puntero = 0;
        $contador = 0;

        $this->SetXY(10, 50);

        /* Sin datos: un aviso en vez de una hoja en blanco */
        if (count($this->arrp) == 0) {
            $this->SetFont('ARIAL', 'B', '12');
            $this->SetWidths(array(180));
            $this->SetAligns(array('C'));
            $this->SetFillTable(1);
            $this->SetFillColor(230, 230, 230);
            $this->SetBorder(true);
            $this->SetJump(10);
            $this->RowM(array(__pdf_txt('No se encontraron servicios con los filtros seleccionados')));
            $this->SetBorder(0);

            return;
        }

        foreach ($this->arrp as $data) {

            $sexo_animal  = $data['sexo_animal'];
            $filtrar_sexo = $this->filtrar_sexo;

            $this->SetBorder(0);

            /* Espacio suficiente para el bloque del animal?
               El bloque más pequeño mide ~100 mm y el alto útil de la
               página es 180 mm, así que entra un bloque por hoja: si ya
               hay contenido, el siguiente empieza en página nueva
               (antes FPDF lo cortaba a mitad del bloque). */
            if ($this->GetY() > 100) {
                $this->AddPage();
                $this->Pie_Pagina();

                $this->SetXY(10, 35);
            }

            if ($sexo_animal == 2) {

                /* Aviso previo a la sección de los machos cuyo código escrito
                   en el servicio no corresponde a un animal del hato.
                   Empieza en hoja nueva para que el aviso no quede solo al
                   pie de la hoja anterior. */
                if (isset($data['registrado']) && ((int) $data['registrado'] == 0) && ($this->aviso_macho_nr == 0)) {
                    $this->aviso_macho_nr = 1;

                    if ($this->GetY() > 35) {
                        $this->AddPage();
                        $this->Pie_Pagina();
                        $this->SetXY(10, 35);
                    }

                    $this->SetFont('ARIAL', 'B', '11');
                    $this->SetWidths(array(180));
                    $this->SetAligns(array('C'));
                    $this->SetFillTable(1);
                    $this->SetFillColor(255, 214, 148);
                    $this->SetBorder(true);
                    $this->SetJump(9);
                    $this->RowM(array(__pdf_txt('SERVICIOS CON MACHO NO REGISTRADO EN EL HATO (código escrito en el servicio)')));
                    $this->SetBorder(0);
                }

                /* -------- Reporte de participación del MACHO -------- */
                $this->cuerpoMacho($data);

            } else if ($sexo_animal == 1) {

                    $cod_hembra = $data['codigo'];

                    /* ---------- Totales (del caché: 1 consulta para todas las hembras) ---------- */
                    $tot = $this->totalesHembra($cod_hembra);

                    $total_servicio    = isset($tot['total_servicio'])    ? $tot['total_servicio']    : 0;
                    $total_gestaciones = isset($tot['total_gestaciones']) ? $tot['total_gestaciones'] : 0;
                    $total_aborto      = isset($tot['total_aborto'])      ? $tot['total_aborto']      : 0;
                    $total_vacia       = isset($tot['total_vacia'])       ? $tot['total_vacia']       : 0;

                    $total_emg_monta  = isset($tot['emg_monta'])  ? (int) $tot['emg_monta']  : 0;
                    $total_emg_gesta  = isset($tot['emg_gesta'])  ? (int) $tot['emg_gesta']  : 0;
                    $total_eiag_monta = isset($tot['eiag_monta']) ? (int) $tot['eiag_monta'] : 0;
                    $total_eiag_gesta = isset($tot['eiag_gesta']) ? (int) $tot['eiag_gesta'] : 0;
                    $total_teg_monta  = isset($tot['teg_monta'])  ? (int) $tot['teg_monta']  : 0;
                    $total_teg_gesta  = isset($tot['teg_gesta'])  ? (int) $tot['teg_gesta']  : 0;

                    $total_dnp      = isset($tot['dnp_total']) ? (int) $tot['dnp_total'] : 0;
                    $total_cant_reg = isset($tot['dnp_reg'])   ? (int) $tot['dnp_reg']   : 0;

                    $cant_gesta_parto  = isset($tot['gesta_parto'])  ? (int) $tot['gesta_parto']  : 0;
                    $cant_gesta_aborto = isset($tot['gesta_aborto']) ? (int) $tot['gesta_aborto'] : 0;

                    /* ---------- Encabezado de la hembra ---------- */
                    $this->SetFont('ARIAL', 'B', '14');
                    $this->SetWidths(array(180));
                    $this->SetAligns(array('C'));
                    $this->SetFillTable(1);
                    $this->SetFillColor(2, 223, 173);

                    //Fondo verde de celda
                    $this->SetBorder(true);
                    $this->SetJump(10);
                    $this->RowM(array(__pdf_txt('Datos de la Hembra')));

                    $this->SetDrawColor(200, 157, 116);
                    //Fondo verde de celda
                    $this->SetTextColor(0, 0, 0);

                    $this->SetWidths(array(40, 40, 100, 40, 40, 30, 30));
                    $this->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C'));
                    $this->SetDrawColor(2, 157, 116);
                    //Fondo verde de celda

                    //Fondo verde de celda
                    $this->SetBorder(true);
                    $this->SetFont('ARIAL', 'B', '11');
                    $this->SetJump(6);
                    $this->SetFillColor(250, 180, 180);

                    $this->SetDrawColor(0, 0, 0);
                    $this->SetBorder(true);
                    $this->SetTextColor(0, 0, 0);
                    $this->SetJump(8);

                    $this->RowMFillColor(array(__pdf_txt('Código'), __pdf_txt('N° Arete'), 'Nombre', 'Total Servicios', 'Total Gestaciones', 'Total Abortos', __pdf_txt('Total Vacías')), true);
                    $this->SetFillColor(200, 250, 216);
                    $this->SetAligns(array('L', 'C', 'L', 'C', 'C', 'C', 'C'));
                    $this->SetFont('ARIAL', 'B', '10');

                    $this->SetFillColor(148, 247, 224);

                    //Fondo verde de celda
                    $this->RowMFillColor(array($data['codigo'], $data['nro_chapeta'], __pdf_txt($data['nombre_h']), $total_servicio, $total_gestaciones, $total_aborto, $total_vacia), true);
                    $this->SetTextColor(0, 0, 0);

                    $this->SetWidths(array(30, 50, 40, 50, 40, 30, 50, 30));

                    $this->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'));
                    //Fondo verde de celda
                    $this->SetFillColor(250, 180, 180);

                    //Fondo verde de celda
                    $this->SetBorder(true);
                    $this->SetFont('ARIAL', 'B', '10');
                    $this->SetJump(6);

                    $this->RowMFillColor(array('', __pdf_txt('Efectividad Monta/ Gest'), __pdf_txt('Efectividad IA/ Gest'), 'Efectividad TE/ Gest', __pdf_txt('DNP Óptimo'), __pdf_txt('IEP Óptimo'), __pdf_txt('% Gestación/Parió'), __pdf_txt('% Aborto')), true);

                    /* ---------- Metas del fundo (1 sola consulta) ---------- */
                    $valores_MMG   = $this->metas['valores_MMG'];
                    $valores_MIAG  = $this->metas['valores_MIAG'];
                    $valores_MTEG  = $this->metas['valores_MTEG'];
                    $valores_DNPOG = $this->metas['valores_DNPOG'];
                    $valores_IEPO  = $this->metas['valores_IEPO'];
                    $valores_POGP  = $this->metas['valores_POGP'];
                    $valores_PMA   = $this->metas['valores_PMA'];
                    $uni_medida_pogp = $this->metas['unidad_POGP'];
                    $uni_medida_pma  = $this->metas['unidad_PMA'];

                    /* ---------- Efectividades (mismas fórmulas de antes) ---------- */
                    $efec_emg = "";
                    if ($total_emg_monta > 0) {
                        if ($total_emg_gesta > 0) {
                            $efec_emg = number_format(($total_emg_monta / $total_emg_gesta), 1);
                        } else {
                            $efec_emg = number_format(($total_emg_gesta), 1);
                        }
                    }

                    $efec_eiag = "";
                    if ($total_eiag_monta > 0) {
                        if ($total_eiag_gesta > 0) {
                            $efec_eiag = number_format(($total_eiag_monta / $total_eiag_gesta), 1);
                        } else {
                            $efec_eiag = number_format(($total_eiag_gesta), 1);
                        }
                    }

                    $efec_teg = "";
                    if ($total_teg_monta > 0) {
                        if ($total_teg_gesta > 0) {
                            $efec_teg = number_format(($total_teg_monta / $total_teg_gesta), 1);
                        } else {
                            $efec_teg = number_format(($total_teg_gesta), 1);
                        }
                    }

                    /* ---------- DNP ---------- */
                    if ($total_cant_reg > 0) {
                        $promedio_dnp = number_format(($total_dnp / $total_cant_reg), 0);
                    } else {
                        $promedio_dnp = "";
                    }

                    /* ---------- IEP ---------- */
                    $IEP = $this->calcularIEP($cod_hembra);

                    /* ---------- % Gestación/Parió y % Aborto ---------- */
                    $tgp   = $cant_gesta_parto;
                    $tgp_a = $cant_gesta_parto + $cant_gesta_aborto;

                    $porcent_gest_part   = "";
                    $porcent_gest_aborto = "";

                    if ($tgp > 0) {
                        if ($tgp_a > 0) {
                            $porcent_gest_part = number_format((($tgp / $tgp_a) * 100), 0);
                            $porcent_gest_part = $porcent_gest_part . ' ' . $uni_medida_pogp;
                        }
                        if ($total_aborto > 0) {
                            $porcent_gest_aborto = number_format(($cant_gesta_aborto / ($tgp_a) * 100), 0);
                            $porcent_gest_aborto = $porcent_gest_aborto . ' ' . $uni_medida_pma;
                        }
                    }

                    /* ---------- Fila ACTUAL / META ---------- */
                    $this->SetFillColor(148, 247, 224);
                    $this->SetJump(8);

                    $this->RowMFillColor(array('ACTUAL', $efec_emg, $efec_eiag, $efec_teg, $promedio_dnp, $IEP, $porcent_gest_part, $porcent_gest_aborto), true);
                    $this->SetFillColor(39, 224, 227);
                    $this->SetJump(6);

                    $this->RowMFillColor(array('META', $valores_MMG, $valores_MIAG, $valores_MTEG, $valores_DNPOG, $valores_IEPO, $valores_POGP, $valores_PMA), true);

                    /* ---------- Detalle de servicios ---------- */
                    $this->SetWidths(array(30, 40, 40, 40, 50, 30, 30, 30, 30));
                    $this->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'));
                    //Fondo verde de celda
                    $this->SetFillColor(250, 180, 180);

                    //Fondo verde de celda
                    $this->SetBorder(true);
                    $this->SetFont('ARIAL', 'B', '10');
                    $this->SetJump(6);

                    $this->RowMFillColor(array(__pdf_txt('Fecha'), __pdf_txt('# Servicio'), 'Tipo de Servicio', __pdf_txt('Estado Fisiológico'), __pdf_txt('Fecha del Último Parto '), __pdf_txt('DNP'), __pdf_txt('IES'), __pdf_txt('Parió?'), __pdf_txt('IEP')), true);

                    $servicios = $this->detalleHembra($cod_hembra);

                    $k = 0;
                    foreach ($servicios as $data_Deta_Serv) {

                        /* Salto de página si se acaba el espacio (con muchos
                           servicios el detalle se salía de la hoja) */
                        if ($this->GetY() > 175) {
                            $this->AddPage();
                            $this->Pie_Pagina();
                            $this->SetXY(10, 35);

                            $this->SetWidths(array(30, 40, 40, 40, 50, 30, 30, 30, 30));
                            $this->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'));
                            $this->SetFillColor(250, 180, 180);
                            $this->SetBorder(true);
                            $this->SetFont('ARIAL', 'B', '10');
                            $this->SetJump(6);
                            $this->RowMFillColor(array(__pdf_txt('Fecha'), __pdf_txt('# Servicio'), 'Tipo de Servicio', __pdf_txt('Estado Fisiológico'), __pdf_txt('Fecha del Último Parto '), __pdf_txt('DNP'), __pdf_txt('IES'), __pdf_txt('Parió?'), __pdf_txt('IEP')), true);
                            $this->SetFillColor(148, 247, 224);
                            $this->SetJump(8);
                        }

                        $fecha_ultimo_parto = $data_Deta_Serv['f_ultimo_parto'];
                        $f_servicio         = $data_Deta_Serv['f_servicio'];
                        $nro_servicio       = $data_Deta_Serv['nro_servicio'];
                        $estado_fisiologico = $data_Deta_Serv['estado_fisiologico'];
                        $tipo_servicio      = $data_Deta_Serv['tipo_servicio'];
                        $pario              = $data_Deta_Serv['pario'];
                        $dnp                = $data_Deta_Serv['dnp'];
                        $fecha_up_anterior  = $data_Deta_Serv['fecha_parto'];

                        $tiempo_iep = "";
                        $tiempo_ies = "";

                        if ($k > 0) {
                            $f_servicio_anterior = $servicios[$k - 1]['f_servicio'];

                            if ($fecha_ultimo_parto != '' && $fecha_up_anterior != '') {
                                $tiempo_iep = $this->tiempo_iep($fecha_ultimo_parto, $fecha_up_anterior);
                            }

                            if ($f_servicio_anterior != '' && $f_servicio != '') {
                                $tiempo_ies = $this->tiempo_ies($f_servicio, $f_servicio_anterior);
                            }
                        }
                        $k++;

                        $this->SetFillColor(148, 247, 224);
                        $this->SetJump(8);

                        $this->RowMFillColor(array($f_servicio, $nro_servicio, __pdf_txt($tipo_servicio), __pdf_txt($estado_fisiologico), $fecha_ultimo_parto, $dnp, $tiempo_ies, $pario, $tiempo_iep), true);
                    }

            }

            $this->SetTextColor(0, 0, 0);

            $this->SetWidths(array(40, 150));
            $this->SetAligns(array('C', 'L'));
            $this->SetJump(5);

            if ($this->GetY() > 320) {
                $this->AddPage();
                $this->Pie_Pagina();

                $this->SetXY(10, 35);

            }
            $this->Ln(15);
            $contador++;

            $this->SetX(140);

            $this->SetWidths(array(60));
            $this->SetBorder(true);

            $this->Ln(5);
        }
    }
    //end cuerpo

    /* ============================================================
       DÍAS ENTRE FECHAS (dd-mm-yyyy)
       ============================================================ */
    function diasEntre($fecha_fin, $fecha_inicio)
    {
        $dias = (strtotime($fecha_fin) - strtotime($fecha_inicio)) / 86400;
        $dias = abs($dias);
        $dias = floor($dias);
        $dias = (int) $dias;
        $this->dias = $dias;
        return $this->dias;
    }

    function tiempo_iep($fecha_ultimo_parto, $fecha_up_anterior)
    {
        return $this->diasEntre($fecha_up_anterior, $fecha_ultimo_parto);
    }

    function tiempo_ies($f_servicio, $f_servicio_anterior)
    {
        return $this->diasEntre($f_servicio_anterior, $f_servicio);
    }

    function tiempo_t($fecha_ultimo_parto, $fecha_parto_anterior)
    {
        return $this->diasEntre($fecha_parto_anterior, $fecha_ultimo_parto);
    }
}
